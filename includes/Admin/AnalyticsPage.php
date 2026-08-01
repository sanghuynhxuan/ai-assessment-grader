<?php

namespace LearnDashAIGrader\Admin;

class AnalyticsPage {

    public function render_page() {
        require_once LD_AI_PLUGIN_DIR . 'templates/admin/analytics-dashboard.php';
    }
}