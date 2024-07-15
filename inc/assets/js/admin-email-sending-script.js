jQuery(document).ready(function($) {
    $('#ab-email-form').on('submit', function(event) {
        event.preventDefault();

        // Show a loading spinner
        $('#ab-loader').show();

        // Disable the submit button
        $('#ab_send_email').prop('disabled', true);

        var formData = $(this).serialize();

        $.ajax({
            url: ab_address_ajax.ajaxurl,
            type: 'POST',
            data: formData + '&action=ab_send_email',
            success: function(response) {
                $('#ab-loader').hide();
                $('#response-message').html(response);
            },
            error: function() {
                $('#ab-loader').hide();
                $('#response-message').html('<div class="error"><p>An error occurred. Please try again.</p></div>');
            },
            complete: function() {
                // Re-enable the submit button
                $('#ab_send_email').prop('disabled', false);
            }
        });
    });
});
