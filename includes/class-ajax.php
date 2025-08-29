<?php
/**
 * AJAX Class - FIXED VERSION 2.0 - NO PENALTY FOR WRONG ANSWERS
 * 
 * Handles all AJAX requests for the e-learning system
 * Greek Translation Ready Version
 */

if (!defined('ABSPATH')) {
    exit;
}

class ELearning_Ajax {
    
    public function __construct() {
        // Quiz-related AJAX handlers
        add_action('wp_ajax_elearning_start_quiz', [$this, 'startQuiz']);
        add_action('wp_ajax_nopriv_elearning_start_quiz', [$this, 'startQuiz']);
        
        add_action('wp_ajax_elearning_submit_quiz', [$this, 'submitQuiz']);
        add_action('wp_ajax_nopriv_elearning_submit_quiz', [$this, 'submitQuiz']);
        
        add_action('wp_ajax_elearning_save_progress', [$this, 'saveProgress']);
        add_action('wp_ajax_nopriv_elearning_save_progress', [$this, 'saveProgress']);
        
        // Admin-related AJAX handlers
        add_action('wp_ajax_elearning_init_editor', [$this, 'initializeEditor']);
        
        // Lesson progress AJAX handlers
        add_action('wp_ajax_elearning_update_lesson_progress', [$this, 'updateLessonProgress']);
        add_action('wp_ajax_nopriv_elearning_update_lesson_progress', [$this, 'updateLessonProgress']);
        
        // NEW: Get section content for dynamic loading
        add_action('wp_ajax_elearning_get_section_content', [$this, 'getSectionContent']);
        add_action('wp_ajax_nopriv_elearning_get_section_content', [$this, 'getSectionContent']);
        
        // New: Get quiz time limit
        add_action('wp_ajax_elearning_get_quiz_settings', [$this, 'getQuizSettings']);
        add_action('wp_ajax_nopriv_elearning_get_quiz_settings', [$this, 'getQuizSettings']);
    }
    
    /**
     * Start a new quiz attempt with rate limiting
     */
    public function startQuiz(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error(__('Αποτυχία ελέγχου ασφαλείας', 'elearning-quiz'));
        }
        
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        
        if (!$quiz_id) {
            wp_send_json_error(__('Μη έγκυρο ID κουίζ', 'elearning-quiz'));
        }
        
        // Check if quiz exists and is published
        $quiz = get_post($quiz_id);
        if (!$quiz || $quiz->post_status !== 'publish' || $quiz->post_type !== 'elearning_quiz') {
            wp_send_json_error(__('Το κουίζ δεν βρέθηκε ή δεν είναι διαθέσιμο', 'elearning-quiz'));
        }
        
        // Rate limiting check
        $user_session = ELearning_Database::getOrCreateUserSession();
        $attempts_key = 'quiz_attempts_' . $user_session;
        $attempts_in_last_minute = get_transient($attempts_key) ?: 0;
        
        if ($attempts_in_last_minute > 5) {
            wp_send_json_error(__('Πολλές προσπάθειες. Παρακαλώ περιμένετε ένα λεπτό πριν δοκιμάσετε ξανά.', 'elearning-quiz'));
        }
        
        // Get quiz questions
        $questions = get_post_meta($quiz_id, '_quiz_questions', true) ?: [];
        if (empty($questions)) {
            wp_send_json_error(__('Αυτό το κουίζ δεν έχει ερωτήσεις', 'elearning-quiz'));
        }
        
        // Validate questions before starting
        $valid_questions = [];
        foreach ($questions as $index => $question) {
            if (!empty($question['question']) && !empty($question['type'])) {
                $valid_questions[] = $index;
            }
        }
        
        if (empty($valid_questions)) {
            wp_send_json_error(__('Δεν βρέθηκαν έγκυρες ερωτήσεις σε αυτό το κουίζ', 'elearning-quiz'));
        }
        
        // Start quiz attempt
        $attempt_id = ELearning_Database::startQuizAttempt($quiz_id, $valid_questions);
        
        if (!$attempt_id) {
            wp_send_json_error(__('Αποτυχία έναρξης προσπάθειας κουίζ. Παρακαλώ δοκιμάστε ξανά.', 'elearning-quiz'));
        }
        
