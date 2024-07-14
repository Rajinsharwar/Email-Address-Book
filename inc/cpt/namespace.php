<?php
/**
 * Address CPT.
 *
 * @package AddressBook
 */

namespace AddressBook\CPT;

/**
 * Registers the Address post type.
 *
 * @since 0.1
 */
function register_address() {
	register_extended_post_type( 'ab_address', [
			'menu_icon' => 'dashicons-id',
			'supports'  => [ 'title', 'revisions' ],
			'exclude_from_search' => true,
		], [
			'singular' => esc_html__( 'Address', 'address-book' ),
			'plural'   => esc_html__( 'Addresses', 'address-book' ),
			'slug'     => 'address',
		]
	);
}
