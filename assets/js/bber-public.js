jQuery(document).ready(function($) {
    // Format number with commas and decimal places
    function formatNumber(number, decimals = 2) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }
    
    // Handle currency conversion
    function convertCurrency() {
        const $amount = $('#bber-amount');
        const $fromCurrency = $('#bber-from-currency');
        const $toCurrency = $('#bber-to-currency');
        const $resultAmount = $('#bber-result-amount');
        const $resultRate = $('#bber-result-rate');
        const $result = $('.bber-result');
        
        // Validate amount
        const amount = parseFloat($amount.val());
        if (isNaN(amount) || amount <= 0) {
            alert('Please enter a valid amount greater than 0.');
            return;
        }
        
        // Get selected currencies
        const fromCurrency = $fromCurrency.val();
        const toCurrency = $toCurrency.val();
        
        // Make AJAX request
        $.ajax({
            url: bber_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'bber_get_exchange_rates',
                nonce: bber_vars.nonce,
                convert: true,
                amount: amount,
                from_currency: fromCurrency,
                to_currency: toCurrency
            },
            beforeSend: function() {
                // Show loading indicator
                $resultAmount.html('<span class="bber-loading">Converting...</span>');
                $resultRate.html('');
                $result.addClass('active');
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // Format the result
                    $resultAmount.html(
                        formatNumber(data.amount) + ' ' + data.from_currency + ' = ' +
                        '<strong>' + formatNumber(data.converted_amount) + ' ' + data.to_currency + '</strong>'
                    );
                    
                    // Show exchange rate
                    $resultRate.html(
                        '1 ' + data.from_currency + ' = ' + formatNumber(data.rate_info.rate, 4) + ' ' + data.to_currency
                    );
                    
                    // Add source info if using alternative data
                    if (data.last_updated && data.last_updated.indexOf('Alternative') >= 0) {
                        $resultRate.append('<div class="bber-source-info"><small>Data from alternative source due to connection issues with Bangkok Bank</small></div>');
                    }
                    else if (data.last_updated && data.last_updated.indexOf('Fallback') >= 0) {
                        $resultRate.append('<div class="bber-source-info"><small>Using fallback data due to connection issues</small></div>');
                    }
                    
                    // Show result container
                    $result.addClass('active');
                } else {
                    $resultAmount.html('<div class="bber-error">Error: ' + response.data.message + '</div>');
                    $resultRate.html('<small>Please try again later. The system is using cached data where possible.</small>');
                }
            },
            error: function(xhr, status, error) {
                $resultAmount.html('<div class="bber-error">Connection Error</div>');
                $resultRate.html('<small>Could not connect to the server. Please check your internet connection and try again later.</small>');
                console.log('AJAX Error: ' + status + ' - ' + error);
            },
            timeout: 15000 // 15 second timeout
        });
    }
    
    // Handle convert button click
    $('#bber-convert').on('click', function() {
        convertCurrency();
    });
    
    // Handle swap currencies button click
    $('#bber-swap-currencies').on('click', function() {
        const $fromCurrency = $('#bber-from-currency');
        const $toCurrency = $('#bber-to-currency');
        
        // Swap selected values
        const fromValue = $fromCurrency.val();
        const toValue = $toCurrency.val();
        
        $fromCurrency.val(toValue);
        $toCurrency.val(fromValue);
        
        // If result is showing, update conversion
        if ($('.bber-result').hasClass('active')) {
            convertCurrency();
        }
    });
    
    // Handle amount input on enter key
    $('#bber-amount').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            convertCurrency();
        }
    });
});
