jQuery(document).ready(function($) {
    $('#test-api-connection').on('click', function() {
        var button = $(this);
        var resultDiv = $('#api-test-result');
        
        button.prop('disabled', true).text('Testing...');
        resultDiv.hide();
        
        var apiKey = $('#api_key').val();
        var environment = $('#environment').val();
        
        if (!apiKey) {
            resultDiv.removeClass('success error').addClass('error').html('Please enter an API key first.').show();
            button.prop('disabled', false).text('Test Connection');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'strike_validate_api_key',
                api_key: apiKey,
                environment: environment,
                nonce: strike_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.removeClass('error').addClass('success').html('✓ ' + response.data).show();
                } else {
                    resultDiv.removeClass('success').addClass('error').html('✗ ' + response.data).show();
                }
            },
            error: function() {
                resultDiv.removeClass('success').addClass('error').html('✗ Connection failed. Please check your settings.').show();
            },
            complete: function() {
                button.prop('disabled', false).text('Test Connection');
            }
        });
    });
});
