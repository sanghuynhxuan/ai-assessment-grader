<?php

namespace LearnDashAIGrader\Admin;

class Menu {

    private $settings_instance;
    private $analytics_instance;
    private $testing_instance;
    private $logs_instance; 

    public function __construct( $settings_instance, $analytics_instance, $testing_instance, $logs_instance ) {
        $this->settings_instance = $settings_instance;
        $this->analytics_instance = $analytics_instance;
        $this->testing_instance = $testing_instance;
        $this->logs_instance = $logs_instance; 
    }

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ), 20 );
    }

    public function add_plugin_admin_menu() {
        add_menu_page( 'AI Grader', 'AI Grader', 'manage_options', 'ld-ai-grader', array( $this->settings_instance, 'render_settings_page' ), 'dashicons-superhero', 50 );
        add_submenu_page( 'ld-ai-grader', 'Settings', 'Settings', 'manage_options', 'ld-ai-grader', array( $this->settings_instance, 'render_settings_page' ) );
        add_submenu_page( 'ld-ai-grader', 'Analytics', 'Analytics', 'manage_options', 'ld-ai-grader-analytics', array( $this->analytics_instance, 'render_page' ) );
        add_submenu_page( 'ld-ai-grader', 'Testing Mode', 'Testing Mode', 'manage_options', 'ld-ai-grader-testing', array( $this->testing_instance, 'render_page' ) );

        add_submenu_page(
            'ld-ai-grader',
            __( 'System Logs', 'learndash-ai-grader' ),
            __( 'Logs', 'learndash-ai-grader' ),
            'manage_options',
            'ld-ai-grader-logs',
            array( $this->logs_instance, 'render_page' )
        );
    }
}