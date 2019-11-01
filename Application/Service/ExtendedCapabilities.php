<?php

/**
 * ======================================================================
 * LICENSE: This file is subject to the terms and conditions defined in *
 * file 'license.txt', which is part of this source code package.       *
 * ======================================================================
 *
 * @version 6.0.0
 */

/**
 * Additional capabilities service
 *
 * Add custom capabilities support that enhance AAM functionality
 *
 * @package AAM
 * @version 6.0.0
 */
class AAM_Service_ExtendedCapabilities
{
    use AAM_Core_Contract_ServiceTrait,
        AAM_Core_Contract_RequestTrait;

    /**
     * AAM configuration setting that is associated with the service
     *
     * @version 6.0.0
     */
    const FEATURE_FLAG = 'core.service.additional-caps.enabled';

    /**
     * Constructor
     *
     * @access protected
     *
     * @return void
     * @version 6.0.0
     */
    protected function __construct()
    {
        if (is_admin()) {
            // Hook that returns the detailed information about the nature of the
            // service. This is used to display information about service on the
            // Settings->Services tab
            add_filter('aam_service_list_filter', function ($services) {
                $services[] = array(
                    'title'       => __('Additional Caps', AAM_KEY),
                    'description' => __('Extend the WordPress core collection of capabilities that allow more granular access control to the backend core features.', AAM_KEY),
                    'setting'     => self::FEATURE_FLAG
                );

                return $services;
            }, 1);
        }

        // Hook that initialize the AAM UI part of the service
        if (AAM_Core_Config::get(self::FEATURE_FLAG, true)) {
            $this->initializeHooks();
        }
    }

    /**
     * Initialize service hooks
     *
     * @return void
     *
     * @access public
     * @version 6.0.0
     */
    protected function initializeHooks()
    {
        if (is_admin()) {
            add_action('init', function() {
                if (is_user_logged_in()) {
                    // Check if user is allowed to see backend
                    if (AAM_Core_API::capExists('access_dashboard')) {
                        // If this is the AJAX call, still allow it because it will break a lot
                        // of frontend stuff that depends on it
                        if (!current_user_can('access_dashboard') && !defined('DOING_AJAX')) {
                            wp_die(__('Access Denied', AAM_KEY), 'aam_access_denied');
                        }
                    }

                    // Check if we need to show admin bar for the current user
                    if (
                        AAM_Core_API::capExists('show_admin_bar')
                        && !current_user_can('show_admin_bar')
                    ) {
                        add_filter('show_admin_bar', '__return_false', PHP_INT_MAX);
                    }
                }
            }, 1);

            // Control admin area
            add_action('admin_notices', array($this, 'controlAdminNotifications'), -1);
            add_action('network_admin_notices', array($this, 'controlAdminNotifications'), -1);
            add_action('user_admin_notices', array($this, 'controlAdminNotifications'), -1);

            // Screen options & contextual help hooks
            add_filter('screen_options_show_screen', array($this, 'screenOptions'));
            add_filter('contextual_help', array($this, 'helpOptions'), 10, 3);

            // Permalink manager
            add_filter('get_sample_permalink_html', function ($html) {
                if (
                    AAM_Core_API::capExists('edit_permalink')
                    && !current_user_can('edit_permalink')
                ) {
                    $html = '';
                }

                return $html;
            });
        }

        // Password reset feature
        add_filter('show_password_fields', array($this, 'canChangePassword'), 10, 2);
        add_action('check_passwords', array($this, 'canUpdatePassword'), 10, 3);
    }

    /**
     * Manage notifications visibility
     *
     * @return void
     *
     * @access public
     * @version 6.0.0
     */
    public function controlAdminNotifications()
    {
        if (
            AAM_Core_API::capExists('show_admin_notices')
            && !current_user_can('show_admin_notices')
        ) {
            remove_all_actions('admin_notices');
            remove_all_actions('network_admin_notices');
            remove_all_actions('user_admin_notices');
        }
    }

    /**
     * Control if user has access to the Screen Options
     *
     * @param boolean $flag
     *
     * @return boolean
     *
     * @access public
     * @version 6.0.0
     */
    public function screenOptions($flag)
    {
        if (AAM_Core_API::capExists('show_screen_options')) {
            $flag = current_user_can('show_screen_options');
        }

        return $flag;
    }

    /**
     * Check if user has access to the Help options
     *
     * @param array  $help
     * @param mixed  $id
     * @param object $screen
     *
     * @return array
     *
     * @access public
     * @version 6.0.0
     */
    public function helpOptions($help, $id, $screen)
    {
        if (
            AAM_Core_API::capExists('show_help_tabs')
            && !current_user_can('show_help_tabs')
        ) {
            $screen->remove_help_tabs();
            $help = array();
        }

        return $help;
    }

    /**
     * Check if user can change his/her own password
     *
     * This method determines if password change fields are going to be dispalyed
     *
     * @param boolean $result
     * @param WP_User $user
     *
     * @return boolean
     *
     * @access public
     * @version 6.0.0
     */
    public function canChangePassword($result, $user)
    {
        $isProfile = $user->ID === get_current_user_id();

        if ($isProfile) {
            if (
                AAM_Core_API::capExists('change_own_password')
                && !current_user_can('change_own_password')
            ) {
                $result = false;
            }
        } elseif (
            AAM_Core_API::capExists('change_passwords')
            && !current_user_can('change_passwords')
        ) {
            $result = false;
        }

        return $result;
    }

    /**
     * Check if user can update others password
     *
     * @param mixed  $login
     * @param string $password
     * @param string $password2
     *
     * @return void
     *
     * @access public
     * @version 6.0.0
     */
    public function canUpdatePassword($login, &$password, &$password2)
    {
        $userId    = $this->getFromPost('user_id', FILTER_VALIDATE_INT);
        $isProfile = $userId === get_current_user_id();

        if ($isProfile) {
            if (
                AAM_Core_API::capExists('change_own_password')
                && !current_user_can('change_own_password')
            ) {
                $password = $password2 = null;
            }
        } elseif (
            AAM_Core_API::capExists('change_passwords')
            && !current_user_can('change_passwords')
        ) {
            $password = $password2 = null;
        }
    }

}

if (defined('AAM_KEY')) {
    AAM_Service_ExtendedCapabilities::bootstrap();
}