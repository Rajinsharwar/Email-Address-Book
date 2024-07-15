<?php
/**
 * Address Book Admin
 *
 * @package AddressBook
 */

namespace AddressBook\Admin;
use AddressBook\CMB2;

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

function ab_address_send_email_callback() {
    $details = get_contact_details_from_cpt();
    $total_users = count($details);

    ?>
    <div class="wrap">
        <h1>Send Bulk Email</h1>
        <form id="ab-email-form" method="post" action="">
            <h3> Sending to total: <?php echo esc_html($total_users); ?> </h3>
            <hr>
            <h2>Compose Email</h2>
            <p>
                <label for="email_subject">Subject:</label>
                <input type="text" name="email_subject" id="email_subject" class="regular-text">
            </p>
            <p>
                <label for="email_body">Body:</label>
                <?php
                // Initialize wp_editor
                $settings = array(
                    'textarea_name' => 'email_body',
                    'textarea_rows' => 10,
                    'editor_class'  => 'large-text',
                    'teeny'         => true,
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

function ab_send_email_ajax_callback() {
    $details = get_contact_details_from_cpt();
    $total_users = count($details);

    if (isset($_POST['email_subject']) && isset($_POST['email_body'])) {
        $email_subject = sanitize_text_field($_POST['email_subject']);
        $email_body = wp_kses_post(stripslashes_deep($_POST['email_body']));

        $email_body = get_email_message_html( $email_body );

        if (!empty($email_subject) && !empty($email_body)) {
            foreach ($details as $detail) {
                $user_email = $detail['email'];

                // Set email headers
                $headers = array('Content-Type: text/html; charset=UTF-8');

                // Send email with headers
                wp_mail($user_email, $email_subject, $email_body, $headers);
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

function get_email_message_html( $content ) {

	ob_start();
	?>
	<!doctype html>
	<html lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width">
		<title>WP Mail SMTP Test Email</title>
	</head>
	<?php echo nl2br( $content ); ?>
	</html>

	<?php
	$message = ob_get_clean();

	return $message;
}

function disable_auto_draft_cpt() {
    if ( 'ab_address' == get_post_type() ) {
		wp_dequeue_script( 'autosave' );
	}
}

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


function flush_email_cache_on_address_save( $post_id ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

    if ( 'ab_address' !== get_post_type( $post_id ) ) {
        return;
    }
    wp_cache_delete( 'ab_address_emails_cache' );
}
