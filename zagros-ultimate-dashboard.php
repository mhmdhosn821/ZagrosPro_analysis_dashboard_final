<?php
/**
 * Plugin Name: Zagros Ultimate Dashboard
 * Plugin URI: https://github.com/mhmdhosn821/ZagrosPro_analysis_dashboard_final
 * Description: Advanced analytics dashboard with native GA4 integration and Microsoft Clarity - Glassmorphism Design
 * Version: 1.0.0
 * Author: Zagros Pro
 * Author URI: https://github.com/mhmdhosn821
 * Text Domain: zagros-ultimate-dashboard
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ZAGROS_ULTIMATE_VERSION', '1.0.0');
define('ZAGROS_ULTIMATE_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Google Service Account Authentication Class
 * Pure PHP implementation without external libraries
 */
class Zagros_Google_Auth {
    
    private $service_account_data;
    private $access_token;
    private $token_expiry;
    
    /**
     * Constructor
     */
    public function __construct($service_account_json) {
        if (is_string($service_account_json)) {
            $this->service_account_data = json_decode($service_account_json, true);
        } else {
            $this->service_account_data = $service_account_json;
        }
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($this->service_account_data)) {
            throw new Exception('Invalid service account JSON');
        }
    }
    
    /**
     * Get access token
     */
    public function get_access_token() {
        // Check if we have a valid cached token
        if ($this->access_token && $this->token_expiry && time() < $this->token_expiry) {
            return $this->access_token;
        }
        
        // Generate new token
        $jwt = $this->create_jwt();
        $token_data = $this->request_access_token($jwt);
        
        if (isset($token_data['access_token'])) {
            $this->access_token = $token_data['access_token'];
            $this->token_expiry = time() + ($token_data['expires_in'] ?? 3600) - 60; // 60 seconds buffer
            return $this->access_token;
        }
        
        throw new Exception('Failed to obtain access token');
    }
    
    /**
     * Create JWT (JSON Web Token)
     */
    private function create_jwt() {
        $now = time();
        $expiry = $now + 3600; // 1 hour
        
        $header = array(
            'alg' => 'RS256',
            'typ' => 'JWT'
        );
        
        $claim_set = array(
            'iss' => $this->service_account_data['client_email'],
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $expiry,
            'iat' => $now
        );
        
        $header_encoded = $this->base64url_encode(json_encode($header));
        $claim_set_encoded = $this->base64url_encode(json_encode($claim_set));
        
        $signature_input = $header_encoded . '.' . $claim_set_encoded;
        $signature = $this->sign_data($signature_input);
        
        return $signature_input . '.' . $signature;
    }
    
    /**
     * Sign data with private key using OpenSSL
     */
    private function sign_data($data) {
        $private_key = $this->service_account_data['private_key'];
        
        $key_resource = openssl_pkey_get_private($private_key);
        
        if (!$key_resource) {
            throw new Exception('Invalid private key');
        }
        
        $signature = '';
        $success = openssl_sign($data, $signature, $key_resource, OPENSSL_ALGO_SHA256);
        
        openssl_free_key($key_resource);
        
        if (!$success) {
            throw new Exception('Failed to sign data');
        }
        
        return $this->base64url_encode($signature);
    }
    
    /**
     * Base64 URL encode
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Request access token from Google
     */
    private function request_access_token($jwt) {
        $url = 'https://oauth2.googleapis.com/token';
        
        $post_data = array(
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        );
        
        $args = array(
            'body' => $post_data,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('HTTP request failed: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Google');
        }
        
        if (isset($data['error'])) {
            throw new Exception('Google API error: ' . ($data['error_description'] ?? $data['error']));
        }
        
        return $data;
    }
}

/**
 * GA4 Data Fetcher Class
 */
class Zagros_GA4_Data {
    
    private $property_id;
    private $auth;
    
