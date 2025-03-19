jQuery(document).ready(function($) {
    // Handle refresh rates button click
    $('#bber-refresh-rates').on('click', function() {
        const $button = $(this);
        const $status = $('#bber-refresh-status');
        
        // Disable button and show loading state
        $button.prop('disabled', true).text(bber_admin_vars.refreshing_text || 'Refreshing...');
        $status.removeClass('success error').addClass('loading').html('Connecting to Bangkok Bank servers...').show();
        
        // Make AJAX request
        $.ajax({
            url: bber_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'bber_get_exchange_rates',
                nonce: bber_admin_vars.nonce,
                clear_cache: true
            },
            success: function(response) {
                if (response.success) {
                    // Check for fallback message in the last_updated field
                    const lastUpdated = response.data.last_updated || '';
                    
                    if (lastUpdated.indexOf('Alternative Source') >= 0) {
                        $status
                            .removeClass('loading error')
                            .addClass('warning')
                            .html('Exchange rates updated successfully using an alternative source due to connectivity issues with Bangkok Bank.')
                            .show();
                    } 
                    else if (lastUpdated.indexOf('Fallback Data') >= 0) {
                        $status
                            .removeClass('loading')
                            .addClass('error')
                            .html('Could not connect to any exchange rate sources. Using fallback data. Please check your internet connection.')
                            .show();
                    }
                    else {
                        $status
                            .removeClass('loading error warning')
                            .addClass('success')
                            .html('Exchange rates updated successfully from Bangkok Bank.')
                            .show();
                    }
                    
                    // Reload page after 2 seconds to show updated rates
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    $status
                        .removeClass('loading success warning')
                        .addClass('error')
                        .html('Error: ' + (response.data.message || 'Unknown error occurred.'))
                        .show();
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Could not connect to the server.';
                
                if (status === 'timeout') {
                    errorMessage = 'Connection timed out. Bangkok Bank servers might be slow or unavailable.';
                } else if (xhr.status === 0) {
                    errorMessage = 'Network connection issue. Please check your internet connection.';
                } else if (xhr.status >= 400) {
                    errorMessage = 'Server error: ' + xhr.status + ' ' + (error || '');
                }
                
                $status
                    .removeClass('loading success warning')
                    .addClass('error')
                    .html('Error: ' + errorMessage)
                    .show();
                    
                console.log('AJAX Error:', status, error, xhr);
            },
            timeout: 30000, // 30 second timeout
            complete: function() {
                // Re-enable button and restore text
                $button.prop('disabled', false).text(bber_admin_vars.refresh_text || 'Refresh Rates Now');
            }
        });
    });
});
