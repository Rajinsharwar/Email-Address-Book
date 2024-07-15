<?php
/**
 * Address Book CMB2
 *
 * @package AddressBook
 */

namespace AddressBook\CMB2;

/**
 * Handle CMB2 fields
 *
 * @since 0.1
 */
function address_meta() {
	$prefix = '_ab_';

	$cmb = new_cmb2_box( [
		'id'           => $prefix . 'metabox',
		'title'        => __( 'Address Information', 'address-book' ),
		'object_types' => [ 'ab_address' ],
		'priority'     => 'high',
	] );

	$cmb->add_field( [
		'name'       => __( 'First Name', 'address-book' ),
		'id'         => $prefix . 'first_name',
		'type'       => 'text',
		'attributes' => array(
            'required'    => 'required',
        ),
	] );

	$cmb->add_field( [
		'name'       => __( 'Last Name', 'address-book' ),
		'id'         => $prefix . 'last_name',
		'type'       => 'text',
	] );

	$cmb->add_field( [
		'name'       => __( 'Email Address', 'address-book' ),
		'id'         => $prefix . 'email',
		'type'       => 'text_email',
		'attributes' => array(
            'required'    => 'required',
        ),
	] );

	$cmb->add_field( [
		'name'       => __( 'Is Active', 'address-book' ),
		'id'         => $prefix . 'is_active',
		'type'       => 'checkbox',
	] );
}

function set_custom_edit_book_columns($columns) {
    $columns['status'] = __( 'Status', 'ab_address' );

    return $columns;
}

function custom_book_column( $column, $post_id ) {
    switch ( $column ) {

        case 'status' :
            $status = get_post_meta( $post_id , '_ab_is_active' , '' , ',' , '' );

			if ( ! empty( $status ) ) {
				echo '✅';
			} else {
				echo '❌';
			}
    }
}

/**
 * Returns the meta data for an address.
 *
 * @since  0.2.1
 * @param  integer $post_id An address post ID.
 * @return array            The address meta in array format.
 */
function get_address_meta( $post_id = 0 ) {
	$post_id = \AddressBook\get_post_id( $post_id );

	// Bail if we don't have an ID.
	if ( is_wp_error( $post_id ) ) {
		return;
	}

	return [
		'email'    => get_post_meta( $post_id, '_ab_email', true ),
	];
}
