<?php
/**
 * Plugin Name: Exchange rates
 * Plugin URI: https://example.com/plugins/exchange-rates
 * Description: Display exchange rates from Bangkok Bank with a modern GUI interface and rate calculator.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: exchange-rates
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('BBER_VERSION', '1.0.0');
define('BBER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BBER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BBER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once BBER_PLUGIN_DIR . 'includes/class-bber-loader.php';
require_once BBER_PLUGIN_DIR . 'includes/class-bber-rates-fetcher.php';
require_once BBER_PLUGIN_DIR . 'includes/class-bber-admin.php';
require_once BBER_PLUGIN_DIR . 'includes/class-bber-public.php';

/**
 * Begins execution of the plugin.
 */
function run_exchange_rates() {
    // Initialize the plugin
    $plugin = new BBER_Loader();
    $plugin->run();
}

// Run the plugin
run_exchange_rates();
