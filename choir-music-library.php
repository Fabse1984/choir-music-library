<?php
/**
 * Plugin Name: Choir Music Library
 * Description: Geschuetzter Notenbereich fuer Chor-Webseiten mit Noten, Hoerbeispielen, Aussprachehilfen und Zusatzdateien.
 * Version: 1.2.11
 * Author: Codex
 * Text Domain: choir-music-library
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CML_Choir_Music_Library {
    const VERSION = '1.2.11';
    const POST_TYPE = 'cml_piece';
    const SUBMISSION_POST_TYPE = 'cml_submission';
    const TAG_TAX = 'cml_piece_tag';
    const META_KEY = '_cml_piece_data';
    const SUBMISSION_META_KEY = '_cml_submission_data';
    const OPTION_LANGUAGE = 'cml_language';
    const OPTION_SUBMISSION_LEVELS = 'cml_submission_levels';
    const OPTION_WATERMARK_SCOPE = 'cml_watermark_scope';
    const OPTION_WATERMARK_LEAD_TEXT = 'cml_watermark_lead_text';
    const OPTION_WATERMARK_BRAND_TEXT = 'cml_watermark_brand_text';
    const OPTION_WATERMARK_HIDE_BRAND_TEXT = 'cml_watermark_hide_brand_text';
    const OPTION_PURCHASES = 'cml_purchases_by_email';
    const USER_PURCHASE_META = '_cml_purchased_product_keys';

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_submission_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_piece'), 10, 2);
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('pre_get_posts', array($this, 'filter_frontend_queries'));
        add_action('admin_post_cml_file', array($this, 'serve_file'));
        add_action('admin_post_nopriv_cml_file', array($this, 'serve_file'));
        add_action('admin_post_cml_submit_piece', array($this, 'handle_piece_submission'));
        add_action('admin_post_nopriv_cml_submit_piece', array($this, 'handle_piece_submission'));
        add_action('admin_post_cml_review_submission', array($this, 'handle_submission_review'));
        add_action('wpsc_payment_ipn_processed', array($this, 'handle_wpsc_payment'), 10, 10);
        add_action('wpsc_paypal_ipn_processed', array($this, 'handle_wpsc_payment'), 10, 10);
        add_action('wpsc_stripe_ipn_processed', array($this, 'handle_wpsc_payment'), 10, 10);
        add_action('wpspc_paypal_ipn_processed', array($this, 'handle_wpsc_payment'), 10, 10);
        add_filter('the_content', array($this, 'render_single_content'));
        add_shortcode('chor_noten_uebersicht', array($this, 'overview_shortcode'));
        add_shortcode('chor_musikstueck', array($this, 'piece_shortcode'));
        add_shortcode('chor_musik_einreichen', array($this, 'submission_shortcode'));
    }

    public function register_post_type() {
        register_post_type(self::POST_TYPE, array(
            'labels' => array(
                'name' => $this->text('pieces'),
                'singular_name' => $this->text('piece'),
                'add_new_item' => $this->text('add_piece'),
                'edit_item' => $this->text('edit_piece'),
                'menu_name' => $this->text('menu_name'),
            ),
            'public' => true,
            'publicly_queryable' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-format-audio',
            'supports' => array('title'),
            'has_archive' => true,
            'rewrite' => array('slug' => 'musikstuecke'),
            'show_in_rest' => true,
        ));
    }

    public function register_submission_post_type() {
        register_post_type(self::SUBMISSION_POST_TYPE, array(
            'labels' => array(
                'name' => $this->text('submissions'),
                'singular_name' => $this->text('submission'),
            ),
            'public' => false,
            'show_ui' => false,
            'supports' => array('title'),
            'capability_type' => 'post',
        ));
    }

    public function register_taxonomy() {
        register_taxonomy(self::TAG_TAX, self::POST_TYPE, array(
            'labels' => array(
                'name' => $this->text('piece_tags'),
                'singular_name' => $this->text('piece_tag'),
            ),
            'hierarchical' => false,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'rewrite' => array('slug' => 'musikstueck-tag'),
        ));
    }

    public function add_meta_boxes() {
        add_meta_box(
            'cml_piece_details',
            $this->text('piece_information'),
            array($this, 'render_details_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'cml_piece_access',
            $this->text('visibility_groups'),
            array($this, 'render_access_meta_box'),
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'cml_piece_purchase',
            $this->text('purchase_settings'),
            array($this, 'render_purchase_meta_box'),
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public function enqueue_admin_assets($hook) {
        global $post_type;

        $is_cml_page = self::POST_TYPE === $post_type || false !== strpos((string) $hook, 'cml-submissions') || false !== strpos((string) $hook, 'cml-settings');
        if (!$is_cml_page) {
            return;
        }

        if (self::POST_TYPE === $post_type) {
            wp_enqueue_media();
        }
        wp_enqueue_style('cml-admin', plugins_url('assets/admin.css', __FILE__), array(), self::VERSION);
        wp_enqueue_script('cml-admin', plugins_url('assets/admin.js', __FILE__), array('jquery'), self::VERSION, true);
        wp_localize_script('cml-admin', 'cmlAdminLabels', array(
            'chooseFile' => $this->text('choose_file'),
            'useFile' => $this->text('use_file'),
            'change' => $this->text('change'),
            'remove' => $this->text('remove'),
        ));
    }

    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            $this->text('settings'),
            $this->text('settings'),
            'manage_options',
            'cml-settings',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            $this->text('submissions'),
            $this->text('submissions'),
            'manage_options',
            'cml-submissions',
            array($this, 'render_submissions_page')
        );
    }

    public function register_settings() {
        register_setting('cml_settings', self::OPTION_LANGUAGE, array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_language'),
            'default' => 'de',
        ));

        register_setting('cml_settings', self::OPTION_SUBMISSION_LEVELS, array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_scalar_list'),
            'default' => array(),
        ));

        register_setting('cml_settings', self::OPTION_WATERMARK_SCOPE, array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_watermark_scope'),
            'default' => 'all',
        ));

        register_setting('cml_settings', self::OPTION_WATERMARK_LEAD_TEXT, array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));

        register_setting('cml_settings', self::OPTION_WATERMARK_BRAND_TEXT, array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_watermark_brand_text'),
            'default' => '',
        ));

        register_setting('cml_settings', self::OPTION_WATERMARK_HIDE_BRAND_TEXT, array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 0,
        ));
    }

    public function sanitize_language($value) {
        return in_array($value, array('de', 'en'), true) ? $value : 'de';
    }

    public function sanitize_watermark_scope($value) {
        return in_array($value, array('all', 'paid'), true) ? $value : 'all';
    }

    public function sanitize_watermark_brand_text($value) {
        $value = sanitize_text_field($value);
        return trim($value);
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $language = $this->get_language();
        $submission_levels = $this->get_submission_levels();
        $membership_levels = $this->get_membership_levels();
        $watermark_scope = $this->get_watermark_scope();
        $watermark_lead_text = get_option(self::OPTION_WATERMARK_LEAD_TEXT, '');
        $watermark_brand_text = get_option(self::OPTION_WATERMARK_BRAND_TEXT, '');
        $hide_watermark_brand_text = (bool) get_option(self::OPTION_WATERMARK_HIDE_BRAND_TEXT, 0);
        if ('AllVoices' === $watermark_brand_text) {
            $watermark_brand_text = '';
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->text('settings')); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('cml_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="cml_language"><?php echo esc_html($this->text('language')); ?></label></th>
                        <td>
                            <select id="cml_language" name="<?php echo esc_attr(self::OPTION_LANGUAGE); ?>">
                                <option value="de" <?php selected($language, 'de'); ?>>Deutsch</option>
                                <option value="en" <?php selected($language, 'en'); ?>>English</option>
                            </select>
                            <p class="description"><?php echo esc_html($this->text('language_description')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html($this->text('submission_levels')); ?></th>
                        <td>
                            <?php if (empty($membership_levels)) : ?>
                                <p><em><?php echo esc_html($this->text('no_levels')); ?></em></p>
                            <?php endif; ?>
                            <?php foreach ($membership_levels as $level_id => $level_name) : ?>
                                <label class="cml-access-option">
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_SUBMISSION_LEVELS); ?>[]" value="<?php echo esc_attr($level_id); ?>" <?php checked(in_array((string) $level_id, $submission_levels, true)); ?>>
                                    <?php echo esc_html($level_name); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description"><?php echo esc_html($this->text('submission_levels_description')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cml_watermark_scope"><?php echo esc_html($this->text('watermark_scope')); ?></label></th>
                        <td>
                            <select id="cml_watermark_scope" name="<?php echo esc_attr(self::OPTION_WATERMARK_SCOPE); ?>">
                                <option value="all" <?php selected($watermark_scope, 'all'); ?>><?php echo esc_html($this->text('watermark_scope_all')); ?></option>
                                <option value="paid" <?php selected($watermark_scope, 'paid'); ?>><?php echo esc_html($this->text('watermark_scope_paid')); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html($this->text('watermark_scope_description')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cml_watermark_lead_text"><?php echo esc_html($this->text('watermark_lead_text')); ?></label></th>
                        <td>
                            <input id="cml_watermark_lead_text" type="text" name="<?php echo esc_attr(self::OPTION_WATERMARK_LEAD_TEXT); ?>" value="<?php echo esc_attr($watermark_lead_text); ?>" class="regular-text">
                            <p class="description"><?php echo esc_html($this->text('watermark_lead_text_description')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cml_watermark_brand_text"><?php echo esc_html($this->text('watermark_brand_text')); ?></label></th>
                        <td>
                            <input id="cml_watermark_brand_text" type="text" name="<?php echo esc_attr(self::OPTION_WATERMARK_BRAND_TEXT); ?>" value="<?php echo esc_attr($watermark_brand_text); ?>" class="regular-text">
                            <p class="description"><?php echo esc_html($this->text('watermark_brand_text_description')); ?></p>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_WATERMARK_HIDE_BRAND_TEXT); ?>" value="1" <?php checked($hide_watermark_brand_text); ?>>
                                <?php echo esc_html($this->text('watermark_hide_brand_text')); ?>
                            </label>
                            <p class="description"><?php echo esc_html($this->text('watermark_hide_brand_text_description')); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button($this->text('save_settings')); ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_frontend_assets() {
        wp_register_style('cml-frontend', plugins_url('assets/frontend.css', __FILE__), array(), self::VERSION);
        wp_register_script('cml-frontend', plugins_url('assets/frontend.js', __FILE__), array(), self::VERSION, true);

        if (is_singular(self::POST_TYPE) || $this->current_post_has_shortcode('chor_noten_uebersicht') || $this->current_post_has_shortcode('chor_musikstueck') || $this->current_post_has_shortcode('chor_musik_einreichen')) {
            wp_enqueue_style('cml-frontend');
            wp_enqueue_script('cml-frontend');
        }
    }

    public function filter_frontend_queries($query) {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        $post_type = $query->get('post_type');
        $targets_piece = self::POST_TYPE === $post_type || (is_array($post_type) && in_array(self::POST_TYPE, $post_type, true)) || $query->is_post_type_archive(self::POST_TYPE);

        if (!$targets_piece) {
            return;
        }

        if (current_user_can('edit_posts')) {
            return;
        }

        $level = $this->get_current_membership_level();
        if (false === $level) {
            $query->set('post__in', array(0));
            return;
        }

        $existing_meta_query = $query->get('meta_query');
        if (!is_array($existing_meta_query)) {
            $existing_meta_query = array();
        }

        $existing_meta_query[] = array(
            'relation' => 'OR',
            array(
                'key' => self::META_KEY,
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => self::META_KEY,
                'value' => 's:14:"allowed_levels";a:0:{}',
                'compare' => 'LIKE',
            ),
            array(
                'key' => self::META_KEY,
                'value' => '"' . $level . '"',
                'compare' => 'LIKE',
            ),
        );

        $query->set('meta_query', $existing_meta_query);
    }

    public function render_details_meta_box($post) {
        wp_nonce_field('cml_save_piece', 'cml_nonce');
        $data = $this->get_piece_data($post->ID);

        $fields = array(
            'composer' => $this->text('composer'),
            'lyricist' => $this->text('lyricist'),
            'arranger' => $this->text('arranger'),
            'voicing' => $this->text('voicing'),
            'extra_info' => $this->text('extra_info'),
            'singing_info' => $this->text('singing_info'),
        );
        ?>
        <div class="cml-admin-grid">
            <?php foreach ($fields as $key => $label) : ?>
                <p class="cml-field cml-field-<?php echo esc_attr($key); ?>">
                    <label for="cml_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                    <?php if (in_array($key, array('extra_info', 'singing_info'), true)) : ?>
                        <textarea id="cml_<?php echo esc_attr($key); ?>" name="cml[<?php echo esc_attr($key); ?>]" rows="4"><?php echo esc_textarea($data[$key]); ?></textarea>
                    <?php else : ?>
                        <input id="cml_<?php echo esc_attr($key); ?>" type="text" name="cml[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($data[$key]); ?>">
                    <?php endif; ?>
                </p>
            <?php endforeach; ?>
        </div>

        <?php
        $this->render_file_group('scores', $this->text('scores_pdf'), 'application/pdf', $data['scores']);
        $this->render_file_group('audio_samples', $this->text('audio_samples'), 'audio', $data['audio_samples']);
        $this->render_file_group('pronunciation', $this->text('pronunciation'), '', $data['pronunciation']);
        $this->render_file_group('misc', $this->text('misc'), '', $data['misc']);
    }

    private function render_file_group($key, $label, $library_type, $files) {
        ?>
        <div class="cml-file-group" data-group="<?php echo esc_attr($key); ?>" data-library-type="<?php echo esc_attr($library_type); ?>">
            <div class="cml-file-group-header">
                <h3><?php echo esc_html($label); ?></h3>
                <button type="button" class="button cml-add-file"><?php echo esc_html($this->text('add_file')); ?></button>
            </div>
            <div class="cml-file-list">
                <?php foreach ($files as $file) : ?>
                    <?php $this->render_file_row($key, $file); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private function render_file_row($group, $file) {
        $attachment_id = absint($file['id']);
        $title = $this->get_custom_file_title($file);
        $filename = $this->get_attachment_filename($attachment_id);
        ?>
        <div class="cml-file-row">
            <input type="hidden" name="cml[<?php echo esc_attr($group); ?>][]" value="<?php echo esc_attr($attachment_id); ?>" class="cml-file-id">
            <input type="text" name="cml[<?php echo esc_attr($group); ?>_titles][]" value="<?php echo esc_attr($title); ?>" class="cml-file-title" placeholder="<?php echo esc_attr($filename); ?>">
            <span class="cml-file-name"><?php echo esc_html($filename); ?></span>
            <button type="button" class="button cml-change-file"><?php echo esc_html($this->text('change')); ?></button>
            <button type="button" class="button-link-delete cml-remove-file"><?php echo esc_html($this->text('remove')); ?></button>
        </div>
        <?php
    }

    public function render_access_meta_box($post) {
        $data = $this->get_piece_data($post->ID);
        $levels = $this->get_membership_levels();
        ?>
        <p><?php echo esc_html($this->text('visibility_hint')); ?></p>
        <?php if (empty($levels)) : ?>
            <p><em><?php echo esc_html($this->text('no_levels')); ?></em></p>
        <?php endif; ?>
        <?php foreach ($levels as $level_id => $level_name) : ?>
            <label class="cml-access-option">
                <input type="checkbox" name="cml[allowed_levels][]" value="<?php echo esc_attr($level_id); ?>" <?php checked(in_array((string) $level_id, $data['allowed_levels'], true)); ?>>
                <?php echo esc_html($level_name); ?>
            </label>
        <?php endforeach; ?>
        <?php
    }

    public function render_purchase_meta_box($post) {
        $data = $this->get_piece_data($post->ID);
        ?>
        <p>
            <label class="cml-access-option">
                <input type="checkbox" name="cml[purchase_required]" value="1" <?php checked(!empty($data['purchase_required'])); ?>>
                <?php echo esc_html($this->text('purchase_required')); ?>
            </label>
        </p>
        <p>
            <label for="cml_purchase_key"><strong><?php echo esc_html($this->text('purchase_product_key')); ?></strong></label>
            <input id="cml_purchase_key" type="text" name="cml[purchase_product_key]" value="<?php echo esc_attr($data['purchase_product_key']); ?>" class="widefat">
        </p>
        <p class="description"><?php echo esc_html($this->text('purchase_product_key_hint')); ?></p>
        <p>
            <label for="cml_purchase_url"><strong><?php echo esc_html($this->text('purchase_url')); ?></strong></label>
            <input id="cml_purchase_url" type="url" name="cml[purchase_url]" value="<?php echo esc_attr($data['purchase_url']); ?>" class="widefat" placeholder="https://">
        </p>
        <p class="description"><?php echo esc_html($this->text('purchase_url_hint')); ?></p>
        <p>
            <label for="cml_purchase_shortcode"><strong><?php echo esc_html($this->text('purchase_shortcode')); ?></strong></label>
            <textarea id="cml_purchase_shortcode" name="cml[purchase_shortcode]" rows="5" class="widefat"><?php echo esc_textarea($data['purchase_shortcode']); ?></textarea>
        </p>
        <p class="description"><?php echo esc_html($this->text('purchase_shortcode_hint')); ?></p>
        <?php
    }

    public function save_piece($post_id, $post) {
        if (!isset($_POST['cml_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cml_nonce'])), 'cml_save_piece')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $raw = isset($_POST['cml']) && is_array($_POST['cml']) ? wp_unslash($_POST['cml']) : array();
        $data = array(
            'composer' => isset($raw['composer']) ? sanitize_text_field($raw['composer']) : '',
            'lyricist' => isset($raw['lyricist']) ? sanitize_text_field($raw['lyricist']) : '',
            'arranger' => isset($raw['arranger']) ? sanitize_text_field($raw['arranger']) : '',
            'voicing' => isset($raw['voicing']) ? sanitize_text_field($raw['voicing']) : '',
            'extra_info' => isset($raw['extra_info']) ? wp_kses_post($raw['extra_info']) : '',
            'singing_info' => isset($raw['singing_info']) ? wp_kses_post($raw['singing_info']) : '',
            'purchase_required' => !empty($raw['purchase_required']) ? 1 : 0,
            'purchase_product_key' => isset($raw['purchase_product_key']) ? sanitize_text_field($raw['purchase_product_key']) : '',
            'purchase_url' => isset($raw['purchase_url']) ? esc_url_raw($raw['purchase_url']) : '',
            'purchase_shortcode' => isset($raw['purchase_shortcode']) ? wp_kses_post($raw['purchase_shortcode']) : '',
            'allowed_levels' => $this->sanitize_scalar_list(isset($raw['allowed_levels']) ? $raw['allowed_levels'] : array()),
            'scores' => $this->sanitize_file_group($raw, 'scores'),
            'audio_samples' => $this->sanitize_file_group($raw, 'audio_samples'),
            'pronunciation' => $this->sanitize_file_group($raw, 'pronunciation'),
            'misc' => $this->sanitize_file_group($raw, 'misc'),
        );

        update_post_meta($post_id, self::META_KEY, $data);
    }

    public function handle_piece_submission() {
        if (!isset($_POST['cml_submission_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cml_submission_nonce'])), 'cml_submit_piece')) {
            $this->redirect_submission_result('error');
        }

        if (!$this->current_user_can_submit_music()) {
            $this->redirect_submission_result('error');
        }

        $type = isset($_POST['cml_submission_type']) ? sanitize_key(wp_unslash($_POST['cml_submission_type'])) : 'new_piece';
        if (!in_array($type, array('new_piece', 'existing_piece'), true)) {
            $type = 'new_piece';
        }

        $target_piece = isset($_POST['cml_submission_target']) ? absint($_POST['cml_submission_target']) : 0;
        if ('existing_piece' === $type && (!$target_piece || !$this->current_user_can_access_piece($target_piece))) {
            $this->redirect_submission_result('error');
        }

        $title = isset($_POST['cml_submission_title']) ? sanitize_text_field(wp_unslash($_POST['cml_submission_title'])) : '';
        if ('new_piece' === $type && '' === trim($title)) {
            $this->redirect_submission_result('error');
        }

        if ('existing_piece' === $type && '' === trim($title)) {
            $title = get_the_title($target_piece);
        }

        $raw = isset($_POST['cml']) && is_array($_POST['cml']) ? wp_unslash($_POST['cml']) : array();
        $data = $this->sanitize_submission_piece_data($raw);
        $files = $this->handle_submission_uploads();
        $data = array_merge($data, $files);

        if ('existing_piece' === $type && !$this->submission_has_content($data)) {
            $this->redirect_submission_result('error');
        }

        $submission_id = wp_insert_post(array(
            'post_type' => self::SUBMISSION_POST_TYPE,
            'post_status' => 'pending',
            'post_title' => $title,
            'post_author' => get_current_user_id(),
        ), true);

        if (is_wp_error($submission_id) || !$submission_id) {
            $this->redirect_submission_result('error');
        }

        update_post_meta($submission_id, self::SUBMISSION_META_KEY, array(
            'type' => $type,
            'target_piece' => $target_piece,
            'submitted_by' => get_current_user_id(),
            'submitted_level' => $this->get_current_membership_level(),
            'piece_data' => $data,
        ));

        $this->redirect_submission_result('sent');
    }

    public function handle_submission_review() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html($this->text('access_denied')));
        }

        $submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
        $review_action = isset($_POST['review_action']) ? sanitize_key(wp_unslash($_POST['review_action'])) : '';

        if (!$submission_id || !wp_verify_nonce(isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '', 'cml_review_submission_' . $submission_id)) {
            wp_die(esc_html($this->text('submission_error')));
        }

        if (!in_array($review_action, array('approve', 'reject'), true)) {
            wp_die(esc_html($this->text('submission_error')));
        }

        if ('reject' === $review_action) {
            update_post_meta($submission_id, '_cml_reviewed_by', get_current_user_id());
            update_post_meta($submission_id, '_cml_reviewed_at', current_time('mysql'));
            wp_update_post(array(
                'ID' => $submission_id,
                'post_status' => 'draft',
            ));
            $this->redirect_review_result('rejected');
        }

        $result = $this->approve_submission($submission_id);
        if (is_wp_error($result)) {
            $this->redirect_review_result('error');
        }

        update_post_meta($submission_id, '_cml_reviewed_by', get_current_user_id());
        update_post_meta($submission_id, '_cml_reviewed_at', current_time('mysql'));
        update_post_meta($submission_id, '_cml_approved_piece', absint($result));
        wp_update_post(array(
            'ID' => $submission_id,
            'post_status' => 'publish',
        ));

        $this->redirect_review_result('approved');
    }

    public function render_submissions_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $submissions = get_posts(array(
            'post_type' => self::SUBMISSION_POST_TYPE,
            'post_status' => 'pending',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'ASC',
        ));
        $status = isset($_GET['cml_review']) ? sanitize_key(wp_unslash($_GET['cml_review'])) : '';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->text('submissions')); ?></h1>
            <?php if ($status && 'error' !== $status) : ?>
                <div class="notice notice-success"><p><?php echo esc_html($this->text('submission_' . $status)); ?></p></div>
            <?php elseif ('error' === $status) : ?>
                <div class="notice notice-error"><p><?php echo esc_html($this->text('submission_error')); ?></p></div>
            <?php endif; ?>

            <?php if (empty($submissions)) : ?>
                <p><?php echo esc_html($this->text('no_submissions')); ?></p>
            <?php endif; ?>

            <?php foreach ($submissions as $submission) : ?>
                <?php $this->render_submission_review_card($submission); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_submission_review_card($submission) {
        $submission_data = $this->get_submission_data($submission->ID);
        $piece_data = $submission_data['piece_data'];
        $user = $submission_data['submitted_by'] ? get_user_by('id', $submission_data['submitted_by']) : null;
        $target = $submission_data['target_piece'] ? get_post($submission_data['target_piece']) : null;
        ?>
        <section class="cml-submission-card">
            <header>
                <h2><?php echo esc_html(get_the_title($submission)); ?></h2>
                <p>
                    <?php echo esc_html('new_piece' === $submission_data['type'] ? $this->text('submit_new_piece') : $this->text('submit_existing_piece_files')); ?>
                    <?php if ($target) : ?>
                        - <a href="<?php echo esc_url(get_edit_post_link($target->ID)); ?>"><?php echo esc_html(get_the_title($target)); ?></a>
                    <?php endif; ?>
                </p>
                <?php if ($user) : ?>
                    <p><?php echo esc_html($this->text('submitted_by')); ?>: <?php echo esc_html($user->display_name); ?></p>
                <?php endif; ?>
            </header>

            <dl class="cml-submission-facts">
                <?php foreach (array('composer', 'lyricist', 'arranger', 'voicing', 'extra_info', 'singing_info') as $field) : ?>
                    <?php if ('' === trim(wp_strip_all_tags((string) $piece_data[$field]))) {
                        continue;
                    } ?>
                    <div>
                        <dt><?php echo esc_html($this->text($field)); ?></dt>
                        <dd><?php echo wp_kses_post(wpautop($piece_data[$field])); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <?php foreach ($this->file_group_labels() as $group => $label) : ?>
                <?php if (empty($piece_data[$group])) {
                    continue;
                } ?>
                <div class="cml-submission-files">
                    <h3><?php echo esc_html($label); ?></h3>
                    <ul>
                        <?php foreach ($piece_data[$group] as $file) : ?>
                            <li>
                                <a href="<?php echo esc_url(wp_get_attachment_url(absint($file['id']))); ?>" target="_blank" rel="noopener"><?php echo esc_html($this->get_submission_file_label($file)); ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cml-review-actions">
                <?php wp_nonce_field('cml_review_submission_' . $submission->ID); ?>
                <input type="hidden" name="action" value="cml_review_submission">
                <input type="hidden" name="submission_id" value="<?php echo esc_attr($submission->ID); ?>">
                <button type="submit" name="review_action" value="approve" class="button button-primary"><?php echo esc_html($this->text('approve_submission')); ?></button>
                <button type="submit" name="review_action" value="reject" class="button"><?php echo esc_html($this->text('reject_submission')); ?></button>
            </form>
        </section>
        <?php
    }

    private function sanitize_file_group($raw, $key) {
        $ids = isset($raw[$key]) && is_array($raw[$key]) ? $raw[$key] : array();
        $titles = isset($raw[$key . '_titles']) && is_array($raw[$key . '_titles']) ? $raw[$key . '_titles'] : array();
        $files = array();

        foreach ($ids as $index => $id) {
            $attachment_id = absint($id);
            if (!$attachment_id || 'attachment' !== get_post_type($attachment_id)) {
                continue;
            }

            $files[] = array(
                'id' => $attachment_id,
                'title' => isset($titles[$index]) ? sanitize_text_field($titles[$index]) : '',
            );
        }

        return $files;
    }

    private function sanitize_submission_piece_data($raw) {
        return array(
            'composer' => isset($raw['composer']) ? sanitize_text_field($raw['composer']) : '',
            'lyricist' => isset($raw['lyricist']) ? sanitize_text_field($raw['lyricist']) : '',
            'arranger' => isset($raw['arranger']) ? sanitize_text_field($raw['arranger']) : '',
            'voicing' => isset($raw['voicing']) ? sanitize_text_field($raw['voicing']) : '',
            'extra_info' => isset($raw['extra_info']) ? wp_kses_post($raw['extra_info']) : '',
            'singing_info' => isset($raw['singing_info']) ? wp_kses_post($raw['singing_info']) : '',
            'purchase_required' => 0,
            'purchase_product_key' => '',
            'purchase_url' => '',
            'purchase_shortcode' => '',
            'allowed_levels' => array(),
            'scores' => array(),
            'audio_samples' => array(),
            'pronunciation' => array(),
            'misc' => array(),
        );
    }

    private function handle_submission_uploads() {
        $uploads = array(
            'scores' => array(),
            'audio_samples' => array(),
            'pronunciation' => array(),
            'misc' => array(),
        );

        if (empty($_FILES['cml_files']) || !is_array($_FILES['cml_files'])) {
            return $uploads;
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $titles = isset($_POST['cml_file_titles']) && is_array($_POST['cml_file_titles']) ? wp_unslash($_POST['cml_file_titles']) : array();
        $files = $_FILES['cml_files'];

        foreach (array_keys($uploads) as $group) {
            if (empty($files['name'][$group]) || !is_array($files['name'][$group])) {
                continue;
            }

            foreach ($files['name'][$group] as $index => $name) {
                if (empty($name) || !isset($files['tmp_name'][$group][$index])) {
                    continue;
                }

                $file = array(
                    'name' => sanitize_file_name($name),
                    'type' => isset($files['type'][$group][$index]) ? $files['type'][$group][$index] : '',
                    'tmp_name' => $files['tmp_name'][$group][$index],
                    'error' => isset($files['error'][$group][$index]) ? $files['error'][$group][$index] : 0,
                    'size' => isset($files['size'][$group][$index]) ? $files['size'][$group][$index] : 0,
                );

                if (!empty($file['error'])) {
                    continue;
                }

                $uploaded = wp_handle_upload($file, array('test_form' => false));
                if (empty($uploaded['file']) || !empty($uploaded['error'])) {
                    continue;
                }

                $attachment_id = wp_insert_attachment(array(
                    'post_mime_type' => isset($uploaded['type']) ? $uploaded['type'] : '',
                    'post_title' => preg_replace('/\.[^.]+$/', '', basename($uploaded['file'])),
                    'post_content' => '',
                    'post_status' => 'inherit',
                    'post_author' => get_current_user_id(),
                ), $uploaded['file']);

                if (!$attachment_id || is_wp_error($attachment_id)) {
                    continue;
                }

                $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
                wp_update_attachment_metadata($attachment_id, $metadata);

                $uploads[$group][] = array(
                    'id' => absint($attachment_id),
                    'title' => isset($titles[$group]) ? sanitize_text_field($titles[$group]) : '',
                );
            }
        }

        return $uploads;
    }

    private function submission_has_content($data) {
        foreach (array('composer', 'lyricist', 'arranger', 'voicing', 'extra_info', 'singing_info') as $field) {
            if ('' !== trim(wp_strip_all_tags((string) $data[$field]))) {
                return true;
            }
        }

        foreach (array('scores', 'audio_samples', 'pronunciation', 'misc') as $group) {
            if (!empty($data[$group])) {
                return true;
            }
        }

        return false;
    }

    private function approve_submission($submission_id) {
        $submission = get_post($submission_id);
        if (!$submission || self::SUBMISSION_POST_TYPE !== $submission->post_type || 'pending' !== $submission->post_status) {
            return new WP_Error('cml_invalid_submission', $this->text('submission_error'));
        }

        $submission_data = $this->get_submission_data($submission_id);
        $piece_data = $submission_data['piece_data'];

        if ('new_piece' === $submission_data['type']) {
            $piece_id = wp_insert_post(array(
                'post_type' => self::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => get_the_title($submission),
                'post_author' => $submission_data['submitted_by'] ? absint($submission_data['submitted_by']) : get_current_user_id(),
            ), true);

            if (is_wp_error($piece_id) || !$piece_id) {
                return new WP_Error('cml_create_piece_failed', $this->text('submission_error'));
            }

            update_post_meta($piece_id, self::META_KEY, $piece_data);
            $this->attach_submission_files_to_piece($piece_data, $piece_id);
            return $piece_id;
        }

        $target_piece = absint($submission_data['target_piece']);
        if (!$target_piece || self::POST_TYPE !== get_post_type($target_piece)) {
            return new WP_Error('cml_missing_target_piece', $this->text('submission_error'));
        }

        $current = $this->get_piece_data($target_piece);
        foreach (array('composer', 'lyricist', 'arranger', 'voicing', 'extra_info', 'singing_info') as $field) {
            if ('' !== trim(wp_strip_all_tags((string) $piece_data[$field]))) {
                $current[$field] = $piece_data[$field];
            }
        }
        foreach (array('scores', 'audio_samples', 'pronunciation', 'misc') as $group) {
            if (!empty($piece_data[$group])) {
                $current[$group] = array_merge($current[$group], $piece_data[$group]);
            }
        }

        if ('' !== trim(get_the_title($submission)) && get_the_title($submission) !== get_the_title($target_piece)) {
            wp_update_post(array(
                'ID' => $target_piece,
                'post_title' => get_the_title($submission),
            ));
        }

        update_post_meta($target_piece, self::META_KEY, $current);
        $this->attach_submission_files_to_piece($piece_data, $target_piece);
        return $target_piece;
    }

    private function attach_submission_files_to_piece($piece_data, $piece_id) {
        foreach (array('scores', 'audio_samples', 'pronunciation', 'misc') as $group) {
            foreach ($piece_data[$group] as $file) {
                $attachment_id = isset($file['id']) ? absint($file['id']) : 0;
                if ($attachment_id) {
                    wp_update_post(array(
                        'ID' => $attachment_id,
                        'post_parent' => $piece_id,
                    ));
                }
            }
        }
    }

    public function sanitize_scalar_list($values) {
        if (!is_array($values)) {
            return array();
        }

        return array_values(array_unique(array_filter(array_map('sanitize_text_field', $values))));
    }

    public function render_single_content($content) {
        if (!is_singular(self::POST_TYPE) || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        return $this->render_piece(get_the_ID(), false);
    }

    public function piece_shortcode($atts) {
        $atts = shortcode_atts(array('id' => get_the_ID()), $atts, 'chor_musikstueck');
        return $this->render_piece(absint($atts['id']));
    }

    public function submission_shortcode($atts) {
        wp_enqueue_style('cml-frontend');
        wp_enqueue_script('cml-frontend');

        if (!$this->current_user_can_submit_music()) {
            return '<div class="cml-access-denied">' . esc_html($this->text('submission_access_denied')) . '</div>';
        }

        $pieces = get_posts(array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        usort($pieces, array($this, 'sort_pieces_by_title'));
        $piece_form_data = array();

        foreach ($pieces as $piece) {
            if (!$this->current_user_can_access_piece($piece->ID)) {
                continue;
            }

            $data = $this->get_piece_data($piece->ID);
            $piece_form_data[(string) $piece->ID] = array(
                'title' => get_the_title($piece),
                'composer' => $data['composer'],
                'lyricist' => $data['lyricist'],
                'arranger' => $data['arranger'],
                'voicing' => $data['voicing'],
                'extra_info' => $data['extra_info'],
                'singing_info' => $data['singing_info'],
            );
        }

        $status = isset($_GET['cml_submission']) ? sanitize_key(wp_unslash($_GET['cml_submission'])) : '';

        ob_start();
        ?>
        <section class="cml-submission">
            <?php if ('sent' === $status) : ?>
                <div class="cml-notice cml-notice-success"><?php echo esc_html($this->text('submission_sent')); ?></div>
            <?php elseif ('error' === $status) : ?>
                <div class="cml-notice cml-notice-error"><?php echo esc_html($this->text('submission_error')); ?></div>
            <?php endif; ?>

            <form class="cml-submission-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-cml-submission-form>
                <?php wp_nonce_field('cml_submit_piece', 'cml_submission_nonce'); ?>
                <input type="hidden" name="action" value="cml_submit_piece">
                <h2><?php echo esc_html($this->text('submit_music')); ?></h2>

                <fieldset>
                    <legend><?php echo esc_html($this->text('submission_type')); ?></legend>
                    <label>
                        <input type="radio" name="cml_submission_type" value="new_piece" checked>
                        <?php echo esc_html($this->text('submit_new_piece')); ?>
                    </label>
                    <label>
                        <input type="radio" name="cml_submission_type" value="existing_piece">
                        <?php echo esc_html($this->text('submit_existing_piece_files')); ?>
                    </label>
                </fieldset>

                <p class="cml-field cml-field-target" data-cml-existing-piece-select hidden>
                    <label for="cml_submission_target"><?php echo esc_html($this->text('existing_piece')); ?></label>
                    <select id="cml_submission_target" name="cml_submission_target">
                        <option value="0"><?php echo esc_html($this->text('choose_existing_piece')); ?></option>
                        <?php foreach ($pieces as $piece) : ?>
                            <?php if (!isset($piece_form_data[(string) $piece->ID])) {
                                continue;
                            } ?>
                            <option value="<?php echo esc_attr($piece->ID); ?>"><?php echo esc_html(get_the_title($piece)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>

                <div class="cml-submission-grid">
                    <p class="cml-field cml-field-title">
                        <label for="cml_submission_title"><?php echo esc_html($this->text('songname')); ?></label>
                        <input id="cml_submission_title" type="text" name="cml_submission_title">
                    </p>
                    <p class="cml-field">
                        <label for="cml_submission_composer"><?php echo esc_html($this->text('composer')); ?></label>
                        <input id="cml_submission_composer" type="text" name="cml[composer]">
                    </p>
                    <p class="cml-field">
                        <label for="cml_submission_lyricist"><?php echo esc_html($this->text('lyricist')); ?></label>
                        <input id="cml_submission_lyricist" type="text" name="cml[lyricist]">
                    </p>
                    <p class="cml-field">
                        <label for="cml_submission_arranger"><?php echo esc_html($this->text('arranger')); ?></label>
                        <input id="cml_submission_arranger" type="text" name="cml[arranger]">
                    </p>
                    <p class="cml-field">
                        <label for="cml_submission_voicing"><?php echo esc_html($this->text('voicing')); ?></label>
                        <input id="cml_submission_voicing" type="text" name="cml[voicing]">
                    </p>
                    <p class="cml-field cml-field-extra_info">
                        <label for="cml_submission_extra_info"><?php echo esc_html($this->text('extra_info')); ?></label>
                        <textarea id="cml_submission_extra_info" name="cml[extra_info]" rows="4"></textarea>
                    </p>
                    <p class="cml-field cml-field-singing_info">
                        <label for="cml_submission_singing_info"><?php echo esc_html($this->text('singing_info')); ?></label>
                        <textarea id="cml_submission_singing_info" name="cml[singing_info]" rows="4"></textarea>
                    </p>
                </div>

                <?php $this->render_frontend_upload_field('scores', $this->text('scores_pdf'), 'application/pdf'); ?>
                <?php $this->render_frontend_upload_field('audio_samples', $this->text('audio_samples'), 'audio/*'); ?>
                <?php $this->render_frontend_upload_field('pronunciation', $this->text('pronunciation'), ''); ?>
                <?php $this->render_frontend_upload_field('misc', $this->text('misc'), ''); ?>

                <p class="cml-submission-note"><?php echo esc_html($this->text('submission_review_hint')); ?></p>
                <button type="submit" class="cml-submit-button"><?php echo esc_html($this->text('send_submission')); ?></button>
                <script type="application/json" data-cml-existing-pieces><?php echo wp_json_encode($piece_form_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
            </form>
            <?php $this->render_plugin_credit(); ?>
        </section>
        <?php

        return ob_get_clean();
    }

    private function render_frontend_upload_field($key, $label, $accept) {
        ?>
        <div class="cml-upload-group">
            <label for="cml_upload_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
            <input id="cml_upload_<?php echo esc_attr($key); ?>" type="file" name="cml_files[<?php echo esc_attr($key); ?>][]" <?php echo $accept ? 'accept="' . esc_attr($accept) . '"' : ''; ?> multiple>
            <input type="text" name="cml_file_titles[<?php echo esc_attr($key); ?>]" placeholder="<?php echo esc_attr($this->text('optional_file_title')); ?>">
        </div>
        <?php
    }

    public function overview_shortcode($atts) {
        wp_enqueue_style('cml-frontend');
        wp_enqueue_script('cml-frontend');

        $pieces = get_posts(array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
        usort($pieces, array($this, 'sort_pieces_by_title'));

        ob_start();
        ?>
        <section class="cml-overview" data-cml-overview>
            <div class="cml-overview-toolbar">
                <div class="cml-view-switch" role="group" aria-label="<?php echo esc_attr($this->text('switch_view')); ?>">
                    <button type="button" class="is-active" data-cml-view="grid"><?php echo esc_html($this->text('tiles')); ?></button>
                    <button type="button" data-cml-view="list"><?php echo esc_html($this->text('list')); ?></button>
                </div>
                <div class="cml-search-toolbar-actions">
                    <button type="button" class="cml-search-open" data-cml-search-open><?php echo esc_html($this->text('search')); ?></button>
                    <button type="button" class="cml-search-reset" data-cml-search-reset hidden><?php echo esc_html($this->text('reset_search_filter')); ?></button>
                </div>
            </div>

            <div class="cml-piece-collection is-grid" data-cml-collection>
                <?php foreach ($pieces as $piece) : ?>
                    <?php if (!$this->current_user_can_access_piece($piece->ID)) {
                        continue;
                    } ?>
                    <?php $this->render_overview_item($piece); ?>
                <?php endforeach; ?>
            </div>

            <div class="cml-search-overlay" data-cml-search-overlay hidden>
                <div class="cml-search-panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr($this->text('search_pieces')); ?>">
                    <button type="button" class="cml-search-close" data-cml-search-close aria-label="<?php echo esc_attr($this->text('close_search')); ?>">&times;</button>
                    <h2><?php echo esc_html($this->text('search_pieces')); ?></h2>
                    <label>
                        <span><?php echo esc_html($this->text('songname')); ?></span>
                        <input type="search" data-cml-filter="title">
                    </label>
                    <label>
                        <span><?php echo esc_html($this->text('composer')); ?></span>
                        <input type="search" data-cml-filter="composer">
                    </label>
                    <label>
                        <span><?php echo esc_html($this->text('tag')); ?></span>
                        <input type="search" data-cml-filter="tags">
                    </label>
                    <div class="cml-search-actions">
                        <button type="button" data-cml-search-reset><?php echo esc_html($this->text('reset')); ?></button>
                        <button type="button" data-cml-search-close><?php echo esc_html($this->text('apply')); ?></button>
                    </div>
                </div>
            </div>
            <?php $this->render_plugin_credit(); ?>
        </section>
        <?php

        return ob_get_clean();
    }

    private function render_overview_item($piece) {
        $data = $this->get_piece_data($piece->ID);
        $tags = wp_get_post_terms($piece->ID, self::TAG_TAX, array('fields' => 'names'));
        ?>
        <article class="cml-overview-item" data-title="<?php echo esc_attr(strtolower($piece->post_title)); ?>" data-composer="<?php echo esc_attr(strtolower($data['composer'])); ?>" data-tags="<?php echo esc_attr(strtolower(implode(' ', $tags))); ?>">
            <div class="cml-overview-row">
                <h3><a href="<?php echo esc_url(get_permalink($piece)); ?>"><?php echo esc_html(get_the_title($piece)); ?></a></h3>
                <?php if ($data['composer']) : ?>
                    <p class="cml-overview-composer"><?php echo esc_html($data['composer']); ?></p>
                <?php endif; ?>
                <?php if (!empty($tags)) : ?>
                    <div class="cml-tags">
                        <?php foreach ($tags as $tag) : ?>
                            <button type="button" class="cml-tag-filter" data-cml-tag="<?php echo esc_attr($tag); ?>"><?php echo esc_html($tag); ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }

    private function render_piece($post_id, $show_title = true) {
        $post = get_post($post_id);

        if (!$post || self::POST_TYPE !== $post->post_type) {
            return '';
        }

        wp_enqueue_style('cml-frontend');
        wp_enqueue_script('cml-frontend');

        if (!$this->current_user_can_access_piece($post_id)) {
            return '<div class="cml-access-denied">' . esc_html($this->text('access_denied')) . '</div>';
        }

        $data = $this->get_piece_data($post_id);
        $tags = wp_get_post_terms($post_id, self::TAG_TAX, array('fields' => 'names'));
        $overview_url = $this->get_overview_url();

        ob_start();
        ?>
        <article class="cml-piece">
            <?php if ($overview_url) : ?>
                <a class="cml-back-link" href="<?php echo esc_url($overview_url); ?>"><?php echo esc_html($this->text('back_to_overview')); ?></a>
            <?php endif; ?>
            <header class="cml-piece-header">
                <?php if ($show_title) : ?>
                    <h1><?php echo esc_html(get_the_title($post)); ?></h1>
                <?php endif; ?>
                <dl class="cml-facts">
                    <?php $this->render_fact($this->text('composer'), $data['composer'], false, 'cml-fact-compact'); ?>
                    <?php $this->render_fact($this->text('lyricist'), $data['lyricist'], false, 'cml-fact-compact'); ?>
                    <?php $this->render_fact($this->text('arranger'), $data['arranger'], false, 'cml-fact-compact'); ?>
                    <?php $this->render_fact($this->text('voicing'), $data['voicing'], false, 'cml-fact-compact'); ?>
                    <?php $this->render_fact($this->text('extra_info'), wpautop($data['extra_info']), true, 'cml-fact-full'); ?>
                    <?php $this->render_fact($this->text('singing_info'), wpautop($data['singing_info']), true, 'cml-fact-full'); ?>
                </dl>
                <?php if (!empty($tags)) : ?>
                    <div class="cml-tags">
                        <?php foreach ($tags as $tag) : ?>
                            <span><?php echo esc_html($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </header>

            <?php if ($this->current_user_can_download_piece($post_id)) : ?>
                <?php $this->render_file_section($post_id, $this->text('scores'), $data['scores'], 'download'); ?>
                <?php $this->render_file_section($post_id, $this->text('audio_samples'), $data['audio_samples'], 'audio'); ?>
                <?php $this->render_file_section($post_id, $this->text('pronunciation'), $data['pronunciation'], 'mixed', true); ?>
                <?php $this->render_file_section($post_id, $this->text('misc'), $data['misc'], 'download', true); ?>
            <?php else : ?>
                <?php $this->render_purchase_panel($data); ?>
            <?php endif; ?>
            <?php $this->render_plugin_credit(); ?>
        </article>
        <?php

        return ob_get_clean();
    }

    private function render_purchase_panel($data) {
        ?>
        <section class="cml-purchase-panel">
            <h2><?php echo esc_html($this->text('purchase_needed')); ?></h2>
            <p><?php echo esc_html($this->text('purchase_needed_hint')); ?></p>
            <?php if (!empty($data['purchase_url'])) : ?>
                <p>
                    <a class="cml-purchase-link" href="<?php echo esc_url($data['purchase_url']); ?>">
                        <?php echo esc_html($this->text('purchase_now')); ?>
                    </a>
                </p>
            <?php endif; ?>
            <?php if (!empty($data['purchase_shortcode'])) : ?>
                <div class="cml-purchase-shortcode">
                    <?php echo do_shortcode(wp_kses_post($data['purchase_shortcode'])); ?>
                </div>
            <?php elseif (empty($data['purchase_url'])) : ?>
                <p><em><?php echo esc_html($this->text('purchase_missing_link')); ?></em></p>
            <?php endif; ?>
        </section>
        <?php
    }

    private function render_plugin_credit() {
        ?>
        <footer class="cml-plugin-credit">Choir Music Library - Fabian Kaltenecker - Version <?php echo esc_html(self::VERSION); ?></footer>
        <?php
    }

    private function render_fact($label, $value, $html = false, $class_name = '') {
        if ('' === trim(wp_strip_all_tags((string) $value))) {
            return;
        }
        ?>
        <div class="<?php echo esc_attr($class_name); ?>">
            <dt><?php echo esc_html($label); ?></dt>
            <dd><?php echo $html ? wp_kses_post($value) : esc_html($value); ?></dd>
        </div>
        <?php
    }

    private function render_file_section($post_id, $title, $files, $mode, $optional = false) {
        if (empty($files) && $optional) {
            return;
        }

        if (empty($files)) {
            return;
        }
        ?>
        <section class="cml-file-section">
            <h2><?php echo esc_html($title); ?></h2>
            <ul class="cml-file-items">
                <?php foreach ($files as $file) : ?>
                    <?php $this->render_frontend_file($post_id, $file, $mode); ?>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php
    }

    private function render_frontend_file($post_id, $file, $mode) {
        $attachment_id = absint($file['id']);
        $mime = get_post_mime_type($attachment_id);
        $custom_title = $this->get_custom_file_title($file);
        $title = $custom_title ? $custom_title : $this->get_attachment_filename($attachment_id);
        $download_url = $this->file_url($post_id, $attachment_id, false);
        $stream_url = $this->file_url($post_id, $attachment_id, true);
        $is_audio = 0 === strpos((string) $mime, 'audio/');
        ?>
        <li class="cml-file-item">
            <div>
                <strong><?php echo esc_html($title); ?></strong>
                <?php if ('audio' === $mode || ('mixed' === $mode && $is_audio)) : ?>
                    <audio controls preload="metadata" src="<?php echo esc_url($stream_url); ?>"></audio>
                <?php endif; ?>
            </div>
            <a class="cml-download-link" href="<?php echo esc_url($download_url); ?>"><?php echo esc_html($this->text('download')); ?></a>
        </li>
        <?php
    }

    public function serve_file() {
        $piece_id = isset($_GET['piece']) ? absint($_GET['piece']) : 0;
        $attachment_id = isset($_GET['file']) ? absint($_GET['file']) : 0;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (!$piece_id || !$attachment_id || !wp_verify_nonce($nonce, 'cml_file_' . $piece_id . '_' . $attachment_id)) {
            status_header(403);
            exit;
        }

        if (!$this->current_user_can_access_piece($piece_id) || !$this->current_user_can_download_piece($piece_id) || !$this->piece_has_attachment($piece_id, $attachment_id)) {
            status_header(403);
            exit;
        }

        $path = get_attached_file($attachment_id);
        if (!$path || !file_exists($path)) {
            status_header(404);
            exit;
        }

        $inline = isset($_GET['inline']) && '1' === $_GET['inline'];
        $mime = get_post_mime_type($attachment_id);
        $filename = basename($path);
        $served_path = $this->prepare_download_path($path, $piece_id, $attachment_id, $mime);

        if (is_wp_error($served_path)) {
            status_header(500);
            wp_die(esc_html($served_path->get_error_message()));
        }

        if ($served_path && file_exists($served_path)) {
            $path = $served_path;
        }

        nocache_headers();
        header('Content-Type: ' . ($mime ? $mime : 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($filename) . '"');
        readfile($path);
        exit;
    }

    private function file_url($post_id, $attachment_id, $inline) {
        return wp_nonce_url(
            add_query_arg(array(
                'action' => 'cml_file',
                'piece' => absint($post_id),
                'file' => absint($attachment_id),
                'inline' => $inline ? '1' : '0',
            ), admin_url('admin-post.php')),
            'cml_file_' . absint($post_id) . '_' . absint($attachment_id)
        );
    }

    private function piece_has_attachment($post_id, $attachment_id) {
        $data = $this->get_piece_data($post_id);
        foreach (array('scores', 'audio_samples', 'pronunciation', 'misc') as $group) {
            foreach ($data[$group] as $file) {
                if (absint($file['id']) === $attachment_id) {
                    return true;
                }
            }
        }

        return false;
    }

    public function handle_wpsc_payment() {
        $args = func_get_args();
        $email = $this->extract_payment_email($args);
        $payment_text = $this->normalize_product_key(implode(' ', $this->flatten_payment_values($args)));

        if (!$email || !$payment_text) {
            return;
        }

        $keys = $this->get_paid_product_keys();

        foreach ($keys as $key) {
            if (false !== strpos($payment_text, $this->normalize_product_key($key))) {
                $this->grant_product_access($email, $key);
            }
        }
    }

    private function current_user_can_download_piece($post_id) {
        if (current_user_can('edit_post', $post_id)) {
            return true;
        }

        $data = $this->get_piece_data($post_id);
        if (empty($data['purchase_required'])) {
            return true;
        }

        $key = trim((string) $data['purchase_product_key']);
        if ('' === $key) {
            return false;
        }

        return $this->current_user_has_product_access($key);
    }

    private function current_user_has_product_access($key) {
        $email = $this->get_current_member_email();
        if (!$email) {
            return false;
        }

        $purchases = get_option(self::OPTION_PURCHASES, array());
        $email_key = strtolower($email);
        $keys = isset($purchases[$email_key]) && is_array($purchases[$email_key]) ? $purchases[$email_key] : array();

        $user_id = get_current_user_id();
        if ($user_id) {
            $user_keys = get_user_meta($user_id, self::USER_PURCHASE_META, true);
            if (is_array($user_keys)) {
                $keys = array_merge($keys, $user_keys);
            }
        }

        return in_array($this->normalize_product_key($key), array_map(array($this, 'normalize_product_key'), $keys), true);
    }

    private function grant_product_access($email, $key) {
        $email = sanitize_email($email);
        if (!$email) {
            return;
        }

        $purchases = get_option(self::OPTION_PURCHASES, array());
        if (!is_array($purchases)) {
            $purchases = array();
        }

        $email_key = strtolower($email);
        $keys = isset($purchases[$email_key]) && is_array($purchases[$email_key]) ? $purchases[$email_key] : array();
        $keys[] = trim((string) $key);
        $purchases[$email_key] = array_values(array_unique(array_filter($keys)));
        update_option(self::OPTION_PURCHASES, $purchases, false);

        $user = get_user_by('email', $email);
        if (!$user) {
            return;
        }

        $keys = get_user_meta($user->ID, self::USER_PURCHASE_META, true);
        if (!is_array($keys)) {
            $keys = array();
        }

        $keys[] = trim((string) $key);
        $keys = array_values(array_unique(array_filter($keys)));
        update_user_meta($user->ID, self::USER_PURCHASE_META, $keys);
    }

    private function get_current_member_email() {
        $user = wp_get_current_user();
        if ($user && $user->exists() && $user->user_email) {
            return sanitize_email($user->user_email);
        }

        if (class_exists('SwpmMemberUtils')) {
            if (method_exists('SwpmMemberUtils', 'get_logged_in_members_email')) {
                return sanitize_email(SwpmMemberUtils::get_logged_in_members_email());
            }

            if (method_exists('SwpmMemberUtils', 'get_logged_in_member_email')) {
                return sanitize_email(SwpmMemberUtils::get_logged_in_member_email());
            }

            if (method_exists('SwpmMemberUtils', 'get_logged_in_members_data')) {
                $member = SwpmMemberUtils::get_logged_in_members_data();
                if (is_object($member) && !empty($member->email)) {
                    return sanitize_email($member->email);
                }
                if (is_array($member) && !empty($member['email'])) {
                    return sanitize_email($member['email']);
                }
            }
        }

        return '';
    }

    private function get_paid_product_keys() {
        $posts = get_posts(array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => self::META_KEY,
                    'value' => 's:17:"purchase_required";i:1;',
                    'compare' => 'LIKE',
                ),
            ),
        ));
        $keys = array();

        foreach ($posts as $post_id) {
            $data = $this->get_piece_data($post_id);
            if (!empty($data['purchase_product_key'])) {
                $keys[] = $data['purchase_product_key'];
            }
        }

        return array_values(array_unique($keys));
    }

    private function extract_payment_email($values) {
        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                $email = $this->extract_payment_email((array) $value);
                if ($email) {
                    return $email;
                }
                continue;
            }

            if (is_scalar($value) && is_email((string) $value)) {
                return sanitize_email((string) $value);
            }

            if (is_scalar($value) && preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', (string) $value, $matches)) {
                return sanitize_email($matches[0]);
            }
        }

        return '';
    }

    private function flatten_payment_values($values) {
        $flat = array();

        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                $flat = array_merge($flat, $this->flatten_payment_values((array) $value));
            } elseif (is_scalar($value)) {
                $flat[] = (string) $value;
            }
        }

        return $flat;
    }

    private function normalize_product_key($value) {
        return strtolower(trim(wp_strip_all_tags((string) $value)));
    }

    private function prepare_download_path($path, $piece_id, $attachment_id, $mime) {
        if ('application/pdf' !== $mime) {
            return $path;
        }

        if (!$this->should_watermark_piece($piece_id)) {
            return $path;
        }

        return $this->create_watermarked_pdf($path, $piece_id, $attachment_id);
    }

    private function should_watermark_piece($piece_id) {
        if ('all' === $this->get_watermark_scope()) {
            return true;
        }

        $data = $this->get_piece_data($piece_id);
        return !empty($data['purchase_required']);
    }

    private function create_watermarked_pdf($path, $piece_id, $attachment_id) {
        if (!$this->load_pdf_libraries()) {
            return new WP_Error('cml_pdf_library_missing', $this->text('pdf_library_missing'));
        }

        $upload_dir = wp_upload_dir();
        if (empty($upload_dir['basedir'])) {
            return new WP_Error('cml_pdf_upload_dir_missing', $this->text('pdf_watermark_failed'));
        }

        $cache_dir = trailingslashit($upload_dir['basedir']) . 'cml-watermarked';
        if (!wp_mkdir_p($cache_dir)) {
            return new WP_Error('cml_pdf_cache_dir_missing', $this->text('pdf_watermark_failed'));
        }

        $watermark = $this->get_watermark_text();
        $cache_key = md5($path . '|' . filemtime($path) . '|' . get_current_user_id() . '|' . $this->get_current_member_email() . '|' . $watermark);
        $target = trailingslashit($cache_dir) . $attachment_id . '-' . $piece_id . '-' . $cache_key . '.pdf';

        if (file_exists($target)) {
            return $target;
        }

        try {
            $pdf = new CML_Watermarked_FPDI();
            $pdf->SetAutoPageBreak(false);
            $page_count = $pdf->setSourceFile($path);

            for ($page = 1; $page <= $page_count; $page++) {
                $template_id = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template_id);
                $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

                $pdf->AddPage($orientation, array($size['width'], $size['height']));
                $pdf->useTemplate($template_id);
                $pdf->addSideWatermark($watermark, $size['width'], $size['height']);
            }

            $pdf->Output('F', $target);
        } catch (Exception $exception) {
            return new WP_Error('cml_pdf_watermark_failed', $this->text('pdf_watermark_failed'));
        }

        return file_exists($target) ? $target : new WP_Error('cml_pdf_watermark_failed', $this->text('pdf_watermark_failed'));
    }

    private function load_pdf_libraries() {
        if (!class_exists('FPDF')) {
            $fpdf_path = __DIR__ . '/vendor/fpdf/fpdf.php';
            if (!file_exists($fpdf_path)) {
                return false;
            }
            require_once $fpdf_path;
        }

        if (!class_exists('setasign\\Fpdi\\Fpdi')) {
            $fpdi_autoload = __DIR__ . '/vendor/fpdi/src/autoload.php';
            if (!file_exists($fpdi_autoload)) {
                return false;
            }
            require_once $fpdi_autoload;
        }

        if (!class_exists('CML_Watermarked_FPDI')) {
            $watermark_class = __DIR__ . '/vendor/cml-watermarked-fpdi.php';
            if (!file_exists($watermark_class)) {
                return false;
            }
            require_once $watermark_class;
        }

        return class_exists('FPDF') && class_exists('setasign\\Fpdi\\Fpdi') && class_exists('CML_Watermarked_FPDI');
    }

    private function get_watermark_text() {
        $email = $this->get_current_member_email();
        $name = $this->get_current_member_display_name();

        if (!$name && $email) {
            $name = $email;
        }

        if (!$email) {
            $email = 'unknown';
        }

        if (!$name) {
            $name = 'Unknown';
        }

        $parts = array_filter(array(
            trim((string) get_option(self::OPTION_WATERMARK_LEAD_TEXT, '')),
            $this->should_hide_watermark_brand_text() ? '' : $this->get_watermark_brand_text(),
            $name,
            $email,
            current_time('d.m.Y'),
        ));

        return implode(' · ', $parts);
    }

    private function get_current_member_display_name() {
        $user = wp_get_current_user();
        if ($user && $user->exists()) {
            $name = trim($user->display_name);
            if ($name) {
                return $name;
            }
        }

        if (class_exists('SwpmMemberUtils') && method_exists('SwpmMemberUtils', 'get_logged_in_members_data')) {
            $member = SwpmMemberUtils::get_logged_in_members_data();
            if (is_object($member)) {
                $parts = array();
                foreach (array('first_name', 'last_name', 'user_name') as $field) {
                    if (!empty($member->{$field})) {
                        $parts[] = $member->{$field};
                    }
                }
                return trim(implode(' ', array_unique($parts)));
            }
            if (is_array($member)) {
                $parts = array();
                foreach (array('first_name', 'last_name', 'user_name') as $field) {
                    if (!empty($member[$field])) {
                        $parts[] = $member[$field];
                    }
                }
                return trim(implode(' ', array_unique($parts)));
            }
        }

        return '';
    }

    private function get_attachment_filename($attachment_id) {
        $path = get_attached_file($attachment_id);
        if ($path) {
            return basename($path);
        }

        $url = wp_get_attachment_url($attachment_id);
        if ($url) {
            return basename(wp_parse_url($url, PHP_URL_PATH));
        }

        return get_the_title($attachment_id);
    }

    private function get_custom_file_title($file) {
        $attachment_id = isset($file['id']) ? absint($file['id']) : 0;
        $title = isset($file['title']) ? trim((string) $file['title']) : '';

        if ('' === $title) {
            return '';
        }

        if ($attachment_id && $title === trim((string) get_the_title($attachment_id))) {
            return '';
        }

        return $title;
    }

    private function get_overview_url() {
        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            's' => '[chor_noten_uebersicht',
        ));

        if (!empty($pages)) {
            return get_permalink($pages[0]);
        }

        return get_post_type_archive_link(self::POST_TYPE);
    }

    private function current_user_can_submit_music() {
        if (current_user_can('manage_options')) {
            return true;
        }

        $level = $this->get_current_membership_level();
        if (false === $level) {
            return false;
        }

        $allowed_levels = $this->get_submission_levels();
        if (empty($allowed_levels)) {
            return false;
        }

        return in_array((string) $level, $allowed_levels, true);
    }

    private function get_submission_levels() {
        $levels = get_option(self::OPTION_SUBMISSION_LEVELS, array());
        return $this->sanitize_scalar_list(is_array($levels) ? $levels : array());
    }

    private function redirect_submission_result($status) {
        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = home_url('/');
        }

        wp_safe_redirect(add_query_arg('cml_submission', sanitize_key($status), $redirect));
        exit;
    }

    private function redirect_review_result($status) {
        wp_safe_redirect(add_query_arg(array(
            'post_type' => self::POST_TYPE,
            'page' => 'cml-submissions',
            'cml_review' => sanitize_key($status),
        ), admin_url('edit.php')));
        exit;
    }

    private function get_submission_data($submission_id) {
        $defaults = array(
            'type' => 'new_piece',
            'target_piece' => 0,
            'submitted_by' => 0,
            'submitted_level' => '',
            'piece_data' => $this->sanitize_submission_piece_data(array()),
        );

        $data = get_post_meta($submission_id, self::SUBMISSION_META_KEY, true);
        if (!is_array($data)) {
            $data = array();
        }

        $data = array_merge($defaults, $data);
        if (!is_array($data['piece_data'])) {
            $data['piece_data'] = array();
        }
        $data['piece_data'] = array_merge($defaults['piece_data'], $data['piece_data']);

        return $data;
    }

    private function file_group_labels() {
        return array(
            'scores' => $this->text('scores_pdf'),
            'audio_samples' => $this->text('audio_samples'),
            'pronunciation' => $this->text('pronunciation'),
            'misc' => $this->text('misc'),
        );
    }

    private function get_submission_file_label($file) {
        $custom_title = $this->get_custom_file_title($file);
        if ($custom_title) {
            return $custom_title;
        }

        return $this->get_attachment_filename(isset($file['id']) ? absint($file['id']) : 0);
    }

    private function current_user_can_access_piece($post_id) {
        if (current_user_can('edit_post', $post_id)) {
            return true;
        }

        $data = $this->get_piece_data($post_id);
        $allowed_levels = array_map('strval', $data['allowed_levels']);
        $level = $this->get_current_membership_level();

        if (false === $level) {
            return false;
        }

        if (empty($allowed_levels)) {
            return true;
        }

        return in_array((string) $level, $allowed_levels, true);
    }

    private function get_current_membership_level() {
        if (class_exists('SwpmMemberUtils')) {
            if (!SwpmMemberUtils::is_member_logged_in()) {
                return false;
            }

            return (string) SwpmMemberUtils::get_logged_in_members_level();
        }

        return is_user_logged_in() ? 'wp_logged_in' : false;
    }

    private function get_piece_data($post_id) {
        $defaults = array(
            'composer' => '',
            'lyricist' => '',
            'arranger' => '',
            'voicing' => '',
            'extra_info' => '',
            'singing_info' => '',
            'purchase_required' => 0,
            'purchase_product_key' => '',
            'purchase_url' => '',
            'purchase_shortcode' => '',
            'allowed_levels' => array(),
            'scores' => array(),
            'audio_samples' => array(),
            'pronunciation' => array(),
            'misc' => array(),
        );

        $data = get_post_meta($post_id, self::META_KEY, true);
        if (!is_array($data)) {
            $data = array();
        }

        return array_merge($defaults, $data);
    }

    private function current_post_has_shortcode($shortcode) {
        if (!is_singular()) {
            return false;
        }

        $post = get_post();
        return $post && has_shortcode($post->post_content, $shortcode);
    }

    private function sort_pieces_by_title($first, $second) {
        return strnatcasecmp(get_the_title($first), get_the_title($second));
    }

    private function get_language() {
        return $this->sanitize_language(get_option(self::OPTION_LANGUAGE, 'de'));
    }

    private function get_watermark_scope() {
        return $this->sanitize_watermark_scope(get_option(self::OPTION_WATERMARK_SCOPE, 'all'));
    }

    private function get_watermark_brand_text() {
        $value = $this->sanitize_watermark_brand_text(get_option(self::OPTION_WATERMARK_BRAND_TEXT, ''));
        if ('' === $value || 'AllVoices' === $value) {
            return home_url('/');
        }

        return $value;
    }

    private function should_hide_watermark_brand_text() {
        return (bool) get_option(self::OPTION_WATERMARK_HIDE_BRAND_TEXT, 0);
    }

    private function text($key) {
        $labels = array(
            'de' => array(
                'pieces' => 'Musikstuecke',
                'piece' => 'Musikstueck',
                'add_piece' => 'Neues Musikstueck anlegen',
                'edit_piece' => 'Musikstueck bearbeiten',
                'menu_name' => 'Chor-Noten',
                'submissions' => 'Einreichungen',
                'submission' => 'Einreichung',
                'piece_tags' => 'Musikstueck-Tags',
                'piece_tag' => 'Musikstueck-Tag',
                'piece_information' => 'Musikstueck-Informationen',
                'visibility_groups' => 'Sichtbarkeit fuer Mitgliedergruppen',
                'settings' => 'Einstellungen',
                'language' => 'Sprache',
                'language_description' => 'Stellt die vom Plugin ausgegebenen Texte auf Deutsch oder Englisch um.',
                'submission_levels' => 'Einreichungen erlauben fuer',
                'submission_levels_description' => 'Mitglieder dieser Simple-Membership-Level duerfen ueber den Shortcode [chor_musik_einreichen] neue Musikstuecke und Dateiergaenzungen einreichen.',
                'watermark_scope' => 'PDF-Wasserzeichen',
                'watermark_scope_all' => 'Alle PDF-Noten mit Wasserzeichen versehen',
                'watermark_scope_paid' => 'Nur zahlungspflichtige PDF-Noten mit Wasserzeichen versehen',
                'watermark_scope_description' => 'Legt fest, fuer welche PDF-Downloads das seitliche Wasserzeichen erzeugt wird.',
                'watermark_lead_text' => 'Wasserzeichen-Freitext davor',
                'watermark_lead_text_description' => 'Optionaler Text, der vor dem Basistext im Wasserzeichen steht.',
                'watermark_brand_text' => 'Wasserzeichen-Basistext',
                'watermark_brand_text_description' => 'Dieser Text steht im Wasserzeichen vor Name, E-Mail und Datum. Leer lassen nutzt die Haupt-URL dieser Webseite.',
                'watermark_hide_brand_text' => 'Wasserzeichen-Basistext nicht anzeigen',
                'watermark_hide_brand_text_description' => 'Wenn aktiv, wird weder der eingetragene Basistext noch die Haupt-URL im Wasserzeichen ausgegeben.',
                'save_settings' => 'Einstellungen speichern',
                'composer' => 'Komponist',
                'lyricist' => 'Texter',
                'arranger' => 'Arrangeur',
                'voicing' => 'Besetzung',
                'extra_info' => 'Zusatzinformationen',
                'singing_info' => 'Informationen zum Singen',
                'scores_pdf' => 'Noten als PDF',
                'scores' => 'Noten',
                'audio_samples' => 'Hoerbeispiele',
                'pronunciation' => 'Aussprachehilfe',
                'misc' => 'Sonstiges',
                'add_file' => 'Datei hinzufuegen',
                'choose_file' => 'Datei auswaehlen',
                'use_file' => 'Datei verwenden',
                'change' => 'Aendern',
                'remove' => 'Entfernen',
                'visibility_hint' => 'Leer lassen, wenn alle eingeloggten Mitglieder dieses Musikstueck sehen duerfen.',
                'no_levels' => 'Keine WP-Simple-Membership-Level gefunden. Admins koennen weiterhin testen.',
                'switch_view' => 'Ansicht wechseln',
                'tiles' => 'Kacheln',
                'list' => 'Liste',
                'search' => 'Suche',
                'reset_search_filter' => 'Suchfilter zuruecksetzen',
                'search_pieces' => 'Musikstuecke suchen',
                'close_search' => 'Suche schliessen',
                'songname' => 'Songname',
                'tag' => 'Tag',
                'submit_music' => 'Musik einreichen',
                'submission_type' => 'Art der Einreichung',
                'submit_new_piece' => 'Neues Musikstueck',
                'submit_existing_piece_files' => 'Dateien oder Aenderungen zu bestehendem Musikstueck',
                'existing_piece' => 'Bestehendes Musikstueck',
                'choose_existing_piece' => 'Musikstueck auswaehlen',
                'optional_file_title' => 'Optionaler Anzeigename fuer diese Datei-Gruppe',
                'submission_review_hint' => 'Die Einreichung wird erst nach Pruefung durch einen Administrator angezeigt.',
                'send_submission' => 'Zur Pruefung einreichen',
                'submission_access_denied' => 'Du bist nicht fuer Musik-Einreichungen freigeschaltet.',
                'submission_sent' => 'Danke, die Einreichung wurde zur Pruefung gespeichert.',
                'submission_error' => 'Die Einreichung konnte nicht verarbeitet werden.',
                'submission_approved' => 'Einreichung wurde freigegeben.',
                'submission_rejected' => 'Einreichung wurde abgelehnt.',
                'no_submissions' => 'Aktuell warten keine Einreichungen auf Pruefung.',
                'submitted_by' => 'Eingereicht von',
                'approve_submission' => 'Freigeben',
                'reject_submission' => 'Ablehnen',
                'reset' => 'Zuruecksetzen',
                'apply' => 'Anwenden',
                'access_denied' => 'Dieses Musikstueck ist fuer deine Mitgliedergruppe nicht freigegeben.',
                'back_to_overview' => 'Zurueck zur Notenuebersicht',
                'download' => 'Download',
                'purchase_settings' => 'Zahlung',
                'purchase_required' => 'Downloads erst nach Zahlung freischalten',
                'purchase_product_key' => 'Produkt-Schluessel',
                'purchase_product_key_hint' => 'Produktname oder SKU aus WP Simple Shopping Cart. Dieser Wert muss im Kaufdatensatz vorkommen.',
                'purchase_url' => 'Kauflink',
                'purchase_url_hint' => 'Direkter Link zur Produkt-, Warenkorb- oder Checkout-Seite fuer dieses Musikstueck.',
                'purchase_shortcode' => 'Kauf-Shortcode',
                'purchase_shortcode_hint' => 'Hier den WP-Simple-Shopping-Cart-Shortcode fuer dieses Stueck einfuegen.',
                'purchase_needed' => 'Zahlung erforderlich',
                'purchase_needed_hint' => 'Die Downloads fuer dieses Musikstueck werden nach erfolgreicher Zahlung freigeschaltet.',
                'purchase_now' => 'Jetzt kaufen',
                'purchase_missing_link' => 'Fuer dieses Musikstueck ist noch kein Kauflink oder Kauf-Shortcode hinterlegt.',
                'pdf_library_missing' => 'Die PDF-Wasserzeichen-Bibliothek konnte nicht geladen werden.',
                'pdf_watermark_failed' => 'Das PDF-Wasserzeichen konnte nicht erzeugt werden.',
            ),
            'en' => array(
                'pieces' => 'Music Pieces',
                'piece' => 'Music Piece',
                'add_piece' => 'Add New Music Piece',
                'edit_piece' => 'Edit Music Piece',
                'menu_name' => 'Choir Scores',
                'submissions' => 'Submissions',
                'submission' => 'Submission',
                'piece_tags' => 'Music Piece Tags',
                'piece_tag' => 'Music Piece Tag',
                'piece_information' => 'Music Piece Information',
                'visibility_groups' => 'Visibility for Member Groups',
                'settings' => 'Settings',
                'language' => 'Language',
                'language_description' => 'Switches the texts generated by this plugin between German and English.',
                'submission_levels' => 'Allow submissions for',
                'submission_levels_description' => 'Members of these Simple Membership levels may submit new music pieces and file additions through the [chor_musik_einreichen] shortcode.',
                'watermark_scope' => 'PDF Watermark',
                'watermark_scope_all' => 'Watermark all PDF scores',
                'watermark_scope_paid' => 'Watermark only paid PDF scores',
                'watermark_scope_description' => 'Defines which PDF downloads receive the side watermark.',
                'watermark_lead_text' => 'Watermark Leading Text',
                'watermark_lead_text_description' => 'Optional text shown before the base text in the watermark.',
                'watermark_brand_text' => 'Watermark Base Text',
                'watermark_brand_text_description' => 'This text appears in the watermark before name, email, and date. Leave empty to use this site main URL.',
                'watermark_hide_brand_text' => 'Do not show watermark base text',
                'watermark_hide_brand_text_description' => 'When enabled, neither the custom base text nor the site main URL is shown in the watermark.',
                'save_settings' => 'Save Settings',
                'composer' => 'Composer',
                'lyricist' => 'Lyricist',
                'arranger' => 'Arranger',
                'voicing' => 'Voicing',
                'extra_info' => 'Additional Information',
                'singing_info' => 'Singing Information',
                'scores_pdf' => 'Scores as PDF',
                'scores' => 'Scores',
                'audio_samples' => 'Audio Samples',
                'pronunciation' => 'Pronunciation Help',
                'misc' => 'Other Files',
                'add_file' => 'Add File',
                'choose_file' => 'Choose File',
                'use_file' => 'Use File',
                'change' => 'Change',
                'remove' => 'Remove',
                'visibility_hint' => 'Leave empty if all logged-in members may view this music piece.',
                'no_levels' => 'No WP Simple Membership levels found. Admins can still test.',
                'switch_view' => 'Switch View',
                'tiles' => 'Tiles',
                'list' => 'List',
                'search' => 'Search',
                'reset_search_filter' => 'Reset Search Filter',
                'search_pieces' => 'Search Music Pieces',
                'close_search' => 'Close Search',
                'songname' => 'Song Name',
                'tag' => 'Tag',
                'submit_music' => 'Submit Music',
                'submission_type' => 'Submission Type',
                'submit_new_piece' => 'New Music Piece',
                'submit_existing_piece_files' => 'Files or Changes for Existing Music Piece',
                'existing_piece' => 'Existing Music Piece',
                'choose_existing_piece' => 'Choose Music Piece',
                'optional_file_title' => 'Optional display name for this file group',
                'submission_review_hint' => 'The submission will only be shown after administrator review.',
                'send_submission' => 'Submit for Review',
                'submission_access_denied' => 'You are not allowed to submit music.',
                'submission_sent' => 'Thank you, the submission has been saved for review.',
                'submission_error' => 'The submission could not be processed.',
                'submission_approved' => 'Submission approved.',
                'submission_rejected' => 'Submission rejected.',
                'no_submissions' => 'There are no submissions waiting for review.',
                'submitted_by' => 'Submitted by',
                'approve_submission' => 'Approve',
                'reject_submission' => 'Reject',
                'reset' => 'Reset',
                'apply' => 'Apply',
                'access_denied' => 'This music piece is not available for your member group.',
                'back_to_overview' => 'Back to Score Overview',
                'download' => 'Download',
                'purchase_settings' => 'Payment',
                'purchase_required' => 'Unlock downloads only after payment',
                'purchase_product_key' => 'Product Key',
                'purchase_product_key_hint' => 'Product name or SKU from WP Simple Shopping Cart. This value must appear in the purchase data.',
                'purchase_url' => 'Purchase Link',
                'purchase_url_hint' => 'Direct link to the product, cart, or checkout page for this music piece.',
                'purchase_shortcode' => 'Purchase Shortcode',
                'purchase_shortcode_hint' => 'Paste the WP Simple Shopping Cart shortcode for this piece here.',
                'purchase_needed' => 'Payment Required',
                'purchase_needed_hint' => 'Downloads for this music piece will be unlocked after successful payment.',
                'purchase_now' => 'Buy Now',
                'purchase_missing_link' => 'No purchase link or purchase shortcode has been configured for this music piece yet.',
                'pdf_library_missing' => 'The PDF watermark library could not be loaded.',
                'pdf_watermark_failed' => 'The PDF watermark could not be created.',
            ),
        );

        $language = $this->get_language();
        if (isset($labels[$language][$key])) {
            return $labels[$language][$key];
        }

        return isset($labels['de'][$key]) ? $labels['de'][$key] : $key;
    }

    private function get_membership_levels() {
        global $wpdb;

        $table = $wpdb->prefix . 'swpm_membership_tbl';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return array();
        }

        $rows = $wpdb->get_results("SELECT id, alias FROM {$table} ORDER BY alias ASC");
        $levels = array();

        foreach ($rows as $row) {
            $levels[(string) $row->id] = $row->alias;
        }

        return $levels;
    }
}

CML_Choir_Music_Library::instance();

register_activation_hook(__FILE__, function() {
    CML_Choir_Music_Library::instance()->register_post_type();
    CML_Choir_Music_Library::instance()->register_taxonomy();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});
