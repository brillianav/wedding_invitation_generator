<?php
/**
 * Plugin Name: Wedding Invitation Maker - BRILLI
 * Plugin URI: https://brillianav.com
 * Description: Generate personalized wedding invitation messages, Indonesian and English invitation URLs, and WhatsApp share links from the frontend.
 * Version: 1.0.0
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
        const VERSION = '1.0.0';
        const OPTION_KEY = 'brilli_wedding_invitation_maker_options';
        const MENU_SLUG = 'brilli-wedding-invitation-maker';
        const SHORTCODE = 'brilli_wedding_invitation_maker';

        public function __construct() {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
            add_action('wp_enqueue_scripts', array($this, 'register_assets'));
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
                'message_id' => "Kepada Yth.\nBapak/Ibu/Saudara/i {name}\n\nHari bahagia kami akan segera tiba, dan dengan penuh kebahagiaan kami mengundang Bapak/Ibu/Saudara/i untuk hadir dalam acara pernikahan:\n\n📅 Brillian & Midiya\n🗓 Sabtu, 3 Oktober 2026\n📍 Dsn/Ds. Karangdiyeng RT/RW.01, Kec. Kutorejo, Kab. Mojokerto\n\nSilakan akses undangan digital melalui link berikut:\n{invitation_url}\n\nDoa restu dan kehadiran Bapak/Ibu/Saudara/i akan menjadi bagian yang sangat berarti dalam hari bahagia kami.\n\nTerima kasih 🙏",
                'message_en' => "Dear Mr./Mrs./Ms. {name},\n\nOur special day is coming soon, and with great joy, we would like to invite you to attend our wedding celebration:\n\n📅 Brillian & Midiya\n🗓 Saturday, October 3, 2026\n📍 Dsn/Ds. Karangdiyeng RT/RW.01, Kutorejo District, Mojokerto Regency\n\nPlease access our digital invitation through the link below:\n{invitation_url}\n\nYour presence and prayers will mean so much to us on this special day.\n\nThank you 🙏",
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

            return wp_parse_args($saved, $this->default_options());
        }

        public function add_admin_menu() {
            add_options_page(
                'Wedding Invitation Maker - BRILLI',
                'Wedding Invitation Maker',
                'manage_options',
                self::MENU_SLUG,
                array($this, 'render_admin_page')
            );
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
            $output['message_id'] = isset($input['message_id']) ? wp_kses_post($input['message_id']) : $defaults['message_id'];
            $output['message_en'] = isset($input['message_en']) ? wp_kses_post($input['message_en']) : $defaults['message_en'];
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
            ?>
            <div class="wrap brilli-wim-admin">
                <h1>Wedding Invitation Maker - BRILLI</h1>
                <p><strong>New plugin slug:</strong> <code>brilli-wedding-invitation-maker</code></p>
                <p>Gunakan setting ini untuk generate kalimat undangan Indonesia dan English, lengkap dengan URL berbeda dan tombol WhatsApp masing-masing.</p>

                <form method="post" action="options.php">
                    <?php settings_fields('brilli_wedding_invitation_maker_group'); ?>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="brilli_wim_base_url_id">URL Undangan Indonesia</label></th>
                            <td>
                                <input type="url" id="brilli_wim_base_url_id" name="<?php echo esc_attr(self::OPTION_KEY); ?>[base_url_id]" value="<?php echo esc_attr($options['base_url_id']); ?>" class="regular-text" placeholder="https://brillian.my.id/">
                                <p class="description">Contoh hasil: <code>https://brillian.my.id/?to=Christopher%20Emmanuel%20Theodore%20Winchester</code></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="brilli_wim_base_url_en">URL Undangan English</label></th>
                            <td>
                                <input type="url" id="brilli_wim_base_url_en" name="<?php echo esc_attr(self::OPTION_KEY); ?>[base_url_en]" value="<?php echo esc_attr($options['base_url_en']); ?>" class="regular-text" placeholder="https://brillian.my.id/en/">
                                <p class="description">Contoh hasil: <code>https://brillian.my.id/en/?to=Christopher%20Emmanuel%20Theodore%20Winchester</code></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="brilli_wim_url_param">Parameter Nama</label></th>
                            <td>
                                <input type="text" id="brilli_wim_url_param" name="<?php echo esc_attr(self::OPTION_KEY); ?>[url_param]" value="<?php echo esc_attr($options['url_param']); ?>" class="regular-text" placeholder="to">
                                <p class="description">Default: <code>to</code>. Dipakai untuk URL Indonesia dan English.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="brilli_wim_custom_url_id">Custom URL Template Indonesia</label></th>
                            <td>
                                <input type="text" id="brilli_wim_custom_url_id" name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_url_id]" value="<?php echo esc_attr($options['custom_url_id']); ?>" class="large-text" placeholder="https://brillian.my.id/?to={encoded_name}">
                                <p class="description">Opsional. Kalau diisi, ini akan menimpa URL Indonesia biasa. Placeholder: <code>{name}</code>, <code>{encoded_name}</code>, <code>{phone}</code>.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="brilli_wim_custom_url_en">Custom URL Template English</label></th>
                            <td>
                                <input type="text" id="brilli_wim_custom_url_en" name="<?php echo esc_attr(self::OPTION_KEY); ?>[custom_url_en]" value="<?php echo esc_attr($options['custom_url_en']); ?>" class="large-text" placeholder="https://brillian.my.id/en/?to={encoded_name}">
                                <p class="description">Opsional. Kalau diisi, ini akan menimpa URL English biasa. Placeholder: <code>{name}</code>, <code>{encoded_name}</code>, <code>{phone}</code>.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="brilli_wim_message_id">Template Kalimat Indonesia</label></th>
                            <td>
                                <textarea id="brilli_wim_message_id" name="<?php echo esc_attr(self::OPTION_KEY); ?>[message_id]" rows="16" class="large-text code"><?php echo esc_textarea($options['message_id']); ?></textarea>
                                <p class="description">Placeholder: <code>{name}</code>, <code>{phone}</code>, <code>{invitation_url}</code>, <code>{encoded_name}</code>.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><label for="brilli_wim_message_en">Template Kalimat English</label></th>
                            <td>
                                <textarea id="brilli_wim_message_en" name="<?php echo esc_attr(self::OPTION_KEY); ?>[message_en]" rows="16" class="large-text code"><?php echo esc_textarea($options['message_en']); ?></textarea>
                                <p class="description">Placeholder: <code>{name}</code>, <code>{phone}</code>, <code>{invitation_url}</code>, <code>{encoded_name}</code>.</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Label Tombol</th>
                            <td>
                                <p><label>Generate<br><input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[generate_button]" value="<?php echo esc_attr($options['generate_button']); ?>" class="regular-text"></label></p>
                                <p><label>Copy Indonesia<br><input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[copy_id_button]" value="<?php echo esc_attr($options['copy_id_button']); ?>" class="regular-text"></label></p>
                                <p><label>Copy English<br><input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[copy_en_button]" value="<?php echo esc_attr($options['copy_en_button']); ?>" class="regular-text"></label></p>
                                <p><label>WhatsApp Indonesia<br><input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[whatsapp_id_button]" value="<?php echo esc_attr($options['whatsapp_id_button']); ?>" class="regular-text"></label></p>
                                <p><label>WhatsApp English<br><input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[whatsapp_en_button]" value="<?php echo esc_attr($options['whatsapp_en_button']); ?>" class="regular-text"></label></p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Simpan Setting'); ?>
                </form>

                <hr>
                <h2>Cara Pakai</h2>
                <p>Tambahkan shortcode ini di Elementor atau Gutenberg:</p>
                <p><code>[brilli_wedding_invitation_maker]</code></p>
                <p>Shortcode pendek juga tersedia:</p>
                <p><code>[brilli_wedding_invitation]</code></p>

                <p class="brilli-wim-credit">Made by <a href="https://brillianav.com" target="_blank" rel="noopener noreferrer">brilli</a></p>
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
                'messageId' => $options['message_id'],
                'messageEn' => $options['message_en'],
            );

            $wrapper_id = 'brilli-wim-' . wp_generate_uuid4();

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

                    <div class="brilli-wim__block">
                        <h3>Kalimat Indonesia</h3>
                        <textarea class="brilli-wim__message brilli-wim__message--id" rows="14" readonly></textarea>
                        <div class="brilli-wim__actions">
                            <button type="button" class="brilli-wim__copy brilli-wim__copy--id"><?php echo esc_html($options['copy_id_button']); ?></button>
                            <a class="brilli-wim__whatsapp brilli-wim__whatsapp--id" href="#" target="_blank" rel="noopener noreferrer"><?php echo esc_html($options['whatsapp_id_button']); ?></a>
                        </div>
                    </div>

                    <div class="brilli-wim__block">
                        <h3>English Message</h3>
                        <textarea class="brilli-wim__message brilli-wim__message--en" rows="14" readonly></textarea>
                        <div class="brilli-wim__actions">
                            <button type="button" class="brilli-wim__copy brilli-wim__copy--en"><?php echo esc_html($options['copy_en_button']); ?></button>
                            <a class="brilli-wim__whatsapp brilli-wim__whatsapp--en" href="#" target="_blank" rel="noopener noreferrer"><?php echo esc_html($options['whatsapp_en_button']); ?></a>
                        </div>
                    </div>

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
