# WooCommerce LINE Messaging

整合 LINE Login 與 LINE Messaging API 的 WordPress 外掛，讓您可以在 WooCommerce 訂單建立時自動發送 LINE 通知給客戶。

## 功能特色

- 整合 Super Socializer 的 LINE Login 資料
- 在訂單建立時自動發送 LINE 通知
- 在訂單狀態改變時發送 LINE 通知
- 使用 LINE Flex Message 格式的精美訊息
- 管理後台可測試 Token 和發送測試訊息
- 查看已連結 LINE 的使用者列表

## 安裝需求

- WordPress 5.8 或更高版本
- PHP 7.4 或更高版本
- WooCommerce 5.0 或更高版本
- Super Socializer 外掛（用於 LINE Login）
- LINE Messaging API Channel（需要在 LINE Developers Console 建立）

## 安裝步驟

### 方法一：透過 WordPress 管理後台上傳（推薦）

1. 將整個外掛資料夾壓縮成 zip 檔案（確保 `woocommerce-line-messaging.php` 在 zip 檔案的根目錄）
2. 登入 WordPress 管理後台
3. 前往「外掛」>「安裝外掛」>「上傳外掛」
4. 點擊「選擇檔案」並選擇剛才建立的 zip 檔案
5. 點擊「立即安裝」
6. 安裝完成後，點擊「啟用外掛」
7. 前往 WooCommerce > LINE 訊息通知 進行設定

### 方法二：透過 FTP 上傳

1. 將整個 `woocommerce-line-messaging` 資料夾上傳到 `/wp-content/plugins/` 目錄
2. 登入 WordPress 管理後台
3. 前往「外掛」>「已安裝外掛」
4. 找到「WooCommerce LINE Messaging」並點擊「啟用」
5. 前往 WooCommerce > LINE 訊息通知 進行設定

## 設定步驟

### 1. 建立 LINE Messaging API Channel

1. 前往 [LINE Developers Console](https://developers.line.biz/console/)
2. 建立一個新的 Provider（如果還沒有）
3. 建立一個新的 Messaging API Channel
4. 在 Channel 設定中取得：
   - Channel Access Token（長期）
   - Channel Secret

### 2. 設定 Super Socializer

1. 確保 Super Socializer 外掛已安裝並啟用
2. 設定 LINE Login 功能
3. 客戶使用 LINE 登入後，外掛會自動儲存 LINE User ID

### 3. 設定外掛

1. 前往 WooCommerce > LINE 訊息通知
2. 填入 Channel Access Token 和 Channel Secret
3. 點擊「測試 Token」確認設定正確
4. 啟用訂單建立通知
5. 選擇要發送通知的訂單狀態
6. 儲存設定

## 使用方式

### 客戶端流程

1. 客戶使用 LINE Login 登入您的網站
2. 外掛自動儲存客戶的 LINE User ID
3. 客戶建立訂單後，會收到 LINE 通知
4. 訂單狀態改變時，會收到相應的 LINE 通知

### 管理員功能

1. 查看已連結 LINE 的使用者列表
2. 測試 Channel Access Token 是否有效
3. 發送測試訊息給指定使用者

## 訊息範例

外掛會發送包含以下資訊的精美 Flex Message：

### 訂單建立通知
- 訂單編號
- 訂單狀態
- 訂單日期
- 商品列表
- 訂單總金額
- 查看訂單詳情按鈕

### 訂單狀態更新通知
- 訂單編號
- 新狀態
- 查看訂單詳情按鈕

## 資料庫結構

外掛會建立一個資料表 `wp_wlm_line_users` 來儲存 LINE User ID 對應：

```sql
CREATE TABLE wp_wlm_line_users (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    line_user_id varchar(255) NOT NULL,
    line_display_name varchar(255) DEFAULT NULL,
    line_picture_url text DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_id (user_id),
    UNIQUE KEY line_user_id (line_user_id)
);
```

## 常見問題

### Q: 客戶沒有收到 LINE 通知？

A: 請檢查：
1. 客戶是否使用 LINE Login 登入過
2. Channel Access Token 是否設定正確
3. 客戶是否已將您的 LINE Bot 加為好友
4. 查看 WordPress 除錯日誌是否有錯誤訊息

### Q: 如何讓客戶加入 LINE Bot 好友？

A: 在 LINE Developers Console 的 Messaging API 設定中，可以找到您的 Bot 的 QR Code 和加入好友連結，您可以將這些資訊提供給客戶。

### Q: 訪客結帳會收到通知嗎？

A: 不會。訪客結帳沒有 WordPress User ID，因此無法找到對應的 LINE User ID。建議鼓勵客戶使用 LINE Login 註冊帳號。

### Q: 可以自訂訊息內容嗎？

A: 目前訊息格式是固定的 Flex Message 格式。如果需要自訂，可以修改 `includes/class-order-notifier.php` 中的 `prepare_order_message()` 和 `prepare_status_change_message()` 方法。

## 開發說明

### 檔案結構

```
woocommerce-line-messaging/
├── woocommerce-line-messaging.php    # 主檔案
├── includes/
│   ├── class-line-messaging.php      # LINE Messaging API 處理
│   ├── class-order-notifier.php      # 訂單通知處理
│   ├── class-admin-settings.php      # 管理後台設定
│   └── class-user-data-handler.php   # 使用者資料處理
├── admin/
│   └── js/
│       └── admin.js                  # 管理後台 JavaScript
└── README.md                          # 說明文件
```

### 可用的 Hooks

#### Actions

- `wlm_before_send_notification` - 發送通知前
- `wlm_after_send_notification` - 發送通知後

#### Filters

- `wlm_order_message` - 修改訂單通知訊息內容
- `wlm_status_change_message` - 修改狀態更新訊息內容

## 授權

此外掛採用 GPL v2 或更高版本授權。

## 支援

如有問題或建議，請聯絡外掛作者。

## 更新日誌

### 1.0.0
- 初始版本
- 整合 Super Socializer LINE Login
- 訂單建立通知
- 訂單狀態更新通知
- 管理後台設定頁面

