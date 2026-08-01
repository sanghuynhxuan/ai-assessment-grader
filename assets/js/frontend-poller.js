jQuery(document).ready(function($) {
    var isScanning = false;

    var checkExist = setInterval(function() {
        if ($('.wpProQuiz_results').is(':visible') || $('.wpProQuiz_graded_points').is(':visible')) {
            clearInterval(checkExist);
            if ( !$('.wpProQuiz_results').data('ai-auto-started') ) {
                $('.wpProQuiz_results').data('ai-auto-started', true);
                fetchAndInjectFeedback(); 
            }
        }
    }, 200);

    $('body').on('click mouseup', 'input[name="reShowQuestion"], .wpProQuiz_button_reShowQuestion, .wpProQuiz_button_restartQuiz, .view-question-button', function() {
        setTimeout(fetchAndInjectFeedback, 1000);
    });

    function fetchAndInjectFeedback() {
        if (isScanning) return; 
        isScanning = true;

        $('.wpProQuiz_listItem').each(function() {
            var $li = $(this);
            var type = $li.attr('data-type');

            if ( !type || ['essay', 'free_answer', 'cloze_answer'].indexOf(type) === -1 ) return;
            if ( $li.data('ai-processed') ) return;

            var questionId = 0;
            var quizId = 0;
            
            try {
                var metaRaw = $li.attr('data-question-meta');
                if (metaRaw) {
                    var meta = JSON.parse(metaRaw);
                    if (meta && meta.question_post_id) questionId = meta.question_post_id;
                }
            } catch(e) {}

            if (!questionId) {
                var inputName = $li.find('input, textarea').attr('name');
                if(inputName) questionId = inputName.split('_').pop(); 
            }

            if ( !questionId ) return;

            var $target = $li.find('.wpProQuiz_questionList');
            if ($target.length === 0) $target = $li;

            $.ajax({
                url: ldAiVars.apiUrl + '/result?t=' + new Date().getTime(),
                method: 'GET',
                beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', ldAiVars.nonce); },
                data: { quiz_id: quizId, question_id: questionId },
                success: function(response) {
                    if ( response.status === 'completed' ) {
                        $li.data('ai-processed', true);
                        
                        var aiScore = parseFloat(response.score);
                        var maxPoints = parseFloat(response.max_points) || 1;
                        
                        $li.data('ai-score', aiScore);
                        $li.data('max-points', maxPoints);

                        if ( type === 'essay' ) {
                            $li.find('.graded-disclaimer').hide(); 
                            $li.find('.wpProQuiz_response').hide();
                            if ( aiScore > 0 ) {
                                $li.find('textarea').css({'border':'1px solid #4caf50', 'background':'#e8f5e9'});
                            }
                        } 
                        else {
                            var ldCorrect = $li.hasClass('wpProQuiz_answerCorrect');
                            if ( !ldCorrect && aiScore > 0.1 ) {
                                markQuestionAsCorrect($li);
                            }
                        }

                        recalculateTotalScore();

                        if ( $li.find('.ld-ai-feedback-box').length === 0 ) {
                            var html = buildFeedbackHTML(response);
                            
                            if ( type === 'essay' ) {
                                $('.wpProQuiz_graded_points').attr('style', 'display: none !important');
                                $('.wpProQuiz_points').attr('style', 'display: block !important');
                                $li.append(html);
                            } else {
                                $target.append(html);
                            }
                        }
                    }
                }
            });
        });
        
        setTimeout(function(){ isScanning = false; }, 2000);
    }

    function recalculateTotalScore() {
        var grandTotalEarned = 0;
        var grandTotalMax = 0;
        var totalCorrectQuestions = 0;

        $('.wpProQuiz_listItem').each(function() {
            var $li = $(this);
            var qPoints = 0;
            var qMax = 0;

            if ( $li.data('max-points') ) {
                qMax = parseFloat($li.data('max-points'));
            } else {
                try {
                    var meta = JSON.parse($li.attr('data-question-meta'));
                    qMax = parseFloat(meta.sfwd_question_question_points);
                    if (!qMax || isNaN(qMax)) qMax = parseFloat(meta.points) || 1;
                } catch(e) { 
                    qMax = 1;
                } 
            }

            grandTotalMax += qMax;

            if ( $li.data('ai-processed') ) {
                qPoints = parseFloat($li.data('ai-score')) || 0;
            } else {
                if ( $li.hasClass('wpProQuiz_answerCorrect') ) {
                    qPoints = qMax;
                } else {
                    qPoints = 0;
                }
            }

            grandTotalEarned += qPoints;

            if ( qPoints >= (qMax * 0.1) && qMax > 0 ) { 
                totalCorrectQuestions++;
            }
        });

        var $pointsContainer = $('.wpProQuiz_points');
        if ($pointsContainer.length) {
            var $spans = $pointsContainer.find('span');
            
            var displayEarned = parseFloat(grandTotalEarned).toFixed(2).replace(/[.,]00$/, "");
            $spans.eq(0).text(displayEarned);
            
            var displayMax = parseFloat(grandTotalMax).toFixed(2).replace(/[.,]00$/, "");
            $spans.eq(1).text(displayMax);

            var percent = 0;
            if (grandTotalMax > 0) {
                percent = Math.round((grandTotalEarned / grandTotalMax) * 100);
            }
            if(percent > 100) percent = 100;
            $spans.eq(2).text(percent + '%');
        }

        $('.wpProQuiz_correct_answer').text(totalCorrectQuestions);
    }

    function markQuestionAsCorrect($li) {
        var $itemContainer = $li.find('.wpProQuiz_questionListItem');
        $itemContainer.removeClass('wpProQuiz_answerIncorrect').addClass('wpProQuiz_answerCorrect');

        var $inputs = $itemContainer.find('input, textarea');
        $inputs.css('background-color', '#e8f5e9').css('border-color', '#4caf50').css('color', '#2e7d32');
        
        $li.find('.wpProQuiz_freeCorrect').attr('style', 'display: none !important'); 
        $li.find('.wpProQuiz_questionIncorrect').attr('style', 'display: none !important');
        $itemContainer.find('.ld-quiz-question-item__status--incorrect').attr('style', 'display: none !important');
        
        $itemContainer.find('.ld-quiz-question-item__status--correct').attr('style', 'display: inline-block !important; color: green; font-weight: bold;');
    }

    function buildFeedbackHTML(data) {
        var displayScore = parseFloat(data.score).toFixed(2).replace(/[.,]00$/, "");
        var displayMax = parseFloat(data.max_points).toFixed(2).replace(/[.,]00$/, "");

        return `
        <div class="ld-ai-feedback-box" style="background:#f0f7ff; border:1px solid #0056b3; padding:15px; margin-top:15px; border-radius:6px; width:100%; clear:both; position:relative; z-index:99;">
            <h4 style="color:#0056b3; margin:0 0 10px 0; font-size: 16px; display:flex; align-items:center;">
                <span style="margin-right:5px;">🤖</span> AI Feedback
            </h4>
            
            <div style="font-size:14px; color:#333; line-height:1.5; margin-bottom:10px;">
                ${data.feedback}
            </div>
            
            <div style="padding-top:10px; border-top:1px solid #cce5ff; color:#0056b3; font-weight:bold; font-size:14px;">
                Score: ${displayScore} / ${displayMax} Points
            </div>

            <div class="ld-ai-rating-section" data-log-id="${data.log_id}" style="margin-top:10px; font-size:12px; color:#666; display:flex; align-items:center;">
                <span style="margin-right:10px;">Is this feedback helpful?</span>
                <div class="ld-ai-rate-actions">
                    <button class="ld-ai-rate-btn" data-rate="5" style="border:1px solid #ccc; background:#fff; cursor:pointer; margin-right:5px; border-radius:3px; padding:2px 8px;">👍 Yes</button>
                    <button class="ld-ai-rate-btn" data-rate="1" style="border:1px solid #ccc; background:#fff; cursor:pointer; border-radius:3px; padding:2px 8px;">👎 No</button>
                </div>
                <span class="ld-ai-rate-msg" style="margin-left:10px; font-weight:bold; color:#28a745;"></span>
            </div>
        </div>`;
    }

    $(document).on('click', '.ld-ai-rate-btn', function(e) {
        e.preventDefault();
        var btn = $(this);
        var container = btn.closest('.ld-ai-rating-section');
        var logId = container.data('log-id');
        var rating = btn.data('rate');
        
        container.find('.ld-ai-rate-actions').fadeOut(200, function() {
            container.find('.ld-ai-rate-msg').text('Thanks for voting!').fadeIn();
        });

        $.ajax({
            url: ldAiVars.apiUrl + '/rate',
            method: 'POST',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', ldAiVars.nonce); },
            data: { log_id: logId, rating: rating }
        });
    });
});