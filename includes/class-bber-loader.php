<?php
/**
 * The main loader class for the plugin.
 */
class BBER_Loader {
    /**
     * The array of actions registered with WordPress.
     */
    protected $actions;

    /**
     * The array of filters registered with WordPress.
     */
    protected $filters;

    /**
     * Initialize the collections used to maintain the actions and filters.
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        // Nothing to load here as dependencies are already included in main file
    }

    /**
     * Register all of the hooks related to the admin area functionality.
     */
    private function define_admin_hooks() {
        $plugin_admin = new BBER_Admin();
        
        // Admin scripts and styles
        $this->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        // Admin menu
        $this->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        
        // Register settings
        $this->add_action('admin_init', $plugin_admin, 'register_settings');
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
     */
    private function define_public_hooks() {
        $plugin_public = new BBER_Public();
        
        // Public scripts and styles
        $this->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        
        // Register shortcodes
        $this->add_action('init', $plugin_public, 'register_shortcodes');
        
        // Register AJAX handlers
        $this->add_action('wp_ajax_bber_get_exchange_rates', $plugin_public, 'ajax_get_exchange_rates');
        $this->add_action('wp_ajax_nopriv_bber_get_exchange_rates', $plugin_public, 'ajax_get_exchange_rates');
        
        // Schedule cron job for fetching rates
        $this->add_action('bber_cron_hook', $plugin_public, 'update_exchange_rates');
    }

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @param    string    $hook             The name of the WordPress action that is being registered.
     * @param    object    $component        A reference to the instance of the object on which the action is defined.
     * @param    string    $callback         The name of the function definition on the $component.
     * @param    int       $priority         Optional. The priority at which the function should be fired. Default is 10.
     * @param    int       $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @param    string    $hook             The name of the WordPress filter that is being registered.
     * @param    object    $component        A reference to the instance of the object on which the filter is defined.
     * @param    string    $callback         The name of the function definition on the $component.
     * @param    int       $priority         Optional. The priority at which the function should be fired. Default is 10.
     * @param    int       $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * A utility function that is used to register the actions and hooks into a single
     * collection.
     *
     * @param    array     $hooks            The collection of hooks that is being registered (that is, actions or filters).
     * @param    string    $hook             The name of the WordPress filter that is being registered.
     * @param    object    $component        A reference to the instance of the object on which the filter is defined.
     * @param    string    $callback         The name of the function definition on the $component.
     * @param    int       $priority         The priority at which the function should be fired.
     * @param    int       $accepted_args    The number of arguments that should be passed to the $callback.
     * @return   array                        The collection of actions and filters registered with WordPress.
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Register the filters and actions with WordPress.
     */
    public function run() {
        // Register activation and deactivation hooks
        register_activation_hook(BBER_PLUGIN_BASENAME, array($this, 'activate'));
        register_deactivation_hook(BBER_PLUGIN_BASENAME, array($this, 'deactivate'));

        // Register all actions
        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        // Register all filters
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }

    /**
     * The code that runs during plugin activation.
     */
    public function activate() {
        // Schedule cron job to update exchange rates
        if (!wp_next_scheduled('bber_cron_hook')) {
            wp_schedule_event(time(), 'hourly', 'bber_cron_hook');
        }

        // Set default options
        $default_options = array(
            'markup_percentage' => 3,
            'display_currencies' => array('USD', 'EUR', 'GBP', 'JPY', 'CNY', 'AUD', 'SGD'),
            'last_updated' => '',
            'cache_duration' => 3600, // 1 hour in seconds
        );

        add_option('bber_options', $default_options);
    }

    /**
     * The code that runs during plugin deactivation.
     */
    public function deactivate() {
        // Clear scheduled cron job
        wp_clear_scheduled_hook('bber_cron_hook');
    }
}
