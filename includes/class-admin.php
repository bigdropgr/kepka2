<?php
/**
 * Admin Class - ENHANCED VERSION
 * 
 * Handles the admin interface, dashboard, analytics, and settings
 * Includes master settings integration
 * Greek only version
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
        add_action('wp_ajax_elearning_cleanup_abandoned', [$this, 'handleAbandonedCleanup']);
        add_action('wp_ajax_elearning_export_questions', [$this, 'handleQuestionsExport']);
        add_filter('post_row_actions', [$this, 'addQuizPreviewAction'], 10, 2);
        add_filter('post_row_actions', [$this, 'addLessonPreviewAction'], 10, 2);
    }
    
    /**
     * Add admin menus
     */
    public function addAdminMenus(): void {
        // Main menu page
        add_menu_page(
            'Σύστημα E-Learning',
            'E-Learning',
            'view_elearning_analytics',
            'elearning-dashboard',
            [$this, 'renderDashboard'],
            'dashicons-welcome-learn-more',
            30
        );
        
        // Dashboard submenu (same as main page)
        add_submenu_page(
            'elearning-dashboard',
            'Πίνακας Ελέγχου',
            'Πίνακας Ελέγχου',
            'view_elearning_analytics',
            'elearning-dashboard',
            [$this, 'renderDashboard']
        );
        
        // Analytics submenu
        add_submenu_page(
            'elearning-dashboard',
            'Αναλυτικά Στοιχεία',
            'Αναλυτικά',
            'view_elearning_analytics',
            'elearning-analytics',
            [$this, 'renderAnalytics']
        );
        
        // Settings submenu (admin only)
        add_submenu_page(
            'elearning-dashboard',
            'Ρυθμίσεις',
            'Ρυθμίσεις',
            'manage_elearning_settings',
            'elearning-settings',
            [$this, 'renderSettings']
        );
        
        // Master Quiz Settings submenu - NEW
        add_submenu_page(
            'elearning-dashboard',
            'Προεπιλεγμένες Ρυθμίσεις Κουίζ',
            'Προεπιλογές Κουίζ',
            'manage_elearning_settings',
            'elearning-master-settings',
            [$this, 'renderMasterSettingsRedirect']
        );
        
        // Import/Export submenu
        add_submenu_page(
            'elearning-dashboard',
            'Εισαγωγή/Εξαγωγή',
            'Εισαγωγή/Εξαγωγή',
            'export_elearning_data',
            'elearning-import-export',
            [$this, 'renderImportExport']
        );
    }
    
    /**
     * Redirect to master settings (handled by ELearning_Master_Settings class)
     */
    public function renderMasterSettingsRedirect(): void {
        // This is handled by the ELearning_Master_Settings class
        // We just need this method to exist for the menu registration
        if (class_exists('ELearning_Master_Settings')) {
            $master_settings = new ELearning_Master_Settings();
            $master_settings->renderMasterSettingsPage();
        } else {
            echo '<div class="wrap"><h1>Προεπιλεγμένες Ρυθμίσεις Κουίζ</h1>';
            echo '<div class="notice notice-error"><p>Η κλάση Master Settings δεν βρέθηκε.</p></div>';
            echo '</div>';
        }
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
            'Γενικές Ρυθμίσεις',
            [$this, 'renderGeneralSettingsSection'],
            'elearning_quiz_settings'
        );
        
        // Data Retention Field
        add_settings_field(
            'data_retention_days',
            'Διατήρηση Δεδομένων (Ημέρες)',
            [$this, 'renderDataRetentionField'],
            'elearning_quiz_settings',
            'elearning_general_settings'
        );
        
        // Enable Progress Tracking
        add_settings_field(
            'enable_progress_tracking',
            'Ενεργοποίηση Παρακολούθησης Προόδου',
            [$this, 'renderProgressTrackingField'],
            'elearning_quiz_settings',
            'elearning_general_settings'
        );
        
        // Enable Quiz Retakes
        add_settings_field(
            'enable_quiz_retakes',
            'Επιτρέπονται Επαναλήψεις Κουίζ',
            [$this, 'renderQuizRetakesField'],
            'elearning_quiz_settings',
            'elearning_general_settings'
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitizeSettings($settings): array {
        $sanitized = [];
        
        $sanitized['data_retention_days'] = absint($settings['data_retention_days'] ?? 365);
        $sanitized['enable_progress_tracking'] = !empty($settings['enable_progress_tracking']);
        $sanitized['enable_quiz_retakes'] = !empty($settings['enable_quiz_retakes']);
        
        return $sanitized;
    }
    
    /**
     * Render dashboard page
     */
    public function renderDashboard(): void {
        $global_stats = ELearning_Database::getGlobalStatistics();
        ?>
        <div class="wrap">
            <h1>Πίνακας Ελέγχου Συστήματος E-Learning</h1>
            
            <div class="elearning-dashboard-widgets">
                <!-- Overview Stats -->
                <div class="elearning-stat-cards">
                    <div class="stat-card">
                        <h3>Συνολικές Προσπάθειες Κουίζ</h3>
                        <div class="stat-number"><?php echo number_format($global_stats['total_quiz_attempts'] ?? 0); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>Ολοκληρωμένα Κουίζ</h3>
                        <div class="stat-number"><?php echo number_format($global_stats['completed_quiz_attempts'] ?? 0); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>Επιτυχημένα Κουίζ</h3>
                        <div class="stat-number"><?php echo number_format($global_stats['passed_quiz_attempts'] ?? 0); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>Μοναδικοί Χρήστες</h3>
                        <div class="stat-number"><?php echo number_format($global_stats['unique_users'] ?? 0); ?></div>
                    </div>
                </div>
                
                <!-- Average Score -->
                <?php if (!empty($global_stats['global_average_score'])): ?>
                <div class="elearning-average-score">
                    <h2>Μέσος Όρος Βαθμολογίας</h2>
                    <div class="average-score-display">
                        <?php echo number_format($global_stats['global_average_score'], 1); ?>%
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Master Settings Quick Access -->
                <div class="elearning-master-settings-preview">
                    <h2>Προεπιλεγμένες Ρυθμίσεις Κουίζ</h2>
                    <?php
                    $master_settings = get_option('elearning_master_quiz_settings', []);
                    ?>
                    <div class="settings-grid">
                        <div class="setting-item">
                            <span class="setting-label">Βαθμός Επιτυχίας:</span>
                            <span class="setting-value"><?php echo ($master_settings['passing_score'] ?? 70); ?>%</span>
                        </div>
                        <div class="setting-item">
                            <span class="setting-label">Τυχαία Ερωτήσεις:</span>
                            <span class="setting-value"><?php echo ($master_settings['randomize_questions'] ?? 'no') === 'yes' ? 'Ναι' : 'Όχι'; ?></span>
                        </div>
                        <div class="setting-item">
                            <span class="setting-label">Τυχαίες Απαντήσεις:</span>
                            <span class="setting-value"><?php echo ($master_settings['randomize_answers'] ?? 'no') === 'yes' ? 'Ναι' : 'Όχι'; ?></span>
                        </div>
                        <div class="setting-item">
                            <span class="setting-label">Χρονικό Όριο:</span>
                            <span class="setting-value"><?php echo ($master_settings['time_limit'] ?? 0) ? ($master_settings['time_limit'] . ' λεπτά') : 'Χωρίς όριο'; ?></span>
                        </div>
                    </div>
                    <a href="<?php echo admin_url('admin.php?page=elearning-master-settings'); ?>" class="button">
                        Διαχείριση Προεπιλογών
                    </a>
                </div>
                
                <!-- Quick Actions -->
                <div class="elearning-quick-actions">
                    <h2>Γρήγορες Ενέργειες</h2>
                    <div class="quick-action-buttons">
                        <a href="<?php echo admin_url('post-new.php?post_type=elearning_lesson'); ?>" class="button button-primary">
                            Δημιουργία Νέου Μαθήματος
                        </a>
                        <a href="<?php echo admin_url('post-new.php?post_type=elearning_quiz'); ?>" class="button button-primary">
                            Δημιουργία Νέου Κουίζ
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=elearning-analytics'); ?>" class="button">
                            Προβολή Αναλυτικών
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=elearning-master-settings'); ?>" class="button">
                            Προεπιλογές Κουίζ
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=elearning-import-export'); ?>" class="button">
                            Εισαγωγή/Εξαγωγή
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
        
        .elearning-average-score,
        .elearning-quick-actions,
        .elearning-master-settings-preview {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .average-score-display {
            font-size: 48px;
            font-weight: bold;
            color: #0073aa;
            text-align: center;
            margin-top: 10px;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .setting-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background: #f0f0f1;
            border-radius: 4px;
        }
        
        .setting-label {
            font-weight: 600;
            color: #666;
        }
        
        .setting-value {
            color: #0073aa;
            font-weight: 600;
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
            <h1>Αναλυτικά Στοιχεία</h1>
            
            <!-- Quiz Selection -->
            <div class="elearning-analytics-filter">
                <form method="get" action="">
                    <input type="hidden" name="page" value="elearning-analytics">
                    <label for="quiz_id">Επιλογή Κουίζ:</label>
                    <select name="quiz_id" id="quiz_id" onchange="this.form.submit()">
                        <option value="">Όλα τα Κουίζ</option>
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
                    <h2><?php echo esc_html(get_the_title($selected_quiz)); ?> - Αναλυτικά</h2>
                    
                    <div class="elearning-stat-cards">
                        <div class="stat-card">
                            <h3>Συνολικές Προσπάθειες</h3>
                            <div class="stat-number"><?php echo number_format($selected_quiz_stats['total_attempts']); ?></div>
                        </div>
                        
                        <div class="stat-card">
                            <h3>Ολοκληρωμένες</h3>
                            <div class="stat-number"><?php echo number_format($selected_quiz_stats['completed_attempts']); ?></div>
                        </div>
                        
                        <div class="stat-card">
                            <h3>Επιτυχημένες</h3>
                            <div class="stat-number"><?php echo number_format($selected_quiz_stats['passed_attempts']); ?></div>
                        </div>
                        
                        <div class="stat-card">
                            <h3>Αποτυχημένες</h3>
                            <div class="stat-number"><?php echo number_format($selected_quiz_stats['failed_attempts']); ?></div>
                        </div>
                        
                        <div class="stat-card">
                            <h3>Μέσος Όρος</h3>
                            <div class="stat-number"><?php echo number_format($selected_quiz_stats['average_score'], 1); ?>%</div>
                        </div>
                        
                        <div class="stat-card">
                            <h3>Υψηλότερη Βαθμολογία</h3>
                            <div class="stat-number"><?php echo number_format($selected_quiz_stats['highest_score'], 1); ?>%</div>
                        </div>
                    </div>
                    
                    <!-- Export Button -->
                    <div class="elearning-export-section">
                        <h3>Εξαγωγή Δεδομένων</h3>
                        <button type="button" class="button button-primary" onclick="exportQuizData(<?php echo $selected_quiz; ?>)">
                            Εξαγωγή Δεδομένων Κουίζ σε CSV
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="notice notice-info">
                    <p>Επιλέξτε ένα κουίζ για να δείτε λεπτομερή στατιστικά.</p>
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
            <h1>Ρυθμίσεις E-Learning</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('elearning_quiz_settings');
                do_settings_sections('elearning_quiz_settings');
                submit_button();
                ?>
            </form>
            
            <!-- Quick Links -->
            <div class="elearning-settings-links">
                <h2>Γρήγοροι Σύνδεσμοι</h2>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=elearning-master-settings'); ?>" class="button">
                        Προεπιλεγμένες Ρυθμίσεις Κουίζ
                    </a>
                    <span class="description" style="margin-left: 10px;">Ορίστε προεπιλεγμένες ρυθμίσεις για όλα τα νέα κουίζ</span>
                </p>
            </div>
            
            <!-- Data Cleanup Section -->
            <div class="elearning-data-cleanup">
                <h2>Διαχείριση Δεδομένων</h2>
                <p>Καθαρισμός παλιών δεδομένων κουίζ και μαθημάτων βάσει των ρυθμίσεων διατήρησης.</p>
                <button type="button" class="button button-secondary" onclick="cleanupOldData()">
                    Καθαρισμός Παλιών Δεδομένων Τώρα
                </button>
                <div id="cleanup-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        
        <script>
        function cleanupOldData() {
            if (!confirm('Είστε σίγουροι ότι θέλετε να καθαρίσετε τα παλιά δεδομένα; Αυτή η ενέργεια δεν μπορεί να αναιρεθεί.')) {
                return;
            }
            
            const button = event.target;
            const resultDiv = document.getElementById('cleanup-result');
            
            button.disabled = true;
            button.textContent = 'Καθαρισμός...';
            
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
                button.textContent = 'Καθαρισμός Παλιών Δεδομένων Τώρα';
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="notice notice-error"><p>Παρουσιάστηκε σφάλμα κατά τον καθαρισμό.</p></div>';
                button.disabled = false;
                button.textContent = 'Καθαρισμός Παλιών Δεδομένων Τώρα';
            });
        }
        </script>
        
        <style>
        .elearning-settings-links {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 30px;
        }
        
        .elearning-data-cleanup {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 20px;
        }
        </style>
        <?php
    }
    
    /**
     * Render import/export page
     */
    public function renderImportExport(): void {
        ?>
        <div class="wrap">
            <h1>Εισαγωγή/Εξαγωγή</h1>
            
            <div class="elearning-import-export-sections">
                <!-- Export Section -->
                <div class="export-section">
                    <h2>Εξαγωγή Δεδομένων Κουίζ</h2>
                    <p>Εξαγωγή προσπαθειών και αποτελεσμάτων κουίζ σε μορφή CSV για ανάλυση.</p>
                    
                    <div class="export-form">
                        <label for="export-quiz-select">Επιλογή Κουίζ:</label>
                        <select id="export-quiz-select">
                            <option value="">Επιλέξτε ένα κουίζ...</option>
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
                                Εξαγωγή Προσπαθειών Κουίζ
                            </label>
                            <label>
                                <input type="radio" name="export_type" value="questions">
                                Εξαγωγή Ερωτήσεων Κουίζ
                            </label>
                        </div>
                        
                        <button type="button" class="button button-primary" onclick="exportSelectedQuiz()">
                            Εξαγωγή σε CSV
                        </button>
                    </div>
                </div>
                
                <!-- Import Section -->
                <div class="import-section">
                    <h2>Εισαγωγή Ερωτήσεων Κουίζ</h2>
                    <p>Εισαγωγή ερωτήσεων κουίζ από μορφή CSV.</p>
                    
                    <div class="import-info">
                        <h4>Απαιτήσεις Μορφής CSV:</h4>
                        <ul>
                            <li>Επικεφαλίδες στηλών (απαιτούμενες): Type, Question</li>
                            <li>Πολλαπλής Επιλογής: Option 1-5, Correct Answer(s)</li>
                            <li>Σωστό/Λάθος: Correct Answer (true/false) ή TF_Statement_1-5, TF_Answer_1-5</li>
                            <li>Συμπλήρωση Κενών: Text with Blanks (χρήση {{blank}}), Word Bank</li>
                            <li>Αντιστοίχιση: Left 1-5, Right 1-5, Matches (μορφή: 1-2,2-1)</li>
                        </ul>
                        
                        <a href="<?php echo ELEARNING_QUIZ_PLUGIN_URL; ?>templates/quiz-import-template.csv" download class="button button-secondary">
                            Λήψη Δείγματος CSV
                        </a>
                    </div>
                    
                    <div class="import-form">
                        <p>Για να εισάγετε ερωτήσεις, επεξεργαστείτε ένα κουίζ και χρησιμοποιήστε το κουμπί Εισαγωγή Ερωτήσεων.</p>
                        <a href="<?php echo admin_url('edit.php?post_type=elearning_quiz'); ?>" class="button">
                            Μετάβαση στα Κουίζ
                        </a>
                    </div>
                </div>
                
                <!-- Bulk Operations Section -->
                <div class="bulk-section">
                    <h2>Μαζικές Λειτουργίες</h2>
                    
                    <div class="bulk-export">
                        <h3>Εξαγωγή Όλων των Δεδομένων</h3>
                        <p>Εξαγωγή όλων των προσπαθειών κουίζ από όλα τα κουίζ.</p>
                        <button type="button" class="button" onclick="exportAllData()">
                            Εξαγωγή Όλων των Προσπαθειών
                        </button>
                    </div>
                    
                    <div class="bulk-cleanup">
                        <h3>Καθαρισμός Δεδομένων</h3>
                        <p>Αφαίρεση εγκαταλειμμένων προσπαθειών κουίζ παλαιότερων των 7 ημερών.</p>
                        <button type="button" class="button" onclick="cleanupAbandonedAttempts()">
                            Καθαρισμός Εγκαταλειμμένων Προσπαθειών
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
                alert('Παρακαλώ επιλέξτε ένα κουίζ για εξαγωγή.');
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
            if (!confirm('Αυτό θα εξάγει όλες τις προσπάθειες κουίζ. Συνέχεια;')) {
                return;
            }
            
            const url = ajaxurl + '?action=elearning_export_quiz_data&export_all=1&nonce=' + '<?php echo wp_create_nonce('elearning_export_nonce'); ?>';
            window.open(url, '_blank');
        }
        
        function cleanupAbandonedAttempts() {
            if (!confirm('Αυτό θα αφαιρέσει όλες τις εγκαταλειμμένες προσπάθειες παλαιότερες των 7 ημερών. Συνέχεια;')) {
                return;
            }
            
            jQuery.post(ajaxurl, {
                action: 'elearning_cleanup_abandoned',
                nonce: '<?php echo wp_create_nonce('elearning_cleanup_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data || 'Ο καθαρισμός απέτυχε');
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
        echo '<p>Διαμορφώστε τις γενικές ρυθμίσεις για το σύστημα e-learning.</p>';
    }
    
    public function renderDataRetentionField(): void {
        $settings = get_option('elearning_quiz_settings', []);
        $value = $settings['data_retention_days'] ?? 365;
        ?>
        <input type="number" name="elearning_quiz_settings[data_retention_days]" value="<?php echo esc_attr($value); ?>" min="0" />
        <p class="description">Αριθμός ημερών διατήρησης δεδομένων κουίζ και μαθημάτων. Ορίστε 0 για αόριστη διατήρηση.</p>
        <?php
    }
    
    public function renderProgressTrackingField(): void {
        $settings = get_option('elearning_quiz_settings', []);
        $value = !empty($settings['enable_progress_tracking']);
        ?>
        <label>
            <input type="checkbox" name="elearning_quiz_settings[enable_progress_tracking]" value="1" <?php checked($value); ?> />
            Ενεργοποίηση παρακολούθησης προόδου χρηστών
        </label>
        <?php
    }
    
    public function renderQuizRetakesField(): void {
        $settings = get_option('elearning_quiz_settings', []);
        $value = !empty($settings['enable_quiz_retakes']);
        ?>
        <label>
            <input type="checkbox" name="elearning_quiz_settings[enable_quiz_retakes]" value="1" <?php checked($value); ?> />
            Επιτρέπονται επαναλήψεις κουίζ από τους χρήστες
        </label>
        <?php
    }
    
    /**
     * Handle quiz data export
     */
    public function handleQuizDataExport(): void {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'elearning_export_nonce')) {
            wp_die('Ο έλεγχος ασφαλείας απέτυχε');
        }
        
        if (!current_user_can('export_elearning_data')) {
            wp_die('Δεν έχετε δικαίωμα εξαγωγής δεδομένων');
        }
        
        $export_all = isset($_GET['export_all']) && $_GET['export_all'] == '1';
        
        if ($export_all) {
            // Export all quiz data
            $csv_data = ELearning_Database::exportAllQuizData();
            $filename = 'all_quiz_attempts_export_' . date('Y-m-d') . '.csv';
        } else {
            $quiz_id = intval($_GET['quiz_id'] ?? 0);
            if (!$quiz_id) {
                wp_die('Μη έγκυρο ID κουίζ');
            }
            
            $csv_data = ELearning_Database::exportQuizData($quiz_id);
            $quiz_title = get_the_title($quiz_id);
            $filename = sanitize_file_name($quiz_title . '_export_' . date('Y-m-d') . '.csv');
        }
        
        if (empty($csv_data)) {
            wp_die('Δεν βρέθηκαν δεδομένα για εξαγωγή');
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Add UTF-8 BOM for Excel compatibility
        echo "\xEF\xBB\xBF";
        echo $csv_data;
        exit;
    }
    
    /**
     * Handle questions export
     */
    public function handleQuestionsExport(): void {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'elearning_export_nonce')) {
            wp_die('Ο έλεγχος ασφαλείας απέτυχε');
        }
        
        if (!current_user_can('export_elearning_data')) {
            wp_die('Δεν έχετε δικαίωμα εξαγωγής δεδομένων');
        }
        
        $quiz_id = intval($_GET['quiz_id'] ?? 0);
        if (!$quiz_id) {
            wp_die('Μη έγκυρο ID κουίζ');
        }
        
        $questions = get_post_meta($quiz_id, '_quiz_questions', true) ?: [];
        
        if (empty($questions)) {
            wp_die('Δεν βρέθηκαν ερωτήσεις για εξαγωγή');
        }
        
        // Generate CSV
        $csv_data = "Type,Question,Option 1,Option 2,Option 3,Option 4,Option 5,Correct Answer(s),Text with Blanks,Word Bank,";
        $csv_data .= "TF_Statement_1,TF_Answer_1,TF_Statement_2,TF_Answer_2,TF_Statement_3,TF_Answer_3,TF_Statement_4,TF_Answer_4,TF_Statement_5,TF_Answer_5,";
        $csv_data .= "Left 1,Left 2,Left 3,Left 4,Left 5,Right 1,Right 2,Right 3,Right 4,Right 5,Matches\n";
        
        foreach ($questions as $question) {
            $row = [];
            $row[] = $question['type'];
            $row[] = $question['question'];
            
            // Multiple choice options
            for ($i = 0; $i < 5; $i++) {
                $row[] = isset($question['options'][$i]) ? $question['options'][$i] : '';
            }
            
            // Correct answers
            if ($question['type'] === 'multiple_choice') {
                $correct = isset($question['correct_answers']) ? implode(';', $question['correct_answers']) : '';
                $row[] = $correct;
            } elseif ($question['type'] === 'true_false') {
                $row[] = isset($question['correct_answer']) ? $question['correct_answer'] : '';
            } else {
                $row[] = '';
            }
            
            // Fill blanks
            $row[] = isset($question['text_with_blanks']) ? $question['text_with_blanks'] : '';
            $row[] = isset($question['word_bank']) ? implode(';', $question['word_bank']) : '';
            
            // True/False statements (if multiple)
            for ($i = 0; $i < 5; $i++) {
                if (isset($question['tf_statements'][$i])) {
                    $row[] = $question['tf_statements'][$i]['text'];
                    $row[] = $question['tf_statements'][$i]['answer'];
                } else {
                    $row[] = '';
                    $row[] = '';
                }
            }
            
            // Matching columns
            for ($i = 0; $i < 5; $i++) {
                $row[] = isset($question['left_column'][$i]) ? $question['left_column'][$i] : '';
            }
            for ($i = 0; $i < 5; $i++) {
                $row[] = isset($question['right_column'][$i]) ? $question['right_column'][$i] : '';
            }
            
            // Matches
            if (isset($question['matches']) && is_array($question['matches'])) {
                $matches = [];
                foreach ($question['matches'] as $match) {
                    $matches[] = $match['left'] . '-' . $match['right'];
                }
                $row[] = implode(';', $matches);
            } else {
                $row[] = '';
            }
            
            // Convert to CSV line
            $csv_data .= '"' . implode('","', array_map('str_replace', ['"'], ['""'], $row)) . "\"\n";
        }
        
        $quiz_title = get_the_title($quiz_id);
        $filename = sanitize_file_name($quiz_title . '_questions_export_' . date('Y-m-d') . '.csv');
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Add UTF-8 BOM for Excel compatibility
        echo "\xEF\xBB\xBF";
        echo $csv_data;
        exit;
    }
    
    /**
     * Handle data cleanup
     */
    public function handleDataCleanup(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_cleanup_nonce')) {
            wp_send_json_error('Ο έλεγχος ασφαλείας απέτυχε');
        }
        
        if (!current_user_can('manage_elearning_settings')) {
            wp_send_json_error('Δεν έχετε δικαίωμα να εκτελέσετε αυτή την ενέργεια');
        }
        
        $settings = get_option('elearning_quiz_settings', []);
        $retention_days = $settings['data_retention_days'] ?? 365;
        
        if ($retention_days <= 0) {
            wp_send_json_error('Ο καθαρισμός δεδομένων είναι απενεργοποιημένος (αόριστη διατήρηση)');
        }
        
        $deleted = ELearning_Database::cleanupOldData($retention_days);
        
        wp_send_json_success([
            'message' => sprintf('Καθαρίστηκαν %d παλιές εγγραφές', $deleted)
        ]);
    }
    
    /**
     * Handle abandoned cleanup
     */
    public function handleAbandonedCleanup(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_cleanup_nonce')) {
            wp_send_json_error('Ο έλεγχος ασφαλείας απέτυχε');
        }
        
        if (!current_user_can('manage_elearning_settings')) {
            wp_send_json_error('Δεν έχετε δικαίωμα να εκτελέσετε αυτή την ενέργεια');
        }
        
        $deleted = ELearning_Database::cleanupAbandonedAttempts(7);
        
        wp_send_json_success([
            'message' => sprintf('Καθαρίστηκαν %d εγκαταλειμμένες προσπάθειες', $deleted)
        ]);
    }
    
    /**
     * Add quiz preview action
     */
    public function addQuizPreviewAction($actions, $post): array {
        if ($post->post_type === 'elearning_quiz') {
            $actions['preview'] = '<a href="' . get_permalink($post->ID) . '" target="_blank">Προεπισκόπηση</a>';
        }
        return $actions;
    }
    
    /**
     * Add lesson preview action
     */
    public function addLessonPreviewAction($actions, $post): array {
        if ($post->post_type === 'elearning_lesson') {
            $actions['preview'] = '<a href="' . get_permalink($post->ID) . '" target="_blank">Προεπισκόπηση</a>';
        }
        return $actions;
    }
    
    /**
     * Show admin notices
     */
    public function showAdminNotices(): void {
        // Check if plugin was just activated
        if (get_transient('elearning_quiz_activated')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Σύστημα E-Learning Quiz ενεργοποιήθηκε επιτυχώς!</strong></p>
                <p>Μπορείτε να ξεκινήσετε δημιουργώντας <a href="<?php echo admin_url('post-new.php?post_type=elearning_lesson'); ?>">μαθήματα</a> και <a href="<?php echo admin_url('post-new.php?post_type=elearning_quiz'); ?>">κουίζ</a>.</p>
            </div>
            <?php
            delete_transient('elearning_quiz_activated');
        }
    }
}