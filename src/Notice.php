<?php // phpcs:ignore WordPress.Files.FileName
/**
 * Admin-Notices class.
 *
 * Creates an admin notice with consistent styling.
 *
 * @package   WPTRT/admin-notices
 * @author    WPTRT <themes@wordpress.org>
 * @copyright 2019 WPTRT
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link      https://github.com/WPTRT/admin-notices
 */

namespace WPTRT\Dashboard;

/**
 * The Admin_Notice class, responsible for creating admin notices.
 *
 * Each notice is a new instance of the object.
 *
 * @since 1.0.0
 */
class Notice {

	/**
	 * The notice-ID.
	 *
	 * @access private
	 * @since 1.0
	 * @var string
	 */
	private $id;

	/**
	 * The name of the option (or user-meta) we're going to use to save the dismiss-status.
	 *
	 * @access private
	 * @since 1.0
	 * @var string
	 */
	private $option_key;

	/**
	 * The notice content.
	 *
	 * @access private
	 * @since 1.0
	 * @var string
	 */
	private $content;

	/**
	 * The notice arguments.
	 *
	 * @access private
	 * @since 1.0
	 * @var array
	 */
	private $args = [
		'dismissible'       => true,
		'scope'             => 'global',
		'type'              => 'info',
		'capability'        => 'edit_theme_options',
		'option_key_prefix' => 'wptrt_notice_dismissed',
		'screens'           => [],
	];

	/**
	 * Constructor.
	 *
	 * @access public
	 * @since 1.0
	 * @param string $id      A unique ID for this notice. Can contain lowercase characters and underscores.
	 * @param string $content The content for our notice.
	 * @param array  $args    An array of additional arguments to change the defaults for this notice.
	 *                        [
	 *                            'dismissible'       => (bool)   Whether this notice should be dismissible or not.
	 *                                                            Defaults to true.
	 *                            'screens'           => (array)  An array of screens where the notice will be displayed.
	 *                                                            Leave empty to always show.
	 *                                                            Defaults to an empty array.
	 *                            'scope'             => (string) Can be "global" or "user".
	 *                                                            Determines if the dismissed status will be saved as an option or user-meta.
	 *                                                            Defaults to "global".
	 *                            'type'              => (string) Can be one of "info", "success", "warning", "error".
	 *                                                            Defaults to "info".
	 *                            'capability'        => (string) The user capability required to see the notice.
	 *                                                            Defaults to "edit_theme_options".
	 *                            'option_key_prefix' => (string) The prefix that will be used to build the option (or post-meta) name.
	 *                                                            Can contain lowercase latin letters and underscores.
	 *                        ].
	 */
	public function __construct( $id, $content, $args = [] ) {

		// Set the object properties.
		$this->id         = $id;
		$this->option_key = $this->args['option_key_prefix'] . '_' . sanitize_key( $this->id );
		$this->content    = $content;
		$this->args       = wp_parse_args( $args, $this->args );

		// Sanity check: Early exit if ID or content are empty.
		if ( ! $this->id || ! $this->content ) {
			return;
		}

		// Add the notice.
		add_action( 'admin_notices', [ $this, 'the_notice' ] );

		// Handle AJAX requests to dismiss the notice.
		add_action( 'wp_ajax_wptrt_dismiss_notice', [ $this, 'ajax_maybe_dismiss_notice' ] );
	}

	/**
	 * Prints the notice.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function the_notice() {

		// Early exit if the user doesn't have the required capability.
		if ( ! current_user_can( $this->args['capability'] ) ) {
			return;
		}

		// Early exit if we're not on the right screen or if the notice has been dismissed.
		if ( ! $this->is_screen() || $this->is_dismissed() ) {
			return;
		}

		$classes = 'notice notice-' . $this->args['type'];
		// Add is-dismissible class.
		$classes .= ( $this->args['dismissible'] ) ? ' is-dismissible' : '';
		?>

		<div id="wptrt-notice-<?php echo esc_attr( $this->id ); ?>" class="<?php echo esc_attr( $classes ); ?>">
			<?php
			/**
			 * Print the content.
			 * This is hardcoded by the theme-author, no need to escape it here.
			 * Any escaping necessary should be applied to the content provided to the object beforehand.
			 */
			echo $this->content; // phpcs:ignore WordPress.Security.EscapeOutput
			?>
		</div>

