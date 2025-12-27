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
        
        // 記錄日誌
        $this->log_message('Response Code: ' . $response_code);
        $this->log_message('Response Body: ' . $response_body);
        
        if ($response_code !== 200) {
            return new WP_Error(
                'api_error',
                'LINE API 錯誤: ' . $response_code,
                array('response' => $response_body)
            );
        }
        
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
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            return new WP_Error('invalid_token', 'Token 驗證失敗');
        }
        
        return true;
    }
}

