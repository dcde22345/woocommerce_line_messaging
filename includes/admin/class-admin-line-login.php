<?php
/**
 * LINE Login 管理後台設定頁面類別
 *
 * @package WooCommerce_LINE_Messaging
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

class WLM_Admin_Line_Login {
    
    /**
     * 初始化
     */
    public static function init() {
        // 註冊設定選單
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        
        // 註冊設定
        add_action('admin_init', array(__CLASS__, 'register_settings'));
    }
    
    /**
     * 新增管理選單
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'LINE Login 設定',
            'LINE Login',
            'manage_woocommerce',
            'wlm-line-login',
            array(__CLASS__, 'render_settings_page')
        );
    }
    
    /**
     * 註冊設定
     */
    public static function register_settings() {
        register_setting('wlm_line_login_settings', 'wlm_line_login_enabled');
        register_setting('wlm_line_login_settings', 'wlm_line_liff_id');
        register_setting('wlm_line_login_settings', 'wlm_line_login_auto_create_user');
        register_setting('wlm_line_login_settings', 'wlm_line_login_redirect_url');
        register_setting('wlm_line_login_settings', 'wlm_line_login_verify_token');
    }
    
    /**
     * 渲染設定頁面
     */
    public static function render_settings_page() {
        // 處理表單提交
        if (isset($_POST['wlm_save_line_login_settings']) && check_admin_referer('wlm_line_login_settings')) {
            update_option('wlm_line_liff_id', sanitize_text_field($_POST['wlm_line_liff_id']));
            update_option('wlm_line_login_enabled', isset($_POST['wlm_line_login_enabled']) ? 'yes' : 'no');
            update_option('wlm_line_login_auto_create_user', isset($_POST['wlm_line_login_auto_create_user']) ? 'yes' : 'no');
            update_option('wlm_line_login_redirect_url', esc_url_raw($_POST['wlm_line_login_redirect_url']));
            update_option('wlm_line_login_verify_token', isset($_POST['wlm_line_login_verify_token']) ? 'yes' : 'no');
            
            echo '<div class="notice notice-success is-dismissible"><p>設定已儲存</p></div>';
        }
        
        $liff_id = get_option('wlm_line_liff_id', '');
        $enabled = get_option('wlm_line_login_enabled', 'no');
        $auto_create = get_option('wlm_line_login_auto_create_user', 'yes');
        $redirect_url = get_option('wlm_line_login_redirect_url', home_url());
        $verify_token = get_option('wlm_line_login_verify_token', 'yes');
        
        ?>
        <div class="wrap">
            <h1>LINE Login 設定</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('wlm_line_login_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wlm_line_login_enabled">啟用 LINE Login</label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="wlm_line_login_enabled" 
                                   name="wlm_line_login_enabled" 
                                   value="yes" 
                                   <?php checked($enabled, 'yes'); ?>>
                            <p class="description">啟用後，使用者可以透過 LINE LIFF 進行登入</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wlm_line_liff_id">LIFF ID</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="wlm_line_liff_id" 
                                   name="wlm_line_liff_id" 
                                   value="<?php echo esc_attr($liff_id); ?>" 
                                   class="regular-text"
                                   placeholder="請輸入 LINE LIFF App ID">
                            <p class="description">
                                在 <a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a> 
                                建立 LIFF App 後取得 LIFF ID
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wlm_line_login_auto_create_user">自動建立使用者</label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="wlm_line_login_auto_create_user" 
                                   name="wlm_line_login_auto_create_user" 
                                   value="yes" 
                                   <?php checked($auto_create, 'yes'); ?>>
                            <p class="description">
                                啟用後，首次使用 LINE Login 的使用者會自動建立 WordPress 帳號。
                                停用則需要使用者先註冊 WordPress 帳號才能綁定 LINE。
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wlm_line_login_verify_token">驗證 Access Token</label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="wlm_line_login_verify_token" 
                                   name="wlm_line_login_verify_token" 
                                   value="yes" 
                                   <?php checked($verify_token, 'yes'); ?>>
                            <p class="description">
                                啟用後，系統會驗證 LINE Access Token 的有效性，提高安全性。
                                建議保持啟用狀態。
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="wlm_line_login_redirect_url">登入後導向網址</label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="wlm_line_login_redirect_url" 
                                   name="wlm_line_login_redirect_url" 
                                   value="<?php echo esc_url($redirect_url); ?>" 
                                   class="regular-text">
                            <p class="description">LINE Login 成功後要導向的網址（預設：網站首頁）。登入後會在 LIFF 視窗內直接導向，讓使用者繼續在 LINE App 內使用網站。</p>
                        </td>
                    </tr>
                </table>
                
                <hr>
                
                <h2>LIFF App 設定說明</h2>
                <div class="card" style="max-width: 800px;">
                    <h3>步驟 1：建立 LIFF App</h3>
                    <ol>
                        <li>前往 <a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a></li>
                        <li>選擇你的 Provider（如果沒有，請先建立一個）</li>
                        <li>選擇你的 Channel（Messaging API Channel 或 Login Channel）</li>
                        <li>進入 Channel 設定頁面，找到「LIFF」分頁</li>
                        <li>點擊「新增」按鈕建立新的 LIFF App</li>
                    </ol>
                    
                    <h3>步驟 2：設定 LIFF App 資訊</h3>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>欄位</th>
                                <th>設定值</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>LIFF App 名稱</strong></td>
                                <td>自訂名稱（例如：WordPress Login）</td>
                            </tr>
                            <tr>
                                <td><strong>Size</strong></td>
                                <td>Full（全螢幕）或 Tall（高型）</td>
                            </tr>
                            <tr>
                                <td><strong>Endpoint URL</strong></td>
                                <td><code><?php echo esc_html(home_url('/?wlm_line_login=1')); ?></code></td>
                            </tr>
                            <tr>
                                <td><strong>Scope</strong></td>
                                <td>至少選擇：<code>profile</code>、<code>openid</code></td>
                            </tr>
                            <tr>
                                <td><strong>Bot link feature</strong></td>
                                <td>可選（如果需要連結到 Bot）</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h3>步驟 3：取得 LIFF ID</h3>
                    <ol>
                        <li>建立 LIFF App 後，會顯示 LIFF ID</li>
                        <li>複製 LIFF ID 並貼到上方的「LIFF ID」欄位</li>
                        <li>儲存設定</li>
                    </ol>
                    
                    <h3>步驟 4：測試登入</h3>
                    <ol>
                        <li>在網站任何地方加入 shortcode：<code>[wlm_line_login_button]</code></li>
                        <li>或直接連結到：<code><?php echo esc_html(home_url('/?wlm_line_login=1')); ?></code></li>
                        <li>點擊連結測試登入功能</li>
                    </ol>
                </div>
                
                <hr>
                
                <h2>使用方式</h2>
                <div class="card" style="max-width: 800px;">
                    <h3>Shortcode</h3>
                    <p>在文章或頁面中使用以下 shortcode：</p>
                    <code>[wlm_line_login_button]</code>
                    
                    <h4>參數選項：</h4>
                    <ul>
                        <li><code>text</code> - 按鈕文字（預設：使用 LINE 登入）</li>
                        <li><code>class</code> - CSS 類別（預設：wlm-line-login-button）</li>
                        <li><code>redirect</code> - 登入後導向的網址</li>
                    </ul>
                    
                    <p><strong>範例：</strong></p>
                    <code>[wlm_line_login_button text="點我登入" redirect="/my-account"]</code>
                    
                    <h3>直接連結</h3>
                    <p>也可以直接使用連結：</p>
                    <code>&lt;a href="<?php echo esc_html(home_url('/?wlm_line_login=1')); ?>"&gt;LINE 登入&lt;/a&gt;</code>
                </div>
                
                <?php submit_button('儲存設定', 'primary', 'wlm_save_line_login_settings'); ?>
            </form>
        </div>
        <?php
    }
}

