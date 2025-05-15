# 🚀 PicoCMS: Single-File PHP Content Management System

![PicoCMS](https://img.shields.io/badge/PicoCMS-v0.1.0--alpha-blue.svg)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)
[![SQLite](https://img.shields.io/badge/SQLite-3-green.svg)](https://www.sqlite.org/index.html)
[![PicoCSS](https://img.shields.io/badge/PicoCSS-v2-orange.svg)](https://picocss.com/)

A lightweight, single-file PHP content management system with SQLite storage. Perfect for small websites, blogs, or prototypes. ✨

**⚠️ Caveat:** This CMS is currently experimental and under active development. Use with caution, especially in production environments.

## ✅ Features

- **Single-file architecture**: Entire CMS contained in one PHP file
- **Zero dependencies**: No external PHP or JS libraries required
- **SQLite database**: No complex database setup needed
- **Responsive design**: Using [PicoCSS](https://picocss.com/) for clean, minimal, classless styling
- **Content management**: Create and manage posts and pages with a simple admin interface
- **Contact form**: Built-in contact form with message management
- **SEO-friendly URLs**: Clean, customizable URLs for all content

## 🔧 Requirements

- PHP 7.4 or higher
- SQLite3 extension for PHP
- Write permissions for the web server in the installation directory

## 💻 Installation

1. **Prepare `index.php` (Crucial Security Step):**

   **Before uploading `index.php` to your web server, it is absolutely critical to change the default administrator credentials.** Open the `index.php` file and modify the following lines with your own secure username and strong password:

   ```php
   const ADMIN_USERNAME = 'admin'; // CHANGE THIS!
   const ADMIN_PASSWORD = 'admin'; // CHANGE THIS!
   ```

   **This step is vital for securing your CMS from unauthorized access from the moment it goes live.**

2. Upload the modified `index.php` to your web server.

3. Visit your website in a browser. The system will automatically perform the initial setup: ✨
   - Create the SQLite database file (`cms.sqlite`).
   - Set up the necessary database tables.
   - Download the primary CSS file (from the CDN specified in settings) and save it as `style.css`. If the primary CSS cannot be fetched, it will use `fallback.css` as a default.
   - Generate some starter content.

**If you did not change the admin credentials before the initial setup (step 1 & 2):**

- The default login will be:
  - Username: `admin`
  - Password: `admin`
- **You must log in immediately using these default credentials and then update `index.php` with a secure username and password as described in step 1.** Do this _before_ making your site publicly accessible or adding any significant content.

Access the admin area by navigating to `/login` (or your configured admin path if you change it later) to manage your site.

## 📝 Usage

### Admin Dashboard

Access the admin area by navigating to `/login`. After successful authentication, you will be directed to the admin dashboard (typically at `/admin`) to:

- Create and manage posts and pages
- Update site settings
- View and respond to contact form submissions

### Content Types

PicoCMS supports two content types:

- **Posts**: Blog articles or news items, displayed chronologically 📰
- **Pages**: Static content like "About Us" or "Contact", added to the main navigation 📄

### URL Structure

- Home: `/`
- Admin dashboard: `/admin`
- Settings: `/settings`
- Single post or page: `/post/slug`
- Contact form: `/contact`
- Login page: `/login`

## 🎨 Customization

### Site Settings

Manage basic site settings from the admin dashboard:

- Site title: The name of your website
- Site description: Short description used for search engines
- Theme Color: Select a theme color for the website (uses PicoCSS).

### Styling

PicoCMS uses [PicoCSS](https://picocss.com/) for styling. You can: 🎨

1. Select a theme color from the settings page. This will use the corresponding PicoCSS classless variant (e.g., `azure`, `green`, `yellow`). The system defaults to `azure`.
2. Keep the default classless styling if no specific theme color is chosen.
3. Link to your own custom CSS file by modifying the `theme_color` setting to a full URL (this is an advanced option).
4. Modify the auto-generated `style.css` file directly (changes might be overwritten if the theme color setting is updated).

## 📁 Directory Structure

```text
/
├── index.php       # The entire CMS application
├── cms.sqlite      # Database file (auto-generated, managed by the CMS)
├── style.css       # CSS styles (auto-generated from CDN or settings, with fallback.css as a backup)
├── pint.json       # Configuration file for Laravel Pint (PHP code style fixer for development)
├── fallback.css    # Default CSS file used if the primary CSS (from CDN/settings) cannot be fetched.
└── README.md       # This documentation file
```

## 💾 Database Schema

PicoCMS uses SQLite with the following tables:

### posts

- `id` (INTEGER): Primary key
- `title` (TEXT): Post title
- `content` (TEXT): Post content (supports basic HTML)
- `type` (TEXT): Content type ('post' or 'page')
- `slug` (TEXT): URL-friendly identifier
- `created_at` (DATETIME): Creation timestamp
- `updated_at` (DATETIME): Last update timestamp

### settings

- `name` (TEXT): Setting name (primary key)
- `value` (TEXT): Setting value

### messages

- `id` (INTEGER): Primary key
- `name` (TEXT): Sender's name
- `email` (TEXT): Sender's email
- `subject` (TEXT): Message subject
- `message` (TEXT): Message content
- `created_at` (DATETIME): Submission timestamp
- `is_read` (INTEGER): Read status (0=unread, 1=read)

## 💻 Development

This section provides guidance for developers looking to contribute to PicoCMS.

### Recommended VS Code Setup

For an optimal development experience with VS Code, consider the following:

1. **Debugger Configuration (`.vscode/launch.json`):**

   The project includes a `.vscode/launch.json` file configured to debug the application using PHP's built-in web server and Xdebug.

   - Ensure you installed and activated one of the [supported browser extensions](https://xdebug.org/docs/step_debug#browser-extensions).
   - Ensure you have the [PHP Debug](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug) extension by Xdebug installed in VS Code.
   - The provided launch configuration is named "Launch Built-in web server".
   - It uses `host.docker.internal` for `xdebug.client_host`. If you are not running PHP/Xdebug inside a Docker container, you might need to change this to `127.0.0.1` or your local machine's IP address.
   - To start debugging, open `index.php`, go to the "Run and Debug" view in VS Code (usually Ctrl+Shift+D or Cmd+Shift+D), select "Launch Built-in web server" from the dropdown, and press F5.

2. **Workspace Settings (`.vscode/settings.json`):**

   The `.vscode/settings.json` file includes a list of words for the [Code Spell Checker](https://marketplace.visualstudio.com/items?itemName=streetsidesoftware.code-spell-checker) extension to ignore. This helps maintain consistency and reduce false positives during spell checking.

   - Install the Code Spell Checker extension if you haven't already.
   - The settings will be applied automatically when you open the project in VS Code.
   - The current custom dictionary includes:

     ```json
     "cSpell.words": [
         "AUTOINCREMENT",
         "Pico",
         "TRANSLIT",
         "laravel",
         "Intelephense"
     ]
     ```

   - Feel free to add more project-specific terms to this list as needed.

### PHP Code Styling (`pint.json`)

This project uses [Laravel Pint](https://laravel.com/docs/pint) for ensuring consistent PHP code style. The configuration is defined in `pint.json`, which uses the `laravel` preset.

1. **Installation:**

   If you don't have Pint installed globally, you can install it as a dev dependency in the project (though this project aims for zero PHP dependencies for the core CMS, Pint is a development tool):

   ```bash
   composer require laravel/pint --dev
   ```

   Alternatively, you can download a [standalone build of Pint](https://github.com/laravel/pint/releases).

2. **Usage:**

   To format your code, run Pint from the project's root directory:

   ```bash
   # If installed via Composer
   ./vendor/bin/pint

   # If using a standalone build (e.g., in the current directory)
   ./pint
   ```

   It's recommended to run Pint before committing your changes.

## 🚀 Roadmap

Here are some of the planned features and improvements for PicoCMS:

- **Enhanced Security**:
  - [ ] Thorough security audit and hardening (e.g., XSS, SQLi, CSRF protection).
  - [ ] Implement rate limiting for forms.
- **Improved User Experience (UX)**:
  - [ ] Make the color picker easier to use (e.g., visual selection, live preview).
  - [ ] Add a WYSIWYG editor for content creation.
  - [ ] Better, more responsive design using classless PicoCMS.
  - [ ] Implement proper error handling.
- **Content & Customization**:
  - [ ] Improved media management (e.g., image uploads, gallery).
  - [ ] Multilingual support for content and admin interface.
- **Development & Deployment**:
  - [ ] Add CI (Continuous Integration) pipeline for automated testing.
  - [ ] Add CD (Continuous Deployment) options.
  - [ ] Add End-to-End tests
  - [ ] Deploy to production for demo purposes
- **Performance**:
  - [ ] Implement caching mechanisms for improved performance.
- **SEO**:
  - [ ] Auto-generate sitemap.xml.
  - [ ] More comprehensive and granular SEO settings per page/post.

This roadmap is subject to change, not necessarily in order of priorities, and contributions are welcome!

## 👥 Contributing

Contributions are welcome! Feel free to: 🤝

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is open-source and available under the MIT License.
