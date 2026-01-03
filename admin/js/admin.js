jQuery(document).ready(function($) {
    
    // 測試 Token
    $('#wlm-test-token').on('click', function() {
        var button = $(this);
        var spinner = button.next('.spinner');
        var result = $('#wlm-test-token-result');
        
        button.prop('disabled', true);
        spinner.addClass('is-active');
        result.html('');
        
        console.log('[WLM] 開始測試 Token...');
        
        $.ajax({
            url: wlmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wlm_test_line_token',
                nonce: wlmAdmin.nonce
            },
            success: function(response) {
                console.log('[WLM] Token 測試回應:', response);
                
                if (response.success) {
                    console.log('[WLM] ✓ Token 驗證成功');
                    result.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    console.error('[WLM] ✗ Token 驗證失敗:', response.data);
                    result.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.error('[WLM] Token 測試 AJAX 錯誤:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                result.html('<span style="color: red;">✗ 發生錯誤 (請查看 Console)</span>');
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
        
        console.log('[WLM] 開始發送測試訊息...');
        console.log('[WLM] User ID:', userId);
        
        $.ajax({
            url: wlmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wlm_send_test_message',
                nonce: wlmAdmin.nonce,
                user_id: userId
            },
            success: function(response) {
                console.log('[WLM] 測試訊息回應:', response);
                
                if (response.success) {
                    console.log('[WLM] ✓ 測試訊息發送成功');
                    result.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    console.error('[WLM] ✗ 測試訊息發送失敗:', response.data);
                    result.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function(xhr, status, error) {
                console.error('[WLM] 測試訊息 AJAX 錯誤:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status,
                    fullResponse: xhr
                });
                
                // 嘗試解析錯誤回應
                try {
                    var errorResponse = JSON.parse(xhr.responseText);
                    console.error('[WLM] 錯誤回應內容:', errorResponse);
                    result.html('<span style="color: red;">✗ 發生錯誤: ' + (errorResponse.data || error) + ' (請查看 Console)</span>');
                } catch(e) {
                    result.html('<span style="color: red;">✗ 發生錯誤 (請查看 Console)</span>');
                }
            },
            complete: function() {
                button.prop('disabled', false);
                spinner.removeClass('is-active');
            }
        });
    });
    
});

