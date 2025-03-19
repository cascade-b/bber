<?php
/**
 * The admin-specific functionality of the plugin.
 */
class BBER_Admin {
    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style('bber-admin', BBER_PLUGIN_URL . 'assets/css/bber-admin.css', array(), BBER_VERSION, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        wp_enqueue_script('bber-admin', BBER_PLUGIN_URL . 'assets/js/bber-admin.js', array('jquery'), BBER_VERSION, false);
        
        // Localize the script with data
        wp_localize_script('bber-admin', 'bber_admin_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bber-admin-nonce'),
        ));
    }

    /**
     * Add plugin admin menu.
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            __('Bangkok Bank Exchange Rates', 'bangkok-bank-exchange-rates'),
            __('Exchange Rates', 'bangkok-bank-exchange-rates'),
            'manage_options',
            'bangkok-bank-exchange-rates',
            array($this, 'display_plugin_admin_page'),
            'dashicons-money-alt',
            100
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'bber_options_group',
            'bber_options',
            array($this, 'validate_options')
        );

        add_settings_section(
            'bber_general_settings',
            __('General Settings', 'bangkok-bank-exchange-rates'),
            array($this, 'general_settings_section_callback'),
            'bangkok-bank-exchange-rates'
        );

        add_settings_field(
            'markup_percentage',
            __('Markup Percentage', 'bangkok-bank-exchange-rates'),
            array($this, 'markup_percentage_callback'),
            'bangkok-bank-exchange-rates',
            'bber_general_settings'
        );

        add_settings_field(
            'display_currencies',
            __('Display Currencies', 'bangkok-bank-exchange-rates'),
            array($this, 'display_currencies_callback'),
            'bangkok-bank-exchange-rates',
            'bber_general_settings'
        );

        add_settings_field(
            'cache_duration',
            __('Cache Duration (seconds)', 'bangkok-bank-exchange-rates'),
            array($this, 'cache_duration_callback'),
            'bangkok-bank-exchange-rates',
            'bber_general_settings'
        );
    }

    /**
     * Display the admin page content.
     */
    public function display_plugin_admin_page() {
        // Get current rates
        $rates_fetcher = new BBER_Rates_Fetcher();
        $rates = $rates_fetcher->fetch_rates();
        $options = get_option('bber_options', array());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="bber-admin-container">
                <div class="bber-admin-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('bber_options_group');
                        do_settings_sections('bangkok-bank-exchange-rates');
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <div class="bber-admin-sidebar">
                    <div class="bber-card">
                        <h2><?php _e('Shortcodes', 'bangkok-bank-exchange-rates'); ?></h2>
                        <p><strong>[bber_rates]</strong> - <?php _e('Display exchange rates table', 'bangkok-bank-exchange-rates'); ?></p>
                        <p><strong>[bber_calculator]</strong> - <?php _e('Display exchange rate calculator', 'bangkok-bank-exchange-rates'); ?></p>
                        <p><strong>[bber_combined]</strong> - <?php _e('Display both rates table and calculator', 'bangkok-bank-exchange-rates'); ?></p>
                    </div>
                    
                    <div class="bber-card">
                        <h2><?php _e('Current Rates', 'bangkok-bank-exchange-rates'); ?></h2>
                        <?php if (is_wp_error($rates)): ?>
                            <p class="bber-error"><?php echo esc_html($rates->get_error_message()); ?></p>
                        <?php else: ?>
                            <p><?php _e('Last Updated:', 'bangkok-bank-exchange-rates'); ?> <strong><?php echo isset($options['last_updated']) ? esc_html($options['last_updated']) : __('Never', 'bangkok-bank-exchange-rates'); ?></strong></p>
                            <p><?php _e('Available Currencies:', 'bangkok-bank-exchange-rates'); ?> <strong><?php echo count($rates); ?></strong></p>
                            <button type="button" class="button button-secondary" id="bber-refresh-rates"><?php _e('Refresh Rates Now', 'bangkok-bank-exchange-rates'); ?></button>
                            <div id="bber-refresh-status"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * General settings section callback.
     */
    public function general_settings_section_callback() {
        echo '<p>' . __('Configure the settings for Bangkok Bank Exchange Rates plugin.', 'bangkok-bank-exchange-rates') . '</p>';
    }

    /**
     * Markup percentage field callback.
     */
    public function markup_percentage_callback() {
        $options = get_option('bber_options', array());
        $markup_percentage = isset($options['markup_percentage']) ? floatval($options['markup_percentage']) : 3;
        ?>
        <input type="number" step="0.01" min="0" max="100" name="bber_options[markup_percentage]" value="<?php echo esc_attr($markup_percentage); ?>" class="small-text" />
        <p class="description"><?php _e('Percentage to increase/decrease rates. Applied as increase to selling rates and decrease to buying rates.', 'bangkok-bank-exchange-rates'); ?></p>
        <?php
    }

    /**
     * Display currencies field callback.
     */
    public function display_currencies_callback() {
        $options = get_option('bber_options', array());
        $display_currencies = isset($options['display_currencies']) ? $options['display_currencies'] : array('USD', 'EUR', 'GBP', 'JPY', 'CNY', 'AUD', 'SGD');
        
        // Get all available currencies
        $rates_fetcher = new BBER_Rates_Fetcher();
        $rates = $rates_fetcher->fetch_rates();
        
        if (!is_wp_error($rates)) {
            echo '<div class="bber-currency-checkboxes">';
            foreach ($rates as $code => $rate) {
                $checked = in_array($code, $display_currencies) ? 'checked="checked"' : '';
                echo '<label><input type="checkbox" name="bber_options[display_currencies][]" value="' . esc_attr($code) . '" ' . $checked . ' /> ' . esc_html($code) . ' - ' . esc_html($rate['name']) . '</label><br>';
            }
            echo '</div>';
        } else {
            echo '<p class="bber-error">' . __('Could not fetch currencies. Please try again later.', 'bangkok-bank-exchange-rates') . '</p>';
            echo '<input type="hidden" name="bber_options[display_currencies]" value="' . esc_attr(json_encode($display_currencies)) . '" />';
        }
        
        echo '<p class="description">' . __('Select which currencies to display in the exchange rates table.', 'bangkok-bank-exchange-rates') . '</p>';
    }

    /**
     * Cache duration field callback.
     */
    public function cache_duration_callback() {
        $options = get_option('bber_options', array());
        $cache_duration = isset($options['cache_duration']) ? intval($options['cache_duration']) : 3600;
        ?>
        <input type="number" min="300" max="86400" name="bber_options[cache_duration]" value="<?php echo esc_attr($cache_duration); ?>" class="medium-text" />
        <p class="description"><?php _e('How long to cache exchange rates in seconds. Minimum 300 (5 minutes), maximum 86400 (24 hours).', 'bangkok-bank-exchange-rates'); ?></p>
        <?php
    }

    /**
     * Validate options before saving.
     *
     * @param array $input The options to validate.
     * @return array Validated options.
     */
    public function validate_options($input) {
        $validated = array();
        
        // Validate markup percentage
        $validated['markup_percentage'] = isset($input['markup_percentage']) ? floatval($input['markup_percentage']) : 3;
        if ($validated['markup_percentage'] < 0) {
            $validated['markup_percentage'] = 0;
        }
        if ($validated['markup_percentage'] > 100) {
            $validated['markup_percentage'] = 100;
        }
        
        // Validate display currencies
        $validated['display_currencies'] = isset($input['display_currencies']) && is_array($input['display_currencies']) ? $input['display_currencies'] : array('USD', 'EUR', 'GBP', 'JPY', 'CNY', 'AUD', 'SGD');
        
        // Validate cache duration
        $validated['cache_duration'] = isset($input['cache_duration']) ? intval($input['cache_duration']) : 3600;
        if ($validated['cache_duration'] < 300) {
            $validated['cache_duration'] = 300;
        }
        if ($validated['cache_duration'] > 86400) {
            $validated['cache_duration'] = 86400;
        }
        
        // Preserve last updated timestamp
        $options = get_option('bber_options', array());
        $validated['last_updated'] = isset($options['last_updated']) ? $options['last_updated'] : '';
        
        return $validated;
    }
}
