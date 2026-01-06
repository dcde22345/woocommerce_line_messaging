<?php
/**
 * LINE Messaging API 處理類別
 *
 * @package WooCommerce_LINE_Messaging
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

class WLM_LINE_Messaging {
    
    /**
     * LINE Messaging API Endpoint
     */
    const API_ENDPOINT = 'https://api.line.me/v2/bot/message/push';
    
    /**
     * Channel Access Token
     *
     * @var string
     */
    private $channel_access_token;
    
    /**
     * 建構子
     */
    public function __construct() {
        $this->channel_access_token = get_option('wlm_line_channel_access_token', '');
    }
    
    /**
     * 發送訊息給指定的 LINE 使用者
     *
     * @param string $line_user_id LINE User ID
     * @param array  $messages     訊息陣列
     * @return bool|WP_Error
     */
    public function send_message($line_user_id, $messages) {
        if (empty($this->channel_access_token)) {
            return new WP_Error('no_token', 'LINE Channel Access Token 未設定');
        }
        
        if (empty($line_user_id)) {
            return new WP_Error('no_user_id', 'LINE User ID 不能為空');
        }
        
        if (empty($messages) || !is_array($messages)) {
            return new WP_Error('no_messages', '訊息內容不能為空');
        }
        
        // 準備請求資料
        $data = array(
            'to' => $line_user_id,
            'messages' => $messages
        );
        
        // 發送請求
        $response = wp_remote_post(self::API_ENDPOINT, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->channel_access_token
            ),
            'body' => json_encode($data),
            'timeout' => 15
        ));
        
        // 檢查錯誤
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // 記錄日誌（不記錄完整 response body 以保護隱私）
        $this->log_message('Response Code: ' . $response_code);
        
        // 只在開發模式且明確啟用時才記錄完整回應
        if (defined('WLM_DEBUG_VERBOSE') && WLM_DEBUG_VERBOSE) {
            $this->log_message('Response Body: ' . $response_body);
        } else {
            $this->log_message('Response: ' . ($response_code === 200 ? 'Success' : 'Failed'));
        }
        
        if ($response_code !== 200) {
            // 解析錯誤訊息，但不暴露完整 response
            $error_data = json_decode($response_body, true);
            $error_message = 'LINE API 錯誤: ' . $response_code;
            
            // 記錄詳細錯誤到日誌
            $this->log_message('API Error - Code: ' . $response_code . ', Body: ' . $response_body, 'error');
            
            // 根據常見的錯誤碼提供更詳細的訊息
            $common_errors = array(
                400 => '請求格式錯誤',
                401 => 'Channel Access Token 無效或已過期',
                403 => '沒有權限使用此 API',
                404 => 'API 端點不存在',
                429 => '請求次數過多，請稍後再試',
                500 => 'LINE 伺服器錯誤',
                503 => 'LINE 服務暫時無法使用'
            );
            
            if (isset($common_errors[$response_code])) {
                $error_message .= ' - ' . $common_errors[$response_code];
            }
            
            if (isset($error_data['message'])) {
                $error_message .= ' (' . $error_data['message'] . ')';
            }
            
            // 如果是 400 錯誤，通常是使用者未加好友
            if ($response_code === 400) {
                $error_message .= '。請確認使用者已將 LINE Bot 加為好友。';
            }
            
            // 如果是 401 錯誤，提供更明確的建議
            if ($response_code === 401) {
                $error_message .= '。請檢查 Token 是否正確，或前往 LINE Developers Console 重新發行 Token。';
            }
            
            return new WP_Error('api_error', $error_message, array(
                'response_code' => $response_code,
                'response_body' => $response_body
            ));
        }
        
        // 記錄成功發送
        $this->log_message('訊息發送成功 - Response Code: 200', 'info');
        
        return true;
    }
    
    /**
     * 發送文字訊息
     *
     * @param string $line_user_id LINE User ID
     * @param string $text         文字內容
     * @return bool|WP_Error
     */
    public function send_text_message($line_user_id, $text) {
        $messages = array(
            array(
                'type' => 'text',
                'text' => $text
            )
        );
        
        return $this->send_message($line_user_id, $messages);
    }
    
    /**
     * 發送 Flex Message
     *
     * @param string $line_user_id LINE User ID
     * @param string $alt_text     替代文字
     * @param array  $contents     Flex Message 內容
     * @return bool|WP_Error
     */
    public function send_flex_message($line_user_id, $alt_text, $contents) {
        $messages = array(
            array(
                'type' => 'flex',
                'altText' => $alt_text,
                'contents' => $contents
            )
        );
        
        return $this->send_message($line_user_id, $messages);
    }
    
    /**
     * 記錄日誌
     *
     * @param string $message 日誌訊息
     * @param string $level   日誌等級 (info, warning, error)
     */
    private function log_message($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[WLM LINE Messaging] [' . strtoupper($level) . '] ' . $message);
        }
    }
    
    /**
     * 驗證 Channel Access Token
     *
     * @param string $token Channel Access Token
     * @return bool|WP_Error
     */
    public static function verify_token($token) {
        if (empty($token)) {
            return new WP_Error('empty_token', 'Token 不能為空');
        }
        
        // 使用 Bot Info API 驗證 token
        $response = wp_remote_get('https://api.line.me/v2/bot/info', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            error_log('[WLM] Token 驗證請求錯誤: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log('[WLM] Token 驗證回應 - Code: ' . $response_code);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = 'Token 驗證失敗 (HTTP ' . $response_code . ')';
            
            if (isset($error_data['message'])) {
                $error_message .= ': ' . $error_data['message'];
            }
            
            error_log('[WLM] Token 驗證失敗 - ' . $error_message);
            return new WP_Error('invalid_token', $error_message);
        }
        
        // 成功時記錄 Bot 資訊（可選）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $bot_info = json_decode($response_body, true);
            if (isset($bot_info['displayName'])) {
                error_log('[WLM] Token 驗證成功 - Bot 名稱: ' . $bot_info['displayName']);
            }
        }
        
        return true;
    }
}

