<?php
/**
 * Plugin Name: Σύστημα E-Learning Quiz για KEPKA
 * Plugin URI: https://BigDrop.gr
 * Description: Ένα ολοκληρωμένο σύστημα e-learning με μαθήματα, κουίζ και αναλυτικά στοιχεία για WordPress.
 * Version: 1.2.0
 * Author: BigDrop
 * Author URI: https://bigdrop.gr
 * Text Domain: elearning-quiz
 * Requires at least: 6.0
 * Tested up to: 6.6
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ELEARNING_QUIZ_VERSION', '1.2.0');
define('ELEARNING_QUIZ_PLUGIN_FILE', __FILE__);
define('ELEARNING_QUIZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ELEARNING_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ELEARNING_QUIZ_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check PHP version
if (version_compare(PHP_VERSION, '8.0', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>';
        echo sprintf(
            'Το Σύστημα E-Learning Quiz απαιτεί PHP 8.0 ή νεότερη έκδοση. Εκτελείτε PHP %s.',
            PHP_VERSION
        );
        echo '</p></div>';
    });
    return;
}

/**
 * Main plugin class
 */
class ELearningQuizSystem {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init(): void {
        // Load plugin dependencies
        $this->loadDependencies();
        
        // Initialize hooks
        $this->initHooks();
        
        // Initialize components
        $this->initComponents();
    }
    
