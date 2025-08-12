<?php
/**
 * Master Settings Class
 * 
 * Handles master/default settings for quizzes
 * Allows setting global defaults that can be applied to all new quizzes
 */

if (!defined('ABSPATH')) {
    exit;
}

class ELearning_Master_Settings {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'addMasterSettingsPage']);
        add_action('admin_init', [$this, 'registerMasterSettings']);
        add_action('wp_ajax_elearning_apply_master_settings', [$this, 'applyMasterSettings']);
        add_action('wp_ajax_elearning_get_master_settings', [$this, 'getMasterSettings']);
        
        // Hook to apply master settings when creating new quiz
        add_action('save_post_elearning_quiz', [$this, 'applyMasterSettingsToNewQuiz'], 10, 3);
    }
    
    /**
     * Add master settings page to admin menu
     */
    public function addMasterSettingsPage(): void {
        add_submenu_page(
            'elearning-dashboard',
            'Προεπιλεγμένες Ρυθμίσεις Κουίζ',
            'Προεπιλογές Κουίζ',
            'manage_elearning_settings',
            'elearning-master-settings',
            [$this, 'renderMasterSettingsPage']
        );
    }
    
    /**
     * Register master settings
     */
    public function registerMasterSettings(): void {
        register_setting('elearning_master_quiz_settings', 'elearning_master_quiz_settings', [
            'sanitize_callback' => [$this, 'sanitizeMasterSettings']
        ]);
        
        // Master Quiz Settings Section
        add_settings_section(
            'elearning_master_quiz_section',
            'Προεπιλεγμένες Ρυθμίσεις για Νέα Κουίζ',
            [$this, 'renderMasterQuizSection'],
            'elearning_master_quiz_settings'
        );
        
        // Passing Score Field
        add_settings_field(
            'master_passing_score',
            'Βαθμός Επιτυχίας (%)',
            [$this, 'renderPassingScoreField'],
            'elearning_master_quiz_settings',
            'elearning_master_quiz_section'
        );
        
        // Questions to Show Field
        add_settings_field(
            'master_questions_to_show',
            'Ερωτήσεις προς Εμφάνιση',
            [$this, 'renderQuestionsToShowField'],
            'elearning_master_quiz_settings',
            'elearning_master_quiz_section'
        );
        
        // Time Limit Field
        add_settings_field(
            'master_time_limit',
            'Χρονικό Όριο (λεπτά)',
            [$this, 'renderTimeLimitField'],
            'elearning_master_quiz_settings',
            'elearning_master_quiz_section'
        );
        
        // Show Results Field
        add_settings_field(
            'master_show_results',
            'Εμφάνιση Αποτελεσμάτων',
            [$this, 'renderShowResultsField'],
            'elearning_master_quiz_settings',
            'elearning_master_quiz_section'
        );
        
        // Randomize Questions Field
        add_settings_field(
            'master_randomize_questions',
            'Τυχαία Σειρά Ερωτήσεων',
            [$this, 'renderRandomizeQuestionsField'],
            'elearning_master_quiz_settings',
            'elearning_master_quiz_section'
        );
        
        // Randomize Answers Field
        add_settings_field(
            'master_randomize_answers',
            'Τυχαία Σειρά Απαντήσεων',
            [$this, 'renderRandomizeAnswersField'],
            'elearning_master_quiz_settings',
            'elearning_master_quiz_section'
        );
        
        // Enable Quiz Retakes Field
        add_settings_field(
            'master_enable_retakes',
            'Επιτρέπονται Επαναλήψεις',
            [$this, 'renderEnableRetakesField'],
            'elearning_master_quiz_settings',
            'elearning_master_quiz_section'
        );
        
        // Max Attempts Field
        add_settings_field(
            'master_max_attempts',
            'Μέγιστες Προσπάθειες',
            [$this, 'renderMaxAttemptsField'],
            'elearning_master_quiz_settings',
            'elearning_master_quiz_section'
        );
    }
    
    /**
     * Sanitize master settings
     */
    public function sanitizeMasterSettings($settings): array {
        $sanitized = [];
        
        $sanitized['passing_score'] = max(0, min(100, absint($settings['passing_score'] ?? 70)));
        $sanitized['questions_to_show'] = max(0, absint($settings['questions_to_show'] ?? 0));
        $sanitized['time_limit'] = max(0, absint($settings['time_limit'] ?? 0));
        $sanitized['show_results'] = sanitize_text_field($settings['show_results'] ?? 'yes');
        $sanitized['randomize_questions'] = sanitize_text_field($settings['randomize_questions'] ?? 'no');
        $sanitized['randomize_answers'] = sanitize_text_field($settings['randomize_answers'] ?? 'no');
        $sanitized['enable_retakes'] = sanitize_text_field($settings['enable_retakes'] ?? 'yes');
        $sanitized['max_attempts'] = max(0, absint($settings['max_attempts'] ?? 0));
        $sanitized['auto_apply_to_new'] = !empty($settings['auto_apply_to_new']);
        
        return $sanitized;
    }
    
    /**
     * Render master settings page
     */
    public function renderMasterSettingsPage(): void {
        ?>
        <div class="wrap">
            <h1>Προεπιλεγμένες Ρυθμίσεις Κουίζ</h1>
            
            <div class="elearning-settings-description">
                <p>Ορίστε τις προεπιλεγμένες ρυθμίσεις που θα εφαρμόζονται σε όλα τα νέα κουίζ. Μπορείτε επίσης να εφαρμόσετε αυτές τις ρυθμίσεις σε υπάρχοντα κουίζ.</p>
            </div>
            
            <form method="post" action="options.php" id="master-settings-form">
                <?php
                settings_fields('elearning_master_quiz_settings');
                do_settings_sections('elearning_master_quiz_settings');
                ?>
                
                <h3>Αυτόματη Εφαρμογή</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">Εφαρμογή σε Νέα Κουίζ</th>
                        <td>
                            <?php
                            $settings = get_option('elearning_master_quiz_settings', []);
                            $auto_apply = !empty($settings['auto_apply_to_new']);
                            ?>
                            <label>
                                <input type="checkbox" name="elearning_master_quiz_settings[auto_apply_to_new]" value="1" <?php checked($auto_apply); ?> />
                                Αυτόματη εφαρμογή αυτών των ρυθμίσεων σε όλα τα νέα κουίζ
                            </label>
                            <p class="description">
                                Όταν είναι ενεργοποιημένο, τα νέα κουίζ θα χρησιμοποιούν αυτές τις προεπιλεγμένες ρυθμίσεις.
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Αποθήκευση Προεπιλογών'); ?>
            </form>
            
            <!-- Apply to Existing Quizzes Section -->
            <div class="elearning-apply-settings-section">
                <h2>Εφαρμογή σε Υπάρχοντα Κουίζ</h2>
                <p>Μπορείτε να εφαρμόσετε τις προεπιλεγμένες ρυθμίσεις σε υπάρχοντα κουίζ.</p>
                
                <div class="apply-settings-options">
                    <h3>Επιλογή Κουίζ</h3>
                    
                    <div class="quiz-selection">
                        <label>
                            <input type="radio" name="apply_to" value="all" checked />
                            Εφαρμογή σε όλα τα κουίζ
                        </label>
                        <br><br>
                        
                        <label>
                            <input type="radio" name="apply_to" value="selected" />
                            Εφαρμογή σε επιλεγμένα κουίζ
                        </label>
                        
                        <div id="quiz-selector" style="display: none; margin-top: 15px;">
                            <select multiple name="selected_quizzes[]" size="10" style="width: 100%; max-width: 500px;">
                                <?php
                                $quizzes = get_posts([
                                    'post_type' => 'elearning_quiz',
                                    'posts_per_page' => -1,
                                    'post_status' => 'publish',
                                    'orderby' => 'title',
                                    'order' => 'ASC'
                                ]);
                                
                                foreach ($quizzes as $quiz) {
                                    echo '<option value="' . esc_attr($quiz->ID) . '">' . esc_html($quiz->post_title) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">Κρατήστε πατημένο το Ctrl (ή Cmd σε Mac) για να επιλέξετε πολλαπλά κουίζ.</p>
                        </div>
                    </div>
                    
                    <h3 style="margin-top: 20px;">Επιλογή Ρυθμίσεων προς Εφαρμογή</h3>
                    <div class="settings-to-apply">
                        <label><input type="checkbox" name="apply_settings[]" value="passing_score" checked /> Βαθμός Επιτυχίας</label><br>
                        <label><input type="checkbox" name="apply_settings[]" value="questions_to_show" /> Ερωτήσεις προς Εμφάνιση</label><br>
                        <label><input type="checkbox" name="apply_settings[]" value="time_limit" /> Χρονικό Όριο</label><br>
                        <label><input type="checkbox" name="apply_settings[]" value="show_results" /> Εμφάνιση Αποτελεσμάτων</label><br>
                        <label><input type="checkbox" name="apply_settings[]" value="randomize_questions" /> Τυχαία Σειρά Ερωτήσεων</label><br>
                        <label><input type="checkbox" name="apply_settings[]" value="randomize_answers" /> Τυχαία Σειρά Απαντήσεων</label><br>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <button type="button" class="button button-primary" id="apply-master-settings">
                            Εφαρμογή Ρυθμίσεων
                        </button>
                        <span id="apply-result" style="margin-left: 15px;"></span>
                    </div>
                </div>
            </div>
            
            <!-- Current Settings Preview -->
            <div class="elearning-settings-preview">
                <h2>Προεπισκόπηση Τρεχουσών Ρυθμίσεων</h2>
                <div id="settings-preview-content">
                    <?php $this->renderSettingsPreview(); ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Toggle quiz selector
            $('input[name="apply_to"]').on('change', function() {
                if ($(this).val() === 'selected') {
                    $('#quiz-selector').show();
                } else {
                    $('#quiz-selector').hide();
                }
            });
            
            // Apply master settings
            $('#apply-master-settings').on('click', function() {
                const $button = $(this);
                const $result = $('#apply-result');
                
                // Get selected options
                const applyTo = $('input[name="apply_to"]:checked').val();
                const selectedQuizzes = applyTo === 'selected' ? 
                    $('select[name="selected_quizzes[]"]').val() : [];
                const settingsToApply = [];
                
                $('input[name="apply_settings[]"]:checked').each(function() {
                    settingsToApply.push($(this).val());
                });
                
                if (settingsToApply.length === 0) {
                    alert('Παρακαλώ επιλέξτε τουλάχιστον μία ρύθμιση για εφαρμογή.');
                    return;
                }
                
                if (applyTo === 'selected' && (!selectedQuizzes || selectedQuizzes.length === 0)) {
                    alert('Παρακαλώ επιλέξτε τουλάχιστον ένα κουίζ.');
                    return;
                }
                
                if (!confirm('Είστε σίγουροι ότι θέλετε να εφαρμόσετε αυτές τις ρυθμίσεις; Αυτή η ενέργεια θα αντικαταστήσει τις υπάρχουσες ρυθμίσεις των επιλεγμένων κουίζ.')) {
                    return;
                }
                
                $button.prop('disabled', true).text('Εφαρμογή...');
                $result.html('<span style="color: #0073aa;">Επεξεργασία...</span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'elearning_apply_master_settings',
                        apply_to: applyTo,
                        selected_quizzes: selectedQuizzes,
                        settings_to_apply: settingsToApply,
                        nonce: '<?php echo wp_create_nonce('elearning_master_settings_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                        } else {
                            $result.html('<span style="color: red;">✗ ' + (response.data || 'Σφάλμα κατά την εφαρμογή των ρυθμίσεων') + '</span>');
                        }
                        $button.prop('disabled', false).text('Εφαρμογή Ρυθμίσεων');
                    },
                    error: function() {
                        $result.html('<span style="color: red;">✗ Σφάλμα σύνδεσης</span>');
                        $button.prop('disabled', false).text('Εφαρμογή Ρυθμίσεων');
                    }
                });
            });
            
            // Update preview when form changes
            $('#master-settings-form').on('change', 'input, select', function() {
                updateSettingsPreview();
            });
            
            function updateSettingsPreview() {
                const settings = {
                    passing_score: $('[name="elearning_master_quiz_settings[passing_score]"]').val(),
                    questions_to_show: $('[name="elearning_master_quiz_settings[questions_to_show]"]').val(),
                    time_limit: $('[name="elearning_master_quiz_settings[time_limit]"]').val(),
                    show_results: $('[name="elearning_master_quiz_settings[show_results]"]').val(),
                    randomize_questions: $('[name="elearning_master_quiz_settings[randomize_questions]"]').val(),
                    randomize_answers: $('[name="elearning_master_quiz_settings[randomize_answers]"]').val()
                };
                
                // Update preview content dynamically
                let previewHtml = '<table class="wp-list-table widefat fixed striped">';
                previewHtml += '<tr><th>Ρύθμιση</th><th>Τιμή</th></tr>';
                previewHtml += '<tr><td>Βαθμός Επιτυχίας</td><td>' + settings.passing_score + '%</td></tr>';
                previewHtml += '<tr><td>Ερωτήσεις προς Εμφάνιση</td><td>' + (settings.questions_to_show || 'Όλες') + '</td></tr>';
                previewHtml += '<tr><td>Χρονικό Όριο</td><td>' + (settings.time_limit ? settings.time_limit + ' λεπτά' : 'Χωρίς όριο') + '</td></tr>';
                previewHtml += '<tr><td>Εμφάνιση Αποτελεσμάτων</td><td>' + (settings.show_results === 'yes' ? 'Ναι' : 'Όχι') + '</td></tr>';
                previewHtml += '<tr><td>Τυχαία Σειρά Ερωτήσεων</td><td>' + (settings.randomize_questions === 'yes' ? 'Ναι' : 'Όχι') + '</td></tr>';
                previewHtml += '<tr><td>Τυχαία Σειρά Απαντήσεων</td><td>' + (settings.randomize_answers === 'yes' ? 'Ναι' : 'Όχι') + '</td></tr>';
                previewHtml += '</table>';
                
                $('#settings-preview-content').html(previewHtml);
            }
        });
        </script>
        
        <style>
        .elearning-settings-description {
            background: #f0f0f1;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .elearning-apply-settings-section {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 30px;
        }
        
        .apply-settings-options {
            margin-top: 20px;
        }
        
        .quiz-selection label {
            display: block;
            margin-bottom: 5px;
        }
        
        .settings-to-apply label {
            display: block;
            margin-bottom: 8px;
        }
        
        .elearning-settings-preview {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 30px;
        }
        
        .elearning-settings-preview table {
            margin-top: 15px;
        }
        
        .elearning-settings-preview th {
            background: #f0f0f1;
            font-weight: 600;
        }
        </style>
        <?php
    }
    
    /**
     * Render master quiz section description
     */
    public function renderMasterQuizSection(): void {
        echo '<p>Αυτές οι ρυθμίσεις θα χρησιμοποιούνται ως προεπιλογές για όλα τα νέα κουίζ που δημιουργούνται.</p>';
    }
    
    /**
     * Render passing score field
     */
    public function renderPassingScoreField(): void {
        $settings = get_option('elearning_master_quiz_settings', []);
        $value = $settings['passing_score'] ?? 70;
        ?>
        <input type="number" name="elearning_master_quiz_settings[passing_score]" value="<?php echo esc_attr($value); ?>" min="0" max="100" />
        <p class="description">Ελάχιστο ποσοστό που απαιτείται για επιτυχία στο κουίζ.</p>
        <?php
    }
    
    /**
     * Render questions to show field
     */
    public function renderQuestionsToShowField(): void {
        $settings = get_option('elearning_master_quiz_settings', []);
        $value = $settings['questions_to_show'] ?? 0;
        ?>
        <input type="number" name="elearning_master_quiz_settings[questions_to_show]" value="<?php echo esc_attr($value); ?>" min="0" />
        <p class="description">Αριθμός ερωτήσεων που θα εμφανίζονται από την τράπεζα ερωτήσεων. Αφήστε 0 για εμφάνιση όλων.</p>
        <?php
    }
    
    /**
     * Render time limit field
     */
    public function renderTimeLimitField(): void {
        $settings = get_option('elearning_master_quiz_settings', []);
        $value = $settings['time_limit'] ?? 0;
        ?>
        <input type="number" name="elearning_master_quiz_settings[time_limit]" value="<?php echo esc_attr($value); ?>" min="0" />
        <p class="description">Χρονικό όριο σε λεπτά. Ορίστε 0 για χωρίς χρονικό όριο.</p>
        <?php
    }
    
    /**
     * Render show results field
     */
    public function renderShowResultsField(): void {
        $settings = get_option('elearning_master_quiz_settings', []);
        $value = $settings['show_results'] ?? 'yes';
        ?>
        <select name="elearning_master_quiz_settings[show_results]">
            <option value="yes" <?php selected($value, 'yes'); ?>>Ναι - Εμφάνιση σωστών απαντήσεων μετά την υποβολή</option>
            <option value="no" <?php selected($value, 'no'); ?>>Όχι - Εμφάνιση μόνο του σκορ</option>
        </select>
        <?php
    }
    
    /**
     * Render randomize questions field
     */
    public function renderRandomizeQuestionsField(): void {
        $settings = get_option('elearning_master_quiz_settings', []);
        $value = $settings['randomize_questions'] ?? 'no';
        ?>
        <select name="elearning_master_quiz_settings[randomize_questions]">
            <option value="yes" <?php selected($value, 'yes'); ?>>Ναι</option>
            <option value="no" <?php selected($value, 'no'); ?>>Όχι</option>
        </select>
        <p class="description">Εμφάνιση ερωτήσεων σε τυχαία σειρά για κάθε προσπάθεια.</p>
        <?php
    }
    
    /**
     * Render randomize answers field
     */
    public function renderRandomizeAnswersField(): void {
        $settings = get_option('elearning_master_quiz_settings', []);
        $value = $settings['randomize_answers'] ?? 'no';
        ?>
        <select name="elearning_master_quiz_settings[randomize_answers]">
            <option value="yes" <?php selected($value, 'yes'); ?>>Ναι</option>
            <option value="no" <?php selected($value, 'no'); ?>>Όχι</option>
        </select>
        <p class="description">Εμφάνιση επιλογών απάντησης σε τυχαία σειρά (για ερωτήσεις πολλαπλής επιλογής).</p>
        <?php
    }
    
    /**
     * Render enable retakes field
     */
    public function renderEnableRetakesField(): void {
        $settings = get_option('elearning_master_quiz_settings', []);
        $value = $settings['enable_retakes'] ?? 'yes';
        ?>
        <select name="elearning_master_quiz_settings[enable_retakes]">
            <option value="yes" <?php selected($value, 'yes'); ?>>Ναι</option>
            <option value="no" <?php selected($value, 'no'); ?>>Όχι</option>
        </select>
        <p class="description">Επιτρέπει στους χρήστες να επαναλάβουν το κουίζ.</p>
        <?php
    }
    
    /**
     * Render max attempts field
     */
    public function renderMaxAttemptsField(): void {
        $settings = get_option('elearning_master_quiz_settings', []);
        $value = $settings['max_attempts'] ?? 0;
        ?>
        <input type="number" name="elearning_master_quiz_settings[max_attempts]" value="<?php echo esc_attr($value); ?>" min="0" />
        <p class="description">Μέγιστος αριθμός προσπαθειών. Ορίστε 0 για απεριόριστες προσπάθειες.</p>
        <?php
    }
    
    /**
     * Render settings preview
     */
    private function renderSettingsPreview(): void {
        $settings = get_option('elearning_master_quiz_settings', []);
        ?>
        <table class="wp-list-table widefat fixed striped">
            <tr>
                <th>Ρύθμιση</th>
                <th>Τρέχουσα Τιμή</th>
            </tr>
            <tr>
                <td>Βαθμός Επιτυχίας</td>
                <td><?php echo ($settings['passing_score'] ?? 70); ?>%</td>
            </tr>
            <tr>
                <td>Ερωτήσεις προς Εμφάνιση</td>
                <td><?php echo ($settings['questions_to_show'] ?? 0) ?: 'Όλες'; ?></td>
            </tr>
            <tr>
                <td>Χρονικό Όριο</td>
                <td><?php echo ($settings['time_limit'] ?? 0) ? ($settings['time_limit'] . ' λεπτά') : 'Χωρίς όριο'; ?></td>
            </tr>
            <tr>
                <td>Εμφάνιση Αποτελεσμάτων</td>
                <td><?php echo ($settings['show_results'] ?? 'yes') === 'yes' ? 'Ναι' : 'Όχι'; ?></td>
            </tr>
            <tr>
                <td>Τυχαία Σειρά Ερωτήσεων</td>
                <td><?php echo ($settings['randomize_questions'] ?? 'no') === 'yes' ? 'Ναι' : 'Όχι'; ?></td>
            </tr>
            <tr>
                <td>Τυχαία Σειρά Απαντήσεων</td>
                <td><?php echo ($settings['randomize_answers'] ?? 'no') === 'yes' ? 'Ναι' : 'Όχι'; ?></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Apply master settings to existing quizzes via AJAX
     */
    public function applyMasterSettings(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_master_settings_nonce')) {
            wp_send_json_error('Ο έλεγχος ασφαλείας απέτυχε');
        }
        
        // Check permissions
        if (!current_user_can('manage_elearning_settings')) {
            wp_send_json_error('Δεν έχετε άδεια να εκτελέσετε αυτή την ενέργεια');
        }
        
        $apply_to = sanitize_text_field($_POST['apply_to'] ?? 'all');
        $selected_quizzes = isset($_POST['selected_quizzes']) ? array_map('intval', $_POST['selected_quizzes']) : [];
        $settings_to_apply = isset($_POST['settings_to_apply']) ? array_map('sanitize_text_field', $_POST['settings_to_apply']) : [];
        
        if (empty($settings_to_apply)) {
            wp_send_json_error('Δεν επιλέχθηκαν ρυθμίσεις για εφαρμογή');
        }
        
        // Get master settings
        $master_settings = get_option('elearning_master_quiz_settings', []);
        
        // Get quizzes to update
        if ($apply_to === 'all') {
            $quizzes = get_posts([
                'post_type' => 'elearning_quiz',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'fields' => 'ids'
            ]);
        } else {
            $quizzes = $selected_quizzes;
        }
        
        if (empty($quizzes)) {
            wp_send_json_error('Δεν βρέθηκαν κουίζ για ενημέρωση');
        }
        
        $updated_count = 0;
        
        // Apply settings to each quiz
        foreach ($quizzes as $quiz_id) {
            $updated = false;
            
            if (in_array('passing_score', $settings_to_apply) && isset($master_settings['passing_score'])) {
                update_post_meta($quiz_id, '_passing_score', $master_settings['passing_score']);
                $updated = true;
            }
            
            if (in_array('questions_to_show', $settings_to_apply) && isset($master_settings['questions_to_show'])) {
                update_post_meta($quiz_id, '_min_questions_to_show', $master_settings['questions_to_show'] ?: 9999);
                $updated = true;
            }
            
            if (in_array('time_limit', $settings_to_apply) && isset($master_settings['time_limit'])) {
                update_post_meta($quiz_id, '_time_limit', $master_settings['time_limit']);
                $updated = true;
            }
            
            if (in_array('show_results', $settings_to_apply) && isset($master_settings['show_results'])) {
                update_post_meta($quiz_id, '_show_results_immediately', $master_settings['show_results']);
                $updated = true;
            }
            
            if (in_array('randomize_questions', $settings_to_apply) && isset($master_settings['randomize_questions'])) {
                update_post_meta($quiz_id, '_randomize_questions', $master_settings['randomize_questions']);
                $updated = true;
            }
            
            if (in_array('randomize_answers', $settings_to_apply) && isset($master_settings['randomize_answers'])) {
                update_post_meta($quiz_id, '_randomize_answers', $master_settings['randomize_answers']);
                $updated = true;
            }
            
            if ($updated) {
                $updated_count++;
            }
        }
        
        wp_send_json_success([
            'message' => sprintf('Ενημερώθηκαν επιτυχώς %d κουίζ', $updated_count)
        ]);
    }
    
    /**
     * Get master settings via AJAX
     */
    public function getMasterSettings(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'elearning_master_settings_nonce')) {
            wp_send_json_error('Ο έλεγχος ασφαλείας απέτυχε');
        }
        
        $settings = get_option('elearning_master_quiz_settings', []);
        wp_send_json_success($settings);
    }
    
    /**
     * Apply master settings to new quiz when created
     */
    public function applyMasterSettingsToNewQuiz($post_id, $post, $update): void {
        // Only apply to new quizzes, not updates
        if ($update) {
            return;
        }
        
        // Check if auto-apply is enabled
        $master_settings = get_option('elearning_master_quiz_settings', []);
        if (empty($master_settings['auto_apply_to_new'])) {
            return;
        }
        
        // Apply master settings to the new quiz
        if (isset($master_settings['passing_score'])) {
            update_post_meta($post_id, '_passing_score', $master_settings['passing_score']);
        }
        
        if (isset($master_settings['questions_to_show'])) {
            update_post_meta($post_id, '_min_questions_to_show', $master_settings['questions_to_show'] ?: 9999);
        }
        
        if (isset($master_settings['time_limit'])) {
            update_post_meta($post_id, '_time_limit', $master_settings['time_limit']);
        }
        
        if (isset($master_settings['show_results'])) {
            update_post_meta($post_id, '_show_results_immediately', $master_settings['show_results']);
        }
        
        if (isset($master_settings['randomize_questions'])) {
            update_post_meta($post_id, '_randomize_questions', $master_settings['randomize_questions']);
        }
        
        if (isset($master_settings['randomize_answers'])) {
            update_post_meta($post_id, '_randomize_answers', $master_settings['randomize_answers']);
        }
    }
}