# Bludit Membership Plugin

This plugin transforms a standard Bludit CMS site into a full-featured, private, members-only knowledge management system. It is a complete overhaul of the original 'Private Mode NG' plugin, rebuilt to provide a robust user registration and content protection system.

This project was built by Stephen and can be found on [GitHub](https://github.com/Oloh).

<br/>

## Features

- **Complete Privacy:** Turns the entire front-end of your Bludit site into a private area accessible only to logged-in members.
- **Separate Member System:** Implements a self-contained registration and login system that is completely separate from Bludit's admin accounts.
- **Domain-Restricted Registration:** Allows you to restrict new member registration to a specific email domain (e.g., `@yourcompany.com`), ensuring only your team can sign up.
- **Welcome Email:** Automatically sends a customizable welcome email to new members upon successful registration.
- **New Post Notifications:** Automatically sends an email notification to all registered members whenever a new article is published, keeping your team informed of new content.
- **Easy Configuration:** All settings, including enabling the plugin, allowing registration, and setting custom URLs, are managed from the plugin's configuration screen.

<br/>

## Requirement

- Bludit 3.x

<br/>

## Install

1.  Download the plugin files.
2.  Upload the main plugin folder to the `bl-plugins` directory of your Bludit installation.
3.  Go to the Bludit **Admin Panel**, navigate to **Settings > Plugins**, and activate the **Membership** plugin.

<br/>

## Configuration

Once the plugin is activated, you can configure it from the settings panel:

- **Enable/Disable:** Quickly turn the entire membership system on or off.
- **Allow Registration:** Open or close new member registration at any time.
- **Page Slugs:** Define the URLs (slugs) for your custom login, registration, and logout pages.

<br/>

## Screenshot

- Bludit Backend

![Membership](screenshot.png)

<br/>

## Copyright & License

**Membership Plugin** is open source software licensed under the [MIT license](https://opensource.org/licenses/MIT).

<br/>
