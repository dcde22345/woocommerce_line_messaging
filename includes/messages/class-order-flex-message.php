<?php
/**
 * 訂單通知 Flex Message 類別
 *
 * @package WooCommerce_LINE_Messaging
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

class WLM_Order_Flex_Message {
    
    /**
     * 建立訂單建立成功的 Flex Message
     *
     * @param WC_Order $order 訂單物件
     * @return array Flex Message 內容
     */
    public static function create_order_success_message($order) {
        // 獲取訂單資訊
        $order_number = $order->get_order_number();
        
        // 獲取訂單總額並格式化（去除 HTML 標籤）
        $order_total_raw = $order->get_total();
        $order_currency = $order->get_currency();
        $order_total = self::format_order_total($order_total_raw, $order_currency);
        
        // 獲取付款方式
        $payment_method_title = $order->get_payment_method_title();
        if (empty($payment_method_title)) {
            $payment_method_title = '未指定';
        }
        
        // 獲取商品列表
        $items = $order->get_items();
        $product_list = self::format_product_list($items);
        
        // 獲取訂單查看 URL
        // 使用自訂路徑 my-account-2 而非預設的 my-account
        $order_id = $order->get_id();
        $order_view_url = home_url('/my-account-2/view-order/' . $order_id . '/');
        
        // 建立 Flex Message
        $flex_message = array(
            'type' => 'bubble',
            'header' => self::create_header(),
            'body' => self::create_body($order_number, $payment_method_title, $order_total, $product_list),
            'footer' => self::create_footer($order_view_url)
        );
        
        // 記錄除錯資訊（只在 WP_DEBUG 啟用時記錄）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // 記錄訂單原始資料
            error_log('[WLM Flex Message Debug] ============================================');
            error_log('[WLM Flex Message Debug] Order ID: ' . $order->get_id());
            error_log('[WLM Flex Message Debug] Order Number: ' . $order_number);
            error_log('[WLM Flex Message Debug] Order Total (formatted): ' . $order_total);
            error_log('[WLM Flex Message Debug] Order Total (raw): ' . $order->get_total());
            error_log('[WLM Flex Message Debug] Payment Method: ' . $payment_method_title);
            error_log('[WLM Flex Message Debug] Items Count: ' . count($items));
            
            // 記錄商品項目詳情
            if (!empty($items)) {
                error_log('[WLM Flex Message Debug] Items Details:');
                foreach ($items as $item_id => $item) {
                    error_log('[WLM Flex Message Debug]   - Item ID: ' . $item_id);
                    error_log('[WLM Flex Message Debug]     Name: ' . $item->get_name());
                    error_log('[WLM Flex Message Debug]     Quantity: ' . $item->get_quantity());
                    error_log('[WLM Flex Message Debug]     Total: ' . $item->get_total());
                }
            } else {
                error_log('[WLM Flex Message Debug] Items: EMPTY ARRAY');
            }
            
            // 記錄格式化後的商品列表
            error_log('[WLM Flex Message Debug] Product List Count: ' . count($product_list));
            if (!empty($product_list)) {
                error_log('[WLM Flex Message Debug] Product List: ' . json_encode($product_list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
            
            // 記錄完整的 Flex Message JSON
            error_log('[WLM Flex Message Debug] Flex Message JSON:');
            error_log(json_encode($flex_message, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            error_log('[WLM Flex Message Debug] ============================================');
        }
        
        return $flex_message;
    }
    
    /**
     * 建立 Header 區塊
     *
     * @return array Header 內容
     */
    private static function create_header() {
        return array(
            'type' => 'box',
            'layout' => 'vertical',
            'contents' => array(
                array(
                    'type' => 'text',
                    'text' => '下單成功!',
                    'weight' => 'bold',
                    'color' => '#111111',
                    'size' => 'xl',
                    'align' => 'start'
                )
            ),
            'backgroundColor' => '#FFD700',
            'paddingAll' => 'lg'
        );
    }
    
    /**
     * 建立 Body 區塊
     *
     * @param string $order_number       訂單編號
     * @param string $payment_method_title 付款方式
     * @param string $order_total       訂單總額
     * @param array  $product_list      商品列表
     * @return array Body 內容
     */
    private static function create_body($order_number, $payment_method_title, $order_total, $product_list) {
        return array(
            'type' => 'box',
            'layout' => 'vertical',
            'contents' => array(
                // 訂單編號
                array(
                    'type' => 'text',
                    'text' => '訂單編號 #' . $order_number,
                    'size' => 'md',
                    'color' => '#111111',
                    'weight' => 'bold',
                    'margin' => 'md'
                ),
                // 付款方式
                array(
                    'type' => 'text',
                    'text' => '付款方式: ' . $payment_method_title,
                    'size' => 'sm',
                    'color' => '#666666',
                    'margin' => 'sm'
                ),
                // 分隔線
                array(
                    'type' => 'separator',
                    'margin' => 'lg'
                ),
                // 請告知繳費超商區塊
                self::create_payment_instructions($order_total),
                // 分隔線
                array(
                    'type' => 'separator',
                    'margin' => 'lg'
                ),
                // 下單品項標題
                array(
                    'type' => 'text',
                    'text' => '下單品項',
                    'size' => 'sm',
                    'color' => '#111111',
                    'weight' => 'bold',
                    'margin' => 'lg'
                ),
                // 商品列表
                array(
                    'type' => 'box',
                    'layout' => 'vertical',
                    'margin' => 'md',
                    'spacing' => 'xs',
                    'contents' => $product_list
                )
            )
        );
    }
    
    /**
     * 建立付款說明區塊
     *
     * @param string $order_total 訂單總額
     * @return array 付款說明區塊內容
     */
    private static function create_payment_instructions($order_total) {
        return array(
            'type' => 'box',
            'layout' => 'vertical',
            'margin' => 'lg',
            'spacing' => 'xs',
            'contents' => array(
                array(
                    'type' => 'text',
                    'text' => '請告知繳費超商',
                    'size' => 'sm',
                    'color' => '#111111',
                    'weight' => 'bold'
                ),
                array(
                    'type' => 'text',
                    'text' => '【可選擇】全家 | OK | 萊爾富',
                    'size' => 'sm',
                    'color' => '#666666',
                    'margin' => 'xs',
                    'wrap' => true
                ),
                array(
                    'type' => 'text',
                    'text' => '【繳費金額】' . $order_total,
                    'size' => 'sm',
                    'color' => '#111111',
                    'weight' => 'bold',
                    'margin' => 'sm'
                ),
                array(
                    'type' => 'box',
                    'layout' => 'horizontal',
                    'margin' => 'sm',
                    'contents' => array(
                        array(
                            'type' => 'text',
                            'text' => '☆',
                            'size' => 'sm',
                            'color' => '#FFD700',
                            'flex' => 0
                        ),
                        array(
                            'type' => 'text',
                            'text' => '前往超商後告知小編會指導您付款',
                            'size' => 'sm',
                            'color' => '#666666',
                            'flex' => 1,
                            'wrap' => true
                        )
                    )
                )
            )
        );
    }
    
    /**
     * 格式化訂單總額（去除 HTML 標籤，純文字格式）
     *
     * @param float  $total    訂單總額
     * @param string $currency 貨幣代碼
     * @return string 格式化後的總額字串
     */
    private static function format_order_total($total, $currency = 'NTD') {
        // 使用 WooCommerce 的貨幣格式化函數
        $formatted = wc_price($total, array('currency' => $currency));
        // 先解碼 HTML 實體編碼（如 &#78;&#84;&#36; 轉換為 NT$）
        $decoded = html_entity_decode($formatted, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // 去除所有 HTML 標籤，只保留純文字
        $clean = wp_strip_all_tags($decoded);
        return $clean;
    }
    
    /**
     * 格式化商品列表
     *
     * @param array $items WooCommerce 訂單商品項目
     * @return array 格式化後的商品列表
     */
    private static function format_product_list($items) {
        $product_list = array();
        
        foreach ($items as $item) {
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();
            
            $product_list[] = array(
                'type' => 'box',
                'layout' => 'horizontal',
                'contents' => array(
                    array(
                        'type' => 'text',
                        'text' => $product_name . ' x' . $quantity,
                        'size' => 'sm',
                        'color' => '#111111',
                        'flex' => 0,
                        'wrap' => true
                    )
                )
            );
        }
        
        return $product_list;
    }
    
    /**
     * 建立 Footer 區塊（包含查看訂單按鈕）
     *
     * @param string $order_view_url 訂單查看 URL
     * @return array Footer 內容
     */
    private static function create_footer($order_view_url) {
        return array(
            'type' => 'box',
            'layout' => 'vertical',
            'spacing' => 'sm',
            'contents' => array(
                array(
                    'type' => 'button',
                    'style' => 'primary',
                    'height' => 'sm',
                    'action' => array(
                        'type' => 'uri',
                        'label' => '查看詳細訂單',
                        'uri' => $order_view_url
                    )
                )
            ),
            'flex' => 0
        );
    }
    
    /**
     * 建立測試訊息（使用假資料）
     *
     * @return array Flex Message 內容
     */
    public static function create_test_message() {
        // 讀取測試資料 JSON 檔案
        $test_data_file = WLM_PLUGIN_DIR . 'includes/messages/test-data.json';
        
        if (!file_exists($test_data_file)) {
            error_log('[WLM] 測試資料檔案不存在: ' . $test_data_file);
            return array();
        }
        
        $json_content = file_get_contents($test_data_file);
        $test_data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[WLM] 測試資料 JSON 解析錯誤: ' . json_last_error_msg());
            return array();
        }
        
        // 從 JSON 取得資料
        $order_id = isset($test_data['order_id']) ? $test_data['order_id'] : 0;
        $order_number = isset($test_data['order_number']) ? $test_data['order_number'] : '';
        $payment_method_title = isset($test_data['payment_method_title']) ? $test_data['payment_method_title'] : '';
        
        // 處理訂單總額：優先使用格式化後的總額，否則使用原始總額並格式化
        $order_total_raw = isset($test_data['order_total']) ? floatval($test_data['order_total']) : 0;
        $order_currency = isset($test_data['currency']) ? $test_data['currency'] : 'TWD';
        
        if (isset($test_data['order_total_formatted']) && !empty($test_data['order_total_formatted'])) {
            $order_total = $test_data['order_total_formatted'];
        } else {
            $order_total = self::format_order_total($order_total_raw, $order_currency);
        }
        
        // 格式化商品列表
        $product_list = array();
        if (isset($test_data['products']) && is_array($test_data['products'])) {
            foreach ($test_data['products'] as $product) {
                $product_name = isset($product['name']) ? $product['name'] : '';
                $quantity = isset($product['quantity']) ? $product['quantity'] : 1;
                
                if (!empty($product_name)) {
                    $product_list[] = array(
                        'type' => 'box',
                        'layout' => 'horizontal',
                        'contents' => array(
                            array(
                                'type' => 'text',
                                'text' => $product_name . ' x' . $quantity,
                                'size' => 'sm',
                                'color' => '#111111',
                                'flex' => 0,
                                'wrap' => true
                            )
                        )
                    );
                }
            }
        }
        
        // 獲取訂單查看 URL
        $order_view_url = '';
        if ($order_id > 0) {
            $order_view_url = home_url('/my-account-2/view-order/' . $order_id . '/');
        }
        
        // 建立 Flex Message
        $flex_message = array(
            'type' => 'bubble',
            'header' => self::create_header(),
            'body' => self::create_body($order_number, $payment_method_title, $order_total, $product_list)
        );
        
        // 如果有訂單 URL，加入 footer
        if (!empty($order_view_url)) {
            $flex_message['footer'] = self::create_footer($order_view_url);
        }
        
        return $flex_message;
    }
}

