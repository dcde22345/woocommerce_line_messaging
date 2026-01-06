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
        // Super Socializer 使用 Line_userId (注意大小寫)
        $line_user_id = get_user_meta($user->ID, 'Line_userId', true);
        
        // 如果沒有找到，嘗試從我們自己儲存的欄位讀取
        if (empty($line_user_id)) {
            $line_user_id = get_user_meta($user->ID, 'line_user_id', true);
        }
        
        if (!empty($line_user_id)) {
            // Super Socializer 可能使用的欄位名稱
            $line_display_name = get_user_meta($user->ID, 'Line_displayName', true);
            if (empty($line_display_name)) {
                $line_display_name = get_user_meta($user->ID, 'line_display_name', true);
            }
            
            $line_picture_url = get_user_meta($user->ID, 'Line_pictureUrl', true);
            if (empty($line_picture_url)) {
                $line_picture_url = get_user_meta($user->ID, 'line_picture_url', true);
            }
            
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
        // 檢查用戶是否存在
        if (!self::user_exists($user_id)) {
            error_log('[WLM] 嘗試為不存在的用戶 (ID: ' . $user_id . ') 保存 LINE 資料');
            return false;
        }
        
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
        // 首先檢查 WordPress users 表中是否存在該用戶
        if (!self::user_exists($user_id)) {
            return null;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wlm_line_users';
        
        $line_user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT line_user_id FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        // 如果資料表中沒有，嘗試從 user meta 獲取
        if (empty($line_user_id)) {
            // 優先使用 Super Socializer 的欄位 Line_userId
            $line_user_id = get_user_meta($user_id, 'Line_userId', true);
            
            // 如果還是沒有，嘗試從我們自己儲存的欄位讀取
            if (empty($line_user_id)) {
                $line_user_id = get_user_meta($user_id, 'line_user_id', true);
            }
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
        // 首先檢查 WordPress users 表中是否存在該用戶
        if (!self::user_exists($user_id)) {
            return null;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wlm_line_users';
        
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        return $data ?: null;
    }
    
    /**
     * 檢查 WordPress 用戶是否存在
     *
     * @param int $user_id WordPress User ID
     * @return bool 用戶是否存在
     */
    private static function user_exists($user_id) {
        if (empty($user_id) || !is_numeric($user_id)) {
            return false;
        }
        
        // 使用 WordPress 內建函數檢查用戶是否存在
        $user = get_userdata($user_id);
        
        if (!$user || !$user->exists()) {
            return false;
        }
        
        // 額外檢查：直接查詢資料庫確認用戶存在
        global $wpdb;
        $user_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} WHERE ID = %d",
            $user_id
        ));
        
        return $user_exists > 0;
    }
    
    /**
     * 同步所有 Super Socializer 的 LINE 使用者到我們的資料表
     * 這個函數可以在管理後台手動執行
     *
     * @return array 同步結果 array('success' => count, 'failed' => count)
     */
    public static function sync_existing_line_users() {
        global $wpdb;
        
        $success_count = 0;
        $failed_count = 0;
        
        // 查詢所有有 Line_userId 的使用者
        $line_users = $wpdb->get_results(
            "SELECT user_id, meta_value as line_user_id 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'Line_userId'"
        );
        
        if (empty($line_users)) {
            return array('success' => 0, 'failed' => 0, 'message' => '沒有找到使用 LINE Login 的使用者');
        }
        
        foreach ($line_users as $user) {
            // 檢查用戶是否還存在於 WordPress users 表中
            if (!self::user_exists($user->user_id)) {
                $failed_count++;
                continue;
            }
            
            $line_display_name = get_user_meta($user->user_id, 'Line_displayName', true);
            $line_picture_url = get_user_meta($user->user_id, 'Line_pictureUrl', true);
            
            $result = self::save_line_user_data(
                $user->user_id,
                $user->line_user_id,
                $line_display_name,
                $line_picture_url
            );
            
            if ($result) {
                $success_count++;
            } else {
                $failed_count++;
            }
        }
        
        return array(
            'success' => $success_count,
            'failed' => $failed_count,
            'message' => "成功同步 {$success_count} 位使用者，失敗 {$failed_count} 位"
        );
    }
}

// 初始化
WLM_User_Data_Handler::init();

