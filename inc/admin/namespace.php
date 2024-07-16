<?php
/**
 * Address Book Admin
 *
 * @package AddressBook
 */

namespace AddressBook\Admin;
use AddressBook\CMB2;

add_action('init', __NAMESPACE__ . '\\handle_emails_disable_enable');

function handle_emails_disable_enable() {
    if ( isset($_POST['action']) && $_POST['action'] === 'enable_all_emails' ) {
        enable_all_emails();
    }

	if ( isset($_POST['action']) && $_POST['action'] === 'disable_all_emails' ) {
        disable_all_emails();
    }

	if ( isset( $_POST['export_as_csv'] ) && 'export_as_csv' === $_POST['export_as_csv'] ) {
        ab_address_export_as_csv();
    }

	if ( isset($_POST['import_csv_action']) && $_POST['import_csv_action'] === 'import_csv' && isset($_FILES['import_csv_file']) && !empty($_FILES['import_csv_file']['tmp_name']) ) {
		ab_address_import_as_csv();
	}
}

/**
 * Adds the admin menus.
 *
 * @since 0.2
 */
function add_menus() {
    add_submenu_page(
        'edit.php?post_type=ab_address',
        __( 'Send Email', 'textdomain' ),
        __( 'Send Email', 'textdomain' ),
        'manage_options',
        'ab_address_send_email',
        __NAMESPACE__ . '\\ab_address_send_email_callback'
    );
}

/**
 * Admin Page Callback function.
 */
