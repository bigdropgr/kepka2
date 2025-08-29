/* E-Learning Quiz System - Frontend JavaScript - Greek Ready Version (Part 1) */
/* Enhanced scoring with Greek language support */

jQuery(document).ready(function($) {
    'use strict';
    
    // Get current language
    const currentLang = document.documentElement.lang || 'el';
    const isGreek = currentLang.includes('el');
    
    // Check if elearningQuiz is defined
    if (typeof elearningQuiz === 'undefined') {
        console.error('E-Learning Quiz: elearningQuiz object not found - Scripts not properly loaded');
        return;
    }
    
    // Greek translations (fallback if not provided by PHP)
    const strings = {
        error: elearningQuiz.strings?.error || (isGreek ? 'Παρουσιάστηκε σφάλμα' : 'An error occurred'),
        loading: elearningQuiz.strings?.loading || (isGreek ? 'Φόρτωση...' : 'Loading...'),
        skip_to_quiz: elearningQuiz.strings?.skip_to_quiz || (isGreek ? 'Μετάβαση στο κουίζ' : 'Skip to quiz content'),
        time_remaining: elearningQuiz.strings?.time_remaining || (isGreek ? 'Χρόνος που απομένει' : 'Time Remaining'),
        one_minute_warning: elearningQuiz.strings?.one_minute_warning || (isGreek ? 'Απομένει ένα λεπτό' : 'One minute remaining'),
        time_up: elearningQuiz.strings?.time_up || (isGreek ? 'Ο χρόνος τελείωσε!' : "Time's Up!"),
        submitting_quiz: elearningQuiz.strings?.submitting_quiz || (isGreek ? 'Υποβολή του κουίζ σας...' : 'Your quiz is being submitted...'),
        drop_here: elearningQuiz.strings?.drop_here || (isGreek ? 'Αφήστε εδώ' : 'Drop here'),
        unanswered_questions: elearningQuiz.strings?.unanswered_questions || (isGreek ? 'Έχετε αναπάντητες ερωτήσεις: ' : 'You have unanswered questions: '),
        submit_anyway: elearningQuiz.strings?.submit_anyway || (isGreek ? 'Υποβολή ούτως ή άλλως;' : 'Submit anyway?'),
        leave_warning: elearningQuiz.strings?.leave_warning || (isGreek ? 'Έχετε μη αποθηκευμένη πρόοδο. Είστε σίγουροι ότι θέλετε να φύγετε;' : 'You have unsaved progress. Are you sure you want to leave?'),
        perfect_score: elearningQuiz.strings?.perfect_score || (isGreek ? 'Συγχαρητήρια, είχατε 100% επιτυχία!' : 'Congratulations, you had 100% success!'),
        congratulations_score: elearningQuiz.strings?.congratulations_score || (isGreek ? 'Συγχαρητήρια, είχατε %s% επιτυχία!' : 'Congratulations, you had %s% success!'),
        sorry_failed: elearningQuiz.strings?.sorry_failed || (isGreek ? 'Λυπούμαστε, είχατε %s% επιτυχία!' : 'Sorry, you had %s% success!'),
        points_earned: elearningQuiz.strings?.points_earned || (isGreek ? 'Βαθμοί που κερδίσατε: %s από %s' : 'Points earned: %s out of %s'),
        your_answer_was: elearningQuiz.strings?.your_answer_was || (isGreek ? 'Η απάντησή σας ήταν' : 'Your answer was'),
        correct_answer_is: elearningQuiz.strings?.correct_answer_is || (isGreek ? 'Η σωστή απάντηση είναι' : 'The correct answer is'),
        no_answer: elearningQuiz.strings?.no_answer || (isGreek ? 'Δεν δόθηκε απάντηση' : 'No answer provided'),
        question: elearningQuiz.strings?.question || (isGreek ? 'Ερώτηση' : 'Question'),
        points: elearningQuiz.strings?.points || (isGreek ? 'Βαθμοί' : 'Points'),
        retry_quiz: elearningQuiz.strings?.retry_quiz || (isGreek ? 'Επανάληψη Κουίζ' : 'Retry Quiz')
    };
    
    // Detect if device is mobile
    const isMobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || window.innerWidth <= 768;
    
    // Quiz state management
    let currentQuiz = {
        id: null,
        attemptId: null,
        currentQuestion: 0,
        totalQuestions: 0,
        answers: {},
        startTime: null,
        questionStartTime: null,
        timeLimit: 0,
        timerInterval: null,
        questionTimings: {},
        selectedWord: null, // For mobile tap interface
        selectedDraggable: null, // For mobile tap interface
        questions: [] // Store questions for results display
    };
    
    // Initialize quiz functionality
    initializeQuiz();
    
    function initializeQuiz() {
        // Start quiz button
        $('.start-quiz-btn').on('click', handleStartQuiz);
        
        // Retake quiz button
        $('.retake-quiz-btn').on('click', handleRetakeQuiz);
        
        // Navigation buttons
        $('.prev-btn').on('click', handlePreviousQuestion);
        $('.next-btn').on('click', handleNextQuestion);
        
        // Submit button - use event delegation for dynamically added buttons
        $(document).on('click', '.quiz-submit-btn', handleSubmitQuiz);
        
        // Answer change handlers
        $(document).on('change', '.quiz-question input[type="radio"], .quiz-question input[type="checkbox"]', handleAnswerChange);
        $(document).on('change', '.match-select', handleMatchingChange);
        
        // Initialize drag and drop OR tap interface based on device
        if (isMobileDevice) {
            initializeMobileTapInterface();
        } else {
            initializeDragAndDrop();
        }
        
        // Keyboard navigation
        $(document).on('keydown', handleKeyboardNavigation);
        
        // Modal handlers
        $(document).on('click', '#confirm-submit', confirmSubmitQuiz);
        $(document).on('click', '#cancel-submit', cancelSubmitQuiz);
        
        // Form submission prevention
        $('.elearning-quiz-form').on('submit', function(e) {
            e.preventDefault();
        });
        
        // Auto-save answers (accessibility feature)
        setInterval(autoSaveProgress, 30000); // Every 30 seconds
        
        // Handle page visibility change
        document.addEventListener('visibilitychange', handleVisibilityChange);
        
        // Handle page unload
        window.addEventListener('beforeunload', handlePageUnload);
        
        // Store quiz questions when they become active
        $(document).on('quiz-started', function() {
            $('.quiz-question').each(function(index) {
                const $question = $(this);
                currentQuiz.questions[index] = {
                    type: $question.data('question-type'),
                    question: $question.find('.question-text').html(),
                    element: $question.clone(),
                    index: index
                };
            });
        });
    }
    
    function handleStartQuiz() {
        const $btn = $(this);
        const quizId = $btn.data('quiz-id');
        
        if (!quizId) {
            console.error('No quiz ID found');
            showError(strings.error);
            return;
        }
        
        // Store original button text
        if (!$btn.data('original-text')) {
            $btn.data('original-text', $btn.text());
        }
        
        $btn.prop('disabled', true).text(strings.loading);
        
        // Start quiz attempt via AJAX
        $.ajax({
            url: elearningQuiz.ajaxUrl,
            type: 'POST',
            data: {
                action: 'elearning_start_quiz',
                quiz_id: quizId,
                nonce: elearningQuiz.nonce
            },
            success: function(response) {
                if (response.success) {
                    currentQuiz.id = quizId;
                    currentQuiz.attemptId = response.data.attempt_id;
                    currentQuiz.totalQuestions = response.data.total_questions;
                    currentQuiz.timeLimit = response.data.time_limit || 0;
                    currentQuiz.startTime = new Date();
                    
                    // Update form with attempt ID
                    $('input[name="attempt_id"]').val(currentQuiz.attemptId);
                    
                    // Show quiz form, hide intro
                    $('.elearning-quiz-intro').slideUp();
                    $('.elearning-quiz-form').slideDown(function() {
                        // Re-initialize accessibility after form is shown
                        initializeAccessibility();
                        // Trigger event to store questions
                        $(document).trigger('quiz-started');
                    });
                    
                    // Initialize first question
                    showQuestion(0);
                    
                    // Start quiz timer if time limit is set
                    if (currentQuiz.timeLimit > 0) {
                        startQuizTimer();
                    }
                    
                    // Start question timer
                    startQuestionTimer();
                    
                    // Focus first input for accessibility
                    setTimeout(() => {
                        $('.quiz-question.active').find('input, select').first().focus();
                    }, 500);
                    
                } else {
                    showError(response.data || strings.error);
                    $btn.prop('disabled', false).text($btn.data('original-text') || (isGreek ? 'Έναρξη Κουίζ' : 'Start Quiz'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                showError(strings.error);
                $btn.prop('disabled', false).text($btn.data('original-text') || (isGreek ? 'Έναρξη Κουίζ' : 'Start Quiz'));
            }
        });
    }
    
    function startQuizTimer() {
        if (currentQuiz.timeLimit <= 0) return;
        
        const endTime = new Date(currentQuiz.startTime.getTime() + currentQuiz.timeLimit * 60000);
        
        // Add timer display
        if (!$('.quiz-timer').length) {
            $('.quiz-progress').after('<div class="quiz-timer"><span class="timer-label">' + 
                strings.time_remaining + 
                ':</span> <span class="timer-display">--:--</span></div>');
        }
        
        // Update timer every second
        currentQuiz.timerInterval = setInterval(function() {
            const now = new Date();
            const remaining = Math.max(0, endTime - now);
            
            if (remaining <= 0) {
                clearInterval(currentQuiz.timerInterval);
                handleTimeUp();
                return;
            }
            
            const minutes = Math.floor(remaining / 60000);
            const seconds = Math.floor((remaining % 60000) / 1000);
            
            $('.timer-display').text(
                String(minutes).padStart(2, '0') + ':' + 
                String(seconds).padStart(2, '0')
            );
            
            // Warning at 1 minute
            if (remaining <= 60000 && !$('.quiz-timer').hasClass('warning')) {
                $('.quiz-timer').addClass('warning');
                announceToScreenReader(strings.one_minute_warning);
            }
        }, 1000);
    }
    
    function handleTimeUp() {
        clearInterval(currentQuiz.timerInterval);
        
        // Show time up modal
        const $modal = $('<div class="quiz-modal" id="time-up-modal">' +
            '<div class="modal-content">' +
            '<h3>' + strings.time_up + '</h3>' +
            '<p>' + strings.submitting_quiz + '</p>' +
            '<div class="loading-spinner"></div>' +
            '</div></div>');
        
        $('body').append($modal);
        $modal.fadeIn();
        
        // Auto-submit quiz
        saveCurrentAnswer();
        submitQuizData();
    }
    
    function handleRetakeQuiz() {
        $('.elearning-quiz-passed').slideUp();
        $('.elearning-quiz-intro').slideDown();
        
        // Reset quiz state
        resetQuizState();
        
        // Reset form
        $('.elearning-quiz-form')[0].reset();
        $('.quiz-question').removeClass('active');
        $('.quiz-results').hide();
        
        // Clear any previous answers
        $('.option-label').removeClass('selected');
        $('.blank-space').empty().removeClass('filled');
        $('.word-item').removeClass('used selected');
        $('.match-select').val('');
        $('.drop-zone').each(function() {
            $(this).html('<span class="drop-placeholder">' + strings.drop_here + '</span>')
                .removeClass('has-item');
        });
        $('.draggable-item').removeClass('used selected');
    }
    
    function resetQuizState() {
        // Clear timer
        if (currentQuiz.timerInterval) {
            clearInterval(currentQuiz.timerInterval);
        }
        
        currentQuiz = {
            id: null,
            attemptId: null,
            currentQuestion: 0,
            totalQuestions: 0,
            answers: {},
            startTime: null,
            questionStartTime: null,
            timeLimit: 0,
            timerInterval: null,
            questionTimings: {},
            selectedWord: null,
            selectedDraggable: null,
            questions: []
        };
        
        // Remove timer display
        $('.quiz-timer').remove();
    }
    
    function handlePreviousQuestion() {
        if (currentQuiz.currentQuestion > 0) {
            saveCurrentAnswer();
            recordQuestionTime();
            showQuestion(currentQuiz.currentQuestion - 1);
        }
    }
    
    function handleNextQuestion() {
        saveCurrentAnswer();
        recordQuestionTime();
        
        if (currentQuiz.currentQuestion < currentQuiz.totalQuestions - 1) {
            showQuestion(currentQuiz.currentQuestion + 1);
        }
    }
    


// Add this right before the submitQuizData call in handleSubmitQuiz function
function debugMatchingAnswers() {
    const $currentQuestion = $('.quiz-question.active');
    if ($currentQuestion.length === 0) return;
    
    const questionType = $currentQuestion.data('question-type');
    if (questionType !== 'matching') return;
    
    console.log('=== MATCHING DEBUG BEFORE SUBMIT ===');
    
    // Check all hidden inputs
    $currentQuestion.find('.match-answer').each(function() {
        const $input = $(this);
        const name = $input.attr('name');
        const value = $input.val();
        const leftMatch = name.match(/\[(\d+)\]$/);
        if (leftMatch) {
            const leftIndex = leftMatch[1];
            console.log(`Hidden input - Left ${leftIndex}: value = "${value}"`);
        }
    });
    
    // Check what saveCurrentAnswer will collect
    saveCurrentAnswer();
    const questionIndex = parseInt($currentQuestion.data('question-index'));
    console.log('Saved answer for submission:', currentQuiz.answers[questionIndex]);
    
    console.log('=================');
}

// Call this function in handleSubmitQuiz before submission
debugMatchingAnswers();

    function handleSubmitQuiz() {
        // Add this line for debugging:
        debugMatchingAnswers();
        
        // Save current answer first
        saveCurrentAnswer();
        
        // Check if all questions answered - but DON'T prevent submission
        const unanswered = [];
        $('.quiz-question').each(function(index) {
            const $question = $(this);
            const questionIndex = parseInt($question.data('question-index'));
            const questionType = $question.data('question-type');
            
            let hasAnswer = false;
            
            switch (questionType) {
                case 'multiple_choice':
                    hasAnswer = $question.find('input[type="radio"]:checked, input[type="checkbox"]:checked').length > 0;
                    break;
                case 'true_false':
                    hasAnswer = $question.find('input[type="radio"]:checked').length > 0;
                    break;
                case 'fill_blanks':
                    hasAnswer = $question.find('.blank-answer').filter(function() {
                        return $(this).val() !== '';
                    }).length > 0;
                    break;
                case 'matching':
                    hasAnswer = $question.find('.match-answer').filter(function() {
                        return $(this).val() !== '';
                    }).length > 0;
                    break;
            }
            
            if (!hasAnswer) {
                unanswered.push(index + 1);
            }
        });
        
        // Optional: Still show warning but don't block submission
        if (unanswered.length > 0) {
            const message = strings.unanswered_questions + 
                unanswered.join(', ') + '. ' + 
                strings.submit_anyway;
            
            if (!confirm(message)) {
                return;
            }
        }
        
        // Show confirmation modal
        const $confirmModal = $('#quiz-confirmation-modal');
        if ($confirmModal.length > 0) {
            $confirmModal.fadeIn();
        } else {
            // If modal doesn't exist, submit directly
            $('#quiz-loading-modal').fadeIn();
            submitQuizData();
        }
    }
 
    function confirmSubmitQuiz() {
        $('#quiz-confirmation-modal').fadeOut();
        $('#quiz-loading-modal').fadeIn();
        
        saveCurrentAnswer();
        recordQuestionTime();
        submitQuizData();
    }
    
    function submitQuizData() {
        // Clear timer
        if (currentQuiz.timerInterval) {
            clearInterval(currentQuiz.timerInterval);
        }
        
        // Prepare answers - ensure we have an object even if empty
        const answersToSubmit = currentQuiz.answers || {};
        
        console.log('Submitting quiz with answers:', answersToSubmit);
        
        // Submit quiz via AJAX
        $.ajax({
            url: elearningQuiz.ajaxUrl,
            type: 'POST',
            data: {
                action: 'elearning_submit_quiz',
                attempt_id: currentQuiz.attemptId,
                answers: JSON.stringify(answersToSubmit), // Will be {} if no answers
                question_timings: JSON.stringify(currentQuiz.questionTimings || {}),
                nonce: elearningQuiz.nonce
            },
            success: function(response) {
                $('#quiz-loading-modal').fadeOut();
                $('#time-up-modal').remove();
                
                console.log('Quiz submission response:', response);
                
                if (response.success) {
                    displayResults(response.data);
                } else {
                    console.error('Quiz submission failed:', response.data);
                    showError(response.data || strings.error);
                }
            },
            error: function(xhr, status, error) {
                $('#quiz-loading-modal').fadeOut();
                $('#time-up-modal').remove();
                console.error('AJAX error:', error);
                console.error('Response:', xhr.responseText);
                showError(strings.error);
            }
        });
    }
    
    function cancelSubmitQuiz() {
        $('#quiz-confirmation-modal').fadeOut();
    }
    
    function showQuestion(questionIndex) {
        // Update current question
        currentQuiz.currentQuestion = questionIndex;
        
        // Hide all questions
        $('.quiz-question').removeClass('active');
        
        // Show current question
        const $currentQuestion = $('.quiz-question').eq(questionIndex);
        $currentQuestion.addClass('active');
        
        // Store question data if not already stored
        if (!currentQuiz.questions[questionIndex]) {
            currentQuiz.questions[questionIndex] = {
                type: $currentQuestion.data('question-type'),
                question: $currentQuestion.find('.question-text').html(),
                element: $currentQuestion.clone()
            };
        }
        
        // Update progress
        updateProgress();
        
        // Update navigation buttons
        updateNavigationButtons();
        
        // Load saved answer if exists
        loadSavedAnswer(questionIndex);
        
        // Start question timer
        startQuestionTimer();
        
        // Scroll to top
        $currentQuestion[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Focus first input for accessibility
        setTimeout(() => {
            $currentQuestion.find('input:not([type="hidden"]), select').first().focus();
        }, 300);
        
        // Announce question to screen reader
        const questionNumber = questionIndex + 1;
        announceToScreenReader(`${strings.question} ${questionNumber} / ${currentQuiz.totalQuestions}`);
    }
    
    function updateProgress() {
        const percentage = ((currentQuiz.currentQuestion + 1) / currentQuiz.totalQuestions) * 100;
        $('.progress-fill').css('width', percentage + '%');
        $('.progress-text .current').text(currentQuiz.currentQuestion + 1);
        $('.progress-text .total').text(currentQuiz.totalQuestions);
        
        // Update progress bar accessibility
        $('.quiz-progress').attr('aria-valuenow', currentQuiz.currentQuestion + 1)
                          .attr('aria-valuetext', `${strings.question} ${currentQuiz.currentQuestion + 1} / ${currentQuiz.totalQuestions}`);
    }
    
    function updateNavigationButtons() {
        // Previous button
        if (currentQuiz.currentQuestion === 0) {
            $('.prev-btn').prop('disabled', true);
        } else {
            $('.prev-btn').prop('disabled', false);
        }
        
        // Next/Submit button
        if (currentQuiz.currentQuestion === currentQuiz.totalQuestions - 1) {
            $('.next-btn').hide();
            $('.quiz-submit-btn').show();
        } else {
            $('.next-btn').show();
            $('.quiz-submit-btn').hide();
        }
    }
    
    function handleAnswerChange() {
        const $question = $(this).closest('.quiz-question');
        const questionType = $question.data('question-type');
        
        // Visual feedback
        if ($(this).is(':radio')) {
            $question.find('.option-label').removeClass('selected');
        }
        $(this).closest('.option-label').addClass('selected');
        
        // Save answer immediately on change
        saveCurrentAnswer();
        
        // Auto-advance for single-choice questions (disabled for true/false)
        if (questionType === 'multiple_choice' && $question.find('input[type="radio"]').length > 0) {
            setTimeout(() => {
                if (currentQuiz.currentQuestion < currentQuiz.totalQuestions - 1) {
                    $('.next-btn').click();
                }
            }, 800);
        }
    }
    
    function handleMatchingChange() {
        const $select = $(this);
        const $question = $select.closest('.quiz-question');
        
        // Visual feedback
        $select.closest('.match-item').addClass('answered');
    }
    
    function saveCurrentAnswer() {
        const $currentQuestion = $('.quiz-question.active');
        if ($currentQuestion.length === 0) return;
        
        // Use the data-question-index attribute which has the original index
        const questionIndex = parseInt($currentQuestion.data('question-index'));
        const questionType = $currentQuestion.data('question-type');
        
        console.log('Saving answer for question index:', questionIndex, 'type:', questionType);
        
        let answer = null;
        
        switch (questionType) {
            case 'multiple_choice':
                const checkboxes = $currentQuestion.find('input[type="checkbox"]:checked');
                const radioButtons = $currentQuestion.find('input[type="radio"]:checked');
                
                if (checkboxes.length > 0) {
                    answer = [];
                    checkboxes.each(function() {
                        answer.push(parseInt($(this).val()));
                    });
                } else if (radioButtons.length > 0) {
                    answer = parseInt(radioButtons.val());
                }
                break;
                
            case 'true_false':
                const tfAnswer = $currentQuestion.find('input[type="radio"]:checked').val();
                if (tfAnswer !== undefined) {
                    answer = tfAnswer;
                }
                break;
                
            case 'fill_blanks':
                answer = [];
                $currentQuestion.find('.blank-answer').each(function() {
                    answer.push($(this).val() || '');
                });
                // Only set to null if ALL blanks are empty
                if (answer.every(a => a === '')) {
                    answer = null;
                }
                break;
                
            case 'matching':
                answer = {};
                let hasMatches = false;
                $currentQuestion.find('.match-answer').each(function() {
                    const $input = $(this);
                    const value = $input.val();
                    
                    if (value !== '' && value !== undefined) {
                        hasMatches = true;
                        // Extract left index from the input name
                        const inputName = $input.attr('name');
                        const leftMatch = inputName.match(/\[answers\]\[(\d+)\]$/);
                        if (leftMatch) {
                            const leftIndex = parseInt(leftMatch[1]);
                            const rightIndex = parseInt(value);
                            answer[leftIndex] = rightIndex;
                        }
                    }
                });
                if (!hasMatches) {
                    answer = null;
                }
                break;
        }
        
        // Store answer with the correct question index
        if (answer !== null) {
            currentQuiz.answers[questionIndex] = answer;
            console.log('Saved answer:', answer, 'for question:', questionIndex);
        } else {
            delete currentQuiz.answers[questionIndex];
            console.log('No answer for question:', questionIndex);
        }
    }
    
    function loadSavedAnswer(questionIndex) {
        const $question = $('.quiz-question').eq(questionIndex);
        const questionType = $question.data('question-type');
        const savedAnswer = currentQuiz.answers[questionIndex];
        
        if (!savedAnswer) return;
        
        switch (questionType) {
            case 'multiple_choice':
                if (Array.isArray(savedAnswer)) {
                    // Multiple select
                    savedAnswer.forEach(value => {
                        $question.find(`input[type="checkbox"][value="${value}"]`).prop('checked', true)
                            .closest('.option-label').addClass('selected');
                    });
                } else {
                    // Single select
                    $question.find(`input[type="radio"][value="${savedAnswer}"]`).prop('checked', true)
                        .closest('.option-label').addClass('selected');
                }
                break;
                
            case 'true_false':
                $question.find(`input[type="radio"][value="${savedAnswer}"]`).prop('checked', true)
                    .closest('.option-label').addClass('selected');
                break;
                
            case 'fill_blanks':
                if (Array.isArray(savedAnswer)) {
                    savedAnswer.forEach((value, index) => {
                        const $blank = $question.find(`.blank-space[data-blank-index="${index}"]`);
                        const $hiddenInput = $question.find(`.blank-answer[data-blank-index="${index}"]`);
                        
                        if (value) {
                            $blank.text(value).addClass('filled');
                            $hiddenInput.val(value);
                            
                            // Mark word as used
                            $question.find(`.word-item[data-word="${value}"]`).addClass('used');
                        }
                    });
                }
                break;
                
            case 'matching':
                Object.keys(savedAnswer).forEach(leftIndex => {
                    const rightIndex = savedAnswer[leftIndex];
                    const $dropZone = $question.find(`.drop-zone[data-left-index="${leftIndex}"]`);
                    const $draggableItem = $question.find(`.draggable-item[data-right-index="${rightIndex}"]`);
                    const itemText = $draggableItem.data('item-text');
                    
                    if ($dropZone.length && $draggableItem.length) {
                        // Add item to drop zone
                        const droppedItemHtml = `
                            <div class="dropped-item">
                                <span>${itemText}</span>
                                <button type="button" class="remove-match" data-left-index="${leftIndex}" data-right-index="${rightIndex}">×</button>
                            </div>
                        `;
                        $dropZone.html(droppedItemHtml).addClass('has-item');
                        
                        // Update hidden input
                        $question.find(`.match-answer[name*="[${leftIndex}]"]`).val(rightIndex);
                        
                        // Mark draggable item as used
                        $draggableItem.addClass('used');
                    }
                });
                break;
        }
    }
    
    function startQuestionTimer() {
        currentQuiz.questionStartTime = new Date();
    }
    
    function recordQuestionTime() {
        if (!currentQuiz.questionStartTime) return;
        
        const timeSpent = Math.round((new Date() - currentQuiz.questionStartTime) / 1000);
        currentQuiz.questionTimings[currentQuiz.currentQuestion] = timeSpent;
    }
    
    // DESKTOP: Original drag and drop functionality
    function initializeDragAndDrop() {
        // Use jQuery UI for better browser compatibility
        
        // === FILL IN THE BLANKS DRAG & DROP ===
        $('.word-item').draggable({
            revert: 'invalid',
            helper: 'clone',
            cursor: 'move',
            zIndex: 1000,
            start: function(event, ui) {
                if ($(this).hasClass('used')) {
                    return false;
                }
                $(this).addClass('dragging');
            },
            stop: function() {
                $(this).removeClass('dragging');
            }
        });
        
        $('.blank-space').droppable({
            accept: '.word-item',
            hoverClass: 'drop-target',
            drop: function(event, ui) {
                const $blank = $(this);
                const word = ui.draggable.data('word');
                const blankIndex = $blank.data('blank-index');
                const $question = $blank.closest('.quiz-question');
                
                // Clear previous value if any
                const previousWord = $blank.text();
                if (previousWord) {
                    $question.find(`.word-item[data-word="${previousWord}"]`).removeClass('used');
                }
                
                // Set new value
                $blank.text(word).addClass('filled');
                $question.find(`.blank-answer[data-blank-index="${blankIndex}"]`).val(word);
                
                // Mark word as used
                ui.draggable.addClass('used');
                
                // Save answer
                saveCurrentAnswer();
                
                // Provide feedback
                const greekAnnouncement = isGreek ? 
                    `${word} τοποθετήθηκε στο κενό ${blankIndex + 1}` : 
                    `${word} placed in blank ${blankIndex + 1}`;
                announceToScreenReader(greekAnnouncement);
            }
        });
        
        // === MATCHING DRAG & DROP === (FIXED VERSION)
        $('.draggable-item').draggable({
            revert: 'invalid',
            helper: 'clone',
            cursor: 'move',
            zIndex: 1000,
            start: function(event, ui) {
                if ($(this).hasClass('used')) {
                    return false;
                }
                $(this).addClass('dragging');
            },
            stop: function() {
                $(this).removeClass('dragging');
            }
        });

        $('.drop-zone').droppable({
            accept: '.draggable-item',
            hoverClass: 'drag-over',
            drop: function(event, ui) {
                const $dropZone = $(this);
                const itemText = ui.draggable.data('item-text');
                const rightIndex = ui.draggable.data('right-index');
                const leftIndex = $dropZone.data('left-index');
                const $question = $dropZone.closest('.quiz-question');
                const questionIndex = $question.data('question-index');
                
                // IMPORTANT: Clear any existing item in this drop zone first
                const $existingItem = $dropZone.find('.dropped-item');
                if ($existingItem.length > 0) {
                    const existingRightIndex = $existingItem.find('.remove-match').data('right-index');
                    // Re-enable the previously matched item
                    $question.find(`.draggable-item[data-right-index="${existingRightIndex}"]`).removeClass('used');
                }
                
                // Clear the drop zone
                $dropZone.empty();
                
                // Add new item to drop zone
                const droppedItemHtml = `
                    <div class="dropped-item">
                        <span>${itemText}</span>
                        <button type="button" class="remove-match" data-left-index="${leftIndex}" data-right-index="${rightIndex}">×</button>
                    </div>
                `;
                $dropZone.html(droppedItemHtml).addClass('has-item');
                
                // CRITICAL FIX: Use exact selector for the hidden input
                const inputName = `questions[${questionIndex}][answers][${leftIndex}]`;
                let $hiddenInput = $question.find(`input[name="${inputName}"]`);
                
                // If not found, try with escape
                if ($hiddenInput.length === 0) {
                    $hiddenInput = $question.find('input.match-answer').filter(function() {
                        return $(this).attr('name') === inputName;
                    });
                }
                
                if ($hiddenInput.length > 0) {
                    $hiddenInput.val(rightIndex);
                    console.log(`Set hidden input for Left ${leftIndex} to value: ${rightIndex}`);
                    console.log(`Hidden input found - name: ${$hiddenInput.attr('name')}, value: ${$hiddenInput.val()}`);
                } else {
                    console.error(`Could not find hidden input with name: ${inputName}`);
                }
                
                // Mark draggable item as used
                ui.draggable.addClass('used');
                
                // DON'T call saveCurrentAnswer here - it will be called when needed
                // saveCurrentAnswer();
                
                // Debug log to verify correct value is set
                console.log(`Matched: Left ${leftIndex} with Right ${rightIndex} (${itemText})`);
                
                // Provide feedback
                const greekAnnouncement = isGreek ? 
                    `${itemText} αντιστοιχίστηκε με το στοιχείο ${parseInt(leftIndex) + 1}` : 
                    `${itemText} matched with item ${parseInt(leftIndex) + 1}`;
                announceToScreenReader(greekAnnouncement);
            }
        });
        
        // Handle remove match button
        $(document).on('click', '.remove-match', function(e) {
            e.preventDefault();
            
            const leftIndex = $(this).data('left-index');
            const rightIndex = $(this).data('right-index');
            const $question = $(this).closest('.quiz-question');
            
            // Clear the drop zone
            const $dropZone = $(this).closest('.drop-zone');
            $dropZone.html('<span class="drop-placeholder">' + strings.drop_here + '</span>').removeClass('has-item');
            
            // Clear hidden input
            $question.find(`.match-answer[name*="[${leftIndex}]"]`).val('');
            
            // Mark draggable item as available again
            $question.find(`.draggable-item[data-right-index="${rightIndex}"]`).removeClass('used');
            
            // Save answer
            saveCurrentAnswer();
            
            // Provide feedback
            const greekAnnouncement = isGreek ? 
                `Η αντιστοίχιση αφαιρέθηκε από το στοιχείο ${parseInt(leftIndex) + 1}` : 
                `Match removed from item ${parseInt(leftIndex) + 1}`;
            announceToScreenReader(greekAnnouncement);
        });
        
        // Double-click to remove word from blank
        $(document).on('dblclick', '.blank-space.filled', function() {
            const word = $(this).text();
            const blankIndex = $(this).data('blank-index');
            const $question = $(this).closest('.quiz-question');
            
            $(this).text('').removeClass('filled');
            $question.find(`.blank-answer[data-blank-index="${blankIndex}"]`).val('');
            $question.find(`.word-item[data-word="${word}"]`).removeClass('used');
            
            // Save answer
            saveCurrentAnswer();
            
            const greekAnnouncement = isGreek ? 
                `${word} αφαιρέθηκε από το κενό ${blankIndex + 1}` : 
                `${word} removed from blank ${blankIndex + 1}`;
            announceToScreenReader(greekAnnouncement);
        });
    }
    /* E-Learning Quiz System - Frontend JavaScript - Greek Ready Version (Part 3 - Final) */
/* Continue from Part 2 */

    // MOBILE: Tap-to-select interface
    function initializeMobileTapInterface() {
        // Add mobile class to quiz container
        $('.elearning-quiz-container').addClass('mobile-interface');
        
        // === FILL IN THE BLANKS TAP INTERFACE ===
        
        // Tap on word to select
        $(document).on('click', '.word-item', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $word = $(this);
            const $question = $word.closest('.quiz-question');
            
            // Don't allow selection of used words
            if ($word.hasClass('used')) {
                return;
            }
            
            // Clear previous selection
            $question.find('.word-item').removeClass('selected');
            $question.find('.blank-space').removeClass('awaiting-selection');
            
            // Select this word
            $word.addClass('selected');
            currentQuiz.selectedWord = $word.data('word');
            
            // Highlight empty blanks
            $question.find('.blank-space:not(.filled)').addClass('awaiting-selection');
            
            // Provide feedback
            const message = isGreek ? 
                `Επιλέχθηκε η λέξη: ${currentQuiz.selectedWord}. Τώρα πατήστε σε ένα κενό για να την τοποθετήσετε.` :
                `Selected word: ${currentQuiz.selectedWord}. Now tap a blank to place it.`;
            announceToScreenReader(message);
        });
        
        // Tap on blank to place selected word
        $(document).on('click', '.blank-space', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $blank = $(this);
            const $question = $blank.closest('.quiz-question');
            const blankIndex = $blank.data('blank-index');
            
            if (currentQuiz.selectedWord) {
                // Clear previous value if any
                const previousWord = $blank.text();
                if (previousWord) {
                    $question.find(`.word-item[data-word="${previousWord}"]`).removeClass('used');
                }
                
                // Place the selected word
                $blank.text(currentQuiz.selectedWord).addClass('filled');
                $question.find(`.blank-answer[data-blank-index="${blankIndex}"]`).val(currentQuiz.selectedWord);
                
                // Mark word as used
                $question.find(`.word-item[data-word="${currentQuiz.selectedWord}"]`)
                    .removeClass('selected')
                    .addClass('used');
                
                // Clear selection state
                $question.find('.blank-space').removeClass('awaiting-selection');
                currentQuiz.selectedWord = null;
                
                // Save answer
                saveCurrentAnswer();
                
                // Provide feedback
                const message = isGreek ? 
                    `Η λέξη τοποθετήθηκε στο κενό ${blankIndex + 1}` :
                    `Word placed in blank ${blankIndex + 1}`;
                announceToScreenReader(message);
            } else if ($blank.hasClass('filled')) {
                // Tap on filled blank to remove word
                const word = $blank.text();
                
                $blank.text('').removeClass('filled');
                $question.find(`.blank-answer[data-blank-index="${blankIndex}"]`).val('');
                $question.find(`.word-item[data-word="${word}"]`).removeClass('used');
                
                // Save answer
                saveCurrentAnswer();
                
                const message = isGreek ? 
                    `${word} αφαιρέθηκε από το κενό ${blankIndex + 1}` :
                    `${word} removed from blank ${blankIndex + 1}`;
                announceToScreenReader(message);
            }
        });
        
        // === MATCHING TAP INTERFACE ===
        
        // Tap on draggable item to select
        $(document).on('click', '.draggable-item', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $item = $(this);
            const $question = $item.closest('.quiz-question');
            
            // Don't allow selection of used items
            if ($item.hasClass('used')) {
                return;
            }
            
            // Clear previous selection
            $question.find('.draggable-item').removeClass('selected');
            $question.find('.drop-zone').removeClass('awaiting-selection');
            
            // Select this item
            $item.addClass('selected');
            currentQuiz.selectedDraggable = {
                text: $item.data('item-text'),
                index: $item.data('right-index')
            };
            
            // Highlight empty drop zones
            $question.find('.drop-zone:not(.has-item)').addClass('awaiting-selection');
            
            // Provide feedback
            const message = isGreek ? 
                `Επιλέχθηκε: ${currentQuiz.selectedDraggable.text}. Τώρα πατήστε σε έναν στόχο για να το αντιστοιχίσετε.` :
                `Selected: ${currentQuiz.selectedDraggable.text}. Now tap a target to match it.`;
            announceToScreenReader(message);
        });
        
        // Tap on drop zone to place selected item
        $(document).on('click', '.drop-zone', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $dropZone = $(this);
            const $question = $dropZone.closest('.quiz-question');
            const leftIndex = $dropZone.data('left-index');
            
            if (currentQuiz.selectedDraggable && !$dropZone.hasClass('has-item')) {
                // Clear any existing match in this drop zone
                const $existingItem = $dropZone.find('.dropped-item');
                if ($existingItem.length > 0) {
                    const existingRightIndex = $existingItem.find('.remove-match').data('right-index');
                    $question.find(`.draggable-item[data-right-index="${existingRightIndex}"]`).removeClass('used');
                }
                
                // Place the selected item
                const droppedItemHtml = `
                    <div class="dropped-item">
                        <span>${currentQuiz.selectedDraggable.text}</span>
                        <button type="button" class="remove-match" data-left-index="${leftIndex}" data-right-index="${currentQuiz.selectedDraggable.index}">×</button>
                    </div>
                `;
                $dropZone.html(droppedItemHtml).addClass('has-item');
                
                // CRITICAL FIX: Update hidden input with the actual index
                const $hiddenInput = $question.find(`.match-answer[name*="[${leftIndex}]"]`);
                $hiddenInput.val(currentQuiz.selectedDraggable.index); // Use the actual index

                // Debug log
                console.log(`Mobile: Set hidden input for Left ${leftIndex} to value: ${currentQuiz.selectedDraggable.index}`);
                
                // Mark draggable item as used
                $question.find(`.draggable-item[data-right-index="${currentQuiz.selectedDraggable.index}"]`)
                    .removeClass('selected')
                    .addClass('used');
                
                // Clear selection state
                $question.find('.drop-zone').removeClass('awaiting-selection');
                currentQuiz.selectedDraggable = null;
                
                // Save answer
                saveCurrentAnswer();
                
                // Debug log
                console.log(`Mobile: Matched Left ${leftIndex} with Right ${currentQuiz.selectedDraggable.index}`);
                
                // Provide feedback
                const message = isGreek ? 
                    `Αντιστοιχίστηκε με το στοιχείο ${parseInt(leftIndex) + 1}` :
                    `Matched with item ${parseInt(leftIndex) + 1}`;
                announceToScreenReader(message);
            }
        });
        
        // Handle remove match button (same for mobile)
        $(document).on('click', '.remove-match', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const leftIndex = $(this).data('left-index');
            const rightIndex = $(this).data('right-index');
            const $question = $(this).closest('.quiz-question');
            
            // Clear the drop zone
            const $dropZone = $(this).closest('.drop-zone');
            $dropZone.html('<span class="drop-placeholder">' + strings.drop_here + '</span>').removeClass('has-item');
            
            // Clear hidden input
            $question.find(`.match-answer[name*="[${leftIndex}]"]`).val('');
            
            // Mark draggable item as available again
            $question.find(`.draggable-item[data-right-index="${rightIndex}"]`).removeClass('used');
            
            // Save answer
            saveCurrentAnswer();
            
            // Provide feedback
            const message = isGreek ? 
                `Η αντιστοίχιση αφαιρέθηκε από το στοιχείο ${parseInt(leftIndex) + 1}` :
                `Match removed from item ${parseInt(leftIndex) + 1}`;
            announceToScreenReader(message);
        });
        
        // Clear selection when tapping elsewhere
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.word-item, .blank-space, .draggable-item, .drop-zone').length) {
                $('.word-item').removeClass('selected');
                $('.draggable-item').removeClass('selected');
                $('.blank-space').removeClass('awaiting-selection');
                $('.drop-zone').removeClass('awaiting-selection');
                currentQuiz.selectedWord = null;
                currentQuiz.selectedDraggable = null;
            }
        });
    }
    
    function handleKeyboardNavigation(e) {
        // Handle keyboard navigation within quiz
        if (!$('.elearning-quiz-form').is(':visible')) return;
        
        switch (e.key) {
            case 'ArrowLeft':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    $('.prev-btn:not(:disabled)').click();
                }
                break;
                
            case 'ArrowRight':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    if ($('.next-btn').is(':visible')) {
                        $('.next-btn').click();
                    } else if ($('.quiz-submit-btn').is(':visible')) {
                        $('.quiz-submit-btn').click();
                    }
                }
                break;
                
            case 'Enter':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    if ($('.next-btn').is(':visible')) {
                        $('.next-btn').click();
                    } else if ($('.quiz-submit-btn').is(':visible')) {
                        $('.quiz-submit-btn').click();
                    }
                }
                break;
                
            case 'Escape':
                // Close modals
                $('.quiz-modal').fadeOut();
                break;
        }
    }

    // Function to properly display fill-in-the-blanks results

    function displayFillBlanksAnswer(result, showCorrect, isPerfectScore = false) {
        let html = '';
        
        // Check if we have the text with blanks
        if (result.text_with_blanks) {
            let textContent = result.text_with_blanks;
            
            // Apply paragraph formatting
            if (!textContent.includes('<p>')) {
                textContent = textContent.replace(/\n\n/g, '</p><p>');
                textContent = '<p>' + textContent + '</p>';
            }
            
            const userAnswers = result.user_answer_raw || [];
            const correctAnswers = result.word_bank || result.correct_answer_raw || [];
            
            let blankIndex = 0;
            
            // Replace {{blank}} markers with the actual answers
            textContent = textContent.replace(/\{\{blank\}\}/g, function(match) {
                let replacement;
                
                if (showCorrect) {
                    // Show correct answer with green highlight
                    const correctAnswer = correctAnswers[blankIndex] || '';
                    replacement = '<span class="blank-filled correct-answer-highlight" style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; font-weight: bold;">' + correctAnswer + '</span>';
                } else {
                    // Show user answer
                    const userAnswer = userAnswers[blankIndex] || '';
                    const isBlankCorrect = userAnswer.toLowerCase().trim() === (correctAnswers[blankIndex] || '').toLowerCase().trim();
                    
                    if (isPerfectScore || isBlankCorrect) {
                        replacement = '<span class="blank-filled correct" style="background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 4px; font-weight: bold;">' + (userAnswer || '___') + '</span>';
                    } else {
                        replacement = '<span class="blank-filled incorrect" style="background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-weight: bold;">' + (userAnswer || '___') + '</span>';
                    }
                }
                
                blankIndex++;
                return replacement;
            });
            
            html = '<div class="fill-blanks-result">' + textContent + '</div>';
        } else {
            // Fallback display
            html = '<div class="answer-value ' + (showCorrect ? 'correct-answer-highlight' : (result.correct ? 'correct' : 'incorrect')) + '">';
            html += showCorrect ? result.correct_answer : result.user_answer;
            html += '</div>';
        }
        
        return html;
    }
    
    function displayResults(resultData) {
        $('.elearning-quiz-form').slideUp();
        
        // Remove skip link when showing results
        $('.skip-link').remove();
        
        const passed = resultData.passed;
        const score = parseFloat(resultData.score);
        const correctAnswers = parseInt(resultData.correct_answers);
        const totalQuestions = parseInt(resultData.total_questions);
        const passingScore = parseFloat(resultData.passing_score);
        const totalPoints = parseInt(resultData.total_points || 0);
        const earnedPoints = parseFloat(resultData.earned_points || 0);
        
        let html = '<div class="quiz-results ' + (passed ? 'passed' : 'failed') + '">';
        
        // Result icon and message
        html += '<div class="result-icon">' + (passed ? '🎉' : '😞') + '</div>';
        html += '<div class="result-message">';
        
        if (score === 100) {
            // Perfect score
            html += '<h3>' + strings.perfect_score + '</h3>';
        } else if (passed) {
            // Passed but not perfect
            html += '<h3>' + strings.congratulations_score.replace('%s', score.toFixed(0)) + '</h3>';
        } else {
            // Failed
            html += '<h3>' + strings.sorry_failed.replace('%s', score.toFixed(0)) + '</h3>';
        }
        
        html += '</div>';
        
        // Score display
        html += '<div class="score-display">' + score.toFixed(1) + '%</div>';
        
        // Points breakdown if available
        if (totalPoints > 0) {
            html += '<div class="points-breakdown">';
            html += '<p class="points-info">' + 
                strings.points_earned
                    .replace('%s', earnedPoints.toFixed(1))
                    .replace('%s', totalPoints) + 
                '</p>';
            html += '</div>';
        }
        
        // Show detailed answers only if not perfect score
        if (score < 100 && resultData.show_answers && resultData.detailed_results) {
            html += '<div class="detailed-answers">';
            
            // Your answers section
            html += '<div class="your-answers-section">';
            html += '<h4>' + strings.your_answer_was + '</h4>';
            html += displayUserAnswers(resultData.detailed_results); // Using EXISTING function
            html += '</div>';
            
            // Correct answers section
            html += '<div class="correct-answers-section">';
            html += '<h4>' + strings.correct_answer_is + '</h4>';
            html += displayCorrectAnswers(resultData.detailed_results); // Using EXISTING function
            html += '</div>';
            
            html += '</div>';
        } else if (score === 100) {
            // For perfect score, just show user answers without corrections
            html += '<div class="detailed-answers perfect-score">';
            html += '<div class="your-answers-section">';
            html += displayUserAnswers(resultData.detailed_results, true); // Using EXISTING function
            html += '</div>';
            html += '</div>';
        }
        
        // Action buttons
        if (!passed) {
            html += '<button type="button" class="retry-btn" onclick="location.reload()">' + 
                strings.retry_quiz + '</button>';
        }
        
        html += '</div>';
        
        // Add custom styles for points display and skipped questions
        html += '<style>';
        html += '.points-breakdown { background: rgba(255,255,255,0.7); padding: 15px; border-radius: 8px; margin: 20px 0; }';
        html += '.points-info { font-size: 18px; margin: 0; color: #333; }';
        html += '.question-partial-credit { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 10px 0; }';
        html += '.question-partial-credit .points-display { font-weight: bold; color: #856404; }';

        // New styles for skipped questions
        html += '.question-result-display.skipped { background: #f8f9fa; border-left: 4px solid #6c757d; opacity: 0.9; }';
        html += '.question-header-result { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }';
        html += '.question-status { display: flex; align-items: center; font-size: 14px; font-weight: 600; }';
        html += '.question-status i { margin-right: 5px; }';
        html += '.skipped-status { color: #6c757d; }';
        html += '.correct-status { color: #28a745; }';
        html += '.partial-status { color: #ffc107; }';
        html += '.incorrect-status { color: #dc3545; }';
        html += '.answer-value.skipped { background: #e9ecef; color: #6c757d; padding: 10px; border-radius: 4px; font-style: italic; }';
        html += '.answer-value.skipped i { color: #6c757d; }';

        // Existing styles
        html += '.blank-filled { padding: 2px 8px; border-radius: 4px; font-weight: bold; display: inline-block; margin: 0 2px; }';
        html += '.blank-filled.correct-answer-highlight { background: #d1fae5; color: #065f46; }';
        html += '.blank-filled.correct { background: #d1fae5; color: #065f46; }';
        html += '.blank-filled.incorrect { background: #fee2e2; color: #991b1b; }';
        html += '</style>';
        
        $('.quiz-results').html(html).slideDown();
        
        // Scroll to results
        $('.quiz-results')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Focus on results for screen readers
        $('.quiz-results').attr('tabindex', '-1').focus();
        
        // Announce results to screen readers
        let announcement;
        if (score === 100) {
            announcement = strings.perfect_score;
        } else if (passed) {
            announcement = strings.congratulations_score.replace('%s', score.toFixed(0));
        } else {
            announcement = strings.sorry_failed.replace('%s', score.toFixed(0));
        }
        
        announceToScreenReader(announcement);
    }
    

    function displayUserAnswers(detailedResults, isPerfectScore = false) {
        let html = '<div class="user-answers-display">';
        
        detailedResults.forEach((result, index) => {
            const isCorrect = result.correct;
            const hasPartialCredit = result.partial_credit || false;
            const wasSkipped = result.skipped || false;
            const questionType = result.question_type;
            
            let questionClass = 'question-result-display';
            if (wasSkipped) {
                questionClass += ' skipped';
            } else if (isCorrect) {
                questionClass += ' correct';
            } else if (hasPartialCredit) {
                questionClass += ' partial-correct';
            } else {
                questionClass += ' incorrect';
            }
            
            html += '<div class="' + questionClass + '">';
            html += '<div class="question-header-result">';
            html += '<div class="question-number">' + strings.question + ' ' + (index + 1) + '</div>';
            
            // Add status indicator
            if (wasSkipped) {
                html += '<div class="question-status skipped-status">';
                html += '<i class="fas fa-minus-circle"></i> ';
                html += (isGreek ? 'Παραλείφθηκε' : 'Skipped');
                html += '</div>';
            } else if (isCorrect) {
                html += '<div class="question-status correct-status">';
                html += '<i class="fas fa-check-circle"></i> ';
                html += (isGreek ? 'Σωστό' : 'Correct');
                html += '</div>';
            } else if (hasPartialCredit) {
                html += '<div class="question-status partial-status">';
                html += '<i class="fas fa-exclamation-circle"></i> ';
                html += (isGreek ? 'Μερικώς Σωστό' : 'Partially Correct');
                html += '</div>';
            } else {
                html += '<div class="question-status incorrect-status">';
                html += '<i class="fas fa-times-circle"></i> ';
                html += (isGreek ? 'Λάθος' : 'Incorrect');
                html += '</div>';
            }
            
            html += '</div>';
            
            html += '<div class="question-text">' + result.question + '</div>';
            
            // Show points earned if available
            if (result.earned_points !== undefined && result.max_points !== undefined && !isPerfectScore) {
                html += '<div class="question-points">';
                html += '<span class="points-display">' + 
                    strings.points + ': ' + 
                    result.earned_points + '/' + result.max_points + 
                    '</span>';
                html += '</div>';
            }
            
            html += '<div class="answer-display">';
            
            if (wasSkipped) {
                // Special display for skipped questions
                html += '<div class="answer-value skipped">';
                html += '<i class="fas fa-info-circle"></i> ';
                html += (isGreek ? 'Δεν δόθηκε απάντηση σε αυτή την ερώτηση' : 'No answer was provided for this question');
                html += '</div>';
            } else if (questionType === 'fill_blanks') {
                // Special handling for fill in the blanks
                html += displayFillBlanksAnswer(result, false, isPerfectScore);
            } else {
                // Other question types
                let answerClass = 'answer-value';
                if (isCorrect) {
                    answerClass += ' correct';
                } else if (hasPartialCredit) {
                    answerClass += ' partial';
                } else {
                    answerClass += ' incorrect';
                }
                
                html += '<div class="' + answerClass + '">';
                html += result.user_answer || strings.no_answer;
                html += '</div>';
            }
            
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        return html;
    }
    
    function displayCorrectAnswers(detailedResults) {
        let html = '<div class="correct-answers-display">';
        
        detailedResults.forEach((result, index) => {
            const wasSkipped = result.skipped || false;
            
            html += '<div class="question-result-display">';
            html += '<div class="question-header-result">';
            html += '<div class="question-number">' + strings.question + ' ' + (index + 1) + '</div>';
            
            if (wasSkipped) {
                html += '<div class="question-status skipped-status">';
                html += '<i class="fas fa-minus-circle"></i> ';
                html += (isGreek ? 'Παραλείφθηκε' : 'Skipped');
                html += '</div>';
            }
            
            html += '</div>';
            html += '<div class="question-text">' + result.question + '</div>';
            
            html += '<div class="answer-display">';
            
            if (result.question_type === 'fill_blanks') {
                // Special handling for fill in the blanks
                html += displayFillBlanksAnswer(result, true);
            } else {
                // Other question types
                html += '<div class="answer-value correct-answer-highlight">';
                html += result.correct_answer;
                html += '</div>';
            }
            
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        return html;
    }
    
    function displayFillBlanksAnswer(result, showCorrect, isPerfectScore = false) {
        let html = '';
        
        // Check if we have the text with blanks
        if (result.text_with_blanks) {
            let textContent = result.text_with_blanks;
            
            // Apply paragraph formatting if not already present
            if (!textContent.includes('<p>')) {
                textContent = textContent.replace(/\n\n/g, '</p><p>');
                if (textContent.trim() !== '') {
                    textContent = '<p>' + textContent + '</p>';
                }
            }
            
            const userAnswers = result.user_answer_raw || [];
            const correctAnswers = result.word_bank || result.correct_answer_raw || [];
            
            let blankIndex = 0;
            
            // Replace {{blank}} markers with the actual answers
            textContent = textContent.replace(/\{\{blank\}\}/g, function(match) {
                let replacement;
                
                if (showCorrect) {
                    // Show correct answer with green highlight
                    const correctAnswer = correctAnswers[blankIndex] || '';
                    replacement = '<span class="blank-filled correct-answer-highlight">' + (correctAnswer || '___') + '</span>';
                } else {
                    // Show user answer
                    const userAnswer = userAnswers[blankIndex] || '';
                    const correctAnswer = correctAnswers[blankIndex] || '';
                    const isBlankCorrect = userAnswer.toLowerCase().trim() === correctAnswer.toLowerCase().trim();
                    
                    if (isPerfectScore || isBlankCorrect) {
                        replacement = '<span class="blank-filled correct">' + (userAnswer || '___') + '</span>';
                    } else {
                        replacement = '<span class="blank-filled incorrect">' + (userAnswer || '___') + '</span>';
                    }
                }
                
                blankIndex++;
                return replacement;
            });
            
            html = '<div class="fill-blanks-result">' + textContent + '</div>';
        } else {
            // Fallback display
            html = '<div class="answer-value ' + (showCorrect ? 'correct-answer-highlight' : (result.correct ? 'correct' : 'incorrect')) + '">';
            html += showCorrect ? result.correct_answer : result.user_answer;
            html += '</div>';
        }
        
        return html;
    }
    
    function autoSaveProgress() {
        if (!currentQuiz.attemptId) return;
        
        saveCurrentAnswer();
        
        // Auto-save progress (silent background save)
        $.ajax({
            url: elearningQuiz.ajaxUrl,
            type: 'POST',
            data: {
                action: 'elearning_save_progress',
                attempt_id: currentQuiz.attemptId,
                current_question: currentQuiz.currentQuestion,
                answers: JSON.stringify(currentQuiz.answers),
                nonce: elearningQuiz.nonce
            },
            success: function(response) {
                // Silent save - no user feedback needed
            },
            error: function() {
                // Silent error - don't disrupt user
            }
        });
    }

    // Add this right after saveCurrentAnswer() in the matching section:
function debugMatchingAnswers() {
    const $currentQuestion = $('.quiz-question.active');
    if ($currentQuestion.length === 0) return;
    
    const questionType = $currentQuestion.data('question-type');
    if (questionType !== 'matching') return;
    
    console.log('=== MATCHING DEBUG ===');
    console.log('Left Column Items:');
    $currentQuestion.find('.left-item').each(function(index) {
        const text = $(this).find('.item-text').first().text();
        console.log(`  ${index}: ${text}`);
    });
    
    console.log('Right Column Items (Original Order):');
    $currentQuestion.find('.draggable-item').each(function() {
        const index = $(this).data('right-index');
        const text = $(this).data('item-text');
        console.log(`  ${index}: ${text}`);
    });
    
    console.log('Current Matches:');
    $currentQuestion.find('.match-answer').each(function() {
        const name = $(this).attr('name');
        const value = $(this).val();
        const leftIndex = name.match(/\[(\d+)\]$/)[1];
        if (value !== '') {
            console.log(`  Left ${leftIndex} → Right ${value}`);
        }
    });
    
    console.log('=================');
}
    
    function showError(message) {
        const $errorDiv = $('<div class="quiz-error" role="alert" style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 15px; border-radius: 6px; margin: 20px 0;">' + message + '</div>');
        
        $('.elearning-quiz-container').prepend($errorDiv);
        
        // Auto-remove error after 5 seconds
        setTimeout(() => {
            $errorDiv.fadeOut(() => $errorDiv.remove());
        }, 5000);
        
        // Focus error for screen readers
        $errorDiv.attr('tabindex', '-1').focus();
    }
    
    function announceToScreenReader(message) {
        // Create temporary element for screen reader announcements
        const $announcement = $('<div>', {
            'class': 'sr-only',
            'aria-live': 'polite',
            'aria-atomic': 'true',
            'text': message
        });
        
        $('body').append($announcement);
        
        // Remove after announcement
        setTimeout(() => {
            $announcement.remove();
        }, 1000);
    }
    
    function handleVisibilityChange() {
        if (document.hidden) {
            // Page is hidden - pause timers (if needed)
        } else {
            // Page is visible - resume timers (if needed)
        }
    }
    
    function handlePageUnload(e) {
        if (currentQuiz.attemptId && Object.keys(currentQuiz.answers).length > 0) {
            autoSaveProgress();
            
            // Show warning if quiz is in progress
            const message = strings.leave_warning;
            e.returnValue = message;
            return message;
        }
    }
    
    // Initialize accessibility features
    function initializeAccessibility() {
        // Add skip links only if quiz form exists and is visible
        if ($('.elearning-quiz-form').length && $('.elearning-quiz-form').is(':visible')) {
            const $skipLink = $('<a href="#quiz-content" class="skip-link">' + 
                strings.skip_to_quiz + '</a>');
            $('.elearning-quiz-container').prepend($skipLink);
        }
        
        // Add landmark roles
        $('.elearning-quiz-form').attr('role', 'main').attr('id', 'quiz-content');
        $('.quiz-progress').attr('role', 'progressbar')
                          .attr('aria-label', isGreek ? 'Πρόοδος Κουίζ' : 'Quiz Progress')
                          .attr('aria-valuemin', 1)
                          .attr('aria-valuemax', currentQuiz.totalQuestions || 1);
        
        // Add live region for announcements
        if (!$('#quiz-announcements').length) {
            $('body').append('<div id="quiz-announcements" class="sr-only" aria-live="polite" aria-atomic="true"></div>');
        }
    }
    
    // Prevent context menu on quiz elements (prevent cheating)
    $('.elearning-quiz-container').on('contextmenu', function(e) {
        if ($(e.target).closest('.quiz-question').length > 0) {
            e.preventDefault();
            return false;
        }
    });
    
    // Prevent text selection on certain elements
    $('.word-item, .blank-space, .draggable-item').css({
        '-webkit-user-select': 'none',
        '-moz-user-select': 'none',
        '-ms-user-select': 'none',
        'user-select': 'none'
    });
    
    console.log('E-Learning Quiz Frontend loaded - Greek Ready Version');
});