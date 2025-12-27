jQuery(document).ready(function($) {
    
    // 測試 Token
    $('#wlm-test-token').on('click', function() {
        var button = $(this);
        var spinner = button.next('.spinner');
        var result = $('#wlm-test-token-result');
        
        button.prop('disabled', true);
        spinner.addClass('is-active');
        result.html('');
        
        $.ajax({
            url: wlmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wlm_test_line_token',
                nonce: wlmAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    result.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    result.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                result.html('<span style="color: red;">✗ 發生錯誤</span>');
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
            result.html('<span style="color: red;">請輸入 User ID</span>');
            return;
        }
        
        button.prop('disabled', true);
        spinner.addClass('is-active');
        result.html('');
        
        $.ajax({
            url: wlmAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wlm_send_test_message',
                nonce: wlmAdmin.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    result.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    result.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                result.html('<span style="color: red;">✗ 發生錯誤</span>');
            },
            complete: function() {
                button.prop('disabled', false);
                spinner.removeClass('is-active');
            }
        });
    });
    
});