		<?php
		/**
		 * Print the script handling the dismiss functionality.
		 */
		$this->print_script();
	}

	/**
	 * Print the script for dismissing the notice.
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function print_script() {

		// Sanity check: early exit if notice is non-dismissible.
		if ( ! $this->args['dismissible'] ) {
			return;
		}

		// Create a nonce.
		$nonce = wp_create_nonce( 'wptrt_dismiss_notice_' . $this->id );
		?>
		<script>
		window.addEventListener( 'load', function() {
			var dismissBtn  = document.querySelector( '#wptrt-notice-<?php echo esc_attr( $this->id ); ?> .notice-dismiss' );

			// Add an event listener to the dismiss button.
			dismissBtn.addEventListener( 'click', function( event ) {
				var httpRequest = new XMLHttpRequest(),
					postData    = '';

				// Build the data to send in our request.
				// Data has to be formatted as a string here.
				postData += 'id=<?php echo esc_attr( rawurlencode( $this->id ) ); ?>';
				postData += '&action=wptrt_dismiss_notice';
				postData += '&nonce=<?php echo esc_html( $nonce ); ?>';

				httpRequest.open( 'POST', '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>' );
				httpRequest.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' )
				httpRequest.send( postData );
			});
		});
		</script>
		<?php
	}

	/**
	 * Check if the notice has been dismissed or not.
	 *
	 * @access public
	 * @since 1.0
	 * @return bool
	 */
	public function is_dismissed() {

		// If the notice is not dismissible, then return false.
		if ( ! $this->args['dismissible'] ) {
			return false;
		}

		// Check if the notice has been dismissed when using user-meta.
		if ( 'user' === $this->args['scope'] ) {
			return ( get_user_meta( get_current_user_id(), $this->option_key, true ) );
		}

		return ( get_option( $this->option_key ) );
	}

	/**
	 * Evaluate if we're on the right place depending on the "screens" argument.
	 *
	 * @access private
	 * @since 1.0
	 * @return bool
	 */
	private function is_screen() {

		// If screen is empty we want this shown on all screens.
		if ( ! $this->args['screens'] || empty( $this->args['screens'] ) ) {
			return true;
		}

		// Make sure the get_current_screen function exists.
		if ( ! function_exists( 'get_current_screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/screen.php';
		}

		// Check if we're on one of the defined screens.
		return ( in_array( get_current_screen()->id, $this->args['screens'], true ) );
	}

	/**
	 * Run check to see if we need to dismiss the notice.
	 * If all tests are successful then call the dismiss_notice() method.
	 *
	 * @access public
	 * @since 1.0
	 * @return void
	 */
	public function ajax_maybe_dismiss_notice() {

		// Sanity check: Early exit if we're not on a wptrt_dismiss_notice action.
		if ( ! isset( $_POST['action'] ) || 'wptrt_dismiss_notice' !== $_POST['action'] ) {
			return;
		}

		// Sanity check: Early exit if the ID of the notice is not the one from this object.
		if ( ! isset( $_POST['id'] ) || $this->id !== $_POST['id'] ) {
			return;
		}

		// Security check: Make sure nonce is OK.
		check_ajax_referer( 'wptrt_dismiss_notice_' . $this->id, 'nonce', true );

		// If we got this far, we need to dismiss the notice.
		$this->dismiss_notice();
	}

	/**
	 * Actually dismisses the notice.
	 *
	 * @access private
	 * @since 1.0
	 * @return void
	 */
	private function dismiss_notice() {
		if ( 'user' === $this->args['scope'] ) {
			update_user_meta( get_current_user_id(), $this->option_key, true );
			return;
		}
		update_option( $this->option_key, true );
	}
}
