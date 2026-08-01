<?php

namespace LearnDashAIGrader\Core;

/**
 * The file that defines the core plugin class.
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 */
class Plugin {

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		if ( defined( 'LD_AI_VERSION' ) ) {
			$this->version = LD_AI_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'learndash-ai-grader';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_integration_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 * We use the Loader class to handle all actions and filters.
	 */
	private function load_dependencies() {
		require_once LD_AI_PLUGIN_DIR . 'includes/Core/Loader.php';
		$this->loader = new Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function set_locale() {
		$this->loader->add_action( 'plugins_loaded', $this, 'load_plugin_textdomain' );
	}

	/**
	 * Load the text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'learndash-ai-grader',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	/**
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		// 1. Settings Logic
		require_once LD_AI_PLUGIN_DIR . 'includes/Admin/Settings.php';
		$plugin_settings = new \LearnDashAIGrader\Admin\Settings();
		$this->loader->add_action( 'admin_init', $plugin_settings, 'init' );

		require_once LD_AI_PLUGIN_DIR . 'includes/Admin/QuestionMetabox.php';
        $question_metabox = new \LearnDashAIGrader\Admin\QuestionMetabox();
        $this->loader->add_action( 'admin_init', $question_metabox, 'init' );

		// 2. Analytics Page Logic
		require_once LD_AI_PLUGIN_DIR . 'includes/Admin/AnalyticsPage.php';
		$analytics_page = new \LearnDashAIGrader\Admin\AnalyticsPage();

		// 3. Testing Mode Page Logic
		require_once LD_AI_PLUGIN_DIR . 'includes/Admin/TestingModePage.php';
		$testing_page = new \LearnDashAIGrader\Admin\TestingModePage();

		// 1. Khởi tạo LOGS PAGE (MỚI)
        require_once LD_AI_PLUGIN_DIR . 'includes/Admin/LogsPage.php';
        $logs_page = new \LearnDashAIGrader\Admin\LogsPage();

        // 2. Truyền $logs_page vào Menu
        require_once LD_AI_PLUGIN_DIR . 'includes/Admin/Menu.php';
        $plugin_menu = new \LearnDashAIGrader\Admin\Menu( $plugin_settings, $analytics_page, $testing_page, $logs_page );
        $plugin_menu->init();

		// 5. Admin API (Stats for Charts)
		require_once LD_AI_PLUGIN_DIR . 'includes/Api/AdminController.php';
		$admin_api = new \LearnDashAIGrader\Api\AdminController();
		$this->loader->add_action( 'init', $admin_api, 'init' );

		// 6. Testing API (Run Test Prompt)
		require_once LD_AI_PLUGIN_DIR . 'includes/Api/TestingController.php';
		$testing_api = new \LearnDashAIGrader\Api\TestingController();
		$this->loader->add_action( 'init', $testing_api, 'init' );

		// 7. Enqueue Admin Scripts & Styles (CSS/JS)
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_assets' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality.
	 */
	private function define_public_hooks() {
		// 1. Frontend API (Polling Result & Student Feedback)
		require_once LD_AI_PLUGIN_DIR . 'includes/Api/FrontendController.php';
		$frontend_api = new \LearnDashAIGrader\Api\FrontendController();
		$this->loader->add_action( 'init', $frontend_api, 'init' );

		// 2. Enqueue Frontend Scripts (Polling JS)
		$this->loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_frontend_assets' );
	}

	/**
	 * Register hooks for Integrations (LearnDash & Action Scheduler)
	 */
	private function define_integration_hooks() {
        // Load Async Handler
        require_once LD_AI_PLUGIN_DIR . 'includes/Engine/AsyncJobHandler.php';
        $async_handler = new \LearnDashAIGrader\Engine\AsyncJobHandler(); // Chú ý Namespace
        $this->loader->add_action( 'init', $async_handler, 'init' );

        // Load Quiz Hooks
        require_once LD_AI_PLUGIN_DIR . 'includes/Integrations/LearnDashQuizHooks.php';
        $ld_hooks = new \LearnDashAIGrader\Integrations\LearnDashQuizHooks(); // Chú ý Namespace
        $ld_hooks->init(); // <--- DÒNG NÀY CỰC QUAN TRỌNG
    }
	/**
	 * Callback: Enqueue Admin Assets
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our plugin pages to optimize performance
		if ( strpos( $hook, 'ld-ai-grader' ) === false ) {
			return;
		}

		// CSS chung cho Admin
		wp_enqueue_style( 'ld-ai-admin-css', LD_AI_PLUGIN_URL . 'assets/css/admin.css', array(), $this->version, 'all' );

		// 1. Load Chart.js for Analytics Page
		if ( strpos( $hook, 'ld-ai-grader-analytics' ) !== false ) {
			wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true );
			wp_enqueue_script( 'ld-ai-admin-charts', LD_AI_PLUGIN_URL . 'assets/js/admin-charts.js', ['jquery', 'chart-js'], $this->version, true );
			
			// Pass API URL to JS
			wp_localize_script( 'ld-ai-admin-charts', 'ldAiAdminVars', [
				'apiUrl' => rest_url( 'ld-ai-grader/v1/admin/stats' ),
				'nonce'  => wp_create_nonce( 'wp_rest' )
			]);
		}

		// 2. Load Testing Script for Testing Page
		if ( strpos( $hook, 'ld-ai-grader-testing' ) !== false ) {
			wp_enqueue_script( 'ld-ai-testing', LD_AI_PLUGIN_URL . 'assets/js/admin-testing.js', ['jquery'], $this->version, true );
			
			// Pass API URL to JS
			wp_localize_script( 'ld-ai-testing', 'ldAiTestVars', [
				'apiUrl' => rest_url( 'ld-ai-grader/v1/test/run' ),
				'nonce'  => wp_create_nonce( 'wp_rest' )
			]);
		}
	}

	/**
	 * Callback: Enqueue Frontend Assets
	 */
	public function enqueue_frontend_assets() {
		// Thêm 'sfwd-essays' vào danh sách cho phép
		if ( is_singular( 'sfwd-quiz' ) || is_singular( 'sfwd-courses' ) || is_singular( 'sfwd-topic' ) || is_singular( 'sfwd-lessons' ) || is_singular( 'sfwd-essays' ) ) {
			
			// Styles
			wp_enqueue_style( 'ld-ai-frontend', LD_AI_PLUGIN_URL . 'assets/css/frontend.css', array(), $this->version, 'all' );

			// Scripts
			wp_enqueue_script( 'ld-ai-poller', LD_AI_PLUGIN_URL . 'assets/js/frontend-poller.js', array( 'jquery' ), $this->version, true );

			// Pass API URL to JS
			wp_localize_script( 'ld-ai-poller', 'ldAiVars', array(
				'apiUrl' => esc_url_raw( rest_url( 'ld-ai-grader/v1' ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
			));
		}
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}
}