    /**
     * Constructor
     */
    public function __construct($property_id, $service_account_json) {
        $this->property_id = $property_id;
        
        try {
            $this->auth = new Zagros_Google_Auth($service_account_json);
        } catch (Exception $e) {
            throw new Exception('Authentication setup failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get realtime active users
     */
    public function get_realtime_users() {
        // Check cache
        $cache_key = 'zagros_ga4_realtime_users';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        try {
            $access_token = $this->auth->get_access_token();
            
            $url = sprintf(
                'https://analyticsdata.googleapis.com/v1beta/properties/%s:runRealtimeReport',
                $this->property_id
            );
            
            $request_body = array(
                'metrics' => array(
                    array('name' => 'activeUsers')
                )
            );
            
            $args = array(
                'body' => json_encode($request_body),
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                )
            );
            
            $response = wp_remote_post($url, $args);
            
            if (is_wp_error($response)) {
                throw new Exception('Realtime API request failed: ' . $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['error'])) {
                throw new Exception('GA4 API error: ' . ($data['error']['message'] ?? 'Unknown error'));
            }
            
            $active_users = 0;
            if (isset($data['rows'][0]['metricValues'][0]['value'])) {
                $active_users = intval($data['rows'][0]['metricValues'][0]['value']);
            }
            
            // Cache for 2 minutes
            set_transient($cache_key, $active_users, 120);
            
            return $active_users;
            
        } catch (Exception $e) {
            error_log('Zagros GA4 Realtime Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get historical data (last 30 days)
     */
    public function get_historical_data() {
        // Check cache
        $cache_key = 'zagros_ga4_historical_data';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        try {
            $access_token = $this->auth->get_access_token();
            
            $url = sprintf(
                'https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport',
                $this->property_id
            );
            
            $request_body = array(
                'dateRanges' => array(
                    array(
                        'startDate' => '30daysAgo',
                        'endDate' => 'today'
                    )
                ),
                'dimensions' => array(
                    array('name' => 'date')
                ),
                'metrics' => array(
                    array('name' => 'sessions'),
                    array('name' => 'totalUsers')
                ),
                'orderBys' => array(
                    array(
                        'dimension' => array('dimensionName' => 'date'),
                        'desc' => false
                    )
                )
            );
            
            $args = array(
                'body' => json_encode($request_body),
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json'
                )
            );
            
            $response = wp_remote_post($url, $args);
            
            if (is_wp_error($response)) {
                throw new Exception('Historical API request failed: ' . $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['error'])) {
                throw new Exception('GA4 API error: ' . ($data['error']['message'] ?? 'Unknown error'));
            }
            
            $result = array(
                'labels' => array(),
                'sessions' => array(),
                'users' => array(),
                'total_sessions' => 0,
                'total_users' => 0
            );
            
            if (isset($data['rows'])) {
                foreach ($data['rows'] as $row) {
                    $date = $row['dimensionValues'][0]['value'];
                    $sessions = intval($row['metricValues'][0]['value']);
                    $users = intval($row['metricValues'][1]['value']);
                    
                    // Format date
                    $formatted_date = date('M d', strtotime($date));
                    
                    $result['labels'][] = $formatted_date;
                    $result['sessions'][] = $sessions;
                    $result['users'][] = $users;
                    $result['total_sessions'] += $sessions;
                    $result['total_users'] += $users;
                }
            }
            
            // Cache for 1 hour
            set_transient($cache_key, $result, 3600);
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Zagros GA4 Historical Error: ' . $e->getMessage());
            return null;
        }
    }
}

/**
 * Main Plugin Class
 */
class Zagros_Ultimate_Dashboard {
    
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
        // Main dashboard menu
        add_menu_page(
            'Zagros Dashboard',
            'Zagros Dashboard',
            'manage_options',
            'zagros-ultimate-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-analytics',
            25
        );
        
        // Settings submenu
        add_submenu_page(
            'zagros-ultimate-dashboard',
            'Dashboard Settings',
            'Settings',
            'manage_options',
            'zagros-ultimate-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('zagros_ultimate_settings', 'zagros_ga4_property_id', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        
        register_setting('zagros_ultimate_settings', 'zagros_service_account_json', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_json'),
            'default' => ''
        ));
        
        register_setting('zagros_ultimate_settings', 'zagros_clarity_embed_url', array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => ''
        ));
    }
    
    /**
     * Sanitize JSON input
     */
    public function sanitize_json($input) {
        if (empty($input)) {
            return '';
        }
        
        // Validate JSON
        $decoded = json_decode($input, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $input;
        }
        
        add_settings_error(
            'zagros_service_account_json',
            'invalid_json',
            'Invalid JSON format for Service Account Key',
            'error'
        );
        
        return get_option('zagros_service_account_json', '');
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'zagros-ultimate') === false) {
            return;
        }
        
        if ($hook === 'toplevel_page_zagros-ultimate-dashboard') {
            // Enqueue Chart.js from CDN
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true);
        }
        
