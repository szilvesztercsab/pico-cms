<?php

/**
 * Single File PHP CMS with SQLite and PicoCSS.
 *
 * @version 0.1.0
 * @license MIT
 * @link https://github.com/szilvesztercsab/picocms
 */

// If this is a request for a static asset like CSS or JS, serve it directly when using PHP's built-in server
if (php_sapi_name() == 'cli-server' && preg_match('/\.(css|js|png|jpg|jpeg|gif|ico)$/', $_SERVER['REQUEST_URI'])) {
  return false;
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('session.serialize_handler', 'php_serialize');

// Configuration
const DB_FILE = 'cms.sqlite';
const SITE_TITLE = 'PicoCMS';
const ADMIN_USERNAME = 'admin'; // CHANGE IT!
const ADMIN_PASSWORD = 'admin'; // CHANGE IT!
define('ADMIN_PASSWORD_HASH', password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT));
define('BASE_URL', get_base_url()); // Get the base URL for permalinks

// Default settings array with descriptions and options
$default_settings = [
  'site_title' => [
    'description' => 'The name of your website',
    'value' => SITE_TITLE,
  ],
  'site_description' => [
    'description' => 'Short description used for search engines',
    'value' => 'A simple PHP CMS with SQLite and PicoCSS.',
  ],
  'theme_color' => [ // Replaces 'css'
    'value' => 'azure', // Default color
    'description' => 'Select the theme color for the website (PicoCSS).',
    'options' => ['azure', 'red', 'pink', 'fuchsia', 'purple', 'violet', 'indigo', 'blue', 'cyan', 'jade', 'green', 'lime', 'yellow', 'amber', 'pumpkin', 'orange', 'sand'],
    'type' => 'select', // Hint for the settings page UI to render a select dropdown
  ],
];

/**
 * Main application function that handles the entire request lifecycle
 *
 * This function bootstraps the application, handling sessions, routing,
 * admin actions, login attempts, and rendering the appropriate page.
 *
 * @return void
 */
function run_application()
{
  // Start session
  session_start();

  // Parse the current request
  $route = parse_request_uri();
  $page = $route['page'];
  $action = $_GET['action'] ?? $route['action'] ?? '';

  // Preserve query params for actions that still use them
  if (isset($_GET['id'])) {
    $route['id'] = (int) $_GET['id'];
  }
  if (isset($_GET['slug'])) {
    $route['slug'] = $_GET['slug'];
  }

  // Get login error if applicable
  $login_error = null;

  // Handle admin actions
  if (is_logged_in() && $action) {
    handle_admin_actions($action, $route);
  }

  // Handle login
  if ($page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_error = handle_login();
  }

  // Get site settings
  $settings = get_site_settings();

  // Ensure CSS file exists
  ensure_css_file_exists($settings);

  // Output buffering to capture content
  ob_start();

  // Handle page request
  handle_page_request($page, $route, $settings, $login_error);

  // Get content and display page with layout
  $content = ob_get_clean();
  include_template('layout', [
    'content' => $content,
    'settings' => $settings,
    'page' => $page,
    'route' => $route,
  ]);
}

/**
 * Constructs the PicoCSS CDN URL for a given theme color.
 *
 * @param string $theme_color The desired theme color.
 * @return string The CDN URL for the CSS file.
 */
function get_pico_css_url($theme_color)
{
  $base_url = 'https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.classless';
  if (empty($theme_color) || $theme_color === 'azure' || $theme_color === 'default') { // 'default' or 'azure' for the standard URL
    return $base_url . '.min.css';
  }

  return $base_url . '.' . $theme_color . '.min.css';
}

/**
 * Ensures the CSS file exists and creates it if not
 *
 * @param array $settings Application settings
 * @return void
 */
function ensure_css_file_exists($settings)
{
  $css_file = 'style.css';
  if (! file_exists($css_file)) {
    $selected_theme = $settings['theme_color'] ?? 'azure'; // Default to azure if not set
    $css_url = get_pico_css_url($selected_theme);

    $css_content = @file_get_contents($css_url);

    if ($css_content === false) {
      error_log("Failed to fetch CSS from URL: $css_url for theme $selected_theme. Attempting fallback.css.");
      // Attempt to load a local fallback CSS file if the CDN fails
      $fallback_css_path = 'fallback.css'; // Ensure this file exists in your project root
      $css_content = @file_get_contents($fallback_css_path);
      if ($css_content === false) {
        error_log('Failed to fetch fallback.css. CSS will be missing or minimal.');
        $css_content = '/* PicoCMS: CSS failed to load from CDN and fallback.css was not found. */';
      }
    }

    if (file_put_contents($css_file, $css_content) === false) {
      error_log("Failed to write CSS to file: $css_file");
    }
  }
}

