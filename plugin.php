<?php
class PrivateModeNgPlugin extends Plugin
{
    // A separate database file for members, stored in the plugin's workspace.
    private $memberDbFile;

    public function init()
    {
        // Set the path for our member database file.
        $this->memberDbFile = $this->workspace() . 'members.json';

        // Default settings for the plugin.
        $this->dbFields = array(
            'enable' => true,
            'allowRegistration' => true,
            'loginPageSlug' => 'member-login',
            'registerPageSlug' => 'member-register',
            'logoutPageSlug' => 'member-logout'
        );

        // Start a session for member authentication.
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    // ========== MEMBER AUTHENTICATION METHODS ==========

    /**
     * Checks if a member is logged in via the session.
     * @return bool
     */
    private function isMemberLoggedIn()
    {
        return !empty($_SESSION['member_logged_in']);
    }

    /**
     * Attempts to log a member in.
     * @param string $username
     * @param string $password
     * @return bool
     */
    private function loginMember($username, $password)
    {
        $members = $this->getMembers();
        if (isset($members[$username]) && password_verify($password, $members[$username]['password'])) {
            $_SESSION['member_logged_in'] = true;
            $_SESSION['member_username'] = $username;
            return true;
        }
        return false;
    }

    /**
     * Registers a new member.
     * @param string $username
     * @param string $password
     * @param string $email
     * @return bool
     */
    private function registerMember($username, $password, $email)
    {
        $members = $this->getMembers();
        if (isset($members[$username])) {
            return false; // User already exists
        }
        $members[$username] = [
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'email' => $email,
            'registered_at' => date('c')
        ];
        return file_put_contents($this->memberDbFile, json_encode($members, JSON_PRETTY_PRINT));
    }

    /**
     * Logs the member out.
     */
    private function logoutMember()
    {
        unset($_SESSION['member_logged_in']);
        unset($_SESSION['member_username']);
    }

    /**
     * Retrieves all members from the database file.
     * @return array
     */
    private function getMembers()
    {
        if (!file_exists($this->memberDbFile)) {
            // Create the file with an empty JSON object if it doesn't exist.
            file_put_contents($this->memberDbFile, '{}');
            return [];
        }
        $json = file_get_contents($this->memberDbFile);
        return json_decode($json, true) ?: [];
    }

    // ========== EMAIL HELPER ==========

    /**
     * Sends an email to a recipient.
     * @param string $to Recipient's email address.
     * @param string $subject The email subject.
     * @param string $message The HTML content of the email.
     * @return bool
     */
    private function sendEmail($to, $subject, $message)
    {
        global $site;
        // Use the site's title and a noreply address for the "From" header.
        $fromEmail = 'noreply@' . $site->domain();
        $fromName = $site->title();

        $headers = "From: " . $fromName . " <" . $fromEmail . ">\r\n";
        $headers .= "Reply-To: " . $fromEmail . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // Create a simple, clean HTML structure for the email.
        $htmlMessage = '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6;">
            <div style="max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h2 style="color: #333;">' . $site->title() . '</h2>
                <hr style="border: 0; border-top: 1px solid #eee;">
                ' . $message . '
                <hr style="border: 0; border-top: 1px solid #eee;">
                <p style="font-size: 0.8em; color: #777;">This is an automated message. Please do not reply directly to this email.</p>
            </div>
        </body>
        </html>';

        // Use the mail() function to send the email.
        return mail($to, $subject, $htmlMessage, $headers);
    }

    // ========== BLUDIT HOOKS ==========

    /**
     * This hook runs before any other part of the site loads.
     * It's perfect for routing and access control.
     */
    public function beforeAll()
    {
        // Only run if the plugin is enabled.
        if (!$this->getValue('enable')) {
            return;
        }

        global $url;
        $slug = $url->slug();
        $login = new Login(); // Bludit's admin login class

        // If the user is an admin, do not interfere with their session.
        if ($login->isLogged()) {
            return;
        }

        // --- Route Handling ---
        $loginSlug = $this->getValue('loginPageSlug');
        $registerSlug = $this->getValue('registerPageSlug');
        $logoutSlug = $this->getValue('logoutPageSlug');

        // If the user is not a logged-in member, redirect them to the login page.
        // We allow access to the login and registration pages themselves.
        if (!$this->isMemberLoggedIn() && !in_array($slug, [$loginSlug, $registerSlug])) {
            Redirect::url(DOMAIN_BASE . $loginSlug);
        }

        // Handle page requests for our custom pages.
        if ($slug === $loginSlug) {
            $this->handleLoginPage();
        } elseif ($slug === $registerSlug) {
            $this->handleRegisterPage();
        } elseif ($slug === $logoutSlug) {
            $this->handleLogoutPage();
        }
    }

    /**
     * Removes the plugin's custom pages from the main content list
     * to prevent them from showing up in search or archives.
     */
    public function beforeSiteLoad()
    {
        global $content;
        $loginSlug = $this->getValue('loginPageSlug');
        $registerSlug = $this->getValue('registerPageSlug');
        $logoutSlug = $this->getValue('logoutPageSlug');

        foreach ($content as $key => $page) {
            if (in_array($page->slug(), [$loginSlug, $registerSlug, $logoutSlug])) {
                unset($content[$key]);
            }
        }
    }
    
    public function afterPageCreate($page)
    {
        // This hook is called when a page is first created.
        // If it's published immediately, send notifications.
        if ($page->published()) {
            $this->notifyMembersOfNewPost($page);
        }
    }

    public function afterPageEdit($page)
    {
        // This hook is called after a page is edited.
        // We check if it is published to send notifications.
        // Note: This will send an email every time a published post is saved.
        if ($page->published()) {
            $this->notifyMembersOfNewPost($page);
        }
    }

    /**
     * Gathers all members and sends them a new post notification.
     * @param Page $page The Bludit Page object.
     */
    private function notifyMembersOfNewPost($page)
    {
        global $site;
        $members = $this->getMembers();
        $subject = "New Post Published: " . $page->title();

        // Create the email message with a link to the new post.
        $message = '
            <p>Hello,</p>
            <p>A new article has been published on ' . $site->title() . ':</p>
            <h3 style="margin-top:20px;"><a href="' . $page->permalink() . '" style="color: #007bff; text-decoration: none;">' . $page->title() . '</a></h3>
            <p><strong>Description:</strong> ' . $page->description() . '</p>
            <p><a href="' . $page->permalink() . '" style="display: inline-block; padding: 10px 15px; background-color: #007bff; color: #fff; text-decoration: none; border-radius: 5px;">Read Full Article</a></p>
        ';

        // Loop through all registered members and send them the email.
        foreach ($members as $member) {
            if (isset($member['email']) && filter_var($member['email'], FILTER_VALIDATE_EMAIL)) {
                $this->sendEmail($member['email'], $subject, $message);
            }
        }
    }


    // ========== PAGE HANDLERS ==========

    private function handleLoginPage()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = Sanitize::html($_POST['username'] ?? '');
            $password = Sanitize::html($_POST['password'] ?? '');

            if ($this->loginMember($username, $password)) {
                Redirect::url(DOMAIN_BASE); // Success, redirect to homepage
            } else {
                // Failed login, show an error message
                $this->renderTemplate('login', ['error' => 'Invalid username or password.']);
            }
        } else {
            $this->renderTemplate('login'); // Show the login form
        }
        exit; // Stop Bludit from processing further
    }

    private function handleRegisterPage()
    {
        if (!$this->getValue('allowRegistration')) {
             $this->renderTemplate('login', ['error' => 'Registration is currently disabled.']);
             exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = Sanitize::html($_POST['email'] ?? '');
            $username = Sanitize::html($_POST['username'] ?? '');
            $password = Sanitize::html($_POST['password'] ?? '');
            $requiredDomain = 'afripoli.org';

            // --- Validation ---
            if (empty($email) || empty($username) || empty($password)) {
                $this->renderTemplate('register', [
                    'error' => 'Email, Username, and Password are required.'
                ]);
            } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $this->renderTemplate('register', [
                    'error' => 'Please enter a valid email address.'
                ]);
            } elseif (!str_ends_with(strtolower($email), '@' . $requiredDomain)) {
                $this->renderTemplate('register', [
                    'error' => "Registration is only allowed for @{$requiredDomain} email addresses."
                ]);
            } elseif ($this->registerMember($username, $password, $email)) {
                // --- Send Welcome Email ---
                global $site;
                $welcomeSubject = "Welcome to " . $site->title();
                $welcomeMessage = "
                    <p>Hi " . Sanitize::html($username) . ",</p>
                    <p>Thank you for registering for the " . $site->title() . " knowledge base. Your account has been created successfully.</p>
                    <p>You can now log in using your credentials.</p>
                    <br>
                    <p>Regards,<br>The Team</p>
                ";
                $this->sendEmail($email, $welcomeSubject, $welcomeMessage);
                // --- End Welcome Email ---
                
                // Redirect to login page with a success message
                $this->renderTemplate('login', ['success' => 'Registration successful! A confirmation has been sent to your email.']);
            } else {
                $this->renderTemplate('register', ['error' => 'Username already taken. Please choose another.']);
            }
        } else {
            $this->renderTemplate('register'); // Show the registration form
        }
        exit; // Stop Bludit from processing further
    }

    private function handleLogoutPage()
    {
        $this->logoutMember();
        Redirect::url(DOMAIN_BASE . $this->getValue('loginPageSlug'));
    }

    /**
     * A helper function to render our HTML templates.
     * @param string $templateName
     * @param array $vars - Variables to pass to the template (e.g., error messages)
     */
    private function renderTemplate($templateName, $vars = [])
    {
        global $site;
        extract($vars); // Make variables available in the template
        header('Content-Type: text/html; charset=utf-8');
        include($this->phpPath() . 'templates' . DS . $templateName . '.php');
    }


    // ========== PLUGIN CONFIGURATION FORM ==========

    public function form()
    {
        global $L;
        $html = '';

        // Enable/Disable Plugin
        $html .= '<div><label>' . $L->get('Enable plugin') . '</label>';
        $html .= '<select name="enable">';
        $html .= '<option value="true" ' . ($this->getValue('enable') ? 'selected' : '') . '>' . $L->get('Enabled') . '</option>';
        $html .= '<option value="false" ' . (!$this->getValue('enable') ? 'selected' : '') . '>' . $L->get('Disabled') . '</option>';
        $html .= '</select></div>';

        // Allow Registration
        $html .= '<div><label>' . $L->get('Allow new member registration') . '</label>';
        $html .= '<select name="allowRegistration">';
        $html .= '<option value="true" ' . ($this->getValue('allowRegistration') ? 'selected' : '') . '>' . $L->get('Enabled') . '</option>';
        $html .= '<option value="false" ' . (!$this->getValue('allowRegistration') ? 'selected' : '') . '>' . $L->get('Disabled') . '</option>';
        $html .= '</select></div>';

        // Page Slugs Configuration
        $html .= '<div><label>' . $L->get('Login page slug') . '</label><input type="text" name="loginPageSlug" value="' . $this->getValue('loginPageSlug') . '"></div>';
        $html .= '<div><label>' . $L->get('Register page slug') . '</label><input type="text" name="registerPageSlug" value="' . $this->getValue('registerPageSlug') . '"></div>';
        $html .= '<div><label>' . $L->get('Logout page slug') . '</label><input type="text" name="logoutPageSlug" value="' . $this->getValue('logoutPageSlug') . '"></div>';

        return $html;
    }
}
?>