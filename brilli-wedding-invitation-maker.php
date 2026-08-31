<?php
/**
 * Plugin Name: Wedding Invitation Maker - BRILLI
 * Plugin URI: https://brillianav.com
 * Description: Generate personalized wedding invitation messages, Indonesian and English invitation URLs, and WhatsApp share links from the frontend.
 * Version: 1.2.1
 * Author: Brillian AV
 * Author URI: https://brillianav.com
 * License: GPLv2 or later
 * Text Domain: brilli-wedding-invitation-maker
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Brilli_Wedding_Invitation_Maker')) {
    class Brilli_Wedding_Invitation_Maker {
        const VERSION = '1.2.1';
        const OPTION_KEY = 'brilli_wedding_invitation_maker_options';
        const MENU_SLUG = 'brilli-wedding-invitation-maker';
        const SHORTCODE = 'brilli_wedding_invitation_maker';

        private $admin_page_hook = '';

        public function __construct() {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('wp_enqueue_scripts', array($this, 'register_assets'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_action_links'));
            add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
            add_shortcode('brilli_wedding_invitation', array($this, 'render_shortcode'));
        }

        public static function activate() {
            if (false === get_option(self::OPTION_KEY, false)) {
                add_option(self::OPTION_KEY, self::default_options_static());
            }
        }

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

        public function default_options() {
            return self::default_options_static();
        }

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

        public function add_admin_menu() {
            $this->admin_page_hook = add_menu_page(
                'Wedding Invitation Maker - BRILLI',
                'Wedding Invitation',
                'manage_options',
                self::MENU_SLUG,
                array($this, 'render_admin_page'),
                'dashicons-heart',
                58
            );
        }

        public function enqueue_admin_assets($hook_suffix) {
            if ($this->admin_page_hook !== $hook_suffix) {
                return;
            }

            wp_enqueue_style(
                'brilli-wedding-invitation-maker-admin',
                plugin_dir_url(__FILE__) . 'assets/brilli-wedding-invitation-maker-admin.css',
                array(),
                self::VERSION
            );
        }

        public function add_plugin_action_links($links) {
            $settings_link = sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)),
                esc_html__('Buka pengaturan', 'brilli-wedding-invitation-maker')
            );

            array_unshift($links, $settings_link);

            return $links;
        }

        public function register_settings() {
            register_setting(
                'brilli_wedding_invitation_maker_group',
                self::OPTION_KEY,
                array($this, 'sanitize_options')
            );
        }

        public function sanitize_options($input) {
            $defaults = $this->default_options();
            $output = array();

            $input = is_array($input) ? $input : array();

            $output['base_url_id'] = isset($input['base_url_id']) ? esc_url_raw(trim($input['base_url_id'])) : $defaults['base_url_id'];
            $output['base_url_en'] = isset($input['base_url_en']) ? esc_url_raw(trim($input['base_url_en'])) : $defaults['base_url_en'];
            $output['url_param'] = isset($input['url_param']) ? sanitize_key(trim($input['url_param'])) : $defaults['url_param'];
            $output['custom_url_id'] = isset($input['custom_url_id']) ? sanitize_text_field(trim($input['custom_url_id'])) : '';
            $output['custom_url_en'] = isset($input['custom_url_en']) ? sanitize_text_field(trim($input['custom_url_en'])) : '';
            foreach (array('formal', 'casual', 'warm') as $template_key) {
                foreach (array('id', 'en') as $language) {
                    $option_key = 'message_' . $template_key . '_' . $language;
                    $output[$option_key] = isset($input[$option_key]) ? wp_kses_post($input[$option_key]) : $defaults[$option_key];
                }
            }
            $output['generate_button'] = isset($input['generate_button']) ? sanitize_text_field($input['generate_button']) : $defaults['generate_button'];
            $output['copy_id_button'] = isset($input['copy_id_button']) ? sanitize_text_field($input['copy_id_button']) : $defaults['copy_id_button'];
            $output['copy_en_button'] = isset($input['copy_en_button']) ? sanitize_text_field($input['copy_en_button']) : $defaults['copy_en_button'];
            $output['whatsapp_id_button'] = isset($input['whatsapp_id_button']) ? sanitize_text_field($input['whatsapp_id_button']) : $defaults['whatsapp_id_button'];
            $output['whatsapp_en_button'] = isset($input['whatsapp_en_button']) ? sanitize_text_field($input['whatsapp_en_button']) : $defaults['whatsapp_en_button'];

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

        public function register_assets() {
            wp_register_style(
                'brilli-wedding-invitation-maker-style',
                plugin_dir_url(__FILE__) . 'assets/brilli-wedding-invitation-maker.css',
                array(),
                self::VERSION
            );

            wp_register_script(
                'brilli-wedding-invitation-maker-script',
                plugin_dir_url(__FILE__) . 'assets/brilli-wedding-invitation-maker.js',
                array(),
                self::VERSION,
                true
            );
        }

        public function render_admin_page() {
            if (!current_user_can('manage_options')) {
                return;
            }

            $options = $this->get_options();
            $logo_url = plugin_dir_url(__FILE__) . 'assets/logo-bav-white.png';
            $template_sections = array(
                'formal' => array(
                    'label' => 'formal',
                    'description' => 'Untuk keluarga, kolega, dan relasi yang membutuhkan bahasa resmi.',
                ),
                'casual' => array(
                    'label' => 'non-formal 1',
                    'description' => 'Untuk teman dan kenalan dengan bahasa yang lebih santai.',
                ),
                'warm' => array(
                    'label' => 'non-formal 2',
                    'description' => 'Untuk sahabat dan orang terdekat dengan bahasa yang hangat.',
                ),
            );
            $button_fields = array(
                'generate_button' => array('Tombol generate', 'Tindakan utama untuk membuat semua versi pesan.'),
                'copy_id_button' => array('Salin Indonesia', 'Menyalin pesan berbahasa Indonesia.'),
                'copy_en_button' => array('Salin English', 'Menyalin pesan berbahasa Inggris.'),
                'whatsapp_id_button' => array('WhatsApp Indonesia', 'Membuka WhatsApp dengan pesan Indonesia.'),
                'whatsapp_en_button' => array('WhatsApp English', 'Membuka WhatsApp dengan pesan Inggris.'),
            );
            ?>
            <div class="wrap brilli-wim-admin">
                <?php settings_errors(); ?>

                <header class="brilli-wim-admin__hero">
                    <div class="brilli-wim-admin__brand">
                        <img src="<?php echo esc_url($logo_url); ?>" width="84" height="84" alt="BRILLI">
                        <div>
                            <span class="brilli-wim-admin__eyebrow">BRILLI tools</span>
                            <h1>Wedding Invitation Maker</h1>
                            <p>Kelola tautan dan enam template pesan undangan dari satu tempat.</p>
                        </div>
                    </div>
                    <div class="brilli-wim-admin__hero-actions">
                        <span class="brilli-wim-admin__version">Versi <?php echo esc_html(self::VERSION); ?></span>
                        <a class="brilli-wim-admin__hero-link" href="#brilli-wim-usage">Lihat cara pakai</a>
                    </div>
                </header>

                <div class="brilli-wim-admin__shell">
                    <aside class="brilli-wim-admin__sidebar">
                        <nav class="brilli-wim-admin__nav" aria-label="Navigasi pengaturan">
                            <a href="#brilli-wim-links"><span>01</span><strong>Tautan undangan</strong></a>
                            <a href="#brilli-wim-messages"><span>02</span><strong>Template pesan</strong></a>
                            <a href="#brilli-wim-buttons"><span>03</span><strong>Label tombol</strong></a>
                            <a href="#brilli-wim-usage"><span>04</span><strong>Cara pakai</strong></a>
                        </nav>

                        <div class="brilli-wim-admin__quick-card">
                            <span>Shortcode utama</span>
                            <code>[<?php echo esc_html(self::SHORTCODE); ?>]</code>
                            <p>Tempel di Elementor, Gutenberg, atau widget shortcode.</p>
                        </div>
                    </aside>

                    <main class="brilli-wim-admin__content">
                        <form method="post" action="options.php">
                            <?php settings_fields('brilli_wedding_invitation_maker_group'); ?>

                            <section id="brilli-wim-links" class="brilli-wim-admin__card">
                                <div class="brilli-wim-admin__card-heading">
                                    <span>Langkah 1</span>
                                    <h2>Tautan undangan</h2>
                                    <p>Tentukan halaman undangan untuk setiap bahasa. Nama tamu ditambahkan otomatis ke URL.</p>
                                </div>

                                <div class="brilli-wim-admin__field-grid">
                                    <div class="brilli-wim-admin__field">
                                        <label for="brilli_wim_base_url_id">URL Indonesia</label>
                                        <input type="url" id="brilli_wim_base_url_id" name="<?php echo esc_attr(self::OPTION_KEY); ?>[base_url_id]" value="<?php echo esc_attr($options['base_url_id']); ?>" placeholder="https://brillian.my.id/">
                                        <p>Halaman utama untuk tamu berbahasa Indonesia.</p>
                                    </div>

                                    <div class="brilli-wim-admin__field">
                                        <label for="brilli_wim_base_url_en">URL English</label>
                                        <input type="url" id="brilli_wim_base_url_en" name="<?php echo esc_attr(self::OPTION_KEY); ?>[base_url_en]" value="<?php echo esc_attr($options['base_url_en']); ?>" placeholder="https://brillian.my.id/en/">
                                        <p>Halaman utama untuk tamu berbahasa Inggris.</p>
                                    </div>

                                    <div class="brilli-wim-admin__field brilli-wim-admin__field--compact">
                                        <label for="brilli_wim_url_param">Parameter nama</label>
                                        <input type="text" id="brilli_wim_url_param" name="<?php echo esc_attr(self::OPTION_KEY); ?>[url_param]" value="<?php echo esc_attr($options['url_param']); ?>" placeholder="to">
                                        <p>Gunakan <code>to</code> jika situs undangan memakai format <code>?to=Nama</code>.</p>
                                    </div>
                                </div>

                                <details class="brilli-wim-admin__advanced">
                                    <summary>Atur template URL khusus <span>Opsional</span></summary>
                                    <div class="brilli-wim-admin__advanced-content">
                                        <p>Isi bagian ini hanya jika struktur URL Anda berbeda. Template khusus akan menggantikan URL utama di atas.</p>
                                        <div class="brilli-wim-admin__field-grid">
                                            <div class="brilli-wim-admin__field">
                                                <label for="brilli_wim_custom_url_id">Template URL Indonesia</label>
                                                <input type="text" id="brilli_wim_custom_url_id" name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_url_id]" value="<?php echo esc_attr($options['custom_url_id']); ?>" placeholder="https://brillian.my.id/?to={encoded_name}">
                                            </div>
                                            <div class="brilli-wim-admin__field">
                                                <label for="brilli_wim_custom_url_en">Template URL English</label>
                                                <input type="text" id="brilli_wim_custom_url_en" name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_url_en]" value="<?php echo esc_attr($options['custom_url_en']); ?>" placeholder="https://brillian.my.id/en/?to={encoded_name}">
                                            </div>
                                        </div>
                                        <p class="brilli-wim-admin__helper">Placeholder URL: <code>{name}</code> <code>{encoded_name}</code> <code>{phone}</code></p>
                                    </div>
                                </details>
                            </section>

                            <section id="brilli-wim-messages" class="brilli-wim-admin__card">
                                <div class="brilli-wim-admin__card-heading">
                                    <span>Langkah 2</span>
                                    <h2>Template pesan</h2>
                                    <p>Sesuaikan gaya pesan untuk setiap tamu dalam bahasa Indonesia dan Inggris.</p>
                                </div>

                                <div class="brilli-wim-admin__tokens" aria-label="Placeholder yang tersedia">
                                    <span>Placeholder:</span>
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
                                            <?php foreach (array('id' => 'Indonesia', 'en' => 'English') as $language_key => $language_label) : ?>
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
                                    <span>Langkah 3</span>
                                    <h2>Label tombol</h2>
                                    <p>Gunakan label singkat agar setiap tindakan mudah dipahami tamu.</p>
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
                                    <strong>Simpan pengaturan</strong>
                                    <span>Perubahan langsung digunakan oleh shortcode.</span>
                                </div>
                                <?php submit_button('Simpan perubahan', 'primary large', 'submit', false); ?>
                            </div>
                        </form>

                        <section id="brilli-wim-usage" class="brilli-wim-admin__card brilli-wim-admin__usage">
                            <div class="brilli-wim-admin__card-heading">
                                <span>Langkah 4</span>
                                <h2>Pasang di halaman</h2>
                                <p>Tambahkan salah satu shortcode berikut ke halaman tempat generator undangan akan ditampilkan.</p>
                            </div>
                            <div class="brilli-wim-admin__shortcodes">
                                <div>
                                    <span>Direkomendasikan</span>
                                    <code>[<?php echo esc_html(self::SHORTCODE); ?>]</code>
                                </div>
                                <div>
                                    <span>Alias singkat</span>
                                    <code>[brilli_wedding_invitation]</code>
                                </div>
                            </div>
                        </section>

                        <footer class="brilli-wim-admin__footer">
                            <span>Wedding Invitation Maker by BRILLI</span>
                            <a href="https://brillianav.com" target="_blank" rel="noopener noreferrer">brillianav.com</a>
                        </footer>
                    </main>
                </div>
            </div>
            <?php
        }

        public function render_shortcode($atts) {
            $options = $this->get_options();

            wp_enqueue_style('brilli-wedding-invitation-maker-style');
            wp_enqueue_script('brilli-wedding-invitation-maker-script');

            $settings = array(
                'baseUrlId' => esc_url_raw($options['base_url_id']),
                'baseUrlEn' => esc_url_raw($options['base_url_en']),
                'urlParam' => sanitize_key($options['url_param']),
                'customUrlId' => $options['custom_url_id'],
                'customUrlEn' => $options['custom_url_en'],
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
            );

            $wrapper_id = 'brilli-wim-' . wp_generate_uuid4();
            $templates = array(
                'formal' => 'formal',
                'casual' => 'non-formal 1',
                'warm' => 'non-formal 2',
            );

            ob_start();
            ?>
            <div id="<?php echo esc_attr($wrapper_id); ?>" class="brilli-wim" data-settings="<?php echo esc_attr(wp_json_encode($settings)); ?>">
                <div class="brilli-wim__field">
                    <label for="<?php echo esc_attr($wrapper_id); ?>-name">Nama</label>
                    <input id="<?php echo esc_attr($wrapper_id); ?>-name" class="brilli-wim__name" type="text" placeholder="Masukkan nama tamu" autocomplete="name">
                </div>

                <div class="brilli-wim__field">
                    <label for="<?php echo esc_attr($wrapper_id); ?>-phone">Nomor HP / WhatsApp</label>
                    <input id="<?php echo esc_attr($wrapper_id); ?>-phone" class="brilli-wim__phone" type="tel" placeholder="Contoh: 08123456789" autocomplete="tel">
                </div>

                <button type="button" class="brilli-wim__generate"><?php echo esc_html($options['generate_button']); ?></button>

                <div class="brilli-wim__result" hidden>
                    <div class="brilli-wim__grid">
                        <div class="brilli-wim__field">
                            <label>URL Indonesia</label>
                            <input class="brilli-wim__url brilli-wim__url--id" type="text" readonly>
                        </div>

                        <div class="brilli-wim__field">
                            <label>URL English</label>
                            <input class="brilli-wim__url brilli-wim__url--en" type="text" readonly>
                        </div>
                    </div>

                    <div class="brilli-wim__tabs" role="tablist" aria-label="Pilih gaya kalimat undangan">
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
                            <h2 class="brilli-wim__panel-title"><?php echo esc_html($template_label); ?></h2>

                            <div class="brilli-wim__block">
                                <h3>Kalimat Indonesia</h3>
                                <textarea class="brilli-wim__message" data-template="<?php echo esc_attr($template_key); ?>" data-language="id" rows="14" readonly></textarea>
                                <div class="brilli-wim__actions">
                                    <button type="button" class="brilli-wim__copy" data-template="<?php echo esc_attr($template_key); ?>" data-language="id"><?php echo esc_html($options['copy_id_button']); ?></button>
                                    <a class="brilli-wim__whatsapp" data-template="<?php echo esc_attr($template_key); ?>" data-language="id" href="#" target="_blank" rel="noopener noreferrer"><?php echo esc_html($options['whatsapp_id_button']); ?></a>
                                </div>
                            </div>

                            <div class="brilli-wim__block">
                                <h3>English Message</h3>
                                <textarea class="brilli-wim__message" data-template="<?php echo esc_attr($template_key); ?>" data-language="en" rows="14" readonly></textarea>
                                <div class="brilli-wim__actions">
                                    <button type="button" class="brilli-wim__copy" data-template="<?php echo esc_attr($template_key); ?>" data-language="en"><?php echo esc_html($options['copy_en_button']); ?></button>
                                    <a class="brilli-wim__whatsapp" data-template="<?php echo esc_attr($template_key); ?>" data-language="en" href="#" target="_blank" rel="noopener noreferrer"><?php echo esc_html($options['whatsapp_en_button']); ?></a>
                                </div>
                            </div>
                        </section>
                        <?php $panel_index++; ?>
                    <?php endforeach; ?>

                    <p class="brilli-wim__notice" aria-live="polite"></p>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
    }

    register_activation_hook(__FILE__, array('Brilli_Wedding_Invitation_Maker', 'activate'));
    new Brilli_Wedding_Invitation_Maker();
}
