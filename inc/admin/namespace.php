<?php
/**
 * Address Book Admin
 *
 * @package AddressBook
 */

namespace AddressBook\Admin;
use AddressBook\CMB2;

/**
 * Updates the taxonomy parent menu item.
 *
 * @since  0.2
 * @param  string $parent_file The old menu parent.
 * @return string              The updated menu parent.
 */
function taxonomy_parent( $parent_file ) {
	if ( in_array( get_current_screen()->taxonomy, [ 'relationship', 'ab_family' ] ) ) {
		$parent_file = 'address-book';
	}

	return $parent_file;
}

/**
 * The Address Book list page.
 *
 * @since 0.2.1
 */
function admin_page() {
	?>
	<div class="wrap address-book-list">
		<h1 class="wp-heading-inline"><?php echo get_admin_page_title(); // WPCS: XSS ok. ?></h1>
		<a href="post-new.php?post_type=ab_address" class="page-title-action"><?php esc_html_e( 'Add New Address', 'address-book' ); ?></a>
		<a href="#" class="page-title-action"><?php esc_html_e( 'Print', 'address-book' ); ?></a>
		<hr class="wp-header-end">

		<table class="wp-list-table widefat fixed striped addresses">
			<thead>
				<tr>
					<th scope="col" id="name" class="manage-column column-name column-primary">
						<?php esc_html_e( 'Name', 'address-book' ); ?>
					</th>
					<th scope="col" id="email-address" class="manage-column column-email-address">
						<?php esc_html_e( 'Email Address', 'address-book' ); ?>
					</th>
				</tr>
			</thead>
			<tbody id="the-list">
				<?php the_address_list(); ?>
			</tbody>
			<tfoot>
				<tr>
					<th scope="col" id="name" class="manage-column column-name column-primary">
						<?php esc_html_e( 'Name', 'address-book' ); ?>
					</th>
					<th scope="col" id="email-address" class="manage-column column-email-address">
						<?php esc_html_e( 'Email Address', 'address-book' ); ?>
					</th>
				</tr>
			</tfoot>
		</table>
	</div>
	<?php
}

/**
 * Renders a single address in the table list.
 *
 * @since  0.2.1
 * @param  object $address WP_Post object.
 */
function render_address( $address ) {
	$address_meta = CMB2\get_address_meta( $address->ID );
	?>
	<tr id="address-<?php echo absint( $address->ID ); ?>">
		<td class="column-primary name column-name has-row-actions" data-colname="<?php esc_html_e( 'Name', 'address-book' ); ?>"">
			<a href="post.php?post=<?php echo absint( $address->ID ); ?>&action=edit"><strong><?php echo esc_attr( $address->post_title ); ?></strong></a>
			<div class="row-actions"><a href="post.php?post=<?php echo absint( $address->ID ); ?>&action=edit"><?php esc_html_e( 'Edit', 'address-book' ); ?></a></div>
		</td>
		<td class="email column-email">
			<a href="mailto:<?php echo esc_html( sanitize_email( $address_meta['email'] ) ); ?>"><?php echo esc_html( $address_meta['email'] ); ?></a>
		</td>
	</tr>
	<?php
}

/**
 * Renders the full address list.
 *
 * @since 0.2.1
 */
function the_address_list() {
	foreach ( \AddressBook\get_addresses() as $address ) {
		render_address( $address );
	}
}
