<?php
/**
 * 管理後台設定頁面類別
 *
 * @package WooCommerce_LINE_Messaging
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

class WLM_Admin_Settings {
    
    /**
     * 初始化
     */
    public static function init() {
        // 註冊設定選單
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        
        // 註冊設定
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        
        // 載入管理腳本
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
        
        // AJAX 處理：測試 LINE Token
        add_action('wp_ajax_wlm_test_line_token', array(__CLASS__, 'ajax_test_line_token'));
        
        // AJAX 處理：發送測試訊息
        add_action('wp_ajax_wlm_send_test_message', array(__CLASS__, 'ajax_send_test_message'));
    }
    
    /**
     * 新增管理選單
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'LINE 訊息通知設定',
            'LINE 訊息通知',
            'manage_woocommerce',
            'wlm-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }
    
    /**
     * 註冊設定
     */
    public static function register_settings() {
        // LINE API 設定
        register_setting('wlm_settings', 'wlm_line_channel_access_token');
        register_setting('wlm_settings', 'wlm_line_channel_secret');
        
        // 通知設定
        register_setting('wlm_settings', 'wlm_enable_order_notification');
        register_setting('wlm_settings', 'wlm_status_notification_statuses');
        
        // LINE API 設定區塊
        add_settings_section(
            'wlm_line_api_section',
            'LINE API 設定',
            array(__CLASS__, 'render_api_section'),
            'wlm_settings'
        );
        
        add_settings_field(
            'wlm_line_channel_access_token',
            'Channel Access Token',
            array(__CLASS__, 'render_channel_access_token_field'),
            'wlm_settings',
            'wlm_line_api_section'
        );
        
        add_settings_field(
            'wlm_line_channel_secret',
            'Channel Secret',
            array(__CLASS__, 'render_channel_secret_field'),
            'wlm_settings',
            'wlm_line_api_section'
        );
        
        // 通知設定區塊
        add_settings_section(
            'wlm_notification_section',
            '通知設定',
            array(__CLASS__, 'render_notification_section'),
            'wlm_settings'
        );
        
        add_settings_field(
            'wlm_enable_order_notification',
            '啟用訂單建立通知',
            array(__CLASS__, 'render_enable_order_notification_field'),
            'wlm_settings',
            'wlm_notification_section'
        );
        
        add_settings_field(
            'wlm_status_notification_statuses',
            '訂單狀態通知',
            array(__CLASS__, 'render_status_notification_field'),
            'wlm_settings',
            'wlm_notification_section'
        );
    }
    
    /**
     * 渲染設定頁面
     */
    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>WooCommerce LINE 訊息通知設定</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wlm_settings');
                do_settings_sections('wlm_settings');
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2>測試功能</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">測試 Channel Access Token</th>
                    <td>
                        <button type="button" class="button" id="wlm-test-token">測試 Token</button>
                        <span class="spinner" style="float: none;"></span>
                        <p class="description" id="wlm-test-token-result"></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">發送測試訊息</th>
                    <td>
                        <input type="number" id="wlm-test-user-id" placeholder="WordPress User ID" style="width: 200px;">
                        <button type="button" class="button" id="wlm-send-test-message">發送測試訊息</button>
                        <span class="spinner" style="float: none;"></span>
                        <p class="description" id="wlm-test-message-result"></p>
                    </td>
                </tr>
            </table>
            
            <hr>
            
            <h2>LINE 使用者資料</h2>
            <?php self::render_line_users_table(); ?>
        </div>
        <?php
    }
    
    /**
     * 渲染 API 設定區塊說明
     */
    public static function render_api_section() {
        echo '<p>請在 <a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a> 建立 Messaging API Channel，並填入以下資訊。</p>';
    }
    
    /**
     * 渲染通知設定區塊說明
     */
    public static function render_notification_section() {
        echo '<p>設定何時發送 LINE 通知給客戶。</p>';
    }
    
    /**
     * 渲染 Channel Access Token 欄位
     */
    public static function render_channel_access_token_field() {
        $value = get_option('wlm_line_channel_access_token', '');
        echo '<input type="text" name="wlm_line_channel_access_token" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">從 LINE Developers Console 取得的 Channel Access Token（長期）</p>';
    }
    
    /**
     * 渲染 Channel Secret 欄位
     */
    public static function render_channel_secret_field() {
        $value = get_option('wlm_line_channel_secret', '');
        echo '<input type="text" name="wlm_line_channel_secret" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">從 LINE Developers Console 取得的 Channel Secret</p>';
    }
    
    /**
     * 渲染啟用訂單通知欄位
     */
    public static function render_enable_order_notification_field() {
        $value = get_option('wlm_enable_order_notification', 'no');
        echo '<label><input type="checkbox" name="wlm_enable_order_notification" value="yes" ' . checked($value, 'yes', false) . ' /> 啟用</label>';
        echo '<p class="description">當客戶建立新訂單時，自動發送 LINE 通知</p>';
    }
    
    /**
     * 渲染狀態通知欄位
     */
    public static function render_status_notification_field() {
        $enabled_statuses = get_option('wlm_status_notification_statuses', array('processing', 'completed', 'cancelled'));
        if (!is_array($enabled_statuses)) {
            $enabled_statuses = array();
        }
        
        $statuses = array(
            'pending' => '待付款',
            'processing' => '處理中',
            'on-hold' => '保留',
            'completed' => '已完成',
            'cancelled' => '已取消',
            'refunded' => '已退款',
            'failed' => '失敗'
        );
        
        foreach ($statuses as $status => $label) {
            $checked = in_array($status, $enabled_statuses) ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="wlm_status_notification_statuses[]" value="' . esc_attr($status) . '" ' . $checked . ' /> ';
            echo esc_html($label);
            echo '</label>';
        }
        
        echo '<p class="description">選擇哪些訂單狀態改變時要發送 LINE 通知</p>';
    }
    
    /**
     * 渲染 LINE 使用者資料表格
     */
    private static function render_line_users_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wlm_line_users';
        
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 50");
        
        if (empty($results)) {
            echo '<p>目前沒有 LINE 使用者資料。</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>WordPress User ID</th>';
        echo '<th>LINE User ID</th>';
        echo '<th>顯示名稱</th>';
        echo '<th>建立時間</th>';
        echo '<th>更新時間</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($results as $row) {
            $user = get_userdata($row->user_id);
            $user_name = $user ? $user->display_name . ' (' . $user->user_email . ')' : '未知使用者';
            
            echo '<tr>';
            echo '<td>' . esc_html($row->user_id) . '<br><small>' . esc_html($user_name) . '</small></td>';
            echo '<td><code>' . esc_html($row->line_user_id) . '</code></td>';
            echo '<td>' . esc_html($row->line_display_name) . '</td>';
            echo '<td>' . esc_html($row->created_at) . '</td>';
            echo '<td>' . esc_html($row->updated_at) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    /**
     * 載入管理腳本
     */
    public static function enqueue_admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_wlm-settings') {
            return;
        }
        
        wp_enqueue_script(
            'wlm-admin',
            WLM_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            WLM_VERSION,
            true
        );
        
        wp_localize_script('wlm-admin', 'wlmAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wlm_admin_nonce')
        ));
    }
    
    /**
     * AJAX: 測試 LINE Token
     */
    public static function ajax_test_line_token() {
        check_ajax_referer('wlm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('沒有權限');
        }
        
        $token = get_option('wlm_line_channel_access_token', '');
        
        if (empty($token)) {
            wp_send_json_error('請先設定 Channel Access Token');
        }
        
        $result = WLM_LINE_Messaging::verify_token($token);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('Token 驗證成功');
    }
    
    /**
     * AJAX: 發送測試訊息
     */
    public static function ajax_send_test_message() {
        check_ajax_referer('wlm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('沒有權限');
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        if (!$user_id) {
            wp_send_json_error('請輸入 User ID');
        }
        
        $line_user_id = WLM_User_Data_Handler::get_line_user_id($user_id);
        
        if (!$line_user_id) {
            wp_send_json_error('找不到此使用者的 LINE User ID');
        }
        
        $line_messaging = new WLM_LINE_Messaging();
        $result = $line_messaging->send_text_message(
            $line_user_id,
            "這是一則測試訊息\n來自 WooCommerce LINE Messaging 外掛"
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success('測試訊息已發送');
    }
}

