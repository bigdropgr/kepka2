/**
 * E-Learning Quiz System - Shortcodes JavaScript
 * Handles loan calculator and other shortcode functionality
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
        calculate: elearningShortcodes.strings?.calculate || (isGreek ? 'Υπολογισμός' : 'Calculate'),
        monthly_payment: elearningShortcodes.strings?.monthly_payment || (isGreek ? 'Μηνιαία Δόση' : 'Monthly Payment'),
        total_payment: elearningShortcodes.strings?.total_payment || (isGreek ? 'Συνολική Πληρωμή' : 'Total Payment'),
        total_interest: elearningShortcodes.strings?.total_interest || (isGreek ? 'Συνολικός Τόκος' : 'Total Interest'),
        error_invalid_input: elearningShortcodes.strings?.error_invalid_input || (isGreek ? 'Παρακαλώ εισάγετε έγκυρους αριθμούς' : 'Please enter valid numbers'),
        error_negative_values: elearningShortcodes.strings?.error_negative_values || (isGreek ? 'Οι τιμές δεν μπορούν να είναι αρνητικές' : 'Values cannot be negative'),
        loading: elearningShortcodes.strings?.loading || (isGreek ? 'Φόρτωση...' : 'Loading...'),
        show_breakdown: elearningShortcodes.strings?.show_breakdown || (isGreek ? 'Εμφάνιση Ανάλυσης Πληρωμής' : 'Show Payment Breakdown'),
        hide_breakdown: elearningShortcodes.strings?.hide_breakdown || (isGreek ? 'Απόκρυψη Ανάλυσης Πληρωμής' : 'Hide Payment Breakdown'),
        principal: elearningShortcodes.strings?.principal || (isGreek ? 'Κεφάλαιο' : 'Principal'),
        interest: elearningShortcodes.strings?.interest || (isGreek ? 'Τόκος' : 'Interest'),
        error_occurred: elearningShortcodes.strings?.error_occurred || (isGreek ? 'Παρουσιάστηκε σφάλμα κατά τον υπολογισμό' : 'An error occurred during calculation'),
        lesson_button_clicked: elearningShortcodes.strings?.lesson_button_clicked || (isGreek ? 'Κλικ στο κουμπί μαθήματος:' : 'Lesson button clicked:'),
        quiz_button_clicked: elearningShortcodes.strings?.quiz_button_clicked || (isGreek ? 'Κλικ στο κουμπί κουίζ:' : 'Quiz button clicked:'),
        progress_data_received: elearningShortcodes.strings?.progress_data_received || (isGreek ? 'Δεδομένα προόδου ελήφθησαν:' : 'Progress data received:'),
        calculation_results: elearningShortcodes.strings?.calculation_results || (isGreek ? 'Αποτελέσματα Υπολογισμού' : 'Loan Calculator Results'),
        loan_amount: elearningShortcodes.strings?.loan_amount || (isGreek ? 'Ποσό Δανείου' : 'Loan Amount'),
        interest_rate: elearningShortcodes.strings?.interest_rate || (isGreek ? 'Επιτόκιο' : 'Interest Rate'),
        loan_term: elearningShortcodes.strings?.loan_term || (isGreek ? 'Διάρκεια Δανείου' : 'Loan Term'),
        years: elearningShortcodes.strings?.years || (isGreek ? 'έτη' : 'years'),
        calculated_at: elearningShortcodes.strings?.calculated_at || (isGreek ? 'Υπολογίστηκε στις' : 'Calculated at')
    };
    
    // Initialize all loan calculators on the page
    $('.loan-calculator-container').each(function() {
        initializeLoanCalculator($(this));
    });
    
    // Initialize lesson/quiz progress tracking
    initializeProgressTracking();
    
    /**
     * Initialize a loan calculator instance
     */
    function initializeLoanCalculator($container) {
        const calculatorId = $container.attr('id');
        
        // Handle Enter key in input fields
        $container.find('.loan-input').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $container.find('.calculate-btn').click();
            }
        });
        
        // Auto-calculate on input change (optional enhancement)
        let calcTimeout;
        $container.find('.loan-input').on('input', function() {
            clearTimeout(calcTimeout);
            // Only auto-calculate if all fields have values
            const hasAllValues = $container.find('.loan-input').filter(function() {
                return $(this).val() === '';
            }).length === 0;
            
            if (hasAllValues) {
                calcTimeout = setTimeout(function() {
                    $container.find('.calculate-btn').click();
                }, 500);
            }
        });
        
        // Format number inputs
        $container.find('.loan-input').on('blur', function() {
            const value = parseFloat($(this).val());
            if (!isNaN(value)) {
                if ($(this).attr('name') === 'interest_rate') {
                    $(this).val(value.toFixed(2));
                } else if ($(this).attr('name') === 'loan_amount') {
                    $(this).val(Math.round(value));
                }
            }
        });
    }
    
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
     * Global function to calculate loan (called from inline onclick)
     */
    window.calculateLoan = function(calculatorId) {
        const $container = $('#' + calculatorId);
        const $form = $container.find('.loan-form');
        const $resultsDiv = $container.find('.loan-results');
        const $errorDiv = $container.find('.error-message');
        const $button = $container.find('.calculate-btn');
        
        // Clear previous errors
        $errorDiv.hide();
        
        // Validate and calculate
        const loanAmount = parseFloat($form.find('[name="loan_amount"]').val());
        const interestRate = parseFloat($form.find('[name="interest_rate"]').val());
        const loanTerm = parseFloat($form.find('[name="loan_term"]').val());
        
        if (isNaN(loanAmount) || isNaN(interestRate) || isNaN(loanTerm)) {
            showError($errorDiv, strings.error_invalid_input);
            return;
        }
        
        if (loanAmount <= 0 || interestRate < 0 || loanTerm <= 0) {
            showError($errorDiv, strings.error_negative_values);
            return;
        }
        
        // Show loading state
        $button.find('.btn-text').hide();
        $button.find('.btn-loading').show();
        $button.prop('disabled', true);
        
        // Calculate after a brief delay for UX
        setTimeout(function() {
            try {
                // Perform calculation
                const monthlyRate = interestRate / 100 / 12;
                const numPayments = loanTerm * 12;
                
                let monthlyPayment;
                if (monthlyRate === 0) {
                    // Handle 0% interest rate
                    monthlyPayment = loanAmount / numPayments;
                } else {
                    monthlyPayment = (loanAmount * monthlyRate * Math.pow(1 + monthlyRate, numPayments)) / 
                                   (Math.pow(1 + monthlyRate, numPayments) - 1);
                }
                
                const totalPayment = monthlyPayment * numPayments;
                const totalInterest = totalPayment - loanAmount;
                
                // Display results
                displayResults($container, {
                    monthlyPayment: monthlyPayment,
                    totalPayment: totalPayment,
                    totalInterest: totalInterest,
                    interestRate: interestRate,
                    loanAmount: loanAmount,
                    currency: $form.data('currency') || '€'
                });
                
                // Show results with animation
                $resultsDiv.slideDown();
                
                // Scroll to results
                $('html, body').animate({
                    scrollTop: $resultsDiv.offset().top - 100
                }, 500);
                
            } catch (error) {
                console.error('Calculation error:', error);
                showError($errorDiv, strings.error_occurred);
            }
            
            // Reset button state
            $button.find('.btn-text').show();
            $button.find('.btn-loading').hide();
            $button.prop('disabled', false);
            
        }, 300);
    };
    
    /**
     * Display calculation results
     */
    function displayResults($container, results) {
        const formatCurrency = function(amount, currency) {
            // Use Greek number formatting for Greek language
            const locale = isGreek ? 'el-GR' : 'en-US';
            return currency + ' ' + new Intl.NumberFormat(locale, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        };
        
        $container.find('.monthly-payment').text(formatCurrency(results.monthlyPayment, results.currency));
        $container.find('.total-payment').text(formatCurrency(results.totalPayment, results.currency));
        $container.find('.total-interest').text(formatCurrency(results.totalInterest, results.currency));
        $container.find('.interest-display').text(results.interestRate.toFixed(2) + '%');
        
        // Update breakdown chart
        const principalPercentage = (results.loanAmount / results.totalPayment) * 100;
        const interestPercentage = (results.totalInterest / results.totalPayment) * 100;
        
        $container.find('.principal-bar').css('width', principalPercentage + '%');
        $container.find('.interest-bar').css('width', interestPercentage + '%');
        
        // Update chart with animation
        $container.find('.principal-bar, .interest-bar').css('transition', 'width 0.5s ease');
    }
    
    /**
     * Show error message
     */
    function showError($errorDiv, message) {
        $errorDiv.find('.error-text').text(message);
        $errorDiv.slideDown();
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $errorDiv.slideUp();
        }, 5000);
    }
    
    /**
     * Toggle breakdown visibility (global function)
     */
    window.toggleBreakdown = function(calculatorId) {
        const $container = $('#' + calculatorId);
        const $breakdown = $container.find('.payment-breakdown');
        const $button = $container.find('.toggle-breakdown');
        
        if ($breakdown.is(':visible')) {
            $breakdown.slideUp();
            $button.text(strings.show_breakdown);
        } else {
            $breakdown.slideDown();
            $button.text(strings.hide_breakdown);
        }
    };
    
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
    
    /**
     * Print calculator results
     */
    $(document).on('click', '.print-results', function(e) {
        e.preventDefault();
        window.print();
    });
    
    /**
     * Export calculator results
     */
    $(document).on('click', '.export-results', function(e) {
        e.preventDefault();
        const $container = $(this).closest('.loan-calculator-container');
        const results = gatherResultsData($container);
        downloadResults(results);
    });
    
    /**
     * Gather results data for export
     */
    function gatherResultsData($container) {
        return {
            loanAmount: $container.find('[name="loan_amount"]').val(),
            interestRate: $container.find('[name="interest_rate"]').val(),
            loanTerm: $container.find('[name="loan_term"]').val(),
            monthlyPayment: $container.find('.monthly-payment').text(),
            totalPayment: $container.find('.total-payment').text(),
            totalInterest: $container.find('.total-interest').text(),
            calculatedAt: new Date().toLocaleString(isGreek ? 'el-GR' : 'en-US')
        };
    }
    
    /**
     * Download results as text file
     */
    function downloadResults(results) {
        const header = isGreek ? 'Αποτελέσματα Υπολογισμού Δανείου' : 'Loan Calculator Results';
        const content = `${header}
======================
${strings.loan_amount}: ${results.loanAmount}
${strings.interest_rate}: ${results.interestRate}%
${strings.loan_term}: ${results.loanTerm} ${strings.years}
${strings.monthly_payment}: ${results.monthlyPayment}
${strings.total_payment}: ${results.totalPayment}
${strings.total_interest}: ${results.totalInterest}
${strings.calculated_at}: ${results.calculatedAt}`;
        
        const blob = new Blob([content], { type: 'text/plain; charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = (isGreek ? 'υπολογισμός-δανείου-' : 'loan-calculation-') + Date.now() + '.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    
    console.log('Shortcodes JavaScript initialized - Greek Version');
});