/**
 * Handle all admin actions based on the specified action and route
 *
 * Processes administrative actions like creating, editing, deleting posts,
 * managing settings, handling messages, and user logout.
 *
 * @param string $action The admin action to perform
 * @param array $route The current route information
 * @return void
 */
function handle_admin_actions($action, $route)
{
  $db = get_db();

  switch ($action) {
    case 'new_post':
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        create_or_update_post();
        redirect('admin');
      }
      break;

    case 'edit_post':
      $id = (int) $route['id'];
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        create_or_update_post($id);
        redirect('admin');
      }
      break;

    case 'delete_post':
      $id = (int) $route['id'];
      $stmt = $db->prepare('DELETE FROM posts WHERE id = :id');
      $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
      $stmt->execute();
      redirect('admin');
      break;

    case 'save_settings':
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        save_settings($_POST);
        redirect('settings');
      }
      break;

    case 'logout':
      session_destroy();
      redirect('');
      break;

    case 'view_message':
      $id = (int) $route['id'];
      $message = get_message_by_id($id);

      // Mark as read if not already
      if ($message && ! $message['is_read']) {
        $stmt = $db->prepare('UPDATE messages SET is_read = 1 WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        $message['is_read'] = 1;
      }

      include_template('view_message', ['message' => $message]);
      exit;

    case 'delete_message':
      $id = (int) $route['id'];
      $stmt = $db->prepare('DELETE FROM messages WHERE id = :id');
      $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
      $stmt->execute();
      redirect('admin');
      break;
  }
}

/**
 * Helper function to redirect to a URL
 *
 * @param string $path The path to redirect to
 * @return void
 */
function redirect($path)
{
  header('Location: ' . url($path));
  exit;
}

/**
 * Create or update a post from form data
 *
 * @param int|null $id Post ID for updates, null for new posts
 * @return void
 */
function create_or_update_post($id = null)
{
  $db = get_db();

  // Get inputs
  $title = trim($_POST['title']);
  $content = $_POST['content'];
  $type = $_POST['type'];
  $slug = slugify($_POST['slug'] ?: $_POST['title']);

  if ($id) {
    // Update existing post
    $stmt = $db->prepare('UPDATE posts
                          SET title = :title,
                              content = :content, 
                              type = :type,
                              slug = :slug,
                              updated_at = CURRENT_TIMESTAMP 
                          WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':content', $content, SQLITE3_TEXT);
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
    $stmt->execute();
  } else {
    // Create new post
    $stmt = $db->prepare('INSERT INTO posts (title, content, type, slug) 
                          VALUES (:title, :content, :type, :slug)');
    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':content', $content, SQLITE3_TEXT);
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
    $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
    $stmt->execute();
  }
}

/**
 * Save settings from form data
 *
 * @param array $form_data Form data from $_POST
 * @return void
 */
function save_settings($form_data)
{
  $db = get_db();
  global $default_settings;

  // Update settings
  $stmt = $db->prepare('INSERT OR REPLACE INTO settings (value, name) VALUES (:value, :name)');

  foreach ($form_data as $key => $value) {
    if (isset($default_settings[$key])) {
      $value_to_save = trim($value);
      $stmt->bindValue(':name', $key, SQLITE3_TEXT);
      $stmt->bindValue(':value', $value_to_save, SQLITE3_TEXT);
      $stmt->execute();
      $stmt->reset();

      // Update CSS file if theme_color setting changes
      if ($key === 'theme_color') {
        $selected_color = $value_to_save;
        if (! empty($selected_color)) {
          $css_url = get_pico_css_url($selected_color);
          try {
            $css_content = @file_get_contents($css_url);
            if ($css_content !== false) {
              if (file_put_contents('style.css', $css_content) === false) {
                error_log("Failed to write updated CSS to style.css for theme $selected_color.");
              }
            } else {
              error_log("Failed to fetch CSS from URL: $css_url for theme $selected_color during save_settings. style.css not updated.");
            }
          } catch (Exception $e) {
            error_log("Error updating CSS file for theme $selected_color: " . $e->getMessage());
          }
        }
      }
    }
  }
}