function ab_address_send_email_callback() {
    $details = get_contact_details_from_cpt();
    $total_users = count($details);

    ?>
    <div class="wrap">
        <h1>Send Bulk Email</h1>
		<form method="post">
			<input type="hidden" name="action" value="disable_all_emails">
			<button type="submit">Disable all emails</button>
		</form>

		<form method="post">
			<input type="hidden" name="action" value="enable_all_emails">
			<button type="submit">Enable all Emails</button>
		</form>

        <form id="ab-email-form" method="post" action="">
            <h3> Sending to total: <?php echo esc_html($total_users); ?> </h3>
            <hr>
            <h2>Compose Email</h2>
            <p>
                <label for="email_subject">Subject:</label>
                <input type="text" name="email_subject" id="email_subject" class="regular-text">
            </p>
            <p>
                <label for="email_body">Body: (Use {{user_first_name}} to replace with user's First name.)</label>
                <?php
                // Initialize wp_editor
                $settings = array(
                    'textarea_name'       => 'email_body',
                    'textarea_rows'       => 10,
                    'editor_class'        => 'large-text',
                    'teeny'               => true,
					'remove_trailing_brs' => false,
					"wpautop"             => false,
                );
                wp_editor('', 'email_body', $settings);
                ?>
            </p>
            <?php submit_button('Send Email', 'primary', 'ab_send_email'); ?>
            <div id="ab-loader" style="display:none;">
                <div class="loading_ab_address loading">
                    <span>S</span>
                    <span>E</span>
                    <span>N</span>
                    <span>D</span>
                    <span>I</span>
                    <span>N</span>
                    <span>G</span>
                </div>
            </div>
            <style>
                .loading_ab_address {
                    font-size: 20px;
                    font-family: 'Montserrat', sans-serif;
                    font-weight: 800;
                    text-align: center;
                }
                .loading {
                    perspective: 1000px;
                }
                .loading span {
                    display: inline-block;
                    margin: 0 -.05em;
                    transform-origin: 50% 50% -25px;
                    transform-style: preserve-3d;
                    animation: loading 1.6s infinite;
                }
                @keyframes loading {
                    0% {
                        transform: rotateX(-360deg);
                    }
                    70% {
                        transform: rotateX(0);
                    }
                }
            </style>
            <div id="response-message"></div>
        </form>
    </div>
    <?php
}

/**
 * Ajax callback for Email send function.
 */
function ab_send_email_ajax_callback() {
    $details = get_contact_details_from_cpt();
    $total_users = count($details);

    if (isset($_POST['email_subject']) && isset($_POST['email_body'])) {
        $email_subject = sanitize_text_field($_POST['email_subject']);
        $email_body = wp_kses_post(stripslashes_deep($_POST['email_body']));

		$email_body = get_email_message_html( $email_body );

		// Set email headers
		$headers = array('Content-Type: text/html; charset=UTF-8');

        if ( ! empty( $email_subject ) && ! empty( $email_body ) ) {
            foreach ( $details as $detail ) {
                $user_email = $detail['email'];

				$user_first_name = $detail['first_name'];

				// Replace with First Names.
				$email_body_updated = str_replace( '{{user_first_name}}', esc_html( $user_first_name ), $email_body );

                // Send email with headers
                wp_mail($user_email, $email_subject, $email_body_updated, $headers);
            }
            echo '<div class="updated"><p>🚀🚀🚀 Email(s) sent successfully!</p></div>';
        } else {
            echo '<div class="error"><p>Please provide both a subject and body for the email.</p></div>';
        }
    } else {
        echo '<div class="error"><p>Please provide both a subject and body for the email.</p></div>';
    }

    wp_die();
}

/**
 * Get Email HTML format.
 */
function get_email_message_html( $content ) {

	ob_start();
	?>
	<!doctype html>
	<html lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width">
	</head>
	<table>
		<tr class="ab_address-email-body">
			<td>
				<?php echo wp_kses_post( stripslashes_deep( $content ) ); ?>
			</td>
		</tr>
	</table>
	</html>

	<?php
	$message = ob_get_clean();

	return $message;
}

/**
 * Disable Auto Draft in ab_address cpt.
 */
function disable_auto_draft_cpt() {
    if ( 'ab_address' == get_post_type() ) {
		wp_dequeue_script( 'autosave' );
	}
}

/**
 * Query function to get contact meta from cpt.
 */
function get_contact_details_from_cpt() {
    global $wpdb;
    $meta_keys = ['_ab_email', '_ab_first_name', '_ab_last_name', '_ab_is_active'];
    $cache_key = 'ab_address_details_cache';

    // Try to get the cached result.
    $cached_details = wp_cache_get($cache_key);

    if ($cached_details !== false) {
        // Return cached result if available.
        return $cached_details;
    }

    // Prepare placeholders for the query
    $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));

    // Prepare the SQL query to fetch all required meta keys
    $sql = $wpdb->prepare(
        "SELECT post_id, meta_key, meta_value FROM $wpdb->postmeta WHERE meta_key IN ($placeholders)",
        ...$meta_keys
    );

    // Execute the query and fetch results
    $results = $wpdb->get_results($sql, ARRAY_A);

    // Organize the results into a structured array
    $details = [];
    foreach ($results as $row) {
        $post_id = $row['post_id'];
        if (!isset($details[$post_id])) {
            $details[$post_id] = [
                'email' => '',
                'first_name' => '',
                'last_name' => '',
                'is_active' => false,
            ];
        }
        switch ($row['meta_key']) {
            case '_ab_email':
                $details[$post_id]['email'] = sanitize_email($row['meta_value']);
                break;
            case '_ab_first_name':
                $details[$post_id]['first_name'] = sanitize_text_field($row['meta_value']);
                break;
            case '_ab_last_name':
                $details[$post_id]['last_name'] = sanitize_text_field($row['meta_value']);
                break;
            case '_ab_is_active':
                $details[$post_id]['is_active'] = filter_var($row['meta_value'], FILTER_VALIDATE_BOOLEAN);
                break;
        }
    }

    // Filter out entries that are not active
    $active_details = array_filter($details, function($detail) {
        return $detail['is_active'];
    });

    // Remove the 'is_active' key from each entry as it's no longer needed
    $formatted_details = array_map(function($detail) {
        unset($detail['is_active']);
        return $detail;
    }, $active_details);

    // Flatten the details into a list of arrays
    $formatted_details = array_values($formatted_details);

    // Cache the result
    wp_cache_set($cache_key, $formatted_details);

    return $formatted_details;
}

/**
 * Flsuh contact details meta cache on post save.
 */
function flush_email_cache_on_address_save( $post_id ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

    if ( 'ab_address' !== get_post_type( $post_id ) ) {
        return;
    }
    wp_cache_delete( 'ab_address_emails_cache' );
}

/**
 * Enable All Emails for sending emails.
 */
function enable_all_emails() {
	global $wpdb;

	$sql = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_ab_email'";

	$results = $wpdb->get_results($sql, ARRAY_A);

	$post_ids = array();
	foreach ( $results as $result ) {
		$post_ids[] = $result['post_id'];
	}

	foreach ( $post_ids as $post_id ) {
		add_post_meta( $post_id, '_ab_is_active', 'on' );
	}
}

/**
 * Disable all emails for sending emails.
 */
function disable_all_emails() {
	global $wpdb;

	$sql = "DELETE FROM $wpdb->postmeta WHERE meta_key = '_ab_is_active' AND `meta_value` = 'on';";

	$wpdb->get_results($sql);
}

/**
 * Add Export form button beside "Add New Address" in the admin.
 */
