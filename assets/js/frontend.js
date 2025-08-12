/* E-Learning Quiz System - Frontend JavaScript - ENHANCED VERSION 4.0 */
/* Added: Multiple T/F support, Random ordering, Better scoring */

jQuery(document).ready(function($) {
    'use strict';
    
    // Check if elearningQuiz is defined
    if (typeof elearningQuiz === 'undefined') {
        console.error('E-Learning Quiz: elearningQuiz object not found - Scripts not properly loaded');
        return;
    }
    
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
        selectedWord: null,
        selectedDraggable: null,
        questions: [],
        questionsOrder: [], // Store the randomized order
        randomizeAnswers: false // Store randomize answers setting
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
        
        // Submit button
        $(document).on('click', '.quiz-submit-btn', handleSubmitQuiz);
        
        // Answer change handlers - Enhanced for multiple T/F
        $(document).on('change', '.quiz-question input[type="radio"], .quiz-question input[type="checkbox"]', handleAnswerChange);
        $(document).on('change', '.match-select', handleMatchingChange);
        
        // Initialize drag and drop OR tap interface
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
        
        // Auto-save answers
        setInterval(autoSaveProgress, 30000);
        
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
                    index: index,
                    originalIndex: $question.data('question-index') // Store original index
                };
            });
        });
    }
    
    function handleStartQuiz() {
        const $btn = $(this);
        const quizId = $btn.data('quiz-id');
        
        if (!quizId) {
            console.error('No quiz ID found');
            showError(elearningQuiz.strings.error || 'An error occurred');
            return;
        }
        
        if (!$btn.data('original-text')) {
            $btn.data('original-text', $btn.text());
        }
        
        $btn.prop('disabled', true).text(elearningQuiz.strings.loading || 'Loading...');
        
        // Get quiz settings first
        $.ajax({
            url: elearningQuiz.ajaxUrl,
            type: 'POST',
            data: {
                action: 'elearning_get_quiz_settings',
                quiz_id: quizId,
                nonce: elearningQuiz.nonce
            },
            success: function(settingsResponse) {
                if (settingsResponse.success) {
                    // Store randomization settings
                    currentQuiz.randomizeAnswers = settingsResponse.data.randomize_answers === 'yes';
                    
                    // Start quiz attempt
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
                                currentQuiz.questionsOrder = response.data.questions_order || [];
                                
                                // Update form with attempt ID
                                $('input[name="attempt_id"]').val(currentQuiz.attemptId);
                                
                                // Reorganize questions based on the randomized order
                                if (currentQuiz.questionsOrder.length > 0) {
                                    reorganizeQuestions();
                                }
                                
                                // Show quiz form, hide intro
                                $('.elearning-quiz-intro').slideUp();
                                $('.elearning-quiz-form').slideDown(function() {
                                    initializeAccessibility();
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
                                
                                // Focus first input
                                setTimeout(() => {
                                    $('.quiz-question.active').find('input, select').first().focus();
                                }, 500);
                                
                            } else {
                                showError(response.data || elearningQuiz.strings.error || 'An error occurred');
                                $btn.prop('disabled', false).text($btn.data('original-text') || 'Start Quiz');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error:', error);
                            showError(elearningQuiz.strings.error || 'An error occurred');
                            $btn.prop('disabled', false).text($btn.data('original-text') || 'Start Quiz');
                        }
                    });
                } else {
                    showError('Could not load quiz settings');
                    $btn.prop('disabled', false).text($btn.data('original-text') || 'Start Quiz');
                }
            }
        });
    }
    
    function reorganizeQuestions() {
        const $container = $('.quiz-questions-container');
        const $questions = $container.find('.quiz-question');
        const reorganized = [];
        
        // Create new order based on questionsOrder array
        currentQuiz.questionsOrder.forEach(function(originalIndex, newIndex) {
            const $question = $questions.filter('[data-question-index="' + originalIndex + '"]');
            if ($question.length > 0) {
                const $clonedQuestion = $question.clone();
                // Update the visual number but keep the original index for submission
                $clonedQuestion.find('.question-title').text('Ερώτηση ' + (newIndex + 1));
                $clonedQuestion.attr('data-display-index', newIndex);
                reorganized.push($clonedQuestion);
            }
        });
        
        // Clear container and add reorganized questions
        $container.empty();
        reorganized.forEach(function($question) {
            $container.append($question);
        });
        
        // Update total in progress display
        $('.progress-text .total').text(reorganized.length);
    }
    
    function startQuizTimer() {
        if (currentQuiz.timeLimit <= 0) return;
        
        const endTime = new Date(currentQuiz.startTime.getTime() + currentQuiz.timeLimit * 60000);
        
        if (!$('.quiz-timer').length) {
            $('.quiz-progress').after('<div class="quiz-timer"><span class="timer-label">' + 
                (elearningQuiz.strings.time_remaining || 'Time Remaining') + 
                ':</span> <span class="timer-display">--:--</span></div>');
        }
        
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
            
            if (remaining <= 60000 && !$('.quiz-timer').hasClass('warning')) {
                $('.quiz-timer').addClass('warning');
                announceToScreenReader(elearningQuiz.strings.one_minute_warning || 'One minute remaining');
            }
        }, 1000);
    }
    
    function handleTimeUp() {
        clearInterval(currentQuiz.timerInterval);
        
        const $modal = $('<div class="quiz-modal" id="time-up-modal">' +
            '<div class="modal-content">' +
            '<h3>' + (elearningQuiz.strings.time_up || 'Time\'s Up!') + '</h3>' +
            '<p>' + (elearningQuiz.strings.submitting_quiz || 'Your quiz is being submitted...') + '</p>' +
            '<div class="loading-spinner"></div>' +
            '</div></div>');
        
        $('body').append($modal);
        $modal.fadeIn();
        
        saveCurrentAnswer();
        submitQuizData();
    }
    
    function handleRetakeQuiz() {
        $('.elearning-quiz-passed').slideUp();
        $('.elearning-quiz-intro').slideDown();
        
        resetQuizState();
        
        $('.elearning-quiz-form')[0].reset();
        $('.quiz-question').removeClass('active');
        $('.quiz-results').hide();
        
        $('.option-label').removeClass('selected');
        $('.blank-space').empty().removeClass('filled');
        $('.word-item').removeClass('used selected');
        $('.match-select').val('');
        $('.drop-zone').each(function() {
            $(this).html('<span class="drop-placeholder">' + 
                (elearningQuiz.strings.drop_here || 'Drop here') + '</span>')
                .removeClass('has-item');
        });
        $('.draggable-item').removeClass('used selected');
    }
    
    function resetQuizState() {
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
            questions: [],
            questionsOrder: [],
            randomizeAnswers: false
        };
        
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
    
    function handleSubmitQuiz() {
        saveCurrentAnswer();
        
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
                    // Check for multiple T/F statements
                    const tfStatements = $question.find('.tf-statement-item');
                    if (tfStatements.length > 0) {
                        // Multiple statements
                        hasAnswer = tfStatements.find('input[type="radio"]:checked').length === tfStatements.length;
                    } else {
                        // Single T/F
                        hasAnswer = $question.find('input[type="radio"]:checked').length > 0;
                    }
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
            
            if (!hasAnswer && !currentQuiz.answers.hasOwnProperty(questionIndex)) {
                unanswered.push(index + 1);
            }
        });
        
        if (unanswered.length > 0) {
            const message = (elearningQuiz.strings.unanswered_questions || 'You have unanswered questions: ') + 
                unanswered.join(', ') + '. ' + 
                (elearningQuiz.strings.submit_anyway || 'Submit anyway?');
            
            if (!confirm(message)) {
                return;
            }
        }
        
        const $confirmModal = $('#quiz-confirmation-modal');
        if ($confirmModal.length > 0) {
            $confirmModal.fadeIn();
        } else {
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
        if (currentQuiz.timerInterval) {
            clearInterval(currentQuiz.timerInterval);
        }
        
        $.ajax({
            url: elearningQuiz.ajaxUrl,
            type: 'POST',
            data: {
                action: 'elearning_submit_quiz',
                attempt_id: currentQuiz.attemptId,
                answers: JSON.stringify(currentQuiz.answers),
                question_timings: JSON.stringify(currentQuiz.questionTimings),
                nonce: elearningQuiz.nonce
            },
            success: function(response) {
                $('#quiz-loading-modal').fadeOut();
                $('#time-up-modal').remove();
                
                if (response.success) {
                    displayResults(response.data);
                } else {
                    showError(response.data || elearningQuiz.strings.error || 'An error occurred');
                }
            },
            error: function() {
                $('#quiz-loading-modal').fadeOut();
                $('#time-up-modal').remove();
                showError(elearningQuiz.strings.error || 'An error occurred');
            }
        });
    }
    
    function cancelSubmitQuiz() {
        $('#quiz-confirmation-modal').fadeOut();
    }
    
    function showQuestion(questionIndex) {
        currentQuiz.currentQuestion = questionIndex;
        
        $('.quiz-question').removeClass('active');
        
        const $currentQuestion = $('.quiz-question').eq(questionIndex);
        $currentQuestion.addClass('active');
        
        if (!currentQuiz.questions[questionIndex]) {
            currentQuiz.questions[questionIndex] = {
                type: $currentQuestion.data('question-type'),
                question: $currentQuestion.find('.question-text').html(),
                element: $currentQuestion.clone()
            };
        }
        
        // Randomize answer order if enabled and not already randomized
        if (currentQuiz.randomizeAnswers && !$currentQuestion.data('answers-randomized')) {
            randomizeAnswers($currentQuestion);
            $currentQuestion.data('answers-randomized', true);
        }
        
        updateProgress();
        updateNavigationButtons();
        loadSavedAnswer(questionIndex);
        startQuestionTimer();
        
        $currentQuestion[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        setTimeout(() => {
            $currentQuestion.find('input:not([type="hidden"]), select').first().focus();
        }, 300);
        
        const questionNumber = questionIndex + 1;
        announceToScreenReader(`Question ${questionNumber} of ${currentQuiz.totalQuestions}`);
    }
    
    function randomizeAnswers($question) {
        const questionType = $question.data('question-type');
        
        if (questionType === 'multiple_choice') {
            const $container = $question.find('.multiple-choice-options');
            const $options = $container.find('.option-label').toArray();
            
            // Fisher-Yates shuffle
            for (let i = $options.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [$options[i], $options[j]] = [$options[j], $options[i]];
            }
            
            // Re-append in new order
            $container.empty();
            $options.forEach(option => $container.append(option));
        }
    }
    
    function updateProgress() {
        const percentage = ((currentQuiz.currentQuestion + 1) / currentQuiz.totalQuestions) * 100;
        $('.progress-fill').css('width', percentage + '%');
        $('.progress-text .current').text(currentQuiz.currentQuestion + 1);
        $('.progress-text .total').text(currentQuiz.totalQuestions);
        
        $('.quiz-progress').attr('aria-valuenow', currentQuiz.currentQuestion + 1)
                          .attr('aria-valuetext', `Question ${currentQuiz.currentQuestion + 1} of ${currentQuiz.totalQuestions}`);
    }
    
    function updateNavigationButtons() {
        if (currentQuiz.currentQuestion === 0) {
            $('.prev-btn').prop('disabled', true);
        } else {
            $('.prev-btn').prop('disabled', false);
        }
        
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
        
        if ($(this).is(':radio')) {
            // For radio buttons in the same group
            const name = $(this).attr('name');
            $question.find('input[type="radio"][name="' + name + '"]').closest('.option-label').removeClass('selected');
        }
        $(this).closest('.option-label').addClass('selected');
        
        saveCurrentAnswer();
        
        // Auto-advance for single-choice questions (but not for multiple T/F)
        const isMultipleTF = questionType === 'true_false' && $question.find('.tf-statement-item').length > 0;
        
        if (questionType === 'multiple_choice' && $question.find('input[type="radio"]').length > 0 && !isMultipleTF) {
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
        
        $select.closest('.match-item').addClass('answered');
    }
    
    function saveCurrentAnswer() {
        const $currentQuestion = $('.quiz-question.active');
        if ($currentQuestion.length === 0) return;
        
        const questionIndex = parseInt($currentQuestion.data('question-index'));
        const questionType = $currentQuestion.data('question-type');
        
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
                // Check if this is multiple T/F statements
                const tfStatements = $currentQuestion.find('.tf-statement-item');
                if (tfStatements.length > 0) {
                    // Multiple statements
                    answer = [];
                    tfStatements.each(function(index) {
                        const selectedValue = $(this).find('input[type="radio"]:checked').val();
                        answer[index] = selectedValue || null;
                    });
                } else {
                    // Single T/F
                    const tfAnswer = $currentQuestion.find('input[type="radio"]:checked').val();
                    if (tfAnswer !== undefined) {
                        answer = tfAnswer;
                    }
                }
                break;
                
            case 'fill_blanks':
                answer = [];
                $currentQuestion.find('.blank-answer').each(function() {
                    answer.push($(this).val() || '');
                });
                if (answer.every(a => a === '')) {
                    answer = null;
                }
                break;
                
            case 'matching':
                answer = {};
                let hasMatches = false;
                $currentQuestion.find('.match-answer').each(function() {
                    const $input = $(this);
                    const inputName = $input.attr('name');
                    const value = $input.val();
                    
                    if (value) {
                        hasMatches = true;
                        const leftIndexMatch = inputName.match(/\[(\d+)\]$/);
                        if (leftIndexMatch) {
                            const leftIndex = parseInt(leftIndexMatch[1]);
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
        
        if (answer !== null && answer !== undefined) {
            currentQuiz.answers[questionIndex] = answer;
        }
    }
    
    function loadSavedAnswer(questionIndex) {
        const $question = $('.quiz-question').eq(questionIndex);
        const questionType = $question.data('question-type');
        const originalIndex = $question.data('question-index');
        const savedAnswer = currentQuiz.answers[originalIndex];
        
        if (!savedAnswer) return;
        
        switch (questionType) {
            case 'multiple_choice':
                if (Array.isArray(savedAnswer)) {
                    savedAnswer.forEach(value => {
                        $question.find(`input[type="checkbox"][value="${value}"]`).prop('checked', true)
                            .closest('.option-label').addClass('selected');
                    });
                } else {
                    $question.find(`input[type="radio"][value="${savedAnswer}"]`).prop('checked', true)
                        .closest('.option-label').addClass('selected');
                }
                break;
                
            case 'true_false':
                if (Array.isArray(savedAnswer)) {
                    // Multiple T/F statements
                    savedAnswer.forEach((value, index) => {
                        if (value) {
                            $question.find(`.tf-statement-item:eq(${index}) input[type="radio"][value="${value}"]`)
                                .prop('checked', true)
                                .closest('.option-label').addClass('selected');
                        }
                    });
                } else {
                    // Single T/F
                    $question.find(`input[type="radio"][value="${savedAnswer}"]`).prop('checked', true)
                        .closest('.option-label').addClass('selected');
                }
                break;
                
            case 'fill_blanks':
                if (Array.isArray(savedAnswer)) {
                    savedAnswer.forEach((value, index) => {
                        const $blank = $question.find(`.blank-space[data-blank-index="${index}"]`);
                        const $hiddenInput = $question.find(`.blank-answer[data-blank-index="${index}"]`);
                        
                        if (value) {
                            $blank.text(value).addClass('filled');
                            $hiddenInput.val(value);
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
                        const droppedItemHtml = `
                            <div class="dropped-item">
                                <span>${itemText}</span>
                                <button type="button" class="remove-match" data-left-index="${leftIndex}" data-right-index="${rightIndex}">×</button>
                            </div>
                        `;
                        $dropZone.html(droppedItemHtml).addClass('has-item');
                        $question.find(`.match-answer[name*="[${leftIndex}]"]`).val(rightIndex);
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
        const $currentQuestion = $('.quiz-question.active');
        const originalIndex = $currentQuestion.data('question-index');
        currentQuiz.questionTimings[originalIndex] = timeSpent;
    }
    
    // DESKTOP: Drag and drop functionality
    function initializeDragAndDrop() {
        // Fill in the blanks drag & drop
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
                
                const previousWord = $blank.text();
                if (previousWord) {
                    $question.find(`.word-item[data-word="${previousWord}"]`).removeClass('used');
                }
                
                $blank.text(word).addClass('filled');
                $question.find(`.blank-answer[data-blank-index="${blankIndex}"]`).val(word);
                
                ui.draggable.addClass('used');
                
                saveCurrentAnswer();
                announceToScreenReader(`${word} placed in blank ${blankIndex + 1}`);
            }
        });
        
        // Matching drag & drop
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
                
                const $hiddenInput = $question.find(`.match-answer[name*="[${leftIndex}]"]`);
                const previousRightIndex = $hiddenInput.val();
                if (previousRightIndex) {
                    $question.find(`.draggable-item[data-right-index="${previousRightIndex}"]`).removeClass('used');
                }
                
                $dropZone.find('.dropped-item').remove();
                $dropZone.removeClass('has-item');
                
                const droppedItemHtml = `
                    <div class="dropped-item">
                        <span>${itemText}</span>
                        <button type="button" class="remove-match" data-left-index="${leftIndex}" data-right-index="${rightIndex}">×</button>
                    </div>
                `;
                $dropZone.html(droppedItemHtml).addClass('has-item');
                
                $hiddenInput.val(rightIndex);
                ui.draggable.addClass('used');
                
                saveCurrentAnswer();
                announceToScreenReader(`${itemText} matched with item ${parseInt(leftIndex) + 1}`);
            }
        });
        
        // Remove match button
        $(document).on('click', '.remove-match', function(e) {
            e.preventDefault();
            
            const leftIndex = $(this).data('left-index');
            const rightIndex = $(this).data('right-index');
            const $question = $(this).closest('.quiz-question');
            
            const $dropZone = $(this).closest('.drop-zone');
            $dropZone.html('<span class="drop-placeholder">' + 
                (elearningQuiz.strings.drop_here || 'Drop here') + '</span>').removeClass('has-item');
            
            $question.find(`.match-answer[name*="[${leftIndex}]"]`).val('');
            $question.find(`.draggable-item[data-right-index="${rightIndex}"]`).removeClass('used');
            
            saveCurrentAnswer();
            announceToScreenReader(`Match removed from item ${parseInt(leftIndex) + 1}`);
        });
        
        // Double-click to remove word from blank
        $(document).on('dblclick', '.blank-space.filled', function() {
            const word = $(this).text();
            const blankIndex = $(this).data('blank-index');
            const $question = $(this).closest('.quiz-question');
            
            $(this).text('').removeClass('filled');
            $question.find(`.blank-answer[data-blank-index="${blankIndex}"]`).val('');
            $question.find(`.word-item[data-word="${word}"]`).removeClass('used');
            
            saveCurrentAnswer();
            announceToScreenReader(`${word} removed from blank ${blankIndex + 1}`);
        });
    }
    
    // MOBILE: Tap-to-select interface
    function initializeMobileTapInterface() {
        $('.elearning-quiz-container').addClass('mobile-interface');
        
        // Fill in the blanks tap interface
        $(document).on('click', '.word-item', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $word = $(this);
            const $question = $word.closest('.quiz-question');
            
            if ($word.hasClass('used')) {
                return;
            }
            
            $question.find('.word-item').removeClass('selected');
            $question.find('.blank-space').removeClass('awaiting-selection');
            
            $word.addClass('selected');
            currentQuiz.selectedWord = $word.data('word');
            
            $question.find('.blank-space:not(.filled)').addClass('awaiting-selection');
            
            announceToScreenReader(`Selected word: ${currentQuiz.selectedWord}. Now tap a blank to place it.`);
        });
        
        $(document).on('click', '.blank-space', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $blank = $(this);
            const $question = $blank.closest('.quiz-question');
            const blankIndex = $blank.data('blank-index');
            
            if (currentQuiz.selectedWord) {
                const previousWord = $blank.text();
                if (previousWord) {
                    $question.find(`.word-item[data-word="${previousWord}"]`).removeClass('used');
                }
                
                $blank.text(currentQuiz.selectedWord).addClass('filled');
                $question.find(`.blank-answer[data-blank-index="${blankIndex}"]`).val(currentQuiz.selectedWord);
                
                $question.find(`.word-item[data-word="${currentQuiz.selectedWord}"]`)
                    .removeClass('selected')
                    .addClass('used');
                
                $question.find('.blank-space').removeClass('awaiting-selection');
                currentQuiz.selectedWord = null;
                
                saveCurrentAnswer();
                announceToScreenReader(`Word placed in blank ${blankIndex + 1}`);
            } else if ($blank.hasClass('filled')) {
                const word = $blank.text();
                
                $blank.text('').removeClass('filled');
                $question.find(`.blank-answer[data-blank-index="${blankIndex}"]`).val('');
                $question.find(`.word-item[data-word="${word}"]`).removeClass('used');
                
                saveCurrentAnswer();
                announceToScreenReader(`${word} removed from blank ${blankIndex + 1}`);
            }
        });
        
        // Matching tap interface
        $(document).on('click', '.draggable-item', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $item = $(this);
            const $question = $item.closest('.quiz-question');
            
            if ($item.hasClass('used')) {
                return;
            }
            
            $question.find('.draggable-item').removeClass('selected');
            $question.find('.drop-zone').removeClass('awaiting-selection');
            
            $item.addClass('selected');
            currentQuiz.selectedDraggable = {
                text: $item.data('item-text'),
                index: $item.data('right-index')
            };
            
            $question.find('.drop-zone:not(.has-item)').addClass('awaiting-selection');
            
            announceToScreenReader(`Selected: ${currentQuiz.selectedDraggable.text}. Now tap a target to match it.`);
        });
        
        $(document).on('click', '.drop-zone', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $dropZone = $(this);
            const $question = $dropZone.closest('.quiz-question');
            const leftIndex = $dropZone.data('left-index');
            
            if (currentQuiz.selectedDraggable && !$dropZone.hasClass('has-item')) {
                const $hiddenInput = $question.find(`.match-answer[name*="[${leftIndex}]"]`);
                const previousRightIndex = $hiddenInput.val();
                if (previousRightIndex) {
                    $question.find(`.draggable-item[data-right-index="${previousRightIndex}"]`).removeClass('used');
                }
                
                const droppedItemHtml = `
                    <div class="dropped-item">
                        <span>${currentQuiz.selectedDraggable.text}</span>
                        <button type="button" class="remove-match" data-left-index="${leftIndex}" data-right-index="${currentQuiz.selectedDraggable.index}">×</button>
                    </div>
                `;
                $dropZone.html(droppedItemHtml).addClass('has-item');
                
                $hiddenInput.val(currentQuiz.selectedDraggable.index);
                
                $question.find(`.draggable-item[data-right-index="${currentQuiz.selectedDraggable.index}"]`)
                    .removeClass('selected')
                    .addClass('used');
                
                $question.find('.drop-zone').removeClass('awaiting-selection');
                currentQuiz.selectedDraggable = null;
                
                saveCurrentAnswer();
                announceToScreenReader(`Matched with item ${parseInt(leftIndex) + 1}`);
            }
        });
        
        $(document).on('click', '.remove-match', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const leftIndex = $(this).data('left-index');
            const rightIndex = $(this).data('right-index');
            const $question = $(this).closest('.quiz-question');
            
            const $dropZone = $(this).closest('.drop-zone');
            $dropZone.html('<span class="drop-placeholder">' + 
                (elearningQuiz.strings.drop_here || 'Drop here') + '</span>').removeClass('has-item');
            
            $question.find(`.match-answer[name*="[${leftIndex}]"]`).val('');
            $question.find(`.draggable-item[data-right-index="${rightIndex}"]`).removeClass('used');
            
            saveCurrentAnswer();
            announceToScreenReader(`Match removed from item ${parseInt(leftIndex) + 1}`);
        });
        
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
                $('.quiz-modal').fadeOut();
                break;
        }
    }
    
    function displayResults(resultData) {
        $('.elearning-quiz-form').slideUp();
        $('.skip-link').remove();
        
        const passed = resultData.passed;
        const score = parseFloat(resultData.score);
        const correctAnswers = parseInt(resultData.correct_answers);
        const totalQuestions = parseInt(resultData.total_questions);
        const passingScore = parseFloat(resultData.passing_score);
        const totalPoints = parseInt(resultData.total_points || 0);
        const earnedPoints = parseFloat(resultData.earned_points || 0);
        
        let html = '<div class="quiz-results ' + (passed ? 'passed' : 'failed') + '">';
        
        html += '<div class="result-icon">' + (passed ? '🎉' : '😞') + '</div>';
        html += '<div class="result-message">';
        
        if (score === 100) {
            html += '<h3>' + (elearningQuiz.strings.perfect_score || 'Congratulations, you had 100% success!') + '</h3>';
        } else if (passed) {
            html += '<h3>' + (elearningQuiz.strings.congratulations_score || 'Congratulations, you had %s% success!').replace('%s', score.toFixed(0)) + '</h3>';
        } else {
            html += '<h3>' + (elearningQuiz.strings.sorry_failed || 'Sorry, you had %s% success!').replace('%s', score.toFixed(0)) + '</h3>';
        }
        
        html += '</div>';
        
        html += '<div class="score-display">' + score.toFixed(1) + '%</div>';
        
        if (totalPoints > 0) {
            html += '<div class="points-breakdown">';
            html += '<p class="points-info">' + 
                (elearningQuiz.strings.points_earned || 'Points earned: %s out of %s')
                    .replace('%s', earnedPoints.toFixed(1))
                    .replace('%s', totalPoints) + 
                '</p>';
            html += '</div>';
        }
        
        if (score < 100 && resultData.show_answers && resultData.detailed_results) {
            html += '<div class="detailed-answers">';
            
            html += '<div class="your-answers-section">';
            html += '<h4>' + (elearningQuiz.strings.your_answer_was || 'Your answer was') + '</h4>';
            html += displayUserAnswers(resultData.detailed_results);
            html += '</div>';
            
            html += '<div class="correct-answers-section">';
            html += '<h4>' + (elearningQuiz.strings.correct_answer_is || 'The correct answer is') + '</h4>';
            html += displayCorrectAnswers(resultData.detailed_results);
            html += '</div>';
            
            html += '</div>';
        } else if (score === 100) {
            html += '<div class="detailed-answers perfect-score">';
            html += '<div class="your-answers-section">';
            html += displayUserAnswers(resultData.detailed_results, true);
            html += '</div>';
            html += '</div>';
        }
        
        if (!passed) {
            html += '<button type="button" class="retry-btn" onclick="location.reload()">' + 
                (elearningQuiz.strings.retry_quiz || 'Retry Quiz') + '</button>';
        }
        
        html += '</div>';
        
        html += '<style>';
        html += '.points-breakdown { background: rgba(255,255,255,0.7); padding: 15px; border-radius: 8px; margin: 20px 0; }';
        html += '.points-info { font-size: 18px; margin: 0; color: #333; }';
        html += '.question-partial-credit { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 10px 0; }';
        html += '.question-partial-credit .points-display { font-weight: bold; color: #856404; }';
        html += '</style>';
        
        $('.quiz-results').html(html).slideDown();
        
        $('.quiz-results')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        $('.quiz-results').attr('tabindex', '-1').focus();
        
        let announcement;
        if (score === 100) {
            announcement = elearningQuiz.strings.perfect_score || 'Congratulations, you had 100% success!';
        } else if (passed) {
            announcement = (elearningQuiz.strings.congratulations_score || 'Congratulations, you had %s% success!').replace('%s', score.toFixed(0));
        } else {
            announcement = (elearningQuiz.strings.sorry_failed || 'Sorry, you had %s% success!').replace('%s', score.toFixed(0));
        }
        
        announceToScreenReader(announcement);
    }
    
    function displayUserAnswers(detailedResults, isPerfectScore = false) {
        let html = '<div class="user-answers-display">';
        
        detailedResults.forEach((result, index) => {
            const isCorrect = result.correct;
            const hasPartialCredit = result.partial_credit || false;
            const questionType = result.question_type;
            
            let questionClass = 'question-result-display';
            if (isCorrect) {
                questionClass += ' correct';
            } else if (hasPartialCredit) {
                questionClass += ' partial-correct';
            } else {
                questionClass += ' incorrect';
            }
            
            html += '<div class="' + questionClass + '">';
            html += '<div class="question-number">' + (elearningQuiz.strings.question || 'Question') + ' ' + (index + 1) + '</div>';
            html += '<div class="question-text">' + result.question + '</div>';
            
            if (result.earned_points !== undefined && result.max_points !== undefined && !isPerfectScore) {
                html += '<div class="question-points">';
                html += '<span class="points-display">' + 
                    (elearningQuiz.strings.points || 'Points') + ': ' + 
                    result.earned_points + '/' + result.max_points + 
                    '</span>';
                html += '</div>';
            }
            
            html += '<div class="answer-display">';
            
            if (questionType === 'fill_blanks') {
                html += displayFillBlanksAnswer(result, false, isPerfectScore);
            } else if (questionType === 'true_false' && result.tf_statements) {
                // Multiple T/F statements
                html += displayMultipleTFAnswer(result, false, isPerfectScore);
            } else {
                let answerClass = 'answer-value';
                if (isCorrect) {
                    answerClass += ' correct';
                } else if (hasPartialCredit) {
                    answerClass += ' partial';
                } else {
                    answerClass += ' incorrect';
                }
                
                html += '<div class="' + answerClass + '">';
                html += result.user_answer || (elearningQuiz.strings.no_answer || 'No answer provided');
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
            html += '<div class="question-result-display">';
            html += '<div class="question-number">' + (elearningQuiz.strings.question || 'Question') + ' ' + (index + 1) + '</div>';
            html += '<div class="question-text">' + result.question + '</div>';
            
            html += '<div class="answer-display">';
            
            if (result.question_type === 'fill_blanks') {
                html += displayFillBlanksAnswer(result, true);
            } else if (result.question_type === 'true_false' && result.tf_statements) {
                // Multiple T/F statements
                html += displayMultipleTFAnswer(result, true);
            } else {
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
    
    function displayMultipleTFAnswer(result, showCorrect, isPerfectScore = false) {
        let html = '<div class="tf-statements-result">';
        
        if (result.tf_statements && Array.isArray(result.tf_statements)) {
            html += '<ol>';
            
            result.tf_statements.forEach((statement, index) => {
                const userAnswer = result.user_answer_raw && result.user_answer_raw[index];
                const correctAnswer = statement.answer;
                const isCorrect = userAnswer === correctAnswer;
                
                let className = '';
                if (showCorrect) {
                    className = 'correct-answer-highlight';
                } else if (isPerfectScore || isCorrect) {
                    className = 'correct';
                } else {
                    className = 'incorrect';
                }
                
                html += '<li class="' + className + '">';
                html += '<strong>' + statement.text + '</strong>: ';
                
                if (showCorrect) {
                    html += correctAnswer === 'true' ? 'Σωστό' : 'Λάθος';
                } else {
                    if (!userAnswer) {
                        html += '<em>Χωρίς απάντηση</em>';
                    } else {
                        html += userAnswer === 'true' ? 'Σωστό' : 'Λάθος';
                    }
                    
                    if (!isCorrect && !isPerfectScore) {
                        html += ' (Σωστή: ' + (correctAnswer === 'true' ? 'Σωστό' : 'Λάθος') + ')';
                    }
                }
                
                html += '</li>';
            });
            
            html += '</ol>';
        }
        
        html += '</div>';
        
        // Add styles for T/F results
        html += '<style>';
        html += '.tf-statements-result ol { margin: 10px 0; padding-left: 20px; }';
        html += '.tf-statements-result li { margin: 8px 0; padding: 8px; border-radius: 4px; }';
        html += '.tf-statements-result li.correct { background: #d1fae5; color: #065f46; }';
        html += '.tf-statements-result li.incorrect { background: #fee2e2; color: #991b1b; }';
        html += '.tf-statements-result li.correct-answer-highlight { background: #dbeafe; color: #1e40af; }';
        html += '</style>';
        
        return html;
    }
    
    function displayFillBlanksAnswer(result, showCorrect, isPerfectScore = false) {
        let html = '';
        
        if (result.question_type === 'fill_blanks' && result.text_with_blanks) {
            const userAnswers = Array.isArray(result.user_answer_raw) ? result.user_answer_raw : [];
            const correctAnswers = Array.isArray(result.correct_answer_raw) ? result.correct_answer_raw : [];
            
            let $textContainer = $('<div>').html(result.text_with_blanks);
            let blankIndex = 0;
            $textContainer.find('*').addBack().contents().each(function() {
                if (this.nodeType === 3) {
                    let text = this.nodeValue;
                    if (text.includes('{{blank}}')) {
                        let newHtml = text.replace(/\{\{blank\}\}/g, function(match) {
                            let replacement = '';
                            
                            if (showCorrect) {
                                const correctAnswer = correctAnswers[blankIndex] || '';
                                replacement = '<span class="blank-filled correct-answer-highlight">' + correctAnswer + '</span>';
                            } else {
                                const userAnswer = userAnswers[blankIndex] || '';
                                const isBlankCorrect = userAnswer === correctAnswers[blankIndex];
                                
                                if (userAnswer === '') {
                                    replacement = '<span class="blank-filled unanswered">___</span>';
                                } else if (isPerfectScore || isBlankCorrect) {
                                    replacement = '<span class="blank-filled correct">' + userAnswer + '</span>';
                                } else {
                                    replacement = '<span class="blank-filled incorrect">' + userAnswer + '</span>';
                                }
                            }
                            
                            blankIndex++;
                            return replacement;
                        });
                        
                        $(this).replaceWith(newHtml);
                    }
                }
            });
            
            html = '<div class="fill-blanks-result">' + $textContainer.html() + '</div>';
        } else {
            const answers = showCorrect ? result.correct_answer_raw : result.user_answer_raw;
            if (Array.isArray(answers)) {
                html = '<div class="fill-blanks-result">';
                html += '<ol>';
                answers.forEach((answer, index) => {
                    const isCorrect = !showCorrect && result.user_answer_raw[index] === result.correct_answer_raw[index];
                    const className = showCorrect ? 'correct-answer-highlight' : (isPerfectScore || isCorrect ? 'correct' : 'incorrect');
                    html += '<li class="' + className + '">' + (answer || '___') + '</li>';
                });
                html += '</ol>';
                html += '</div>';
            } else {
                html = '<div class="answer-value ' + (showCorrect ? 'correct-answer-highlight' : (result.correct ? 'correct' : 'incorrect')) + '">';
                html += showCorrect ? result.correct_answer : result.user_answer;
                html += '</div>';
            }
        }
        
        return html;
    }
    
    function autoSaveProgress() {
        if (!currentQuiz.attemptId) return;
        
        saveCurrentAnswer();
        
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
                // Silent save
            },
            error: function() {
                // Silent error
            }
        });
    }
    
    function showError(message) {
        const $errorDiv = $('<div class="quiz-error" role="alert" style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 15px; border-radius: 6px; margin: 20px 0;">' + message + '</div>');
        
        $('.elearning-quiz-container').prepend($errorDiv);
        
        setTimeout(() => {
            $errorDiv.fadeOut(() => $errorDiv.remove());
        }, 5000);
        
        $errorDiv.attr('tabindex', '-1').focus();
    }
    
    function announceToScreenReader(message) {
        const $announcement = $('<div>', {
            'class': 'sr-only',
            'aria-live': 'polite',
            'aria-atomic': 'true',
            'text': message
        });
        
        $('body').append($announcement);
        
        setTimeout(() => {
            $announcement.remove();
        }, 1000);
    }
    
    function handleVisibilityChange() {
        // Pause/resume timers if needed
    }
    
    function handlePageUnload(e) {
        if (currentQuiz.attemptId && Object.keys(currentQuiz.answers).length > 0) {
            autoSaveProgress();
            
            const message = elearningQuiz.strings.leave_warning || 'You have unsaved progress. Are you sure you want to leave?';
            e.returnValue = message;
            return message;
        }
    }
    
    function initializeAccessibility() {
        if ($('.elearning-quiz-form').length && $('.elearning-quiz-form').is(':visible')) {
            const $skipLink = $('<a href="#quiz-content" class="skip-link">' + 
                (elearningQuiz.strings.skip_to_quiz || 'Skip to quiz content') + '</a>');
            $('.elearning-quiz-container').prepend($skipLink);
        }
        
        $('.elearning-quiz-form').attr('role', 'main').attr('id', 'quiz-content');
        $('.quiz-progress').attr('role', 'progressbar')
                          .attr('aria-label', 'Quiz Progress')
                          .attr('aria-valuemin', 1)
                          .attr('aria-valuemax', currentQuiz.totalQuestions || 1);
        
        if (!$('#quiz-announcements').length) {
            $('body').append('<div id="quiz-announcements" class="sr-only" aria-live="polite" aria-atomic="true"></div>');
        }
    }
    
    $('.elearning-quiz-container').on('contextmenu', function(e) {
        if ($(e.target).closest('.quiz-question').length > 0) {
            e.preventDefault();
            return false;
        }
    });
    
    $('.word-item, .blank-space, .draggable-item').css({
        '-webkit-user-select': 'none',
        '-moz-user-select': 'none',
        '-ms-user-select': 'none',
        'user-select': 'none'
    });
});