/**
 * Handle login requests and authenticate users
 *
 * Verifies submitted username and password against configured admin credentials.
 * If successful, sets session login state and redirects to admin page.
 *
 * @return string|null Returns error message on failed login, null on success
 */
function handle_login()
{
  if ($_POST['username'] === ADMIN_USERNAME && password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
    $_SESSION['logged_in'] = true;
    header('Location: ' . url('admin'));
    exit;
  }

  return 'Invalid username or password';
}

/**
 * Handle page requests based on the current page
 *
 * Determines which template to load based on the requested page and
 * prepares any necessary data for the template.
 *
 * @param string $page The requested page
 * @param array $route The route information
 * @param array $settings The site settings
 * @param string|null $login_error Login error message, if any
 * @return void
 */
function handle_page_request($page, $route, $settings, $login_error = null)
{
  $common_data = [
    'settings' => $settings,
    'page' => $page,
    'route' => $route,
  ];

  switch ($page) {
    case 'login':
      include_template('login', array_merge(['login_error' => $login_error], $common_data));
      break;

    case 'admin':
      require_login();
      $data = array_merge([
        'unread_count' => get_unread_messages_count(),
        'messages' => get_all_messages(),
        'pages' => get_all_posts('page'),
        'posts' => get_all_posts('post'),
      ], $common_data);
      include_template('admin', $data);
      break;

    case 'new':
      require_login();
      include_template('edit_post', array_merge(['is_new' => true], $common_data));
      break;

    case 'edit':
      require_login();
      $id = (int) $route['id'];
      $post = get_post_by_id($id);
      if (! $post) {
        include_template('404', $common_data);
        break;
      }
      include_template('edit_post', array_merge(['post' => $post, 'is_new' => false], $common_data));
      break;

    case 'settings':
      require_login();
      include_template('settings', $common_data);
      break;

    case 'post':
      $slug = $route['slug'] ?? '';
      $post = get_post_by_slug($slug);
      if ($post) {
        include_template('single', array_merge(['post' => $post], $common_data));
      } else {
        include_template('404', $common_data);
      }
      break;

    case 'contact':
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handle_contact_submission();
        include_template('contact', array_merge(['success' => true], $common_data));
      } else {
        include_template('contact', $common_data);
      }
      break;

    case 'home':
    default:
      $data = array_merge(['posts' => get_all_posts('post')], $common_data);
      include_template('home', $data);
      break;
  }
}

/**
 * Process and store contact form submissions
 *
 * Sanitizes form input and saves the message to the database.
 *
 * @return bool True if message was saved successfully
 */
function handle_contact_submission()
{
  $db = get_db();

  // Get and clean inputs
  $name = trim($_POST['name']);
  $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
  $subject = trim($_POST['subject']);
  $message = trim($_POST['message']);

  // Validate required fields
  if (empty($name) || empty($email) || empty($subject) || empty($message)) {
    return false;
  }

  $stmt = $db->prepare('INSERT INTO messages (name, email, subject, message) 
                       VALUES (:name, :email, :subject, :message)');
  $stmt->bindValue(':name', $name, SQLITE3_TEXT);
  $stmt->bindValue(':email', $email, SQLITE3_TEXT);
  $stmt->bindValue(':subject', $subject, SQLITE3_TEXT);
  $stmt->bindValue(':message', $message, SQLITE3_TEXT);
  $result = $stmt->execute();

  return $result !== false;
}

/**
 * Retrieve all system settings from the database
 *
 * @return array Associative array of setting name => value pairs
 */
function get_site_settings()
{
  $db = get_db();
  $settings_query = $db->query('SELECT * FROM settings');
  $settings = [];
  while ($row = $settings_query->fetchArray(SQLITE3_ASSOC)) {
    $settings[$row['name']] = $row['value'];
  }

  return $settings;
}

/**
 * Get the base URL for the current request
 *
 * Determines the base URL of the application by examining server variables,
 * including protocol, host, and script path.
 *
 * @return string The base URL without trailing slash
 */
function get_base_url()
{
  $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]";
  $script_name = dirname($_SERVER['SCRIPT_NAME']);
  if ($script_name != '/' && $script_name != '\\') {
    $base_url .= $script_name;
  }

  return rtrim($base_url, '/');
}

/**
 * Generate a URL for the given path and query parameters
 *
 * @param string $path The path component of the URL
 * @param array $params Optional query parameters
 * @return string The complete URL
 */
