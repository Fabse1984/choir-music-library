<?php
/**
 * Plugin Name: Choir Music Library
 * Description: Geschuetzter Notenbereich fuer Chor-Webseiten mit Noten, Hoerbeispielen, Aussprachehilfen und Zusatzdateien.
 * Version: 1.0.3
 * Author: Codex
 * Text Domain: choir-music-library
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CML_Choir_Music_Library {
    const VERSION = '1.0.3';
    const POST_TYPE = 'cml_piece';
    const TAG_TAX = 'cml_piece_tag';
    const META_KEY = '_cml_piece_data';

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_piece'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('pre_get_posts', array($this, 'filter_frontend_queries'));
        add_action('admin_post_cml_file', array($this, 'serve_file'));
        add_action('admin_post_nopriv_cml_file', array($this, 'serve_file'));
        add_filter('the_content', array($this, 'render_single_content'));
        add_shortcode('chor_noten_uebersicht', array($this, 'overview_shortcode'));
        add_shortcode('chor_musikstueck', array($this, 'piece_shortcode'));
    }

    public function register_post_type() {
        register_post_type(self::POST_TYPE, array(
            'labels' => array(
                'name' => __('Musikstuecke', 'choir-music-library'),
                'singular_name' => __('Musikstueck', 'choir-music-library'),
                'add_new_item' => __('Neues Musikstueck anlegen', 'choir-music-library'),
                'edit_item' => __('Musikstueck bearbeiten', 'choir-music-library'),
                'menu_name' => __('Chor-Noten', 'choir-music-library'),
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

    public function register_taxonomy() {
        register_taxonomy(self::TAG_TAX, self::POST_TYPE, array(
            'labels' => array(
                'name' => __('Musikstueck-Tags', 'choir-music-library'),
                'singular_name' => __('Musikstueck-Tag', 'choir-music-library'),
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
            __('Musikstueck-Informationen', 'choir-music-library'),
            array($this, 'render_details_meta_box'),
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'cml_piece_access',
            __('Sichtbarkeit fuer Mitgliedergruppen', 'choir-music-library'),
            array($this, 'render_access_meta_box'),
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public function enqueue_admin_assets($hook) {
        global $post_type;

        if (self::POST_TYPE !== $post_type) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('cml-admin', plugins_url('assets/admin.css', __FILE__), array(), self::VERSION);
        wp_enqueue_script('cml-admin', plugins_url('assets/admin.js', __FILE__), array('jquery'), self::VERSION, true);
    }

    public function enqueue_frontend_assets() {
        wp_register_style('cml-frontend', plugins_url('assets/frontend.css', __FILE__), array(), self::VERSION);
        wp_register_script('cml-frontend', plugins_url('assets/frontend.js', __FILE__), array(), self::VERSION, true);

        if (is_singular(self::POST_TYPE) || $this->current_post_has_shortcode('chor_noten_uebersicht') || $this->current_post_has_shortcode('chor_musikstueck')) {
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
            'composer' => __('Komponist', 'choir-music-library'),
            'lyricist' => __('Texter', 'choir-music-library'),
            'voicing' => __('Besetzung', 'choir-music-library'),
            'extra_info' => __('Zusatzinformationen', 'choir-music-library'),
            'singing_info' => __('Informationen zum Singen', 'choir-music-library'),
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
        $this->render_file_group('scores', __('Noten als PDF', 'choir-music-library'), 'application/pdf', $data['scores']);
        $this->render_file_group('audio_samples', __('Hoerbeispiele', 'choir-music-library'), 'audio', $data['audio_samples']);
        $this->render_file_group('pronunciation', __('Aussprachehilfe', 'choir-music-library'), '', $data['pronunciation']);
        $this->render_file_group('misc', __('Sonstiges', 'choir-music-library'), '', $data['misc']);
    }

    private function render_file_group($key, $label, $library_type, $files) {
        ?>
        <div class="cml-file-group" data-group="<?php echo esc_attr($key); ?>" data-library-type="<?php echo esc_attr($library_type); ?>">
            <div class="cml-file-group-header">
                <h3><?php echo esc_html($label); ?></h3>
                <button type="button" class="button cml-add-file"><?php esc_html_e('Datei hinzufuegen', 'choir-music-library'); ?></button>
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
        $title = $file['title'] ? $file['title'] : get_the_title($attachment_id);
        $path = get_attached_file($attachment_id);
        $filename = $path ? basename($path) : get_the_title($attachment_id);
        ?>
        <div class="cml-file-row">
            <input type="hidden" name="cml[<?php echo esc_attr($group); ?>][]" value="<?php echo esc_attr($attachment_id); ?>" class="cml-file-id">
            <input type="text" name="cml[<?php echo esc_attr($group); ?>_titles][]" value="<?php echo esc_attr($title); ?>" class="cml-file-title" placeholder="<?php esc_attr_e('Anzeigename', 'choir-music-library'); ?>">
            <span class="cml-file-name"><?php echo esc_html($filename); ?></span>
            <button type="button" class="button cml-change-file"><?php esc_html_e('Aendern', 'choir-music-library'); ?></button>
            <button type="button" class="button-link-delete cml-remove-file"><?php esc_html_e('Entfernen', 'choir-music-library'); ?></button>
        </div>
        <?php
    }

    public function render_access_meta_box($post) {
        $data = $this->get_piece_data($post->ID);
        $levels = $this->get_membership_levels();
        ?>
        <p><?php esc_html_e('Leer lassen, wenn alle eingeloggten Mitglieder dieses Musikstueck sehen duerfen.', 'choir-music-library'); ?></p>
        <?php if (empty($levels)) : ?>
            <p><em><?php esc_html_e('Keine WP-Simple-Membership-Level gefunden. Admins koennen weiterhin testen.', 'choir-music-library'); ?></em></p>
        <?php endif; ?>
        <?php foreach ($levels as $level_id => $level_name) : ?>
            <label class="cml-access-option">
                <input type="checkbox" name="cml[allowed_levels][]" value="<?php echo esc_attr($level_id); ?>" <?php checked(in_array((string) $level_id, $data['allowed_levels'], true)); ?>>
                <?php echo esc_html($level_name); ?>
            </label>
        <?php endforeach; ?>
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
            'voicing' => isset($raw['voicing']) ? sanitize_text_field($raw['voicing']) : '',
            'extra_info' => isset($raw['extra_info']) ? wp_kses_post($raw['extra_info']) : '',
            'singing_info' => isset($raw['singing_info']) ? wp_kses_post($raw['singing_info']) : '',
            'allowed_levels' => $this->sanitize_scalar_list(isset($raw['allowed_levels']) ? $raw['allowed_levels'] : array()),
            'scores' => $this->sanitize_file_group($raw, 'scores'),
            'audio_samples' => $this->sanitize_file_group($raw, 'audio_samples'),
            'pronunciation' => $this->sanitize_file_group($raw, 'pronunciation'),
            'misc' => $this->sanitize_file_group($raw, 'misc'),
        );

        update_post_meta($post_id, self::META_KEY, $data);
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

    private function sanitize_scalar_list($values) {
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

        ob_start();
        ?>
        <section class="cml-overview" data-cml-overview>
            <div class="cml-overview-toolbar">
                <div class="cml-view-switch" role="group" aria-label="<?php esc_attr_e('Ansicht wechseln', 'choir-music-library'); ?>">
                    <button type="button" class="is-active" data-cml-view="grid"><?php esc_html_e('Kacheln', 'choir-music-library'); ?></button>
                    <button type="button" data-cml-view="list"><?php esc_html_e('Liste', 'choir-music-library'); ?></button>
                </div>
                <div class="cml-search-toolbar-actions">
                    <button type="button" class="cml-search-open" data-cml-search-open><?php esc_html_e('Suche', 'choir-music-library'); ?></button>
                    <button type="button" class="cml-search-reset" data-cml-search-reset hidden><?php esc_html_e('Suchfilter zuruecksetzen', 'choir-music-library'); ?></button>
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
                <div class="cml-search-panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Musikstuecke suchen', 'choir-music-library'); ?>">
                    <button type="button" class="cml-search-close" data-cml-search-close aria-label="<?php esc_attr_e('Suche schliessen', 'choir-music-library'); ?>">&times;</button>
                    <h2><?php esc_html_e('Musikstuecke suchen', 'choir-music-library'); ?></h2>
                    <label>
                        <span><?php esc_html_e('Songname', 'choir-music-library'); ?></span>
                        <input type="search" data-cml-filter="title">
                    </label>
                    <label>
                        <span><?php esc_html_e('Komponist', 'choir-music-library'); ?></span>
                        <input type="search" data-cml-filter="composer">
                    </label>
                    <label>
                        <span><?php esc_html_e('Tag', 'choir-music-library'); ?></span>
                        <input type="search" data-cml-filter="tags">
                    </label>
                    <div class="cml-search-actions">
                        <button type="button" data-cml-search-reset><?php esc_html_e('Zuruecksetzen', 'choir-music-library'); ?></button>
                        <button type="button" data-cml-search-close><?php esc_html_e('Anwenden', 'choir-music-library'); ?></button>
                    </div>
                </div>
            </div>
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
            return '<div class="cml-access-denied">' . esc_html__('Dieses Musikstueck ist fuer deine Mitgliedergruppe nicht freigegeben.', 'choir-music-library') . '</div>';
        }

        $data = $this->get_piece_data($post_id);
        $tags = wp_get_post_terms($post_id, self::TAG_TAX, array('fields' => 'names'));

        ob_start();
        ?>
        <article class="cml-piece">
            <header class="cml-piece-header">
                <?php if ($show_title) : ?>
                    <h1><?php echo esc_html(get_the_title($post)); ?></h1>
                <?php endif; ?>
                <dl class="cml-facts">
                    <?php $this->render_fact(__('Komponist', 'choir-music-library'), $data['composer'], false, 'cml-fact-compact'); ?>
                    <?php $this->render_fact(__('Texter', 'choir-music-library'), $data['lyricist'], false, 'cml-fact-compact'); ?>
                    <?php $this->render_fact(__('Besetzung', 'choir-music-library'), $data['voicing'], false, 'cml-fact-compact'); ?>
                    <?php $this->render_fact(__('Zusatzinformationen', 'choir-music-library'), wpautop($data['extra_info']), true, 'cml-fact-full'); ?>
                    <?php $this->render_fact(__('Informationen zum Singen', 'choir-music-library'), wpautop($data['singing_info']), true, 'cml-fact-full'); ?>
                </dl>
                <?php if (!empty($tags)) : ?>
                    <div class="cml-tags">
                        <?php foreach ($tags as $tag) : ?>
                            <span><?php echo esc_html($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </header>

            <?php $this->render_file_section($post_id, __('Noten', 'choir-music-library'), $data['scores'], 'download'); ?>
            <?php $this->render_file_section($post_id, __('Hoerbeispiele', 'choir-music-library'), $data['audio_samples'], 'audio'); ?>
            <?php $this->render_file_section($post_id, __('Aussprachehilfe', 'choir-music-library'), $data['pronunciation'], 'mixed', true); ?>
            <?php $this->render_file_section($post_id, __('Sonstiges', 'choir-music-library'), $data['misc'], 'download', true); ?>
        </article>
        <?php

        return ob_get_clean();
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
        $title = $file['title'] ? $file['title'] : get_the_title($attachment_id);
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
            <a class="cml-download-link" href="<?php echo esc_url($download_url); ?>"><?php esc_html_e('Download', 'choir-music-library'); ?></a>
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

        if (!$this->current_user_can_access_piece($piece_id) || !$this->piece_has_attachment($piece_id, $attachment_id)) {
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
            'voicing' => '',
            'extra_info' => '',
            'singing_info' => '',
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