        // Get global settings instead of quiz-specific
        $global_settings = get_option('elearning_quiz_settings', []);
        $min_questions = $global_settings['questions_per_quiz'] ?? count($valid_questions);
        $total_questions = min($min_questions, count($valid_questions));
        $time_limit = $global_settings['default_time_limit'] ?? 0;
        
        wp_send_json_success([
            'attempt_id' => $attempt_id,
            'total_questions' => $total_questions,
            'time_limit' => intval($time_limit),
            'message' => __('Το κουίζ ξεκίνησε επιτυχώς', 'elearning-quiz')
        ]);
    }
    
    /**
     * Get section content for dynamic loading - NEW METHOD
     */
    public function getSectionContent(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error(__('Αποτυχία ελέγχου ασφαλείας', 'elearning-quiz'));
        }
        
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $section_index = intval($_POST['section_index'] ?? 0);
        
        if (!$lesson_id) {
            wp_send_json_error(__('Μη έγκυρο ID μαθήματος', 'elearning-quiz'));
        }
        
        // Get lesson sections
        $sections = get_post_meta($lesson_id, '_lesson_sections', true) ?: [];
        
        if (!isset($sections[$section_index])) {
            wp_send_json_error(__('Η ενότητα δεν βρέθηκε', 'elearning-quiz'));
        }
        
        $section = $sections[$section_index];
        
        wp_send_json_success([
            'content' => wp_kses_post($section['content']),
            'title' => esc_html($section['title'])
        ]);
    }
    
    /**
     * Submit quiz and calculate results with improved validation
     */
    public function submitQuiz(): void {
        // Add error logging for debugging
        error_log('=== SUBMIT QUIZ CALLED ===');
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            error_log('Nonce verification failed');
            wp_send_json_error(__('Αποτυχία ελέγχου ασφαλείας', 'elearning-quiz'));
            return;
        }
        
        $attempt_id = sanitize_text_field($_POST['attempt_id'] ?? '');
        $answers_json = wp_unslash($_POST['answers'] ?? '{}');
        $question_timings_json = wp_unslash($_POST['question_timings'] ?? '{}');
        
        error_log('Attempt ID: ' . $attempt_id);
        error_log('Raw answers JSON: ' . $answers_json);
        
        if (!$attempt_id) {
            error_log('Invalid attempt ID');
            wp_send_json_error(__('Μη έγκυρο ID προσπάθειας', 'elearning-quiz'));
            return;
        }
        
        // Get attempt details
        global $wpdb;
        $attempt = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}elearning_quiz_attempts WHERE attempt_id = %s",
            $attempt_id
        ), ARRAY_A);
        
        if (!$attempt) {
            error_log('Quiz attempt not found');
            wp_send_json_error(__('Η προσπάθεια κουίζ δεν βρέθηκε', 'elearning-quiz'));
            return;
        }
        
        if ($attempt['status'] !== 'started') {
            error_log('Quiz already submitted');
            wp_send_json_error(__('Αυτό το κουίζ έχει ήδη υποβληθεί', 'elearning-quiz'));
            return;
        }
        
        // Get global settings instead of per-quiz settings
        $global_settings = get_option('elearning_quiz_settings', []);
        $time_limit = $global_settings['default_time_limit'] ?? 0;
        
        // Check for timeout
        if ($time_limit > 0) {
            $elapsed_time = time() - strtotime($attempt['start_time']);
            if ($elapsed_time > ($time_limit * 60)) {
                error_log('Quiz time limit exceeded');
                wp_send_json_error(__('Υπέρβαση χρονικού ορίου κουίζ', 'elearning-quiz'));
                return;
            }
        }
        
        // Get quiz data
        $quiz_id = $attempt['quiz_id'];
        $quiz = get_post($quiz_id);
        $questions = get_post_meta($quiz_id, '_quiz_questions', true) ?: [];
        
        // Use global settings
        $passing_score = $global_settings['default_passing_score'] ?? 70;
        $show_results = !empty($global_settings['show_correct_answers']) ? 'yes' : 'no';
        
        // Parse answers
        $user_answers = json_decode($answers_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            $user_answers = [];
        }
        
        $question_timings = json_decode($question_timings_json, true) ?: [];
        
        error_log('Parsed user answers: ' . print_r($user_answers, true));
        
        // Calculate results - IMPORTANT: Pass ALL questions, not filtered
        $results = $this->calculateQuizResultsImproved($questions, $user_answers, $passing_score);
        
        error_log('Quiz results: ' . print_r($results, true));
        
        // Save detailed answers for each question
        foreach ($questions as $question_index => $question) {
            // Skip invalid questions
            if (empty($question['type']) || empty($question['question'])) {
                continue;
            }
            
            // Get user's answer for this specific question index
            $user_answer = isset($user_answers[$question_index]) ? $user_answers[$question_index] : null;
            $correct_answer = $this->getCorrectAnswer($question);
            
            // Determine if answer is correct
            if ($user_answer === null) {
                $is_correct = false;
                $user_answer_for_db = ''; // Empty for database
            } else {
                $is_correct = $this->isAnswerCorrect($question, $user_answer, $correct_answer);
                $user_answer_for_db = $user_answer;
            }
            
            $time_spent = isset($question_timings[$question_index]) ? $question_timings[$question_index] : null;
            
            error_log("Saving answer for question $question_index - Type: {$question['type']}, Correct: " . ($is_correct ? 'Yes' : 'No'));
            
            // Save the answer
            ELearning_Database::saveQuizAnswer(
                $attempt_id,
                $question_index,
                $question['type'],
                $question['question'],
                $user_answer_for_db,
                $correct_answer,
                $is_correct,
                $time_spent
            );
        }
        
        // Complete the attempt
        $completed = ELearning_Database::completeQuizAttempt(
            $attempt_id,
            $results['score'],
            $results['total_questions'],
            $results['correct_answers'],
            $passing_score
        );
        
        if (!$completed) {
            error_log('Failed to save quiz results');
            wp_send_json_error(__('Αποτυχία αποθήκευσης αποτελεσμάτων κουίζ', 'elearning-quiz'));
            return;
        }
        
        // Prepare response data
        $response_data = [
            'score' => $results['score'],
            'correct_answers' => $results['correct_answers'],
            'total_questions' => $results['total_questions'],
            'total_points' => $results['total_points'],
            'earned_points' => $results['earned_points'],
            'passed' => $results['passed'],
            'passing_score' => $passing_score,
            'show_answers' => $show_results === 'yes',
            'time_taken' => time() - strtotime($attempt['start_time'])
        ];
        
        // Add detailed results if showing answers
        if ($show_results === 'yes') {
            $response_data['detailed_results'] = $this->getDetailedResults($questions, $user_answers);
        }
        
        error_log('Sending success response with score: ' . $results['score']);
        wp_send_json_success($response_data);
    }
    
    /**
     * Save quiz progress (auto-save)
     */
    public function saveProgress(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error(__('Αποτυχία ελέγχου ασφαλείας', 'elearning-quiz'));
        }
        
        $attempt_id = sanitize_text_field($_POST['attempt_id'] ?? '');
        $current_question = intval($_POST['current_question'] ?? 0);
        $answers_json = wp_unslash($_POST['answers'] ?? '');
        
        if (!$attempt_id) {
            wp_send_json_error(__('Μη έγκυρο ID προσπάθειας', 'elearning-quiz'));
        }
        
        // Store progress in transient (temporary storage)
        $progress_key = 'quiz_progress_' . $attempt_id;
        set_transient($progress_key, [
            'current_question' => $current_question,
            'answers' => json_decode($answers_json, true),
            'saved_at' => current_time('mysql')
        ], HOUR_IN_SECONDS);
        
        wp_send_json_success(['message' => __('Η πρόοδος αποθηκεύτηκε', 'elearning-quiz')]);
    }
    
    /**
     * Get quiz settings including time limit
     */
    public function getQuizSettings(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error(__('Αποτυχία ελέγχου ασφαλείας', 'elearning-quiz'));
        }
        
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        
        if (!$quiz_id) {
            wp_send_json_error(__('Μη έγκυρο ID κουίζ', 'elearning-quiz'));
        }
        
        // Return global settings
        $global_settings = get_option('elearning_quiz_settings', []);
        
        $settings = [
            'time_limit' => $global_settings['default_time_limit'] ?? 0,
            'passing_score' => $global_settings['default_passing_score'] ?? 70,
            'show_results' => !empty($global_settings['show_correct_answers']) ? 'yes' : 'no',
            'min_questions' => $global_settings['questions_per_quiz'] ?? 0
        ];
        
        wp_send_json_success($settings);
    }
    
    /**
     * Calculate quiz results with improved scoring for multi-part questions
     */
    private function calculateQuizResultsImproved($questions, $user_answers, $passing_score): array {
        $total_points = 0;
        $earned_points = 0;
        $total_questions = 0;
        $correct_full_questions = 0;
        
        error_log('=== CALCULATING IMPROVED QUIZ RESULTS ===');
        
        // CRITICAL FIX: Loop through ALL questions, not just answered ones
        foreach ($questions as $question_index => $question) {
            // Validate question has required fields
            if (empty($question['type']) || empty($question['question'])) {
                error_log("Question $question_index missing required fields");
                continue;
            }
            
            $total_questions++;
            
            // Get user's answer for this question (might be null if skipped)
            $user_answer = isset($user_answers[$question_index]) ? $user_answers[$question_index] : null;
            
            // Calculate max points for this question regardless of whether it was answered
            $question_max_points = $this->getQuestionMaxPoints($question);
            $total_points += $question_max_points;
            
            // Calculate earned points (0 if question was skipped)
            if ($user_answer !== null) {
                $question_result = $this->calculateQuestionPoints($question, $user_answer);
                $earned_points += $question_result['earned_points'];
                
                // Count as correct if user got full points for the question
                if ($question_result['earned_points'] == $question_result['max_points']) {
                    $correct_full_questions++;
                }
                
                error_log("Question $question_index - Type: {$question['type']}, Points: {$question_result['earned_points']}/{$question_result['max_points']}");
            } else {
                // Question was skipped
                error_log("Question $question_index - Type: {$question['type']}, Points: 0/{$question_max_points} (SKIPPED)");
            }
        }
        
        // Calculate percentage score based on points
        $score = $total_points > 0 ? ($earned_points / $total_points) * 100 : 0;
        $passed = $score >= $passing_score;
        
        error_log("Final score: $score% ($earned_points/$total_points points)");
        error_log("Full correct questions: $correct_full_questions/$total_questions");
        
        return [
            'score' => round($score, 2),
            'correct_answers' => $correct_full_questions,
            'total_questions' => $total_questions,
            'total_points' => $total_points,
            'earned_points' => $earned_points,
            'passed' => $passed
        ];
    }
    

    /**
     * Get maximum points for a question type
     */
    private function getQuestionMaxPoints($question): int {
        switch ($question['type']) {
            case 'multiple_choice':
                $correct_answers = $question['correct_answers'] ?? [];
                // Single or multiple choice
                if (!is_array($correct_answers) || count($correct_answers) <= 1) {
                    return 1;
                }
                return count($correct_answers);
                
            case 'true_false':
                return 1;
                
            case 'fill_blanks':
                $text_with_blanks = $question['text_with_blanks'] ?? '';
                $blank_count = substr_count($text_with_blanks, '{{blank}}');
                return max(1, $blank_count);
                
            case 'matching':
                $matches = $question['matches'] ?? [];
                return max(1, count($matches));
                
            default:
                return 1;
        }
    }

    /**
     * Calculate points for a single question based on type
     */
    private function calculateQuestionPoints($question, $user_answer): array {
        switch ($question['type']) {
            case 'multiple_choice':
                return $this->calculateMultipleChoicePoints($question, $user_answer);
                
            case 'true_false':
                return $this->calculateTrueFalsePoints($question, $user_answer);
                
            case 'fill_blanks':
                return $this->calculateFillBlanksPoints($question, $user_answer);
                
            case 'matching':
                return $this->calculateMatchingPoints($question, $user_answer);
                
            default:
                return ['max_points' => 1, 'earned_points' => 0];
        }
    }
    
    /**
     * Calculate points for multiple choice questions - WITH PENALTIES
     */
    private function calculateMultipleChoicePoints($question, $user_answer): array {
        $correct_answers = $question['correct_answers'] ?? [];
        
        // Single answer multiple choice
        if (!is_array($correct_answers) || count($correct_answers) == 1) {
            $correct = is_array($correct_answers) ? $correct_answers[0] : $correct_answers;
            $is_correct = intval($user_answer) === intval($correct);
            return [
                'max_points' => 1,
                'earned_points' => $is_correct ? 1 : 0
            ];
        }
        
        // Multiple correct answers - partial credit WITH PENALTIES
        if (!is_array($user_answer)) {
            $user_answer = [$user_answer];
        }
        
        $user_answer = array_map('intval', $user_answer);
        $correct_answers = array_map('intval', $correct_answers);
        
        // Count correct selections, incorrect selections, and missed answers
        $correct_selections = array_intersect($user_answer, $correct_answers);
        $incorrect_selections = array_diff($user_answer, $correct_answers);
        $missed_answers = array_diff($correct_answers, $user_answer);
        
        // Calculate points: +1 for each correct, -1 for each incorrect
        $points = count($correct_selections) - count($incorrect_selections);
        $max_points = count($correct_answers);
        
        // Log for debugging
        error_log("Multiple choice scoring:");
        error_log("  Correct selections: " . count($correct_selections) . " (+" . count($correct_selections) . " points)");
        error_log("  Incorrect selections: " . count($incorrect_selections) . " (-" . count($incorrect_selections) . " points)");
        error_log("  Missed answers: " . count($missed_answers) . " (no penalty)");
        error_log("  Total points: " . $points . " / " . $max_points);
        
        // Ensure points don't go below 0 for the question
        $earned_points = max(0, $points);
        
        return [
            'max_points' => $max_points,
            'earned_points' => $earned_points
        ];
    }
    
    /**
     * Calculate points for true/false questions
     */
    private function calculateTrueFalsePoints($question, $user_answer): array {
        $correct_answer = $question['correct_answer'] ?? 'true';
        $is_correct = $user_answer === $correct_answer;
        
        return [
            'max_points' => 1,
            'earned_points' => $is_correct ? 1 : 0
        ];
    }
    
    /**
     * Calculate points for fill in the blanks questions
     */
    private function calculateFillBlanksPoints($question, $user_answer): array {
        if (!is_array($user_answer)) {
            return ['max_points' => 1, 'earned_points' => 0];
        }
        
        // Get expected answers from text and word bank
        $text_with_blanks = $question['text_with_blanks'] ?? '';
        $word_bank = $question['word_bank'] ?? [];
        
        // Count number of blanks
        $blank_count = substr_count($text_with_blanks, '{{blank}}');
        $max_points = $blank_count;
        
        // Count correct answers
        $correct_count = 0;
        for ($i = 0; $i < $blank_count && $i < count($word_bank); $i++) {
            $user_answer_for_blank = trim(strtolower($user_answer[$i] ?? ''));
            $expected_answer = trim(strtolower($word_bank[$i] ?? ''));
            
            if ($user_answer_for_blank === $expected_answer) {
                $correct_count++;
            }
        }
        
        error_log("Fill blanks: $correct_count correct out of $blank_count blanks");
        
        return [
            'max_points' => $max_points,
            'earned_points' => $correct_count
        ];
    }
    
    /**
     * Also update the calculateMatchingPoints method to handle malformed answers
     * Replace the calculateMatchingPoints method (around line 475) with this:
     */
    private function calculateMatchingPoints($question, $user_answer): array {
        // Check if user_answer is actually an array
        if (!is_array($user_answer)) {
            return ['max_points' => 1, 'earned_points' => 0];
        }
        
        $correct_matches = $question['matches'] ?? [];
        $max_points = count($correct_matches);
        $earned_points = 0;
        
        // Debug logging
        error_log("Correct matches for matching question:");
        foreach ($correct_matches as $match) {
            error_log("  Expected: Left {$match['left']} → Right {$match['right']}");
        }
        
        error_log("User answers for matching question:");
        foreach ($user_answer as $left => $right) {
            error_log("  User: Left $left → Right $right");
        }
        
        // Check each correct match
        foreach ($correct_matches as $match) {
            $left_index = strval($match['left'] ?? '');
            $right_index = strval($match['right'] ?? '');
            
            // Check if user's answer for this left item matches the expected right item
            if (isset($user_answer[$left_index])) {
                $user_right = strval($user_answer[$left_index]);
                if ($user_right === $right_index) {
                    $earned_points++;
                    error_log("  ✓ Match correct: Left $left_index → Right $right_index");
                } else {
                    error_log("  ✗ Match incorrect: Left $left_index → Expected Right $right_index, Got Right $user_right");
                }
            } else {
                error_log("  ✗ No answer for Left $left_index");
            }
        }
        
        error_log("Matching: $earned_points correct out of $max_points matches");
        
        return [
            'max_points' => $max_points,
            'earned_points' => $earned_points
        ];
    }
    
    /**
     * Calculate quiz results with improved validation (backward compatibility)
     */
    private function calculateQuizResults($questions, $user_answers, $passing_score): array {
        // Use the improved scoring method
        return $this->calculateQuizResultsImproved($questions, $user_answers, $passing_score);
    }
    
    /**
     * Get correct answer for a question
     */
    private function getCorrectAnswer($question) {
        switch ($question['type']) {
            case 'multiple_choice':
                return $question['correct_answers'] ?? [];
                
            case 'true_false':
                return $question['correct_answer'] ?? 'true';
                
            case 'fill_blanks':
                return $question['word_bank'] ?? [];
                
            case 'matching':
                return $question['matches'] ?? [];
                
            default:
                return null;
        }
    }
    
    /**
     * Check if user answer is correct with improved matching logic
     */
    private function isAnswerCorrect($question, $user_answer, $correct_answer): bool {
        // Use the points calculation to determine if answer is fully correct
        $result = $this->calculateQuestionPoints($question, $user_answer);
        return $result['earned_points'] == $result['max_points'];
    }
    
    /**
     * Extract expected answers from fill-in-the-blanks text
     */
    private function extractExpectedAnswers($text_with_blanks, $word_bank): array {
        // Count the number of blanks in the text
        $blank_count = substr_count($text_with_blanks, '{{blank}}');
        
        // For simple implementation, we'll use the word bank in order
        // In a more advanced version, we could parse the text more intelligently
        $expected_answers = [];
        for ($i = 0; $i < $blank_count && $i < count($word_bank); $i++) {
            $expected_answers[$i] = $word_bank[$i];
        }
        
        return $expected_answers;
    }
    
    /**
     * Get detailed results for review - INCLUDING SKIPPED QUESTIONS
     */
    private function getDetailedResults($questions, $user_answers): array {
        $detailed_results = [];
        
        // Process ALL questions, not just answered ones
        foreach ($questions as $question_index => $question) {
            // Skip invalid questions
            if (empty($question['type']) || empty($question['question'])) {
                continue;
            }
            
            // Get user's answer (might be null for skipped questions)
            $user_answer = isset($user_answers[$question_index]) ? $user_answers[$question_index] : null;
            $was_skipped = ($user_answer === null);
            
            $correct_answer = $this->getCorrectAnswer($question);
            
            // Calculate points for this question
            if (!$was_skipped) {
                $points_result = $this->calculateQuestionPoints($question, $user_answer);
                $is_correct = $points_result['earned_points'] == $points_result['max_points'];
            } else {
                // Skipped question - 0 points earned
                $max_points = $this->getQuestionMaxPoints($question);
                $points_result = [
                    'earned_points' => 0,
                    'max_points' => $max_points
                ];
                $is_correct = false;
            }
            
            // Store raw question data for later display
            $result_data = [
                'question' => wp_strip_all_tags($question['question']),
                'question_type' => $question['type'],
                'question_index' => $question_index,
                'user_answer_raw' => $user_answer,
                'correct_answer_raw' => $correct_answer,
                'correct' => $is_correct,
                'earned_points' => $points_result['earned_points'],
                'max_points' => $points_result['max_points'],
                'partial_credit' => $points_result['earned_points'] > 0 && !$is_correct,
                'skipped' => $was_skipped  // NEW: Mark if question was skipped
            ];
            
            // Add question-specific data for display
            if ($question['type'] === 'fill_blanks') {
                $result_data['text_with_blanks'] = $question['text_with_blanks'] ?? '';
                $result_data['word_bank'] = $question['word_bank'] ?? [];
            } elseif ($question['type'] === 'matching') {
                $result_data['left_column'] = $question['left_column'] ?? [];
                $result_data['right_column'] = $question['right_column'] ?? [];
                $result_data['matches'] = $question['matches'] ?? [];
                
                // Debug logging
                if (!$was_skipped) {
                    error_log("Matching Question - Left Column: " . print_r($question['left_column'], true));
                    error_log("Matching Question - Right Column: " . print_r($question['right_column'], true));
                    error_log("User Answer Raw: " . print_r($user_answer, true));
                    error_log("Correct Answer Raw: " . print_r($correct_answer, true));
                }
            } elseif ($question['type'] === 'multiple_choice') {
                $result_data['options'] = $question['options'] ?? [];
                $result_data['correct_answers'] = $question['correct_answers'] ?? [];
            }
            
            // Format answers for display
            if ($was_skipped) {
                $result_data['user_answer'] = __('Δεν δόθηκε απάντηση', 'elearning-quiz');
            } else {
                $result_data['user_answer'] = $this->formatAnswerForDisplay($user_answer, $question);
            }
            $result_data['correct_answer'] = $this->formatAnswerForDisplay($correct_answer, $question);
            
            // Debug the formatted answers
            if ($question['type'] === 'matching' && !$was_skipped) {
                error_log("Formatted User Answer: " . $result_data['user_answer']);
                error_log("Formatted Correct Answer: " . $result_data['correct_answer']);
            }
            
            $detailed_results[] = $result_data;
        }
        
        return $detailed_results;
    }
    
    /**
     * Format answer for display - FIXED VERSION
     */
    private function formatAnswerForDisplay($answer, $question): string {
        // Handle null/empty answers first
        if ($answer === null || $answer === '') {
            return __('Δεν δόθηκε απάντηση', 'elearning-quiz');
        }
        
        switch ($question['type']) {
            case 'multiple_choice':
                if (is_array($answer)) {
                    $options = [];
                    foreach ($answer as $index) {
                        if (isset($question['options'][$index])) {
                            $options[] = $question['options'][$index];
                        }
                    }
                    return !empty($options) ? implode(', ', $options) : __('Δεν δόθηκε απάντηση', 'elearning-quiz');
                } else {
                    return isset($question['options'][$answer]) ? $question['options'][$answer] : __('Δεν δόθηκε απάντηση', 'elearning-quiz');
                }
                
            case 'true_false':
                // Handle both string and boolean values
                if (is_bool($answer)) {
                    $answer = $answer ? 'true' : 'false';
                }
                if ($answer === 'true') {
                    return __('Σωστό', 'elearning-quiz');
                } elseif ($answer === 'false') {
                    return __('Λάθος', 'elearning-quiz');
                }
                return __('Δεν δόθηκε απάντηση', 'elearning-quiz');
                
            case 'fill_blanks':
                if (is_array($answer)) {
                    $filled_words = array_filter($answer, function($word) {
                        return !empty(trim($word));
                    });
                    if (empty($filled_words)) {
                        return __('Δεν συμπληρώθηκαν κενά', 'elearning-quiz');
                    }
                    return implode(', ', $filled_words);
                }
                return $answer ?: __('Δεν δόθηκε απάντηση', 'elearning-quiz');
                
            case 'matching':
                if (is_array($answer)) {
                    $matches = [];
                    
                    // Check if this is the correct answer format (array of match objects)
                    if (!empty($answer) && isset($answer[0]) && is_array($answer[0]) && isset($answer[0]['left'])) {
                        // This is the correct answer format from the database
                        foreach ($answer as $match) {
                            $left_idx = $match['left'];
                            $right_idx = $match['right'];
                            if (isset($question['left_column'][$left_idx]) && isset($question['right_column'][$right_idx])) {
                                $matches[] = $question['left_column'][$left_idx] . ' → ' . $question['right_column'][$right_idx];
                            }
                        }
                    } else {
                        // This is the user answer format (associative array where key=left, value=right)
                        foreach ($answer as $left_idx => $right_idx) {
                            if (isset($question['left_column'][$left_idx]) && isset($question['right_column'][$right_idx])) {
                                $matches[] = $question['left_column'][$left_idx] . ' → ' . $question['right_column'][$right_idx];
                            }
                        }
                    }
                    
                    if (empty($matches)) {
                        return __('Δεν έγιναν αντιστοιχίσεις', 'elearning-quiz');
                    }
                    
                    sort($matches);
                    return implode('; ', $matches);
                }
                return __('Δεν έγιναν αντιστοιχίσεις', 'elearning-quiz');
                
            default:
                // Handle various types safely
                if (is_array($answer)) {
                    $filtered = array_filter($answer);
                    return !empty($filtered) ? implode(', ', $filtered) : __('Δεν δόθηκε απάντηση', 'elearning-quiz');
                } elseif (is_bool($answer)) {
                    return $answer ? 'true' : 'false';
                } elseif (is_string($answer) || is_numeric($answer)) {
                    return (string)$answer;
                } else {
                    return __('Δεν δόθηκε απάντηση', 'elearning-quiz');
                }
        }
    }
    
    /**
     * Initialize new wp_editor instance via AJAX (from admin)
     */
    public function initializeEditor(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_admin_nonce')) {
            wp_die(__('Αποτυχία ελέγχου ασφαλείας', 'elearning-quiz'));
        }
        
        // Check user capabilities
        if (!current_user_can('edit_elearning_lessons')) {
            wp_die(__('Δεν έχετε άδεια να εκτελέσετε αυτήν την ενέργεια', 'elearning-quiz'));
        }
        
        $editor_id = sanitize_text_field($_POST['editor_id'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        
        if (empty($editor_id)) {
            wp_send_json_error(__('Μη έγκυρο ID επεξεργαστή', 'elearning-quiz'));
        }
        
        // Extract index from editor ID
        $index = str_replace('section_content_', '', $editor_id);
        $editor_name = "lesson_sections[{$index}][content]";
        
        // Start output buffering to capture wp_editor output
        ob_start();
        
        wp_editor($content, $editor_id, [
            'textarea_name' => $editor_name,
            'textarea_rows' => 8,
            'media_buttons' => true,
            'teeny' => false,
            'dfw' => false,
            'tinymce' => [
                'toolbar1' => 'bold,italic,underline,separator,alignleft,aligncenter,alignright,separator,link,unlink,undo,redo',
                'toolbar2' => 'formatselect,forecolor,separator,bullist,numlist,separator,outdent,indent,separator,image,code',
                'resize' => true,
                'wp_autoresize_on' => true,
            ],
            'quicktags' => [
                'buttons' => 'strong,em,ul,ol,li,link,close'
            ]
        ]);
        
        $editor_html = ob_get_clean();
        
        wp_send_json_success([
            'editor_html' => $editor_html,
            'editor_id' => $editor_id
        ]);
    }
    
    /**
     * Update lesson progress with improved tracking
     */
    public function updateLessonProgress(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error(__('Αποτυχία ελέγχου ασφαλείας', 'elearning-quiz'));
        }
        
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $section_index = intval($_POST['section_index'] ?? 0);
        $completed = !empty($_POST['completed']);
        $time_spent = intval($_POST['time_spent'] ?? 0);
        $scroll_percentage = floatval($_POST['scroll_percentage'] ?? 0);
        
        if (!$lesson_id) {
            wp_send_json_error(__('Μη έγκυρο ID μαθήματος', 'elearning-quiz'));
        }
        
        // Validate lesson exists
        $lesson = get_post($lesson_id);
        if (!$lesson || $lesson->post_type !== 'elearning_lesson') {
            wp_send_json_error(__('Το μάθημα δεν βρέθηκε', 'elearning-quiz'));
        }
        
        // Update lesson progress
        $result = ELearning_Database::updateLessonProgress(
            $lesson_id,
            $section_index,
            $completed,
            $time_spent,
            $scroll_percentage
        );
        
        if ($result) {
            // Get updated progress for all sections
            $user_session = ELearning_Database::getOrCreateUserSession();
            $all_progress = ELearning_Database::getLessonProgress($lesson_id, $user_session);
            
            // Calculate overall progress
            $sections = get_post_meta($lesson_id, '_lesson_sections', true) ?: [];
            $total_sections = count($sections);
            $completed_sections = 0;
            
            foreach ($all_progress as $progress) {
                if (!empty($progress['completed'])) {
                    $completed_sections++;
                }
            }
            
            $overall_progress = $total_sections > 0 ? ($completed_sections / $total_sections) * 100 : 0;
            
            wp_send_json_success([
                'message' => __('Η πρόοδος ενημερώθηκε', 'elearning-quiz'),
                'overall_progress' => round($overall_progress, 2),
                'completed_sections' => $completed_sections,
                'total_sections' => $total_sections
            ]);
        } else {
            wp_send_json_error(__('Αποτυχία ενημέρωσης προόδου', 'elearning-quiz'));
        }
    }
}