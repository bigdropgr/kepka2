<?php
/**
 * Admin Class
 * 
 * Handles the admin interface, dashboard, analytics, and settings
 * Greek Translation Ready Version
 */

if (!defined('ABSPATH')) {
    exit;
}

class ELearning_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'addAdminMenus']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_notices', [$this, 'showAdminNotices']);
        add_action('wp_ajax_elearning_export_quiz_data', [$this, 'handleQuizDataExport']);
        add_action('wp_ajax_elearning_cleanup_old_data', [$this, 'handleDataCleanup']);
        add_filter('post_row_actions', [$this, 'addQuizPreviewAction'], 10, 2);
        add_filter('post_row_actions', [$this, 'addLessonPreviewAction'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueEditorScripts']);
    }

    /**
     * Enqueue editor scripts for lesson pages
     */
    public function enqueueEditorScripts($hook): void {
        global $post_type;
        
        if (($hook == 'post.php' || $hook == 'post-new.php') && $post_type == 'elearning_lesson') {
            // Make sure WordPress editor scripts are loaded
            wp_enqueue_editor();
            wp_enqueue_media();
            
            // Add inline script to ensure wp.editor is available
            wp_add_inline_script('editor', '
                window.wpEditorL10n = window.wpEditorL10n || {
                    tinymce: {
                        baseURL: "' . includes_url('js/tinymce') . '",
                        suffix: ".min"
                    }
                };
            ');
        }
    }
    
    /**
     * Add admin menus
     */
    public function addAdminMenus(): void {
        // Main menu page
        add_menu_page(
            __('Σύστημα E-Learning', 'elearning-quiz'),
            __('E-Learning', 'elearning-quiz'),
            'view_elearning_analytics',
            'elearning-dashboard',
            [$this, 'renderDashboard'],
            'dashicons-welcome-learn-more',
            30
        );
        
        // Dashboard submenu (same as main page)
        add_submenu_page(
            'elearning-dashboard',
            __('Πίνακας Ελέγχου', 'elearning-quiz'),
            __('Πίνακας Ελέγχου', 'elearning-quiz'),
            'view_elearning_analytics',
            'elearning-dashboard',
            [$this, 'renderDashboard']
        );
        
        // Analytics submenu
        add_submenu_page(
            'elearning-dashboard',
            __('Αναλυτικά Στοιχεία', 'elearning-quiz'),
            __('Αναλυτικά Στοιχεία', 'elearning-quiz'),
            'view_elearning_analytics',
            'elearning-analytics',
            [$this, 'renderAnalytics']
        );
        
        // Settings submenu (admin only)
        add_submenu_page(
            'elearning-dashboard',
            __('Ρυθμίσεις', 'elearning-quiz'),
            __('Ρυθμίσεις', 'elearning-quiz'),
            'manage_elearning_settings',
            'elearning-settings',
            [$this, 'renderSettings']
        );
        
        // Import/Export submenu
        add_submenu_page(
            'elearning-dashboard',
            __('Εισαγωγή/Εξαγωγή', 'elearning-quiz'),
            __('Εισαγωγή/Εξαγωγή', 'elearning-quiz'),
            'export_elearning_data',
            'elearning-import-export',
            [$this, 'renderImportExport']
        );
    }
    
    /**
     * Register plugin settings
     */
    public function registerSettings(): void {
        register_setting('elearning_quiz_settings', 'elearning_quiz_settings', [
            'sanitize_callback' => [$this, 'sanitizeSettings']
        ]);
        
        // General Settings Section
        add_settings_section(
            'elearning_general_settings',
            __('Γενικές Ρυθμίσεις', 'elearning-quiz'),
            [$this, 'renderGeneralSettingsSection'],
            'elearning_quiz_settings'
        );
        
        // EXISTING FIELDS (keep these)
        // Data Retention Field
        add_settings_field(
            'data_retention_days',
            __('Διατήρηση Δεδομένων (Ημέρες)', 'elearning-quiz'),
            [$this, 'renderDataRetentionField'],
            'elearning_quiz_settings',
            'elearning_general_settings'
        );
        
        // NEW: Quiz Settings Section
        add_settings_section(
            'elearning_quiz_defaults',
            __('Προεπιλεγμένες Ρυθμίσεις Κουίζ', 'elearning-quiz'),
            [$this, 'renderQuizDefaultsSection'],
            'elearning_quiz_settings'
        );
        
        // Default Passing Score Field (moved from individual quiz)
        add_settings_field(
            'default_passing_score',
            __('Βαθμολογία Επιτυχίας (%)', 'elearning-quiz'),
            [$this, 'renderDefaultPassingScoreField'],
            'elearning_quiz_settings',
            'elearning_quiz_defaults'
        );
        
        // Questions Per Quiz Field (moved from individual quiz)
        add_settings_field(
            'questions_per_quiz',
            __('Ερωτήσεις Ανά Κουίζ', 'elearning-quiz'),
            [$this, 'renderQuestionsPerQuizField'],
            'elearning_quiz_settings',
            'elearning_quiz_defaults'
        );
        
        // Time Limit Field (NEW)
        add_settings_field(
            'default_time_limit',
            __('Χρονικό Όριο (λεπτά)', 'elearning-quiz'),
            [$this, 'renderDefaultTimeLimitField'],
            'elearning_quiz_settings',
            'elearning_quiz_defaults'
        );
        
        // Show Correct Answers Field (keep existing)
        add_settings_field(
            'show_correct_answers',
            __('Εμφάνιση Σωστών Απαντήσεων', 'elearning-quiz'),
            [$this, 'renderShowCorrectAnswersField'],
            'elearning_quiz_settings',
            'elearning_quiz_defaults'
        );
        
        // Enable Quiz Retakes Field (NEW)
        add_settings_field(
            'enable_quiz_retakes',
            __('Επανάληψη Κουίζ', 'elearning-quiz'),
            [$this, 'renderEnableQuizRetakesField'],
            'elearning_quiz_settings',
            'elearning_quiz_defaults'
        );
        
        // Enable Progress Tracking Field (NEW)
        add_settings_field(
            'enable_progress_tracking',
            __('Παρακολούθηση Προόδου', 'elearning-quiz'),
            [$this, 'renderEnableProgressTrackingField'],
            'elearning_quiz_settings',
            'elearning_quiz_defaults'
        );
    }

    
    /**
     * Sanitize settings
     */
    public function sanitizeSettings($settings): array {
        $sanitized = [];
        
        // Existing fields
        $sanitized['data_retention_days'] = absint($settings['data_retention_days'] ?? 365);
        // Quiz default settings
        $sanitized['default_passing_score'] = max(0, min(100, absint($settings['default_passing_score'] ?? 70)));
        $sanitized['questions_per_quiz'] = max(1, absint($settings['questions_per_quiz'] ?? 10));
        $sanitized['default_time_limit'] = max(0, absint($settings['default_time_limit'] ?? 0));
        $sanitized['show_correct_answers'] = !empty($settings['show_correct_answers']);
        $sanitized['enable_quiz_retakes'] = !empty($settings['enable_quiz_retakes']);
        $sanitized['enable_progress_tracking'] = !empty($settings['enable_progress_tracking']);
        
        return $sanitized;
    }
    
    /**
     * Render dashboard page
     */
    public function renderDashboard(): void {
        $global_stats = ELearning_Database::getGlobalStatistics();
        ?>
        <div class="wrap">
            <h1><?php _e('Πίνακας Ελέγχου Συστήματος E-Learning', 'elearning-quiz'); ?></h1>
            
            <div class="elearning-dashboard-widgets">
                <!-- Overview Stats -->
                <div class="elearning-stat-cards">
                    <div class="stat-card">
                        <h3><?php _e('Συνολικές Προσπάθειες Κουίζ', 'elearning-quiz'); ?></h3>
                        <div class="stat-number"><?php echo number_format($global_stats['total_quiz_attempts'] ?? 0); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php _e('Ολοκληρωμένα Κουίζ', 'elearning-quiz'); ?></h3>
                        <div class="stat-number"><?php echo number_format($global_stats['completed_quiz_attempts'] ?? 0); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php _e('Επιτυχημένα Κουίζ', 'elearning-quiz'); ?></h3>
                        <div class="stat-number"><?php echo number_format($global_stats['passed_quiz_attempts'] ?? 0); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php _e('Μοναδικοί Χρήστες', 'elearning-quiz'); ?></h3>
                        <div class="stat-number"><?php echo number_format($global_stats['unique_users'] ?? 0); ?></div>
                    </div>
                </div>
                
                <!-- Language Stats -->
                <div class="elearning-language-stats">
                    <h2><?php _e('Χρήση ανά Γλώσσα', 'elearning-quiz'); ?></h2>
                    <div class="language-stat-row">
                        <div class="language-stat">
                            <span class="language-label"><?php _e('Αγγλικά', 'elearning-quiz'); ?>:</span>
                            <span class="language-count"><?php echo number_format($global_stats['english_total'] ?? 0); ?></span>
                        </div>
                        <div class="language-stat">
                            <span class="language-label"><?php _e('Ελληνικά', 'elearning-quiz'); ?>:</span>
                            <span class="language-count"><?php echo number_format($global_stats['greek_total'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Average Score -->
                <?php if (!empty($global_stats['global_average_score'])): ?>
                <div class="elearning-average-score">
                    <h2><?php _e('Μέση Βαθμολογία', 'elearning-quiz'); ?></h2>
                    <div class="average-score-display">
                        <?php echo number_format($global_stats['global_average_score'], 1); ?>%
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Quick Actions -->
                <div class="elearning-quick-actions">
                    <h2><?php _e('Γρήγορες Ενέργειες', 'elearning-quiz'); ?></h2>
                    <div class="quick-action-buttons">
                        <a href="<?php echo admin_url('post-new.php?post_type=elearning_lesson'); ?>" class="button button-primary">
                            <?php _e('Δημιουργία Νέου Μαθήματος', 'elearning-quiz'); ?>
                        </a>
                        <a href="<?php echo admin_url('post-new.php?post_type=elearning_quiz'); ?>" class="button button-primary">
                            <?php _e('Δημιουργία Νέου Κουίζ', 'elearning-quiz'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=elearning-analytics'); ?>" class="button">
                            <?php _e('Προβολή Αναλυτικών', 'elearning-quiz'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=elearning-import-export'); ?>" class="button">
                            <?php _e('Εισαγωγή/Εξαγωγή', 'elearning-quiz'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .elearning-dashboard-widgets {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        
        .elearning-stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .elearning-language-stats,
        .elearning-average-score,
        .elearning-quick-actions {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .language-stat-row {
            display: flex;
            gap: 40px;
        }
        
        .language-stat {
            font-size: 16px;
        }
        
        .language-count {
            font-weight: bold;
            color: #0073aa;
        }
        
        .average-score-display {
            font-size: 48px;
            font-weight: bold;
            color: #0073aa;
            text-align: center;
            margin-top: 10px;
        }
        
        .quick-action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        </style>
        <?php
    }
    
    /**
     * Render analytics page
     */
    public function renderAnalytics(): void {
        // Get all quizzes for the dropdown
        $quizzes = get_posts([
            'post_type' => 'elearning_quiz',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        $selected_quiz = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;
        $selected_quiz_stats = [];
        
        if ($selected_quiz) {
            $selected_quiz_stats = ELearning_Database::getQuizStatistics($selected_quiz);
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Αναλυτικά Στοιχεία', 'elearning-quiz'); ?></h1>
            
            <!-- Quiz Selection -->
            <div class="elearning-analytics-filter">
                <form method="get" action="">
                    <input type="hidden" name="page" value="elearning-analytics">
                    <label for="quiz_id"><?php _e('Επιλογή Κουίζ:', 'elearning-quiz'); ?></label>
                    <select name="quiz_id" id="quiz_id" onchange="this.form.submit()">
                        <option value=""><?php _e('Όλα τα Κουίζ', 'elearning-quiz'); ?></option>
                        <?php foreach ($quizzes as $quiz): ?>
                            <option value="<?php echo $quiz->ID; ?>" <?php selected($selected_quiz, $quiz->ID); ?>>
                                <?php echo esc_html($quiz->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            
            <?php if ($selected_quiz && !empty($selected_quiz_stats)): ?>
                <!-- Quiz-specific analytics -->
                <div class="elearning-quiz-analytics">
                    <h2><?php echo esc_html(get_the_title($selected_quiz)); ?> - <?php _e('Αναλυτικά Στοιχεία', 'elearning-quiz'); ?></h2>
                    
                    <div class="elearning-stat-cards">
                        <div class="stat-card">
                            <h3><?php _e('Συνολικές Προσπάθειες', 'elearning-quiz'); ?></h3>
                            <div class="stat-number"><?php echo number_format($selected_quiz_stats['total_attempts']); ?></div>
                        </div>
                        
                        <div class="stat-card">
                            <h3><?php _e('Ολοκληρωμένες', 'elearning-quiz'); ?></h3>
                            <div class="stat-number"><?php echo number_format($selected_quiz_stats['completed_attempts']); ?></div>
                        </div>
                        
                        <div class="stat-card">
                            <h3><?php _e('Επιτυχημένες', 'elearning-quiz'); ?></h3>
                            <div class="stat-number"><?php echo number_format($selected_quiz_stats['passed_attempts']); ?></div>
                        </div>
                        
                        <div class="stat-card">
                            <h3><?php _e('Αποτυχημένες', 'elearning-quiz'); ?></h3>
                            <div class="stat-number"><?php echo number_format($selected_quiz_stats['failed_attempts']); ?></div>
                        </div>
                        
                        <div class="stat-card">
                            <h3><?php _e('Μέση Βαθμολογία', 'elearning-quiz'); ?></h3>
                            <div class="stat-number"><?php echo number_format($selected_quiz_stats['average_score'], 1); ?>%</div>
                        </div>
                        
                        <div class="stat-card">
                            <h3><?php _e('Υψηλότερη Βαθμολογία', 'elearning-quiz'); ?></h3>
                            <div class="stat-number"><?php echo number_format($selected_quiz_stats['highest_score'], 1); ?>%</div>
                        </div>
                    </div>
                    
                    <!-- Language breakdown -->
                    <div class="elearning-language-breakdown">
                        <h3><?php _e('Ανάλυση ανά Γλώσσα', 'elearning-quiz'); ?></h3>
                        <div class="language-stat-row">
                            <div class="language-stat">
                                <span class="language-label"><?php _e('Προσπάθειες στα Αγγλικά', 'elearning-quiz'); ?>:</span>
                                <span class="language-count"><?php echo number_format($selected_quiz_stats['english_attempts']); ?></span>
                            </div>
                            <div class="language-stat">
                                <span class="language-label"><?php _e('Προσπάθειες στα Ελληνικά', 'elearning-quiz'); ?>:</span>
                                <span class="language-count"><?php echo number_format($selected_quiz_stats['greek_attempts']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Export Button -->
                    <div class="elearning-export-section">
                        <h3><?php _e('Εξαγωγή Δεδομένων', 'elearning-quiz'); ?></h3>
                        <button type="button" class="button button-primary" onclick="exportQuizData(<?php echo $selected_quiz; ?>)">
                            <?php _e('Εξαγωγή Δεδομένων Κουίζ σε CSV', 'elearning-quiz'); ?>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php _e('Επιλέξτε ένα κουίζ για να δείτε λεπτομερή αναλυτικά στοιχεία.', 'elearning-quiz'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        function exportQuizData(quizId) {
            const url = ajaxurl + '?action=elearning_export_quiz_data&quiz_id=' + quizId + '&nonce=' + '<?php echo wp_create_nonce('elearning_export_nonce'); ?>';
            window.open(url, '_blank');
        }
        </script>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function renderSettings(): void {
        ?>
        <div class="wrap">
            <h1><?php _e('Ρυθμίσεις E-Learning', 'elearning-quiz'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('elearning_quiz_settings');
                do_settings_sections('elearning_quiz_settings');
                submit_button();
                ?>
            </form>
            
            <!-- Data Cleanup Section -->
            <div class="elearning-data-cleanup">
                <h2><?php _e('Διαχείριση Δεδομένων', 'elearning-quiz'); ?></h2>
                <p><?php _e('Καθαρίστε παλιά δεδομένα κουίζ και μαθημάτων βάσει των ρυθμίσεων διατήρησης.', 'elearning-quiz'); ?></p>
                <button type="button" class="button button-secondary" onclick="cleanupOldData()">
                    <?php _e('Καθαρισμός Παλιών Δεδομένων', 'elearning-quiz'); ?>
                </button>
                <div id="cleanup-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <script>
        function cleanupOldData() {
            if (!confirm('<?php _e('Είστε σίγουροι ότι θέλετε να καθαρίσετε τα παλιά δεδομένα; Αυτή η ενέργεια δεν μπορεί να αναιρεθεί.', 'elearning-quiz'); ?>')) {
                return;
            }
            
            const button = event.target;
            const resultDiv = document.getElementById('cleanup-result');
            
            button.disabled = true;
            button.textContent = '<?php _e('Καθαρισμός...', 'elearning-quiz'); ?>';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=elearning_cleanup_old_data&nonce=<?php echo wp_create_nonce('elearning_cleanup_nonce'); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<div class="notice notice-success"><p>' + data.data.message + '</p></div>';
                } else {
                    resultDiv.innerHTML = '<div class="notice notice-error"><p>' + data.data + '</p></div>';
                }
                button.disabled = false;
                button.textContent = '<?php _e('Καθαρισμός Παλιών Δεδομένων', 'elearning-quiz'); ?>';
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="notice notice-error"><p><?php _e('Παρουσιάστηκε σφάλμα κατά τον καθαρισμό.', 'elearning-quiz'); ?></p></div>';
                button.disabled = false;
                button.textContent = '<?php _e('Καθαρισμός Παλιών Δεδομένων', 'elearning-quiz'); ?>';
            });
        }
        </script>
        <?php
    }
    
    /**
     * Render import/export page
     */
    public function renderImportExport(): void {
        ?>
        <div class="wrap">
            <h1><?php _e('Εισαγωγή/Εξαγωγή', 'elearning-quiz'); ?></h1>
            
            <div class="elearning-import-export-sections">
                <!-- Export Section -->
                <div class="export-section">
                    <h2><?php _e('Εξαγωγή Δεδομένων Κουίζ', 'elearning-quiz'); ?></h2>
                    <p><?php _e('Εξαγωγή προσπαθειών και αποτελεσμάτων κουίζ σε μορφή CSV για ανάλυση.', 'elearning-quiz'); ?></p>
                    
                    <div class="export-form">
                        <label for="export-quiz-select"><?php _e('Επιλογή Κουίζ:', 'elearning-quiz'); ?></label>
                        <select id="export-quiz-select">
                            <option value=""><?php _e('Επιλέξτε ένα κουίζ...', 'elearning-quiz'); ?></option>
                            <?php
                            $quizzes = get_posts([
                                'post_type' => 'elearning_quiz',
                                'posts_per_page' => -1,
                                'post_status' => 'publish',
                                'orderby' => 'title',
                                'order' => 'ASC'
                            ]);
                            foreach ($quizzes as $quiz):
                            ?>
                                <option value="<?php echo $quiz->ID; ?>"><?php echo esc_html($quiz->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div class="export-options">
                            <label>
                                <input type="radio" name="export_type" value="attempts" checked>
                                <?php _e('Εξαγωγή Προσπαθειών Κουίζ', 'elearning-quiz'); ?>
                            </label>
                            <label>
                                <input type="radio" name="export_type" value="questions">
                                <?php _e('Εξαγωγή Ερωτήσεων Κουίζ', 'elearning-quiz'); ?>
                            </label>
                        </div>
                        
                        <button type="button" class="button button-primary" onclick="exportSelectedQuiz()">
                            <?php _e('Εξαγωγή σε CSV', 'elearning-quiz'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Import Section -->
                <div class="import-section">
                    <h2><?php _e('Εισαγωγή Ερωτήσεων Κουίζ', 'elearning-quiz'); ?></h2>
                    <p><?php _e('Εισαγωγή ερωτήσεων κουίζ από μορφή CSV.', 'elearning-quiz'); ?></p>
                    
                    <div class="import-info">
                        <h4><?php _e('Απαιτήσεις Μορφής CSV:', 'elearning-quiz'); ?></h4>
                        <ul>
                            <li><?php _e('Επικεφαλίδες στηλών (απαραίτητες): Type, Question', 'elearning-quiz'); ?></li>
                            <li><?php _e('Πολλαπλής Επιλογής: Option 1-5, Correct Answer(s)', 'elearning-quiz'); ?></li>
                            <li><?php _e('Σωστό/Λάθος: Correct Answer (true/false)', 'elearning-quiz'); ?></li>
                            <li><?php _e('Συμπλήρωση Κενών: Text with Blanks (χρήση {{blank}}), Word Bank', 'elearning-quiz'); ?></li>
                            <li><?php _e('Αντιστοίχιση: Left 1-5, Right 1-5, Matches (μορφή: 1-2,2-1)', 'elearning-quiz'); ?></li>
                        </ul>
                        
                        <a href="<?php echo ELEARNING_QUIZ_PLUGIN_URL; ?>templates/quiz-import-template.csv" download class="button button-secondary">
                            <?php _e('Λήψη Δείγματος CSV', 'elearning-quiz'); ?>
                        </a>
                    </div>
                    
                    <div class="import-form">
                        <p><?php _e('Για να εισάγετε ερωτήσεις, επεξεργαστείτε ένα κουίζ και χρησιμοποιήστε το κουμπί Εισαγωγή Ερωτήσεων.', 'elearning-quiz'); ?></p>
                        <a href="<?php echo admin_url('edit.php?post_type=elearning_quiz'); ?>" class="button">
                            <?php _e('Μετάβαση στα Κουίζ', 'elearning-quiz'); ?>
                        </a>
                    </div>
                </div>
                
                <!-- Bulk Operations Section -->
                <div class="bulk-section">
                    <h2><?php _e('Μαζικές Λειτουργίες', 'elearning-quiz'); ?></h2>
                    
                    <div class="bulk-export">
                        <h3><?php _e('Εξαγωγή Όλων των Δεδομένων', 'elearning-quiz'); ?></h3>
                        <p><?php _e('Εξαγωγή όλων των προσπαθειών κουίζ από όλα τα κουίζ.', 'elearning-quiz'); ?></p>
                        <button type="button" class="button" onclick="exportAllData()">
                            <?php _e('Εξαγωγή Όλων των Προσπαθειών', 'elearning-quiz'); ?>
                        </button>
                    </div>
                    
                    <div class="bulk-cleanup">
                        <h3><?php _e('Καθαρισμός Δεδομένων', 'elearning-quiz'); ?></h3>
                        <p><?php _e('Αφαίρεση εγκαταλειμμένων προσπαθειών κουίζ παλαιότερων των 7 ημερών.', 'elearning-quiz'); ?></p>
                        <button type="button" class="button" onclick="cleanupAbandonedAttempts()">
                            <?php _e('Καθαρισμός Εγκαταλειμμένων Προσπαθειών', 'elearning-quiz'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function exportSelectedQuiz() {
            const select = document.getElementById('export-quiz-select');
            const quizId = select.value;
            const exportType = document.querySelector('input[name="export_type"]:checked').value;
            
            if (!quizId) {
                alert('<?php _e('Παρακαλώ επιλέξτε ένα κουίζ για εξαγωγή.', 'elearning-quiz'); ?>');
                return;
            }
            
            let url;
            if (exportType === 'questions') {
                url = ajaxurl + '?action=elearning_export_questions&quiz_id=' + quizId + '&nonce=' + '<?php echo wp_create_nonce('elearning_export_nonce'); ?>';
            } else {
                url = ajaxurl + '?action=elearning_export_quiz_data&quiz_id=' + quizId + '&nonce=' + '<?php echo wp_create_nonce('elearning_export_nonce'); ?>';
            }
            
            window.open(url, '_blank');
        }
        
        function exportAllData() {
            if (!confirm('<?php _e('Αυτό θα εξάγει όλες τις προσπάθειες κουίζ. Συνέχεια;', 'elearning-quiz'); ?>')) {
                return;
            }
            
            const url = ajaxurl + '?action=elearning_export_quiz_data&export_all=1&nonce=' + '<?php echo wp_create_nonce('elearning_export_nonce'); ?>';
            window.open(url, '_blank');
        }
        
        function cleanupAbandonedAttempts() {
            if (!confirm('<?php _e('Αυτό θα αφαιρέσει όλες τις εγκαταλειμμένες προσπάθειες παλαιότερες των 7 ημερών. Συνέχεια;', 'elearning-quiz'); ?>')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'elearning_cleanup_abandoned',
                nonce: '<?php echo wp_create_nonce('elearning_cleanup_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data || '<?php _e('Ο καθαρισμός απέτυχε', 'elearning-quiz'); ?>');
                }
            });
        }
        </script>
        
        <style>
        .elearning-import-export-sections {
            display: grid;
            gap: 30px;
            margin-top: 20px;
        }
        
        .export-section,
        .import-section,
        .bulk-section {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .export-form,
        .import-form {
            margin-top: 15px;
        }
        
        .export-form select,
        .import-form input[type="file"] {
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .export-options {
            margin: 15px 0;
        }
        
        .export-options label {
            display: block;
            margin-bottom: 5px;
        }
        
        .import-info {
            background: #f0f0f1;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
        
        .import-info h4 {
            margin-top: 0;
        }
        
        .import-info ul {
            margin-bottom: 15px;
        }
        
        .bulk-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .bulk-export,
        .bulk-cleanup {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .bulk-export h3,
        .bulk-cleanup h3 {
            margin-top: 0;
        }
        </style>
        <?php
    }
    
    /**
     * Render settings sections and fields
     */
    public function renderGeneralSettingsSection(): void {
        echo '<p>' . __('Διαμορφώστε τις γενικές ρυθμίσεις για το σύστημα e-learning.', 'elearning-quiz') . '</p>';
    }
    
    public function renderDataRetentionField(): void {
        $settings = get_option('elearning_quiz_settings', []);
        $value = $settings['data_retention_days'] ?? 365;
        ?>
        <input type="number" name="elearning_quiz_settings[data_retention_days]" value="<?php echo esc_attr($value); ?>" min="0" />
        <p class="description"><?php _e('Αριθμός ημερών διατήρησης δεδομένων κουίζ και μαθημάτων. Ορίστε 0 για επ\' αόριστον διατήρηση.', 'elearning-quiz'); ?></p>
        <?php
    }
    
    public function renderDefaultPassingScoreField(): void {
        $settings = get_option('elearning_quiz_settings', []);
        $value = $settings['default_passing_score'] ?? 70;
        ?>
        <input type="number" name="elearning_quiz_settings[default_passing_score]" value="<?php echo esc_attr($value); ?>" min="0" max="100" />
        <p class="description"><?php _e('Προεπιλεγμένο ποσοστό βαθμολογίας επιτυχίας για νέα κουίζ.', 'elearning-quiz'); ?></p>
        <?php
    }
    
    public function renderQuestionsPerQuizField(): void {
        $settings = get_option('elearning_quiz_settings', []);
        $value = $settings['questions_per_quiz'] ?? 10;
        ?>
        <input type="number" name="elearning_quiz_settings[questions_per_quiz]" value="<?php echo esc_attr($value); ?>" min="1" />
        <p class="description"><?php _e('Προεπιλεγμένος αριθμός ερωτήσεων που θα εμφανίζονται ανά κουίζ.', 'elearning-quiz'); ?></p>
        <?php
    }
    
    public function renderShowCorrectAnswersField(): void {
        $settings = get_option('elearning_quiz_settings', []);
        $value = !empty($settings['show_correct_answers']);
        ?>
        <label>
            <input type="checkbox" name="elearning_quiz_settings[show_correct_answers]" value="1" <?php checked($value); ?> />
            <?php _e('Εμφάνιση σωστών απαντήσεων μετά την ολοκλήρωση του κουίζ', 'elearning-quiz'); ?>
        </label>
        <?php
    }
    

    /**
     * Render quiz master settings sections and fields
     */
    public function renderQuizDefaultsSection(): void {
        echo '<p>' . __('Αυτές οι ρυθμίσεις θα εφαρμόζονται σε όλα τα κουίζ. Οι μεμονωμένες ρυθμίσεις κουίζ έχουν καταργηθεί.', 'elearning-quiz') . '</p>';
    }

    public function renderDefaultTimeLimitField(): void {
        $settings = get_option('elearning_quiz_settings', []);
        $value = $settings['default_time_limit'] ?? 0;
        ?>
        <input type="number" name="elearning_quiz_settings[default_time_limit]" value="<?php echo esc_attr($value); ?>" min="0" />
        <p class="description"><?php _e('Χρονικό όριο σε λεπτά για όλα τα κουίζ. Ορίστε 0 για κανένα όριο.', 'elearning-quiz'); ?></p>
        <?php
    }

    public function renderEnableQuizRetakesField(): void {
        $settings = get_option('elearning_quiz_settings', []);
        $value = !empty($settings['enable_quiz_retakes']);
        ?>
        <label>
            <input type="checkbox" name="elearning_quiz_settings[enable_quiz_retakes]" value="1" <?php checked($value); ?> />
            <?php _e('Επιτρέψτε στους χρήστες να επαναλάβουν τα κουίζ', 'elearning-quiz'); ?>
        </label>
        <?php
    }

    public function renderEnableProgressTrackingField(): void {
        $settings = get_option('elearning_quiz_settings', []);
        $value = !empty($settings['enable_progress_tracking']);
        ?>
        <label>
            <input type="checkbox" name="elearning_quiz_settings[enable_progress_tracking]" value="1" <?php checked($value); ?> />
            <?php _e('Ενεργοποίηση παρακολούθησης προόδου για μαθήματα και κουίζ', 'elearning-quiz'); ?>
        </label>
        <?php
    }

    /**
     * Handle quiz data export
     */
    public function handleQuizDataExport(): void {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'elearning_export_nonce')) {
            wp_die(__('Αποτυχία ελέγχου ασφαλείας', 'elearning-quiz'));
        }
        
        if (!current_user_can('export_elearning_data')) {
            wp_die(__('Δεν έχετε άδεια να εξάγετε δεδομένα', 'elearning-quiz'));
        }
        
        $quiz_id = intval($_GET['quiz_id'] ?? 0);
        if (!$quiz_id) {
            wp_die(__('Μη έγκυρο ID κουίζ', 'elearning-quiz'));
        }
        
        $csv_data = ELearning_Database::exportQuizData($quiz_id);
        
        if (empty($csv_data)) {
            wp_die(__('Δεν βρέθηκαν δεδομένα για αυτό το κουίζ', 'elearning-quiz'));
        }
        
        $quiz_title = get_the_title($quiz_id);
        $filename = sanitize_file_name($quiz_title . '_export_' . date('Y-m-d') . '.csv');
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $csv_data;
        exit;
    }
    
    /**
     * Handle data cleanup
     */
    public function handleDataCleanup(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_cleanup_nonce')) {
            wp_send_json_error(__('Αποτυχία ελέγχου ασφαλείας', 'elearning-quiz'));
        }
        
        if (!current_user_can('manage_elearning_settings')) {
            wp_send_json_error(__('Δεν έχετε άδεια να καθαρίσετε δεδομένα', 'elearning-quiz'));
        }
        
        $deleted_count = ELearning_Database::cleanupOldData();
        
        wp_send_json_success([
            'message' => sprintf(__('Καθαρίστηκαν επιτυχώς %d παλιές εγγραφές.', 'elearning-quiz'), $deleted_count)
        ]);
    }
    
    /**
     * Add quiz preview action
     */
    public function addQuizPreviewAction($actions, $post): array {
        if ($post->post_type === 'elearning_quiz' && $post->post_status === 'publish') {
            $preview_url = get_permalink($post->ID);
            $actions['quiz_preview'] = '<a href="' . esc_url($preview_url) . '" target="_blank">' . __('Προεπισκόπηση Κουίζ', 'elearning-quiz') . '</a>';
        }
        return $actions;
    }
    
    /**
     * Add lesson preview action
     */
    public function addLessonPreviewAction($actions, $post): array {
        if ($post->post_type === 'elearning_lesson' && $post->post_status === 'publish') {
            $preview_url = get_permalink($post->ID);
            $actions['lesson_preview'] = '<a href="' . esc_url($preview_url) . '" target="_blank">' . __('Προεπισκόπηση Μαθήματος', 'elearning-quiz') . '</a>';
        }
        return $actions;
    }
    
    /**
     * Show admin notices
     */
    public function showAdminNotices(): void {
        // Check if database tables exist
        global $wpdb;
        $table_name = $wpdb->prefix . 'elearning_quiz_attempts';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            echo '<div class="notice notice-error"><p>';
            echo __('Σύστημα E-Learning Κουίζ: Οι πίνακες βάσης δεδομένων λείπουν. Παρακαλώ απενεργοποιήστε και ενεργοποιήστε ξανά το πρόσθετο.', 'elearning-quiz');
            echo '</p></div>';
        }
    }
}