function url($path = '', $params = [])
{
  $url = BASE_URL . '/' . ltrim($path, '/');

  if (! empty($params)) {
    $url .= '?' . http_build_query($params);
  }

  return $url;
}

/**
 * Initialize the SQLite database and create tables if they don't exist
 *
 * Creates the necessary tables for posts, settings, and messages.
 * Populates default settings and sample content if the database is empty.
 *
 * @return \SQLite3 Database connection
 */
function init_db()
{
  global $default_settings;
  $db = new SQLite3(DB_FILE);

  // Create tables if they don't exist
  $db->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT 'post',
            slug TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

  $db->exec('
        CREATE TABLE IF NOT EXISTS settings (
            name TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )
    ');

  // Add new messages table for contact form
  $db->exec('
        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_read INTEGER DEFAULT 0
        )
    ');

  // Initialize default settings if they don't exist
  $settings = $db->query('SELECT COUNT(*) as count FROM settings');
  $row = $settings->fetchArray(SQLITE3_ASSOC);

  if ($row['count'] == 0) {
    $stmt = $db->prepare('INSERT INTO settings (name, value) VALUES (:name, :value)');

    foreach ($default_settings as $key => $setting) {
      $stmt->bindValue(':name', $key, SQLITE3_TEXT);
      $stmt->bindValue(':value', $setting['value'], SQLITE3_TEXT);
      $stmt->execute();
      $stmt->reset();
    }

    // Create sample posts and pages
    $posts = [
      [
        'title' => 'Hello World',
        'content' => "Hello World! <br /> How Are you?",
        'type' => 'post',
        'slug' => 'hello-world',
      ],
      [
        'title' => 'Another Post',
        'content' => "This is another post.",
        'type' => 'post',
        'slug' => 'another-post',
      ],
      [
        'title' => 'Welcome',
        'content' => "Welcome to the {$default_settings['site_title']['value']}!",
        'type' => 'page',
        'slug' => 'welcome',
      ],
    ];
    foreach ($posts as $post) {
      $stmt = $db->prepare('INSERT INTO posts (title, content, type, slug) VALUES (:title, :content, :type, :slug)');
      $stmt->bindValue(':title', $post['title'], SQLITE3_TEXT);
      $stmt->bindValue(':content', $post['content'], SQLITE3_TEXT);
      $stmt->bindValue(':type', $post['type'], SQLITE3_TEXT);
      $stmt->bindValue(':slug', $post['slug'], SQLITE3_TEXT);
      $stmt->execute();
      $stmt->reset();
    }

    // Create a sample message
    $stmt = $db->prepare('INSERT INTO messages (name, email, subject, message) VALUES (:name, :email, :subject, :message)');
    $stmt->bindValue(':name', 'John Doe', SQLITE3_TEXT);
    $stmt->bindValue(':email', 'john.doe@example.com', SQLITE3_TEXT);
    $stmt->bindValue(':subject', 'Test Message', SQLITE3_TEXT);
    $stmt->bindValue(':message', 'This is a test message.', SQLITE3_TEXT);
    $stmt->execute();
    $stmt->reset();
  }

  return $db;
}

/**
 * Get or initialize the database connection
 *
 * Returns an existing database connection or creates a new one if none exists.
 * Uses a static variable to maintain a single connection across multiple calls.
 *
 * @return \SQLite3 The database connection
 */
function get_db()
{
  static $db = null;
  if ($db === null) {
    $db = init_db();
  }

  return $db;
}

/**
 * Convert text to a URL-friendly slug
 *
 * Transforms arbitrary text into a URL-safe string by removing special characters,
 * replacing spaces with hyphens, and converting to lowercase.
 *
 * @param string $text The text to convert to a slug
 * @return string The generated slug
 */
function slugify($text)
{
  $text = preg_replace('~[^\pL\d]+~u', '-', $text);
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  $text = preg_replace('~[^-\w]+~', '', $text);
  $text = trim($text, '-');
  $text = preg_replace('~-+~', '-', $text);
  $text = strtolower($text);

  return $text ?: 'n-a';
}

/**
 * Check if the current user is logged in
 *
 * @return bool True if the user is logged in, false otherwise
 */
function is_logged_in()
{
  return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Require user to be logged in, redirect to login page if not
 *
 * @return void
 */
function require_login()
{
  if (! is_logged_in()) {
    header('Location: ' . url('login'));
    exit;
  }
}

/**
 * Parse the requested URI to determine the current route
 *
 * Examines the current request URI and extracts route parameters
 * such as page, action, slug, and ID.
 *
 * @return array Route information containing page, action, slug, and ID
 */
function parse_request_uri()
{
  $uri = $_SERVER['REQUEST_URI'];

  // Remove the query string
  if (($pos = strpos($uri, '?')) !== false) {
    $uri = substr($uri, 0, $pos);
  }

  // Remove the base path from the URI
  $script_name = dirname($_SERVER['SCRIPT_NAME']);
  if ($script_name != '/' && $script_name != '\\') {
    $uri = substr($uri, strlen($script_name));
  }

  // Split the URI into parts
  $uri = trim($uri, '/');
  $parts = $uri ? explode('/', $uri) : [];

  // Default route
  $route = [
    'page' => 'home',
    'action' => '',
    'slug' => '',
    'id' => null,
  ];

  // First part is the page or action
  if (! empty($parts[0])) {
    $route['page'] = $parts[0];

    // Special case for post and edit pages
    if ($parts[0] === 'post' && ! empty($parts[1])) {
      $route['slug'] = $parts[1];
    } elseif ($parts[0] === 'edit' && ! empty($parts[1])) {
      $route['id'] = (int) $parts[1];
    } elseif ($parts[0] === 'admin' && ! empty($parts[1])) {
      $route['action'] = $parts[1];
      if (! empty($parts[2])) {
        $route['id'] = (int) $parts[2];
      }
    }
  }

  return $route;
}

/**
 * Get a post by its ID
 *
 * @param int $id The post ID to retrieve
 * @return array|false Post data as associative array or false if not found
 */
function get_post_by_id($id)
{
  $db = get_db();
  $stmt = $db->prepare('SELECT * FROM posts WHERE id = :id');
  $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
  $result = $stmt->execute();

  return $result->fetchArray(SQLITE3_ASSOC);
}

/**
 * Get a post by its slug
 *
 * @param string $slug The post slug to retrieve
 * @return array|false Post data as associative array or false if not found
 */
function get_post_by_slug($slug)
{
  $db = get_db();
  $stmt = $db->prepare('SELECT * FROM posts WHERE slug = :slug');
  $stmt->bindValue(':slug', $slug, SQLITE3_TEXT);
  $result = $stmt->execute();

  return $result->fetchArray(SQLITE3_ASSOC);
}

/**
 * Get all posts, optionally filtered by type
 *
 * @param string|null $type Optional post type filter ('post' or 'page')
 * @return array Array of posts as associative arrays
 */
function get_all_posts($type = null)
{
  $db = get_db();

  if ($type) {
    $stmt = $db->prepare('SELECT * FROM posts WHERE type = :type ORDER BY created_at DESC');
    $stmt->bindValue(':type', $type, SQLITE3_TEXT);
  } else {
    $stmt = $db->prepare('SELECT * FROM posts ORDER BY created_at DESC');
  }

  $result = $stmt->execute();

  $posts = [];
  while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $posts[] = $row;
  }

  return $posts;
}

/**
 * Get all messages, optionally limited to a specific count
 *
 * @param int|null $limit Optional maximum number of messages to return
 * @return array Array of messages as associative arrays
 */
function get_all_messages($limit = null)
{
  $db = get_db();
  $sql = 'SELECT * FROM messages ORDER BY created_at DESC';

  if ($limit) {
    $sql .= ' LIMIT ' . (int) $limit;
  }

  $result = $db->query($sql);

  $messages = [];
  while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $messages[] = $row;
  }

  return $messages;
}

