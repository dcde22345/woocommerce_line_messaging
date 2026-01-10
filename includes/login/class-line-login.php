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
        
        // 註冊頭像過濾器
        add_filter('get_avatar_url', array(__CLASS__, 'filter_avatar_url'), 10, 3);
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
        $line_email = isset($_POST['line_email']) ? sanitize_email($_POST['line_email']) : '';
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
        
        // 更新 WordPress 用戶資料（email 和頭像）
        self::update_wordpress_user_profile($user_id, $line_display_name, $line_picture_url, $access_token, $line_email);
        
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
     * 更新 WordPress 用戶資料（email 和頭像）
     *
     * @param int    $user_id           WordPress User ID
     * @param string $display_name      LINE 顯示名稱
     * @param string $picture_url       LINE 頭像 URL
     * @param string $access_token      LINE Access Token（用於獲取 email）
     * @param string $line_email        LINE Email（從 ID Token 獲取）
     * @return void
     */
    private static function update_wordpress_user_profile($user_id, $display_name, $picture_url, $access_token = '', $line_email = '') {
        $user_data = array('ID' => $user_id);
        
        // 更新顯示名稱
        if (!empty($display_name)) {
            $user_data['display_name'] = $display_name;
        }
        
        // 優先使用從 ID Token 獲取的 email
        if (!empty($line_email) && is_email($line_email)) {
            // 檢查 email 是否已被其他用戶使用
            $existing_user = get_user_by('email', $line_email);
            if (!$existing_user || $existing_user->ID == $user_id) {
                $user_data['user_email'] = $line_email;
            }
        } else {
            // 如果 ID Token 沒有 email，嘗試從 LINE API 獲取
            if (!empty($access_token)) {
                $api_email = self::get_line_user_email($access_token);
                if (!empty($api_email) && is_email($api_email)) {
                    // 檢查 email 是否已被其他用戶使用
                    $existing_user = get_user_by('email', $api_email);
                    if (!$existing_user || $existing_user->ID == $user_id) {
                        $user_data['user_email'] = $api_email;
                    }
                }
            }
        }
        
        // 更新用戶資料
        if (count($user_data) > 1) {
            wp_update_user($user_data);
        }
        
        // 下載並設置頭像
        if (!empty($picture_url)) {
            self::set_user_avatar_from_url($user_id, $picture_url);
        }
    }
    
    /**
     * 從 LINE API 獲取用戶 email
     *
     * @param string $access_token LINE Access Token
     * @return string|null Email 或 null
     */
    private static function get_line_user_email($access_token) {
        if (empty($access_token)) {
            return null;
        }
        
        // LINE API v2 獲取 email（需要 email scope）
        $response = wp_remote_get('https://api.line.me/v2/profile', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return null;
        }
        
        $profile = json_decode(wp_remote_retrieve_body($response), true);
        
        // LINE API 通常不提供 email，除非用戶授權 email scope
        return isset($profile['email']) ? sanitize_email($profile['email']) : null;
    }
    
    /**
     * 從 URL 下載並設置用戶頭像
     *
     * @param int    $user_id    WordPress User ID
     * @param string $avatar_url 頭像 URL
     * @return bool 是否成功
     */
    private static function set_user_avatar_from_url($user_id, $avatar_url) {
        if (empty($avatar_url)) {
            return false;
        }
        
        // 檢查是否已經有頭像，避免重複下載
        $existing_avatar_id = get_user_meta($user_id, 'wlm_line_avatar_id', true);
        if ($existing_avatar_id && wp_attachment_is_image($existing_avatar_id)) {
            return true;
        }
        
        // 下載圖片
        $response = wp_remote_get($avatar_url, array(
            'timeout' => 15,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            error_log('[WLM] 下載頭像失敗: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('[WLM] 下載頭像失敗 - HTTP ' . $response_code);
            return false;
        }
        
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            return false;
        }
        
        // 獲取文件擴展名和 MIME 類型
        $file_extension = 'jpg';
        $mime_type = 'image/jpeg';
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        if (strpos($content_type, 'png') !== false) {
            $file_extension = 'png';
            $mime_type = 'image/png';
        } elseif (strpos($content_type, 'gif') !== false) {
            $file_extension = 'gif';
            $mime_type = 'image/gif';
        } elseif (strpos($content_type, 'webp') !== false) {
            $file_extension = 'webp';
            $mime_type = 'image/webp';
        }
        
        // 使用 WordPress 臨時文件功能
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        // 創建臨時文件
        $temp_file = wp_tempnam('line-avatar-' . $user_id);
        if (!$temp_file) {
            error_log('[WLM] 無法創建臨時文件');
            return false;
        }
        
        // 保存圖片到臨時位置
        file_put_contents($temp_file, $image_data);
        
        // 準備上傳文件
        $filename = 'line-avatar-' . $user_id . '-' . time() . '.' . $file_extension;
        $file_array = array(
            'name' => $filename,
            'tmp_name' => $temp_file,
            'size' => filesize($temp_file),
            'type' => $mime_type
        );
        
        // 使用 WordPress 媒體庫上傳
        $attachment_id = media_handle_sideload($file_array, 0);
        
        // 清理臨時文件（media_handle_sideload 通常會處理，但確保清理）
        if (file_exists($temp_file)) {
            @unlink($temp_file);
        }
        
        if (is_wp_error($attachment_id)) {
            error_log('[WLM] 上傳頭像失敗: ' . $attachment_id->get_error_message());
            return false;
        }
        
        // 設置為用戶頭像
        // 方法 1: 使用 Simple Local Avatars 外掛（如果安裝）
        if (function_exists('simple_local_avatars')) {
            update_user_meta($user_id, 'simple_local_avatar', $attachment_id);
        }
        
        // 方法 2: 使用自定義 meta（通用方法）
        update_user_meta($user_id, 'wlm_line_avatar_id', $attachment_id);
        update_user_meta($user_id, 'wlm_line_avatar_url', wp_get_attachment_url($attachment_id));
        
        return true;
    }
    
    /**
     * 過濾頭像 URL，使用 LINE 頭像
     *
     * @param string $url         原始頭像 URL
     * @param mixed  $id_or_email 用戶 ID 或 email
     * @param array  $args        參數
     * @return string 頭像 URL
     */
    public static function filter_avatar_url($url, $id_or_email, $args) {
        $user_id = null;
        
        // 獲取用戶 ID
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        } elseif (is_object($id_or_email)) {
            if (isset($id_or_email->user_id)) {
                $user_id = (int) $id_or_email->user_id;
            } elseif (isset($id_or_email->ID)) {
                $user_id = (int) $id_or_email->ID;
            }
        }
        
        if (!$user_id) {
            return $url;
        }
        
        // 優先使用 Simple Local Avatars（如果安裝）
        if (function_exists('simple_local_avatars')) {
            $avatar_id = get_user_meta($user_id, 'simple_local_avatar', true);
            if ($avatar_id) {
                $avatar_url = wp_get_attachment_image_url($avatar_id, 'thumbnail');
                if ($avatar_url) {
                    return $avatar_url;
                }
            }
        }
        
        // 使用我們保存的頭像
        $avatar_id = get_user_meta($user_id, 'wlm_line_avatar_id', true);
        if ($avatar_id) {
            $avatar_url = wp_get_attachment_image_url($avatar_id, isset($args['size']) ? $args['size'] : 'thumbnail');
            if ($avatar_url) {
                return $avatar_url;
            }
        }
        
        // 如果沒有本地頭像，使用 LINE 頭像 URL（作為備用）
        $line_avatar_url = get_user_meta($user_id, 'wlm_line_avatar_url', true);
        if ($line_avatar_url) {
            return $line_avatar_url;
        }
        
        $line_picture_url = get_user_meta($user_id, 'line_picture_url', true);
        if ($line_picture_url) {
            return $line_picture_url;
        }
        
        return $url;
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

