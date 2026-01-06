jQuery(document).ready(function($) {
    
    // 測試 Token
    $('#wlm-test-token').on('click', function() {
        var button = $(this);
        var spinner = button.next('.spinner');
        var result = $('#wlm-test-token-result');
        
        button.prop('disabled', true);
        spinner.addClass('is-active');
        result.html('');
        
        if (typeof wlmAdmin !== 'undefined' && wlmAdmin.debug) {
            console.log('[WLM] 開始測試 Token...');
        }
        
        $.ajax({
            url: wlmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wlm_test_line_token',
                nonce: wlmAdmin.nonce
            },
            success: function(response) {
                // 只在開發模式下記錄詳細資訊
                if (typeof wlmAdmin !== 'undefined' && wlmAdmin.debug) {
                    console.log('[WLM] Token 測試回應:', response);
                }
                
                if (response.success) {
                    if (typeof wlmAdmin !== 'undefined' && wlmAdmin.debug) {
                        console.log('[WLM] ✓ Token 驗證成功');
                    }
                    result.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    if (typeof wlmAdmin !== 'undefined' && wlmAdmin.debug) {
                        console.error('[WLM] ✗ Token 驗證失敗');
                    }
                    result.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function(xhr, status, error) {
                // 不記錄敏感資訊，只記錄基本錯誤
                if (typeof wlmAdmin !== 'undefined' && wlmAdmin.debug) {
                    console.error('[WLM] Token 測試 AJAX 錯誤:', {
                        status: status,
                        error: error,
                        statusCode: xhr.status
                    });
                }
                result.html('<span style="color: red;">✗ 發生錯誤，請檢查設定</span>');
            },
            complete: function() {
                button.prop('disabled', false);
                spinner.removeClass('is-active');
            }
        });
    });
    
    // 發送測試訊息
    $('#wlm-send-test-message').on('click', function() {
        var button = $(this);
        var spinner = button.next('.spinner');
        var result = $('#wlm-test-message-result');
        var userId = $('#wlm-test-user-id').val();
        
        if (!userId) {
            console.warn('[WLM] 未輸入 User ID');
            result.html('<span style="color: red;">請輸入 User ID</span>');
            return;
        }
        
        button.prop('disabled', true);
        spinner.addClass('is-active');
        result.html('');
        
        if (typeof wlmAdmin !== 'undefined' && wlmAdmin.debug) {
            console.log('[WLM] 開始發送測試訊息...');
            console.log('[WLM] User ID:', userId);
        }
        
        $.ajax({
            url: wlmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wlm_send_test_message',
                nonce: wlmAdmin.nonce,
                user_id: userId
            },
            success: function(response) {
                if (typeof wlmAdmin !== 'undefined' && wlmAdmin.debug) {
                    console.log('[WLM] 測試訊息回應:', response);
                }
                
                if (response.success) {
                    if (typeof wlmAdmin !== 'undefined' && wlmAdmin.debug) {
                        console.log('[WLM] ✓ 測試訊息發送成功');
                    }
                    result.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    if (typeof wlmAdmin !== 'undefined' && wlmAdmin.debug) {
                        console.error('[WLM] ✗ 測試訊息發送失敗');
                    }
                    result.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function(xhr, status, error) {
                // 不記錄敏感資訊，只記錄基本錯誤
                if (typeof wlmAdmin !== 'undefined' && wlmAdmin.debug) {
                    console.error('[WLM] 測試訊息 AJAX 錯誤:', {
                        status: status,
                        error: error,
                        statusCode: xhr.status
                    });
                }
                
                // 嘗試解析錯誤回應，但不記錄完整內容
                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    result.html('<span style="color: red;">✗ 發生錯誤: ' + (errorResponse.data || error) + '</span>');
                } catch(e) {
                    result.html('<span style="color: red;">✗ 發生錯誤，請檢查設定</span>');
                }
            },
            complete: function() {
                button.prop('disabled', false);
                spinner.removeClass('is-active');
            }
        });
    });
    
});

