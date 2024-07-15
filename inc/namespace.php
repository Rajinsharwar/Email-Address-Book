<?php
/**
 * Address Book
 *
 * @package AddressBook
 */

namespace AddressBook;

use AddressBook\CMB2;

/**
 * Bootstrap the plugin.
 *
 * Registers actions and filter required to run the plugin.
 */
function bootstrap() {

	add_action( 'save_post', __NAMESPACE__ . '\\flush_cached_addresses' );
	add_action( 'init', __NAMESPACE__ . '\\CPT\\register_address' );
	add_action( 'cmb2_init', __NAMESPACE__ . '\\CMB2\\address_meta' );

	add_action( 'admin_menu', __NAMESPACE__ . '\\Admin\\add_menus' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\Admin\\disable_auto_draft_cpt' );
	add_action('save_post', __NAMESPACE__ . '\\Admin\\flush_email_cache_on_address_save');

	add_action( 'manage_ab_address_posts_custom_column' , __NAMESPACE__ . '\\CMB2\\custom_book_column', 10, 2 );
	add_filter( 'manage_ab_address_posts_columns', __NAMESPACE__ . '\\CMB2\\set_custom_edit_book_columns' );

	add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\load_custom_admin_script');

	add_action('wp_ajax_ab_send_email', __NAMESPACE__ . '\\Admin\\ab_send_email_ajax_callback');
	add_action('wp_ajax_nopriv_ab_send_email', __NAMESPACE__ . '\\Admin\\ab_send_email_ajax_callback');
}

/**
 * Helper function to get an ID.
 *
 * @since  0.2.1
 * @param  integer $post_id A post ID. If passed, we just make sure it's an int.
 * @return mixed            Either a WP_Error (if no ID could be found) or the post ID.
 */
function get_post_id( $post_id = 0 ) {
	if ( 0 === $post_id ) {
		$post_id = get_the_ID();
	}

	if ( ! $post_id || ! is_int( $post_id ) ) {
		return new \WP_Error( 'no_IDea', esc_html__( 'No Address ID found', 'address-book' ) );
	}

	return $post_id;
}

/**
 * Handle the query to pull addresses and sort by relationship and family.
 *
 * @since  0.2.2
 * @param  int  $numposts The number of posts to query. -1 pulls all posts.
 * @param  bool $inactive Whether to include inactive addresses.
 * @return object         The WP_Query object.
 */
function address_query( $numposts = -1, $inactive = false ) {
	$args = [
		'post_type'      => 'ab_address',
		'nopaging'       => true,
		'posts_per_page' => $numposts,
		'no_found_rows'  => true,
	];

	// Include inactive posts if $inactive is true.
	if ( $inactive ) {
		$args['meta_query'] = [
			[
				'key'     => 'inactive',
				'compare' => '!=',
				'value'   => '',
			],
		];
	}

	$address_query = new \WP_Query( $args );

	return $address_query;
}

/**
 * Remove the inactive addresses and return a filtered, sorted (by family and relationship) list of addresses.
 *
 * @since  0.2.1
 * @param  int  $numposts The number of posts to query. -1 pulls all posts.
 * @param  bool $inactive Whether to include inactive addresses.
 * @return array The array of addresses.
 */
function get_addresses( $numposts = -1, $inactive = false ) {
	$addresses = wp_cache_get( 'full_address_list', 'address_book' );

	if ( ! $addresses && $numposts < 0 ) {
		$addresses = address_query()->posts;
		wp_cache_set( 'full_address_list', $addresses, 'address_book', DAY_IN_SECONDS );
	}

	return $addresses;
}

/**
 * Delete cached address list on save post.
 *
 * @since  0.2.2
 * @param  int $post_id The post ID.
 * @return void
 */
function flush_cached_addresses( $post_id ) {
	// Don't flush for revisions.
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	wp_cache_delete( 'full_address_list', 'address_book' );
}

function load_custom_admin_script() {
	wp_enqueue_script('ab_address_admin_script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-email-sending-script.js', array('jquery'), '1.0', true);

    // Pass the AJAX URL to the script
    wp_localize_script('ab_address_admin_script', 'ab_address_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
