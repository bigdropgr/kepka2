<?php
/**
 * Shortcodes Class
 * 
 * Handles all shortcodes for the e-learning system
 * Updated with quiz access control
 */

if (!defined('ABSPATH')) {
    exit;
}

class ELearning_Shortcodes {
    
    public function __construct() {
        add_action('init', [$this, 'registerShortcodes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueShortcodeAssets']);
    }
    
    /**
     * Register all shortcodes
     */
    public function registerShortcodes(): void {
        add_shortcode('display_lesson', [$this, 'displayLesson']);
        add_shortcode('display_quiz', [$this, 'displayQuiz']);
        add_shortcode('quiz_stats', [$this, 'displayQuizStats']);
        add_shortcode('user_progress', [$this, 'displayUserProgress']);
        add_shortcode('lesson_feed', [$this, 'displayLessonFeed']);
        add_shortcode('quiz_feed', [$this, 'displayQuizFeed']);
    }
    
    /**
     * Enqueue assets when shortcodes are used
     */
    public function enqueueShortcodeAssets(): void {
        global $post;
        
        if (!$post) {
            return;
        }
        
        $content = $post->post_content;
        
        $shortcodes = ['display_lesson', 'display_quiz', 'quiz_stats', 'user_progress', 'lesson_feed', 'quiz_feed'];
        $has_shortcode = false;
        
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($content, $shortcode)) {
                $has_shortcode = true;
                break;
            }
        }
        
        if ($has_shortcode) {
            // Enqueue styles and scripts
            wp_enqueue_style(
                'elearning-shortcodes',
                ELEARNING_QUIZ_PLUGIN_URL . 'assets/css/shortcodes.css',
                [],
                ELEARNING_QUIZ_VERSION
            );
            
            wp_enqueue_script(
                'elearning-shortcodes',
                ELEARNING_QUIZ_PLUGIN_URL . 'assets/js/shortcodes.js',
                ['jquery'],
                ELEARNING_QUIZ_VERSION,
                true
            );
            
            wp_localize_script('elearning-shortcodes', 'elearningShortcodes', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('elearning_shortcode_nonce'),
                'strings' => [
                    'loading' => 'Φόρτωση...',
                    'error' => 'Παρουσιάστηκε σφάλμα',
                    'lesson_button_clicked' => 'Κλικ στο κουμπί μαθήματος:',
                    'quiz_button_clicked' => 'Κλικ στο κουμπί κουίζ:',
                    'progress_data_received' => 'Δεδομένα προόδου ελήφθησαν:'
                ]
            ]);
        }
    }
    
    /**
     * Display Lesson Feed Shortcode
     */
    public function displayLessonFeed($atts): string {
        $atts = shortcode_atts([
            'posts_per_page' => 6,
            'columns' => 3,
            'show_excerpt' => 'true',
            'show_progress' => 'true',
            'show_quiz_link' => 'true',
            'show_sections_count' => 'true',
            'show_featured_image' => 'true',
            'category' => '',
            'order' => 'DESC',
            'orderby' => 'date',
            'layout' => 'grid', // grid or list
            'pagination' => 'false',
            'paged' => 1
        ], $atts, 'lesson_feed');
        
        // Handle pagination
        if ($atts['pagination'] === 'true') {
            $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
        } else {
            $paged = intval($atts['paged']);
        }
        
        // Query arguments
        $args = [
            'post_type' => 'elearning_lesson',
            'posts_per_page' => intval($atts['posts_per_page']),
            'paged' => $paged,
            'order' => $atts['order'],
            'orderby' => $atts['orderby'],
            'post_status' => 'publish'
        ];
        
        // Add category filter if specified
        if (!empty($atts['category'])) {
            $args['tax_query'] = [[
                'taxonomy' => 'lesson_category',
                'field' => 'slug',
                'terms' => explode(',', $atts['category'])
            ]];
        }
        
        $lessons_query = new WP_Query($args);
        
        if (!$lessons_query->have_posts()) {
            return '<div class="elearning-notice">Δεν βρέθηκαν μαθήματα.</div>';
        }
        
        ob_start();
        
        // Determine column classes
        $columns = intval($atts['columns']);
        $column_class = '';
        
        switch ($columns) {
            case 2:
                $column_class = 'col-lg-6 col-md-6 col-sm-6 col-12';
                break;
            case 3:
                $column_class = 'col-lg-4 col-md-6 col-sm-6 col-12';
                break;
            case 4:
                $column_class = 'col-lg-3 col-md-4 col-sm-6 col-12';
                break;
            case 6:
                $column_class = 'col-lg-2 col-md-4 col-sm-6 col-12';
                break;
            default:
                $column_class = 'col-lg-4 col-md-6 col-sm-6 col-12';
        }
        
        ?>
        <div class="elearning-lesson-feed layout-<?php echo esc_attr($atts['layout']); ?>">
            <?php if ($atts['layout'] === 'grid'): ?>
                <div class="row wd-spacing-20">
                    <?php while ($lessons_query->have_posts()): $lessons_query->the_post(); ?>
                        <div class="<?php echo esc_attr($column_class); ?> lesson-feed-column">
                            <?php echo $this->renderLessonCard(get_the_ID(), $atts); ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <?php while ($lessons_query->have_posts()): $lessons_query->the_post(); ?>
                    <?php echo $this->renderLessonCard(get_the_ID(), $atts); ?>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($atts['pagination'] === 'true' && $lessons_query->max_num_pages > 1): ?>
            <div class="elearning-pagination">
                <?php
                echo paginate_links([
                    'total' => $lessons_query->max_num_pages,
                    'current' => $paged,
                    'prev_text' => '<i class="fa fa-angle-left"></i>',
                    'next_text' => '<i class="fa fa-angle-right"></i>',
                    'type' => 'list'
                ]);
                ?>
            </div>
        <?php endif; ?>
        
        <?php
        wp_reset_postdata();
        
        return ob_get_clean();
    }
    
    /**
     * Render individual lesson card
     */
    private function renderLessonCard($lesson_id, $atts): string {
        $lesson = get_post($lesson_id);
        $sections = get_post_meta($lesson_id, '_lesson_sections', true) ?: [];
        $associated_quiz = get_post_meta($lesson_id, '_associated_quiz', true);
        
        // Get user progress
        $user_session = ELearning_Database::getOrCreateUserSession();
        $progress_percentage = $this->getLessonProgressPercentage($lesson_id, $user_session);
        $is_completed = $progress_percentage >= 100;
        
        ob_start();
        ?>
        <div class="lesson-feed-item <?php echo $is_completed ? 'completed' : ''; ?>">
            <div class="lesson-card">
                <?php if ($atts['show_featured_image'] === 'true' && has_post_thumbnail($lesson_id)): ?>
                    <div class="lesson-card-image">
                        <a href="<?php echo get_permalink($lesson_id); ?>">
                            <?php echo get_the_post_thumbnail($lesson_id, 'medium'); ?>
                        </a>
                        <?php if ($is_completed): ?>
                            <div class="lesson-completed-badge">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="lesson-card-content">
                    <h3 class="lesson-card-title">
                        <a href="<?php echo get_permalink($lesson_id); ?>">
                            <?php echo esc_html($lesson->post_title); ?>
                        </a>
                    </h3>
                    
                    <?php if ($atts['show_sections_count'] === 'true'): ?>
                        <div class="lesson-meta">
                            <span class="sections-count">
                                <i class="fas fa-list"></i>
                                <?php echo count($sections); ?> ενότητες
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_excerpt'] === 'true'): ?>
                        <div class="lesson-excerpt">
                            <?php echo wp_trim_words($lesson->post_excerpt ?: $lesson->post_content, 20); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_progress'] === 'true'): ?>
                        <div class="lesson-progress-bar">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%;"></div>
                            </div>
                            <span class="progress-text"><?php echo $progress_percentage; ?>% ολοκληρωμένο</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="lesson-card-actions">
                        <a href="<?php echo get_permalink($lesson_id); ?>" class="button lesson-btn">
                            <?php echo $is_completed ? 'Επανάληψη Μαθήματος' : 'Έναρξη Μαθήματος'; ?>
                        </a>
                        
                        <?php if ($atts['show_quiz_link'] === 'true' && $associated_quiz && $is_completed): ?>
                            <a href="<?php echo get_permalink($associated_quiz); ?>" class="button quiz-btn">
                                Δώστε το Κουίζ
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Display Quiz Feed Shortcode
     * Usage: [quiz_feed posts_per_page="6" columns="3" show_excerpt="true" show_stats="true" show_lesson_link="true" category="" order="DESC" orderby="date"]
     */
    public function displayQuizFeed($atts): string {
        $atts = shortcode_atts([
            'posts_per_page' => 6,
            'columns' => 3,
            'show_excerpt' => 'true',
            'show_stats' => 'true',
            'show_lesson_link' => 'true',
            'show_questions_count' => 'true',
            'show_passing_score' => 'true',
            'show_attempts' => 'true',
            'show_featured_image' => 'true',
            'category' => '',
            'order' => 'DESC',
            'orderby' => 'date',
            'layout' => 'grid', // grid or list
            'pagination' => 'false',
            'paged' => 1
        ], $atts, 'quiz_feed');
        
        // Handle pagination
        if ($atts['pagination'] === 'true') {
            $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
        } else {
            $paged = intval($atts['paged']);
        }
        
        // Query arguments
        $args = [
            'post_type' => 'elearning_quiz',
            'posts_per_page' => intval($atts['posts_per_page']),
            'paged' => $paged,
            'order' => $atts['order'],
            'orderby' => $atts['orderby'],
            'post_status' => 'publish'
        ];
        
        // Add category filter if specified
        if (!empty($atts['category'])) {
            $args['tax_query'] = [[
                'taxonomy' => 'quiz_category',
                'field' => 'slug',
                'terms' => explode(',', $atts['category'])
            ]];
        }
        
        $quizzes_query = new WP_Query($args);
        
        if (!$quizzes_query->have_posts()) {
            return '<div class="elearning-notice">Δεν βρέθηκαν κουίζ.</div>';
        }
        
        ob_start();
        
        // Add inline styles to ensure grid layout works
        ?>
        <style>
        .elearning-quiz-feed-grid {
            display: flex !important;
            flex-wrap: wrap !important;
            margin-left: -10px !important;
            margin-right: -10px !important;
        }
        
        .elearning-quiz-feed-grid .quiz-grid-item {
            padding: 0 10px !important;
            margin-bottom: 20px !important;
        }
        
        @media (min-width: 992px) {
            .elearning-quiz-feed-grid .quiz-grid-item {
                flex: 0 0 <?php echo 100/intval($atts['columns']); ?>% !important;
                max-width: <?php echo 100/intval($atts['columns']); ?>% !important;
            }
        }
        
        @media (min-width: 768px) and (max-width: 991px) {
            .elearning-quiz-feed-grid .quiz-grid-item {
                flex: 0 0 50% !important;
                max-width: 50% !important;
            }
        }
        
        @media (max-width: 767px) {
            .elearning-quiz-feed-grid .quiz-grid-item {
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }
        }
        </style>
        
        <div class="elearning-quiz-feed layout-<?php echo esc_attr($atts['layout']); ?>">
            <?php if ($atts['layout'] === 'grid'): ?>
                <div class="elearning-quiz-feed-grid">
                    <?php while ($quizzes_query->have_posts()): $quizzes_query->the_post(); ?>
                        <div class="quiz-grid-item">
                            <?php echo $this->renderQuizCard(get_the_ID(), $atts); ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <?php while ($quizzes_query->have_posts()): $quizzes_query->the_post(); ?>
                    <?php echo $this->renderQuizCard(get_the_ID(), $atts); ?>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($atts['pagination'] === 'true' && $quizzes_query->max_num_pages > 1): ?>
            <div class="elearning-pagination">
                <?php
                echo paginate_links([
                    'total' => $quizzes_query->max_num_pages,
                    'current' => $paged,
                    'prev_text' => '<i class="fa fa-angle-left"></i>',
                    'next_text' => '<i class="fa fa-angle-right"></i>',
                    'type' => 'list'
                ]);
                ?>
            </div>
        <?php endif; ?>
        
        <?php
        wp_reset_postdata();
        
        return ob_get_clean();
    }
    
    /**
     * Render individual quiz card
     */
    private function renderQuizCard($quiz_id, $atts): string {
        $quiz = get_post($quiz_id);
        $questions = get_post_meta($quiz_id, '_quiz_questions', true) ?: [];
        $passing_score = get_post_meta($quiz_id, '_passing_score', true) ?: 70;
        $associated_lesson = get_post_meta($quiz_id, '_associated_lesson', true);
        $time_limit = get_post_meta($quiz_id, '_time_limit', true) ?: 0;
        
        // Get user attempts
        $user_session = ELearning_Database::getOrCreateUserSession();
        $attempts = ELearning_Database::getUserQuizAttempts($user_session, $quiz_id);
        $has_passed = false;
        $best_score = 0;
        $attempt_count = count($attempts);
        
        foreach ($attempts as $attempt) {
            if ($attempt['passed'] == 1) {
                $has_passed = true;
            }
            if ($attempt['score'] > $best_score) {
                $best_score = $attempt['score'];
            }
        }
        
        // Check if user can access quiz
        $can_access = true;
        $lesson_completed = false;
        
        if ($associated_lesson) {
            $lesson_completed = $this->isLessonCompleted($associated_lesson, $user_session);
            $can_access = $lesson_completed;
        }
        
        ob_start();
        ?>
        <div class="quiz-feed-item <?php echo $has_passed ? 'passed' : ''; ?> <?php echo !$can_access ? 'locked' : ''; ?>">
            <div class="quiz-card">
                <?php if ($atts['show_featured_image'] === 'true' && has_post_thumbnail($quiz_id)): ?>
                    <div class="quiz-card-image">
                        <a href="<?php echo get_permalink($quiz_id); ?>">
                            <?php echo get_the_post_thumbnail($quiz_id, 'medium'); ?>
                        </a>
                        <?php if ($has_passed): ?>
                            <div class="quiz-passed-badge">
                                <i class="fas fa-trophy"></i>
                            </div>
                        <?php elseif (!$can_access): ?>
                            <div class="quiz-locked-badge">
                                <i class="fas fa-lock"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="quiz-card-content">
                    <h3 class="quiz-card-title">
                        <a href="<?php echo get_permalink($quiz_id); ?>">
                            <?php echo esc_html($quiz->post_title); ?>
                        </a>
                    </h3>
                    
                    <div class="quiz-meta">
                        <?php if ($atts['show_questions_count'] === 'true'): ?>
                            <span class="questions-count">
                                <i class="fas fa-question-circle"></i>
                                <?php echo count($questions); ?> ερωτήσεις
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($atts['show_passing_score'] === 'true'): ?>
                            <span class="passing-score">
                                <i class="fas fa-percentage"></i>
                                <?php echo $passing_score; ?>% για επιτυχία
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($time_limit > 0): ?>
                            <span class="time-limit">
                                <i class="fas fa-clock"></i>
                                <?php echo $time_limit; ?> λεπτά
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($atts['show_excerpt'] === 'true' && $quiz->post_excerpt): ?>
                        <div class="quiz-excerpt">
                            <?php echo wp_trim_words($quiz->post_excerpt, 20); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($atts['show_stats'] === 'true' && $attempt_count > 0): ?>
                        <div class="quiz-user-stats">
                            <span class="attempts">
                                <i class="fas fa-redo"></i>
                                <?php echo $attempt_count; ?> προσπάθειες
                            </span>
                            <span class="best-score">
                                <i class="fas fa-star"></i>
                                Καλύτερη: <?php echo number_format($best_score, 1); ?>%
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$can_access && $associated_lesson): ?>
                        <div class="quiz-locked-notice">
                            <?php 
                            $lesson = get_post($associated_lesson);
                            if ($lesson) {
                                echo 'Ολοκληρώστε πρώτα το <strong>' . esc_html($lesson->post_title) . '</strong>';
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="quiz-card-actions">
                        <?php if ($can_access): ?>
                            <a href="<?php echo get_permalink($quiz_id); ?>" class="button quiz-btn">
                                <?php echo $has_passed ? 'Επανάληψη Κουίζ' : 'Έναρξη Κουίζ'; ?>
                            </a>
                        <?php else: ?>
                            <?php if ($associated_lesson): ?>
                                <a href="<?php echo get_permalink($associated_lesson); ?>" class="button lesson-btn">
                                    Μετάβαση στο Μάθημα
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($atts['show_lesson_link'] === 'true' && $associated_lesson && $can_access): ?>
                            <a href="<?php echo get_permalink($associated_lesson); ?>" class="button lesson-link-btn">
                                Επανάληψη Μαθήματος
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Display Lesson Shortcode
     * Usage: [display_lesson id="123"]
     */
    public function displayLesson($atts): string {
        $atts = shortcode_atts([
            'id' => 0,
            'show_progress' => 'true',
            'show_quiz_link' => 'true'
        ], $atts, 'display_lesson');
        
        $lesson_id = intval($atts['id']);
        
        if (!$lesson_id) {
            return '<div class="elearning-error">Παρακαλώ καθορίστε ένα ID μαθήματος.</div>';
        }
        
        $lesson = get_post($lesson_id);
        
        if (!$lesson || $lesson->post_type !== 'elearning_lesson') {
            return '<div class="elearning-error">Το μάθημα δεν βρέθηκε.</div>';
        }
        
        ob_start();
        ?>
        <div class="embedded-lesson" data-lesson-id="<?php echo esc_attr($lesson_id); ?>">
            <div class="lesson-header">
                <h3 class="lesson-title">
                    <a href="<?php echo get_permalink($lesson_id); ?>"><?php echo esc_html($lesson->post_title); ?></a>
                </h3>
                
                <?php if ($atts['show_progress'] === 'true'): ?>
                    <div class="lesson-progress-indicator">
                        <?php echo $this->getLessonProgressIndicator($lesson_id); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="lesson-excerpt">
                <?php echo wp_trim_words($lesson->post_excerpt ?: $lesson->post_content, 30); ?>
            </div>
            
            <div class="lesson-actions">
                <a href="<?php echo get_permalink($lesson_id); ?>" class="button lesson-btn">
                    Προβολή Μαθήματος
                </a>
                
                <?php if ($atts['show_quiz_link'] === 'true'): ?>
                    <?php
                    $associated_quiz = get_post_meta($lesson_id, '_associated_quiz', true);
                    if ($associated_quiz):
                    ?>
                        <a href="<?php echo get_permalink($associated_quiz); ?>" class="button quiz-btn">
                            Δώστε το Κουίζ
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Display Quiz Shortcode - Updated with access control
     * Usage: [display_quiz id="123"]
     */
    public function displayQuiz($atts): string {
        $atts = shortcode_atts([
            'id' => 0,
            'show_stats' => 'false',
            'show_description' => 'true',
            'show_access_requirements' => 'true'
        ], $atts, 'display_quiz');
        
        $quiz_id = intval($atts['id']);
        
        if (!$quiz_id) {
            return '<div class="elearning-error">Παρακαλώ καθορίστε ένα ID κουίζ.</div>';
        }
        
        $quiz = get_post($quiz_id);
        
        if (!$quiz || $quiz->post_type !== 'elearning_quiz') {
            return '<div class="elearning-error">Το κουίζ δεν βρέθηκε.</div>';
        }
        
        $questions = get_post_meta($quiz_id, '_quiz_questions', true) ?: [];
        $passing_score = get_post_meta($quiz_id, '_passing_score', true) ?: 70;
        $associated_lesson = get_post_meta($quiz_id, '_associated_lesson', true);
        
        // Check if user has completed the required lesson
        $can_access = true;
        $lesson_completed = false;
        $lesson_progress = 0;
        
        if ($associated_lesson && $atts['show_access_requirements'] === 'true') {
            $user_session = ELearning_Database::getOrCreateUserSession();
            $lesson_completed = $this->isLessonCompleted($associated_lesson, $user_session);
            $lesson_progress = $this->getLessonProgressPercentage($associated_lesson, $user_session);
            $can_access = $lesson_completed;
        }
        
        ob_start();
        ?>
        <div class="embedded-quiz" data-quiz-id="<?php echo esc_attr($quiz_id); ?>">
            <div class="quiz-header">
                <h3 class="quiz-title">
                    <?php if ($can_access): ?>
                        <a href="<?php echo get_permalink($quiz_id); ?>"><?php echo esc_html($quiz->post_title); ?></a>
                    <?php else: ?>
                        <?php echo esc_html($quiz->post_title); ?> <i class="fas fa-lock"></i>
                    <?php endif; ?>
                </h3>
                
                <div class="quiz-meta">
                    <span class="quiz-questions"><?php echo count($questions); ?> Ερωτήσεις</span>
                    <span class="quiz-passing"><?php echo $passing_score; ?>% για Επιτυχία</span>
                </div>
            </div>
            
            <?php if (!$can_access && $associated_lesson): ?>
                <div class="quiz-access-notice">
                    <p class="access-requirement">
                        <?php 
                        $lesson = get_post($associated_lesson);
                        if ($lesson) {
                            echo 'Πρέπει να ολοκληρώσετε το <strong>' . esc_html($lesson->post_title) . '</strong> πριν δώσετε αυτό το κουίζ.';
                        }
                        ?>
                    </p>
                    <?php if ($lesson_progress > 0): ?>
                        <div class="progress-preview">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $lesson_progress; ?>%;"></div>
                            </div>
                            <span class="progress-text"><?php echo $lesson_progress; ?>% ολοκληρωμένο</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_description'] === 'true' && $quiz->post_excerpt && $can_access): ?>
                <div class="quiz-description">
                    <?php echo wp_kses_post($quiz->post_excerpt); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_stats'] === 'true' && $can_access): ?>
                <div class="quiz-stats-summary">
                    <?php echo $this->getQuizStatsSummary($quiz_id); ?>
                </div>
            <?php endif; ?>
            
            <div class="quiz-actions">
                <?php if ($can_access): ?>
                    <a href="<?php echo get_permalink($quiz_id); ?>" class="button quiz-start-btn">
                        Έναρξη Κουίζ
                    </a>
                <?php else: ?>
                    <?php if ($associated_lesson): ?>
                        <a href="<?php echo get_permalink($associated_lesson); ?>" class="button lesson-link-btn">
                            Μετάβαση στο Μάθημα
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($can_access && $associated_lesson): ?>
                    <a href="<?php echo get_permalink($associated_lesson); ?>" class="button lesson-link-btn">
                        Επανάληψη Μαθήματος
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .embedded-quiz .quiz-access-notice {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .embedded-quiz .quiz-access-notice .access-requirement {
            color: #721c24;
            margin: 0 0 10px 0;
        }
        
        .embedded-quiz .progress-preview {
            max-width: 300px;
        }
        
        .embedded-quiz .progress-preview .progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .embedded-quiz .progress-preview .progress-fill {
            height: 100%;
            background: #28a745;
            transition: width 0.3s ease;
        }
        
        .embedded-quiz .progress-preview .progress-text {
            font-size: 12px;
            color: #6c757d;
        }
        
        .embedded-quiz .quiz-title .fa-lock {
            color: #6c757d;
            font-size: 0.8em;
            margin-left: 5px;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Check if lesson is completed
     */
    private function isLessonCompleted($lesson_id, $user_session) {
        $sections = get_post_meta($lesson_id, '_lesson_sections', true) ?: [];
        if (empty($sections)) {
            return true; // No sections means lesson is accessible
        }
        
        $progress = ELearning_Database::getLessonProgress($lesson_id, $user_session);
        
        // Check if all sections are completed
        $total_sections = count($sections);
        $completed_sections = 0;
        
        foreach ($progress as $section_progress) {
            if (!empty($section_progress['completed'])) {
                $completed_sections++;
            }
        }
        
        return $completed_sections >= $total_sections;
    }
    
    /**
     * Get lesson progress percentage
     */
    private function getLessonProgressPercentage($lesson_id, $user_session) {
        $sections = get_post_meta($lesson_id, '_lesson_sections', true) ?: [];
        if (empty($sections)) {
            return 100;
        }
        
        $progress = ELearning_Database::getLessonProgress($lesson_id, $user_session);
        
        $total_sections = count($sections);
        $completed_sections = 0;
        
        foreach ($progress as $section_progress) {
            if (!empty($section_progress['completed'])) {
                $completed_sections++;
            }
        }
        
        return $total_sections > 0 ? round(($completed_sections / $total_sections) * 100) : 0;
    }
    
    /**
     * Display Quiz Statistics Shortcode
     * Usage: [quiz_stats id="123" type="basic"]
     */
    public function displayQuizStats($atts): string {
        $atts = shortcode_atts([
            'id' => 0,
            'type' => 'basic', // basic, detailed, chart
            'show_language' => 'true'
        ], $atts, 'quiz_stats');
        
        $quiz_id = intval($atts['id']);
        
        if (!$quiz_id) {
            return '<div class="elearning-error">Παρακαλώ καθορίστε ένα ID κουίζ.</div>';
        }
        
        $stats = ELearning_Database::getQuizStatistics($quiz_id);
        
        if (empty($stats)) {
            return '<div class="elearning-notice">Δεν υπάρχουν ακόμα στατιστικά για αυτό το κουίζ.</div>';
        }
        
        ob_start();
        ?>
        <div class="quiz-statistics" data-quiz-id="<?php echo esc_attr($quiz_id); ?>">
            <h4>Στατιστικά Κουίζ</h4>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['total_attempts']); ?></span>
                    <span class="stat-label">Συνολικές Προσπάθειες</span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['passed_attempts']); ?></span>
                    <span class="stat-label">Επιτυχίες</span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($stats['average_score'], 1); ?>%</span>
                    <span class="stat-label">Μέση Βαθμολογία</span>
                </div>
                
                <?php if ($atts['show_language'] === 'true'): ?>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo number_format($stats['english_attempts']); ?> / <?php echo number_format($stats['greek_attempts']); ?></span>
                        <span class="stat-label">EN / EL</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($atts['type'] === 'detailed'): ?>
                <div class="detailed-stats">
                    <div class="stat-row">
                        <span class="stat-label">Ποσοστό Ολοκλήρωσης:</span>
                        <span class="stat-value">
                            <?php 
                            $completion_rate = $stats['total_attempts'] > 0 ? ($stats['completed_attempts'] / $stats['total_attempts']) * 100 : 0;
                            echo number_format($completion_rate, 1); 
                            ?>%
                        </span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Ποσοστό Επιτυχίας:</span>
                        <span class="stat-value">
                            <?php 
                            $pass_rate = $stats['completed_attempts'] > 0 ? ($stats['passed_attempts'] / $stats['completed_attempts']) * 100 : 0;
                            echo number_format($pass_rate, 1); 
                            ?>%
                        </span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Υψηλότερη Βαθμολογία:</span>
                        <span class="stat-value"><?php echo number_format($stats['highest_score'], 1); ?>%</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Χαμηλότερη Βαθμολογία:</span>
                        <span class="stat-value"><?php echo number_format($stats['lowest_score'], 1); ?>%</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Display User Progress Shortcode
     * Usage: [user_progress]
     */
    public function displayUserProgress($atts): string {
        $atts = shortcode_atts([
            'show_quizzes' => 'true',
            'show_lessons' => 'true',
            'limit' => 5
        ], $atts, 'user_progress');
        
        $user_session = ELearning_Database::getOrCreateUserSession();
        $limit = intval($atts['limit']);
        
        ob_start();
        ?>
        <div class="user-progress-widget">
            <h4>Η Πρόοδός σας</h4>
            
            <?php if ($atts['show_quizzes'] === 'true'): ?>
                <div class="progress-section quiz-progress">
                    <h5>Πρόσφατες Προσπάθειες Κουίζ</h5>
                    <?php echo $this->getUserRecentQuizzes($user_session, $limit); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($atts['show_lessons'] === 'true'): ?>
                <div class="progress-section lesson-progress">
                    <h5>Πρόοδος Μαθημάτων</h5>
                    <?php echo $this->getUserLessonProgress($user_session, $limit); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Helper: Get lesson progress indicator
     */
    private function getLessonProgressIndicator($lesson_id): string {
        $user_session = ELearning_Database::getOrCreateUserSession();
        $progress = ELearning_Database::getLessonProgress($lesson_id, $user_session);
        
        $total_sections = count(get_post_meta($lesson_id, '_lesson_sections', true) ?: []);
        $completed_sections = 0;
        
        foreach ($progress as $section_progress) {
            if (!empty($section_progress['completed'])) {
                $completed_sections++;
            }
        }
        
        $percentage = $total_sections > 0 ? ($completed_sections / $total_sections) * 100 : 0;
        
        return sprintf(
            '<div class="progress-bar"><div class="progress-fill" style="width: %d%%"></div></div><span class="progress-text">%d/%d ενότητες</span>',
            $percentage,
            $completed_sections,
            $total_sections
        );
    }
    
    /**
     * Helper: Get quiz stats summary
     */
    private function getQuizStatsSummary($quiz_id): string {
        $stats = ELearning_Database::getQuizStatistics($quiz_id);
        
        if (empty($stats)) {
            return '<p>Δεν υπάρχουν προσπάθειες ακόμα.</p>';
        }
        
        $pass_rate = $stats['completed_attempts'] > 0 ? ($stats['passed_attempts'] / $stats['completed_attempts']) * 100 : 0;
        
        return sprintf(
            '<div class="stats-mini"><span>%d προσπάθειες</span> <span>%.1f%% επιτυχία</span> <span>%.1f%% μέσος όρος</span></div>',
            $stats['total_attempts'],
            $pass_rate,
            $stats['average_score']
        );
    }
    
    /**
     * Helper: Get user recent quizzes
     */
    private function getUserRecentQuizzes($user_session, $limit): string {
        $attempts = ELearning_Database::getUserQuizAttempts($user_session);
        $attempts = array_slice($attempts, 0, $limit);
        
        if (empty($attempts)) {
            return '<p>Δεν υπάρχουν προσπάθειες κουίζ ακόμα.</p>';
        }
        
        $output = '<ul class="progress-list">';
        foreach ($attempts as $attempt) {
            $quiz_title = get_the_title($attempt['quiz_id']);
            $status_class = $attempt['passed'] ? 'passed' : 'failed';
            $status_text = $attempt['passed'] ? 'Επιτυχία' : 'Αποτυχία';
            
            $output .= sprintf(
                '<li class="progress-item %s"><span class="item-title">%s</span><span class="item-status">%s (%.1f%%)</span></li>',
                $status_class,
                esc_html($quiz_title),
                $status_text,
                $attempt['score']
            );
        }
        $output .= '</ul>';
        
        return $output;
    }
    
    /**
     * Helper: Get user lesson progress
     */
    private function getUserLessonProgress($user_session, $limit): string {
        global $wpdb;
        
        $progress_table = $wpdb->prefix . 'elearning_lesson_progress';
        
        $lesson_progress = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                lesson_id,
                COUNT(*) as total_sections,
                COUNT(CASE WHEN completed = 1 THEN 1 END) as completed_sections
             FROM $progress_table 
             WHERE user_session = %s
             GROUP BY lesson_id
             ORDER BY MAX(updated_at) DESC
             LIMIT %d",
            $user_session,
            $limit
        ), ARRAY_A);
        
        if (empty($lesson_progress)) {
            return '<p>Δεν έχετε ξεκινήσει κανένα μάθημα ακόμα.</p>';
        }
        
        $output = '<ul class="progress-list">';
        foreach ($lesson_progress as $progress) {
            $lesson_title = get_the_title($progress['lesson_id']);
            $percentage = $progress['total_sections'] > 0 ? ($progress['completed_sections'] / $progress['total_sections']) * 100 : 0;
            $status_class = $percentage == 100 ? 'completed' : 'in-progress';
            
            $output .= sprintf(
                '<li class="progress-item %s"><span class="item-title">%s</span><span class="item-progress">%d%% (%d/%d)</span></li>',
                $status_class,
                esc_html($lesson_title),
                $percentage,
                $progress['completed_sections'],
                $progress['total_sections']
            );
        }
        $output .= '</ul>';
        
        return $output;
    }
}