# 安全性說明

## 保護措施

本外掛已實施以下安全措施來保護您的敏感資料：

### 1. API Keys 保護

- **密碼欄位**：Channel Access Token 和 Channel Secret 使用密碼輸入框，預設隱藏顯示
- **遮罩顯示**：儲存後會顯示部分遮罩的值，避免完全暴露
- **顯示按鈕**：需要手動點擊「顯示」按鈕才能看到完整值

### 2. 日誌安全

- **最小化記錄**：預設只記錄成功/失敗狀態，不記錄完整 API 回應
- **詳細日誌選用**：只有在 `wp-config.php` 中定義 `WLM_DEBUG_VERBOSE` 為 `true` 時才記錄完整回應
- **錯誤訊息過濾**：API 錯誤訊息不會暴露完整回應內容

### 3. 使用者隱私

- **LINE User ID 遮罩**：管理後台預設只顯示部分 ID
- **點擊顯示**：需要手動點擊才能查看完整 LINE User ID
- **權限檢查**：所有 AJAX 端點都需要 `manage_woocommerce` 權限

### 4. 檔案保護

- **目錄保護**：所有 PHP 檔案都有 `ABSPATH` 檢查，防止直接訪問
- **.htaccess**：額外的 Apache 層級保護（如果使用 Apache）

## 最佳實踐

### 正式環境設定

在正式環境中，請確保 `wp-config.php` 中：

```php
// 關閉除錯模式
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);

// 不要定義 WLM_DEBUG_VERBOSE
// define('WLM_DEBUG_VERBOSE', true); // 不要在正式環境啟用
```

### 開發環境設定

如果需要詳細的除錯日誌，在 `wp-config.php` 中加入：

```php
// 開啟除錯模式
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// 啟用詳細日誌（僅開發環境）
define('WLM_DEBUG_VERBOSE', true);
```

**警告**：`WLM_DEBUG_VERBOSE` 會記錄完整的 API 回應，可能包含敏感資訊。請勿在正式環境啟用。

### 保護 debug.log 檔案

如果啟用了 WP_DEBUG_LOG，請確保 `wp-content/debug.log` 無法從外部訪問。

#### Apache

在 `wp-content/.htaccess` 中加入：

```apache
<Files debug.log>
    Order allow,deny
    Deny from all
</Files>
```

#### Nginx

在 nginx 設定中加入：

```nginx
location ~* /wp-content/debug\.log$ {
    deny all;
}
```

### 定期更換 Token

建議定期更換 LINE Channel Access Token，特別是在：

- 懷疑 token 洩漏時
- 團隊成員異動時
- 發現異常 API 使用時

### 備份與還原

備份資料庫時請注意：

- `wp_options` 表包含 Channel Access Token 和 Secret
- `wp_wlm_line_users` 表包含 LINE User ID
- 備份檔案應妥善加密保存

## 回報安全問題

如果您發現任何安全漏洞，請私下聯絡開發者，不要公開發布。

## 權限需求

本外掛需要以下權限：

- `manage_woocommerce`：管理 WooCommerce 設定和查看訂單
- 資料庫讀寫權限：儲存 LINE 使用者對應資料

## 資料儲存

本外掛儲存以下資料：

1. **WordPress Options 表** (`wp_options`)
   - `wlm_line_channel_access_token`：LINE Channel Access Token
   - `wlm_line_channel_secret`：LINE Channel Secret
   - 其他設定選項

2. **專用資料表** (`wp_wlm_line_users`)
   - WordPress User ID
   - LINE User ID
   - LINE 顯示名稱
   - LINE 頭像 URL

3. **User Meta 表** (`wp_usermeta`)
   - `line_user_id`：LINE User ID（備份）
   - `line_display_name`：LINE 顯示名稱（備份）
   - `line_picture_url`：LINE 頭像 URL（備份）

所有敏感資料都應遵守 GDPR 和當地隱私法規。

## 版本歷史

- **1.0.0**：初始版本，實施基本安全措施

