<?php
/**
 * 處理 LINE 使用者資料的類別
 *
 * @package WooCommerce_LINE_Messaging
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

class WLM_User_Data_Handler {
    
    /**
     * 初始化
     */
    public static function init() {
        // 掛載 Super Socializer LINE Login 成功後的 hook
        add_action('the_champ_login_success', array(__CLASS__, 'handle_line_login'), 10, 2);
        
        // 掛載 WordPress 登入後的 hook 以備用
        add_action('wp_login', array(__CLASS__, 'handle_wp_login'), 10, 2);
    }
    
    /**
     * 處理 LINE Login 成功後的資料
     *
     * @param int   $user_id WordPress User ID
     * @param array $profile 社交平台的使用者資料
     */
    public static function handle_line_login($user_id, $profile) {
        // 檢查是否為 LINE Login
        if (!isset($profile['provider']) || $profile['provider'] !== 'line') {
            return;
        }
        
        // 從 profile 中提取 LINE User ID
        $line_user_id = isset($profile['id']) ? sanitize_text_field($profile['id']) : '';
        $line_display_name = isset($profile['name']) ? sanitize_text_field($profile['name']) : '';
        $line_picture_url = isset($profile['avatar']) ? esc_url_raw($profile['avatar']) : '';
        
        if (empty($line_user_id)) {
            error_log('[WLM] LINE User ID 為空');
            return;
        }
        
        // 儲存或更新 LINE 使用者資料
        self::save_line_user_data($user_id, $line_user_id, $line_display_name, $line_picture_url);
    }
    
    /**
     * 處理 WordPress 登入
     *
     * @param string  $user_login 使用者登入名稱
     * @param WP_User $user       使用者物件
     */
    public static function handle_wp_login($user_login, $user) {
        // 嘗試從 user meta 獲取 LINE 資料
        $line_user_id = get_user_meta($user->ID, 'line_user_id', true);
        
        if (!empty($line_user_id)) {
            $line_display_name = get_user_meta($user->ID, 'line_display_name', true);
            $line_picture_url = get_user_meta($user->ID, 'line_picture_url', true);
            
            self::save_line_user_data($user->ID, $line_user_id, $line_display_name, $line_picture_url);
        }
    }
    
    /**
     * 儲存 LINE 使用者資料到資料庫
     *
     * @param int    $user_id           WordPress User ID
     * @param string $line_user_id      LINE User ID
     * @param string $line_display_name LINE 顯示名稱
     * @param string $line_picture_url  LINE 頭像 URL
     * @return bool
     */
    public static function save_line_user_data($user_id, $line_user_id, $line_display_name = '', $line_picture_url = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wlm_line_users';
        
        // 檢查是否已存在
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing) {
            // 更新現有記錄
            $result = $wpdb->update(
                $table_name,
                array(
                    'line_user_id' => $line_user_id,
                    'line_display_name' => $line_display_name,
                    'line_picture_url' => $line_picture_url,
                    'updated_at' => current_time('mysql')
                ),
                array('user_id' => $user_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // 插入新記錄
            $result = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'line_user_id' => $line_user_id,
                    'line_display_name' => $line_display_name,
                    'line_picture_url' => $line_picture_url
                ),
                array('%d', '%s', '%s', '%s')
            );
        }
        
        // 同時儲存到 user meta 作為備份
        update_user_meta($user_id, 'line_user_id', $line_user_id);
        update_user_meta($user_id, 'line_display_name', $line_display_name);
        update_user_meta($user_id, 'line_picture_url', $line_picture_url);
        
        return $result !== false;
    }
    
    /**
     * 根據 WordPress User ID 獲取 LINE User ID
     *
     * @param int $user_id WordPress User ID
     * @return string|null LINE User ID 或 null
     */
    public static function get_line_user_id($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wlm_line_users';
        
        $line_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT line_user_id FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        // 如果資料表中沒有，嘗試從 user meta 獲取
        if (empty($line_user_id)) {
            $line_user_id = get_user_meta($user_id, 'line_user_id', true);
        }
        
        return $line_user_id ?: null;
    }
    
    /**
     * 獲取 LINE 使用者的完整資料
     *
     * @param int $user_id WordPress User ID
     * @return object|null LINE 使用者資料物件或 null
     */
    public static function get_line_user_data($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wlm_line_users';
        
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        return $data ?: null;
    }
}

// 初始化
WLM_User_Data_Handler::init();

