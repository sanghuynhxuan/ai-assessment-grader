<?php

namespace LearnDashAIGrader\Admin;

class TestingModePage {

    public function render_page() {
        $settings = get_option( 'ld_ai_grader_settings' );
        
        require_once LD_AI_PLUGIN_DIR . 'templates/admin/testing-mode.php';
    }
}