function export_csv_admin_button() {
    global $pagenow, $typenow;

    if ( 'edit.php' === $pagenow && 'ab_address' === $typenow ) {
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var addNewButton = document.querySelector('.page-title-action');
                if ( addNewButton ) {
                    var form = document.createElement('form');
                    form.method = 'post';
                    form.style.display = 'inline'; // Ensure the form is inline

                    var newButton = document.createElement('button');
                    newButton.type = 'submit';
                    newButton.className = 'page-title-action';
                    newButton.innerText = 'Export as CSV'; // Change this to the desired button text

                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'export_as_csv';
                    input.value = 'export_as_csv'; // Set a unique value to identify this action

                    form.appendChild(input);
                    form.appendChild(newButton);

                    addNewButton.parentNode.insertBefore(form, addNewButton.nextSibling);
                }
            });
        </script>
        <?php
    }
}

/**
 * Export contact details as a CSV file and prompt download.
 */
function ab_address_export_as_csv() {
    $details = get_contact_details_from_cpt();

    $output = fopen('php://output', 'w');

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="contacts.csv"');

    fputcsv($output, array('First Name', 'Last Name', 'Email'));

    foreach ( $details as $detail ) {
        $user_first_name = isset( $detail['first_name'] ) ? $detail['first_name'] : '';
        $user_last_name = isset( $detail['last_name'] ) ? $detail['last_name'] : '';
        $user_email = isset( $detail['email'] ) ? $detail['email'] : '';

        fputcsv($output, array($user_first_name, $user_last_name, $user_email));
    }

    fclose($output);
    exit();
}

/**
 * Add Import form button beside "Add New Address" in the admin.
 */
function import_csv_admin_button() {
    global $pagenow, $typenow;

    if ( 'edit.php' === $pagenow && 'ab_address' === $typenow ) {
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var addNewButton = document.querySelector('.page-title-action');
                if ( addNewButton ) {
                    var form = document.createElement('form');
                    form.method = 'post';
                    form.enctype = 'multipart/form-data'; // For file uploads
                    form.style.display = 'inline'; // Ensure the form is inline

                    var newButton = document.createElement('button');
                    newButton.type = 'submit';
                    newButton.className = 'page-title-action';
                    newButton.innerText = 'Import CSV'; // Change this to the desired button text

                    var input = document.createElement('input');
                    input.type = 'file';
                    input.name = 'import_csv_file';
                    input.accept = '.csv'; // Restrict to CSV files

                    var hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'import_csv_action';
                    hiddenInput.value = 'import_csv';

                    form.appendChild(input);
                    form.appendChild(hiddenInput);
                    form.appendChild(newButton);

                    addNewButton.parentNode.insertBefore(form, addNewButton.nextSibling);
                }
            });
        </script>
        <?php
    }
}

/**
 * Import contact details from a CSV file.
 */
function ab_address_import_as_csv() {
	$file = $_FILES['import_csv_file']['tmp_name'];

	if ( ! function_exists('wp_handle_upload') ) {
		require_once(ABSPATH . 'wp-admin/includes/file.php');
	}

	// Check if values already here.
	global $wpdb;

	// Prepare the SQL query to fetch all required meta keys
	$sql = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = '_ab_email'";

	// Execute the query and fetch results
	$results = $wpdb->get_results($sql, ARRAY_A);

    $meta_values = array_column($results, 'meta_value');

	// Import.
	if (($handle = fopen($file, 'r')) !== false) {
		// Skip the header row
		fgetcsv($handle);

		while (($data = fgetcsv($handle)) !== false) {
			if (count($data) < 3) {
				continue; // Skip invalid rows
			}

			$first_name = sanitize_text_field($data[0]);
			$last_name = sanitize_text_field($data[1]);
			$email = sanitize_email($data[2]);

			// Skip duplicate emails.
			if ( in_array( $email, $meta_values, ) ) {
				continue;
			}

			$post_title = $first_name . ' ' . $last_name;

			$post_id = wp_insert_post(array(
				'post_title'  => $post_title,
				'post_type'   => 'ab_address',
				'post_status' => 'publish',
			));

			if (!is_wp_error($post_id)) {
				update_post_meta($post_id, '_ab_first_name', $first_name);
				update_post_meta($post_id, '_ab_last_name', $last_name);
				$status = update_post_meta($post_id, '_ab_email', $email);
				error_log($status);
			}
		}

		fclose($handle);
	}

	wp_redirect(admin_url('edit.php?post_type=ab_address'));
	exit();
}

function save_ab_address( $meta_id, $object_id, $meta_key, $_meta_value ) {
	if ( $meta_key !== '_ab_email' ) {
		return;
    }

	global $wpdb;

	// Prepare the SQL query to fetch all required meta keys
	$sql = "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = '_ab_email'";

	// Execute the query and fetch results
	$results = $wpdb->get_results($sql, ARRAY_A);

    $meta_values = array_column($results, 'meta_value');
    if ( in_array($_meta_value, $meta_values ) ) {
		wp_die(__('Email Exists!', 'ab_address'));
	}
}