/**
 * Get a message by its ID
 *
 * @param int $id The message ID to retrieve
 * @return array|false Message data as associative array or false if not found
 */
function get_message_by_id($id)
{
  $db = get_db();
  $stmt = $db->prepare('SELECT * FROM messages WHERE id = :id');
  $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
  $result = $stmt->execute();

  return $result->fetchArray(SQLITE3_ASSOC);
}

/**
 * Count the number of unread messages
 *
 * @return int Number of unread messages
 */
function get_unread_messages_count()
{
  $db = get_db();
  $result = $db->query('SELECT COUNT(*) as count FROM messages WHERE is_read = 0');
  $row = $result->fetchArray(SQLITE3_ASSOC);

  return $row['count'];
}

/**
 * Render a template with the provided data
 *
 * Loads and renders a template file, making the provided data available
 * as local variables within the template's scope.
 *
 * @param string $name Template name to include
 * @param array $data Data to extract and make available to the template
 * @return void
 */
function include_template($name, $data = [])
{
  extract($data);
  global $default_settings;

  // Common variables
  $site_title = $settings['site_title'] ?? $default_settings['site_title']['value'];
  $site_description = $settings['site_description'] ?? $default_settings['site_description']['value'];

  switch ($name) {
    case 'layout': ?>
      <!DOCTYPE html>
      <html lang="en">

      <head>
        <title><?= $site_title ?></title>
        <meta name="description" content="<?= $site_description ?>">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="<?= url('style.css') ?>">
        <script>
          const progressIndicatorSelector = 'body > progress';

          const hideProgressIndicator = () =>
            document.querySelector(progressIndicatorSelector).style.visibility = 'hidden';

          const showProgressIndicator = () =>
            document.querySelector(progressIndicatorSelector).style.visibility = 'visible';

          document.addEventListener('DOMContentLoaded', hideProgressIndicator);
          window.addEventListener('beforeunload', showProgressIndicator);
          window.onunload = function() {};
        </script>
      </head>

      <body>

        <progress></progress>

        <?php if (is_logged_in()): ?>
          <header>
            <nav>
              <ul>
                <li><a href="<?= url('admin') ?>" <?= $page === 'admin' ? 'aria-current="page"' : '' ?>>Admin Dashboard</a></li>
                <li><a href="<?= url('settings') ?>" <?= $page === 'settings' ? 'aria-current="page"' : '' ?>>Settings</a></li>
                <li><a href="<?= url('admin/logout') ?>">Logout</a></li>
              </ul>
            </nav>
          </header>
        <?php endif; ?>

        <header>
          <nav>
            <ul>
              <li>
                <a href="<?= url() ?>"><?= $site_title ?></a>
              </li>
            </ul>
            <ul>
              <?php foreach (get_all_posts('page') as $page_item): ?>
                <li>
                  <a href="<?= url('post/' . $page_item['slug']) ?>" <?= ($page === 'post' && $route['slug'] === $page_item['slug']) ? 'aria-current="page"' : '' ?>>
                    <?= $page_item['title'] ?>
                  </a>
                </li>
              <?php endforeach; ?>
              <li><a href="<?= url('contact') ?>" <?= $page === 'contact' ? 'aria-current="page"' : '' ?>>Contact</a></li>
            </ul>
          </nav>
        </header>

        <main>
          <?= $content ?>
        </main>

        <footer>
          <small>&copy; <?= date('Y') ?> <?= $site_title ?></small>
        </footer>
      </body>

      </html>
    <?php break;

    case 'login': ?>
      <section>
        <hgroup>
          <h2>Login</h2>
          <p>Enter your credentials to access the admin area</p>
        </hgroup>
        <?php if (isset($login_error)): ?>
          <p role="alert"><?= $login_error ?></p>
        <?php endif; ?>
        <form method="post">
          <fieldset>
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autocomplete="username">
          </fieldset>
          <fieldset>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
          </fieldset>
          <button type="submit">Login</button>
        </form>
      </section>
    <?php break;

    case 'admin': ?>
      <section>
        <header>
          <h2>Admin Dashboard</h2>
          <p><a href="<?= url('new') ?>" role="button">Create New Post</a></p>
        </header>
        <section>
          <header>
            <h3>Messages <?= $unread_count ? "($unread_count unread)" : '' ?></h3>
          </header>
          <?php if (! empty($messages)): ?>
            <table>
              <thead>
                <tr>
                  <th scope="col">Date</th>
                  <th scope="col">Name</th>
                  <th scope="col">Subject</th>
                  <th scope="col">Status</th>
                  <th scope="col">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($messages as $message): ?>
                  <tr>
                    <td><time
                        datetime="<?= date('Y-m-d', strtotime($message['created_at'])) ?>"><?= date('Y-m-d', strtotime($message['created_at'])) ?></time>
                    </td>
                    <td><?= htmlspecialchars($message['name']) ?></td>
                    <td><?= htmlspecialchars($message['subject']) ?></td>
                    <td><?= $message['is_read'] ? 'Read' : '<mark>Unread</mark>' ?></td>
                    <td>
                      <a href="<?= url('admin/view_message/' . $message['id']) ?>">View</a> |
                      <a href="<?= url('admin/delete_message/' . $message['id']) ?>"
                        onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p>No messages yet.</p>
          <?php endif; ?>
        </section>
        <section>
          <header>
            <h3>Pages</h3>
          </header>
          <?php render_post_table($pages, 'page'); ?>
        </section>
        <section>
          <header>
            <h3>Posts</h3>
          </header>
          <?php render_post_table($posts, 'post'); ?>
        </section>
      </section>
    <?php break;

    case 'edit_post': ?>
      <section>
        <header>
          <h2><?= $is_new ? 'Create New Post' : 'Edit Post' ?></h2>
        </header>
        <form method="post" action="<?= $is_new ? url('admin/new_post') : url('admin/edit_post/' . $post['id']) ?>"
          id="postForm">
          <fieldset>
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= $is_new ? '' : htmlspecialchars($post['title']) ?>"
              required>
          </fieldset>
          <fieldset>
            <label for="slug">Slug</label>
            <input type="text" id="slug" name="slug" value="<?= $is_new ? '' : htmlspecialchars($post['slug']) ?>"
              placeholder="URL-friendly version of the title">
            <small>Leave empty to generate from title</small>
          </fieldset>
          <fieldset>
            <label for="type">Type</label>
            <select id="type" name="type">
              <option value="post" <?= (! $is_new && $post['type'] == 'post') ? 'selected' : '' ?>>Post</option>
              <option value="page" <?= (! $is_new && $post['type'] == 'page') ? 'selected' : '' ?>>Page</option>
            </select>
          </fieldset>
          <fieldset>
            <label for="content">Content</label>
            <textarea id="content" name="content"><?= $is_new ? '' : htmlspecialchars($post['content']) ?></textarea>
            <small>Basic HTML formatting is supported (headings, links, lists, etc).</small>
          </fieldset>
          <div role="group">
            <button type="submit">Save</button>
            <a href="<?= url('admin') ?>" type="reset">Cancel</a>
          </div>
        </form>
        <script>
          // Optional: Auto-generate slug from title if slug is empty
          document.getElementById('title').addEventListener('blur', function() {
            var slugField = document.getElementById('slug');
            if (slugField.value === '') {
              var title = this.value;
              // Simple slug generation - a more comprehensive function exists in PHP
              var slug = title.toLowerCase()
                .replace(/[^\w\s-]/g, '') // Remove special chars
                .replace(/\s+/g, '-') // Replace spaces with -
                .replace(/-+/g, '-'); // Replace multiple - with single -
              slugField.value = slug;
            }
          });
        </script>
      </section>
    <?php break;

    case 'settings': ?>
      <section>
        <header>
          <h2>Site Settings</h2>
          <p>Configure your website settings</p>
        </header>
        <form method="post" action="<?= url('admin/save_settings') ?>">
          <?php foreach ($default_settings as $key => $setting): ?>
            <fieldset>
              <?php $current_value = $settings[$key] ?? $setting['value']; ?>
              <label for="<?= $key ?>"><?= ucwords(str_replace('_', ' ', $key)) ?></label>
              <input type="text" id="<?= $key ?>" name="<?= $key ?>" value="<?= htmlspecialchars($current_value) ?>"
                <?= $key == 'site_title' ? 'required' : '' ?>>
              <?php if (isset($setting['description'])): ?>
                <small><?= $setting['description'] ?></small>
              <?php endif; ?>
            </fieldset>
          <?php endforeach; ?>
          <div role="group">
            <button type="submit">Save Settings</button>
            <a href="<?= url('admin') ?>" type="reset">Cancel</a>
          </div>
        </form>
      </section>
    <?php break;

    case 'home': ?>
      <section>
        <header>
          <h2>Latest Posts</h2>
        </header>
        <?php $posts = get_all_posts('post'); ?>
        <?php if (count($posts) > 0): ?>
          <?php foreach ($posts as $post): ?>
            <article>
              <header>
                <h3><a href="<?= url('post/' . $post['slug']) ?>"><?= $post['title'] ?></a></h3>
                <small>
                  <time datetime="<?= date('Y-m-d', strtotime($post['created_at'])) ?>">
                    Posted on <?= date('F j, Y', strtotime($post['created_at'])) ?>
                  </time>
                </small>
              </header>
              <p>
                <?= substr(strip_tags($post['content']), 0, 200) ?>...
              </p>
              <footer>
                <a href="<?= url('post/' . $post['slug']) ?>" aria-label="Read more about <?= $post['title'] ?>">Read More</a>
              </footer>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <p>No posts yet.</p>
        <?php endif; ?>
      </section>
    <?php break;

    case 'single': ?>
      <article>
        <header>
          <h1><?= $post['title'] ?></h1>
          <?php if ($post['type'] == 'post'): ?>
            <small>
              <time datetime="<?= date('Y-m-d', strtotime($post['created_at'])) ?>">
                Posted on <?= date('F j, Y', strtotime($post['created_at'])) ?>
              </time>
            </small>
          <?php endif; ?>
        </header>
        <div>
          <?= $post['content'] ?>
        </div>
        <?php if ($post['type'] == 'post'): ?>
          <footer>
            <nav aria-label="Post navigation">
              <!-- In a future version, you could add prev/next post links here -->
              <a href="<?= url() ?>">← Back to all posts</a>
            </nav>
          </footer>
        <?php endif; ?>
      </article>
    <?php break;

    case 'contact': ?>
      <section>
        <header>
          <h2>Contact Us</h2>
          <p>Send us a message and we'll get back to you soon.</p>
        </header>
        <?php if (isset($success) && $success): ?>
          <div role="alert">
            <p>Thank you! Your message has been sent successfully.</p>
            <p><a href="<?= url() ?>">Return to home page</a></p>
          </div>
        <?php else: ?>
          <form method="post">
            <fieldset>
              <label for="name">Name</label>
              <input type="text" id="name" name="name" required>
            </fieldset>
            <fieldset>
              <label for="email">Email</label>
              <input type="email" id="email" name="email" required>
            </fieldset>
            <fieldset>
              <label for="subject">Subject</label>
              <input type="text" id="subject" name="subject" required>
            </fieldset>
            <fieldset>
              <label for="message">Message</label>
              <textarea id="message" name="message" required></textarea>
            </fieldset>
            <div role="group">
              <button type="submit">Send Message</button>
              <a href="<?= url() ?>" type="reset">Cancel</a>
            </div>
          </form>
        <?php endif; ?>
      </section>
    <?php break;

    case 'view_message': ?>
      <section>
        <header>
          <h2>Message: <?= htmlspecialchars($message['subject']) ?></h2>
          <p>
            From <?= htmlspecialchars($message['name']) ?>
            (<a href="mailto:<?= htmlspecialchars($message['email']) ?>"><?= htmlspecialchars($message['email']) ?></a>)
            on <?= date('F j, Y', strtotime($message['created_at'])) ?>
          </p>
        </header>
        <div>
          <?= nl2br(htmlspecialchars($message['message'])) ?>
        </div>
        <div role="group">
          <a href="<?= url('admin') ?>" role="button">Back to Admin</a>
          <a href="<?= url('admin/delete_message/' . $message['id']) ?>" role="button" data-theme="contrast"
            onclick="return confirm('Are you sure you want to delete this message?')">
            Delete Message
          </a>
        </div>
      </section>
    <?php break;

    case '404': ?>
      <article>
        <header>
          <h1>Page Not Found</h1>
        </header>
        <p>Sorry, the page you requested could not be found.</p>
        <footer>
          <a href="<?= url() ?>" role="button">Return to Home Page</a>
        </footer>
      </article>
  <?php break;
  }
}

