<?php
/**
 * Plugin Name: Dokan Category Attributes Manager
 * Plugin URI: https://castingagency.co
 * Description: Manage category-specific attributes for Dokan vendors with dynamic fields, conditional display, and search filters.
 * Version: 1.0.0
 * Author: Casting Agency
 * Author URI: https://castingagency.co
 * Text Domain: dokan-category-attributes
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'DCA_VERSION', '1.0.0' );
define( 'DCA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DCA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DCA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Plugin Class
 */
final class Dokan_Category_Attributes {
	
	/**
	 * Plugin instance
	 * 
	 * @var Dokan_Category_Attributes
	 */
	private static $instance = null;
	
	/**
	 * Database handler
	 * 
	 * @var DCA_Database
	 */
	public $database;
	
	/**
	 * Attribute manager
	 * 
	 * @var DCA_Attribute_Manager
	 */
	public $attributes;
	
	/**
	 * Get plugin instance
	 * 
	 * @return Dokan_Category_Attributes
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}
	
	/**
	 * Include required files
	 */
	private function includes() {
		// Core classes
		require_once DCA_PLUGIN_DIR . 'includes/class-database.php';
		require_once DCA_PLUGIN_DIR . 'includes/class-attribute-manager.php';
		require_once DCA_PLUGIN_DIR . 'includes/class-frontend-display.php';
		require_once DCA_PLUGIN_DIR . 'includes/class-dashboard-fields.php';
		require_once DCA_PLUGIN_DIR . 'includes/class-store-filters.php';
		require_once DCA_PLUGIN_DIR . 'includes/class-sample-data.php';
		
		// Admin classes
		if ( is_admin() ) {
			require_once DCA_PLUGIN_DIR . 'includes/class-admin-menu.php';
			require_once DCA_PLUGIN_DIR . 'includes/class-ajax-handler.php';
		}
	}
	
	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Activation/deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		
		// Init plugin
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		
		// Enqueue assets
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
	}
	
	/**
	 * Initialize plugin
	 */
	public function init() {
		// Check if Dokan is active
		if ( ! class_exists( 'WeDevs_Dokan' ) ) {
			add_action( 'admin_notices', array( $this, 'dokan_missing_notice' ) );
			return;
		}
		
		// Initialize core classes
		$this->database = new DCA_Database();
		$this->attributes = new DCA_Attribute_Manager();
		
		// Initialize frontend classes
		new DCA_Frontend_Display();
		new DCA_Dashboard_Fields();
		new DCA_Store_Filters();
		
		// Initialize admin classes
		if ( is_admin() ) {
			new DCA_Admin_Menu();
			new DCA_Ajax_Handler();
		}
		
		// Load text domain
		load_plugin_textdomain( 'dokan-category-attributes', false, dirname( DCA_PLUGIN_BASENAME ) . '/languages' );
	}
	
	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create database tables
		require_once DCA_PLUGIN_DIR . 'includes/class-database.php';
		require_once DCA_PLUGIN_DIR . 'includes/class-attribute-manager.php';
		require_once DCA_PLUGIN_DIR . 'includes/class-sample-data.php';
		
		$database = new DCA_Database();
		$database->create_tables();
		
		// Install sample data
		DCA_Sample_Data::install();
		
		// Set default options
		add_option( 'dca_version', DCA_VERSION );
		add_option( 'dca_activated', time() );
		
		// Flush rewrite rules
		flush_rewrite_rules();
	}
	
	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();
	}
	
	/**
	 * Dokan missing notice
	 */
	public function dokan_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Dokan Category Attributes Manager', 'dokan-category-attributes' ); ?></strong> 
				<?php esc_html_e( 'requires Dokan plugin to be installed and activated.', 'dokan-category-attributes' ); ?>
			</p>
		</div>
		<?php
	}
	
	/**
	 * Enqueue admin scripts
	 */
	public function admin_scripts( $hook ) {
		// Only load on plugin pages
		if ( strpos( $hook, 'dokan-category-attributes' ) === false ) {
			return;
		}
		
		// Admin CSS
		wp_enqueue_style(
			'dca-admin',
			DCA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			DCA_VERSION
		);
		
		// Admin JS
		wp_enqueue_script(
			'dca-admin',
			DCA_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			DCA_VERSION,
			true
		);
		
		wp_localize_script( 'dca-admin', 'dcaAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'dca-admin-nonce' ),
			'i18n' => array(
				'confirmDelete' => __( 'Are you sure you want to delete this?', 'dokan-category-attributes' ),
				'fieldRequired' => __( 'This field is required.', 'dokan-category-attributes' ),
			)
		) );
	}
	
	/**
	 * Enqueue frontend scripts
	 */
	public function frontend_scripts() {
		// Frontend CSS
		wp_enqueue_style(
			'dca-frontend',
			DCA_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			DCA_VERSION
		);
		
		// Dashboard CSS (only on vendor dashboard)
		if ( function_exists( 'dokan_is_seller_dashboard' ) && dokan_is_seller_dashboard() ) {
			wp_enqueue_style(
				'dca-dashboard',
				DCA_PLUGIN_URL . 'assets/css/dashboard.css',
				array(),
				DCA_VERSION
			);
			
			wp_enqueue_script(
				'dca-dashboard',
				DCA_PLUGIN_URL . 'assets/js/dashboard.js',
				array( 'jquery' ),
				DCA_VERSION,
				true
			);
		}
		
		// Filter JS (only on store listing pages)
		wp_enqueue_script(
			'dca-filters',
			DCA_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			DCA_VERSION,
			true
		);
	}
}

/**
 * Main instance of plugin
 * 
 * @return Dokan_Category_Attributes
 */
function DCA() {
	return Dokan_Category_Attributes::instance();
}

// Initialize plugin
DCA();
