# WPTRT Admin Notices

This is a custom class allowing WordPress theme authors to add admin notices to the WordPress dashboard.
Its primary purpose is for providing a standardized method of creating admin notices in a consistent manner using the default WordPress styles.

## Usage

```php
new \WPTRT\Dashboard\Notice( (string) $id, (string) $content, (array) $args );
```

### Arguments

* `$id` - Required - `(string)` - A unique ID for this notice. The ID can contain lowercase latin letters and underscores. It is used to construct the option (or user-meta) key that will be strored in the database.
* `$content` - Required - `(string)` - The content for this notice. Note: Please make sure that your text is properly wrapped in `<p>` tags.
* `$args` - Optional - `(array)` - Extra arguments for this notice. Can be used to alter the notice's default behavior.

---------

The `$args` argument can have the following values:
* `dismissible` - `(bool)` - Whether this notice should be dismissible or not. Defaults to `true`.
* `screens` - `(array)` - An array of screens where the notice will be displayed. Leave empty to always show. Defaults to an empty array.
* `scope` - `(string)` Can be "global" or "user". Determines if the dismissed status will be saved as an option or user-meta. Defaults to `global`.
* `style` - `(string)` - Can be one of "info", "success", "warning", "error". Defaults to "info".
* `capability` - `(string)` - The user capability required to see the notice. Defaults to `edit_theme_options`.
* `option_key_prefix` - `(string)` - The prefix that will be used to build the option (or post-meta) name. Can contain lowercase latin letters and underscores. The actual option is built by combining the `option_key_prefix` argument with the defined ID from the 1st argument of the class. Defaults to `wptrt_notice_dismissed`.

## Examples
You can add the following code within your theme's existing code.

### Simple notice.

```php
use WPTRT\Dashboard\Notice;

$notice_id      = 'my_theme_notice';
$notice_content = '<p>' . esc_html__( 'This is the content for my new notice', 'textdomain' ) . '</p>';
new Notice( $notice_id, $notice_content );
```
The above example will create a new notice that will only show on all dashboard pages. When the notice gets dismissed, a new option will be saved in the database with the key `wptrt_notice_dismissed_my_theme_notice`. The key gets created by appending the `$notice_id` to the default prefix for the option (`wptrt_notice_dismissed`), separated by an underscore.

### Advanced example using extra arguments.

```php
use WPTRT\Dashboard\Notice;

$notice_id      = 'my_theme_notice';
$notice_content = '<p>' . esc_html__( 'This is the content for my new notice', 'textdomain' ) . '</p>';
$notice_args    = [
	'screens'           => [ 'themes' ],       // Only show notice in the "themes" screen.
	'scope'             => 'user',             // Dismiss is per-user instead of global.
	'style'             => 'warning',          // Changes the color to orange.
	'option_key_prefix' => 'notice_dismissed', // Changes the prefix for the user-meta we'll save.
];
new Notice( $notice_id, $notice_content, $notice_args );
```

The above example will create a new notice that will only show in the "Themes" screen in the dasboard. When the notice gets dismissed, a new user-meta will be saved and the key for the stored user-meta will be `notice_dismissed_my_theme_notice`. The key gets created by appending the `$notice_id` to our defined `option_key_prefix`, separated by an underscore.


## Autoloading

You'll need to use an autoloader with this. Ideally, this would be [Composer](https://getcomposer.org).  However, we have a [basic autoloader](https://github.com/WPTRT/autoload) available to include with themes if needed.

### Composer

From the command line:

```sh
composer require wptrt/admin-notices
```

### WPTRT Autoloader

If using the WPTRT autoloader, use the following code:

```php
include get_theme_file_path( 'path/to/autoload/src/Loader.php' );

$loader = new \WPTRT\Autoload\Loader();
$loader->add( 'WPTRT\\Dashboard\\Notice', get_theme_file_path( 'path/to/admin-notices/src' ) );
$loader->register();
```
