<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo $site->title(); ?></title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f4f4; }
        .container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); width: 300px; }
        h2 { text-align: center; color: #333; }
        input { width: 100%; padding: 0.8rem; margin-bottom: 1rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;}
        button { width: 100%; padding: 0.8rem; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .message { padding: 1rem; margin-bottom: 1rem; border-radius: 4px; text-align: center; }
        .error { background-color: #f8d7da; color: #721c24; }
        .success { background-color: #d4edda; color: #155724; }
        .register-link { text-align: center; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Member Login</h2>

        <?php if (isset($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>

        <div class="register-link">
             <p>Don't have an account? <a href="<?php echo DOMAIN_BASE . $this->getValue('registerPageSlug'); ?>">Register here</a></p>
        </div>
    </div>
</body>
</html>