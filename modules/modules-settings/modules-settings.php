<?php
/**
 * @package PublishPress
 * @author  PublishPress
 *
 * Copyright (c) 2018 PublishPress
 *
 * ------------------------------------------------------------------------------
 * Based on Edit Flow
 * Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Automattic, and
 * others
 * Copyright (c) 2009-2016 Mohammad Jangda, Daniel Bachhuber, et al.
 * ------------------------------------------------------------------------------
 *
 * This file is part of PublishPress
 *
 * PublishPress is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! class_exists('PP_Modules_Settings')) {
    /**
     * class PP_Modules_Settings
     * Threaded commenting in the admin for discussion between writers and editors
     *
     * @author batmoo
     */
    class PP_Modules_Settings extends PP_Module
    {
        const SETTINGS_SLUG = 'pp-modules-settings';

        protected $options_group_name = 'modules_settings';

        public function __construct()
        {
            $this->module_url = $this->get_module_url(__FILE__);
            // Register the module with PublishPress
            $args = [
                'title'                => __('General', 'publishpress'),
                'short_description'    => false,
                'extended_description' => false,
                'module_url'           => $this->module_url,
                'icon_class'           => 'dashicons dashicons-admin-settings',
                'slug'                 => 'modules-settings',
                'default_options'      => [
                    'enabled'          => 'on',
                    'display_branding' => 'on',
                ],
                'configure_page_cb'    => 'print_configure_view',
                'autoload'             => false,
                'options_page'         => true,
            ];

            $this->module = PublishPress()->register_module($this->options_group_name, $args);
        }

        /**
         * Initialize the rest of the stuff in the class if the module is active
         */
        public function init()
        {
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'add_admin_scripts']);
        }

        /**
         * Load any of the admin scripts we need but only on the pages we need them
         */
        public function add_admin_scripts()
        {
            global $pagenow;

            wp_enqueue_style('publishpress-modules-css', $this->module_url . 'lib/modules-settings.css', false,
                PUBLISHPRESS_VERSION, 'all');
        }

        /**
         * Register settings for notifications so we can partially use the Settings API
         * (We use the Settings API for form generation, but not saving)
         *
         * @since 0.7
         * @uses  add_settings_section(), add_settings_field()
         */
        public function register_settings()
        {
            if (PublishPress\Legacy\Util::hasAnyValidLicenseKeySet()) {
                add_settings_section($this->module->options_group_name . '_general', false, '__return_false',
                    $this->module->options_group_name);
                add_settings_field('display_branding', __('Display PublishPress branding:', 'publishpress'),
                    [$this, 'settings_branding_option'], $this->module->options_group_name,
                    $this->module->options_group_name . '_general');
            }
        }

        /**
         * Branding options
         *
         * @since 0.7
         */
        public function settings_branding_option()
        {
            echo '<label for="publishpress_display_branding">';
            echo '<input id="publishpress_display_branding" name="'
                 . $this->options_group_name . '[display_branding]"';
            if (isset($this->module->options->display_branding)) {
                checked($this->module->options->display_branding, 'on');
            }
            echo ' type="checkbox" value="on" />&nbsp;&nbsp;&nbsp;</label>';
            echo '<br />';
        }

        /**
         * Validate data entered by the user
         *
         * @since 0.7
         *
         * @param array $new_options New values that have been entered by the user
         *
         * @return array $new_options Form values after they've been sanitized
         */
        public function settings_validate($new_options)
        {
            return $new_options;
        }

        /**
         * Save the custom settings
         *
         * @param array $new_options New values that have been entered by the user
         */
        public function settings_save($new_options)
        {
            if ( ! isset($_POST['publishpress_options'])) {
                return true;
            }

            global $publishpress;

            $displayBranding = 'off';
            if (isset($_POST[$this->options_group_name]['display_branding'])) {
                $displayBranding = $_POST[$this->options_group_name]['display_branding'];
            }
            $publishpress->update_module_option($this->options_group_name, 'display_branding', $displayBranding);


            $enabledFeatures = $_POST['publishpress_options']['features'];

            // Run through all the modules updating their statuses
            foreach ($publishpress->modules as $mod_data) {
                if ($mod_data->autoload
                    || $mod_data->slug === $this->module->slug) {
                    continue;
                }

                $status = array_key_exists($mod_data->slug, $enabledFeatures) ? 'on' : 'off';
                $publishpress->update_module_option($mod_data->name, 'enabled', $status);
            }

            return true;
        }

        /**
         * Settings page for editorial comments
         *
         * @since 0.7
         */
        public function print_configure_view()
        {
            global $publishpress; ?>
            <form class="basic-settings"
                  action="<?php echo esc_url(menu_page_url($this->module->settings_slug, false)); ?>" method="post">
                <?php settings_fields($this->module->options_group_name); ?>
                <?php do_settings_sections($this->module->options_group_name); ?>

                <?php
                foreach ($publishpress->class_names as $slug => $class_name) {
                    $mod_data = $publishpress->$slug->module;

                    if ($mod_data->autoload
                        || $mod_data->slug === $this->module->slug
                        || ! isset($mod_data->general_options)
                        || $mod_data->options->enabled != 'on') {
                        continue;
                    }

                    echo sprintf('<h3>%s</h3>', $mod_data->title);
                    echo sprintf('<p>%s</p>', $mod_data->short_description);

                    echo '<input name="publishpress_module_name[]" type="hidden" value="' . esc_attr($mod_data->name) . '" />';

                    $publishpress->$slug->print_configure_view();
                } ?>

                <div id="modules-wrapper">
                    <h3><?php echo __('Features', 'publishpress'); ?></h3>
                    <p><?php echo __('Feel free to select only the features you need.', 'publishpress'); ?></p>

                    <table class="form-table">
                        <tbody>
                        <tr>
                            <th scope="row"><?php echo __('Enabled features', 'publishpress'); ?></th>
                            <td>
                                <?php foreach ($publishpress->modules as $mod_name => $mod_data) : ?>

                                    <?php if ($mod_data->autoload || $mod_data->slug === $this->module->slug) {
                                        continue;
                                    } ?>

                                    <label for="feature-<?php echo $mod_data->slug; ?>">
                                        <input id="feature-<?php echo $mod_data->slug; ?>"
                                               name="publishpress_options[features][<?php echo $mod_data->slug; ?>]" <?php echo ($mod_data->options->enabled == 'on') ? "checked=\"checked\"" : ""; ?>
                                               type="checkbox">
                                        &nbsp;&nbsp;&nbsp;<?php echo $mod_data->title; ?>
                                    </label>
                                    <br>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <?php echo '<input name="publishpress_module_name[]" type="hidden" value="' . esc_attr($this->module->name) . '" />'; ?>
                </div>
                <?php
                wp_nonce_field('edit-publishpress-settings');

                submit_button(null, 'primary', 'submit', false); ?>
            </form>
            <?php
        }
    }
}
