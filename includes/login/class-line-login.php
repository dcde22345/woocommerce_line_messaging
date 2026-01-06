<?php
/**
 * LINE Login (LIFF) 處理類別
 *
 * @package WooCommerce_LINE_Messaging
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

class WLM_Line_Login {
    
    /**
     * 初始化
     */
    public static function init() {
        // 註冊 AJAX handler
        add_action('wp_ajax_wlm_line_liff_login', array(__CLASS__, 'handle_liff_login'));
        add_action('wp_ajax_nopriv_wlm_line_liff_login', array(__CLASS__, 'handle_liff_login'));
        
        // 註冊 shortcode
        add_shortcode('wlm_line_login_button', array(__CLASS__, 'render_login_button'));
        
        // 註冊 LIFF 頁面處理
        add_action('template_redirect', array(__CLASS__, 'handle_liff_page'));
    }
    
    /**
     * 處理 LINE LIFF 登入
     */
    public static function handle_liff_login() {
        // 驗證 nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wlm_line_liff_login')) {
            wp_send_json_error(array('message' => '安全驗證失敗'));
            return;
        }
        
        // 獲取 LINE 資料
        $line_user_id = isset($_POST['line_user_id']) ? sanitize_text_field($_POST['line_user_id']) : '';
        $line_display_name = isset($_POST['line_display_name']) ? sanitize_text_field($_POST['line_display_name']) : '';
        $line_picture_url = isset($_POST['line_picture_url']) ? esc_url_raw($_POST['line_picture_url']) : '';
        $access_token = isset($_POST['access_token']) ? sanitize_text_field($_POST['access_token']) : '';
        
        if (empty($line_user_id)) {
            wp_send_json_error(array('message' => 'LINE User ID 不能為空'));
            return;
        }
        
        // 可選：驗證 Access Token
        if (get_option('wlm_line_login_verify_token', 'yes') === 'yes') {
            $is_valid = self::verify_line_access_token($access_token, $line_user_id);
            if (!$is_valid) {
                wp_send_json_error(array('message' => 'Token 驗證失敗'));
                return;
            }
        }
        
        // 檢查是否已有 WordPress User 綁定此 LINE User ID
        global $wpdb;
        $table_name = $wpdb->prefix . 'wlm_line_users';
        $existing_user = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id FROM $table_name WHERE line_user_id = %s",
            $line_user_id
        ));
        
        $user_id = null;
        
        if ($existing_user) {
            // 已有綁定的使用者，直接登入
            $user_id = $existing_user->user_id;
        } else {
            // 檢查是否啟用自動建立使用者
            $auto_create = get_option('wlm_line_login_auto_create_user', 'yes');
            
            if ($auto_create === 'yes') {
                // 自動建立新使用者
                $user_id = self::create_or_get_user_from_line($line_user_id, $line_display_name);
            } else {
                // 要求使用者先註冊 WordPress 帳號
                wp_send_json_error(array(
                    'message' => '請先註冊 WordPress 帳號後再綁定 LINE',
                    'require_registration' => true
                ));
                return;
            }
        }
        
        if (!$user_id) {
            wp_send_json_error(array('message' => '無法建立或找到使用者'));
            return;
        }
        
        // 儲存 LINE 使用者資料
        WLM_User_Data_Handler::save_line_user_data($user_id, $line_user_id, $line_display_name, $line_picture_url);
        
        // WordPress 登入
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        
        // 觸發登入 hook（讓其他外掛知道使用者已登入）
        $user = get_userdata($user_id);
        if ($user) {
            do_action('wp_login', $user->user_login, $user);
        }
        
        // 獲取導向 URL
        $redirect_url = get_option('wlm_line_login_redirect_url', home_url());
        $redirect_url = apply_filters('wlm_liff_login_redirect', $redirect_url, $user_id);
        
        wp_send_json_success(array(
            'message' => '登入成功',
            'redirect_url' => $redirect_url
        ));
    }
    
    /**
     * 從 LINE 資料建立或取得 WordPress User
     *
     * @param string $line_user_id LINE User ID
     * @param string $display_name LINE 顯示名稱
     * @return int|null WordPress User ID 或 null
     */
    private static function create_or_get_user_from_line($line_user_id, $display_name) {
        // 使用 LINE User ID 作為 username（需要加上前綴避免衝突）
        $username = 'line_' . substr($line_user_id, 0, 20);
        
        // 檢查使用者是否已存在
        $user = get_user_by('login', $username);
        
        if ($user) {
            return $user->ID;
        }
        
        // 建立新使用者
        // 使用隨機密碼（使用者無法用密碼登入，只能透過 LINE）
        $random_password = wp_generate_password(32, false);
        
        // 使用虛擬 email（LINE User ID + @line.local）
        $email = 'line_' . $line_user_id . '@line.local';
        
        // 檢查 email 是否已被使用
        if (email_exists($email)) {
            $email = 'line_' . $line_user_id . '_' . time() . '@line.local';
        }
        
        $user_id = wp_create_user(
            $username,
            $random_password,
            $email
        );
        
        if (is_wp_error($user_id)) {
            error_log('[WLM] 建立使用者失敗: ' . $user_id->get_error_message());
            return null;
        }
        
        // 更新顯示名稱
        wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $display_name ?: 'LINE User'
        ));
        
        return $user_id;
    }
    
    /**
     * 驗證 LINE Access Token
     *
     * @param string $access_token LINE Access Token
     * @param string $line_user_id LINE User ID
     * @return bool
     */
    private static function verify_line_access_token($access_token, $line_user_id) {
        if (empty($access_token)) {
            return false;
        }
        
        // 使用 LINE API 驗證 token
        $response = wp_remote_get('https://api.line.me/v2/profile', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            error_log('[WLM] Token 驗證請求錯誤: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('[WLM] Token 驗證失敗 - HTTP ' . $response_code);
            return false;
        }
        
        $profile = json_decode(wp_remote_retrieve_body($response), true);
        
        // 驗證 User ID 是否匹配
        if (!isset($profile['userId']) || $profile['userId'] !== $line_user_id) {
            error_log('[WLM] Token 驗證失敗 - User ID 不匹配');
            return false;
        }
        
        return true;
    }
    
    /**
     * 處理 LIFF 頁面請求
     */
    public static function handle_liff_page() {
        // 檢查是否為 LIFF 頁面請求
        if (!isset($_GET['wlm_line_login']) || $_GET['wlm_line_login'] !== '1') {
            return;
        }
        
        // 檢查是否啟用 LINE Login
        if (get_option('wlm_line_login_enabled') !== 'yes') {
            wp_die('LINE Login 功能未啟用', 'LINE Login', array('response' => 403));
            return;
        }
        
        // 載入 LIFF 頁面模板
        $template_path = WLM_PLUGIN_DIR . 'includes/login/templates/line-login.php';
        
        if (file_exists($template_path)) {
            include $template_path;
            exit;
        } else {
            wp_die('LIFF 頁面模板不存在', 'LINE Login', array('response' => 500));
        }
    }
    
    /**
     * 渲染 LINE Login 按鈕 Shortcode
     *
     * @param array $atts Shortcode 屬性
     * @return string
     */
    public static function render_login_button($atts) {
        // 檢查是否啟用 LINE Login
        if (get_option('wlm_line_login_enabled') !== 'yes') {
            return '';
        }
        
        $atts = shortcode_atts(array(
            'text' => '使用 LINE 登入',
            'class' => 'wlm-line-login-button',
            'redirect' => ''
        ), $atts);
        
        $login_url = home_url('/?wlm_line_login=1');
        
        if (!empty($atts['redirect'])) {
            $login_url = add_query_arg('redirect', urlencode($atts['redirect']), $login_url);
        }
        
        return sprintf(
            '<a href="%s" class="%s" target="_blank">%s</a>',
            esc_url($login_url),
            esc_attr($atts['class']),
            esc_html($atts['text'])
        );
    }
    
    /**
     * 獲取 LIFF ID
     *
     * @return string
     */
    public static function get_liff_id() {
        return get_option('wlm_line_liff_id', '');
    }
}

