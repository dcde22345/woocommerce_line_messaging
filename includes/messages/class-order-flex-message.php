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
        $order_total = $order->get_formatted_order_total();
        
        // 獲取付款方式
        $payment_method_title = $order->get_payment_method_title();
        if (empty($payment_method_title)) {
            $payment_method_title = '未指定';
        }
        
        // 獲取商品列表
        $items = $order->get_items();
        $product_list = self::format_product_list($items);
        
        // 建立 Flex Message
        $flex_message = array(
            'type' => 'bubble',
            'header' => self::create_header(),
            'body' => self::create_body($order_number, $payment_method_title, $order_total, $product_list)
        );
        
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
        $order_number = isset($test_data['order_number']) ? $test_data['order_number'] : '';
        $payment_method_title = isset($test_data['payment_method_title']) ? $test_data['payment_method_title'] : '';
        $order_total = isset($test_data['order_total']) ? $test_data['order_total'] : '';
        
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
        
        // 建立 Flex Message
        $flex_message = array(
            'type' => 'bubble',
            'header' => self::create_header(),
            'body' => self::create_body($order_number, $payment_method_title, $order_total, $product_list)
        );
        
        return $flex_message;
    }
}