    /**
     * Load plugin dependencies
     */
    private function loadDependencies(): void {
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-post-types.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-database.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-admin.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-ajax.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-user-roles.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-analytics.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-shortcodes.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-import-export.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-woodmart-integration.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-lesson-widget.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-quiz-widget.php';
        require_once ELEARNING_QUIZ_PLUGIN_DIR . 'includes/class-master-settings.php';
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void {
        register_activation_hook(ELEARNING_QUIZ_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(ELEARNING_QUIZ_PLUGIN_FILE, [$this, 'deactivate']);
        register_uninstall_hook(ELEARNING_QUIZ_PLUGIN_FILE, [__CLASS__, 'uninstall']);
        
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
    }
    
    /**
     * Initialize plugin components
     */
    private function initComponents(): void {
        // Initialize post types
        new ELearning_Post_Types();
        
        // Initialize database
        new ELearning_Database();
        
        // Initialize admin interface
        if (is_admin()) {
            new ELearning_Admin();
        }
        
        // Initialize frontend
        if (!is_admin()) {
            new ELearning_Frontend();
        }
        
        // Initialize AJAX handlers (both frontend and admin)
        new ELearning_Ajax();
        
        // Initialize user roles
        new ELearning_User_Roles();
        
        // Initialize analytics
        new ELearning_Analytics();
        
        // Initialize shortcodes
        new ELearning_Shortcodes();
        
        // Initialize import/export
        new ELearning_Import_Export();
        
        // Initialize master settings
        new ELearning_Master_Settings();
        
        // Schedule cleanup cron job
        if (!wp_next_scheduled('elearning_cleanup_abandoned_quizzes')) {
            wp_schedule_event(time(), 'hourly', 'elearning_cleanup_abandoned_quizzes');
        }
        
        add_action('elearning_cleanup_abandoned_quizzes', [ELearning_Database::class, 'trackQuizAbandonment']);
    }
    
    /**
     * Plugin activation
     */
    public function activate(): void {
        // Create database tables
        ELearning_Database::createTables();
        
        // Add user roles and capabilities
        ELearning_User_Roles::addRoles();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set plugin version
        update_option('elearning_quiz_version', ELEARNING_QUIZ_VERSION);
        
        // Set default settings
        $this->setDefaultSettings();
        
        // Set default master quiz settings
        $this->setDefaultMasterSettings();
    }
    
    /**
     * Set default plugin settings
     */
    private function setDefaultSettings(): void {
        $default_settings = [
            'data_retention_days' => 365,
            'enable_progress_tracking' => true,
            'default_passing_score' => 70,
            'enable_quiz_retakes' => true,
            'questions_per_quiz' => 10,
            'show_correct_answers' => true,
            'cookie_consent_integration' => false
        ];
        
        add_option('elearning_quiz_settings', $default_settings);
    }
    
    /**
     * Set default master quiz settings
     */
    private function setDefaultMasterSettings(): void {
        $default_master_settings = [
            'passing_score' => 70,
            'questions_to_show' => 0, // 0 means show all
            'time_limit' => 0, // 0 means no limit
            'show_results' => 'yes',
            'randomize_questions' => 'no',
            'randomize_answers' => 'no',
            'enable_retakes' => 'yes',
            'max_attempts' => 0, // 0 means unlimited
            'auto_apply_to_new' => true
        ];
        
        add_option('elearning_master_quiz_settings', $default_master_settings);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
        // Clear scheduled events
        wp_clear_scheduled_hook('elearning_cleanup_abandoned_quizzes');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall(): void {
        // Remove database tables
        ELearning_Database::dropTables();
        
        // Remove user roles
        ELearning_User_Roles::removeRoles();
        
        // Remove plugin options
        delete_option('elearning_quiz_version');
        delete_option('elearning_quiz_settings');
        delete_option('elearning_quiz_db_version');
        delete_option('elearning_master_quiz_settings');
        
        // Clean up any transients
        delete_transient('elearning_quiz_cache');
        delete_transient('global_quiz_stats');
        
        // Clear all quiz stats transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_quiz_stats_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_quiz_stats_%'");
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueueScripts(): void {
        // Only enqueue on relevant pages
        if (!is_singular(['elearning_lesson', 'elearning_quiz']) && !has_shortcode(get_post()->post_content ?? '', 'loan_calculator')) {
            return;
        }
        
        $css_version = $this->getFileVersion('assets/css/frontend.css');
        $js_version = $this->getFileVersion('assets/js/frontend.js');
        
        // Check if WoodMart is active
        $theme = wp_get_theme();
        $is_woodmart = $theme->get('Name') === 'WoodMart' || $theme->get('Template') === 'woodmart';
        
        if ($is_woodmart) {
            // Use the WoodMart integrated CSS file
            wp_enqueue_style(
                'elearning-quiz-frontend',
                ELEARNING_QUIZ_PLUGIN_URL . 'assets/css/woodmart-integrated-frontend.css',
                ['woodmart-style'], // Make it dependent on WoodMart's main style
                $css_version
            );
        } else {
            // Use the standard CSS file for other themes
            wp_enqueue_style(
                'elearning-quiz-frontend',
                ELEARNING_QUIZ_PLUGIN_URL . 'assets/css/frontend.css',
                [],
                $css_version
            );
        }
        
        // Main frontend script with dependencies for drag & drop
        wp_enqueue_script(
            'elearning-quiz-frontend',
            ELEARNING_QUIZ_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'],
            $js_version,
            true
        );
        
        // Localize script for AJAX and strings - ALL IN GREEK
        wp_localize_script('elearning-quiz-frontend', 'elearningQuiz', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('elearning_quiz_nonce'),
            'strings' => [
                'loading' => 'Φόρτωση...',
                'error' => 'Παρουσιάστηκε σφάλμα. Παρακαλώ δοκιμάστε ξανά.',
                'confirm_submit' => 'Είστε σίγουροι ότι θέλετε να υποβάλετε τις απαντήσεις σας;',
                'congratulations' => 'Συγχαρητήρια!',
                'quiz_passed' => 'Ολοκληρώσατε επιτυχώς το κουίζ.',
                'try_again' => 'Δοκιμάστε Ξανά',
                'quiz_failed' => 'Δεν περάσατε το κουίζ. Παρακαλώ μελετήστε το υλικό και δοκιμάστε ξανά.',
                'correct_answers' => 'Σωστές Απαντήσεις',
                'passing_score' => 'Βαθμός Επιτυχίας',
                'retry_quiz' => 'Επανάληψη Κουίζ',
                'next_section' => 'Επόμενη Ενότητα',
                'previous_section' => 'Προηγούμενη Ενότητα',
                'mark_complete' => 'Ολοκλήρωση Ενότητας',
                'section_completed' => 'Η ενότητα ολοκληρώθηκε!',
                'time_remaining' => 'Υπολειπόμενος Χρόνος',
                'time_up' => 'Ο Χρόνος Τελείωσε!',
                'submitting_quiz' => 'Το κουίζ σας υποβάλλεται...',
                'one_minute_warning' => 'Απομένει ένα λεπτό',
                'unanswered_questions' => 'Έχετε αναπάντητες ερωτήσεις',
                'submit_anyway' => 'Υποβολή ούτως ή άλλως;',
                'your_answer' => 'Η Απάντησή σας',
                'correct_answer' => 'Σωστή Απάντηση',
                'no_answer' => 'Δεν δόθηκε απάντηση',
                'points_earned' => 'Πόντοι που κερδίσατε: %s από %s',
                'review_answers' => 'Ανασκόπηση Απαντήσεων',
                'perfect_score' => 'Συγχαρητήρια, είχατε 100% επιτυχία!',
                'congratulations_score' => 'Συγχαρητήρια, είχατε %s% επιτυχία!',
                'sorry_failed' => 'Λυπούμαστε, είχατε %s% επιτυχία!',
                'wrong_answers_count' => 'Είχατε %s λάθη',
                'your_answer_was' => 'Η απάντησή σας ήταν',
                'correct_answer_is' => 'Η σωστή απάντηση είναι',
                'question' => 'Ερώτηση',
                'drop_here' => 'Αφήστε εδώ',
                'leave_warning' => 'Έχετε μη αποθηκευμένη πρόοδο. Είστε σίγουροι ότι θέλετε να φύγετε;',
                'skip_to_quiz' => 'Μετάβαση στο περιεχόμενο του κουίζ',
                'points' => 'Πόντοι',
                'statement_text' => 'Δήλωση',
                'true_option' => 'Σωστό',
                'false_option' => 'Λάθος',
                'add_statement' => 'Προσθήκη Δήλωσης',
                'remove' => 'Αφαίρεση',
                'tf_statements' => 'Δηλώσεις Σωστό/Λάθος',
                'tf_instruction' => 'Προσθέστε πολλαπλές δηλώσεις για αξιολόγηση ως Σωστό ή Λάθος'
            ]
        ]);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueAdminScripts($hook): void {
        // Only load on our plugin pages and post edit screens
        $allowed_hooks = ['post.php', 'post-new.php', 'edit.php'];
        $allowed_post_types = ['elearning_lesson', 'elearning_quiz'];
        
        // Check if we're on a master settings page
        $is_master_settings = (strpos($hook, 'elearning-master-settings') !== false);
        
        if (!in_array($hook, $allowed_hooks) && 
            strpos($hook, 'elearning-quiz') === false &&
            strpos($hook, 'elearning-') === false) {
            return;
        }
        
        // Check if we're editing our custom post types
        if (in_array($hook, ['post.php', 'post-new.php', 'edit.php'])) {
            $post_type = get_post_type();
            if (!in_array($post_type, $allowed_post_types) && !$is_master_settings) {
                return;
            }
        }
        
        $admin_css_version = $this->getFileVersion('assets/css/admin.css');
        $admin_js_version = $this->getFileVersion('assets/js/admin.js');
        
        // Admin stylesheet
        wp_enqueue_style(
            'elearning-quiz-admin',
            ELEARNING_QUIZ_PLUGIN_URL . 'assets/css/admin.css',
            [],
            $admin_css_version
        );
        
        // Admin script
        wp_enqueue_script(
            'elearning-quiz-admin',
            ELEARNING_QUIZ_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'jquery-ui-sortable'],
            $admin_js_version,
            true
        );
        
        // Localize admin script - ALL IN GREEK
        wp_localize_script('elearning-quiz-admin', 'elearningQuizAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('elearning_quiz_admin_nonce'),
            'strings' => [
                'confirm_delete' => 'Είστε σίγουροι ότι θέλετε να διαγράψετε αυτό το στοιχείο;',
                'add_question' => 'Προσθήκη Ερώτησης',
                'add_option' => 'Προσθήκη Επιλογής',
                'add_word' => 'Προσθήκη Λέξης',
                'remove' => 'Αφαίρεση',
                'option_text' => 'Κείμενο επιλογής',
                'correct' => 'Σωστό',
                'word' => 'Λέξη',
                'section' => 'Ενότητα',
                'question' => 'Ερώτηση',
                'add_left_item' => 'Προσθήκη Αριστερού Στοιχείου',
                'add_right_item' => 'Προσθήκη Δεξιού Στοιχείου',
                'add_match' => 'Προσθήκη Αντιστοίχισης',
                'select_left' => 'Επιλέξτε αριστερό στοιχείο',
                'select_right' => 'Επιλέξτε δεξί στοιχείο',
                'matches_with' => 'αντιστοιχεί με',
                'left_column' => 'Αριστερή Στήλη',
                'right_column' => 'Δεξιά Στήλη',
                'left_item' => 'Αριστερό στοιχείο',
                'right_item' => 'Δεξί στοιχείο',
                'options' => 'Επιλογές',
                'text_with_blanks' => 'Κείμενο με Κενά',
                'blank_instruction' => 'Χρησιμοποιήστε {{blank}} για να σημειώσετε πού θα εμφανίζονται τα κενά.',
                'word_bank' => 'Τράπεζα Λέξεων',
                'correct_answer' => 'Σωστή Απάντηση',
                'true_option' => 'Σωστό',
                'false_option' => 'Λάθος',
                'correct_matches' => 'Σωστές Αντιστοιχίσεις',
                'saving' => 'Αποθήκευση...',
                'saved' => 'Αποθηκεύτηκε',
                'error_saving' => 'Σφάλμα κατά την αποθήκευση',
                'loading' => 'Φόρτωση...',
                'deleting' => 'Διαγραφή...',
                'deleted' => 'Διαγράφηκε',
                'are_you_sure' => 'Είστε σίγουροι;',
                'yes' => 'Ναι',
                'no' => 'Όχι',
                'cancel' => 'Ακύρωση',
                'close' => 'Κλείσιμο',
                'statement_text' => 'Δήλωση',
                'add_statement' => 'Προσθήκη Δήλωσης',
                'tf_statements' => 'Δηλώσεις Σωστό/Λάθος',
                'tf_instruction' => 'Προσθέστε πολλαπλές δηλώσεις για αξιολόγηση ως Σωστό ή Λάθος'
            ]
        ]);
    }
    
    /**
     * Get file version for cache busting
     */
    private function getFileVersion(string $file_path): string {
        $full_path = ELEARNING_QUIZ_PLUGIN_DIR . $file_path;
        
        if (file_exists($full_path)) {
            return ELEARNING_QUIZ_VERSION . '-' . filemtime($full_path);
        }
        
        return ELEARNING_QUIZ_VERSION;
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    ELearningQuizSystem::getInstance();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    ELearningQuizSystem::getInstance()->activate();
});