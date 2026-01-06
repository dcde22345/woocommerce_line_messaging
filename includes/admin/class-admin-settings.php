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
        // 每次開啟頁面時自動同步 LINE 使用者
        $sync_result = WLM_User_Data_Handler::sync_existing_line_users();
        
        // 顯示啟用時的同步結果（只顯示一次）
        $sync_count = get_option('wlm_activation_sync_count', 0);
        $sync_time = get_option('wlm_activation_sync_time', '');
        
        ?>
        <div class="wrap">
            <h1>WooCommerce LINE 訊息通知設定</h1>
            
            <?php if ($sync_count > 0 && $sync_time): ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong>外掛啟用時已自動同步：</strong>
                    成功同步 <?php echo esc_html($sync_count); ?> 位 LINE 使用者
                    （同步時間：<?php echo esc_html($sync_time); ?> ）
                </p>
            </div>
            <?php 
                // 顯示一次後就清除，避免一直顯示
                if (isset($_GET['settings-updated'])) {
                    delete_option('wlm_activation_sync_count');
                    delete_option('wlm_activation_sync_time');
                }
            endif; 
            ?>
            
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
            <p>每次開啟此頁面時會自動同步最新的 LINE 使用者資料</p>
            
            <!-- 搜尋表單 -->
            <form method="get" action="">
                <input type="hidden" name="page" value="wlm-settings">
                <table class="form-table">
                    <tr>
                        <th scope="row">搜尋使用者</th>
                        <td>
                            <input type="text" 
                                   name="wlm_search_email" 
                                   value="<?php echo isset($_GET['wlm_search_email']) ? esc_attr($_GET['wlm_search_email']) : ''; ?>" 
                                   placeholder="輸入使用者 Email" 
                                   style="width: 300px;">
                            <button type="submit" class="button">搜尋</button>
                            <?php if (isset($_GET['wlm_search_email']) && !empty($_GET['wlm_search_email'])): ?>
                                <a href="?page=wlm-settings" class="button">清除搜尋</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </form>
            
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
        $masked_value = !empty($value) ? substr($value, 0, 10) . '...' . substr($value, -10) : '';
        ?>
        <input type="password" 
               name="wlm_line_channel_access_token" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" 
               placeholder="<?php echo $masked_value ? '已設定 (點擊顯示)' : '輸入 Channel Access Token'; ?>" />
        <button type="button" class="button" onclick="var input=this.previousElementSibling; input.type=input.type==='password'?'text':'password'; this.textContent=input.type==='password'?'顯示':'隱藏';">顯示</button>
        <?php if ($masked_value): ?>
            <p class="description">目前值：<code><?php echo esc_html($masked_value); ?></code></p>
        <?php endif; ?>
        <p class="description">從 LINE Developers Console 取得的 Channel Access Token（長期）</p>
        <?php
    }
    
    /**
     * 渲染 Channel Secret 欄位
     */
    public static function render_channel_secret_field() {
        $value = get_option('wlm_line_channel_secret', '');
        $masked_value = !empty($value) ? substr($value, 0, 4) . str_repeat('*', strlen($value) - 8) . substr($value, -4) : '';
        ?>
        <input type="password" 
               name="wlm_line_channel_secret" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" 
               placeholder="<?php echo $masked_value ? '已設定 (點擊顯示)' : '輸入 Channel Secret'; ?>" />
        <button type="button" class="button" onclick="var input=this.previousElementSibling; input.type=input.type==='password'?'text':'password'; this.textContent=input.type==='password'?'顯示':'隱藏';">顯示</button>
        <?php if ($masked_value): ?>
            <p class="description">目前值：<code><?php echo esc_html($masked_value); ?></code></p>
        <?php endif; ?>
        <p class="description">從 LINE Developers Console 取得的 Channel Secret</p>
        <?php
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
        
        // 先清理已刪除的用戶資料
        self::cleanup_deleted_users();
        
        // 檢查是否有搜尋條件
        $search_email = isset($_GET['wlm_search_email']) ? sanitize_email($_GET['wlm_search_email']) : '';
        
        if (!empty($search_email)) {
            // 先找到符合 email 的使用者
            $user = get_user_by('email', $search_email);
            
            if ($user) {
                // 根據 user_id 查詢
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC",
                    $user->ID
                ));
            } else {
                $results = array();
            }
            
            if (empty($results)) {
                echo '<div class="notice notice-warning"><p>找不到 Email 為 <strong>' . esc_html($search_email) . '</strong> 的 LINE 使用者</p></div>';
                return;
            }
        } else {
            // 沒有搜尋條件，顯示最新 5 筆
            $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 5");
        }
        
        // 過濾掉不存在的用戶
        $valid_results = array();
        foreach ($results as $row) {
            if (self::user_exists($row->user_id)) {
                $valid_results[] = $row;
            }
        }
        
        if (empty($valid_results)) {
            echo '<p>目前沒有 LINE 使用者資料。</p>';
            echo '<p><small>使用者需要透過 Super Socializer 的 LINE Login 登入後，資料才會顯示在這裡。</small></p>';
            return;
        }
        
        // 顯示結果數量（只計算有效的用戶）
        $total_count = self::get_valid_line_users_count();
        if (empty($search_email)) {
            echo '<p><small>顯示最新 ' . count($valid_results) . ' 筆，共 ' . $total_count . ' 筆資料</small></p>';
        } else {
            echo '<p><small>找到 ' . count($valid_results) . ' 筆符合的資料</small></p>';
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
        
        foreach ($valid_results as $row) {
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
     * 清理已刪除的用戶資料
     *
     * @return int 刪除的記錄數
     */
    private static function cleanup_deleted_users() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wlm_line_users';
        
        // 獲取所有 LINE 使用者記錄
        $all_line_users = $wpdb->get_results("SELECT user_id FROM $table_name");
        
        if (empty($all_line_users)) {
            return 0;
        }
        
        $deleted_count = 0;
        $user_ids_to_delete = array();
        
        foreach ($all_line_users as $line_user) {
            if (!self::user_exists($line_user->user_id)) {
                $user_ids_to_delete[] = $line_user->user_id;
            }
        }
        
        if (!empty($user_ids_to_delete)) {
            // 確保所有 ID 都是整數並轉義
            $sanitized_ids = array_map('intval', $user_ids_to_delete);
            $sanitized_ids = array_map('absint', $sanitized_ids);
            
            // 批量刪除不存在的用戶記錄
            $ids_string = implode(',', $sanitized_ids);
            $deleted_count = $wpdb->query(
                "DELETE FROM $table_name WHERE user_id IN ($ids_string)"
            );
            
            if ($deleted_count > 0) {
                error_log('[WLM] 清理了 ' . $deleted_count . ' 筆已刪除用戶的 LINE 資料');
            }
        }
        
        return $deleted_count;
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
     * 獲取有效的 LINE 使用者數量（只計算 WordPress users 表中存在的用戶）
     *
     * @return int 有效的使用者數量
     */
    private static function get_valid_line_users_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wlm_line_users';
        
        // 獲取所有 LINE 使用者記錄
        $all_line_users = $wpdb->get_results("SELECT user_id FROM $table_name");
        
        if (empty($all_line_users)) {
            return 0;
        }
        
        $valid_count = 0;
        foreach ($all_line_users as $line_user) {
            if (self::user_exists($line_user->user_id)) {
                $valid_count++;
            }
        }
        
        return $valid_count;
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
            'nonce' => wp_create_nonce('wlm_admin_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG && defined('WLM_DEBUG_VERBOSE') && WLM_DEBUG_VERBOSE
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
        
        error_log('[WLM] 測試 Token - Token 長度: ' . strlen($token));
        
        if (empty($token)) {
            error_log('[WLM] Token 為空');
            wp_send_json_error('請先設定 Channel Access Token');
        }
        
        $result = WLM_LINE_Messaging::verify_token($token);
        
        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $error_message = $result->get_error_message();
            error_log('[WLM] Token 驗證失敗 - Code: ' . $error_code . ', Message: ' . $error_message);
            wp_send_json_error($error_message);
        }
        
        error_log('[WLM] Token 驗證成功');
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
        
        // 記錄除錯資訊
        error_log('[WLM] 發送測試訊息 - User ID: ' . $user_id);
        
        $line_user_id = WLM_User_Data_Handler::get_line_user_id($user_id);
        
        if (!$line_user_id) {
            error_log('[WLM] 找不到 User ID ' . $user_id . ' 的 LINE User ID');
            wp_send_json_error('找不到此使用者的 LINE User ID。請確認該使用者是否已使用 LINE Login 登入過。');
        }
        
        // 只在開發模式下記錄 LINE User ID
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WLM_DEBUG_VERBOSE') && WLM_DEBUG_VERBOSE) {
            error_log('[WLM] 找到 LINE User ID: ' . $line_user_id);
        }
        
        // 檢查 Token 是否設定
        $token = get_option('wlm_line_channel_access_token', '');
        if (empty($token)) {
            error_log('[WLM] Channel Access Token 未設定');
            wp_send_json_error('Channel Access Token 未設定，請先設定 Token');
        }
        
        // 建立測試用的 Flex Message（使用假資料）
        $test_message = WLM_Order_Flex_Message::create_test_message();
        
        $line_messaging = new WLM_LINE_Messaging();
        $result = $line_messaging->send_flex_message(
            $line_user_id,
            '測試訊息 - 訂單建立成功通知',
            $test_message
        );
        
        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $error_message = $result->get_error_message();
            $error_data = $result->get_error_data();
            
            error_log('[WLM] 發送訊息失敗 - Code: ' . $error_code . ', Message: ' . $error_message);
            
            // 提供更詳細的錯誤訊息
            $detailed_message = $error_message;
            if ($error_data && is_array($error_data)) {
                $detailed_message .= ' (詳細資訊請查看 Console)';
            }
            
            wp_send_json_error($detailed_message);
        }
        
        error_log('[WLM] 測試訊息發送成功');
        wp_send_json_success('測試訊息已發送');
    }
}

