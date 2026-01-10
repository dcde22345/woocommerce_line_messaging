<?php
/**
 * LINE LIFF Login 頁面模板
 * 
 * 此檔案會透過 template_redirect hook 載入
 * 確保 WordPress 環境已完全載入
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LINE Login - <?php bloginfo('name'); ?></title>
    <script src="https://static.line-scdn.net/liff/edge/versions/2.22.3/sdk.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-container {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 90%;
        }
        .login-status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            font-size: 16px;
        }
        .login-status.loading {
            background: #e3f2fd;
            color: #1976d2;
        }
        .login-status.success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .login-status.error {
            background: #ffebee;
            color: #c62828;
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #1976d2;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .line-logo {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            background: #00B900;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="line-logo">LINE</div>
        <h1>LINE 登入</h1>
        <div id="login-status" class="login-status loading">
            <div class="spinner"></div>
            <div>正在登入...</div>
        </div>
    </div>
    
    <script>
        (function() {
            const liffId = '<?php echo esc_js(WLM_Line_Login::get_liff_id()); ?>';
            const ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            const nonce = '<?php echo esc_js(wp_create_nonce('wlm_line_liff_login')); ?>';
            const redirectUrl = '<?php echo esc_js(isset($_GET['redirect']) ? esc_url_raw($_GET['redirect']) : get_option('wlm_line_login_redirect_url', home_url())); ?>';
            
            const statusEl = document.getElementById('login-status');
            
            function updateStatus(message, type) {
                statusEl.className = 'login-status ' + type;
                statusEl.innerHTML = '<div>' + message + '</div>';
            }
            
            async function initializeLiff() {
                try {
                    if (!liffId) {
                        updateStatus('LIFF ID 未設定，請聯繫管理員', 'error');
                        return;
                    }
                    
                    // 初始化 LIFF（請求 email scope）
                    await liff.init({ 
                        liffId: liffId
                    });
                    
                    if (!liff.isLoggedIn()) {
                        // 如果未登入，導向 LINE 登入頁面（請求 email scope）
                        liff.login({
                            redirectUri: window.location.href
                        });
                        return;
                    }
                    
                    // 獲取 LINE Profile
                    const profile = await liff.getProfile();
                    
                    // 嘗試從 ID Token 獲取 email（如果可用）
                    let lineEmail = null;
                    try {
                        const idToken = liff.getIDToken();
                        if (idToken) {
                            // 解碼 ID Token 獲取 email
                            const decodedToken = await liff.getDecodedIDToken();
                            if (decodedToken && decodedToken.email) {
                                lineEmail = decodedToken.email;
                            }
                        }
                    } catch (e) {
                        // ID Token 可能不包含 email，繼續使用其他方法
                        console.log('[WLM] 無法從 ID Token 獲取 email:', e.message);
                    }
                    
                    // 獲取 Access Token
                    const accessToken = liff.getAccessToken();
                    
                    // 發送到後端處理（傳遞 email）
                    await sendToBackend(profile, accessToken, lineEmail);
                    
                } catch (error) {
                    console.error('LIFF Error:', error);
                    updateStatus('登入失敗: ' + error.message, 'error');
                }
            }
            
            async function sendToBackend(profile, accessToken, lineEmail) {
                try {
                    updateStatus('正在處理登入資訊...', 'loading');
                    
                    const formData = new URLSearchParams({
                        action: 'wlm_line_liff_login',
                        line_user_id: profile.userId,
                        line_display_name: profile.displayName || '',
                        line_picture_url: profile.pictureUrl || '',
                        line_email: lineEmail || '',
                        access_token: accessToken || '',
                        nonce: nonce
                    });
                    
                    const response = await fetch(ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        updateStatus('登入成功！正在導向...', 'success');
                        
                        // 延遲一下讓使用者看到成功訊息
                        setTimeout(() => {
                            const targetUrl = result.data.redirect_url || redirectUrl;
                            
                            // 在 LIFF 視窗內直接導向（保持 LIFF 環境）
                            // 使用 window.location.href 會保持 LIFF 環境
                            window.location.href = targetUrl;
                        }, 1500);
                    } else {
                        updateStatus('登入失敗: ' + (result.data.message || '未知錯誤'), 'error');
                        
                        // 如果需要註冊，顯示提示
                        if (result.data.require_registration) {
                            setTimeout(() => {
                                updateStatus('請先註冊 WordPress 帳號後再綁定 LINE', 'error');
                            }, 2000);
                        }
                    }
                } catch (error) {
                    console.error('Backend Error:', error);
                    updateStatus('連線錯誤: ' + error.message, 'error');
                }
            }
            
            // 頁面載入時執行
            if (typeof liff !== 'undefined') {
                initializeLiff();
            } else {
                updateStatus('無法載入 LINE SDK，請檢查網路連線', 'error');
            }
        })();
    </script>
</body>
</html>

