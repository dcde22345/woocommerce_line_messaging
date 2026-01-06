# LINE LIFF Login 設定指南

本指南將協助您在 LINE Developers Console 中設定 LIFF App，以啟用 LINE Login 功能。

## 前置需求

1. 擁有 LINE 帳號
2. 已建立 LINE Developers 帳號
3. 已建立 Provider（如果還沒有）

## 設定步驟

### 步驟 1：登入 LINE Developers Console

1. 前往 [LINE Developers Console](https://developers.line.biz/console/)
2. 使用您的 LINE 帳號登入

### 步驟 2：選擇或建立 Provider

1. 如果還沒有 Provider，點擊「Create」建立一個新的 Provider
2. 輸入 Provider 名稱（例如：您的網站名稱）
3. 選擇或建立 Provider

### 步驟 3：選擇 Channel

您需要選擇一個 Channel 來建立 LIFF App。有兩種選擇：

#### 選項 A：使用現有的 Messaging API Channel（推薦）

如果您已經有 Messaging API Channel（用於發送訊息），可以直接使用：

1. 在 Provider 頁面中，選擇您的 Messaging API Channel
2. 進入 Channel 設定頁面

#### 選項 B：建立新的 Login Channel

如果您想要分開管理，可以建立專門的 Login Channel：

1. 在 Provider 頁面中，點擊「Create」
2. 選擇「LINE Login」
3. 填寫 Channel 資訊：
   - **Channel name**: 自訂名稱（例如：WordPress Login）
   - **Channel description**: 描述（可選）
   - **App type**: Web app
   - **Email address**: 您的 Email
   - **Privacy policy URL**: 您的隱私政策網址（可選）
   - **Terms of service URL**: 您的服務條款網址（可選）
4. 勾選同意條款
5. 點擊「Create」

### 步驟 4：建立 LIFF App

1. 在 Channel 設定頁面中，找到左側選單的「LIFF」選項
2. 點擊「LIFF」進入 LIFF 管理頁面
3. 點擊右上角的「Add」按鈕
4. 填寫 LIFF App 資訊：

   **必填欄位：**
   - **LIFF app name**: 自訂名稱（例如：WordPress Login）
   - **Size**: 
     - `Full` - 全螢幕模式（推薦用於登入）
     - `Tall` - 高型模式
     - `Compact` - 緊湊模式
   - **Endpoint URL**: 
     ```
     https://your-domain.com/?wlm_line_login=1
     ```
     請將 `your-domain.com` 替換為您的網站網址
   
   **選填欄位：**
   - **Scope**: 
     - 至少選擇 `profile`（獲取使用者基本資料）
     - 建議選擇 `openid`（使用 OpenID Connect）
     - 可選 `email`（如果 Channel 支援）
   - **Bot link feature**: 
     - 如果啟用，使用者登入後可以連結到 LINE Bot
     - 可選，視需求啟用

5. 點擊「Add」建立 LIFF App

### 步驟 5：取得 LIFF ID

1. 建立 LIFF App 後，會在列表中顯示
2. 複製 **LIFF ID**（通常是一串長字串，例如：`1234567890-ABCDEFGH`）

### 步驟 6：在 WordPress 中設定

1. 登入 WordPress 管理後台
2. 前往 **WooCommerce > LINE Login**
3. 填入以下設定：
   - **啟用 LINE Login**: 勾選
   - **LIFF ID**: 貼上剛才複製的 LIFF ID
   - **自動建立使用者**: 建議勾選（首次登入自動建立帳號）
   - **驗證 Access Token**: 建議勾選（提高安全性）
   - **登入後導向網址**: 設定登入成功後要導向的頁面
4. 點擊「儲存設定」

### 步驟 7：測試登入

1. 在網站任何地方加入 shortcode：
   ```
   [wlm_line_login_button]
   ```
2. 或直接連結到：
   ```
   https://your-domain.com/?wlm_line_login=1
   ```
3. 點擊連結測試登入功能
4. 確認可以成功登入並建立/綁定 WordPress 帳號

## 常見問題

### Q: Endpoint URL 應該設定什麼？

A: 設定為：
```
https://your-domain.com/?wlm_line_login=1
```
請將 `your-domain.com` 替換為您的實際網址。

### Q: 可以使用 HTTP 嗎？

A: 不可以。LIFF App 的 Endpoint URL 必須使用 HTTPS。

### Q: Scope 應該選擇哪些？

A: 至少選擇 `profile` 和 `openid`。如果需要 Email，可以選擇 `email`（但需要 Channel 支援）。

### Q: 登入後沒有自動建立使用者？

A: 請檢查「自動建立使用者」設定是否已啟用。如果停用，使用者需要先註冊 WordPress 帳號才能綁定 LINE。

### Q: 如何修改登入按鈕樣式？

A: 可以使用 shortcode 的 `class` 參數自訂 CSS 類別：
```
[wlm_line_login_button class="my-custom-class"]
```

### Q: 如何設定登入後導向特定頁面？

A: 有兩種方式：
1. 在設定頁面設定「登入後導向網址」
2. 在 shortcode 中使用 `redirect` 參數：
   ```
   [wlm_line_login_button redirect="/my-account"]
   ```

## 安全建議

1. **啟用 Token 驗證**：建議在設定中啟用「驗證 Access Token」，以提高安全性
2. **使用 HTTPS**：確保網站使用 HTTPS 連線
3. **定期更新**：定期檢查並更新外掛版本
4. **保護 LIFF ID**：不要將 LIFF ID 公開分享

## 技術支援

如果遇到問題，請檢查：
1. WordPress 錯誤日誌
2. 瀏覽器開發者工具的控制台
3. LINE Developers Console 的 Channel 設定

## 相關連結

- [LINE Developers Console](https://developers.line.biz/console/)
- [LIFF 官方文件](https://developers.line.biz/en/docs/liff/)
- [LINE Login 文件](https://developers.line.biz/en/docs/line-login/)

