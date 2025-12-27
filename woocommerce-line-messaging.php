<?php
/**
 * Plugin Name: WooCommerce LINE Messaging
 * Plugin URI: https://github.com/dcde22345/woocommerce_line_messaging
 * Description: 整合 LINE Login 與 LINE Messaging API，在 WooCommerce 訂單建立時發送 LINE 通知給客戶
 * Version: 1.0.0
 * Author: Hank Tsai
 * Text Domain: woocommerce-line-messaging
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package WooCommerce_LINE_Messaging
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

// 定義常數
define('WLM_VERSION', '1.0.0');
define('WLM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WLM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WLM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * 檢查必要的外掛是否啟用
 */
function wlm_check_requirements() {
    $errors = array();
    
    // 檢查 WooCommerce
    if (!class_exists('WooCommerce')) {
        $errors[] = '此外掛需要安裝並啟用 WooCommerce。';
    }
    
    // 檢查 super-socializer
    if (!function_exists('the_champ_login_button')) {
        $errors[] = '此外掛需要安裝並啟用 Super Socializer 以使用 LINE Login 功能。';
    }
    
    if (!empty($errors)) {
        add_action('admin_notices', function() use ($errors) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>WooCommerce LINE Messaging:</strong></p>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        });
        return false;
    }
    
    return true;
}

/**
 * 宣告與 WooCommerce HPOS 的相容性
 */
function wlm_declare_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}
add_action('before_woocommerce_init', 'wlm_declare_compatibility');

/**
 * 載入外掛核心檔案
 */
function wlm_load_plugin() {
    if (!wlm_check_requirements()) {
        return;
    }
    
    // 載入核心類別
    require_once WLM_PLUGIN_DIR . 'includes/class-line-messaging.php';
    require_once WLM_PLUGIN_DIR . 'includes/class-order-notifier.php';
    require_once WLM_PLUGIN_DIR . 'includes/class-admin-settings.php';
    require_once WLM_PLUGIN_DIR . 'includes/class-user-data-handler.php';
    
    // 初始化管理設定
    WLM_Admin_Settings::init();
    
    // 初始化訂單通知
    WLM_Order_Notifier::init();
}
add_action('plugins_loaded', 'wlm_load_plugin');

/**
 * 外掛啟用時的處理
 */
function wlm_activate() {
    // 建立資料表儲存 LINE User ID 對應
    global $wpdb;
    $table_name = $wpdb->prefix . 'wlm_line_users';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        line_user_id varchar(255) NOT NULL,
        line_display_name varchar(255) DEFAULT NULL,
        line_picture_url text DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY user_id (user_id),
        UNIQUE KEY line_user_id (line_user_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // 自動同步所有現有的 LINE Login 使用者
    wlm_sync_existing_line_users_on_activation();
}
register_activation_hook(__FILE__, 'wlm_activate');

/**
 * 啟用時同步現有的 LINE 使用者
 */
function wlm_sync_existing_line_users_on_activation() {
    global $wpdb;
    
    // 查詢所有有 Line_userId 的使用者（Super Socializer 儲存的欄位）
    $line_users = $wpdb->get_results(
        "SELECT user_id, meta_value as line_user_id 
         FROM {$wpdb->usermeta} 
         WHERE meta_key = 'Line_userId'"
    );
    
    if (empty($line_users)) {
        return;
    }
    
    $table_name = $wpdb->prefix . 'wlm_line_users';
    $synced_count = 0;
    
    foreach ($line_users as $user) {
        // 獲取其他 LINE 相關資料
        $line_display_name = get_user_meta($user->user_id, 'Line_displayName', true);
        $line_picture_url = get_user_meta($user->user_id, 'Line_pictureUrl', true);
        
        // 檢查是否已存在
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d",
            $user->user_id
        ));
        
        if ($existing) {
            // 更新現有記錄
            $wpdb->update(
                $table_name,
                array(
                    'line_user_id' => $user->line_user_id,
                    'line_display_name' => $line_display_name,
                    'line_picture_url' => $line_picture_url,
                    'updated_at' => current_time('mysql')
                ),
                array('user_id' => $user->user_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // 插入新記錄
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user->user_id,
                    'line_user_id' => $user->line_user_id,
                    'line_display_name' => $line_display_name,
                    'line_picture_url' => $line_picture_url
                ),
                array('%d', '%s', '%s', '%s')
            );
        }
        
        // 同時儲存到 user meta 作為備份
        update_user_meta($user->user_id, 'line_user_id', $user->line_user_id);
        update_user_meta($user->user_id, 'line_display_name', $line_display_name);
        update_user_meta($user->user_id, 'line_picture_url', $line_picture_url);
        
        $synced_count++;
    }
    
    // 儲存同步結果，供管理後台顯示
    update_option('wlm_activation_sync_count', $synced_count);
    update_option('wlm_activation_sync_time', current_time('mysql'));
}

/**
 * 外掛停用時的處理
 */
function wlm_deactivate() {
    // 清理排程任務等
}
register_deactivation_hook(__FILE__, 'wlm_deactivate');

/**
 * 外掛卸載時的處理
 */
function wlm_uninstall() {
    global $wpdb;
    
    // 刪除選項
    delete_option('wlm_line_channel_access_token');
    delete_option('wlm_line_channel_secret');
    delete_option('wlm_enable_order_notification');
    delete_option('wlm_order_notification_template');
    
    // 刪除資料表
    $table_name = $wpdb->prefix . 'wlm_line_users';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}
register_uninstall_hook(__FILE__, 'wlm_uninstall');

