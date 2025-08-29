/* E-Learning Quiz System - Admin JavaScript - Greek Ready Version */
/* Fixed: Visual editor initialization for new sections and matching/fill-in-the-blanks saving issues */
/* Greek language support */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('E-Learning Quiz System admin loaded - Greek Ready Version');
    
    // Get current language
    const currentLang = document.documentElement.lang || 'el';
    const isGreek = currentLang.includes('el');
    
    var sectionIndex = $('#lesson-sections-container .lesson-section').length;
    var questionIndex = $('#quiz-questions-container .quiz-question').length;
    
    // Greek translations (fallback if not provided by PHP)
    const strings = {
        confirm_delete: elearningQuizAdmin.strings?.confirm_delete || (isGreek ? 'Είστε σίγουροι ότι θέλετε να το διαγράψετε;' : 'Are you sure you want to delete this?'),
        section: elearningQuizAdmin.strings?.section || (isGreek ? 'Ενότητα' : 'Section'),
        question: elearningQuizAdmin.strings?.question || (isGreek ? 'Ερώτηση' : 'Question'),
        remove: elearningQuizAdmin.strings?.remove || (isGreek ? 'Αφαίρεση' : 'Remove'),
        add_option: elearningQuizAdmin.strings?.add_option || (isGreek ? 'Προσθήκη Επιλογής' : 'Add Option'),
        add_word: elearningQuizAdmin.strings?.add_word || (isGreek ? 'Προσθήκη Λέξης' : 'Add Word'),
        add_left_item: elearningQuizAdmin.strings?.add_left_item || (isGreek ? 'Προσθήκη Αριστερού Στοιχείου' : 'Add Left Item'),
        add_right_item: elearningQuizAdmin.strings?.add_right_item || (isGreek ? 'Προσθήκη Δεξιού Στοιχείου' : 'Add Right Item'),
        add_match: elearningQuizAdmin.strings?.add_match || (isGreek ? 'Προσθήκη Αντιστοίχισης' : 'Add Match'),
        option_text: elearningQuizAdmin.strings?.option_text || (isGreek ? 'Κείμενο επιλογής' : 'Option text'),
        correct: elearningQuizAdmin.strings?.correct || (isGreek ? 'Σωστό' : 'Correct'),
        word: elearningQuizAdmin.strings?.word || (isGreek ? 'Λέξη' : 'Word'),
        left_item: elearningQuizAdmin.strings?.left_item || (isGreek ? 'Αριστερό στοιχείο' : 'Left item'),
        right_item: elearningQuizAdmin.strings?.right_item || (isGreek ? 'Δεξί στοιχείο' : 'Right item'),
        select_left: elearningQuizAdmin.strings?.select_left || (isGreek ? 'Επιλέξτε αριστερό στοιχείο' : 'Select left item'),
        select_right: elearningQuizAdmin.strings?.select_right || (isGreek ? 'Επιλέξτε δεξί στοιχείο' : 'Select right item'),
        matches_with: elearningQuizAdmin.strings?.matches_with || (isGreek ? 'ταιριάζει με' : 'matches with'),
        options: elearningQuizAdmin.strings?.options || (isGreek ? 'Επιλογές' : 'Options'),
        text_with_blanks: elearningQuizAdmin.strings?.text_with_blanks || (isGreek ? 'Κείμενο με Κενά' : 'Text with Blanks'),
        blank_instruction: elearningQuizAdmin.strings?.blank_instruction || (isGreek ? 'Χρησιμοποιήστε {{blank}} για να σημειώσετε που θα εμφανίζονται τα κενά.' : 'Use {{blank}} to mark where blanks should appear.'),
        word_bank: elearningQuizAdmin.strings?.word_bank || (isGreek ? 'Τράπεζα Λέξεων' : 'Word Bank'),
        correct_answer: elearningQuizAdmin.strings?.correct_answer || (isGreek ? 'Σωστή Απάντηση' : 'Correct Answer'),
        true_option: elearningQuizAdmin.strings?.true_option || (isGreek ? 'Σωστό' : 'True'),
        false_option: elearningQuizAdmin.strings?.false_option || (isGreek ? 'Λάθος' : 'False'),
        left_column: elearningQuizAdmin.strings?.left_column || (isGreek ? 'Αριστερή Στήλη' : 'Left Column'),
        right_column: elearningQuizAdmin.strings?.right_column || (isGreek ? 'Δεξιά Στήλη' : 'Right Column'),
        correct_matches: elearningQuizAdmin.strings?.correct_matches || (isGreek ? 'Σωστές Αντιστοιχίσεις' : 'Correct Matches')
    };
    
    // Add Section functionality - FIXED VERSION with Visual Editor
    $('#add-section').on('click', function(e) {
        e.preventDefault();
        
        var container = $('#lesson-sections-container');
        var template = $('#section-template').html();
        
        var newSection = template.replace(/\{\{INDEX\}\}/g, sectionIndex);
        container.append(newSection);
        
        // Initialize TinyMCE for the new section
        var editorId = 'section_content_' + sectionIndex;
        
        // Remove the placeholder textarea and initialize proper editor
        initializeWpEditor(editorId, sectionIndex);
        
        // Update section numbers
        updateSectionNumbers();
        
        sectionIndex++;
    });
    
    // Remove Section functionality
    $(document).on('click', '.remove-section', function(e) {
        e.preventDefault();
        
        if (confirm(strings.confirm_delete)) {
            var sectionDiv = $(this).closest('.lesson-section');
            var editorId = sectionDiv.find('textarea[id^="section_content_"]').attr('id');
            
            // Remove TinyMCE instance if it exists
            if (editorId && typeof tinymce !== 'undefined') {
                var editor = tinymce.get(editorId);
                if (editor) {
                    editor.remove();
                }
            }
            
            sectionDiv.remove();
            updateSectionNumbers();
        }
    });
    
    // Add Question functionality
    $('#add-question').on('click', function(e) {
        console.log('Add question clicked!');
        e.preventDefault();
        
        var container = $('#quiz-questions-container');
        var template = $('#question-template').html();
        
        var newQuestion = template.replace(/\{\{INDEX\}\}/g, questionIndex);
        container.append(newQuestion);
        
        // Update question numbers
        updateQuestionNumbers();
        
        questionIndex++;
    });
    
    // Remove Question functionality
    $(document).on('click', '.remove-question', function(e) {
        e.preventDefault();
        
        if (confirm(strings.confirm_delete)) {
            $(this).closest('.quiz-question').remove();
            updateQuestionNumbers();
        }
    });
    
    // === FIXED: Button container selection logic ===
    
    // Add Left Item functionality - FIXED VERSION 2.0
    $(document).on('click', '.add-left-item', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var leftColumn = button.closest('.left-column');
        var container = leftColumn.find('.match-items-container');
        var questionDiv = button.closest('.quiz-question');
        var questionIdx = questionDiv.data('index');
        
        // FIXED: Count existing items to determine next index
        var leftIndex = container.find('.match-item').length;
        
        var newLeftItem = '<div class="match-item">' +
            '<input type="text" name="quiz_questions[' + questionIdx + '][left_column][' + leftIndex + ']" placeholder="' + strings.left_item + '" class="regular-text" />' +
            '<button type="button" class="remove-left-item button-link-delete">' + strings.remove + '</button>' +
            '</div>';
        
        container.append(newLeftItem);
        updateMatchingSelects(questionDiv);
    });
    
    // Add Right Item functionality - FIXED VERSION 2.0
    $(document).on('click', '.add-right-item', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var rightColumn = button.closest('.right-column');
        var container = rightColumn.find('.match-items-container');
        var questionDiv = button.closest('.quiz-question');
        var questionIdx = questionDiv.data('index');
        
        // FIXED: Count existing items to determine next index
        var rightIndex = container.find('.match-item').length;
        
        var newRightItem = '<div class="match-item">' +
            '<input type="text" name="quiz_questions[' + questionIdx + '][right_column][' + rightIndex + ']" placeholder="' + strings.right_item + '" class="regular-text" />' +
            '<button type="button" class="remove-right-item button-link-delete">' + strings.remove + '</button>' +
            '</div>';
        
        container.append(newRightItem);
        updateMatchingSelects(questionDiv);
    });
    
    // Add Word functionality - FIXED VERSION 2.0
    $(document).on('click', '.add-word', function(e) {
        console.log('Add word clicked!');
        e.preventDefault();
        
        var button = $(this);
        var container = button.prev('.word-bank-container');
        var questionDiv = button.closest('.quiz-question');
        var questionIdx = questionDiv.data('index');
        
        // FIXED: Count existing items to determine next index
        var wordIndex = container.find('.word-row').length;
        
        var newWord = '<div class="word-row">' +
            '<input type="text" name="quiz_questions[' + questionIdx + '][word_bank][' + wordIndex + ']" placeholder="' + strings.word + '" class="regular-text" />' +
            '<button type="button" class="remove-word button-link-delete">' + strings.remove + '</button>' +
            '</div>';
        
        container.append(newWord);
    });
    
    // Add Match functionality - FIXED VERSION 2.0
    $(document).on('click', '.add-match', function(e) {
        console.log('Add match clicked!');
        e.preventDefault();
        
        var button = $(this);
        var container = button.prev('.matches-container');
        var questionDiv = button.closest('.quiz-question');
        var questionIdx = questionDiv.data('index');
        var matchIndex = container.find('.match-row').length;
        
        var leftOptions = '';
        var rightOptions = '';
        
        // FIXED: Get left column items with their actual indices
        questionDiv.find('.left-column .match-item').each(function(index) {
            var input = $(this).find('input[type="text"]');
            var value = input.val() || strings.left_item + ' ' + (index + 1);
            leftOptions += '<option value="' + index + '">' + value + '</option>';
        });
        
        // FIXED: Get right column items with their actual indices
        questionDiv.find('.right-column .match-item').each(function(index) {
            var input = $(this).find('input[type="text"]');
            var value = input.val() || strings.right_item + ' ' + (index + 1);
            rightOptions += '<option value="' + index + '">' + value + '</option>';
        });
        
        var newMatch = '<div class="match-row">' +
            '<select name="quiz_questions[' + questionIdx + '][matches][' + matchIndex + '][left]" class="match-left-select">' +
            '<option value="">' + strings.select_left + '</option>' +
            leftOptions +
            '</select>' +
            '<span>' + strings.matches_with + '</span>' +
            '<select name="quiz_questions[' + questionIdx + '][matches][' + matchIndex + '][right]" class="match-right-select">' +
            '<option value="">' + strings.select_right + '</option>' +
            rightOptions +
            '</select>' +
            '<button type="button" class="remove-match button-link-delete">' + strings.remove + '</button>' +
            '</div>';
        
        container.append(newMatch);
    });
    
    // Add Option functionality - FIXED VERSION 2.0
    $(document).on('click', '.add-option', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var container = button.prev('.options-container');
        var questionDiv = button.closest('.quiz-question');
        var questionIdx = questionDiv.data('index');
        
        // FIXED: Count existing options to determine next index
        var optionIndex = container.find('.option-row').length;
        
        var newOption = '<div class="option-row">' +
            '<input type="text" name="quiz_questions[' + questionIdx + '][options][' + optionIndex + ']" placeholder="' + strings.option_text + '" class="regular-text" />' +
            '<label><input type="checkbox" name="quiz_questions[' + questionIdx + '][correct_answers][]" value="' + optionIndex + '" /> ' + strings.correct + '</label>' +
            '<button type="button" class="remove-option button-link-delete">' + strings.remove + '</button>' +
            '</div>';
        
        container.append(newOption);
    });
    
    // FIXED: Remove handlers with index updates
    $(document).on('click', '.remove-left-item', function(e) {
        e.preventDefault();
        var questionDiv = $(this).closest('.quiz-question');
        $(this).closest('.match-item').remove();
        
        // Re-index remaining items
        reindexMatchingItems(questionDiv, 'left');
        updateMatchingSelects(questionDiv);
    });
    
    $(document).on('click', '.remove-right-item', function(e) {
        e.preventDefault();
        var questionDiv = $(this).closest('.quiz-question');
        $(this).closest('.match-item').remove();
        
        // Re-index remaining items
        reindexMatchingItems(questionDiv, 'right');
        updateMatchingSelects(questionDiv);
    });
    
    $(document).on('click', '.remove-word', function(e) {
        e.preventDefault();
        var container = $(this).closest('.word-bank-container');
        var questionDiv = $(this).closest('.quiz-question');
        var questionIdx = questionDiv.data('index');
        
        $(this).closest('.word-row').remove();
        
        // Re-index remaining words
        container.find('.word-row').each(function(index) {
            $(this).find('input[type="text"]').attr('name', 'quiz_questions[' + questionIdx + '][word_bank][' + index + ']');
        });
    });
    
    $(document).on('click', '.remove-match', function(e) {
        e.preventDefault();
        var container = $(this).closest('.matches-container');
        var questionDiv = $(this).closest('.quiz-question');
        var questionIdx = questionDiv.data('index');
        
        $(this).closest('.match-row').remove();
        
        // Re-index remaining matches
        container.find('.match-row').each(function(index) {
            $(this).find('.match-left-select').attr('name', 'quiz_questions[' + questionIdx + '][matches][' + index + '][left]');
            $(this).find('.match-right-select').attr('name', 'quiz_questions[' + questionIdx + '][matches][' + index + '][right]');
        });
    });
    
    $(document).on('click', '.remove-option', function(e) {
        e.preventDefault();
        var container = $(this).closest('.options-container');
        var questionDiv = $(this).closest('.quiz-question');
        var questionIdx = questionDiv.data('index');
        
        $(this).closest('.option-row').remove();
        
        // Re-index remaining options
        container.find('.option-row').each(function(index) {
            $(this).find('input[type="text"]').attr('name', 'quiz_questions[' + questionIdx + '][options][' + index + ']');
            $(this).find('input[type="checkbox"]').val(index);
        });
    });
    
    // FIXED: Function to re-index matching items
    function reindexMatchingItems(questionDiv, column) {
        var questionIdx = questionDiv.data('index');
        var columnClass = column === 'left' ? '.left-column' : '.right-column';
        
        questionDiv.find(columnClass + ' .match-item').each(function(index) {
            $(this).find('input[type="text"]').attr('name', 'quiz_questions[' + questionIdx + '][' + column + '_column][' + index + ']');
        });
    }
    
    // Update matching selects when items change
    $(document).on('input', '.left-column input[type="text"], .right-column input[type="text"]', function() {
        var questionDiv = $(this).closest('.quiz-question');
        updateMatchingSelects(questionDiv);
    });
    
    // Question type change handler
    $(document).on('change', '.question-type-select', function() {
        var questionContainer = $(this).closest('.quiz-question');
        var optionsContainer = questionContainer.find('.question-options');
        var questionType = $(this).val();
        var questionIdx = questionContainer.data('index');
        
        // Update options container data-type
        optionsContainer.attr('data-type', questionType);
        
        // Load appropriate options template based on question type
        loadQuestionTypeOptions(questionType, questionIdx, optionsContainer);
    });
    
    // FIXED VERSION 4.0: Initialize WordPress editor properly for all sections
    function initializeWpEditor(editorId, index) {
        // Check if wp.editor is available
        if (typeof wp !== 'undefined' && typeof wp.editor !== 'undefined') {
            // Get the textarea element
            var $textarea = $('#' + editorId);
            var content = $textarea.val() || '';
            
            // Remove any existing editor instance
            if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                wp.editor.remove(editorId);
            }
            
            // Wait a moment for DOM to be ready
            setTimeout(function() {
                // Initialize the editor with WordPress settings
                wp.editor.initialize(editorId, {
                    tinymce: {
                        wpautop: true,
                        plugins: 'lists,paste,wordpress,wplink,wptextpattern,wpview,wordpress,image,charmap,hr,media,paste,tabfocus,textcolor,fullscreen,wordpress,wpeditimage,wpgallery,wplink,wpdialogs,wpview',
                        toolbar1: 'formatselect,bold,italic,underline,strikethrough,separator,alignleft,aligncenter,alignright,alignjustify,separator,link,unlink,separator,wp_more,separator,spellchecker,fullscreen,wp_adv',
                        toolbar2: 'bullist,numlist,separator,outdent,indent,separator,undo,redo,separator,forecolor,backcolor,separator,pastetext,pasteword,removeformat,separator,media,charmap,separator,wp_help',
                        toolbar3: '',
                        toolbar4: '',
                        resize: true,
                        wp_autoresize_on: true,
                        setup: function(editor) {
                            editor.on('change', function() {
                                editor.save();
                                // Trigger change event on textarea
                                jQuery('#' + editor.id).trigger('change');
                            });
                        }
                    },
                    quicktags: {
                        buttons: 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close'
                    },
                    mediaButtons: true
                });
                
                // Set the content after initialization
                if (content) {
                    setTimeout(function() {
                        if (tinymce.get(editorId)) {
                            tinymce.get(editorId).setContent(content);
                        }
                    }, 100);
                }
            }, 100);
            
        } else {
            // Fallback to AJAX method if wp.editor is not available
            console.log('WordPress editor API not available, using AJAX fallback');
            
            var textarea = $('#' + editorId);
            var data = {
                action: 'elearning_init_editor',
                editor_id: editorId,
                content: textarea.val(),
                nonce: elearningQuizAdmin.nonce
            };
            
            $.post(elearningQuizAdmin.ajaxUrl, data, function(response) {
                if (response.success) {
                    // Replace the textarea's parent cell with the new editor
                    textarea.closest('td').html(response.data.editor_html);
                }
            });
        }
    }
    
    // Function to update section numbers
    function updateSectionNumbers() {
        $('#lesson-sections-container .lesson-section').each(function(index) {
            $(this).find('.section-header h4').text(strings.section + ' ' + (index + 1));
            $(this).attr('data-index', index);
        });
    }
    
    // Function to update question numbers
    function updateQuestionNumbers() {
        $('#quiz-questions-container .quiz-question').each(function(index) {
            $(this).find('.question-header h4').text(strings.question + ' ' + (index + 1));
            $(this).attr('data-index', index);
        });
    }
    
    // FIXED: Function to update matching selects
    function updateMatchingSelects(questionDiv) {
        var questionIdx = questionDiv.data('index');
        
        // Update all match selects in this question
        questionDiv.find('.matches-container .match-row').each(function(matchIndex) {
            var leftSelect = $(this).find('.match-left-select');
            var rightSelect = $(this).find('.match-right-select');
            
            var leftCurrentValue = leftSelect.val();
            var rightCurrentValue = rightSelect.val();
            
            // Update left select options
            var leftOptions = '<option value="">' + strings.select_left + '</option>';
            questionDiv.find('.left-column .match-item').each(function(index) {
                var input = $(this).find('input[type="text"]');
                var value = input.val() || strings.left_item + ' ' + (index + 1);
                var selected = leftCurrentValue == index ? ' selected' : '';
                leftOptions += '<option value="' + index + '"' + selected + '>' + value + '</option>';
            });
            leftSelect.html(leftOptions);
            
            // Update right select options
            var rightOptions = '<option value="">' + strings.select_right + '</option>';
            questionDiv.find('.right-column .match-item').each(function(index) {
                var input = $(this).find('input[type="text"]');
                var value = input.val() || strings.right_item + ' ' + (index + 1);
                var selected = rightCurrentValue == index ? ' selected' : '';
                rightOptions += '<option value="' + index + '"' + selected + '>' + value + '</option>';
            });
            rightSelect.html(rightOptions);
        });
    }
    
    // Function to load question type options
    function loadQuestionTypeOptions(questionType, questionIdx, container) {
        var html = '';
        
        switch (questionType) {
            case 'multiple_choice':
                html = '<h5>' + strings.options + '</h5>' +
                    '<div class="options-container">' +
                    '<div class="option-row">' +
                    '<input type="text" name="quiz_questions[' + questionIdx + '][options][0]" placeholder="' + strings.option_text + '" class="regular-text" />' +
                    '<label><input type="checkbox" name="quiz_questions[' + questionIdx + '][correct_answers][]" value="0" /> ' + strings.correct + '</label>' +
                    '<button type="button" class="remove-option button-link-delete">' + strings.remove + '</button>' +
                    '</div>' +
                    '</div>' +
                    '<button type="button" class="add-option button">' + strings.add_option + '</button>';
                break;
                
            case 'fill_blanks':
                html = '<h5>' + strings.text_with_blanks + '</h5>' +
                    '<p class="description">' + strings.blank_instruction + '</p>' +
                    '<textarea name="quiz_questions[' + questionIdx + '][text_with_blanks]" rows="4" class="large-text"></textarea>' +
                    '<h5>' + strings.word_bank + '</h5>' +
                    '<div class="word-bank-container">' +
                    '<div class="word-row">' +
                    '<input type="text" name="quiz_questions[' + questionIdx + '][word_bank][0]" placeholder="' + strings.word + '" class="regular-text" />' +
                    '<button type="button" class="remove-word button-link-delete">' + strings.remove + '</button>' +
                    '</div>' +
                    '</div>' +
                    '<button type="button" class="add-word button">' + strings.add_word + '</button>';
                break;
                
            case 'true_false':
                html = '<h5>' + strings.correct_answer + '</h5>' +
                    '<label><input type="radio" name="quiz_questions[' + questionIdx + '][correct_answer]" value="true" checked /> ' + strings.true_option + '</label><br>' +
                    '<label><input type="radio" name="quiz_questions[' + questionIdx + '][correct_answer]" value="false" /> ' + strings.false_option + '</label>';
                break;
                
            case 'matching':
                html = '<div class="matching-columns">' +
                    '<div class="left-column">' +
                    '<h5>' + strings.left_column + '</h5>' +
                    '<div class="match-items-container">' +
                    '<div class="match-item">' +
                    '<input type="text" name="quiz_questions[' + questionIdx + '][left_column][0]" placeholder="' + strings.left_item + '" class="regular-text" />' +
                    '<button type="button" class="remove-left-item button-link-delete">' + strings.remove + '</button>' +
                    '</div>' +
                    '</div>' +
                    '<button type="button" class="add-left-item button">' + strings.add_left_item + '</button>' +
                    '</div>' +
                    '<div class="right-column">' +
                    '<h5>' + strings.right_column + '</h5>' +
                    '<div class="match-items-container">' +
                    '<div class="match-item">' +
                    '<input type="text" name="quiz_questions[' + questionIdx + '][right_column][0]" placeholder="' + strings.right_item + '" class="regular-text" />' +
                    '<button type="button" class="remove-right-item button-link-delete">' + strings.remove + '</button>' +
                    '</div>' +
                    '</div>' +
                    '<button type="button" class="add-right-item button">' + strings.add_right_item + '</button>' +
                    '</div>' +
                    '</div>' +
                    '<h5>' + strings.correct_matches + '</h5>' +
                    '<div class="matches-container"></div>' +
                    '<button type="button" class="add-match button">' + strings.add_match + '</button>';
                break;
        }
        
        container.html(html);
    }
    
    // Form submission handler to sync TinyMCE content
    $('form').on('submit', function() {
        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
    });
    
    // Initialize existing questions on page load
    $('.quiz-question').each(function() {
        var questionDiv = $(this);
        var questionType = questionDiv.find('.question-type-select').val();
        
        if (questionType === 'matching') {
            updateMatchingSelects(questionDiv);
        }
    });
    
    console.log('Admin JavaScript loaded and ready - Greek Version');
});