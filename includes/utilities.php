<?php
/**
 * Various utilities we use.
 *
 * @package WooCartExpiration
 */

// Declare our namespace.
namespace LiquidWeb\WooCartExpiration\Utilities;

// Set our aliases.
use LiquidWeb\WooCartExpiration as Core;
use LiquidWeb\WooCartExpiration\Cookies as Cookies;

/**
 * Check to see if the expiration is indeed enabled.
 *
 * @return boolean
 */
function maybe_expiration_enabled() {

	// Pull the option.
	$enable = get_option( Core\OPTIONS_PREFIX . 'enabled', 'no' );

	// Return a simple true / false.
	return 'yes' === sanitize_text_field( $enable ) ? true : false;
}

/**
 * Get the URL of the cart, for redirects.
 *
 * @return string
 */
function get_cart_url() {

	// Get my ID, which is stored.
	$cart_page_id   = get_option( 'woocommerce_cart_page_id', 0 );

	// If no cart ID exists, grab the home page.
	$cart_page_url  = ! $cart_page_id ? home_url() : get_permalink( $cart_page_id );

	// Return the URL.
	return trailingslashit( $cart_page_url );
}

/**
 * Calculate the expiration time for a new cookie.
 *
 * @param  string $key  Request a single key from the array.
 *
 * @return array        The cart expire and actual cookie (which is more).
 */
function get_initial_expiration_times( $key = false ) {

	// Fetch the amount of time.
	$stored = get_option( Core\OPTIONS_PREFIX . 'mins', 15 );

	// Set the car expire time.
	$expire = current_time( 'timestamp', true ) + ( absint( $stored ) * MINUTE_IN_SECONDS );

	// Set an actual expire, which is 7 additional minutes.
	$cookie = absint( $expire ) + 420;

	// Set up the array.
	$setup  = array( 'expire' => $expire, 'cookie' => $cookie );

	// Return the array.
	if ( ! $key ) {
		return $setup;
	}

	// Return the single, or false.
	return isset( $setup[ $key ] ) ? $setup[ $key ] : false;
}

/**
 * Get the expiration on a current cookie.
 *
 * @return integer  The expiration time in Unix or zero.
 */
function get_current_cookie_expiration() {

	// First get the cookie data.
	$cookie = Cookies\check_cookie( true );

	// Bail without the data.
	if ( ! $cookie || empty( $cookie['expire'] ) ) {
		return 0;
	}

	// Set my current time.
	$current_time   = current_time( 'timestamp', true );

	// Return the remaining amount, or a zero.
	return absint( $cookie['expire'] ) >= absint( $current_time ) ? absint( $cookie['expire'] ) : 0;
}

/**
 * Get the remaining time on a current cookie.
 *
 * @param  string $return  How to return the data.
 *
 * @return mixed           The remaining time in seconds, or as a formatted string.
 */
function get_current_cookie_remaining( $return = '' ) {

	// First get the cookie data.
	$cookie = Cookies\check_cookie( true );

	// Bail without the data.
	if ( ! $cookie || empty( $cookie['expire'] ) ) {
		return 0;
	}

	// Set my current time.
	$current_time   = current_time( 'timestamp', true );

	// If we are expired, just return zero.
	if ( absint( $current_time ) >= absint( $cookie['expire'] ) ) {
		return 0;
	}

	// Set my remaining time.
	$remaining_time = absint( $cookie['expire'] ) - absint( $current_time );

	// Set my two pieces of data.
	$remain_minutes = floor( ( $remaining_time / 60 ) % 60 );
	$remain_seconds = $remaining_time % 60;

	// Handle my different return formats.
	switch ( sanitize_text_field( $return ) ) {

		case 'format' :
			return absint( $remain_minutes ) . ':' . absint( $remain_seconds );
			break;

		case 'array' :
			return array( 'minutes' => absint( $remain_minutes ), 'seconds' => absint( $remain_seconds ) );
			break;

		default :
			return absint( $remaining_time );

		// End all case breaks.
	}

	// And we're done.
}

