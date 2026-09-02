<?php
/**
 * Plugin Name: Wedding Invitation Maker - BRILLI
 * Plugin URI: https://brillianav.com
 * Description: Generate personalized wedding invitation messages, Indonesian and English invitation URLs, and WhatsApp share links from the frontend.
 * Version: 1.7.0
 * Requires at least: 5.8
 * Requires PHP: 5.6
 * Author: Brillian AV
 * Author URI: https://brillianav.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: brilli-wedding-invitation-maker
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('BRILLI_WIM_PLUGIN_FILE')) {
    define('BRILLI_WIM_PLUGIN_FILE', __FILE__);
}

if (!defined('BRILLI_WIM_PLUGIN_URL')) {
    define('BRILLI_WIM_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!class_exists('Brilli_Wedding_Invitation_Maker')) {
    /**
     * Main plugin controller.
     */
    class Brilli_Wedding_Invitation_Maker {
        const VERSION = '1.7.0';
        const DB_VERSION = '1';
        const DB_VERSION_OPTION = 'brilli_wim_db_version';
        const HISTORY_PAGE_SIZE = 50;
        const OPTION_KEY = 'brilli_wedding_invitation_maker_options';
        const OPTION_GROUP = 'brilli_wedding_invitation_maker_group';
        const MENU_SLUG = 'brilli-wedding-invitation-maker';
        const SHORTCODE = 'brilli_wedding_invitation_maker';
        const SHORTCODE_ALIAS = 'brilli_wedding_invitation';
        const ADMIN_STYLE_HANDLE = 'brilli-wedding-invitation-maker-admin';
        const STYLE_HANDLE = 'brilli-wedding-invitation-maker-style';
        const SCRIPT_HANDLE = 'brilli-wedding-invitation-maker-script';

        /**
         * Hook suffix for the plugin's top-level admin page.
         *
         * @var string
         */
        private $admin_page_hook = '';

        /**
         * Register WordPress hooks and shortcodes.
         */
        public function register_hooks() {
            add_action('init', array($this, 'load_textdomain'));
            add_action('init', array($this, 'maybe_upgrade_database'));
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('wp_enqueue_scripts', array($this, 'register_assets'));
            add_filter('plugin_action_links_' . plugin_basename(BRILLI_WIM_PLUGIN_FILE), array($this, 'add_plugin_action_links'));
            add_action('wp_ajax_brilli_wim_add_history', array($this, 'ajax_add_history'));
            add_action('wp_ajax_nopriv_brilli_wim_add_history', array($this, 'ajax_add_history'));
            add_action('wp_ajax_brilli_wim_get_history', array($this, 'ajax_get_history'));
            add_action('wp_ajax_nopriv_brilli_wim_get_history', array($this, 'ajax_get_history'));
            add_action('wp_ajax_brilli_wim_clear_history', array($this, 'ajax_clear_history'));
            add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
            add_shortcode(self::SHORTCODE_ALIAS, array($this, 'render_shortcode'));
        }

        /**
         * Load plugin translations for private and non-directory installs.
         */
        public function load_textdomain() {
            load_plugin_textdomain(
                'brilli-wedding-invitation-maker',
                false,
                dirname(plugin_basename(BRILLI_WIM_PLUGIN_FILE)) . '/languages'
            );
        }

        /**
         * Create default settings on first activation.
         */
        public static function activate() {
            if (false === get_option(self::OPTION_KEY, false)) {
                add_option(self::OPTION_KEY, self::default_options_static(), '', false);
            }

            self::install_history_table();
        }

        /**
         * Return the prefixed history table name.
         *
         * @return string
         */
        public static function history_table_name() {
            global $wpdb;

            return $wpdb->prefix . 'brilli_wim_history';
        }

        /**
         * Create or upgrade the generation-history table.
         */
        public static function install_history_table() {
            global $wpdb;

            $table_name = self::history_table_name();
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table_name} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                page_id bigint(20) unsigned NOT NULL DEFAULT 0,
                guest_name varchar(191) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY page_history (page_id, id)
            ) {$charset_collate};";

            if (!function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }

            dbDelta($sql);
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
        }

        /**
         * Ensure active installations receive database upgrades.
         */
        public function maybe_upgrade_database() {
            if (self::DB_VERSION !== get_option(self::DB_VERSION_OPTION, '')) {
                self::install_history_table();
            }
        }

        /**
         * Read and validate the page ID supplied to a history request.
         *
         * @return int
         */
        private function get_history_request_page_id() {
            $page_id = isset($_POST['page_id']) ? absint(wp_unslash($_POST['page_id'])) : 0;
            $post_status = $page_id ? get_post_status($page_id) : false;

            if (!$page_id || ('publish' !== $post_status && !current_user_can('read_post', $page_id))) {
                wp_send_json_error(
                    array('message' => __('Halaman riwayat tidak valid.', 'brilli-wedding-invitation-maker')),
                    400
                );
            }

            check_ajax_referer('brilli_wim_history_' . $page_id, 'nonce');

            return $page_id;
        }

        /**
         * Store a generated guest name for the current page.
         */
        public function ajax_add_history() {
            global $wpdb;

            $page_id = $this->get_history_request_page_id();
            $guest_name = isset($_POST['guest_name']) ? sanitize_text_field(wp_unslash($_POST['guest_name'])) : '';

            if (function_exists('mb_substr')) {
                $guest_name = mb_substr($guest_name, 0, 191);
            } else {
                $guest_name = substr($guest_name, 0, 191);
            }

            if ('' === $guest_name) {
                wp_send_json_error(
                    array('message' => __('Nama tamu tidak boleh kosong.', 'brilli-wedding-invitation-maker')),
                    400
                );
            }

            if (!current_user_can('manage_options') && !empty($_SERVER['REMOTE_ADDR'])) {
                $remote_address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
                $rate_limit_key = 'brilli_wim_rate_' . md5($page_id . '|' . $remote_address);

                if (get_transient($rate_limit_key)) {
                    wp_send_json_error(
                        array('message' => __('Terlalu banyak permintaan. Silakan tunggu sebentar.', 'brilli-wedding-invitation-maker')),
                        429
                    );
                }

                set_transient($rate_limit_key, 1, 1);
            }

            $inserted = $wpdb->insert(
                self::history_table_name(),
                array(
                    'page_id' => $page_id,
                    'guest_name' => $guest_name,
                    'created_at' => current_time('mysql', true),
                ),
                array('%d', '%s', '%s')
            );

            if (false === $inserted) {
                wp_send_json_error(
                    array('message' => __('Riwayat gagal disimpan.', 'brilli-wedding-invitation-maker')),
                    500
                );
            }

            wp_send_json_success(array('message' => __('Riwayat berhasil disimpan.', 'brilli-wedding-invitation-maker')));
        }

        /**
         * Return one page of shared generation history.
         */
        public function ajax_get_history() {
            global $wpdb;

            $page_id = $this->get_history_request_page_id();
            $history_page = isset($_POST['history_page']) ? max(1, absint(wp_unslash($_POST['history_page']))) : 1;
            $offset = ($history_page - 1) * self::HISTORY_PAGE_SIZE;
            $table_name = self::history_table_name();
            $total = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE page_id = %d", $page_id)
            );
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, guest_name, created_at FROM {$table_name} WHERE page_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
                    $page_id,
                    self::HISTORY_PAGE_SIZE,
                    $offset
                )
            );
            $entries = array();

            foreach ($rows as $row) {
                $timestamp = strtotime($row->created_at . ' UTC');
                $entries[] = array(
                    'id' => (int) $row->id,
                    'name' => $row->guest_name,
                    'createdAt' => $timestamp ? $timestamp * 1000 : 0,
                );
            }

            wp_send_json_success(
                array(
                    'entries' => $entries,
                    'total' => $total,
                    'page' => $history_page,
                    'hasMore' => ($offset + count($entries)) < $total,
                )
            );
        }

        /**
         * Clear shared history for a page. Administrators only.
         */
        public function ajax_clear_history() {
            global $wpdb;

            $page_id = $this->get_history_request_page_id();

            if (!current_user_can('manage_options')) {
                wp_send_json_error(
                    array('message' => __('Anda tidak memiliki izin untuk menghapus riwayat.', 'brilli-wedding-invitation-maker')),
                    403
                );
            }

            $wpdb->delete(self::history_table_name(), array('page_id' => $page_id), array('%d'));
            wp_send_json_success(array('message' => __('Riwayat berhasil dihapus.', 'brilli-wedding-invitation-maker')));
        }

        /**
         * Return the complete default option set.
         *
         * @return array
         */
        public static function default_options_static() {
            return array(
                'base_url_id' => 'https://brillian.my.id/',
                'base_url_en' => 'https://brillian.my.id/en/',
                'url_param' => 'to',
                'custom_url_id' => '',
                'custom_url_en' => '',
                'message_formal_id' => "Kepada Yth.\nBapak/Ibu/Saudara/i {name}\n\nDengan penuh kebahagiaan, kami mengundang Bapak/Ibu/Saudara/i untuk hadir dan memberikan doa restu pada acara pernikahan kami:\n\n💍 Brillian & Midiya\n🗓 Sabtu, 3 Oktober 2026\n📍 Dsn/Ds. Karangdiyeng RT/RW.01, Kec. Kutorejo, Kab. Mojokerto\n\nUndangan digital dapat diakses melalui tautan berikut:\n{invitation_url}\n\nKehadiran dan doa restu Bapak/Ibu/Saudara/i merupakan kehormatan serta kebahagiaan bagi kami.\n\nTerima kasih.",
                'message_formal_en' => "Dear Mr./Mrs./Ms. {name},\n\nWith great joy, we cordially invite you to attend and share your blessings at our wedding celebration:\n\n💍 Brillian & Midiya\n🗓 Saturday, October 3, 2026\n📍 Dsn/Ds. Karangdiyeng RT/RW.01, Kutorejo District, Mojokerto Regency\n\nPlease view our digital invitation through the link below:\n{invitation_url}\n\nYour presence and blessings would be a great honor and joy to us.\n\nThank you.",
                'message_casual_id' => "Halo {name}! 👋\n\nKami punya kabar bahagia: Brillian & Midiya akan menikah! Kami ingin mengajak kamu ikut merayakan hari spesial kami pada:\n\n🗓 Sabtu, 3 Oktober 2026\n📍 Dsn/Ds. Karangdiyeng RT/RW.01, Kec. Kutorejo, Kab. Mojokerto\n\nDetail lengkap acaranya ada di sini:\n{invitation_url}\n\nSemoga kamu bisa hadir dan berbagi kebahagiaan bersama kami. Sampai ketemu! 🤍",
                'message_casual_en' => "Hi {name}! 👋\n\nWe have happy news: Brillian & Midiya are getting married! We would love for you to celebrate our special day with us on:\n\n🗓 Saturday, October 3, 2026\n📍 Dsn/Ds. Karangdiyeng RT/RW.01, Kutorejo District, Mojokerto Regency\n\nYou can find all the details here:\n{invitation_url}\n\nWe hope you can make it and share the joy with us. See you there! 🤍",
                'message_warm_id' => "Hai {name} ✨\n\nAkhirnya hari yang kami tunggu segera tiba! Brillian & Midiya akan memulai perjalanan baru, dan rasanya belum lengkap tanpa kehadiranmu.\n\nCatat tanggalnya ya:\n🗓 Sabtu, 3 Oktober 2026\n📍 Dsn/Ds. Karangdiyeng RT/RW.01, Kec. Kutorejo, Kab. Mojokerto\n\nBuka undangannya di sini:\n{invitation_url}\n\nDatang ya—kami ingin merayakan, tertawa, dan membuat kenangan indah bersamamu! 🥰",
                'message_warm_en' => "Hey {name} ✨\n\nThe day we have been waiting for is almost here! Brillian & Midiya are starting a new chapter, and it would not feel complete without you.\n\nSave the date:\n🗓 Saturday, October 3, 2026\n📍 Dsn/Ds. Karangdiyeng RT/RW.01, Kutorejo District, Mojokerto Regency\n\nOpen the invitation here:\n{invitation_url}\n\nCome celebrate, laugh, and make beautiful memories with us! 🥰",
                'generate_button' => 'Generate Undangan',
                'copy_id_button' => 'Copy Indonesia',
                'copy_en_button' => 'Copy English',
                'whatsapp_id_button' => 'Send Indonesia via WhatsApp',
                'whatsapp_en_button' => 'Send English via WhatsApp',
            );
        }

        /**
         * Return default settings for instance methods.
         *
         * @return array
         */
        public function default_options() {
            return self::default_options_static();
        }

        /**
         * Merge saved settings with defaults and legacy keys.
         *
         * @return array
         */
        public function get_options() {
            $saved = get_option(self::OPTION_KEY, array());

            if (!is_array($saved)) {
                $saved = array();
            }

            $options = wp_parse_args($saved, $this->default_options());

            // Preserve customized templates from version 1.0 as the Formal tab.
            if (!array_key_exists('message_formal_id', $saved) && isset($saved['message_id'])) {
                $options['message_formal_id'] = $saved['message_id'];
            }

            if (!array_key_exists('message_formal_en', $saved) && isset($saved['message_en'])) {
                $options['message_formal_en'] = $saved['message_en'];
            }

            return $options;
        }

        /**
         * Add the top-level admin menu page.
         */
        public function add_admin_menu() {
            $this->admin_page_hook = add_menu_page(
                __('Wedding Invitation Maker - BRILLI', 'brilli-wedding-invitation-maker'),
                __('Wedding Invitation', 'brilli-wedding-invitation-maker'),
                'manage_options',
                self::MENU_SLUG,
                array($this, 'render_admin_page'),
                'dashicons-heart',
                58
            );
        }

        /**
         * Enqueue admin CSS only on the plugin page.
         *
         * @param string $hook_suffix Current admin page hook suffix.
         */
        public function enqueue_admin_assets($hook_suffix) {
            if ($this->admin_page_hook !== $hook_suffix) {
                return;
            }

            wp_enqueue_style(
                self::ADMIN_STYLE_HANDLE,
                BRILLI_WIM_PLUGIN_URL . 'assets/brilli-wedding-invitation-maker-admin.css',
                array(),
                self::VERSION
            );
        }

        /**
         * Add a settings shortcut to the Plugins screen.
         *
         * @param array $links Existing plugin action links.
         * @return array
         */
        public function add_plugin_action_links($links) {
            $settings_link = sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)),
                esc_html__('Buka pengaturan', 'brilli-wedding-invitation-maker')
            );

            array_unshift($links, $settings_link);

            return $links;
        }

        /**
         * Register the plugin's Settings API option.
         */
        public function register_settings() {
            register_setting(
                self::OPTION_GROUP,
                self::OPTION_KEY,
                array(
                    'type' => 'array',
                    'sanitize_callback' => array($this, 'sanitize_options'),
                    'default' => self::default_options_static(),
                    'show_in_rest' => false,
                )
            );
        }

        /**
         * Sanitize all values submitted from the settings page.
         *
         * @param mixed $input Submitted option value.
         * @return array
         */
        public function sanitize_options($input) {
            $defaults = $this->default_options();
            $output = array();
            $button_keys = array(
                'generate_button',
                'copy_id_button',
                'copy_en_button',
                'whatsapp_id_button',
                'whatsapp_en_button',
            );

            // options.php unslashes registered settings before this callback runs.
            $input = is_array($input) ? $input : array();

            $output['base_url_id'] = esc_url_raw(trim($this->get_input_value($input, 'base_url_id', $defaults['base_url_id'])), array('http', 'https'));
            $output['base_url_en'] = esc_url_raw(trim($this->get_input_value($input, 'base_url_en', $defaults['base_url_en'])), array('http', 'https'));
            $output['url_param'] = sanitize_key($this->get_input_value($input, 'url_param', $defaults['url_param']));
            $output['custom_url_id'] = $this->sanitize_url_template($this->get_input_value($input, 'custom_url_id'));
            $output['custom_url_en'] = $this->sanitize_url_template($this->get_input_value($input, 'custom_url_en'));

            foreach (array('formal', 'casual', 'warm') as $template_key) {
                foreach (array('id', 'en') as $language) {
                    $option_key = 'message_' . $template_key . '_' . $language;
                    $output[$option_key] = sanitize_textarea_field($this->get_input_value($input, $option_key, $defaults[$option_key]));
                }
            }

            foreach ($button_keys as $button_key) {
                $output[$button_key] = sanitize_text_field($this->get_input_value($input, $button_key, $defaults[$button_key]));

                if ('' === $output[$button_key]) {
                    $output[$button_key] = $defaults[$button_key];
                }
            }

            if (empty($output['base_url_id']) && empty($output['custom_url_id'])) {
                $output['base_url_id'] = $defaults['base_url_id'];
            }

            if (empty($output['base_url_en']) && empty($output['custom_url_en'])) {
                $output['base_url_en'] = $defaults['base_url_en'];
            }

            if (empty($output['url_param'])) {
                $output['url_param'] = 'to';
            }

            return $output;
        }

        /**
         * Read a string value from submitted settings.
         *
         * @param array  $input   Submitted settings.
         * @param string $key     Option key.
         * @param string $default Fallback value.
         * @return string
         */
        private function get_input_value($input, $key, $default = '') {
            if (!isset($input[$key]) || !is_string($input[$key])) {
                return $default;
            }

            return $input[$key];
        }

        /**
         * Validate a URL template while preserving supported placeholders.
         *
         * @param string $value Submitted URL template.
         * @return string
         */
        private function sanitize_url_template($value) {
            static $invalid_url_reported = false;

            $value = trim(sanitize_text_field($value));

            if ('' === $value) {
                return '';
            }

            $validation_url = strtr(
                $value,
                array(
                    '{name}' => 'Guest-Name',
                    '{encoded_name}' => 'Guest-Name',
                    '{phone}' => '628123456789',
                )
            );
            $scheme = wp_parse_url($validation_url, PHP_URL_SCHEME);
            $host = wp_parse_url($validation_url, PHP_URL_HOST);

            if (!in_array($scheme, array('http', 'https'), true) || empty($host)) {
                if (!$invalid_url_reported) {
                    add_settings_error(
                        self::OPTION_KEY,
                        'brilli_wim_invalid_url_template',
                        __('Template URL khusus harus berupa URL HTTP atau HTTPS yang valid.', 'brilli-wedding-invitation-maker'),
                        'error'
                    );
                    $invalid_url_reported = true;
                }

                return '';
            }

            return $value;
        }

        /**
         * Register frontend assets for on-demand shortcode loading.
         */
        public function register_assets() {
            wp_register_style(
                self::STYLE_HANDLE,
                BRILLI_WIM_PLUGIN_URL . 'assets/brilli-wedding-invitation-maker.css',
                array(),
                self::VERSION
            );

            wp_register_script(
                self::SCRIPT_HANDLE,
                BRILLI_WIM_PLUGIN_URL . 'assets/brilli-wedding-invitation-maker.js',
                array(),
                self::VERSION,
                true
            );
        }

        /**
         * Render the plugin settings page.
         */
        public function render_admin_page() {
            if (!current_user_can('manage_options')) {
                return;
            }

            $options = $this->get_options();
            $logo_url = BRILLI_WIM_PLUGIN_URL . 'assets/logo-bav-white.png';
            $template_sections = array(
                'formal' => array(
                    'label' => __('Formal', 'brilli-wedding-invitation-maker'),
                    'description' => __('Untuk keluarga, kolega, dan relasi yang membutuhkan bahasa resmi.', 'brilli-wedding-invitation-maker'),
                ),
                'casual' => array(
                    'label' => __('Nonformal 1', 'brilli-wedding-invitation-maker'),
                    'description' => __('Untuk teman dan kenalan dengan bahasa yang lebih santai.', 'brilli-wedding-invitation-maker'),
                ),
                'warm' => array(
                    'label' => __('Nonformal 2', 'brilli-wedding-invitation-maker'),
                    'description' => __('Untuk sahabat dan orang terdekat dengan bahasa yang hangat.', 'brilli-wedding-invitation-maker'),
                ),
            );
            $button_fields = array(
                'generate_button' => array(__('Tombol generate', 'brilli-wedding-invitation-maker'), __('Tindakan utama untuk membuat semua versi pesan.', 'brilli-wedding-invitation-maker')),
                'copy_id_button' => array(__('Salin Indonesia', 'brilli-wedding-invitation-maker'), __('Menyalin pesan berbahasa Indonesia.', 'brilli-wedding-invitation-maker')),
                'copy_en_button' => array(__('Salin English', 'brilli-wedding-invitation-maker'), __('Menyalin pesan berbahasa Inggris.', 'brilli-wedding-invitation-maker')),
                'whatsapp_id_button' => array(__('WhatsApp Indonesia', 'brilli-wedding-invitation-maker'), __('Membuka WhatsApp dengan pesan Indonesia.', 'brilli-wedding-invitation-maker')),
                'whatsapp_en_button' => array(__('WhatsApp English', 'brilli-wedding-invitation-maker'), __('Membuka WhatsApp dengan pesan Inggris.', 'brilli-wedding-invitation-maker')),
            );
            ?>
            <div class="wrap brilli-wim-admin">
                <?php settings_errors(); ?>

                <header class="brilli-wim-admin__hero">
                    <div class="brilli-wim-admin__brand">
                        <img src="<?php echo esc_url($logo_url); ?>" width="84" height="84" alt="BRILLI">
                        <div>
                            <span class="brilli-wim-admin__eyebrow"><?php esc_html_e('BRILLI tools', 'brilli-wedding-invitation-maker'); ?></span>
                            <h1><?php esc_html_e('Wedding Invitation Maker', 'brilli-wedding-invitation-maker'); ?></h1>
                            <p><?php esc_html_e('Kelola tautan dan enam template pesan undangan dari satu tempat.', 'brilli-wedding-invitation-maker'); ?></p>
                        </div>
                    </div>
                    <div class="brilli-wim-admin__hero-actions">
                        <span class="brilli-wim-admin__version"><?php esc_html_e('Versi', 'brilli-wedding-invitation-maker'); ?> <?php echo esc_html(self::VERSION); ?></span>
                        <a class="brilli-wim-admin__hero-link" href="#brilli-wim-usage"><?php esc_html_e('Lihat cara pakai', 'brilli-wedding-invitation-maker'); ?></a>
                    </div>
                </header>

                <div class="brilli-wim-admin__shell">
                    <aside class="brilli-wim-admin__sidebar">
                        <nav class="brilli-wim-admin__nav" aria-label="<?php esc_attr_e('Navigasi pengaturan', 'brilli-wedding-invitation-maker'); ?>">
                            <a href="#brilli-wim-links"><span>01</span><strong><?php esc_html_e('Tautan undangan', 'brilli-wedding-invitation-maker'); ?></strong></a>
                            <a href="#brilli-wim-messages"><span>02</span><strong><?php esc_html_e('Template pesan', 'brilli-wedding-invitation-maker'); ?></strong></a>
                            <a href="#brilli-wim-buttons"><span>03</span><strong><?php esc_html_e('Label tombol', 'brilli-wedding-invitation-maker'); ?></strong></a>
                            <a href="#brilli-wim-usage"><span>04</span><strong><?php esc_html_e('Cara pakai', 'brilli-wedding-invitation-maker'); ?></strong></a>
                        </nav>

                        <div class="brilli-wim-admin__quick-card">
                            <span><?php esc_html_e('Shortcode utama', 'brilli-wedding-invitation-maker'); ?></span>
                            <code>[<?php echo esc_html(self::SHORTCODE); ?>]</code>
                            <p><?php esc_html_e('Tempel di Elementor, Gutenberg, atau widget shortcode.', 'brilli-wedding-invitation-maker'); ?></p>
                        </div>
                    </aside>

                    <main class="brilli-wim-admin__content">
                        <form method="post" action="options.php">
                            <?php settings_fields(self::OPTION_GROUP); ?>

                            <section id="brilli-wim-links" class="brilli-wim-admin__card">
                                <div class="brilli-wim-admin__card-heading">
                                    <span><?php esc_html_e('Langkah 1', 'brilli-wedding-invitation-maker'); ?></span>
                                    <h2><?php esc_html_e('Tautan undangan', 'brilli-wedding-invitation-maker'); ?></h2>
                                    <p><?php esc_html_e('Tentukan halaman undangan untuk setiap bahasa. Nama tamu ditambahkan otomatis ke URL.', 'brilli-wedding-invitation-maker'); ?></p>
                                </div>

                                <div class="brilli-wim-admin__field-grid">
                                    <div class="brilli-wim-admin__field">
                                        <label for="brilli_wim_base_url_id"><?php esc_html_e('URL Indonesia', 'brilli-wedding-invitation-maker'); ?></label>
                                        <input type="url" id="brilli_wim_base_url_id" name="<?php echo esc_attr(self::OPTION_KEY); ?>[base_url_id]" value="<?php echo esc_attr($options['base_url_id']); ?>" placeholder="https://brillian.my.id/">
                                        <p><?php esc_html_e('Halaman utama untuk tamu berbahasa Indonesia.', 'brilli-wedding-invitation-maker'); ?></p>
                                    </div>

                                    <div class="brilli-wim-admin__field">
                                        <label for="brilli_wim_base_url_en"><?php esc_html_e('URL English', 'brilli-wedding-invitation-maker'); ?></label>
                                        <input type="url" id="brilli_wim_base_url_en" name="<?php echo esc_attr(self::OPTION_KEY); ?>[base_url_en]" value="<?php echo esc_attr($options['base_url_en']); ?>" placeholder="https://brillian.my.id/en/">
                                        <p><?php esc_html_e('Halaman utama untuk tamu berbahasa Inggris.', 'brilli-wedding-invitation-maker'); ?></p>
                                    </div>

                                    <div class="brilli-wim-admin__field brilli-wim-admin__field--compact">
                                        <label for="brilli_wim_url_param"><?php esc_html_e('Parameter nama', 'brilli-wedding-invitation-maker'); ?></label>
                                        <input type="text" id="brilli_wim_url_param" name="<?php echo esc_attr(self::OPTION_KEY); ?>[url_param]" value="<?php echo esc_attr($options['url_param']); ?>" placeholder="to">
                                        <p>
                                            <?php
                                            printf(
                                                /* translators: 1: URL parameter name, 2: example query string. */
                                                wp_kses(
                                                    __('Gunakan %1$s jika situs undangan memakai format %2$s.', 'brilli-wedding-invitation-maker'),
                                                    array('code' => array())
                                                ),
                                                '<code>to</code>',
                                                '<code>?to=Nama</code>'
                                            );
                                            ?>
                                        </p>
                                    </div>
                                </div>

                                <details class="brilli-wim-admin__advanced">
                                    <summary><?php esc_html_e('Atur template URL khusus', 'brilli-wedding-invitation-maker'); ?> <span><?php esc_html_e('Opsional', 'brilli-wedding-invitation-maker'); ?></span></summary>
                                    <div class="brilli-wim-admin__advanced-content">
                                        <p><?php esc_html_e('Isi bagian ini hanya jika struktur URL Anda berbeda. Template khusus akan menggantikan URL utama di atas.', 'brilli-wedding-invitation-maker'); ?></p>
                                        <div class="brilli-wim-admin__field-grid">
                                            <div class="brilli-wim-admin__field">
                                                <label for="brilli_wim_custom_url_id"><?php esc_html_e('Template URL Indonesia', 'brilli-wedding-invitation-maker'); ?></label>
                                                <input type="text" id="brilli_wim_custom_url_id" name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_url_id]" value="<?php echo esc_attr($options['custom_url_id']); ?>" placeholder="https://brillian.my.id/?to={encoded_name}">
                                            </div>
                                            <div class="brilli-wim-admin__field">
                                                <label for="brilli_wim_custom_url_en"><?php esc_html_e('Template URL English', 'brilli-wedding-invitation-maker'); ?></label>
                                                <input type="text" id="brilli_wim_custom_url_en" name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_url_en]" value="<?php echo esc_attr($options['custom_url_en']); ?>" placeholder="https://brillian.my.id/en/?to={encoded_name}">
                                            </div>
                                        </div>
                                        <p class="brilli-wim-admin__helper"><?php esc_html_e('Placeholder URL:', 'brilli-wedding-invitation-maker'); ?> <code>{name}</code> <code>{encoded_name}</code> <code>{phone}</code></p>
                                    </div>
                                </details>
                            </section>

                            <section id="brilli-wim-messages" class="brilli-wim-admin__card">
                                <div class="brilli-wim-admin__card-heading">
                                    <span><?php esc_html_e('Langkah 2', 'brilli-wedding-invitation-maker'); ?></span>
                                    <h2><?php esc_html_e('Template pesan', 'brilli-wedding-invitation-maker'); ?></h2>
                                    <p><?php esc_html_e('Sesuaikan gaya pesan untuk setiap tamu dalam bahasa Indonesia dan Inggris.', 'brilli-wedding-invitation-maker'); ?></p>
                                </div>

                                <div class="brilli-wim-admin__tokens" aria-label="<?php esc_attr_e('Placeholder yang tersedia', 'brilli-wedding-invitation-maker'); ?>">
                                    <span><?php esc_html_e('Placeholder:', 'brilli-wedding-invitation-maker'); ?></span>
                                    <code>{name}</code>
                                    <code>{phone}</code>
                                    <code>{invitation_url}</code>
                                    <code>{encoded_name}</code>
                                </div>

                                <?php foreach ($template_sections as $template_key => $template) : ?>
                                    <div class="brilli-wim-admin__template">
                                        <div class="brilli-wim-admin__template-heading">
                                            <span><?php echo esc_html($template['label']); ?></span>
                                            <p><?php echo esc_html($template['description']); ?></p>
                                        </div>
                                        <div class="brilli-wim-admin__template-grid">
                                            <?php foreach (array('id' => __('Indonesia', 'brilli-wedding-invitation-maker'), 'en' => __('English', 'brilli-wedding-invitation-maker')) as $language_key => $language_label) : ?>
                                                <?php
                                                $option_key = 'message_' . $template_key . '_' . $language_key;
                                                $field_id = 'brilli_wim_' . $option_key;
                                                ?>
                                                <div class="brilli-wim-admin__field">
                                                    <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($language_label); ?></label>
                                                    <textarea id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr(self::OPTION_KEY); ?>[<?php echo esc_attr($option_key); ?>]" rows="12"><?php echo esc_textarea($options[$option_key]); ?></textarea>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </section>

                            <section id="brilli-wim-buttons" class="brilli-wim-admin__card">
                                <div class="brilli-wim-admin__card-heading">
                                    <span><?php esc_html_e('Langkah 3', 'brilli-wedding-invitation-maker'); ?></span>
                                    <h2><?php esc_html_e('Label tombol', 'brilli-wedding-invitation-maker'); ?></h2>
                                    <p><?php esc_html_e('Gunakan label singkat agar setiap tindakan mudah dipahami tamu.', 'brilli-wedding-invitation-maker'); ?></p>
                                </div>

                                <div class="brilli-wim-admin__button-grid">
                                    <?php foreach ($button_fields as $option_key => $button_field) : ?>
                                        <div class="brilli-wim-admin__field">
                                            <label for="brilli_wim_<?php echo esc_attr($option_key); ?>"><?php echo esc_html($button_field[0]); ?></label>
                                            <input type="text" id="brilli_wim_<?php echo esc_attr($option_key); ?>" name="<?php echo esc_attr(self::OPTION_KEY); ?>[<?php echo esc_attr($option_key); ?>]" value="<?php echo esc_attr($options[$option_key]); ?>">
                                            <p><?php echo esc_html($button_field[1]); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>

                            <div class="brilli-wim-admin__savebar">
                                <div>
                                    <strong><?php esc_html_e('Simpan pengaturan', 'brilli-wedding-invitation-maker'); ?></strong>
                                    <span><?php esc_html_e('Perubahan langsung digunakan oleh shortcode.', 'brilli-wedding-invitation-maker'); ?></span>
                                </div>
                                <?php submit_button(__('Simpan perubahan', 'brilli-wedding-invitation-maker'), 'primary large', 'submit', false); ?>
                            </div>
                        </form>

                        <section id="brilli-wim-usage" class="brilli-wim-admin__card brilli-wim-admin__usage">
                            <div class="brilli-wim-admin__card-heading">
                                <span><?php esc_html_e('Langkah 4', 'brilli-wedding-invitation-maker'); ?></span>
                                <h2><?php esc_html_e('Pasang di halaman', 'brilli-wedding-invitation-maker'); ?></h2>
                                <p><?php esc_html_e('Tambahkan salah satu shortcode berikut ke halaman tempat generator undangan akan ditampilkan.', 'brilli-wedding-invitation-maker'); ?></p>
                            </div>
                            <div class="brilli-wim-admin__shortcodes">
                                <div>
                                    <span><?php esc_html_e('Direkomendasikan', 'brilli-wedding-invitation-maker'); ?></span>
                                    <code>[<?php echo esc_html(self::SHORTCODE); ?>]</code>
                                </div>
                                <div>
                                    <span><?php esc_html_e('Alias singkat', 'brilli-wedding-invitation-maker'); ?></span>
                                    <code>[<?php echo esc_html(self::SHORTCODE_ALIAS); ?>]</code>
                                </div>
                            </div>
                        </section>

                        <footer class="brilli-wim-admin__footer">
                            <span><?php esc_html_e('Wedding Invitation Maker by BRILLI', 'brilli-wedding-invitation-maker'); ?></span>
                            <a href="https://brillianav.com" target="_blank" rel="noopener noreferrer">brillianav.com</a>
                        </footer>
                    </main>
                </div>
            </div>
            <?php
        }

        /**
         * Render a wedding invitation generator instance.
         *
         * @param array $_atts Shortcode attributes reserved for future use.
         * @return string
         */
        public function render_shortcode($_atts) {
            $options = $this->get_options();
            $page_id = function_exists('get_queried_object_id') ? absint(get_queried_object_id()) : 0;

            if (!$page_id && function_exists('get_the_ID')) {
                $page_id = absint(get_the_ID());
            }

            $can_clear_history = function_exists('current_user_can') && current_user_can('manage_options');

            wp_enqueue_style(self::STYLE_HANDLE);
            wp_enqueue_script(self::SCRIPT_HANDLE);

            $settings = array(
                'baseUrlId' => esc_url_raw($options['base_url_id']),
                'baseUrlEn' => esc_url_raw($options['base_url_en']),
                'urlParam' => sanitize_key($options['url_param']),
                'customUrlId' => $options['custom_url_id'],
                'customUrlEn' => $options['custom_url_en'],
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'historyNonce' => wp_create_nonce('brilli_wim_history_' . $page_id),
                'pageId' => $page_id,
                'canClearHistory' => $can_clear_history,
                'messages' => array(
                    'formal' => array(
                        'id' => $options['message_formal_id'],
                        'en' => $options['message_formal_en'],
                    ),
                    'casual' => array(
                        'id' => $options['message_casual_id'],
                        'en' => $options['message_casual_en'],
                    ),
                    'warm' => array(
                        'id' => $options['message_warm_id'],
                        'en' => $options['message_warm_en'],
                    ),
                ),
                'i18n' => array(
                    'nameRequired' => __('Masukkan nama tamu untuk membuat undangan.', 'brilli-wedding-invitation-maker'),
                    'generated' => __('Tiga versi undangan berhasil dibuat dan siap dibagikan.', 'brilli-wedding-invitation-maker'),
                    'copyIdSuccess' => __('Kalimat Indonesia berhasil disalin.', 'brilli-wedding-invitation-maker'),
                    'copyEnSuccess' => __('English message copied.', 'brilli-wedding-invitation-maker'),
                    'copyError' => __('Pesan tidak dapat disalin. Silakan salin secara manual.', 'brilli-wedding-invitation-maker'),
                    'copiedId' => __('Tersalin', 'brilli-wedding-invitation-maker'),
                    'copiedEn' => __('Copied', 'brilli-wedding-invitation-maker'),
                    'historyClear' => __('Hapus semua riwayat', 'brilli-wedding-invitation-maker'),
                    'historyClearConfirm' => __('Klik lagi untuk menghapus', 'brilli-wedding-invitation-maker'),
                    'historyCleared' => __('Riwayat berhasil dihapus.', 'brilli-wedding-invitation-maker'),
                    'historyGeneratedAt' => __('Dibuat pada', 'brilli-wedding-invitation-maker'),
                    'historyLoading' => __('Memuat riwayat…', 'brilli-wedding-invitation-maker'),
                    'historyLoadMore' => __('Tampilkan lebih banyak', 'brilli-wedding-invitation-maker'),
                    'historyLoadError' => __('Riwayat belum dapat dimuat. Silakan coba lagi.', 'brilli-wedding-invitation-maker'),
                    'historySaveError' => __('Undangan berhasil dibuat, tetapi riwayat gagal disimpan.', 'brilli-wedding-invitation-maker'),
                ),
            );

            $wrapper_id = 'brilli-wim-' . wp_generate_uuid4();
            $templates = array(
                'formal' => __('Formal', 'brilli-wedding-invitation-maker'),
                'casual' => __('Nonformal 1', 'brilli-wedding-invitation-maker'),
                'warm' => __('Nonformal 2', 'brilli-wedding-invitation-maker'),
            );
            $hero_image_url = BRILLI_WIM_PLUGIN_URL . 'assets/favicon-wedding.png';

            ob_start();
            ?>
            <div id="<?php echo esc_attr($wrapper_id); ?>" class="brilli-wim" data-settings="<?php echo esc_attr(wp_json_encode($settings)); ?>">
                <header class="brilli-wim__intro">
                    <div class="brilli-wim__intro-copy">
                        <span class="brilli-wim__eyebrow"><i aria-hidden="true"></i> <?php esc_html_e('Wedding invitation studio', 'brilli-wedding-invitation-maker'); ?></span>
                        <h2><?php esc_html_e('Buat pesan undangan yang terasa personal.', 'brilli-wedding-invitation-maker'); ?></h2>
                        <p><?php esc_html_e('Isi data tamu sekali, lalu pilih gaya pesan dan bahasa yang paling sesuai.', 'brilli-wedding-invitation-maker'); ?></p>
                    </div>
                    <figure class="brilli-wim__hero-art">
                        <img src="<?php echo esc_url($hero_image_url); ?>" width="190" height="190" alt="<?php esc_attr_e('Ilustrasi pixel pasangan pengantin', 'brilli-wedding-invitation-maker'); ?>" decoding="async">
                    </figure>
                </header>

                <section class="brilli-wim__composer" aria-labelledby="<?php echo esc_attr($wrapper_id); ?>-guest-heading">
                    <div class="brilli-wim__step-heading">
                        <span>01</span>
                        <div>
                            <h3 id="<?php echo esc_attr($wrapper_id); ?>-guest-heading"><?php esc_html_e('Masukkan data tamu', 'brilli-wedding-invitation-maker'); ?></h3>
                            <p><?php esc_html_e('Nama dipakai untuk mempersonalisasi tautan dan seluruh template pesan.', 'brilli-wedding-invitation-maker'); ?></p>
                        </div>
                    </div>

                    <div class="brilli-wim__form-grid">
                        <div class="brilli-wim__field">
                            <label for="<?php echo esc_attr($wrapper_id); ?>-name"><?php esc_html_e('Nama tamu', 'brilli-wedding-invitation-maker'); ?></label>
                            <input id="<?php echo esc_attr($wrapper_id); ?>-name" class="brilli-wim__name" type="text" placeholder="<?php esc_attr_e('Contoh: Christopher Emmanuel', 'brilli-wedding-invitation-maker'); ?>" autocomplete="name" required aria-required="true">
                        </div>

                        <div class="brilli-wim__field">
                            <label for="<?php echo esc_attr($wrapper_id); ?>-phone"><?php esc_html_e('Nomor WhatsApp', 'brilli-wedding-invitation-maker'); ?> <span><?php esc_html_e('Opsional', 'brilli-wedding-invitation-maker'); ?></span></label>
                            <input id="<?php echo esc_attr($wrapper_id); ?>-phone" class="brilli-wim__phone" type="tel" placeholder="<?php esc_attr_e('Contoh: 08123456789', 'brilli-wedding-invitation-maker'); ?>" autocomplete="tel" inputmode="tel">
                        </div>
                    </div>

                    <div class="brilli-wim__generate-row">
                        <button type="button" class="brilli-wim__generate">
                            <span><?php echo esc_html($options['generate_button']); ?></span>
                            <span aria-hidden="true">→</span>
                        </button>
                        <div class="brilli-wim__local-tools">
                            <button type="button" class="brilli-wim__history-trigger" aria-haspopup="dialog" aria-controls="<?php echo esc_attr($wrapper_id); ?>-history-dialog">
                                <span class="brilli-wim__history-icon" aria-hidden="true">↺</span>
                                <span><?php esc_html_e('Lihat riwayat', 'brilli-wedding-invitation-maker'); ?></span>
                                <span class="brilli-wim__history-count" aria-label="<?php esc_attr_e('Jumlah riwayat', 'brilli-wedding-invitation-maker'); ?>">0</span>
                            </button>
                            <p class="brilli-wim__privacy"><?php esc_html_e('Nama dan waktu pembuatan disimpan di server serta dapat dilihat pengunjung halaman ini. Nomor WhatsApp tidak disimpan.', 'brilli-wedding-invitation-maker'); ?></p>
                        </div>
                    </div>

                    <p class="brilli-wim__notice" aria-live="polite" aria-atomic="true"></p>
                </section>

                <section class="brilli-wim__result" aria-labelledby="<?php echo esc_attr($wrapper_id); ?>-result-heading" hidden>
                    <div class="brilli-wim__result-heading">
                        <div class="brilli-wim__step-heading">
                            <span>02</span>
                            <div>
                                <h3 id="<?php echo esc_attr($wrapper_id); ?>-result-heading"><?php esc_html_e('Pesan siap dibagikan', 'brilli-wedding-invitation-maker'); ?></h3>
                                <p><?php esc_html_e('Pilih gaya, periksa isi pesan, lalu salin atau kirim melalui WhatsApp.', 'brilli-wedding-invitation-maker'); ?></p>
                            </div>
                        </div>
                        <span class="brilli-wim__ready"><i aria-hidden="true"></i> <?php esc_html_e('Siap digunakan', 'brilli-wedding-invitation-maker'); ?></span>
                    </div>

                    <div class="brilli-wim__link-grid">
                        <div class="brilli-wim__link-card">
                            <span class="brilli-wim__language">ID</span>
                            <div class="brilli-wim__field">
                                <label for="<?php echo esc_attr($wrapper_id); ?>-url-id"><?php esc_html_e('Tautan Indonesia', 'brilli-wedding-invitation-maker'); ?></label>
                                <input id="<?php echo esc_attr($wrapper_id); ?>-url-id" class="brilli-wim__url brilli-wim__url--id" type="text" readonly>
                            </div>
                        </div>

                        <div class="brilli-wim__link-card">
                            <span class="brilli-wim__language">EN</span>
                            <div class="brilli-wim__field">
                                <label for="<?php echo esc_attr($wrapper_id); ?>-url-en"><?php esc_html_e('English invitation link', 'brilli-wedding-invitation-maker'); ?></label>
                                <input id="<?php echo esc_attr($wrapper_id); ?>-url-en" class="brilli-wim__url brilli-wim__url--en" type="text" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="brilli-wim__tabs" role="tablist" aria-label="<?php esc_attr_e('Pilih gaya kalimat undangan', 'brilli-wedding-invitation-maker'); ?>">
                        <?php $tab_index = 0; ?>
                        <?php foreach ($templates as $template_key => $template_label) : ?>
                            <button
                                id="<?php echo esc_attr($wrapper_id . '-tab-' . $template_key); ?>"
                                class="brilli-wim__tab"
                                type="button"
                                role="tab"
                                aria-selected="<?php echo 0 === $tab_index ? 'true' : 'false'; ?>"
                                aria-controls="<?php echo esc_attr($wrapper_id . '-panel-' . $template_key); ?>"
                                tabindex="<?php echo 0 === $tab_index ? '0' : '-1'; ?>"
                                data-template="<?php echo esc_attr($template_key); ?>"
                            ><?php echo esc_html($template_label); ?></button>
                            <?php $tab_index++; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php $panel_index = 0; ?>
                    <?php foreach ($templates as $template_key => $template_label) : ?>
                        <section
                            id="<?php echo esc_attr($wrapper_id . '-panel-' . $template_key); ?>"
                            class="brilli-wim__panel"
                            role="tabpanel"
                            aria-labelledby="<?php echo esc_attr($wrapper_id . '-tab-' . $template_key); ?>"
                            data-template="<?php echo esc_attr($template_key); ?>"
                            <?php echo 0 === $panel_index ? '' : 'hidden'; ?>
                        >
                            <h4 class="brilli-wim__panel-title"><?php echo esc_html($template_label); ?></h4>

                            <div class="brilli-wim__message-grid">
                                <article class="brilli-wim__message-card">
                                    <div class="brilli-wim__message-heading">
                                        <span class="brilli-wim__language">ID</span>
                                        <div>
                                            <h5><?php esc_html_e('Bahasa Indonesia', 'brilli-wedding-invitation-maker'); ?></h5>
                                            <p><?php esc_html_e('Untuk tamu berbahasa Indonesia.', 'brilli-wedding-invitation-maker'); ?></p>
                                        </div>
                                    </div>
                                    <label class="brilli-wim__sr-only" for="<?php echo esc_attr($wrapper_id . '-' . $template_key); ?>-message-id">
                                        <?php
                                        printf(
                                            /* translators: %s: invitation template name. */
                                            esc_html__('Pesan %s bahasa Indonesia', 'brilli-wedding-invitation-maker'),
                                            esc_html($template_label)
                                        );
                                        ?>
                                    </label>
                                    <textarea id="<?php echo esc_attr($wrapper_id . '-' . $template_key); ?>-message-id" class="brilli-wim__message" data-template="<?php echo esc_attr($template_key); ?>" data-language="id" rows="14" readonly></textarea>
                                    <div class="brilli-wim__actions">
                                        <button type="button" class="brilli-wim__copy" data-template="<?php echo esc_attr($template_key); ?>" data-language="id"><?php echo esc_html($options['copy_id_button']); ?></button>
                                        <a class="brilli-wim__whatsapp" data-template="<?php echo esc_attr($template_key); ?>" data-language="id" href="#" target="_blank" rel="noopener noreferrer"><?php echo esc_html($options['whatsapp_id_button']); ?></a>
                                    </div>
                                </article>

                                <article class="brilli-wim__message-card">
                                    <div class="brilli-wim__message-heading">
                                        <span class="brilli-wim__language">EN</span>
                                        <div>
                                            <h5><?php esc_html_e('English', 'brilli-wedding-invitation-maker'); ?></h5>
                                            <p><?php esc_html_e('For English-speaking guests.', 'brilli-wedding-invitation-maker'); ?></p>
                                        </div>
                                    </div>
                                    <label class="brilli-wim__sr-only" for="<?php echo esc_attr($wrapper_id . '-' . $template_key); ?>-message-en">
                                        <?php
                                        printf(
                                            /* translators: %s: invitation template name. */
                                            esc_html__('%s English message', 'brilli-wedding-invitation-maker'),
                                            esc_html($template_label)
                                        );
                                        ?>
                                    </label>
                                    <textarea id="<?php echo esc_attr($wrapper_id . '-' . $template_key); ?>-message-en" class="brilli-wim__message" data-template="<?php echo esc_attr($template_key); ?>" data-language="en" rows="14" readonly></textarea>
                                    <div class="brilli-wim__actions">
                                        <button type="button" class="brilli-wim__copy" data-template="<?php echo esc_attr($template_key); ?>" data-language="en"><?php echo esc_html($options['copy_en_button']); ?></button>
                                        <a class="brilli-wim__whatsapp" data-template="<?php echo esc_attr($template_key); ?>" data-language="en" href="#" target="_blank" rel="noopener noreferrer"><?php echo esc_html($options['whatsapp_en_button']); ?></a>
                                    </div>
                                </article>
                            </div>
                        </section>
                        <?php $panel_index++; ?>
                    <?php endforeach; ?>
                </section>

                <dialog
                    id="<?php echo esc_attr($wrapper_id); ?>-history-dialog"
                    class="brilli-wim__history-dialog"
                    aria-labelledby="<?php echo esc_attr($wrapper_id); ?>-history-title"
                    aria-describedby="<?php echo esc_attr($wrapper_id); ?>-history-description"
                >
                    <div class="brilli-wim__history-shell">
                        <header class="brilli-wim__history-header">
                            <div>
                                <span class="brilli-wim__history-kicker"><?php esc_html_e('Riwayat bersama', 'brilli-wedding-invitation-maker'); ?></span>
                                <h3 id="<?php echo esc_attr($wrapper_id); ?>-history-title"><?php esc_html_e('Riwayat nama tamu', 'brilli-wedding-invitation-maker'); ?></h3>
                            </div>
                            <button type="button" class="brilli-wim__history-close" aria-label="<?php esc_attr_e('Tutup riwayat', 'brilli-wedding-invitation-maker'); ?>">×</button>
                        </header>

                        <div class="brilli-wim__history-body">
                            <p id="<?php echo esc_attr($wrapper_id); ?>-history-description" class="brilli-wim__history-description"><?php esc_html_e('Daftar nama yang pernah dibuat di halaman ini oleh seluruh pengunjung, dari yang terbaru.', 'brilli-wedding-invitation-maker'); ?></p>

                            <div class="brilli-wim__history-summary" aria-live="polite">
                                <span class="brilli-wim__history-summary-count">0</span>
                                <span><?php esc_html_e('nama tersimpan', 'brilli-wedding-invitation-maker'); ?></span>
                            </div>

                            <ol class="brilli-wim__history-list"></ol>
                            <button type="button" class="brilli-wim__history-more" hidden><?php esc_html_e('Tampilkan lebih banyak', 'brilli-wedding-invitation-maker'); ?></button>
                            <p class="brilli-wim__history-status" aria-live="polite" aria-atomic="true"></p>
                            <div class="brilli-wim__history-empty">
                                <span aria-hidden="true">✦</span>
                                <strong><?php esc_html_e('Belum ada riwayat', 'brilli-wedding-invitation-maker'); ?></strong>
                                <p><?php esc_html_e('Nama akan muncul di sini setelah undangan berhasil dibuat.', 'brilli-wedding-invitation-maker'); ?></p>
                            </div>
                        </div>

                        <footer class="brilli-wim__history-footer">
                            <p><?php esc_html_e('Hanya nama dan waktu pembuatan yang disimpan di WordPress. Nomor WhatsApp tidak pernah masuk ke riwayat.', 'brilli-wedding-invitation-maker'); ?></p>
                            <?php if ($can_clear_history) : ?>
                                <button type="button" class="brilli-wim__history-clear" disabled><?php esc_html_e('Hapus semua riwayat', 'brilli-wedding-invitation-maker'); ?></button>
                            <?php endif; ?>
                        </footer>
                    </div>
                </dialog>
            </div>
            <?php
            return ob_get_clean();
        }
    }

    register_activation_hook(BRILLI_WIM_PLUGIN_FILE, array('Brilli_Wedding_Invitation_Maker', 'activate'));

    $brilli_wim_plugin = new Brilli_Wedding_Invitation_Maker();
    $brilli_wim_plugin->register_hooks();
}
