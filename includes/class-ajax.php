<?php
/**
 * AJAX Class - ENHANCED VERSION 3.0
 * 
 * Handles all AJAX requests for the e-learning system
 * ENHANCED: Added wp_editor initialization for dynamic sections
 * Greek only version
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
        add_action('wp_ajax_elearning_get_wp_editor', [$this, 'getWpEditor']);
        
        // Lesson progress AJAX handlers
        add_action('wp_ajax_elearning_update_lesson_progress', [$this, 'updateLessonProgress']);
        add_action('wp_ajax_nopriv_elearning_update_lesson_progress', [$this, 'updateLessonProgress']);
        
        // Get section content for dynamic loading
        add_action('wp_ajax_elearning_get_section_content', [$this, 'getSectionContent']);
        add_action('wp_ajax_nopriv_elearning_get_section_content', [$this, 'getSectionContent']);
        
        // Get quiz time limit
        add_action('wp_ajax_elearning_get_quiz_settings', [$this, 'getQuizSettings']);
        add_action('wp_ajax_nopriv_elearning_get_quiz_settings', [$this, 'getQuizSettings']);
    }
    
    /**
     * Get WordPress editor HTML via AJAX
     */
    public function getWpEditor(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_admin_nonce')) {
            wp_send_json_error('Ο έλεγχος ασφαλείας απέτυχε');
        }
        
        // Check user capabilities
        if (!current_user_can('edit_elearning_lessons')) {
            wp_send_json_error('Δεν έχετε άδεια να εκτελέσετε αυτή την ενέργεια');
        }
        
        $editor_id = sanitize_text_field($_POST['editor_id'] ?? '');
        $editor_name = sanitize_text_field($_POST['editor_name'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        
        if (empty($editor_id) || empty($editor_name)) {
            wp_send_json_error('Μη έγκυρες παράμετροι επεξεργαστή');
        }
        
        // Start output buffering to capture wp_editor output
        ob_start();
        
        // Generate the editor
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
        
        // Prepare TinyMCE settings for initialization
        $tinymce_settings = [
            'selector' => '#' . $editor_id,
            'toolbar1' => 'bold,italic,underline,separator,alignleft,aligncenter,alignright,separator,link,unlink,undo,redo',
            'toolbar2' => 'formatselect,forecolor,separator,bullist,numlist,separator,outdent,indent,separator,image,code',
            'resize' => true,
            'wp_autoresize_on' => true,
            'content_css' => includes_url('css/dashicons.css') . ',' . includes_url('js/tinymce/skins/wordpress/wp-content.css'),
            'body_class' => 'wp-editor',
            'keep_styles' => false,
            'paste_remove_styles' => true
        ];
        
        // Prepare QuickTags settings
        $quicktags_settings = [
            'id' => $editor_id,
            'buttons' => 'strong,em,ul,ol,li,link,close'
        ];
        
        wp_send_json_success([
            'editor_html' => $editor_html,
            'editor_id' => $editor_id,
            'tinymce_settings' => $tinymce_settings,
            'quicktags_settings' => $quicktags_settings
        ]);
    }
    
    /**
     * Start a new quiz attempt with randomization support
     */
    public function startQuiz(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error('Ο έλεγχος ασφαλείας απέτυχε');
        }
        
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        
        if (!$quiz_id) {
            wp_send_json_error('Μη έγκυρο ID κουίζ');
        }
        
        // Check if quiz exists and is published
        $quiz = get_post($quiz_id);
        if (!$quiz || $quiz->post_status !== 'publish' || $quiz->post_type !== 'elearning_quiz') {
            wp_send_json_error('Το κουίζ δεν βρέθηκε ή δεν είναι διαθέσιμο');
        }
        
        // Rate limiting check
        $user_session = ELearning_Database::getOrCreateUserSession();
        $attempts_key = 'quiz_attempts_' . $user_session;
        $attempts_in_last_minute = get_transient($attempts_key) ?: 0;
        
        if ($attempts_in_last_minute > 5) {
            wp_send_json_error('Πάρα πολλές προσπάθειες. Παρακαλώ περιμένετε ένα λεπτό πριν δοκιμάσετε ξανά.');
        }
        
        // Get quiz questions
        $questions = get_post_meta($quiz_id, '_quiz_questions', true) ?: [];
        if (empty($questions)) {
            wp_send_json_error('Αυτό το κουίζ δεν έχει ερωτήσεις');
        }
        
        // Get randomization settings
        $randomize_questions = get_post_meta($quiz_id, '_randomize_questions', true) ?: 'no';
        $min_questions = get_post_meta($quiz_id, '_min_questions_to_show', true) ?: count($questions);
        
        // Validate questions
        $valid_questions = [];
        foreach ($questions as $index => $question) {
            if (!empty($question['question']) && !empty($question['type'])) {
                $valid_questions[] = $index;
            }
        }
        
        if (empty($valid_questions)) {
            wp_send_json_error('Δεν βρέθηκαν έγκυρες ερωτήσεις σε αυτό το κουίζ');
        }
        
        // Apply randomization if enabled
        if ($randomize_questions === 'yes') {
            shuffle($valid_questions);
        }
        
        // Limit questions if needed
        if ($min_questions < count($valid_questions)) {
            $valid_questions = array_slice($valid_questions, 0, $min_questions);
        }
        
        // Start quiz attempt
        $attempt_id = ELearning_Database::startQuizAttempt($quiz_id, $valid_questions);
        
        if (!$attempt_id) {
            wp_send_json_error('Αποτυχία έναρξης προσπάθειας κουίζ. Παρακαλώ δοκιμάστε ξανά.');
        }
        
        // Get quiz settings
        $time_limit = get_post_meta($quiz_id, '_time_limit', true) ?: 0;
        
        wp_send_json_success([
            'attempt_id' => $attempt_id,
            'total_questions' => count($valid_questions),
            'time_limit' => intval($time_limit),
            'questions_order' => $valid_questions, // Send the order to frontend
            'message' => 'Το κουίζ ξεκίνησε επιτυχώς'
        ]);
    }
    
    /**
     * Get section content for dynamic loading
     */
    public function getSectionContent(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error('Ο έλεγχος ασφαλείας απέτυχε');
        }
        
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $section_index = intval($_POST['section_index'] ?? 0);
        
        if (!$lesson_id) {
            wp_send_json_error('Μη έγκυρο ID μαθήματος');
        }
        
        // Get lesson sections
        $sections = get_post_meta($lesson_id, '_lesson_sections', true) ?: [];
        
        if (!isset($sections[$section_index])) {
            wp_send_json_error('Η ενότητα δεν βρέθηκε');
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
            wp_send_json_error('Ο έλεγχος ασφαλείας απέτυχε');
        }
        
        $attempt_id = sanitize_text_field($_POST['attempt_id'] ?? '');
        $answers_json = wp_unslash($_POST['answers'] ?? '');
        $question_timings_json = wp_unslash($_POST['question_timings'] ?? '{}');
        
        error_log('Attempt ID: ' . $attempt_id);
        error_log('Raw answers JSON: ' . $answers_json);
        
        if (!$attempt_id) {
            error_log('Invalid attempt ID');
            wp_send_json_error('Μη έγκυρο ID προσπάθειας');
        }
        
        // Get attempt details
        global $wpdb;
        $attempt = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}elearning_quiz_attempts WHERE attempt_id = %s",
            $attempt_id
        ), ARRAY_A);
        
        if (!$attempt) {
            error_log('Quiz attempt not found');
            wp_send_json_error('Η προσπάθεια κουίζ δεν βρέθηκε');
        }
        
        if ($attempt['status'] !== 'started') {
            error_log('Quiz already submitted');
            wp_send_json_error('Αυτό το κουίζ έχει ήδη υποβληθεί');
        }
        
        // Check for timeout
        $time_limit = get_post_meta($attempt['quiz_id'], '_time_limit', true) ?: 0;
        if ($time_limit > 0) {
            $elapsed_time = time() - strtotime($attempt['start_time']);
            if ($elapsed_time > ($time_limit * 60)) {
                error_log('Quiz time limit exceeded');
                wp_send_json_error('Το χρονικό όριο του κουίζ έχει λήξει');
            }
        }
        
        // Get quiz data
        $quiz_id = $attempt['quiz_id'];
        $quiz = get_post($quiz_id);
        $questions = get_post_meta($quiz_id, '_quiz_questions', true) ?: [];
        $passing_score = get_post_meta($quiz_id, '_passing_score', true) ?: 70;
        $show_results = get_post_meta($quiz_id, '_show_results_immediately', true) ?: 'yes';
        
        // Parse answers
        $user_answers = json_decode($answers_json, true) ?: [];
        $question_timings = json_decode($question_timings_json, true) ?: [];
        
        error_log('Parsed user answers: ' . print_r($user_answers, true));
        
        // Validate answers
        if (empty($user_answers)) {
            error_log('No answers provided');
            wp_send_json_error('Δεν δόθηκαν απαντήσεις');
        }
        
        // Calculate results with new scoring system
        $results = $this->calculateQuizResultsImproved($questions, $user_answers, $passing_score);
        
        error_log('Quiz results: ' . print_r($results, true));
        
        // Save detailed answers
        foreach ($user_answers as $question_index => $user_answer) {
            if (isset($questions[$question_index])) {
                $question = $questions[$question_index];
                $correct_answer = $this->getCorrectAnswer($question);
                $is_correct = $this->isAnswerCorrect($question, $user_answer, $correct_answer);
                $time_spent = $question_timings[$question_index] ?? null;
                
                error_log("Saving answer for question $question_index - Type: {$question['type']}, Correct: " . ($is_correct ? 'Yes' : 'No'));
                
                ELearning_Database::saveQuizAnswer(
                    $attempt_id,
                    $question_index,
                    $question['type'],
                    $question['question'],
                    $user_answer,
                    $correct_answer,
                    $is_correct,
                    $time_spent
                );
            }
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
            wp_send_json_error('Αποτυχία αποθήκευσης αποτελεσμάτων κουίζ');
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
        
        error_log('Sending success response');
        wp_send_json_success($response_data);
    }
    
    /**
     * Save quiz progress (auto-save)
     */
    public function saveProgress(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error('Ο έλεγχος ασφαλείας απέτυχε');
        }
        
        $attempt_id = sanitize_text_field($_POST['attempt_id'] ?? '');
        $current_question = intval($_POST['current_question'] ?? 0);
        $answers_json = wp_unslash($_POST['answers'] ?? '');
        
        if (!$attempt_id) {
            wp_send_json_error('Μη έγκυρο ID προσπάθειας');
        }
        
        // Store progress in transient (temporary storage)
        $progress_key = 'quiz_progress_' . $attempt_id;
        set_transient($progress_key, [
            'current_question' => $current_question,
            'answers' => json_decode($answers_json, true),
            'saved_at' => current_time('mysql')
        ], HOUR_IN_SECONDS);
        
        wp_send_json_success(['message' => 'Η πρόοδος αποθηκεύτηκε']);
    }
    
    /**
     * Get quiz settings including time limit
     */
    public function getQuizSettings(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error('Ο έλεγχος ασφαλείας απέτυχε');
        }
        
        $quiz_id = intval($_POST['quiz_id'] ?? 0);
        
        if (!$quiz_id) {
            wp_send_json_error('Μη έγκυρο ID κουίζ');
        }
        
        $settings = [
            'time_limit' => get_post_meta($quiz_id, '_time_limit', true) ?: 0,
            'passing_score' => get_post_meta($quiz_id, '_passing_score', true) ?: 70,
            'show_results' => get_post_meta($quiz_id, '_show_results_immediately', true) ?: 'yes',
            'min_questions' => get_post_meta($quiz_id, '_min_questions_to_show', true) ?: 0,
            'randomize_questions' => get_post_meta($quiz_id, '_randomize_questions', true) ?: 'no',
            'randomize_answers' => get_post_meta($quiz_id, '_randomize_answers', true) ?: 'no'
        ];
        
        wp_send_json_success($settings);
    }
    
    /**
     * Calculate quiz results with improved scoring for multi-part questions including True/False
     */
    private function calculateQuizResultsImproved($questions, $user_answers, $passing_score): array {
        $total_points = 0;
        $earned_points = 0;
        $total_questions = 0;
        $correct_full_questions = 0;
        
        error_log('=== CALCULATING IMPROVED QUIZ RESULTS ===');
        
        foreach ($user_answers as $question_index => $user_answer) {
            if (isset($questions[$question_index])) {
                $question = $questions[$question_index];
                
                // Validate question has required fields
                if (empty($question['type']) || empty($question['question'])) {
                    error_log("Question $question_index missing required fields");
                    continue;
                }
                
                $total_questions++;
                
                // Calculate points based on question type
                $question_result = $this->calculateQuestionPoints($question, $user_answer);
                
                $total_points += $question_result['max_points'];
                $earned_points += $question_result['earned_points'];
                
                // Count as correct if user got full points for the question
                if ($question_result['earned_points'] == $question_result['max_points']) {
                    $correct_full_questions++;
                }
                
                error_log("Question $question_index - Type: {$question['type']}, Points: {$question_result['earned_points']}/{$question_result['max_points']}");
            }
        }
        
        // Calculate percentage score based on points
        $score = $total_points > 0 ? ($earned_points / $total_points) * 100 : 0;
        $passed = $score >= $passing_score;
        
        error_log("Final score: $score% ($earned_points/$total_points points)");
        error_log("Full correct questions: $correct_full_questions/$total_questions");
        
        return [
            'score' => round($score, 2),
            'correct_answers' => $correct_full_questions, // For backward compatibility
            'total_questions' => $total_questions,
            'total_points' => $total_points,
            'earned_points' => $earned_points,
            'passed' => $passed
        ];
    }
    
    /**
     * Calculate points for a single question based on type
     */
    private function calculateQuestionPoints($question, $user_answer): array {
        switch ($question['type']) {
            case 'multiple_choice':
                return $this->calculateMultipleChoicePoints($question, $user_answer);
                
            case 'true_false':
                // Check if this is a multi-statement true/false
                if (!empty($question['tf_statements']) && is_array($question['tf_statements'])) {
                    return $this->calculateMultipleTrueFalsePoints($question, $user_answer);
                } else {
                    // Single true/false
                    return $this->calculateTrueFalsePoints($question, $user_answer);
                }
                
            case 'fill_blanks':
                return $this->calculateFillBlanksPoints($question, $user_answer);
                
            case 'matching':
                return $this->calculateMatchingPoints($question, $user_answer);
                
            default:
                return ['max_points' => 1, 'earned_points' => 0];
        }
    }
    
    /**
     * Calculate points for multiple true/false statements
     */
    private function calculateMultipleTrueFalsePoints($question, $user_answer): array {
        $statements = $question['tf_statements'] ?? [];
        $max_points = count($statements);
        $earned_points = 0;
        
        if (!is_array($user_answer)) {
            return ['max_points' => $max_points, 'earned_points' => 0];
        }
        
        foreach ($statements as $index => $statement) {
            $correct_answer = $statement['answer'] ?? 'true';
            $user_answer_for_statement = $user_answer[$index] ?? null;
            
            if ($user_answer_for_statement === $correct_answer) {
                $earned_points++;
            }
        }
        
        error_log("Multiple T/F: $earned_points correct out of $max_points statements");
        
        return [
            'max_points' => $max_points,
            'earned_points' => $earned_points
        ];
    }
    
    /**
     * Calculate points for multiple choice questions - NO PENALTY FOR WRONG ANSWERS
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
        
        // Multiple correct answers - partial credit WITHOUT penalty
        if (!is_array($user_answer)) {
            $user_answer = [$user_answer];
        }
        
        $user_answer = array_map('intval', $user_answer);
        $correct_answers = array_map('intval', $correct_answers);
        
        // Count correct selections
        $correct_selections = array_intersect($user_answer, $correct_answers);
        
        // Give partial credit for each correct answer, NO penalty for wrong ones
        $points = count($correct_selections);
        $max_points = count($correct_answers);
        
        return [
            'max_points' => $max_points,
            'earned_points' => min($points, $max_points) // Ensure we don't exceed max points
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
     * Calculate points for matching questions
     */
    private function calculateMatchingPoints($question, $user_answer): array {
        if (!is_array($user_answer)) {
            return ['max_points' => 1, 'earned_points' => 0];
        }
        
        $correct_matches = $question['matches'] ?? [];
        $max_points = count($correct_matches);
        $earned_points = 0;
        
        // Check each correct match
        foreach ($correct_matches as $match) {
            $left_index = strval($match['left'] ?? '');
            $right_index = strval($match['right'] ?? '');
            
            if (isset($user_answer[$left_index]) && strval($user_answer[$left_index]) === $right_index) {
                $earned_points++;
            }
        }
        
        error_log("Matching: $earned_points correct out of $max_points matches");
        
        return [
            'max_points' => $max_points,
            'earned_points' => $earned_points
        ];
    }
    
    /**
     * Get correct answer for a question
     */
    private function getCorrectAnswer($question) {
        switch ($question['type']) {
            case 'multiple_choice':
                return $question['correct_answers'] ?? [];
                
            case 'true_false':
                // Check if this is multi-statement
                if (!empty($question['tf_statements'])) {
                    $correct_answers = [];
                    foreach ($question['tf_statements'] as $index => $statement) {
                        $correct_answers[$index] = $statement['answer'] ?? 'true';
                    }
                    return $correct_answers;
                }
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
     * Get detailed results for review - ENHANCED VERSION
     */
    private function getDetailedResults($questions, $user_answers): array {
        $detailed_results = [];
        
        foreach ($user_answers as $question_index => $user_answer) {
            if (isset($questions[$question_index])) {
                $question = $questions[$question_index];
                $correct_answer = $this->getCorrectAnswer($question);
                
                // Calculate points for this question
                $points_result = $this->calculateQuestionPoints($question, $user_answer);
                $is_correct = $points_result['earned_points'] == $points_result['max_points'];
                
                // Format answers for display
                $formatted_user_answer = $this->formatAnswerForDisplay($user_answer, $question);
                $formatted_correct_answer = $this->formatAnswerForDisplay($correct_answer, $question);
                
                $result_data = [
                    'question' => wp_strip_all_tags($question['question']),
                    'question_type' => $question['type'],
                    'question_index' => $question_index,
                    'user_answer' => $formatted_user_answer,
                    'correct_answer' => $formatted_correct_answer,
                    'user_answer_raw' => $user_answer,
                    'correct_answer_raw' => $correct_answer,
                    'correct' => $is_correct,
                    'earned_points' => $points_result['earned_points'],
                    'max_points' => $points_result['max_points'],
                    'partial_credit' => $points_result['earned_points'] > 0 && !$is_correct
                ];
                
                // Add text_with_blanks for fill-in-the-blanks questions
                if ($question['type'] === 'fill_blanks') {
                    $result_data['text_with_blanks'] = $question['text_with_blanks'] ?? '';
                }
                
                // Add statements for multiple true/false
                if ($question['type'] === 'true_false' && !empty($question['tf_statements'])) {
                    $result_data['tf_statements'] = $question['tf_statements'];
                }
                
                $detailed_results[] = $result_data;
            }
        }
        
        return $detailed_results;
    }
    
    /**
     * Format answer for display
     */
    private function formatAnswerForDisplay($answer, $question): string {
        switch ($question['type']) {
            case 'multiple_choice':
                if (is_array($answer)) {
                    $options = [];
                    foreach ($answer as $index) {
                        if (isset($question['options'][$index])) {
                            $options[] = $question['options'][$index];
                        }
                    }
                    return implode(', ', $options);
                } else {
                    return $question['options'][$answer] ?? '';
                }
                
            case 'true_false':
                // Check if this is multi-statement
                if (!empty($question['tf_statements']) && is_array($answer)) {
                    $formatted = [];
                    foreach ($question['tf_statements'] as $index => $statement) {
                        $user_answer = $answer[$index] ?? 'no answer';
                        $formatted[] = ($index + 1) . '. ' . ($user_answer === 'true' ? 'Σωστό' : ($user_answer === 'false' ? 'Λάθος' : 'Χωρίς απάντηση'));
                    }
                    return implode('; ', $formatted);
                }
                return $answer === 'true' ? 'Σωστό' : 'Λάθος';
                
            case 'fill_blanks':
                if (is_array($answer)) {
                    return implode(', ', array_filter($answer));
                }
                return $answer;
                
            case 'matching':
                if (is_array($answer)) {
                    $matches = [];
                    
                    // For correct answer display (array of match objects)
                    if (isset($answer[0]) && is_array($answer[0]) && isset($answer[0]['left'])) {
                        foreach ($answer as $match) {
                            $left_idx = $match['left'];
                            $right_idx = $match['right'];
                            if (isset($question['left_column'][$left_idx]) && isset($question['right_column'][$right_idx])) {
                                $matches[] = $question['left_column'][$left_idx] . ' → ' . $question['right_column'][$right_idx];
                            }
                        }
                    } else {
                        // For user answer display (associative array)
                        foreach ($answer as $left => $right) {
                            if (isset($question['left_column'][$left]) && isset($question['right_column'][$right])) {
                                $matches[] = $question['left_column'][$left] . ' → ' . $question['right_column'][$right];
                            }
                        }
                    }
                    return implode('; ', $matches);
                }
                return '';
                
            default:
                return is_array($answer) ? implode(', ', $answer) : $answer;
        }
    }
    
    /**
     * Update lesson progress with improved tracking
     */
    public function updateLessonProgress(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_quiz_nonce')) {
            wp_send_json_error('Ο έλεγχος ασφαλείας απέτυχε');
        }
        
        $lesson_id = intval($_POST['lesson_id'] ?? 0);
        $section_index = intval($_POST['section_index'] ?? 0);
        $completed = !empty($_POST['completed']);
        $time_spent = intval($_POST['time_spent'] ?? 0);
        $scroll_percentage = floatval($_POST['scroll_percentage'] ?? 0);
        
        if (!$lesson_id) {
            wp_send_json_error('Μη έγκυρο ID μαθήματος');
        }
        
        // Validate lesson exists
        $lesson = get_post($lesson_id);
        if (!$lesson || $lesson->post_type !== 'elearning_lesson') {
            wp_send_json_error('Το μάθημα δεν βρέθηκε');
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
                'message' => 'Η πρόοδος ενημερώθηκε',
                'overall_progress' => round($overall_progress, 2),
                'completed_sections' => $completed_sections,
                'total_sections' => $total_sections
            ]);
        } else {
            wp_send_json_error('Αποτυχία ενημέρωσης προόδου');
        }
    }
}