        // Add inline styles
        add_action('admin_head', array($this, 'add_inline_styles'));
        
        // Add inline scripts
        if ($hook === 'toplevel_page_zagros-ultimate-dashboard') {
            add_action('admin_footer', array($this, 'add_inline_scripts'));
        }
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
            
            .zagros-ultimate-wrapper {
                margin: 20px 20px 20px 0;
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                min-height: calc(100vh - 60px);
                padding: 30px;
            }
            
            .zagros-ultimate-header {
                margin-bottom: 30px;
                text-align: center;
            }
            
            .zagros-ultimate-title {
                color: #fff;
                font-size: 32px;
                font-weight: 700;
                margin: 0 0 10px 0;
                text-shadow: 0 0 20px rgba(0, 230, 255, 0.5);
            }
            
            .zagros-ultimate-subtitle {
                color: rgba(255, 255, 255, 0.7);
                font-size: 16px;
            }
            
            /* Glass Card Styles */
            .zagros-glass-card {
                background: rgba(255, 255, 255, 0.05);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 16px;
                padding: 25px;
                box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
                transition: all 0.3s ease;
            }
            
            .zagros-glass-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 12px 40px 0 rgba(0, 230, 255, 0.3);
            }
            
            /* Grid Layout */
            .zagros-metrics-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin-bottom: 20px;
            }
            
            @media (max-width: 1200px) {
                .zagros-metrics-grid {
                    grid-template-columns: repeat(2, 1fr);
                }
            }
            
            @media (max-width: 768px) {
                .zagros-metrics-grid {
                    grid-template-columns: 1fr;
                }
            }
            
            /* Metric Card */
            .zagros-metric-card {
                text-align: center;
            }
            
            .zagros-metric-label {
                color: rgba(255, 255, 255, 0.7);
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 1px;
                margin-bottom: 10px;
            }
            
            .zagros-metric-value {
                color: #00e6ff;
                font-size: 48px;
                font-weight: 700;
                line-height: 1;
                text-shadow: 0 0 20px rgba(0, 230, 255, 0.5);
            }
            
            .zagros-metric-value.realtime {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 15px;
            }
            
            /* Pulsing Dot Animation */
            .zagros-pulse-dot {
                width: 20px;
                height: 20px;
                background: #00ff00;
                border-radius: 50%;
                box-shadow: 0 0 20px rgba(0, 255, 0, 0.8);
                animation: pulse 2s ease-in-out infinite;
            }
            
            @keyframes pulse {
                0%, 100% {
                    transform: scale(1);
                    opacity: 1;
                }
                50% {
                    transform: scale(1.3);
                    opacity: 0.7;
                }
            }
            
            /* Chart Container */
            .zagros-chart-container {
                margin-bottom: 20px;
            }
            
            .zagros-chart-title {
                color: #fff;
                font-size: 20px;
                font-weight: 600;
                margin-bottom: 20px;
            }
            
            #zagrosDailyChart {
                max-height: 400px;
            }
            
            /* Clarity Container */
            .zagros-clarity-container {
                margin-bottom: 20px;
            }
            
            .zagros-clarity-title {
                color: #fff;
                font-size: 20px;
                font-weight: 600;
                margin-bottom: 20px;
            }
            
            .zagros-clarity-iframe {
                width: 100%;
                height: 800px;
                border: none;
                border-radius: 12px;
                background: #fff;
            }
            
            /* Error Message */
            .zagros-error-message {
                background: rgba(255, 100, 100, 0.15);
                border: 2px solid rgba(255, 100, 100, 0.5);
                border-radius: 12px;
                padding: 20px;
                color: #ff6b6b;
                text-align: center;
                margin: 20px 0;
            }
            
            .zagros-error-message h3 {
                margin: 0 0 10px 0;
                color: #ff6b6b;
            }
            
            /* Settings Page Styles */
            .zagros-settings-container {
                max-width: 900px;
                margin: 30px auto;
                background: #fff;
                border-radius: 12px;
                padding: 30px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            }
            
            .zagros-settings-title {
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 30px;
                padding-bottom: 15px;
                border-bottom: 3px solid #0073aa;
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
                border-radius: 6px;
                transition: border-color 0.3s ease;
                font-family: 'Vazir', monospace;
            }
            
            .zagros-form-input:focus {
                border-color: #0073aa;
                outline: none;
                box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
            }
            
            .zagros-form-textarea {
                height: 200px;
                font-family: monospace;
                font-size: 13px;
                resize: vertical;
            }
            
            .zagros-form-description {
                font-size: 13px;
                color: #666;
                margin-top: 6px;
                line-height: 1.5;
            }
            
            .zagros-form-description code {
                background: #f5f5f5;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: monospace;
            }
            
            .zagros-submit-button {
                background: #0073aa;
                color: #fff;
                border: none;
                padding: 12px 30px;
                font-size: 16px;
                font-weight: 600;
                border-radius: 6px;
                cursor: pointer;
                transition: background 0.3s ease;
                font-family: 'Vazir', sans-serif;
            }
            
            .zagros-submit-button:hover {
                background: #005a87;
            }
            
            /* Loading State */
            .zagros-loading {
                text-align: center;
                padding: 40px;
                color: rgba(255, 255, 255, 0.7);
            }
            
            .zagros-loading-spinner {
                border: 4px solid rgba(255, 255, 255, 0.1);
                border-top: 4px solid #00e6ff;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                animation: spin 1s linear infinite;
                margin: 0 auto 20px;
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
        <?php
    }
    
    /**
     * Add inline scripts
     */
    public function add_inline_scripts() {
        $property_id = get_option('zagros_ga4_property_id', '');
        $service_account = get_option('zagros_service_account_json', '');
        
        if (empty($property_id) || empty($service_account)) {
            return;
        }
        
        try {
            $ga4 = new Zagros_GA4_Data($property_id, $service_account);
            $historical = $ga4->get_historical_data();
            
            if ($historical && !empty($historical['labels'])) {
                ?>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var ctx = document.getElementById('zagrosDailyChart');
                    if (ctx) {
                        ctx = ctx.getContext('2d');
                        
                        var gradient = ctx.createLinearGradient(0, 0, 0, 400);
                        gradient.addColorStop(0, 'rgba(0, 230, 255, 0.4)');
                        gradient.addColorStop(1, 'rgba(0, 230, 255, 0.0)');
                        
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode($historical['labels']); ?>,
                                datasets: [{
                                    label: 'Daily Sessions',
                                    data: <?php echo json_encode($historical['sessions']); ?>,
                                    borderColor: '#00e6ff',
                                    backgroundColor: gradient,
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4,
                                    pointBackgroundColor: '#00e6ff',
                                    pointBorderColor: '#fff',
                                    pointBorderWidth: 2,
                                    pointRadius: 4,
                                    pointHoverRadius: 6
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: true,
                                plugins: {
                                    legend: {
                                        display: true,
                                        labels: {
                                            color: '#fff',
                                            font: {
                                                family: 'Vazir',
                                                size: 14
                                            }
                                        }
                                    },
                                    tooltip: {
                                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                        titleColor: '#00e6ff',
                                        bodyColor: '#fff',
                                        borderColor: '#00e6ff',
                                        borderWidth: 1,
                                        padding: 12,
                                        displayColors: false
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            color: 'rgba(255, 255, 255, 0.7)',
                                            font: {
                                                family: 'Vazir'
                                            }
                                        },
                                        grid: {
                                            color: 'rgba(255, 255, 255, 0.1)'
                                        }
                                    },
                                    x: {
                                        ticks: {
                                            color: 'rgba(255, 255, 255, 0.7)',
                                            font: {
                                                family: 'Vazir'
                                            },
                                            maxRotation: 45,
                                            minRotation: 45
                                        },
                                        grid: {
                                            color: 'rgba(255, 255, 255, 0.1)'
                                        }
                                    }
                                }
                            }
                        });
                    }
                });
                </script>
                <?php
            }
        } catch (Exception $e) {
            // Silent fail for chart initialization
            error_log('Zagros Chart Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $property_id = get_option('zagros_ga4_property_id', '');
        $service_account = get_option('zagros_service_account_json', '');
        $clarity_url = get_option('zagros_clarity_embed_url', '');
        
        ?>
        <div class="zagros-ultimate-wrapper">
            <div class="zagros-ultimate-header">
                <h1 class="zagros-ultimate-title">Zagros Ultimate Dashboard</h1>
                <p class="zagros-ultimate-subtitle">Advanced Analytics with Google Analytics 4 & Microsoft Clarity</p>
            </div>
            
            <?php if (empty($property_id) || empty($service_account)): ?>
                <div class="zagros-error-message">
                    <h3>⚙️ Configuration Required</h3>
                    <p>Please configure your GA4 Property ID and Service Account JSON in the <a href="<?php echo admin_url('admin.php?page=zagros-ultimate-settings'); ?>" style="color: #00e6ff;">Settings page</a>.</p>
                </div>
            <?php else: ?>
                <?php
                try {
                    $ga4 = new Zagros_GA4_Data($property_id, $service_account);
                    $realtime_users = $ga4->get_realtime_users();
                    $historical = $ga4->get_historical_data();
                    
                    if ($realtime_users === null || $historical === null) {
                        throw new Exception('Failed to fetch GA4 data. Please check your configuration.');
                    }
                    ?>
                    
                    <!-- Top Row: Key Metrics -->
                    <div class="zagros-metrics-grid">
                        <div class="zagros-glass-card zagros-metric-card">
                            <div class="zagros-metric-label">Active Users (Realtime)</div>
                            <div class="zagros-metric-value realtime">
                                <span class="zagros-pulse-dot"></span>
                                <span><?php echo number_format($realtime_users); ?></span>
                            </div>
                        </div>
                        
                        <div class="zagros-glass-card zagros-metric-card">
                            <div class="zagros-metric-label">Total Sessions (30 Days)</div>
                            <div class="zagros-metric-value">
                                <?php echo number_format($historical['total_sessions']); ?>
                            </div>
                        </div>
                        
                        <div class="zagros-glass-card zagros-metric-card">
                            <div class="zagros-metric-label">Total Users (30 Days)</div>
                            <div class="zagros-metric-value">
                                <?php echo number_format($historical['total_users']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Middle Row: Chart -->
                    <div class="zagros-glass-card zagros-chart-container">
                        <h2 class="zagros-chart-title">Daily Sessions Trend (Last 30 Days)</h2>
                        <canvas id="zagrosDailyChart"></canvas>
                    </div>
                    
                <?php
                } catch (Exception $e) {
                    ?>
                    <div class="zagros-error-message">
                        <h3>❌ Error Loading GA4 Data</h3>
                        <p><?php echo esc_html($e->getMessage()); ?></p>
                        <p>Please verify your Service Account JSON and Property ID in the <a href="<?php echo admin_url('admin.php?page=zagros-ultimate-settings'); ?>" style="color: #00e6ff;">Settings page</a>.</p>
                    </div>
                    <?php
                }
                ?>
            <?php endif; ?>
            
            <!-- Bottom Row: Clarity Iframe -->
            <?php if (!empty($clarity_url)): ?>
                <div class="zagros-glass-card zagros-clarity-container">
                    <h2 class="zagros-clarity-title">Microsoft Clarity Live Dashboard</h2>
                    <iframe 
                        src="<?php echo esc_url($clarity_url); ?>"
                        class="zagros-clarity-iframe"
                        sandbox="allow-same-origin allow-scripts allow-popups allow-forms"
                        loading="lazy"
                    ></iframe>
                </div>
            <?php else: ?>
                <div class="zagros-glass-card zagros-clarity-container">
                    <h2 class="zagros-clarity-title">Microsoft Clarity Live Dashboard</h2>
                    <div class="zagros-error-message">
                        <h3>🔗 Clarity URL Not Configured</h3>
                        <p>Please add your Microsoft Clarity embed URL in the <a href="<?php echo admin_url('admin.php?page=zagros-ultimate-settings'); ?>" style="color: #00e6ff;">Settings page</a>.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Save settings if form submitted
        if (isset($_POST['zagros_ultimate_save_settings'])) {
            check_admin_referer('zagros_ultimate_settings_save');
            
            update_option('zagros_ga4_property_id', sanitize_text_field($_POST['zagros_ga4_property_id']));
            update_option('zagros_service_account_json', $this->sanitize_json($_POST['zagros_service_account_json']));
            update_option('zagros_clarity_embed_url', esc_url_raw($_POST['zagros_clarity_embed_url']));
            
            // Clear caches
            delete_transient('zagros_ga4_realtime_users');
            delete_transient('zagros_ga4_historical_data');
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $property_id = get_option('zagros_ga4_property_id', '');
        $service_account = get_option('zagros_service_account_json', '');
        $clarity_url = get_option('zagros_clarity_embed_url', '');
        ?>
        
        <div class="wrap">
            <div class="zagros-settings-container">
                <h1 class="zagros-settings-title">Zagros Ultimate Dashboard - Settings</h1>
                
                <form method="post" action="">
                    <?php wp_nonce_field('zagros_ultimate_settings_save'); ?>
                    
                    <div class="zagros-form-group">
                        <label class="zagros-form-label" for="zagros_ga4_property_id">
                            GA4 Property ID
                        </label>
                        <input 
                            type="text" 
                            id="zagros_ga4_property_id" 
                            name="zagros_ga4_property_id" 
                            class="zagros-form-input" 
                            value="<?php echo esc_attr($property_id); ?>"
                            placeholder="123456789"
                        />
                        <p class="zagros-form-description">
                            Enter your Google Analytics 4 Property ID (numeric value only). Find it in GA4 Admin > Property Settings.
                        </p>
                    </div>
                    
                    <div class="zagros-form-group">
                        <label class="zagros-form-label" for="zagros_service_account_json">
                            Service Account JSON Key
                        </label>
                        <textarea 
                            id="zagros_service_account_json" 
                            name="zagros_service_account_json" 
                            class="zagros-form-input zagros-form-textarea"
                            placeholder='{"type": "service_account", "project_id": "...", ...}'
                        ><?php echo esc_textarea($service_account); ?></textarea>
                        <p class="zagros-form-description">
                            Paste the entire JSON key file content from Google Cloud Console. 
                            <br><strong>Steps:</strong>
                            <br>1. Go to Google Cloud Console > IAM & Admin > Service Accounts
                            <br>2. Create a service account or select existing one
                            <br>3. Create and download JSON key
                            <br>4. Add service account email to GA4 property with "Viewer" role
                        </p>
                    </div>
                    
                    <div class="zagros-form-group">
                        <label class="zagros-form-label" for="zagros_clarity_embed_url">
                            Microsoft Clarity Embed URL
                        </label>
                        <input 
                            type="url" 
                            id="zagros_clarity_embed_url" 
                            name="zagros_clarity_embed_url" 
                            class="zagros-form-input" 
                            value="<?php echo esc_attr($clarity_url); ?>"
                            placeholder="https://clarity.microsoft.com/projects/view/..."
                        />
                        <p class="zagros-form-description">
                            Enter the shareable link from Microsoft Clarity dashboard. Go to Clarity > Settings > Share to get the URL.
                        </p>
                    </div>
                    
                    <button type="submit" name="zagros_ultimate_save_settings" class="zagros-submit-button">
                        Save Settings
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
}

// Initialize plugin
function zagros_ultimate_dashboard_init() {
    return Zagros_Ultimate_Dashboard::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'zagros_ultimate_dashboard_init');
