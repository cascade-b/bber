<?php
/**
 * The public-facing functionality of the plugin.
 */
class BBER_Public {
    /**
     * Register the stylesheets for the public-facing side of the site.
     */
    public function enqueue_styles() {
        wp_enqueue_style('bber-public', BBER_PLUGIN_URL . 'assets/css/bber-public.css', array(), BBER_VERSION, 'all');
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     */
    public function enqueue_scripts() {
        wp_enqueue_script('bber-public', BBER_PLUGIN_URL . 'assets/js/bber-public.js', array('jquery'), BBER_VERSION, false);
        
        // Localize the script with data
        wp_localize_script('bber-public', 'bber_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bber-public-nonce'),
        ));
    }

    /**
     * Register shortcodes.
     */
    public function register_shortcodes() {
        add_shortcode('bber_rates', array($this, 'rates_shortcode'));
        add_shortcode('bber_calculator', array($this, 'calculator_shortcode'));
        add_shortcode('bber_combined', array($this, 'combined_shortcode'));
    }

    /**
     * Shortcode to display exchange rates table.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function rates_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('EXCHANGE RATES', 'bangkok-bank-exchange-rates'),
            'show_last_updated' => 'yes',
        ), $atts, 'bber_rates');
        
        // Get exchange rates
        $rates_fetcher = new BBER_Rates_Fetcher();
        $rates = $rates_fetcher->fetch_rates();
        
        if (is_wp_error($rates)) {
            return '<div class="bber-error">' . esc_html($rates->get_error_message()) . '</div>';
        }
        
        // Get plugin options
        $options = get_option('bber_options', array());
        $display_currencies = isset($options['display_currencies']) ? $options['display_currencies'] : array('USD', 'EUR', 'GBP', 'JPY', 'CNY', 'AUD', 'SGD');
        $last_updated = isset($options['last_updated']) ? $options['last_updated'] : '';
        
        // Filter rates to display only selected currencies
        $filtered_rates = array();
        foreach ($rates as $code => $rate) {
            if (in_array($code, $display_currencies)) {
                $filtered_rates[$code] = $rate;
            }
        }
        
        // Start output buffering
        ob_start();
        ?>
        <div class="bber-container bber-rates-table">
            <h2 class="bber-title"><?php echo esc_html($atts['title']); ?></h2>
            
            <?php if ('yes' === $atts['show_last_updated'] && !empty($last_updated)): ?>
                <div class="bber-last-updated">
                    <?php echo sprintf(__('Last updated: %s', 'bangkok-bank-exchange-rates'), esc_html($last_updated)); ?>
                </div>
            <?php endif; ?>
            
            <div class="bber-table-container">
                <table class="bber-table">
                    <thead>
                        <tr>
                            <th><?php _e('Currency', 'bangkok-bank-exchange-rates'); ?></th>
                            <th><?php _e('Buying Rates', 'bangkok-bank-exchange-rates'); ?></th>
                            <th><?php _e('Selling Rates', 'bangkok-bank-exchange-rates'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered_rates as $code => $rate): ?>
                            <tr>
                                <td class="bber-currency">
                                    <div class="bber-currency-code"><?php echo esc_html($code); ?></div>
                                    <div class="bber-currency-name"><?php echo esc_html($rate['name']); ?></div>
                                </td>
                                <td><?php echo number_format($rate['buying_sight'], 4); ?></td>
                                <td><?php echo number_format($rate['selling'], 4); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="bber-disclaimer">
                <p><?php _e('Rates are subject to change without notice. The rates shown include a markup.', 'bangkok-bank-exchange-rates'); ?></p>
                <p><?php _e('Source: Bangkok Bank', 'bangkok-bank-exchange-rates'); ?> - <a href="<?php echo esc_url(BBER_Rates_Fetcher::BANGKOK_BANK_URL); ?>" target="_blank"><?php _e('View Original Rates', 'bangkok-bank-exchange-rates'); ?></a></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode to display exchange rate calculator.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function calculator_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Currency Converter', 'bangkok-bank-exchange-rates'),
        ), $atts, 'bber_calculator');
        
        // Get exchange rates
        $rates_fetcher = new BBER_Rates_Fetcher();
        $rates = $rates_fetcher->fetch_rates();
        
        if (is_wp_error($rates)) {
            return '<div class="bber-error">' . esc_html($rates->get_error_message()) . '</div>';
        }
        
        // Add THB to the rates array if not already present
        if (!isset($rates['THB'])) {
            $rates['THB'] = array(
                'code' => 'THB',
                'name' => 'Thai Baht',
                'buying_sight' => 1,
                'buying_transfer' => 1,
                'selling' => 1,
                'average' => 1,
            );
        }
        
        // Sort currencies alphabetically
        ksort($rates);
        
        // Start output buffering
        ob_start();
        ?>
        <div class="bber-container bber-calculator">
            <h2 class="bber-title"><?php echo esc_html($atts['title']); ?></h2>
            
            <div class="bber-calculator-form">
                <div class="bber-form-row">
                    <div class="bber-form-group">
                        <label for="bber-amount"><?php _e('Amount', 'bangkok-bank-exchange-rates'); ?></label>
                        <input type="number" id="bber-amount" class="bber-input" value="1" min="0.01" step="0.01">
                    </div>
                </div>
                
                <div class="bber-form-row">
                    <div class="bber-form-group">
                        <label for="bber-from-currency"><?php _e('From Currency', 'bangkok-bank-exchange-rates'); ?></label>
                        <select id="bber-from-currency" class="bber-select">
                            <?php foreach ($rates as $code => $rate): ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($code, 'THB'); ?>>
                                    <?php echo esc_html($code . ' - ' . $rate['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="bber-swap-button">
                        <button type="button" id="bber-swap-currencies" class="bber-button bber-button-icon">
                            <span class="dashicons dashicons-swap"></span>
                        </button>
                    </div>
                    
                    <div class="bber-form-group">
                        <label for="bber-to-currency"><?php _e('To Currency', 'bangkok-bank-exchange-rates'); ?></label>
                        <select id="bber-to-currency" class="bber-select">
                            <?php foreach ($rates as $code => $rate): ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($code, 'USD'); ?>>
                                    <?php echo esc_html($code . ' - ' . $rate['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="bber-form-row">
                    <button type="button" id="bber-convert" class="bber-button bber-button-primary"><?php _e('Convert', 'bangkok-bank-exchange-rates'); ?></button>
                </div>
                
                <div class="bber-result">
                    <div id="bber-result-amount"></div>
                    <div id="bber-result-rate"></div>
                </div>
            </div>
            
            <div class="bber-disclaimer">
                <p><?php _e('Rates are subject to change without notice. The rates shown include a markup.', 'bangkok-bank-exchange-rates'); ?></p>
                <p><?php _e('Source: Bangkok Bank', 'bangkok-bank-exchange-rates'); ?> - <a href="<?php echo esc_url(BBER_Rates_Fetcher::BANGKOK_BANK_URL); ?>" target="_blank"><?php _e('View Original Rates', 'bangkok-bank-exchange-rates'); ?></a></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode to display both exchange rates table and calculator.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function combined_shortcode($atts) {
        $atts = shortcode_atts(array(
            'rates_title' => __('EXCHANGE RATES', 'bangkok-bank-exchange-rates'),
            'calculator_title' => __('Currency Converter', 'bangkok-bank-exchange-rates'),
            'show_last_updated' => 'yes',
        ), $atts, 'bber_combined');
        
        $rates_output = $this->rates_shortcode(array(
            'title' => $atts['rates_title'],
            'show_last_updated' => $atts['show_last_updated'],
        ));
        
        $calculator_output = $this->calculator_shortcode(array(
            'title' => $atts['calculator_title'],
        ));
        
        return '<div class="bber-combined">' . $rates_output . $calculator_output . '</div>';
    }

    /**
     * AJAX handler for getting exchange rates.
     */
    public function ajax_get_exchange_rates() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bber-public-nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'bangkok-bank-exchange-rates')));
        }
        
        // Get exchange rates
        $rates_fetcher = new BBER_Rates_Fetcher();
        
        // Clear cache if requested
        if (isset($_POST['clear_cache']) && $_POST['clear_cache']) {
            delete_transient('bber_exchange_rates');
        }
        
        $rates = $rates_fetcher->fetch_rates();
        
        if (is_wp_error($rates)) {
            wp_send_json_error(array('message' => $rates->get_error_message()));
        }
        
        // Get plugin options
        $options = get_option('bber_options', array());
        
        // Add THB to the rates array if not already present
        if (!isset($rates['THB'])) {
            $rates['THB'] = array(
                'code' => 'THB',
                'name' => 'Thai Baht',
                'buying_sight' => 1,
                'buying_transfer' => 1,
                'selling' => 1,
                'average' => 1,
            );
        }
        
        // Convert amount if requested
        if (isset($_POST['convert']) && $_POST['convert']) {
            $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 1;
            $from_currency = isset($_POST['from_currency']) ? sanitize_text_field($_POST['from_currency']) : 'THB';
            $to_currency = isset($_POST['to_currency']) ? sanitize_text_field($_POST['to_currency']) : 'USD';
            
            $converted_amount = $rates_fetcher->convert_currency($amount, $from_currency, $to_currency, $rates);
            
            if (is_wp_error($converted_amount)) {
                wp_send_json_error(array('message' => $converted_amount->get_error_message()));
            }
            
            // Get rate information
            $rate_info = array(
                'from' => $from_currency,
                'to' => $to_currency,
                'rate' => 0,
            );
            
            if ($from_currency === 'THB') {
                $rate_info['rate'] = 1 / $rates[$to_currency]['buying_transfer'];
            } elseif ($to_currency === 'THB') {
                $rate_info['rate'] = $rates[$from_currency]['selling'];
            } else {
                $rate_info['rate'] = $converted_amount / $amount;
            }
            
            wp_send_json_success(array(
                'amount' => $amount,
                'from_currency' => $from_currency,
                'to_currency' => $to_currency,
                'converted_amount' => $converted_amount,
                'rate_info' => $rate_info,
                'last_updated' => isset($options['last_updated']) ? $options['last_updated'] : '',
            ));
        }
        
        wp_send_json_success(array(
            'rates' => $rates,
            'last_updated' => isset($options['last_updated']) ? $options['last_updated'] : '',
        ));
    }

    /**
     * Update exchange rates (called by cron).
     */
    public function update_exchange_rates() {
        // Get exchange rates
        $rates_fetcher = new BBER_Rates_Fetcher();
        
        // Clear cache
        delete_transient('bber_exchange_rates');
        
        // Fetch new rates
        $rates = $rates_fetcher->fetch_rates();
        
        // Log the update
        if (is_wp_error($rates)) {
            error_log('Bangkok Bank Exchange Rates update failed: ' . $rates->get_error_message());
        } else {
            error_log('Bangkok Bank Exchange Rates updated successfully. Found ' . count($rates) . ' currencies.');
        }
    }
}
