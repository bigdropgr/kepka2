/**
 * E-Learning Quiz System - Shortcodes JavaScript
 * Handles shortcode functionality
 * Greek language support
 */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('E-Learning Shortcodes JS loaded - Greek Version');
    
    // Get current language
    const currentLang = document.documentElement.lang || 'el';
    const isGreek = currentLang.includes('el');
    
    // Greek translations (fallback if not provided by PHP)
    const strings = {
        loading: elearningShortcodes.strings?.loading || (isGreek ? 'Φόρτωση...' : 'Loading...'),
        error_occurred: elearningShortcodes.strings?.error_occurred || (isGreek ? 'Παρουσιάστηκε σφάλμα' : 'An error occurred'),
        lesson_button_clicked: elearningShortcodes.strings?.lesson_button_clicked || (isGreek ? 'Κλικ στο κουμπί μαθήματος:' : 'Lesson button clicked:'),
        quiz_button_clicked: elearningShortcodes.strings?.quiz_button_clicked || (isGreek ? 'Κλικ στο κουμπί κουίζ:' : 'Quiz button clicked:'),
        progress_data_received: elearningShortcodes.strings?.progress_data_received || (isGreek ? 'Δεδομένα προόδου ελήφθησαν:' : 'Progress data received:')
    };
    
    /**
     * Initialize progress tracking for embedded lessons/quizzes
     */
    function initializeProgressTracking() {
        // Track embedded lesson views
        $('.embedded-lesson').each(function() {
            const lessonId = $(this).data('lesson-id');
            if (lessonId) {
                trackLessonView(lessonId);
            }
        });
        
        // Track embedded quiz views
        $('.embedded-quiz').each(function() {
            const quizId = $(this).data('quiz-id');
            if (quizId) {
                trackQuizView(quizId);
            }
        });
    }
    
    /**
     * Track lesson view (for analytics)
     */
    function trackLessonView(lessonId) {
        // This could be expanded to send analytics data
        console.log(isGreek ? 'Παρακολούθηση προβολής μαθήματος:' : 'Tracking lesson view:', lessonId);
    }
    
    /**
     * Track quiz view (for analytics)
     */
    function trackQuizView(quizId) {
        // This could be expanded to send analytics data
        console.log(isGreek ? 'Παρακολούθηση προβολής κουίζ:' : 'Tracking quiz view:', quizId);
    }
    
    /**
     * Handle AJAX-loaded content
     */
    $(document).on('click', '.lesson-btn, .quiz-btn', function(e) {
        const $button = $(this);
        const href = $button.attr('href');
        
        // Add loading state
        $button.addClass('loading');
        
        // Track click event
        if ($button.hasClass('lesson-btn')) {
            console.log(strings.lesson_button_clicked, href);
        } else if ($button.hasClass('quiz-btn')) {
            console.log(strings.quiz_button_clicked, href);
        }
        
        // Remove loading state when page loads
        setTimeout(function() {
            $button.removeClass('loading');
        }, 1000);
    });
    
    /**
     * Handle user progress widget updates
     */
    if ($('.user-progress-widget').length > 0) {
        // Refresh progress data periodically
        setInterval(function() {
            refreshUserProgress();
        }, 60000); // Every minute
    }
    
    /**
     * Refresh user progress data
     */
    function refreshUserProgress() {
        const $widget = $('.user-progress-widget');
        if ($widget.length === 0) return;
        
        $.ajax({
            url: elearningShortcodes.ajaxUrl,
            type: 'POST',
            data: {
                action: 'elearning_get_user_progress',
                nonce: elearningShortcodes.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateProgressDisplay(response.data);
                }
            }
        });
    }
    
    /**
     * Update progress display
     */
    function updateProgressDisplay(data) {
        // This would update the progress widget with fresh data
        console.log(strings.progress_data_received, data);
    }
    
    console.log('Shortcodes JavaScript initialized - Greek Version');
});