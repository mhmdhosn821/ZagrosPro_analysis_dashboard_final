<?php
/**
 * Plugin Name: Zagros Glass Analytics
 * Plugin URI: https://github.com/mhmdhosn821/ZagrosPro_analysis_dashboard_final
 * Description: داشبورد تحلیلی شیشه‌ای برای نمایش گزارشات Looker Studio و Clarity
 * Version: 1.0.0
 * Author: Zagros Pro
 * Author URI: https://github.com/mhmdhosn821
 * Text Domain: zagros-glass-analytics
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ZAGROS_ANALYTICS_VERSION', '1.0.0');
define('ZAGROS_ANALYTICS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZAGROS_ANALYTICS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class Zagros_Glass_Analytics {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'آمار و عملکرد',           // Page title
            'آمار و عملکرد',           // Menu title
            'manage_options',          // Capability
            'zagros-analytics',        // Menu slug
            array($this, 'render_dashboard_page'), // Callback
            'dashicons-chart-area',    // Icon
            30                         // Position
        );
        
        // Settings submenu
        add_submenu_page(
            'zagros-analytics',        // Parent slug
            'تنظیمات',                 // Page title
            'تنظیمات',                 // Menu title
            'manage_options',          // Capability
            'zagros-analytics-settings', // Menu slug
            array($this, 'render_settings_page') // Callback
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('zagros_analytics_settings', 'zagros_analytics_looker_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => 'https://lookerstudio.google.com/embed/reporting/725e7835-b666-4fc6-9149-db250d49b930/page/kIV1C'
        ));
        
        register_setting('zagros_analytics_settings', 'zagros_analytics_clarity_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
        
        register_setting('zagros_analytics_settings', 'zagros_analytics_dashboard_height', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 2125
        ));
    }
    
    /**
     * Get default or saved settings
     */
    private function get_setting($key, $default = '') {
        $value = get_option($key, $default);
        return !empty($value) ? $value : $default;
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        // Only load on our plugin pages
        if ($hook !== 'toplevel_page_zagros-analytics' && $hook !== 'آمار-و-عملکرد_page_zagros-analytics-settings') {
            return;
        }
        
        // Enqueue styles inline
        add_action('admin_head', array($this, 'add_inline_styles'));
        
        // Enqueue scripts inline
        add_action('admin_footer', array($this, 'add_inline_scripts'));
    }
    
    /**
     * Add inline styles
     */
    public function add_inline_styles() {
        ?>
        <style>
            @import url('https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/font-face.css');
            
            body, html {
                font-family: 'Vazir', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
            }
            
            .zagros-analytics-wrapper {
                direction: rtl;
                text-align: right;
                margin: 20px 20px 20px 0;
            }
            
            .zagros-glass-container {
                background: linear-gradient(135deg, rgba(30, 30, 50, 0.95), rgba(50, 50, 80, 0.9));
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border-radius: 20px;
                border: 1px solid rgba(255, 255, 255, 0.18);
                box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
                padding: 30px;
                margin: 0;
                min-height: calc(100vh - 120px);
            }
            
            .zagros-header {
                margin-bottom: 30px;
            }
            
            .zagros-title {
                color: #fff;
                font-size: 28px;
                font-weight: 700;
                margin: 0 0 10px 0;
                text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            }
            
            .zagros-subtitle {
                color: rgba(255, 255, 255, 0.8);
                font-size: 16px;
                margin: 0;
            }
            
            .zagros-tabs {
                display: flex;
                gap: 10px;
                margin-bottom: 25px;
                border-bottom: 2px solid rgba(255, 255, 255, 0.1);
                padding-bottom: 0;
            }
            
            .zagros-tab-button {
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-bottom: none;
                border-radius: 10px 10px 0 0;
                color: rgba(255, 255, 255, 0.7);
                padding: 12px 30px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                font-family: 'Vazir', sans-serif;
            }
            
            .zagros-tab-button:hover {
                background: rgba(255, 255, 255, 0.15);
                color: rgba(255, 255, 255, 0.9);
            }
            
            .zagros-tab-button.active {
                background: rgba(255, 255, 255, 0.25);
                color: #fff;
                border-color: rgba(255, 255, 255, 0.3);
                box-shadow: 0 -2px 10px rgba(255, 255, 255, 0.1);
            }
            
            .zagros-tab-content {
                display: none;
            }
            
            .zagros-tab-content.active {
                display: block;
            }
            
            .zagros-iframe-container {
                background: rgba(255, 255, 255, 0.05);
                border-radius: 15px;
                padding: 10px;
                overflow: auto;
                max-height: 2200px;
            }
            
            .zagros-iframe {
                width: 100%;
                border: none;
                border-radius: 10px;
                display: block;
                background: #fff;
            }
            
            .zagros-empty-message {
                background: rgba(255, 200, 100, 0.15);
                border: 2px dashed rgba(255, 200, 100, 0.5);
                border-radius: 15px;
                padding: 60px 30px;
                text-align: center;
                color: rgba(255, 255, 255, 0.9);
            }
            
            .zagros-empty-message-icon {
                font-size: 64px;
                margin-bottom: 20px;
                opacity: 0.6;
            }
            
            .zagros-empty-message-title {
                font-size: 24px;
                font-weight: 700;
                margin-bottom: 15px;
            }
            
            .zagros-empty-message-text {
                font-size: 16px;
                opacity: 0.8;
                line-height: 1.6;
            }
            
            .zagros-settings-container {
                background: #fff;
                border-radius: 10px;
                padding: 30px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                max-width: 900px;
            }
            
            .zagros-settings-title {
                font-size: 24px;
                font-weight: 700;
                margin-bottom: 25px;
                color: #1e1e32;
                border-bottom: 3px solid #0073aa;
                padding-bottom: 15px;
            }
            
            .zagros-form-group {
                margin-bottom: 25px;
            }
            
            .zagros-form-label {
                display: block;
                font-size: 16px;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1e1e32;
            }
            
            .zagros-form-input {
                width: 100%;
                padding: 12px 15px;
                font-size: 14px;
                border: 2px solid #ddd;
                border-radius: 5px;
                transition: border-color 0.3s ease;
                font-family: 'Vazir', sans-serif;
                direction: ltr;
                text-align: left;
            }
            
            .zagros-form-input:focus {
                border-color: #0073aa;
                outline: none;
                box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
            }
            
            .zagros-form-help {
                font-size: 13px;
                color: #666;
                margin-top: 5px;
                font-style: italic;
            }
            
            .zagros-submit-button {
                background: linear-gradient(135deg, #0073aa, #005a87);
                color: #fff;
                border: none;
                border-radius: 5px;
                padding: 12px 30px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                font-family: 'Vazir', sans-serif;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            
            .zagros-submit-button:hover {
                background: linear-gradient(135deg, #005a87, #004368);
                box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
                transform: translateY(-2px);
            }
            
            .zagros-success-message {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                color: #155724;
                padding: 12px 20px;
                border-radius: 5px;
                margin-bottom: 20px;
                font-weight: 600;
            }
            
            /* Custom scrollbar */
            .zagros-iframe-container::-webkit-scrollbar {
                width: 12px;
            }
            
            .zagros-iframe-container::-webkit-scrollbar-track {
                background: rgba(255, 255, 255, 0.1);
                border-radius: 10px;
            }
            
            .zagros-iframe-container::-webkit-scrollbar-thumb {
                background: rgba(255, 255, 255, 0.3);
                border-radius: 10px;
            }
            
            .zagros-iframe-container::-webkit-scrollbar-thumb:hover {
                background: rgba(255, 255, 255, 0.5);
            }
        </style>
        <?php
    }
    
    /**
     * Add inline scripts
     */
    public function add_inline_scripts() {
        ?>
        <script>
        (function() {
            'use strict';
            
            // Tab switching functionality
            const tabButtons = document.querySelectorAll('.zagros-tab-button');
            const tabContents = document.querySelectorAll('.zagros-tab-content');
            
            tabButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(function(btn) {
                        btn.classList.remove('active');
                    });
                    tabContents.forEach(function(content) {
                        content.classList.remove('active');
                    });
                    
                    // Add active class to clicked button and corresponding content
                    this.classList.add('active');
                    document.getElementById(targetTab).classList.add('active');
                });
            });
        })();
        </script>
        <?php
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'zagros-glass-analytics'));
        }
        
        // Get settings with defaults
        $looker_url = $this->get_setting(
            'zagros_analytics_looker_url',
            'https://lookerstudio.google.com/embed/reporting/725e7835-b666-4fc6-9149-db250d49b930/page/kIV1C'
        );
        $clarity_url = $this->get_setting('zagros_analytics_clarity_url', '');
        $dashboard_height = $this->get_setting('zagros_analytics_dashboard_height', 2125);
        
        ?>
        <div class="wrap zagros-analytics-wrapper">
            <div class="zagros-glass-container">
                <div class="zagros-header">
                    <h1 class="zagros-title">📊 داشبورد تحلیلی زاگرس</h1>
                    <p class="zagros-subtitle">مشاهده گزارشات جامع و تحلیل عملکرد</p>
                </div>
                
                <div class="zagros-tabs">
                    <button class="zagros-tab-button active" data-tab="tab-report">
                        📈 گزارش جامع فروش
                    </button>
                    <button class="zagros-tab-button" data-tab="tab-clarity">
                        🔴 رصد زنده (Clarity)
                    </button>
                </div>
                
                <div id="tab-report" class="zagros-tab-content active">
                    <div class="zagros-iframe-container">
                        <iframe 
                            class="zagros-iframe"
                            src="<?php echo esc_url($looker_url); ?>"
                            height="<?php echo esc_attr($dashboard_height); ?>"
                            sandbox="allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>
                
                <div id="tab-clarity" class="zagros-tab-content">
                    <?php if (!empty($clarity_url)): ?>
                        <div class="zagros-iframe-container">
                            <iframe 
                                class="zagros-iframe"
                                src="<?php echo esc_url($clarity_url); ?>"
                                height="<?php echo esc_attr($dashboard_height); ?>"
                                sandbox="allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                                allowfullscreen
                            ></iframe>
                        </div>
                    <?php else: ?>
                        <div class="zagros-empty-message">
                            <div class="zagros-empty-message-icon">🔗</div>
                            <div class="zagros-empty-message-title">لینک لایو تنظیم نشده است</div>
                            <div class="zagros-empty-message-text">
                                برای نمایش داشبورد رصد زنده، لطفاً از بخش تنظیمات لینک Clarity را وارد کنید.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('شما دسترسی لازم برای مشاهده این صفحه را ندارید.', 'zagros-glass-analytics'));
        }
        
        // Handle form submission
        if (isset($_POST['zagros_analytics_save_settings'])) {
            // Verify nonce
            if (!isset($_POST['zagros_analytics_nonce']) || 
                !wp_verify_nonce($_POST['zagros_analytics_nonce'], 'zagros_analytics_settings_action')) {
                wp_die(__('خطای امنیتی. لطفاً دوباره تلاش کنید.', 'zagros-glass-analytics'));
            }
            
            // Sanitize and save settings
            if (isset($_POST['zagros_analytics_looker_url'])) {
                update_option('zagros_analytics_looker_url', esc_url_raw($_POST['zagros_analytics_looker_url']));
            }
            
            if (isset($_POST['zagros_analytics_clarity_url'])) {
                update_option('zagros_analytics_clarity_url', esc_url_raw($_POST['zagros_analytics_clarity_url']));
            }
            
            if (isset($_POST['zagros_analytics_dashboard_height'])) {
                update_option('zagros_analytics_dashboard_height', absint($_POST['zagros_analytics_dashboard_height']));
            }
            
            $settings_saved = true;
        }
        
        // Get current settings
        $looker_url = $this->get_setting(
            'zagros_analytics_looker_url',
            'https://lookerstudio.google.com/embed/reporting/725e7835-b666-4fc6-9149-db250d49b930/page/kIV1C'
        );
        $clarity_url = $this->get_setting('zagros_analytics_clarity_url', '');
        $dashboard_height = $this->get_setting('zagros_analytics_dashboard_height', 2125);
        
        ?>
        <div class="wrap zagros-analytics-wrapper">
            <div class="zagros-settings-container">
                <h1 class="zagros-settings-title">⚙️ تنظیمات داشبورد تحلیلی</h1>
                
                <?php if (isset($settings_saved) && $settings_saved): ?>
                    <div class="zagros-success-message">
                        ✅ تنظیمات با موفقیت ذخیره شد!
                    </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <?php wp_nonce_field('zagros_analytics_settings_action', 'zagros_analytics_nonce'); ?>
                    
                    <div class="zagros-form-group">
                        <label class="zagros-form-label" for="zagros_analytics_looker_url">
                            🔗 لینک گزارش Looker Studio (تب اول)
                        </label>
                        <input 
                            type="url" 
                            id="zagros_analytics_looker_url" 
                            name="zagros_analytics_looker_url" 
                            class="zagros-form-input"
                            value="<?php echo esc_attr($looker_url); ?>"
                            placeholder="https://lookerstudio.google.com/embed/reporting/..."
                        />
                        <p class="zagros-form-help">
                            لینک embed گزارش Looker Studio را وارد کنید
                        </p>
                    </div>
                    
                    <div class="zagros-form-group">
                        <label class="zagros-form-label" for="zagros_analytics_clarity_url">
                            🔴 لینک رصد زنده Clarity (تب دوم)
                        </label>
                        <input 
                            type="url" 
                            id="zagros_analytics_clarity_url" 
                            name="zagros_analytics_clarity_url" 
                            class="zagros-form-input"
                            value="<?php echo esc_attr($clarity_url); ?>"
                            placeholder="https://clarity.microsoft.com/..."
                        />
                        <p class="zagros-form-help">
                            لینک داشبورد Microsoft Clarity را وارد کنید (اختیاری)
                        </p>
                    </div>
                    
                    <div class="zagros-form-group">
                        <label class="zagros-form-label" for="zagros_analytics_dashboard_height">
                            📏 ارتفاع داشبورد (پیکسل)
                        </label>
                        <input 
                            type="number" 
                            id="zagros_analytics_dashboard_height" 
                            name="zagros_analytics_dashboard_height" 
                            class="zagros-form-input"
                            value="<?php echo esc_attr($dashboard_height); ?>"
                            min="500"
                            max="5000"
                            step="25"
                        />
                        <p class="zagros-form-help">
                            ارتفاع iframe به پیکسل (پیشفرض: 2125)
                        </p>
                    </div>
                    
                    <button type="submit" name="zagros_analytics_save_settings" class="zagros-submit-button">
                        💾 ذخیره تنظیمات
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
function zagros_glass_analytics_init() {
    return Zagros_Glass_Analytics::get_instance();
}

// Hook initialization
add_action('plugins_loaded', 'zagros_glass_analytics_init');
