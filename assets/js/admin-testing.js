jQuery(document).ready(function($) {
    $('#btn-run-test').on('click', function(e) {
        e.preventDefault();
        
        let btn = $(this);
        let spinner = btn.next('.spinner');
        let container = $('#test-result-container');
        let placeholder = $('#test-placeholder');

        // UI Reset
        container.hide();
        placeholder.show().text('Processing... Connecting to AI...');
        btn.prop('disabled', true);
        spinner.addClass('is-active');

        // Data
        let data = {
            provider: $('#test-provider').val(),
            model: $('#test-model').val(),
            question: $('#test-question').val(),
            rubric: $('#test-rubric').val(),
            student_answer: $('#test-answer').val()
        };

        // Call API
        $.ajax({
            url: ldAiTestVars.apiUrl,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', ldAiTestVars.nonce);
            },
            data: data,
            success: function(res) {
                // Success UI
                placeholder.hide();
                container.show();
                
                $('#res-score').text(res.score);
                // Convert xuống dòng thành <br> để dễ đọc
                let feedbackHtml = res.feedback ? res.feedback.replace(/\n/g, '<br>') : 'No feedback';
                $('#res-feedback').html( feedbackHtml );
                
                $('#res-tokens').text(res.tokens);
                $('#res-cost').text(res.cost);
                
                // Format JSON đẹp
                let rawJson = res.raw;
                try {
                    rawJson = JSON.stringify(JSON.parse(res.raw), null, 2);
                } catch(e) {}
                $('#res-raw').text( rawJson );
            },
            error: function(xhr, status, error) {
                // HIỂN THỊ LỖI RA MÀN HÌNH (DEBUG)
                let errorMsg = "Unknown Error";
                if(xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                } else {
                    errorMsg = xhr.status + " " + xhr.statusText + ": " + xhr.responseText;
                }
                
                alert("❌ ERROR: " + errorMsg); // Alert lỗi để bạn thấy ngay
                placeholder.show().html('<div style="color:red; font-weight:bold; padding:10px; border:1px solid red; background:#fff0f0;">Failed: ' + errorMsg + '</div>');
            },
            complete: function() {
                btn.prop('disabled', false);
                spinner.removeClass('is-active');
            }
        });
    });
});