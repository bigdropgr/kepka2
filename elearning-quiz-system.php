<?php
/**
 * Plugin Name: E-Learning Quiz System for KEPKA
 * Plugin URI: https://BigDrop.gr
 * Description: Ένα ολοκληρωμένο σύστημα e-learning με μαθήματα, κουίζ και αναλυτικά στοιχεία για το WordPress. / A comprehensive e-learning system with lessons, quizzes, and analytics for WordPress.
 * Version: 1.0.0
 * Author: BigDrop
 * Author URI: https://bigdrop.gr
 * Text Domain: elearning-quiz
 * Domain Path: /languages
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
define('ELEARNING_QUIZ_VERSION', '1.0.0');
define('ELEARNING_QUIZ_PLUGIN_FILE', __FILE__);
define('ELEARNING_QUIZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ELEARNING_QUIZ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ELEARNING_QUIZ_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check PHP version
if (version_compare(PHP_VERSION, '8.0', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>';
        echo sprintf(
            esc_html__('E-Learning Quiz System requires PHP 8.0 or higher. You are running PHP %s.', 'elearning-quiz'),
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
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void {
        register_activation_hook(ELEARNING_QUIZ_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(ELEARNING_QUIZ_PLUGIN_FILE, [$this, 'deactivate']);
        register_uninstall_hook(ELEARNING_QUIZ_PLUGIN_FILE, [__CLASS__, 'uninstall']);
        
        // Load textdomain early for Greek support
        add_action('plugins_loaded', [$this, 'loadTextdomain'], 0);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        
        // Add Greek locale support
        add_filter('locale', [$this, 'forceGreekLocale']);
    }
    
    /**
     * Force Greek locale if needed
     */
    public function forceGreekLocale($locale) {
        // Check if we should use Greek
        if (get_option('elearning_use_greek', false) || (defined('ICL_LANGUAGE_CODE') && ICL_LANGUAGE_CODE === 'el')) {
            return 'el';
        }
        return $locale;
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
            'cookie_consent_integration' => false,
            'default_language' => 'el' // Greek as default
        ];
        
        add_option('elearning_quiz_settings', $default_settings);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
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
        delete_option('elearning_use_greek');
        
        // Clean up any transients
        delete_transient('elearning_quiz_cache');
    }
    
    /**
     * Load plugin textdomain for Greek translations
     */
    public function loadTextdomain(): void {
        // First try to load Greek translation
        $locale = determine_locale();
        
        // Load MO file from plugin languages directory
        $mofile = ELEARNING_QUIZ_PLUGIN_DIR . 'languages/elearning-quiz-' . $locale . '.mo';
        
        if (file_exists($mofile)) {
            load_textdomain('elearning-quiz', $mofile);
        } else {
            // Fallback to default WordPress loading
            load_plugin_textdomain(
                'elearning-quiz',
                false,
                dirname(ELEARNING_QUIZ_PLUGIN_BASENAME) . '/languages'
            );
        }
    }
    
    /**
     * Enqueue frontend scripts and styles with Greek translations
     */
    public function enqueueScripts(): void {
        // Only enqueue on relevant pages
        if (!is_singular(['elearning_lesson', 'elearning_quiz'])) {
            return;
        }
        
        $css_version = $this->getFileVersion('assets/css/frontend.css');
        $js_version = $this->getFileVersion('assets/js/frontend.js');
        
        // Check if WoodMart is active
        $theme = wp_get_theme();
        $is_woodmart = $theme->get('Name') === 'WoodMart' || $theme->get('Template') === 'woodmart';
        
        if ($is_woodmart) {
            wp_enqueue_style(
                'elearning-quiz-frontend',
                ELEARNING_QUIZ_PLUGIN_URL . 'assets/css/woodmart-integrated-frontend.css',
                ['woodmart-style'],
                $css_version
            );
        } else {
            wp_enqueue_style(
                'elearning-quiz-frontend',
                ELEARNING_QUIZ_PLUGIN_URL . 'assets/css/frontend.css',
                [],
                $css_version
            );
        }
        
        wp_enqueue_script(
            'elearning-quiz-frontend',
            ELEARNING_QUIZ_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'],
            $js_version,
            true
        );
        
        // Localize script with Greek translations
        wp_localize_script('elearning-quiz-frontend', 'elearningQuiz', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('elearning_quiz_nonce'),
            'locale' => get_locale(),
            'isGreek' => (get_locale() === 'el' || get_locale() === 'el_GR'),
            'strings' => $this->getFrontendStrings()
        ]);
    }
    
    /**
     * Get frontend strings for translation
     */
    private function getFrontendStrings(): array {
        return [
            // Core strings
            'loading' => __('Φόρτωση...', 'elearning-quiz'),
            'error' => __('Παρουσιάστηκε σφάλμα. Παρακαλώ δοκιμάστε ξανά.', 'elearning-quiz'),
            'confirm_submit' => __('Είστε σίγουροι ότι θέλετε να υποβάλετε τις απαντήσεις σας;', 'elearning-quiz'),
            
            // Quiz strings
            'congratulations' => __('Συγχαρητήρια!', 'elearning-quiz'),
            'quiz_passed' => __('Ολοκληρώσατε με επιτυχία αυτό το κουίζ.', 'elearning-quiz'),
            'try_again' => __('Δοκιμάστε Ξανά', 'elearning-quiz'),
            'quiz_failed' => __('Δεν περάσατε αυτό το κουίζ. Παρακαλώ ελέγξτε το υλικό και δοκιμάστε ξανά.', 'elearning-quiz'),
            'correct_answers' => __('Σωστές Απαντήσεις', 'elearning-quiz'),
            'passing_score' => __('Βαθμός Επιτυχίας', 'elearning-quiz'),
            'retry_quiz' => __('Επανάληψη Κουίζ', 'elearning-quiz'),
            
            // Section strings
            'next_section' => __('Επόμενη Ενότητα', 'elearning-quiz'),
            'previous_section' => __('Προηγούμενη Ενότητα', 'elearning-quiz'),
            'mark_complete' => __('Σήμανση ως Ολοκληρωμένο', 'elearning-quiz'),
            'section_completed' => __('Η ενότητα ολοκληρώθηκε!', 'elearning-quiz'),
            
            // Time strings
            'time_remaining' => __('Χρόνος που απομένει', 'elearning-quiz'),
            'time_up' => __('Ο χρόνος τελείωσε!', 'elearning-quiz'),
            'submitting_quiz' => __('Το κουίζ σας υποβάλλεται...', 'elearning-quiz'),
            'one_minute_warning' => __('Απομένει ένα λεπτό', 'elearning-quiz'),
            
            // Question strings
            'unanswered_questions' => __('Έχετε αναπάντητες ερωτήσεις', 'elearning-quiz'),
            'submit_anyway' => __('Υποβολή ούτως ή άλλως;', 'elearning-quiz'),
            'your_answer' => __('Η Απάντησή σας', 'elearning-quiz'),
            'correct_answer' => __('Σωστή Απάντηση', 'elearning-quiz'),
            'no_answer' => __('Δεν δόθηκε απάντηση', 'elearning-quiz'),
            'review_answers' => __('Ανασκόπηση Απαντήσεων', 'elearning-quiz'),
            
            // Score strings
            'perfect_score' => __('Συγχαρητήρια, είχατε 100% επιτυχία!', 'elearning-quiz'),
            'congratulations_score' => __('Συγχαρητήρια, είχατε %s% επιτυχία!', 'elearning-quiz'),
            'sorry_failed' => __('Λυπούμαστε, είχατε %s% επιτυχία!', 'elearning-quiz'),
            'wrong_answers_count' => __('Είχατε %s λάθη', 'elearning-quiz'),
            'your_answer_was' => __('Η απάντησή σας ήταν', 'elearning-quiz'),
            'correct_answer_is' => __('Η σωστή απάντηση είναι', 'elearning-quiz'),
            
            // Navigation
            'question' => __('Ερώτηση', 'elearning-quiz'),
            'drop_here' => __('Αφήστε εδώ', 'elearning-quiz'),
            'leave_warning' => __('Έχετε μη αποθηκευμένη πρόοδο. Είστε σίγουροι ότι θέλετε να φύγετε;', 'elearning-quiz'),
            'skip_to_quiz' => __('Μετάβαση στο κουίζ', 'elearning-quiz'),
        ];
    }
    
    /**
     * Enqueue admin scripts and styles with Greek translations
     */
    public function enqueueAdminScripts($hook): void {
        $allowed_hooks = ['post.php', 'post-new.php', 'edit.php'];
        $allowed_post_types = ['elearning_lesson', 'elearning_quiz'];
        
        if (!in_array($hook, $allowed_hooks) && 
            strpos($hook, 'elearning-quiz') === false) {
            return;
        }
        
        if (in_array($hook, ['post.php', 'post-new.php', 'edit.php'])) {
            $post_type = get_post_type();
            if (!in_array($post_type, $allowed_post_types)) {
                return;
            }
        }
        
        $admin_css_version = $this->getFileVersion('assets/css/admin.css');
        $admin_js_version = $this->getFileVersion('assets/js/admin.js');
        
        wp_enqueue_style(
            'elearning-quiz-admin',
            ELEARNING_QUIZ_PLUGIN_URL . 'assets/css/admin.css',
            [],
            $admin_css_version
        );
        
        wp_enqueue_script(
            'elearning-quiz-admin',
            ELEARNING_QUIZ_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'jquery-ui-sortable'],
            $admin_js_version,
            true
        );
        
        // Localize admin script with Greek translations
        wp_localize_script('elearning-quiz-admin', 'elearningQuizAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('elearning_quiz_admin_nonce'),
            'locale' => get_locale(),
            'isGreek' => (get_locale() === 'el' || get_locale() === 'el_GR'),
            'strings' => $this->getAdminStrings()
        ]);
    }
    
    /**
     * Get admin strings for translation
     */
    private function getAdminStrings(): array {
        return [
            'confirm_delete' => __('Είστε σίγουροι ότι θέλετε να διαγράψετε αυτό το στοιχείο;', 'elearning-quiz'),
            'add_question' => __('Προσθήκη Ερώτησης', 'elearning-quiz'),
            'add_option' => __('Προσθήκη Επιλογής', 'elearning-quiz'),
            'add_word' => __('Προσθήκη Λέξης', 'elearning-quiz'),
            'remove' => __('Αφαίρεση', 'elearning-quiz'),
            'option_text' => __('Κείμενο επιλογής', 'elearning-quiz'),
            'correct' => __('Σωστό', 'elearning-quiz'),
            'word' => __('Λέξη', 'elearning-quiz'),
            'section' => __('Ενότητα', 'elearning-quiz'),
            'question' => __('Ερώτηση', 'elearning-quiz'),
            'add_left_item' => __('Προσθήκη Αριστερού Στοιχείου', 'elearning-quiz'),
            'add_right_item' => __('Προσθήκη Δεξιού Στοιχείου', 'elearning-quiz'),
            'add_match' => __('Προσθήκη Αντιστοίχισης', 'elearning-quiz'),
            'select_left' => __('Επιλέξτε αριστερό στοιχείο', 'elearning-quiz'),
            'select_right' => __('Επιλέξτε δεξί στοιχείο', 'elearning-quiz'),
            'matches_with' => __('αντιστοιχεί με', 'elearning-quiz'),
            'left_column' => __('Αριστερή Στήλη', 'elearning-quiz'),
            'right_column' => __('Δεξιά Στήλη', 'elearning-quiz'),
            'left_item' => __('Αριστερό στοιχείο', 'elearning-quiz'),
            'right_item' => __('Δεξί στοιχείο', 'elearning-quiz'),
            'options' => __('Επιλογές', 'elearning-quiz'),
            'text_with_blanks' => __('Κείμενο με Κενά', 'elearning-quiz'),
            'blank_instruction' => __('Χρησιμοποιήστε {{blank}} για να σημειώσετε πού θα εμφανίζονται τα κενά.', 'elearning-quiz'),
            'word_bank' => __('Τράπεζα Λέξεων', 'elearning-quiz'),
            'correct_answer' => __('Σωστή Απάντηση', 'elearning-quiz'),
            'true_option' => __('Σωστό', 'elearning-quiz'),
            'false_option' => __('Λάθος', 'elearning-quiz'),
            'correct_matches' => __('Σωστές Αντιστοιχίσεις', 'elearning-quiz'),
        ];
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