/**
 * Call our function that empties the cart.
 *
 * @return void
 */
function clear_current_cart() {

	// Set our global for Woo.
	global $woocommerce;

	// Run the cart empty setup.
	foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $cart_item ) {
		$woocommerce->cart->remove_cart_item( $cart_item_key );
	}

	// Now recalc the totals.
	$woocommerce->cart->calculate_totals();

	// And clear out the cookie.
	Cookies\clear_cookie();
}

/**
 * Check if we are on the checkout page
 * which could also be orders.
 *
 * @return string
 */
function maybe_checkout_page() {

	// First see if it's checkout.
	if ( ! is_checkout() ) {
		return 'no';
	}

	// Check for an order variable.
	$order  = get_query_var( 'order-received', false );

	// If we have an order variable, it isn't actually checkout.
	if ( $order ) {
		return 'order';
	}

	// @@todo other checks needed?
	return 'checkout';
}

/**
 * Confirm we are on the settings page in the admin.
 *
 * @param  string $compare  What we are comparing the screen object to.
 *
 * @return mixed
 */
function maybe_admin_settings_page( $compare = 'woocommerce_page_wc-settings' ) {

	// Bail if not on admin or our function doesnt exist.
	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return false;
	}

	// Get my current screen.
	$screen = get_current_screen();

	// Bail without.
	if ( empty( $screen ) || ! is_object( $screen ) ) {
		return false;
	}

	// If the compare string is false, return the entire object.
	if ( empty( $compare ) ) {
		return $screen;
	}

	// If we have no base or ID, or mismatched, it's false right off the bat.
	if ( empty( $screen->base ) || empty( $screen->id ) || sanitize_text_field( $screen->id ) !== sanitize_text_field( $screen->base ) ) {
		return false;
	}

	// Check against just the base, since they both should match here.
	if ( sanitize_text_field( $compare ) !== sanitize_text_field( $screen->base ) ) {
		return false;
	}

	// No other checks? Go forth.
	return true;
}

/**
 * Return our base link, with function fallbacks.
 *
 * @param  boolean $include_hash  Whether to include the anchor hash.
 *
 * @return string
 */
function get_settings_tab_link( $include_hash = true ) {

	// First set the main link.
	$settings   = ! function_exists( 'menu_page_url' ) ? admin_url( 'admin.php?page=wc-settings&tab=general' ) : add_query_arg( array( 'tab' => 'general' ), menu_page_url( 'wc-settings', false ) );

	// Now return the link with the hash.
	return ! $include_hash ? $settings : $settings . '#' . sanitize_html_class( Core\SETTINGS_ANCHOR );
}

/**
 * Handles a front-end redirect request.
 *
 * @param  string $redirect  The redirect URL to send.
 *
 * @return void
 */
function single_page_redirect( $redirect = '' ) {

	// Bail on admin.
	if ( empty( $redirect ) ) {
		return false;
	}

	// Do the redirect.
	wp_redirect( esc_url( $redirect ) );
	exit;
}

/**
 * Check our various constants on an Ajax call.
 *
 * @param  string  $action  Which action we are checking for.
 *
 * @return boolean
 */
function check_ajax_constants( $action = '' ) {

	// Check for a REST API request.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	// Check for running an autosave.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return false;
	}

	// Check for running a cron, unless we've skipped that.
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return false;
	}

	// Set the action.
	$action = ! empty( $action ) ? sanitize_text_field( $action ) : false;

	// Check for the specific action.
	if ( ! $action || ! isset( $_POST['action'] ) || sanitize_text_field( $action ) !== sanitize_text_field( $_POST['action'] ) ) { // WPCS: CSRF ok.
		return false;
	}

	// We hit none of the checks, so proceed.
	return true;
}