/**
 * Renders a table of posts for the admin dashboard
 *
 * @param array $posts Array of posts to display
 * @param string $type Type of posts ('post' or 'page')
 * @return void
 */
function render_post_table($posts, $type)
{
  $show_date = $type === 'post'; ?>
  <table>
    <thead>
      <tr>
        <th scope="col">Title</th>
        <th scope="col">Slug</th>
        <?php if ($show_date): ?>
          <th scope="col">Date</th>
        <?php endif; ?>
        <th scope="col">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($posts)): ?>
        <tr>
          <td colspan="<?= $show_date ? 4 : 3 ?>">No <?= $type ?>s found.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($posts as $post): ?>
          <tr>
            <td><?= htmlspecialchars($post['title']) ?></td>
            <td><?= htmlspecialchars($post['slug']) ?></td>
            <?php if ($show_date): ?>
              <td><time
                  datetime="<?= date('Y-m-d', strtotime($post['created_at'])) ?>"><?= date('Y-m-d', strtotime($post['created_at'])) ?></time>
              </td>
            <?php endif; ?>
            <td>
              <a href="<?= url('edit/' . $post['id']) ?>">Edit</a> |
              <a href="<?= url('admin/delete_post/' . $post['id']) ?>"
                onclick="return confirm('Are you sure you want to delete this <?= $type ?>?')"
                data-tooltip="Delete this <?= $type ?>">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
<?php
}

// Run the application
run_application();
?>