<?php
/**
 * @wordpress-plugin
 * Plugin Name:     RunCloud Hub
 * Description:     RunCloud server-side caching for WordPress, Nginx FastCGI/Proxy Cache and Redis Object Cache
 * Author:          RunCloud
 * Author URI:      https://runcloud.io/
 * Version:         1.1.0
 * License:         GPL-2.0 or later
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:     runcloud-hub
 * Domain Path:     /languages
 */

/*
Copyright 2020 RunCloud.io
All rights reserved.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version
2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
with this program. If not, visit: https://www.gnu.org/licenses/
 */

/* 
More informations:

RunCloud Hub
https://blog.runcloud.io/runcloud-hub/

RunCache - Nginx FastCGI/Proxy Cache
https://blog.runcloud.io/nginx-fastcgi-cache/

Redis Object Cache
https://blog.runcloud.io/redis-object-cache/


*/

if (!defined('WPINC') || defined('RUNCLOUD_HUB_HOOK')) {
    exit;
}

/**
 * constant
 */
define('RUNCLOUD_HUB_FILE', __FILE__);
define('RUNCLOUD_HUB_HOOK', plugin_basename(RUNCLOUD_HUB_FILE));
define('RUNCLOUD_HUB_PATH', realpath(plugin_dir_path(RUNCLOUD_HUB_FILE)) . '/');
define('RUNCLOUD_HUB_PATH_LANG', RUNCLOUD_HUB_PATH . 'languages/');
define('RUNCLOUD_HUB_PATH_VIEW', RUNCLOUD_HUB_PATH . 'partials/');
define('RUNCLOUD_HUB_PATH_RSC', RUNCLOUD_HUB_PATH . 'assets/');
define('RUNCLOUD_HUB_PATH_LIB', RUNCLOUD_HUB_PATH . 'library/');

final class RunCloud_Hub
{
    // reference
    private static $name       = 'RunCloud Hub';
    private static $slug       = 'runcloud-hub';
    private static $islug      = 'runcloud';

    private static $db_version = 'runcloud_hub_version';
    private static $db_setting = 'runcloud_hub_config';
    private static $db_stats   = 'runcloud_hub_stats';
    private static $db_type    = 'runcloud_hub_type';

    // version
    private static $version      = '1.1.0';
    private static $version_prev = '1.0.4';

    // later
    private static $hook       = '';
    private static $cache_path = '';
    private static $ordfirst;
    private static $ordlast;

    // url
    private static $plugin_url        = '';
    private static $plugin_url_assets = '';

    // view
    private static $checked = [];
    private static $value   = [];

    // is cond
    private static $is_purge_cache_home    = false;
    private static $is_purge_cache_content = false;
    private static $is_purge_cache_archive = false;
    private static $is_purge_cache_urlpath = false;
    private static $is_run_preload         = false;
    private static $is_redis_debug         = false;
    private static $is_html_footprint      = false;

    // purge prefix
    private static $purge_prefix_var = 'runcache-purge';
    private static $purge_prefix_all = 'runcache-purgeall';

    // misc
    private static $dropin_file   = 'object-cache.php';
    private static $dropin_source = 'wp-object-cache.php';
    private static $dropin_config = 'redis-config.php';
    private static $transientk    = 'runcloudhub';
    private static $magictoken    = '__rcmagiclink';
    private static $optpage       = false;
    private static $onfirehooks   = false;

    // api
    private static $endpoint = 'https://manage.runcloud.io/api/runcloud-hub';

    // update
    private static $trunk = 'https://manage.runcloud.io/resources/runcloud-hub/latest_version';

    /**
     * is_wp_ssl.
     */
    private static function is_wp_ssl()
    {
        $scheme = parse_url(get_site_url(), PHP_URL_SCHEME);
        return ('https' === $scheme ? true : false);
    }

    /**
     * is_wp_cli.
     */
    private static function is_wp_cli()
    {
        return (defined('WP_CLI') && WP_CLI);
    }

    /**
     * is_defined_halt.
     */
    private static function is_defined_halt()
    {
        return defined('RUNCLOUD_HUB_HALT');
    }

    /**
     * define_halt.
     */
    private static function define_halt()
    {
        if (!self::is_defined_halt() && !self::is_wp_cli()) {
            define('RUNCLOUD_HUB_HALT', true);
        }
    }

    /**
     * is_apache.
     */
    private static function is_apache()
    {
        if (false !== strpos($_SERVER['SERVER_SOFTWARE'], 'Apache')) {
            return true;
        }

        if ('apache2handler' === php_sapi_name()) {
            return true;
        }

        return false;
    }

    /**
     * is_nginx.
     */
    private static function is_nginx()
    {
        if (isset($GLOBALS['is_nginx']) && (bool) $GLOBALS['is_nginx']) {
            return true;
        }

        if (false !== strpos($_SERVER['SERVER_SOFTWARE'], 'nginx')) {
            return true;
        }

        return false;
    }

    /**
     * is_srcache.
     */
    private static function is_srcache()
    {
        if (is_multisite()) {
            $blog_id = self::get_main_site_id();
            switch_to_blog($blog_id);
        }

        $type = get_option(self::$db_type);

        if (is_multisite()) {
            restore_current_blog();
        }

        return ( $type === 'sr' || $type === 'srcache' ? true : false );
    }

    /**
     * switch_cache_type.
     */
    private static function switch_cache_type( $status = false )
    {
        if ( self::is_srcache() ) {
            $updated = self::update_cache_type('fastcgi');
            $debug_message = sprintf( esc_html__('Switching cache purger type from %s to %s', 'runcloud-hub'), 'sr', 'fastcgi' );
        }
        else {
            $updated = self::update_cache_type('sr');
            $debug_message = sprintf( esc_html__('Switching cache purger type from %s to %s', 'runcloud-hub'), 'fastcgi', 'sr' );
        }
        self::debug(__METHOD__, $debug_message);

        if ( $updated ) {
            $req_status = [
                'code' => 200,
                'status' => esc_html__('Switching cache purger type was successful.', 'runcloud-hub'),
            ];
        }
        else {
            $req_status = [
                'code' => 0,
                'status' => esc_html__('Switching cache purger type was failed.', 'runcloud-hub'),
            ];
        }
        return $req_status;
    }

    /**
     * update_cache_type.
     */
    public static function update_cache_type($type, &$message = '') {
        $cache_type = get_option(self::$db_type);
        if ( $cache_type === $type ) {
            $message = sprintf( esc_html__('Updating the cache type is not needed. Cache type: %s', 'runcloud-hub'), $type );
            self::debug(__METHOD__, $message);
            return true;
        }

        $updated = update_option(self::$db_type, $type);
        if ($updated) {
            $message = sprintf( esc_html__('Updating the cache type was successful. Cache type: %s', 'runcloud-hub'), $type );
        }
        else {
            $message = esc_html__('Updating the cache type was failed.', 'runcloud-hub');
        }
        self::debug(__METHOD__, $message);

        return $updated;
    }

    /**
     * is_ajax.
     */
    private static function is_ajax() 
    {
        if ( wp_doing_ajax() ) {
            return true;
        }

        if ( ! empty( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) && 'xmlhttprequest' === strtolower( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ]) ) {
            return true;
        }

        return false;
    }

    /**
     * is_fl_builder.
     */
    private static function is_fl_builder() 
    {
        if ( isset($_POST) && isset($_POST['fl_builder']) ) {
            return true;
        }

        if ( isset($_GET) && isset($_GET['fl_builder']) ) {
            return true;
        }

        return false;
    }

    /**
     * fastcgi_close.
     */
    private static function fastcgi_close()
    {
        if ((php_sapi_name() === 'fpm-fcgi')
            && function_exists('fastcgi_finish_request')) {
            @session_write_close();
            @fastcgi_finish_request();
        }
    }

    /**
     * close_exit.
     */
    private static function close_exit($content = '')
    {
        if (!empty($content)) {
            if (is_array($content)) {
                header('Content-Type: application/json');
                echo json_encode($content, JSON_UNESCAPED_SLASHES);
            }
            else {
                echo esc_html($content);
            }
        }
        self::fastcgi_close();
        exit;
    }

    /**
     * register_locale.
     */
    private static function register_locale()
    {
        add_action(
            'plugins_loaded',
            function () {
                load_plugin_textdomain(
                    'runcloud-hub',
                    false,
                    RUNCLOUD_HUB_PATH_LANG
                );
            },
            0
        );
    }

    public static function wp_cache_delete() {
        wp_cache_delete('uninstall_plugins', 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
        wp_cache_delete(self::get_main_site_id().'-notoptions', 'site-options');
        wp_cache_delete(self::get_main_site_id().'-active_sitewide_plugins', 'site-options');
    }

    /**
     * register_init.
     */
    public static function register_init()
    {
        self::register_locale();
        self::register_wpcli_hooks();

        self::$hook              = RUNCLOUD_HUB_HOOK;
        self::$plugin_url        = plugin_dir_url(RUNCLOUD_HUB_FILE);
        self::$plugin_url_assets = self::$plugin_url . 'assets/';
        self::$cache_path        = (defined('RUNCLOUD_HUB_PATH_CACHE') ? trailingslashit(RUNCLOUD_HUB_PATH_CACHE) : WP_CONTENT_DIR . '/cache/' . self::$slug . '/');

        self::$ordfirst = -PHP_INT_MAX;
        self::$ordlast  = PHP_INT_MAX;

        $__varfunc_maybe_clear_alloptions_cache = function ($option) {
            if (!wp_installing()) {
                $alloptions = wp_load_alloptions();

                if (isset($alloptions[$option])) {
                    self::wp_cache_delete();
                }
            }
        };

        add_action('added_option', $__varfunc_maybe_clear_alloptions_cache);
        add_action('updated_option', $__varfunc_maybe_clear_alloptions_cache);
        add_action('deleted_option', $__varfunc_maybe_clear_alloptions_cache);

        self::reset_purge_action();

        if (defined('RUNCLOUD_HUB_OPTPAGE') && (bool) RUNCLOUD_HUB_OPTPAGE) {
            self::$optpage = true;
        }

        define('RUNCLOUD_HUB_INIT', true);
    }

    /**
     * register_cron_schedules.
     */
    private static function register_cron_schedules()
    {
        add_action(
            'cron_schedules',
            function ($schedules) {
                $name             = self::$slug . '-minute';
                $schedules[$name] = [
                    'interval' => MINUTE_IN_SECONDS,
                    'display'  => $name,
                ];

                $name             = self::$slug . '-hour';
                $schedules[$name] = [
                    'interval' => HOUR_IN_SECONDS,
                    'display'  => $name,
                ];

                $name             = self::$slug . '-day';
                $schedules[$name] = [
                    'interval' => DAY_IN_SECONDS,
                    'display'  => $name,
                ];

                $name             = self::$slug . '-week';
                $schedules[$name] = [
                    'interval' => WEEK_IN_SECONDS,
                    'display'  => $name,
                ];

                $name             = self::$slug . '-month';
                $schedules[$name] = [
                    'interval' => MONTH_IN_SECONDS,
                    'display'  => $name,
                ];

                $name             = self::$slug . '-year';
                $schedules[$name] = [
                    'interval' => YEAR_IN_SECONDS,
                    'display'  => $name,
                ];
                return $schedules;
            },
            self::$ordlast
        );
    }

    /**
     * default_settings.
     */
    private static function default_settings()
    {
        $options = [
            'homepage_post_onn'                   => 1,
            'homepage_removed_onn'                => 1,
            'content_publish_onn'                 => 1,
            'content_comment_approved_onn'        => 1,
            'content_comment_removed_onn'         => 1,
            'archives_homepage_onn'               => 1,
            'archives_content_onn'                => 1,
            'redis_cache_onn'                     => 0,
            'redis_prefix'                        => '',
            'redis_maxttl_onn'                    => 1,
            'redis_maxttl_int'                    => 1,
            'redis_maxttl_unt'                    => 86400,
            'redis_maxttl_var'                    => 86400,
            'redis_ignored_groups_onn'            => 1,
            'redis_ignored_groups_mch'            => [
                'counts',
                'plugins',
                'themes',
                'comment',
                'wc_session_id',
                'bp_notifications',
                'bp_messages',
                'bp_pages',
            ],
            'redis_debug_onn'                     => 0,
            'rcapi_key'                           => '',
            'rcapi_secret'                        => '',
            'rcapi_webapp_id'                     => '',
            'rcapi_magiclink_onn'                 => 1,
            'stats_onn'                           => 1,
            'stats_schedule_onn'                  => 1,
            'stats_schedule_int'                  => 1,
            'stats_schedule_unt'                  => 86400,
            'stats_schedule_var'                  => 86400,
            'stats_transfer_onn'                  => 1,
            'stats_transfer_var'                  => 'daily',
            'stats_health_onn'                    => 1,
            'stats_health_var'                    => 'hourly',
            'exclude_url_onn'                     => 1,
            'exclude_url_mch'                     => [
                '/.well-known.*',
                '/store.*',
                '/cart.*',
                '/my-account.*',
                '/checkout.*',
                '/addons.*',
                '/wp-admin/',
                '/xmlrpc.php',
                'wp-.*.php',
                '/feed/',
                'index.php',
                'wp-comments-popup.php',
                'wp-links-opml.php',
                'wp-locations.php',
                'sitemap(_index)?.xml',
                '[a-z0-9_-]+-sitemap([0-9]+)?.xml',
            ],
            'exclude_cookie_onn'                  => 1,
            'exclude_cookie_mch'                  => [
                'comment_author',
                'wordpress_[a-f0-9]+',
                'wp-postpass',
                'wordpress_no_cache',
                'wordpress_logged_in',
                'woocommerce_cart_hash',
                'woocommerce_items_in_cart',
                'wp_woocommerce_session',
                'woocommerce_recently_viewed'
            ],
            'exclude_browser_onn'                 => 0,
            'exclude_browser_mch'                 => [],
            'exclude_visitorip_onn'               => 0,
            'exclude_visitorip_mch'               => [],
            'allow_query_onn'                     => 0,
            'allow_query_mch'                     => [
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_expid',
                'fb_action_ids',
                'fb_action_types',
                'fb_source',
                'fbclid',
                'gclid',
                'age-verified',
                'ao_noptimize',
                'usqp',
                'cn-reloaded',
                '_ga',
            ],
            'exclude_query_onn'                   => 0,
            'exclude_query_mch'                   => [],
            'schedule_purge_onn'                  => 0,
            'schedule_purge_int'                  => 12,
            'schedule_purge_unt'                  => 3600,
            'schedule_purge_var'                  => 43200,
            'url_path_onn'                        => 0,
            'url_path_mch'                        => [],
            'html_footprint_onn'                  => 0,
            'cache_key_extra_onn'                 => 0,
            'cache_key_extra_var'                 => '',
            'preload_speed'                       => 12,
            'preload_onn'                         => 0,
            'preload_schedule_onn'                => 0,
            'preload_schedule_int'                => 1,
            'preload_schedule_unt'                => 86400,
            'preload_schedule_var'                => 86400,
            'preload_path_onn'                    => 0,
            'preload_path_mch'                    => [],
        ];

        return $options;
    }

    /**
     * can_manage_network_options.
     */
    private static function can_manage_network_options()
    {
        return (is_multisite() && current_user_can(apply_filters('capability', 'manage_network_options')));
    }

    /**
     * can_manage_network_plugins.
     */
    private static function can_manage_network_plugins()
    {
        return (is_multisite() && current_user_can(apply_filters('capability_network', 'manage_network_plugins')));
    }

    /**
     * can_manage_options.
     */
    private static function can_manage_options()
    {
        return (current_user_can(apply_filters('capability', 'manage_options')));
    }

    /**
     * is_client_mode.
     */
    public static function is_client_mode()
    {
        return ( defined('RUNCLOUD_HUB_CLIENT_MODE') && RUNCLOUD_HUB_CLIENT_MODE ? true : false );
    }

    /**
     * get_main_site_id.
     */
    public static function get_main_site_id()
    {
        if (is_multisite()) {
            return get_network()->site_id;
        }

        return get_current_blog_id();
    }

    /**
     * get_main_site_url.
     */
    public static function get_main_site_url()
    {
        return get_site_url(self::get_main_site_id());
    }

    /**
     * is_main_site.
     */
    public static function is_main_site()
    {
        return (get_current_blog_id() === self::get_main_site_id());
    }

    /**
     * is_subdirectory.
     */
    public static function is_subdirectory()
    {
        if ( is_multisite() ) {
            return false;
        }
        $url = untrailingslashit(home_url());
        $part = wp_parse_url($url);
        return (!empty($part['path']) ? true : false);
    }

    /**
     * remove_protocol.
     */
    private static function remove_protocol($host)
    {
        return preg_replace('@^(https?://|//)@', '', trim($host));
    }

    /**
     * remove_www.
     */
    private static function remove_www($host)
    {
        return preg_replace('/^www\./', '', $host);
    }

    /**
     * is_num.
     */
    public static function is_num($str)
    {
        return preg_match('@^\d+$@', $str);
    }

    /**
     * install_options.
     */
    private static function install_options($all_site = false)
    {
        $__varfunc_install = function ($blog_id) {
            $options                 = self::default_settings();
            $options['redis_prefix'] = self::redis_key_prefix($options['redis_prefix'], $blog_id);
            add_option(self::$db_setting, $options);
            self::register_cron_hooks();
        };

        if (is_multisite() && self::can_manage_network_options() && $all_site) {
            foreach (get_sites(array('number' => 500)) as $site) {
                switch_to_blog($site->blog_id);

                $__varfunc_install($site->blog_id);

                restore_current_blog();
            }
        } else {
            $__varfunc_install(get_current_blog_id());
        }
    }

    /**
     * uninstall_options.
     */
    private static function uninstall_options($all_site = false)
    {

        self::wp_cache_delete();
        if ( !self::$onfirehooks ) {
            if (is_multisite()) {
                self::late_purge_cache_redis_all();
            }
            else {
                self::late_purge_cache_redis();
            }
        }

        $__varfunc_uninstall = function () {
            delete_option(self::$db_setting);
            self::register_cron_hooks(false);
        };

        if (is_multisite() && self::can_manage_network_options() && $all_site) {
            foreach (get_sites(array('number' => 500)) as $site) {
                switch_to_blog($site->blog_id);

                $__varfunc_uninstall();

                restore_current_blog();
            }
        } else {
            $__varfunc_uninstall();
        }
    }

    /**
     * stats_is_enabled.
     */
    private static function stats_is_enabled() {
        $options = self::get_setting();

        if (empty($options['rcapi_key']) || empty($options['rcapi_secret']) || empty($options['rcapi_webapp_id'])) {
            return false;
        }

        if (empty($options['stats_onn'])) {
            return false;
        }

        if (empty($options['stats_transfer_onn']) && empty($options['stats_health_onn'])) {
            return false;
        }

        return true;
    }

    /**
     * redis_is_connect.
     */
    public static function redis_is_connect()
    {
        global $wp_object_cache;
        if ( self::is_dropin_valid() && method_exists( $wp_object_cache, 'redis_status' ) ) {
            return $wp_object_cache->redis_status();
        }
        return;
    }

    /**
     * redis_is_enabled.
     */
    public static function redis_is_enabled()
    {
        if (!is_multisite()) {
            return self::get_setting('redis_cache_onn');
        }
        else {
            if(defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL) {
                return self::get_setting('redis_cache_onn');
            }
            else {
                $blog_id = self::get_main_site_id();
                return self::get_setting('redis_cache_onn', $blog_id);
            }
        }

        return false;
    }

    /**
     * redis_can_enable.
     */
    public static function redis_can_enable()
    {
        if (!is_multisite()) {
            return true;
        }
        else {
            if (self::is_main_site()) {
                return true;
            }
            else {
                if(defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * add_redis_stats.
     */
    public static function add_redis_stats() {
        if (!self::can_manage_options()) {
            return;
        }

        if (!self::redis_is_enabled()) {
            return;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }
        if (defined('WP_CLI') && WP_CLI) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        $output = '';
        if (self::redis_is_connect()) {
            global $wp_object_cache;
            if (method_exists($wp_object_cache, 'info')) {
                $info = $wp_object_cache->info();
                $info_hits = isset($info->hits) ? $info->hits : '';
                $info_misses = isset($info->misses) ? $info->misses : '';
                $info_ratio = isset($info->ratio) ? $info->ratio : '';
                $info_bytes = isset($info->bytes) ? size_format( $info->bytes, 2 ) : '';
                $output = sprintf(esc_html__('Redis Object Cache: Hits %s, Misses %s, Hit Ratio %s%s, Size %s', 'runcloud-hub'), $info_hits, $info_misses, $info_ratio, '%', $info_bytes);
            }
            else {
                $output = esc_html__('Redis Object Cache: Could not get statistics info', 'runcloud-hub');
            }
        }
        else {
            $output = esc_html__('Redis Object Cache: Not connected', 'runcloud-hub');
        }

        if (!empty($output)) {
            echo '<style>.rc-redis-debug{background:#23282d;color:#ccc;text-align:center;padding:10px;font-size:14px;line-height:20px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;}.wp-admin .rc-redis-debug{margin-left:160px;}.wp-admin.folded .rc-redis-debug{margin-left:36px;}</style>';
            echo '<div class="rc-redis-debug">';
            echo esc_html($output);
            echo '</div>';
        }
    }

    /**
     * redis_key_prefix.
     */
    public static function redis_key_prefix($salt = '', $blog_id = '')
    {
        if (empty($blog_id)) {
            $blog_id = get_current_blog_id();
        }

        if (empty($salt)) {
            $salt = self::nw_hash(get_site_url() . md5(time() . $blog_id), 14);
        }

        $key = self::nw_hash($salt . $blog_id, 14);
        return $key;
    }

    /**
     * get_dropin_source_file.
     */
    private static function get_dropin_source_file()
    {
        return RUNCLOUD_HUB_PATH_LIB . self::$dropin_source;
    }

    /**
     * get_dropin_file.
     */
    private static function get_dropin_file()
    {
        return WP_CONTENT_DIR . '/' . self::$dropin_file;
    }

    /**
     * get_dropin_config_file.
     */
    private static function get_dropin_config_file()
    {
        return self::$cache_path.self::$dropin_config;
    }

    /**
     * is_dropin_exists.
     */
    private static function is_dropin_exists()
    {
        $file = self::get_dropin_file();
        if (is_file($file) && is_readable($file)) {
            clearstatcache(true, $file);
            return true;
        }
        return false;
    }

    /**
     * is_dropin_config_exist.
     */
    private static function is_dropin_config_exist()
    {
        $file = self::get_dropin_config_file();
        if (is_file($file) && is_readable($file)) {
            clearstatcache(true, $file);
            return true;
        }
        return false;
    }

    /**
     * is_dropin_hub.
     */
    private static function is_dropin_hub()
    {
        if ( !self::is_dropin_exists() ) {
            return false;
        }

        $dropin = get_plugin_data( self::get_dropin_file() );

        if (strpos($dropin['Description'], 'RunCloud Hub') === false) {
            return false;
        }

        return true;
    }

    /**
     * is_dropin_valid.
     */
    private static function is_dropin_valid()
    {
        if ( !self::is_dropin_exists() ) {
            return false;
        }

        $dropin = get_plugin_data( self::get_dropin_file() );
        $source = get_plugin_data( self::get_dropin_source_file() );

        if (strpos($dropin['Description'], 'RunCloud Hub') === false) {
            return false;
        }

        if (strcmp( $dropin['Version'], $source['Version'] ) !== 0) {
            return false;
        }

        return true;
    }

    /**
     * is_dropin_active.
     */
    private static function is_dropin_active()
    {
        if ( defined('RCWP_REDIS_DISABLED') && RCWP_REDIS_DISABLED ) {
            return false;
        }

        if ( !self::is_dropin_exists() ) {
            return false;
        }

        $dropin = get_plugin_data( self::get_dropin_file() );

        if (strpos($dropin[ 'Description' ], 'RunCloud Hub') === false) {
            return false;
        }

        if ( defined('RCWP_REDIS_DROPIN') && RCWP_REDIS_DROPIN ) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * is_dropin_need_install.
     */
    private static function is_dropin_need_install()
    {
        if ( defined('RUNCLOUD_HUB_INSTALL_DROPIN') && !RUNCLOUD_HUB_INSTALL_DROPIN ) {
            return false;
        }

        if ( self::is_dropin_exists() ) {
            return false;
        }

        if (!is_multisite()) {
            return (self::get_setting('redis_cache_onn') ? true : false);
        }
        else {
            if(!(defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL)) {
                $blog_id = self::get_main_site_id();
                return (self::get_setting('redis_cache_onn', $blog_id) ? true : false);
            }
        }

        return true;
    }

    /**
     * is_dropin_need_remove.
     */
    private static function is_dropin_need_remove()
    {
        $is_exist = self::is_dropin_exists() && self::is_dropin_hub() ? true : false;

        $is_needed = true;
        if (!is_multisite()) {
            $is_needed = (self::get_setting('redis_cache_onn') ? true : false);
        }
        else {
            if(!(defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL)) {
                $blog_id = self::get_main_site_id();
                $is_needed = (self::get_setting('redis_cache_onn', $blog_id) ? true : false);
            }
        }

        if ($is_exist && !$is_needed) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * is_dropin_need_update.
     */
    private static function is_dropin_need_update()
    {
        if ( defined('RCWP_REDIS_DISABLED') && RCWP_REDIS_DISABLED ) {
            return false;
        }

        if ( !self::is_dropin_exists() ) {
            return false;
        }

        // drop-in is enabled by other plugin
        if ( self::is_dropin_exists() && !self::is_dropin_hub() ) {
            return false;
        }

        if ( self::is_dropin_hub() && self::is_dropin_valid() ) {
            return false;
        }

        return true;
    }

    /**
     * is_dropin_need_replace.
     */
    private static function is_dropin_need_replace()
    {
        if ( self::is_dropin_exists() && !self::is_dropin_hub() ) {
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * is_dropin_need_install.
     */
    private static function is_dropin_config_need_install()
    {
        if ( defined('RUNCLOUD_HUB_INSTALL_DROPIN') && !RUNCLOUD_HUB_INSTALL_DROPIN ) {
            return false;
        }

        if ( self::is_dropin_config_exist() ) {
            return false;
        }

        return true;
    }

    /**
     * is_dropin_config_need_update.
     */
    /**
     * is_dropin_config_need_update.
     */
    private static function is_dropin_config_need_update()
    {
        if ( ! self::is_dropin_active() ) {
            return false;
        }

        $file_dropin_config = self::get_dropin_config_file();
        if ( !file_exists( $file_dropin_config ) ) {
            return true;
        }

        $config = file_exists($file_dropin_config) ? @include($file_dropin_config) : array();

        $main_blog_id = is_multisite() ? self::get_main_site_id() : '';
        $options = self::get_setting('', $main_blog_id);

        if (!isset($config['prefix'], $config['maxttl'])) {
            return true;
        }

        if ($config['prefix'] !== $options['redis_prefix']) {
            return true;
        }
        if ($config['maxttl'] !== (int) $options['redis_maxttl_var']) {
            return true;
        }

        $main_status_ok = false;
        $blog_status_ok = false;

        $main_status = $options['redis_cache_onn'];
        if ($main_status === 1 && isset($config['status']) && $config['status'] === 1) {
            $main_status_ok = true;
        }
        elseif ($main_status !== 1 && isset($config['status']) && $config['status'] !== 1) {
            $main_status_ok = true;
        }

        if (is_multisite() && defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL) {
            $blog_id = get_current_blog_id();
            $blog_status = self::get_setting('redis_cache_onn', $blog_id);
            $url = home_url();
            $part = wp_parse_url($url);
            $host = $part['host'];
            if ($blog_status === 1 && isset($config['status_sites'][$host]) && $config['status_sites'][$host] === 1) {
                $blog_status_ok = true;
            }
            elseif ($blog_status !== 1 && isset($config['status_sites'][$host]) && $config['status_sites'][$host] !== 1) {
                $blog_status_ok = true;
            }
        }
        else {
            $blog_status_ok = true;
        }

        if ($main_status_ok && $blog_status_ok) {
            return false;
        }
        else {
            return true;
        }
    }

    /**
     * try_fix_dropin.
     */
    public static function try_fix_dropin()
    {
        if ( self::is_ourscreen() ) {
            $req_status = null;
            if (self::is_dropin_need_install() || self::is_dropin_need_update()) {
                $req_status = self::install_dropin(true, true);
            }
            elseif (self::is_dropin_need_remove()) {
                $req_status = self::uninstall_dropin(true, true);
            }
            if (self::is_dropin_config_need_install() || self::is_dropin_config_need_update()) {
                $req_status = self::install_dropin_config(true, true);
            }
            if (!empty($req_status)) {
                set_transient(self::$transientk . '/callback_purgecache', $req_status, 120);
            }
        }
    }

    /**
     * try_setup_dropin.
     */
    public static function try_setup_dropin()
    {
        self::debug(__METHOD__, 'event run');
        if (self::is_dropin_need_install() || self::is_dropin_need_update()) {
            self::install_dropin(true);
        }
        elseif (self::is_dropin_need_remove()) {
            self::uninstall_dropin(false);
        }
        if (self::is_dropin_config_need_install() || self::is_dropin_config_need_update()) {
            self::install_dropin_config(true);
        }
    }

    /**
     * install_dropin.
     */
    public static function install_dropin($force = false, $status = false)
    {
        if ( defined('RUNCLOUD_HUB_INSTALL_DROPIN') && !RUNCLOUD_HUB_INSTALL_DROPIN ) {
            if ($status) {
                return array(
                    'code' => 0,
                    'status' => esc_html__('Install Drop-in Stopped - RUNCLOUD_HUB_INSTALL_DROPIN', 'runcloud-hub'),
                );
            }
            return false;
        }

        $file_dropin_source = self::get_dropin_source_file();
        $file_dropin = self::get_dropin_file();
        $perm = self::get_fileperms('file');

        if (!file_exists($file_dropin_source)) {
            if ($status) {
                return array(
                    'code' => 0,
                    'status' => esc_html__('Install Drop-in Stopped - Drop-in source file does not exist', 'runcloud-hub'),
                );
            }
            return false;
        }

        $need_install = self::is_dropin_need_install();
        $need_update = self::is_dropin_need_update();

        if ($force || $need_install || $need_update) {
            // self::wp_cache_delete();

            if ($force || $need_update) {
                if (file_exists($file_dropin)) {
                    $remove = @unlink($file_dropin);
                    if (!$remove) {
                        if ($status) {
                            return array(
                                'code' => 0,
                                'status' => esc_html__('Install Drop-in Stopped - Drop-in file was not writable', 'runcloud-hub'),
                            );
                        }
                        return false;
                    }
                }
            }

            if ( !file_exists($file_dropin) ) {
                $buff = file_get_contents($file_dropin_source);
                if ( !empty($buff) && file_put_contents($file_dropin, $buff, LOCK_EX) ) {
                    @chmod($file_dropin, $perm);
                    if (!$force) {
                        if ($status) {
                            return array(
                                'code' => 200,
                                'status' => esc_html__('Installing/updating object cache drop-in was successful', 'runcloud-hub'),
                            );
                        }
                        return true;
                    }
                    else {
                        return self::install_dropin_config($force, $status);
                    }
                }
                else {
                    if ($status) {
                        return array(
                            'code' => 0,
                            'status' => esc_html__('Installing/updating object cache drop-in failed', 'runcloud-hub'),
                        );
                    }
                    return false;
                }
            }
            else {
                if ($status) {
                    return array(
                        'code' => 0,
                        'status' => esc_html__('Install Drop-in Stopped - Drop-in file could not be updated', 'runcloud-hub'),
                    );
                }
                return false;
            }
        }
    }

    /**
     * install_dropin_config.
     */
    public static function install_dropin_config($force = false, $status = false)
    {
        if ( defined('RUNCLOUD_HUB_INSTALL_DROPIN') && !RUNCLOUD_HUB_INSTALL_DROPIN ) {
            if ($status) {
                return array(
                    'code' => 0,
                    'status' => esc_html__('Update Drop-in Config Stopped - RUNCLOUD_HUB_INSTALL_DROPIN', 'runcloud-hub'),
                );
            }
            return false;
        }

        $file_dropin_config = self::get_dropin_config_file();
        $perm = self::get_fileperms('file');

        $need_install = self::is_dropin_config_need_install();
        $need_update = self::is_dropin_config_need_update();

        if ($force || $need_install || $need_update) {
            // self::wp_cache_delete();

            if ($force || $need_update) {
                if (file_exists($file_dropin_config)) {
                    $remove = @unlink($file_dropin_config);
                    if (!$remove) {
                        if ($status) {
                            return array(
                                'code' => 0,
                                'status' => esc_html__('Update Drop-in Config Stopped - Drop-in config file was not writable', 'runcloud-hub'),
                            );
                        }
                        return false;
                    }
                }
            }

            if ( !file_exists($file_dropin_config) ) {
                // create cache dir if not exist
                @self::nw_wakeup($file_dropin_config, 'clear');
                $buff = '<?php '.PHP_EOL.'return '.var_export(self::get_dropin_config(),1).';'.PHP_EOL;
                if ( !empty($buff) && file_put_contents($file_dropin_config, $buff, LOCK_EX) ) {
                    self::wp_cache_delete();
                    if ( !self::$onfirehooks ) {
                        wp_cache_flush();
                    }
                    @chmod($file_dropin_config, $perm);
                    if ($status) {
                        return array(
                            'code' => 200,
                            'status' => esc_html__('Updating object cache drop-in config was successful', 'runcloud-hub'),
                        );
                    }
                    return true;
                }
                else {
                    if ($status) {
                        return array(
                            'code' => 0,
                            'status' => esc_html__('Updating object cache drop-in config failed', 'runcloud-hub'),
                        );
                    }
                    return false;
                }
            }
            else {
                if ($status) {
                    return array(
                        'code' => 0,
                        'status' => esc_html__('Install Drop-in Config Stopped - Drop-in config file could not be updated', 'runcloud-hub'),
                    );
                }
                return false;
            }
        }
    }

    /**
     * get_dropin_config.
     */
    private static function get_dropin_config()
    {
        $config = array(
            'type' => '',
        );

        if (is_multisite()) {
            if(defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL) {
                $config['type'] = 'multisite_subdomain';
            }
            else {
                $config['type'] = 'multisite_subdirectory';
            }
            $url = self::get_main_site_url();
            $part = wp_parse_url($url);
            $config['domain'] = $part['host'];
            $config['path'] = (!empty($part['path']) ? str_replace('/', '__', trim( $part['path'], '/' ) ) : '');
        }
        else {
            $url = untrailingslashit(home_url());
            $part = wp_parse_url($url);
            if(empty($part['path'])) {
                $config['type'] = 'single_domain';
            }
            else {
                $config['type'] = 'single_subdirectory';
            }
            $config['domain'] = $part['host'];
            $config['path'] = (!empty($part['path']) ? str_replace('/', '__', trim( $part['path'], '/' ) ) : '');
        }

        if (is_multisite()) {
            $main_site_id = self::get_main_site_id();
            switch_to_blog($main_site_id);
        }

        $options = self::get_setting();
        $config['status'] = $options['redis_cache_onn'];
        $config['prefix'] = $options['redis_prefix'];
        $config['maxttl'] = (int) $options['redis_maxttl_var'];
        if (!empty($options['redis_ignored_groups_onn']) && !empty($options['redis_ignored_groups_mch']) && is_array($options['redis_ignored_groups_mch'])) {
            $ignored_groups = (array) $options['redis_ignored_groups_mch'];
            $config['ignored_groups'] = $ignored_groups;
        }
        else {
            $config['ignored_groups'] = [];
        }

        if (is_multisite()) {
            restore_current_blog();
        }

        if ($config['type'] == 'multisite_subdomain') {
            foreach (get_sites(array('number' => 500)) as $site) {
                $blog_id = $site->blog_id;
                $url = get_home_url($blog_id);
                $part = wp_parse_url($url);
                $host = $part['host'];
                $config['status_sites'][$host] = self::get_setting('redis_cache_onn', $blog_id);
            }
        }

        return $config;
    }

    /**
     * uninstall_dropin.
     */
    public static function uninstall_dropin($config = true, $status = false)
    {
        self::debug(__METHOD__, 'event run');

        $removed = false;
        $file_dropin = self::get_dropin_file();
        if (file_exists($file_dropin)) {
            self::debug(__METHOD__, 'remove dropin');
            $removed = @unlink($file_dropin);
        }

        if ($config) {
            $file_dropin_config = self::get_dropin_config_file();
            if (file_exists($file_dropin_config)) {
                self::debug(__METHOD__, 'remove dropin config');
                @unlink($file_dropin_config);
            }
        }

        wp_cache_flush();

        if ($status) {
            if ($removed) {
                return array(
                    'code' => 200,
                    'status' => esc_html__('Removing object cache drop-in was successful', 'runcloud-hub'),
                );
            }
            else {
                return array(
                    'code' => 0,
                    'status' => esc_html__('Removing object cache drop-in failed', 'runcloud-hub'),
                );
            }
        }

        return $removed;
    }

    /**
     * is_plugin_active.
     */
    public static function is_plugin_active($plugin)
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($plugin);
    }

    /**
     * is_plugin_active_for_network.
     */
    private static function is_plugin_active_for_network($plugin)
    {
        if (!function_exists('is_plugin_active_for_network')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active_for_network($plugin);
    }

    /**
     * force_site_deactivate_plugin.
     */
    private static function force_site_deactivate_plugin()
    {
        if ((self::can_manage_network_plugins() || self::is_wp_cli())
            && self::is_plugin_active_for_network(self::$hook)) {
            foreach (get_sites(array('number' => 500)) as $site) {
                switch_to_blog($site->blog_id);
                deactivate_plugins(self::$hook, true, false);
                restore_current_blog();
            }
        }
    }

    /**
     * callback_links.
     */
    public static function callback_links($links)
    {
        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                admin_url('options-general.php?page=' . self::$slug),
                esc_html__('Settings', 'runcloud-hub')
            )
        );
        return $links;
    }

    /**
     * callback_page.
     */
    public static function callback_page()
    {
        $hook_id = add_submenu_page(
            'options-general.php',
            self::$name,
            self::$name,
            apply_filters('capability', 'manage_options'),
            self::$slug,
            [__CLASS__, 'view_index']
        );
    }

    /**
     * callback_network_page.
     */
    public static function callback_network_page()
    {
        $hook_id = add_submenu_page(
            'settings.php',
            self::$name,
            self::$name,
            apply_filters('capability', 'manage_network_options'),
            self::$slug,
            [__CLASS__, 'view_network_index']
        );
    }

    /**
     * is_ourscreen.
     */
    private static function is_ourscreen()
    {
        if(!function_exists('get_current_screen')) {
            return false;
        }
        $screen = get_current_screen();
        $allowed_screens = array(
            'settings_page_'.self::$slug,
            'settings_page_'.self::$slug.'-network',
        );
        if ( !empty($screen->id) && in_array($screen->id, $allowed_screens) ) {
            return true;
        }
        return false;
    }

    /**
     * view_timeduration_select.
     */
    private static function view_timeduration_select($selected = '60', $skip = '')
    {
        $lists = [
            [
                'id'  => 'minute',
                'sec' => MINUTE_IN_SECONDS,
                'txt' => esc_html__('Minutes', 'runcloud-hub'),
            ],
            [
                'id'  => 'hour',
                'sec' => HOUR_IN_SECONDS,
                'txt' => esc_html__('Hours', 'runcloud-hub'),
            ],
            [
                'id'  => 'day',
                'sec' => DAY_IN_SECONDS,
                'txt' => esc_html__('Days', 'runcloud-hub'),
            ],
            [
                'id'  => 'week',
                'sec' => WEEK_IN_SECONDS,
                'txt' => esc_html__('Weeks', 'runcloud-hub'),
            ],
            [
                'id'  => 'month',
                'sec' => MONTH_IN_SECONDS,
                'txt' => esc_html__('Months', 'runcloud-hub'),
            ],
            [
                'id'  => 'year',
                'sec' => YEAR_IN_SECONDS,
                'txt' => esc_html__('Years', 'runcloud-hub'),
            ],
        ];

        foreach ($lists as $arr) {
            $id  = $arr['id'];
            $val = $arr['sec'];
            $txt = $arr['txt'];

            if (!empty($skip)) {
                if (is_array($skip) && in_array($id, $skip)) {
                    continue;
                } elseif ($skip === $id) {
                    continue;
                }
            }

            echo '<option value="' . esc_attr($val) . '"' . ($val == $selected ? ' selected' : '') . '>' . esc_html($txt) . '</option>';
        }
    }

    /**
     * view_timeduration_select.
     */
    private static function view_number_select($selected = 0, $min = 0, $max = 100)
    {
        for ($x = $min; $x <= $max; $x++) {
            echo '<option value="' . esc_attr($x) . '"' . ($x == $selected ? ' selected' : '') . '>' . esc_html($x) . '</option>';
        }
    }

    /**
     * view_stats_select.
     */
    private static function view_stats_select($selected = 'hourly', $skip = '')
    {
        $types = [
            'hourly'  => esc_html__('Hourly', 'runcloud-hub'),
            'daily'   => esc_html__('Daily', 'runcloud-hub'),
            'monthly' => esc_html__('Monthly', 'runcloud-hub'),
        ];

        foreach ($types as $id => $name) {
            if (!empty($skip) && $skip === $id) {
                continue;
            }
            echo '<option value="' . esc_attr($id) . '"' . ($id == $selected ? ' selected' : '') . '>' . esc_html($name) . '</option>';
        }
    }

    /**
     * view_purge_link.
     */
    private static function view_purge_link($type)
    {
        echo esc_url_raw( self::get_purge_link($type) );
    }

    /**
     * view_site_select.
     */
    private static function view_site_select()
    {
        foreach (get_sites(array('number' => 500)) as $site) {
            $n = $site->blog_id;
            $v = self::remove_protocol(get_home_url($n));
            if ((int) $n === (int) self::get_main_site_id()) {
                $v = $v . ' '.esc_html__('(primary)', 'runcloud-hub');
            }
            echo '<option value="' . esc_attr($n) . '"' . ($n == $selected ? ' selected' : '') . '>' . esc_html($v) . '</option>';
        }
    }

    /**
     * view_site_switch.
     */
    private static function view_site_switch()
    {
        if (!is_multisite()) {
            return;
        }

        $network_active = is_plugin_active_for_network( self::$slug.'/'.self::$slug.'.php' );
        $current_blog_id = get_current_blog_id();
        $main_site_id = self::get_main_site_id();

        if (is_network_admin()) {
            echo '<option value="">' . esc_html__('Select Site', 'runcloud-hub') . '</option>';
        }
        if ($network_active && !is_network_admin()) {
            $admin_url = network_admin_url('settings.php?page=' . self::$slug);
            echo '<option value="'.esc_attr($admin_url).'">' . esc_html__('Network Admin Settings', 'runcloud-hub') . '</option>';
        }

        $sites = get_sites(array('number' => 500));
        foreach ($sites as $site) {
            $blog_id = $site->blog_id;
            switch_to_blog($blog_id);
            if (!$network_active) {
                $active = is_plugin_active( self::$slug.'/'.self::$slug.'.php' );
                if (!$active) {
                    restore_current_blog();
                    continue;
                }
            }
            $label = home_url();
            $label = self::remove_protocol($label);
            if ($main_site_id == $blog_id) {
                $label = $label . ' '.esc_html__('(primary)', 'runcloud-hub');
            }
            $admin_url = admin_url('options-general.php?page=' . self::$slug);
            echo '<option value="' . esc_attr($admin_url) . '"' . (!is_network_admin() && $blog_id == $current_blog_id ? ' selected' : '') . '>' . esc_html($label) . '</option>';
            restore_current_blog();
        }
    }

    /**
     * view_fattr.
     */
    private static function view_fattr()
    {
        echo 'autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"';
    }

    /**
     * view_fname.
     */
    private static function view_fname($name)
    {
        echo self::$db_setting . '[' . esc_attr($name) . ']';
    }

    /**
     * view_checked.
     *
     * @since   0.0.0
     */
    private static function view_checked($name)
    {
        echo (isset(self::$checked[$name]) ? esc_attr(self::$checked[$name]) : '');
    }

    /**
     * view_rvalue.
     */
    private static function view_rvalue($name)
    {
        return (isset(self::$value[$name]) ? self::$value[$name] : '');
    }

    /**
     * view_fvalue.
     */
    private static function view_fvalue($name)
    {
        echo self::view_rvalue($name);
    }

    /**
     * view_do_preload.
     */
    private static function view_do_preload()
    {
        $buff = self::wakeup_get('lookup_preload_run');
        if (empty($buff)) {
            return;
        }

        if(isset($buff['num']) && isset($buff['total'])){
            $num = $buff['num'];
            $total = $buff['total'];
        }
        else {
            $num = 0;
            $total = self::get_total_posts();
            $total = self::get_total_posts();
            if ($total) {
                self::wakeup_set(
                    'lookup_preload_run',
                    [
                        'num'   => 0,
                        'total' => $total,
                        'type'  => 'admin',
                    ]
                );
            }
        }

        $type = isset($buff['type']) ? $buff['type'] : 'admin';

        if (!$total || ($total && $num >= $total)) {
            self::wakeup_flush('lookup_preload_run');
            self::wakeup_flush('preload_process');
            return;
        }

        if ($type === 'admin') {
            if ($num == 0) {
                // preload homepage
                $preload_url = home_url('/');
                self::fetch_preload($preload_url, false);
                // preload path
                $options = self::get_setting();
                if (!empty($options['preload_path_onn']) && !empty($options['preload_path_mch']) && is_array($options['preload_path_mch'])) {
                    foreach ($options['preload_path_mch'] as $path) {
                        if ('/' === $path) {
                            continue;
                        }
                        $path    = strtolower($path);
                        $preload_url = home_url('/') . ltrim($path, '/');
                        self::fetch_preload($preload_url, false);
                    }
                }
            }

            $time = time();
            $link_cancel = self::get_purge_link('preload_cancel');
            $link_run_background = self::get_purge_link('preload_run_background');
            $inc = self::get_preload_inc();
            $interval = self::get_preload_interval();

            $link_trigger = admin_url('admin-post.php?action=' . self::nw_hash(self::$slug . '_preload') . '&_preload=' . $time);
            $link_trigger = add_query_arg('_wpnonce', wp_create_nonce('preload' . $time), $link_trigger);

            echo '<div class="notice notice-info" id="runcloud-preload" ';
            echo 'data-preload-abort="' . esc_url_raw($link_cancel) . '" ';
            echo 'data-preload-trigger="' . esc_url_raw($link_trigger) . '" ';
            echo 'data-preload-num="' . esc_attr($num) . '" ';
            echo 'data-preload-total="' . esc_attr($total) . '" ';
            echo 'data-preload-inc="' . esc_attr($inc) . '" ';
            echo 'data-preload-interval="' . esc_attr($interval) . '">';
            echo '<p id="preload-text"><strong>';
            if ($num) {
                esc_html_e('Preloading is running... Please wait...', 'runcloud-hub');
            }
            else {
                esc_html_e('Preloading is started... Please wait...', 'runcloud-hub');
            }
            echo '</strong></p>';
            echo '<p id="preload-button">';
            echo '<a href="' . esc_url_raw($link_cancel) . '" class="preload-stop button-primary">'.esc_html__('Cancel', 'runcloud-hub').'</a>';
            echo '<a href="' . esc_url_raw($link_run_background) . '" class="preload-switch button-secondary">'.esc_html__('Run in the background', 'runcloud-hub').'</a>';
            echo '</p>';
            echo '</div>';
        }
        else {
            $link_cancel = self::get_purge_link('preload_cancel');
            $link_run_admin = self::get_purge_link('preload_run_admin');
            echo '<div class="notice notice-info" id="runcloud-preload">';
            echo '<p id="preload-text"><strong>';
            printf( esc_html__('Preloading is running in the background (%s/%s). Please refresh to see the progress.', 'runcloud-hub'), $num, $total );
            echo '</strong></p>';
            echo '<p id="preload-button">';
            echo '<a href="' . esc_url_raw($link_cancel) . '" class="preload-stop button-primary">'.esc_html__('Stop', 'runcloud-hub').'</a>';
            echo '<a href="' . esc_url_raw($link_run_admin) . '" class="preload-switch button-secondary">'.esc_html__('Run now', 'runcloud-hub').'</a>';
            echo '</p>';
            echo '</div>';
        }
    }

    /**
     * callback_view_preload.
     */
    public static function callback_view_preload()
    {
        if (!isset($_GET['_wpnonce'], $_GET['_preload'])) {
            self::debug(__METHOD__, esc_html__('Invalid request', 'runcloud-hub'));
            self::wakeup_flush('lookup_preload_run');
            self::wakeup_flush('preload_process');
            self::close_exit(esc_html__('Invalid request', 'runcloud-hub'));
        }

        if (!wp_verify_nonce($_GET['_wpnonce'], 'preload' . $_GET['_preload'])) {
            self::debug(__METHOD__, esc_html__('Invalid nonce', 'runcloud-hub'));
            self::wakeup_flush('lookup_preload_run');
            self::wakeup_flush('preload_process');
            self::close_exit(esc_html__('Invalid nonce', 'runcloud-hub'));
        }

        $buff = self::wakeup_get('lookup_preload_run');
        if (!empty($buff) && is_array($buff)) {
            if (isset($_GET['num']) && self::is_num($_GET['num'])) {
                $num  = (int) $_GET['num'];
                if ($num >= (int) $buff['total']) {
                    self::wakeup_flush('lookup_preload_run');
                    self::wakeup_flush('preload_process');
                    self::close_exit('Done');
                } 
                else {
                    $buff['num'] = $num;
                    self::wakeup_set('lookup_preload_run', $buff);
                    $inc = self::get_preload_inc();
                    $urls = self::get_preload_urls($buff['num'], $inc);
                    if (!empty($urls)) {
                        foreach ($urls as $url) {
                            $num++;
                            self::debug(__METHOD__, sprintf( esc_html__('Preload %s / %s : %s', 'runcloud-hub'), $num, $buff['total'], $url ) );
                            self::fetch_preload($url, false);
                        }
                        self::close_exit($url);
                    }
                    else {
                        self::debug(__METHOD__, sprintf( esc_html__('Preload %s / %s : %s', 'runcloud-hub'), $num, $buff['total'], esc_html__('Preload URL is empty', 'runcloud-hub') ) );
                        self::close_exit();
                    }
                }
            }
        }
        self::debug(__METHOD__, esc_html__('Preload URL is empty', 'runcloud-hub') );
        self::close_exit();
    }

    /**
     * view_page.
     */
    public static function view_page($name, $once = false)
    {
        $__varfunc_view = function ($name, $once = false) {
            $file = RUNCLOUD_HUB_PATH_VIEW . $name . '.php';
            if (is_file($file) && is_readable($file)) {
                clearstatcache(true, $file);

                if ($once) {
                    include_once $file;
                } else {
                    include $file;
                }
            } else {
                echo sprintf( esc_html__( 'Cannot read "%s" file', 'runcloud-hub'), $name ).'<br>';
            }
        };

        if (!empty($name)) {
            if (is_array($name)) {
                foreach ($name as $nm) {
                    $__varfunc_view($nm);
                }
            } else {
                $__varfunc_view($name);
            }
        }
    }

    /**
     * view_index.
     */
    public static function view_index($page = '')
    {
        $options_default = self::default_settings();
        $options         = self::get_setting();
        self::$checked   = [];
        if (!empty($options) && is_array($options)) {
            foreach ($options as $key => $val) {
                if (preg_match('/.*_onn$/', $key)) {
                    $val                 = (int) $val;
                    self::$checked[$key] = (1 === $val ? ' checked' : '');
                    self::$value[$key]   = $val;
                } elseif (preg_match('/.*_mch$/', $key)) {
                    $ckey = str_replace('_mch', '_onn', $key);
                    if (!empty($val) && is_array($val)) {
                        self::$value[$key] = implode("\n", $val);
                    } else {
                        self::$checked[$ckey] = '';
                        self::$value[$key]    = (!empty($options_default[$key]) && is_array($options_default[$key]) ? implode("\n", $options_default[$key]) : '');
                    }
                } elseif (preg_match('/^rcapi.*_id$/', $key)) {
                    self::$value[$key] = (empty($val) ? '' : $val);
                } else {
                    self::$value[$key] = $val;
                }
            }
        }

        $page = (!empty($page) ? esc_attr($page) : 'admin');
        self::view_page($page);
    }

    /**
     * view_network_index.
     */
    public static function view_network_index()
    {
        self::view_index('admin-network');
    }

    /**
     * assets_version.
     */
    private static function assets_version($file)
    {
        $fm = RUNCLOUD_HUB_PATH_RSC . $file;
        if (file_exists($fm)) {
            return self::nw_hash(filemtime($fm));
        }

        return self::nw_hash(self::$version);
    }

    /**
     * admin_assets.
     */
    public static function admin_assets()
    {
        if ( self::can_manage_options() ) {
            $fm = 'css/' . self::$islug . '-wp.css';
            wp_enqueue_style(
                self::$slug . '-wp',
                self::$plugin_url_assets . $fm,
                [],
                self::assets_version($fm),
                'all'
            );
        }

        if(!self::is_ourscreen()) {
            return;
        }

        $fm = 'css/' . self::$islug . '.css';
        wp_enqueue_style(
            self::$slug,
            self::$plugin_url_assets . $fm,
            [],
            self::assets_version($fm),
            'all'
        );

        $fm = 'js/' . self::$islug . '.js';
        wp_enqueue_script(
            self::$slug,
            self::$plugin_url_assets . $fm,
            [],
            self::assets_version($fm),
            true
        );
    }

    /**
     * append_wp_http_referer.
     */
    private static function append_wp_http_referer()
    {
        $referer = '';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $referer = filter_var(wp_unslash($_SERVER['REQUEST_URI']), FILTER_SANITIZE_URL);
            $referer = '&_wp_http_referer=' . rawurlencode(remove_query_arg('fl_builder', $referer));
        }

        return $referer;
    }

    /**
     * callback_bar_menu.
     */
    public static function callback_bar_menu($wp_admin_bar)
    {
        if (!self::can_manage_options()) {
            return;
        }

        if (is_network_admin() && !self::is_plugin_active_for_network(self::$hook)) {
            return;
        }

        global $pagenow, $post;

        $referer = self::append_wp_http_referer();
        $wp_admin_bar->add_menu(
            [
                'id'    => self::$slug,
                'title' => self::$name,
            ]
        );

        $admin_url = (is_network_admin() ? network_admin_url('settings.php?page=' . self::$slug) : admin_url('options-general.php?page=' . self::$slug));

        if (!self::$optpage) {
            $wp_admin_bar->add_menu(
                [
                    'parent' => self::$slug,
                    'id'     => self::$slug . '-setting',
                    'title'  => esc_html__('Settings', 'runcloud-hub'),
                    'href'   => $admin_url . (self::is_ourscreen() ? '#setting' : ''),
                ]
            );
        }

        if (is_admin()) {
            if ($post && 'post.php' === $pagenow && isset($_GET['action'], $_GET['post'])) {
                $wp_admin_bar->add_menu(
                    [
                        'parent' => self::$slug,
                        'id'     => self::$slug . '-clearcachepost',
                        'title'  => esc_html__('Clear Cache Of This Post', 'runcloud-hub'),
                        'href'   => self::get_purge_link('post-' . get_the_ID()),
                    ]
                );
            }
        } else {
            if (is_singular()) {
                $wp_admin_bar->add_menu(
                    [
                        'parent' => self::$slug,
                        'id'     => self::$slug . '-clearcachepost',
                        'title'  => esc_html__('Clear Cache Of This Post', 'runcloud-hub'),
                        'href'   => self::get_purge_link('post-' . get_the_ID()),
                    ]
                );
            }
            else {
                $wp_admin_bar->add_menu(
                    [
                        'parent' => self::$slug,
                        'id'     => self::$slug . '-clearcacheurl',
                        'title'  => esc_html__('Clear Cache Of This URL', 'runcloud-hub'),
                        'href'   => self::get_purge_link('url'),
                    ]
                );
            }
        }

        if (!is_network_admin()) {
            $wp_admin_bar->add_menu(
                [
                    'parent' => self::$slug,
                    'id'     => self::$slug . '-clearcacheall',
                    'title'  => esc_html__('Clear All Cache', 'runcloud-hub'),
                    'href'   => self::get_purge_link('all'),
                ]
            );

            if (self::redis_is_connect() && self::is_dropin_active()) {
                $wp_admin_bar->add_menu(
                    [
                        'parent' => self::$slug,
                        'id'     => self::$slug . '-clearcacheredis',
                        'title'  => esc_html__('Clear Redis Object Cache', 'runcloud-hub'),
                        'href'   => self::get_purge_link('redis'),
                    ]
                );
            }
        }
        else {
            $wp_admin_bar->add_menu(
                [
                    'parent' => self::$slug,
                    'id'     => self::$slug . '-clearcacheallsites',
                    'title'  => esc_html__('Clear All Sites Cache', 'runcloud-hub'),
                    'href'   => self::get_purge_link('siteall'),
                ]
            );

            if (self::redis_is_connect() && self::is_dropin_active()) {
                $wp_admin_bar->add_menu(
                    [
                        'parent' => self::$slug,
                        'id'     => self::$slug . '-clearcacheredis',
                        'title'  => esc_html__('Clear All Sites Redis Object Cache', 'runcloud-hub'),
                        'href'   => self::get_purge_link('redisall'),
                    ]
                );
            }
        }
    }

    /**
     * after_update_setting.
     */
    public static function after_update_setting($old, $options)
    {
        if (!empty($options)) {
            self::lookup_reset();

            $doreload = false;

            if (is_multisite()) {

                if ( false !== strpos(wp_get_referer(), '/network/settings.php')) {
                    self::debug(__METHOD__, ['multisite'=>get_site_url()]);
                    if ( !empty($options['redis_cache_net_onn']) ) {
                        $redis_cache_onn = $options['redis_cache_onn'];
                        foreach (get_sites(array('number' => 500)) as $site) {
                            switch_to_blog($site->blog_id);

                            self::debug(__METHOD__.'update_setting_var:redis_cache_onn='.$redis_cache_onn, ['multisite'=>get_site_url()]);

                            self::update_setting_var('redis_cache_onn', $redis_cache_onn);
                            restore_current_blog();
                        }
                    }

                    // display notice for network
                    self::wakeup_set('___network_reload', $doreload ? 1 : 3 );
                }
            }

            self::wp_cache_delete();

            // 2 = reset with current changes
            self::schedule_cron_purge(2);
            self::schedule_cron_preload(2);
            self::schedule_cron_rcstats(2);

            // update rcapi push
            $referer = wp_get_raw_referer();
            if (!empty($referer) && preg_match('@#runcache(\-rules)?$@', $referer, $mm)) {
                add_action('shutdown', [__CLASS__, 'rcapi_push'], self::$ordlast);
            }

            // fetch stats if stats is empty
            add_action('shutdown', [__CLASS__, 'rcapi_fetch_stats'], self::$ordlast);

            // check dropin
            add_action('shutdown', [__CLASS__, 'try_setup_dropin'], self::$ordlast);
        }
    }

    /**
     * callback_register_setting.
     */
    public static function callback_register_setting()
    {
        register_setting(
            self::$slug,
            self::$db_setting,
            [__CLASS__, 'update_setting']
        );
    }

    /**
     * callback_notices.
     */
    public static function callback_notices()
    {
        if (!is_admin()) {
            return;
        }

        if (defined('DOING_AUTOSAVE') || self::is_ajax() ) {
            return;
        }

        if (self::can_manage_options()) {
            add_action('all_admin_notices', [__CLASS__, 'callback_compatibility'], self::$ordlast);
            add_action('all_admin_notices', [__CLASS__, 'callback_purgecache_notice'], self::$ordlast);
        }
    }

    /**
     * callback_compatibility.
     */
    public static function callback_compatibility()
    {
        if (self::maybe_deactivate_runcache_purger()) {
            $msg  = esc_html__('RunCache Purger has been automatically deactivated due to superseded by the RunCloud Hub Plugin', 'runcloud-hub');
            echo '<div id="rc-notice" class="notice notice-info is-dismissible">';
            echo '<p>';
            if (!self::is_ourscreen()) {
                echo '<strong>' . self::$name . ':</strong>&nbsp;' . esc_html($msg);
            } else {
                echo '<strong>' . esc_html($msg) . '</strong>';
            }
            echo '</p>';
            echo '</div>';
        }

        if ( self::is_ourscreen() ) {
            $__varfunc_redirect = function($opt = 1) {
                echo '<div id="rc-notice" class="notice notice-info is-dismissible">';
                echo '<p><strong>'.esc_html__('Updating... Please wait...', 'runcloud-hub').'</strong></p>';
                echo '</div>';

                echo '<script type="text/javascript" id="' . self::$slug . '-dropinrefresh">';
                echo '(function(){';
                if ( 1 === $opt ) {
                    echo 'var url = window.location.href.replace(/#/,"&rcpage=");';
                } else {
                    echo 'var url = window.location.href.replace(/&rcpage=/,"#");';
                }
                echo 'window.setTimeout(function() { window.location.replace(url); }, 1);';
                echo '})();';
                echo '</script>';
                self::fastcgi_close();
            };

            $__varfunc_flush_keys = function() {
                self::wakeup_flush('___network_reload');
                self::wakeup_flush('page_reload');
            };

            $__varfunc_display_notice = function() {
                echo '<div id="rc-notice" class="notice notice-success is-dismissible">';
                echo '<p><strong>'.esc_html__('Settings saved.', 'runcloud-hub').'</strong></p>';
                echo '</div>';
            };

            $key = ( is_network_admin() ? '___network_reload' : 'page_reload' );
            if (self::wakeup_check($key)) {

                $token = self::wakeup_get($key);

                if ( '3' === $token ) {
                    $__varfunc_flush_keys();
                    $__varfunc_display_notice();

                } elseif ( '2' === $token ) {
                    self::wakeup_set($key, 3);
                    $__varfunc_redirect(2);

                } elseif ( '1' === $token ) {
                    self::wakeup_set($key, 2);
                    $__varfunc_redirect(1);

                } else {
                    $__varfunc_flush_keys();
                }
            }
        }
    }

    /**
     * get_purge_link.
     */
    private static function get_purge_link($type)
    {
        $tag     = 'purge';
        $referer = self::append_wp_http_referer();
        if (!is_admin()) {
            $referer .= '&__notadmin=1';
        }
        return wp_nonce_url(admin_url('admin-post.php?action=' . self::nw_hash(self::$slug . '_' . $tag) . '&type=' . $type . $referer), $tag . '_' . $type);
    }

    /**
     * get_setting.
     */
    public static function get_setting($key = '', $blog_id = '')
    {
        if ($blog_id && is_multisite()) {
            switch_to_blog($blog_id);
        }

        $options = get_option(self::$db_setting, self::default_settings());
        if (!empty($options) && is_array($options)) {
            $options = array_merge(self::default_settings(), $options);
        } else {
            $options = [];
        }

        if (!empty($key)) {
            if (isset($options[$key])) {
                $return = $options[$key];
            }
            else {
                $return = null;
            }
        }
        else {
            $return = $options;
        }

        if ($blog_id && is_multisite()) {
            restore_current_blog();
        }

        return $return;
    }

    /**
     * dump_setting.
     */
    public static function dump_setting($all = true)
    {
        $__varfunc_options = function () {
            $options = get_option(self::$db_setting, self::default_settings());
            if (!empty($options) && is_array($options)) {
                $options = array_merge(self::default_settings(), $options);
            } else {
                $options = [];
            }
            return $options;
        };

        if (is_multisite()) {
            if ($all) {
                $results = [];
                $num = 0;
                foreach (get_sites(array('number' => 500)) as $site) {
                    $num++;
                    switch_to_blog($site->blog_id);
                    $results[$num]['blog_id'] = $site->blog_id;
                    $results[$num]['url']     = home_url();
                    $results[$num]['options'] = $__varfunc_options();
                    restore_current_blog();
                }
                return $results;
            }
        }

        return $__varfunc_options();
    }

    /**
     * set_settings.
     */
    public static function reset_purge_action()
    {
        self::$is_purge_cache_home    = false;
        self::$is_purge_cache_content = false;
        self::$is_purge_cache_archive = false;
        self::$is_purge_cache_urlpath = false;
        self::$is_run_preload         = false;
        self::$is_redis_debug         = false;
        self::$is_html_footprint      = false;

        $options = self::get_setting();
        if (!empty($options)) {
            if (!empty($options['url_path_onn'])) {
                self::$is_purge_cache_urlpath = true;
            }

            if (!empty($options['preload_onn'])) {
                self::$is_run_preload = true;
            }

            if (!empty($options['redis_debug_onn'])) {
                self::$is_redis_debug = true;
            }

            if (!empty($options['html_footprint_onn'])) {
                self::$is_html_footprint = true;
            }
        }
    }

    /**
     * setting_cast_int.
     */
    private static function setting_cast_int($input)
    {
        if (!empty($input) && is_array($input)) {
            foreach ($input as $key => $val) {
                if (preg_match('/.*(_onn|_int|_unt)$/', $key)) {
                    $input[$key] = (int) $val;
                }
            }
        }
        return $input;
    }

    /**
     * sanitize_input.
     */
    private static function sanitize_input($input)
    {
        if (!empty($input) && is_array($input)) {
            $input = self::setting_cast_int($input);

            foreach ($input as $key => $val) {
                if (preg_match('/.*_int$/', $key)) {
                    if (('schedule_purge_int' === $key || 'preload_schedule_int' === $key) && empty($val)) {
                        $val = 1;
                    }

                    $key_prefix = str_replace('_int', '', $key);
                    $unt_key    = $key_prefix . '_unt';

                    if (isset($input[$unt_key])) {
                        $unt_key_var         = $key_prefix . '_var';
                        $input[$unt_key_var] = (0 !== $val ? ((int) $val * (int) $input[$unt_key]) : 0);
                    }
                } elseif ( 'rcapi_webapp_id' === $key ) {
                    $key = trim($key);
                    $input[$key] = (self::is_num($val) ? (int) $val : '');
                } elseif ( 'rcapi_key' === $key || 'rcapi_secret' === $key ) {
                    $input[$key] = trim($val);
                } elseif (preg_match('/.*_mch$/', $key) && !is_array($val)) {
                    $list = explode("\n", str_replace(["\n", "\r"], "\n", $val));
                    $list = array_unique($list);
                    foreach ($list as $num => $lst) {
                        if (empty($lst)) {
                            unset($list[$num]);
                        }

                        if ('url_path_mch' === $key || 'exclude_url_mch' === $key || 'preload_path_mch' === $key) {
                            $path = wp_parse_url($lst, PHP_URL_PATH);
                            if (!empty($path)) {
                                $path = strtolower($path);
                                if ('/' !== $path) {
                                    $list[$num] = $path;
                                } else {
                                    unset($list[$num]);
                                }
                            }
                        }
                    }
                    if (!empty($list) && is_array($list)) {
                        $list = array_values($list);
                    }

                    $input[$key] = (is_array($list) && count($list) > 0 ? $list : []);
                } elseif ('redis_prefix' === $key && empty($val)) {
                    $input[$key] = self::redis_key_prefix();
                } elseif ('redis_port' === $key && !self::is_num($val)) {
                    unset($input[$key]); // reset default
                } elseif ('cache_key_extra_var' === $key) {
                    $val = preg_replace('@[^A-Za-z0-9_$]@', '', trim($val));
                    if (empty($val)) {
                        $input['cache_key_extra_onn'] = 0;
                    } else {
                        if ( '$' !== substr($val, 0, 1) ) {
                            $val = '$' . $val;
                        }
                    }
                    $input[$key] = $val;
                }
            }
        }

        return $input;
    }

    /**
     * update_setting.
     */
    public static function update_setting($input)
    {
        $options = array_merge(self::default_settings(), self::get_setting());
        if (!empty($input) && is_array($input)) {
            foreach ($options as $key => $val) {
                if (preg_match('/.*_onn$/', $key)) {
                    if (!isset($input[$key])) {
                        $input[$key] = 0;
                    }
                }
            }
        } else {
            $input = self::default_settings();
        }

        $input                = self::sanitize_input($input);
        $options              = array_merge($options, $input);
        $options['timestamp'] = gmdate('Y-m-d H:i:s') . ' UTC';

        // rcapi key/secret only for main site
        if (!self::is_main_site()) {
            $options['rcapi_key']    = '';
            $options['rcapi_secret'] = '';
            $options['rcapi_webapp_id'] = '';
        }

        if (isset($options['stats_onn'])) {
            $options['stats_schedule_onn'] = $options['stats_onn'];
        }

        if (empty($options['stats_transfer_onn']) && empty($options['stats_health_onn'])) {
            $options['stats_onn'] = 0;
        }

        return $options;
    }

    /**
     * update_setting_var.
     */
    public static function update_setting_var($key, $value)
    {
        $options = self::get_setting();
        if (isset($options[$key])) {
            $options[$key]          = $value;
            $options                = self::sanitize_input($options);
            $options['timestamp']   = gmdate('Y-m-d H:i:s') . ' UTC';
            return update_option(self::$db_setting, $options);
        }
        return false;
    }

    /**
     * update_setting_cli.
     */
    public static function update_setting_cli($input)
    {
        $options                = self::get_setting();
        $input                  = self::sanitize_input($input);
        $options                = array_merge($options, $input);
        $options['timestamp']   = gmdate('Y-m-d H:i:s') . ' UTC';
        return update_option(self::$db_setting, $options);
    }

    /**
     * register_admin_hooks.
     */
    public static function register_admin_hooks()
    {
        if (!self::$optpage) {
            add_filter('plugin_action_links_' . self::$hook, [__CLASS__, 'callback_links'], self::$ordfirst);
        }
        add_action('admin_menu', [__CLASS__, 'callback_page'], self::$ordfirst);

        if (self::is_plugin_active_for_network(self::$hook)) {
            add_action('network_admin_menu', [__CLASS__, 'callback_network_page'], self::$ordfirst);
        }

        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets'], self::$ordlast);

        add_action('admin_bar_menu', [__CLASS__, 'callback_bar_menu'], self::$ordlast);
        add_action('update_option_' . self::$db_setting, [__CLASS__, 'after_update_setting'], self::$ordlast, 2);
        add_action('admin_init', [__CLASS__, 'callback_register_setting'], self::$ordfirst);
        add_action('admin_post_' . self::nw_hash(self::$slug . '_purge'), [__CLASS__, 'callback_purgecache'], self::$ordlast);
        add_action('admin_post_' . self::$slug . '_deactivate_plugin', [__CLASS__, 'callback_deactivate_plugin']);
        add_action('plugins_loaded', [__CLASS__, 'callback_notices'], self::$ordfirst);

        // autologin
        add_action('init', [__CLASS__, 'callback_magiclink_login'], self::$ordlast);

        // cron
        add_action('cron_purge_' . self::nw_hash(self::$slug . 'cron_purge'), [__CLASS__, 'callback_cron_purge'], self::$ordfirst);
        add_action('cron_preload_' . self::nw_hash(self::$slug . 'cron_preload'), [__CLASS__, 'callback_cron_preload'], self::$ordfirst);
        add_action('cron_rcstats_' . self::nw_hash(self::$slug . 'cron_rcstats'), [__CLASS__, 'callback_cron_rcstats'], self::$ordfirst);

        // wakeup
        add_action('admin_post_' . self::nw_hash(self::$slug . '_queue'), [__CLASS__, 'callback_wakeup_pong'], self::$ordfirst);

        add_action('admin_head', [__CLASS__, 'callback_wakeup_ping'], self::$ordlast);

        // preload
        add_action('admin_post_' . self::nw_hash(self::$slug . '_preload'), [__CLASS__, 'callback_view_preload'], self::$ordfirst);

        // try to auto-fix all drop-in issue
        add_action('admin_head', [__CLASS__, 'try_fix_dropin'], self::$ordlast);
    }

    /**
     * get_user_ip.
     */
    private static function get_user_ip()
    {
        foreach ([
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key]);
                $ip = end($ip);

                if (false !== filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * get_user_agent.
     */
    private static function get_user_agent($fallback_default = false)
    {
        $comp_ua    = '(compatible; RunCloud-Plugin ' . self::$version . '; +https://runcloud.io)';
        $default_ua = 'Mozilla/5.0 ' . $comp_ua;
        $ua         = '';
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            if (false === strpos($_SERVER['HTTP_USER_AGENT'], 'RunCloud-Plugin')) {
                $ua = $_SERVER['HTTP_USER_AGENT'] . ' ' . $comp_ua;
            } else {
                $ua = $_SERVER['HTTP_USER_AGENT'];
            }
        }

        if ($fallback_default && empty($ua)) {
            $ua = $default_ua;
        }

        return $ua;
    }

    /**
     * __curl_resolve_loopback.
     */
    public static function __curl_resolve_loopback($handle, $r, $url)
    {
        $myhost = parse_url(get_site_url(), PHP_URL_HOST);

        $url_host   = parse_url($url, PHP_URL_HOST);
        $url_scheme = parse_url($url, PHP_URL_SCHEME);
        $url_port   = parse_url($url, PHP_URL_PORT);

        if ($myhost !== $url_host) {
            return;
        }

        $port = 80;

        if ('https' === $url_scheme) {
            $port = 443;
        }

        if (!empty($url_port) && $url_port !== $port) {
            $port = $url_port;
        }

        curl_setopt(
            $handle,
            CURLOPT_RESOLVE,
            [
                $url_host . ':' . $port . ':127.0.0.1',
            ]
        );

        curl_setopt(
            $handle,
            CURLOPT_DNS_USE_GLOBAL_CACHE, 
            false
        );
    }

    /**
     * purge_request.
     */
    private static function purge_request($url, $options = [])
    {
        if (self::is_defined_halt()) {
            return false;
        }

        $hostname = parse_url($url, PHP_URL_HOST);

        static $done = [];
 
        if (isset($done[$hostname][$url])) {
            return;
        }

        $args = [
            'method'      => 'GET',
            'timeout'     => 10,
            'redirection' => 5,
            'httpversion' => '1.1',
            'user-agent'  => self::get_user_agent(),
            'blocking'    => true,
            'headers'     => ['Host' => $hostname],
            'cookies'     => [],
            'body'        => null,
            'compress'    => false,
            'decompress'  => true,
            'sslverify'   => false,
            'stream'      => false,
            'filename'    => null,
        ];

        if (!empty($options) && is_array($options)) {
            $args = array_merge($args, $options);
        }

        if ($args['blocking'] === false) {
            $args['timeout'] = 0.01;
        }

        $return = [
            'blocking' => $args['blocking'],
            'code'   => '',
            'status' => '',
            'host'   => $args['headers']['Host'],
            'url'    => $url,
            'method' => $args['method'],
        ];

        add_action('http_api_curl', [__CLASS__, '__curl_resolve_loopback'], self::$ordlast, 3);

        $response = wp_remote_request($url, $args);

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code == 403) {
            if (false !== strpos($url, 'http://')) {
                $url = str_replace('http://', 'https://', $url);
                $response = wp_remote_request($url, $args);
                $return['url'] = $url;
                $return['note'] = 'retry code : '.$response_code;
            }
        }

        if (is_wp_error($response)) {
            $return['code']   = wp_remote_retrieve_response_code($response);
            $return['status'] = $response->get_error_message();
            self::define_halt();
        } else {
            $return['header'] = (is_object($response['headers']) ? (array) $response['headers'] : null);
            $return['code']   = wp_remote_retrieve_response_code($response);
            if (self::is_debug()) {
                $return['response'] = $response['body'];
            }
        }

        remove_action('http_api_curl', [__CLASS__, '__curl_resolve_loopback'], self::$ordfirst);

        if ($args['blocking'] === false) {
            $return['status'] = esc_html__('Purge cache was initiated and will run shortly', 'runcloud-hub');
        }

        if (empty($return['status'])) {
            switch ($return['code']) {
                case '200':
                    // Successful purge
                    $return['status'] = esc_html__('Purge cache was successful', 'runcloud-hub');
                    break;
                case '412':
                    // Preconditioned failed
                    $return['status'] = esc_html__('Purge cache was skipped (no cache)', 'runcloud-hub');
                    break;
                case '400':
                    // Request Forbidden
                    $return['status'] = esc_html__('Purge cache failed - Request Forbidden', 'runcloud-hub');
                    break;
                case '403':
                    // Request Forbidden
                    $return['status'] = esc_html__('Purge cache failed - Request Forbidden', 'runcloud-hub');
                    break;
                case '404':
                    // Request Not Found
                    $return['status'] = esc_html__('Purge cache failed - Request Not Found', 'runcloud-hub');
                    break;
                case '405':
                    // Method Not Allowed
                    $return['status'] = esc_html__('Purge cache failed - Method Not Allowed', 'runcloud-hub');
                    self::define_halt();
                    break;
                default:
                    if (substr($return['code'], 0, 2) == 50) {
                        $return['status'] = esc_html__('Purge cache failed - Failed to connect', 'runcloud-hub');
                    }
                    else {
                        $return['status'] = sprintf(esc_html__('Purge cache failed - %s', 'runcloud-hub'), $return['code']);
                    }
                    self::define_halt();
            }
        }

        self::debug(__METHOD__, $return);

        $done[$hostname][$url] = 1;

        return $return;
    }

    /**
     * nginx_purge_type.
     */
    public static function nginx_purge_type()
    {
        if (self::is_srcache()) {
            return 'srcache';
        }

        return (self::is_nginx() ? 'fastcgi' : 'proxy');
    }

    /**
     * nginx_purge_all.
     */
    private static function nginx_purge_all($type = null, $proto = null, $run_action = true)
    {
        static $done_purgeall = false;

        if (self::is_defined_halt()) {
            return false;
        }

        $type = (!empty($type) ? $type : self::nginx_purge_type());

        if (empty($proto) && 'http' !== $proto && 'https' !== $proto) {
            $proto = (self::is_wp_ssl() ? 'https' : 'http');
        }

        if ( !is_multisite() ) {
            $hostname = parse_url(home_url(), PHP_URL_HOST);
            $purge_prefix  = self::$purge_prefix_all;
            $request_query = $proto . '://' . $hostname . '/' . $purge_prefix . '-' . $type;
            $return = self::purge_request($request_query, ['method' => 'PURGE']);
        }
        else {
            $url = home_url('/');
            $hostname = parse_url($url, PHP_URL_HOST);
            $purge_prefix  = self::$purge_prefix_var;
            $request_query = str_replace($hostname, $hostname . '/' . $purge_prefix . '-' . $type . '/', $url) . '*';
            $request_query = str_replace($purge_prefix . '-' . $type . '//', $purge_prefix . '-' . $type . '/', $request_query);
            $return = self::purge_request($request_query, ['method' => 'GET']);
        }

        if (!$done_purgeall) {
            if ( $run_action ) {
                // remove others cache
                self::purge_cache_known_plugins();
                // trigger others plugin
                do_action('runcloud_purge_nginx_cache');
            }
            $done_purgeall = true;
        }

        self::purge_cache_redis();

        return $return;
    }

    /**
     * nginx_purge_all_sites.
     */
    private static function nginx_purge_all_sites($type = null, $proto = null, $run_action = true)
    {
        static $done_purgesiteall = false;

        if (self::is_defined_halt()) {
            return false;
        }

        $type = (!empty($type) ? $type : self::nginx_purge_type());

        if (empty($proto) && 'http' !== $proto && 'https' !== $proto) {
            $proto = (self::is_wp_ssl() ? 'https' : 'http');
        }

        $blog_id = self::get_main_site_id();
        $url = home_url('/');
        $hostname = parse_url($url, PHP_URL_HOST);
        $purge_prefix  = self::$purge_prefix_all;
        $request_query = $proto . '://' . $hostname . '/' . $purge_prefix . '-' . $type;
        $return = self::purge_request($request_query, ['method' => 'PURGE']);

        if (!$done_purgesiteall) {
            if ( $run_action ) {
                if (is_multisite()) {
                    foreach (get_sites(array('number' => 500)) as $site) {
                        switch_to_blog($site->blog_id);
                        // remove others cache
                        self::purge_cache_known_plugins();
                        // trigger others plugin
                        do_action('runcloud_purge_nginx_cache');
                        restore_current_blog();
                    }
                }
            }
            $done_purgesiteall = true;
        }

        self::purge_cache_redis_all();

        return $return;
    }

    /**
     * nginx_purge_cache_url.
     */
    private static function nginx_purge_cache_url($url, $wildcard = true, $blocking = true)
    {
        if (self::is_defined_halt()) {
            return false;
        }

        $hostname = parse_url($url, PHP_URL_HOST);

        $type          = self::nginx_purge_type();
        $purge_prefix  = self::$purge_prefix_var;
        $request_query = str_replace($hostname, $hostname . '/' . $purge_prefix . '-' . $type . '/', $url) . ( $wildcard ? '*' : '' );
        $request_query = str_replace($purge_prefix . '-' . $type . '//', $purge_prefix . '-' . $type . '/', $request_query);

        return self::purge_request($request_query, ['method' => 'GET', 'blocking' => $blocking]);
    }

    /**
     * callback_purgecache.
     */
    public static function callback_purgecache()
    {
        if (!self::can_manage_options()) {
            return;
        }

        if (!isset($_GET['type'], $_GET['_wpnonce'], $_GET['action']) ) {
            return;
        }

        $wp_referer = wp_get_referer();
        $get_type   = sanitize_text_field($_GET['type']);
        $get_nonce  = sanitize_text_field($_GET['_wpnonce']);
        $get_action = sanitize_text_field($_GET['action']);

        $type = explode('-', $get_type);
        $type = reset($type);
        $id   = explode('-', $get_type);
        $id   = end($id);

        $aname    = self::nw_hash(self::$slug . '_purge');
        $notadmin = (isset($_GET['__notadmin']) ? true : false);
        $status   = true;

        $req_status = '';

        if (wp_verify_nonce($get_nonce, 'purge_' . $get_type)) {
            if ($aname === $get_action) {
                switch ($type) {
                    case 'all':
                        $req_status = self::purge_cache_all();
                        break;
                    case 'siteall':
                        $req_status = self::purge_cache_all_sites();
                        break;
                    case 'post':
                        self::$is_purge_cache_content = true;
                        $options = self::get_setting();
                        if (!empty($options)) {
                            if (!empty($options['homepage_post_onn'])) {
                                self::$is_purge_cache_home = true;
                            }
                            if (self::$is_purge_cache_home && !empty($options['archives_homepage_onn'])) {
                                self::$is_purge_cache_archive = true;
                            }
                            if (self::$is_purge_cache_content && !empty($options['archives_content_onn'])) {
                                self::$is_purge_cache_archive = true;
                            }
                        }
                        $req_status = self::purge_cache_post($id);
                        break;
                    case 'url':
                        if ('/' === $wp_referer) {
                            $req_status = self::purge_cache_home();
                        } else {
                            $up = $wp_referer;
                            if ('/' === $up[0]) {
                                $up = home_url($up);
                            }
                            $req_status = self::purge_cache_url($up);
                        }
                        break;
                    case 'homepage':
                        $req_status = self::purge_cache_home();
                        break;
                    case 'redis':
                        $req_status = self::purge_cache_redis();
                        break;
                    case 'redisall':
                        $req_status = self::purge_cache_redis_all();
                        break;
                    case 'preload':
                        $status = false;
                        $total = self::get_total_posts();
                        if ($total) {
                            self::wakeup_set(
                                'lookup_preload_run',
                                [
                                    'num'   => 0,
                                    'total' => $total,
                                    'type'  => 'admin',
                                ]
                            );
                        }
                        self::$is_run_preload = false;
                        break;
                    case 'preload_cancel':
                        $status = false;
                        self::wakeup_flush('lookup_preload_run');
                        self::wakeup_flush('preload_process');
                        break;
                    case 'preload_run_admin':
                        $status = false;
                        $buff = self::wakeup_get('lookup_preload_run');
                        if (isset($buff['type'])) {
                            $buff['type'] = 'admin';
                            $buff = self::wakeup_set('lookup_preload_run', $buff);
                        }
                        break;
                    case 'preload_run_background':
                        $status = false;
                        $buff = self::wakeup_get('lookup_preload_run');
                        if (isset($buff['type'])) {
                            $buff['type'] = 'background';
                            $buff = self::wakeup_set('lookup_preload_run', $buff);
                            self::wakeup_queue('run_preload');
                        }
                        break;
                    case 'fetchstats':
                        $req_status = self::rcapi_fetch_stats(true, true);
                        break;
                    case 'installdropin':
                        $req_status = self::reinstall_dropin(true);
                        break;
                    case 'reset':
                        $req_status = self::reinstall_options(false, true);
                        break;
                    case 'switchpurger':
                        $req_status = self::switch_cache_type(true);
                        break;
                    default:
                        $req_status = [
                            'code' => 0,
                            'status' => esc_html__('Failed, no valid action was specified', 'runcloud-hub'),
                        ];
                }
            }
            else {
                $req_status = [
                    'code' => 0,
                    'status' => esc_html__('Failed, no valid action was specified', 'runcloud-hub'),
                ];
            }
        }
        else {
            $req_status = [
                'code' => 0,
                'status' => esc_html__('Failed, security nonce was invalid', 'runcloud-hub'),
            ];
        }

        self::reset_purge_action();

        if ($status) {
            if (empty($req_status)) {
                $req_status = [
                    'code' => 0,
                    'status' => '',
                ];
            }
            set_transient(self::$transientk . '/callback_purgecache', $req_status, 120);
            if ($notadmin) {
                $wp_referer = remove_query_arg('fl_builder', $wp_referer);
                if ($type == 'all' && self::$is_run_preload) {
                    wp_safe_redirect(admin_url('options-general.php?page=' . self::$slug));
                    self::close_exit();
                }
                else {
                    delete_transient(self::$transientk . '/callback_purgecache');
                    wp_safe_redirect(esc_url_raw($wp_referer));
                    self::close_exit();
                }
            }
        }

        wp_safe_redirect(esc_url_raw($wp_referer));
        self::close_exit();
    }

    /**
     * callback_purgecache_notice.
     */
    public static function callback_purgecache_notice()
    {
        $req_status = get_transient(self::$transientk . '/callback_purgecache');
        if (!empty($req_status) && is_array($req_status)) {
            if (200 === $req_status['code'] || 412 === $req_status['code']) {
                $notice_type = 'success';
                if (!empty($req_status['status'])) {
                    $msg = $req_status['status'];
                }
                else {
                    $msg = esc_html__('Success', 'runcloud-hub');
                }
            }
            else {
                $notice_type = 'error';
                if (!empty($req_status['status'])) {
                    $msg = $req_status['status'];
                }
                else {
                    $msg = esc_html__('Unknown Error', 'runcloud-hub');
                }
            }

            if (isset($_GET['_rc_purge_ref'])) {
                $msg .= '.&nbsp; ' . esc_html__('Redirecting in 5 seconds...', 'runcloud-hub');
            }

            echo '<style>';

            echo '</style>';
            echo '<div id="rc-notice" class="notice notice-' . esc_attr($notice_type) . ' is-dismissible">';
            echo '<p>';
            if (!self::is_ourscreen()) {
                echo '<strong>' . esc_html(self::$name) . ':</strong>&nbsp;' . esc_html($msg);
            } else {
                echo '<strong>' . esc_html($msg) . '</strong>';
            }
            if (isset($_GET['_rc_purge_ref'])) {
                echo '<noscript><meta http-equiv="refresh" content="5;url=' . esc_url(home_url('/').$_GET['_rc_purge_ref']) . '"></noscript>';
            }
            echo '</p>';
            echo '</div>';
        }
        delete_transient(self::$transientk . '/callback_purgecache');
    }

    /**
     * callback_deactivate_plugin,
     */
    public static function callback_deactivate_plugin()
    {
        if (!wp_verify_nonce($_GET['_wpnonce'], 'deactivate_plugin')) {
            wp_nonce_ays('');
        }

        deactivate_plugins($_GET['plugin']);
        self::wp_cache_delete();

        wp_safe_redirect(wp_get_referer());
        self::close_exit();
    }

    /**
     * get_post_terms_urls.
     */
    private static function get_post_terms_urls($post_id)
    {
        $urls       = [];
        $taxonomies = get_object_taxonomies(get_post_type($post_id), 'objects');

        foreach ($taxonomies as $taxonomy) {
            if (!$taxonomy->public) {
                continue;
            }

            if (class_exists('WooCommerce')) {
                if ('product_shipping_class' === $taxonomy->name) {
                    continue;
                }
            }

            $terms = get_the_terms($post_id, $taxonomy->name);

            if (!empty($terms)) {
                foreach ($terms as $term) {
                    $term_url = get_term_link($term->slug, $taxonomy->name);
                    if (!is_wp_error($term_url)) {
                        $urls[] = $term_url;
                    }
                }
            }
        }

        return $urls;
    }

    /**
     * get_post_dates_urls.
     */
    private static function get_post_dates_urls($post_id)
    {
        $date       = explode('-', get_the_time('Y-m-d', $post_id));
        $link_year  = trailingslashit(get_year_link($date[0]));
        $link_month = trailingslashit(get_month_link($date[0], $date[1]));
        $link_day   = trailingslashit(get_day_link($date[0], $date[1], $date[2]));
        $urls       = [
            $link_year,
            $link_month,
            $link_day,
        ];

        if (is_object($GLOBALS['wp_rewrite'])) {
            $pagination_base = trailingslashit($GLOBALS['wp_rewrite']->pagination_base);
            $urls[]          = $link_year . $pagination_base;
            $urls[]          = $link_month . $pagination_base;
        }

        return $urls;
    }

    /**
     * purge_cache_redis.
     */
    public static function purge_cache_redis()
    {
        static $done_purgeredis = false;
        $ok                 = false;
        $is_dropin          = self::is_dropin_exists();
        $is_connect         = self::redis_is_connect();

        if ($is_connect) {
            $ok = wp_cache_flush();

            if (self::is_wp_cli()) {
                self::late_purge_cache_redis();
            }

            if ( $ok ) {
                $status = esc_html__('Purge Redis object cache was successful', 'runcloud-hub');
            }
            else {
                $status = esc_html__('Purge Redis object cache failed - Request not found', 'runcloud-hub');
            }
        }
        else {
            $status = esc_html__('Purge Redis object cache failed - Redis is not connected', 'runcloud-hub');
        }

        $return = [
            'code'       => ($ok ? 200 : 404),
            'status'     => $status,
            'is_redis'   => 1,
            'is_dropin'  => ($is_dropin ? 1 : 0),
            'is_connect' => ($is_connect ? 1 : 0),
        ];

        self::debug(__METHOD__, $return);

        if (!$done_purgeredis) {
            // trigger others plugin
            do_action('runcloud_purge_redis_cache');
            $done_purgeredis = true;
        }

        return $return;
    }

    /**
     * purge_cache_redis_all.
     */
    public static function purge_cache_redis_all()
    {
        static $done_purgeredisall = false;
        $ok                 = false;
        $is_dropin          = self::is_dropin_exists();
        $is_connect         = self::redis_is_connect();

        if (self::is_wp_cli() || self::can_manage_network_options()) {
            if (function_exists('wp_cache_flush_sites')) {
                if ($is_connect) {
                    $ok = wp_cache_flush_sites();

                    if (self::is_wp_cli()) {
                        self::late_purge_cache_redis_all();
                    }

                    if ( $ok ) {
                        $status = esc_html__('Purge all sites Redis object cache was successful', 'runcloud-hub');
                    }
                    else {
                        $status = esc_html__('Purge all sites Redis object cache failed - Request not found', 'runcloud-hub');
                    }
                }
                else {
                    $status = esc_html__('Purge all sites Redis object cache failed - Redis server is not connected', 'runcloud-hub');
                }
            }
            else {
                $status = esc_html__('Purge all sites Redis object cache failed - Redis object cache is disabled on main site', 'runcloud-hub');
            }
        }
        else {
            $status = esc_html__('Purge all sites Redis object cache failed - For Super Administrator only', 'runcloud-hub');
        }

        $return = [
            'code'       => ($ok ? 200 : 404),
            'status'     => $status,
            'is_redis'   => 1,
            'is_dropin'  => ($is_dropin ? 1 : 0),
            'is_connect' => ($is_connect ? 1 : 0),
        ];

        self::debug(__METHOD__, $return);

        if (!$done_purgeredisall) {
            // trigger others plugin
            do_action('runcloud_purge_redis_cache_all');
            $done_purgeredisall = true;
        }

        return $return;
    }

    /**
     * flush_feed.
     */
    public static function flush_feed($blocking = true)
    {
        if (self::is_defined_halt()) {
            return;
        }

        $urls   = [];
        $urls[] = get_feed_link();
        $urls[] = get_feed_link('comments_');
        if (!empty($urls)) {
            foreach ($urls as $url) {
                self::nginx_purge_cache_url($url, true, $blocking);
                if ($blocking && self::$is_run_preload) {
                    self::fetch_preload($url, false);
                }
            }
        }
    }

    /**
     * purge_cache_url.
     */
    public static function purge_cache_url($url)
    {
        if (self::is_defined_halt()) {
            return;
        }

        $return = self::nginx_purge_cache_url($url);

        if (self::$is_run_preload) {
            self::fetch_preload($url, false);
        }

        return $return;
    }

    /**
     * purge_cache_home.
     */
    public static function purge_cache_home()
    {
        if (self::is_defined_halt()) {
            return;
        }

        $home_url = home_url('/');
        $home_paged_url = $home_url.'page/';

        $return = self::nginx_purge_cache_url($home_url, false);
        self::nginx_purge_cache_url($home_paged_url, true, false);

        if (self::$is_run_preload) {
            self::fetch_preload($home_url, false);
        }

        return $return;
    }

    /**
     * purge_cache_all.
     */
    public static function purge_cache_all()
    {
        if (self::is_defined_halt()) {
            return;
        }

        $return = self::nginx_purge_all();

        if (self::$is_run_preload) {
            $total = self::get_total_posts();
            if ($total) {
                self::wakeup_set(
                    'lookup_preload_run',
                    [
                        'num'   => 0,
                        'total' => $total,
                        'type'  => 'background',
                    ]
                );
                self::wakeup_queue('run_preload');
            }
        }

        return $return;
    }

    /**
     * purge_cache_all_noaction.
     */
    public static function purge_cache_all_noaction()
    {
        if (self::is_defined_halt()) {
            return;
        }

        // run nginx_purge_all but with run_action false
        // it is dedicated for purger hooks with better plugin integration
        $return = self::nginx_purge_all(null, null, false);

        if (self::$is_run_preload) {
            $total = self::get_total_posts();
            if ($total) {
                self::wakeup_set(
                    'lookup_preload_run',
                    [
                        'num'   => 0,
                        'total' => $total,
                        'type'  => 'background',
                    ]
                );
                self::wakeup_queue('run_preload');
            }
        }

        return $return;
    }

    /**
     * purge_cache_all_sites.
     */
    public static function purge_cache_all_sites()
    {
        if (self::is_defined_halt()) {
            return;
        }

        $return = self::nginx_purge_all_sites();

        return $return;
    }

    /**
     * purge_cache_on_post_status_change.
     */
    public static function purge_cache_on_post_status_change($new_status, $old_status, $post) {
        $post_id = $post->ID;

        // exit early if options is empty
        $options = self::get_setting();
        if (empty($options)) {
            return;
        }

        if ($new_status == 'publish' || $old_status == 'publish' || $new_status == 'future' || $old_status == 'future') {
            // purge post/page/CPT on published/removed
            if (!empty($options['content_publish_onn'])) {
                self::$is_purge_cache_content = true;
            }

            // purge homepage on post updated/added
            if ($new_status != 'trash' && !empty($options['homepage_post_onn'])) {
                self::$is_purge_cache_home = true;
            }

            // purge homepage on post removed
            if ($new_status == 'trash' && !empty($options['homepage_removed_onn'])) {
                self::$is_purge_cache_home = true;
            }

            // purge archives when a purged homepage is triggered
            if (self::$is_purge_cache_home && !empty($options['archives_homepage_onn'])) {
                self::$is_purge_cache_archive = true;
            }

            // purge archives when a purged content is triggered
            if (self::$is_purge_cache_content && !empty($options['archives_content_onn'])) {
                self::$is_purge_cache_archive = true;
            }
        }

        self::purge_cache_post($post_id);
    }

    /**
     * purge_cache_on_comment.
     */
    public static function purge_cache_on_comment($comment_id, $comment) {
        $oldstatus = '';
        $newstatus = $comment->comment_approved === '1' ? 'approved' : 'unapproved';

        self::purge_cache_on_comment_status_change($newstatus, $oldstatus, $comment);
    }

    /**
     * purge_cache_on_comment_status_change.
     */
    public static function purge_cache_on_comment_status_change($newstatus, $oldstatus, $comment) {
        $post_id = $comment->comment_post_ID;

        // exit early if options is empty
        $options = self::get_setting();
        if (empty($options)) {
            return;
        }

        // purge post/page/CPT when a comment is approved
        if ($newstatus == 'approved' && !empty($options['content_comment_approved_onn'])) {
            self::$is_purge_cache_content = true;
        }

        // purge post/page/CPT when a comment is removed
        if ($newstatus != 'approved' && !empty($options['content_comment_removed_onn'])) {
            self::$is_purge_cache_content = true;
        }

        // purge archive when a purged content is triggered
        if (self::$is_purge_cache_content && !empty($options['archives_content_onn'])) {
            self::$is_purge_cache_archive = true;
        }

        self::purge_cache_post($post_id);
    }

    /**
     * purge_cache_post.
     */
    public static function purge_cache_post($post_id)
    {
        if (defined('DOING_AUTOSAVE') || self::is_defined_halt()) {
            return;
        }

        if ( empty($post_id) ) {
            return;
        }

        $post_data = get_post($post_id);
        if (!is_object($post_data)) {
            return;
        }

        self::debug(__METHOD__, ['post_id' => $post_id]);

        if ('auto-draft' === $post_data->post_status
            || empty($post_data->post_type)
            || 'attachment' === $post_data->post_type
            || 'nav_menu_item' === $post_data->post_type) {
            return;
        }

        $post_type = get_post_type_object($post_data->post_type);
        if (!is_object($post_type) || true !== $post_type->public) {
            return;
        }

        $return = '';

        // Purge Post/Page/CPT
        if ( self::$is_purge_cache_content ) {
            $purge_permalink = '';
            $permalink = get_permalink($post_id);
            if (false !== strpos($permalink, '?')) {
                // fix permalink url when status set to trashed
                if (!function_exists('get_sample_permalink')) {
                    include_once ABSPATH . 'wp-admin/includes/post.php';
                }
                $permalink_structure = get_sample_permalink($post_id);
                $permalink           = str_replace(array('%postname%', '%pagename%'), $permalink_structure[1], $permalink_structure[0]);
            }

            if ('/' !== parse_url($permalink, PHP_URL_PATH)) {
                $purge_permalink = str_replace('__trashed/', '/', $permalink);
            }
            unset($permalink);

            if (!empty($purge_permalink)) {
                $return = self::nginx_purge_cache_url($purge_permalink);
                if (self::$is_run_preload) {
                    self::fetch_preload($purge_permalink, false);
                }
            }
        }

        // Purge Homepage
        if ( self::$is_purge_cache_home ) {
            $purge_home = home_url('/');
            self::nginx_purge_cache_url($purge_home, false, false);
            self::nginx_purge_cache_url($purge_home.'page/', true, false);

            // Blog Posts page, if available
            $page_for_posts_id = (int) get_option('page_for_posts');
            if ('post' === $post_data->post_type && $page_for_posts_id > 0) {
                $purge_blog = get_permalink($page_for_posts_id);
                self::nginx_purge_cache_url($purge_blog, true, false);
            }
        }

        // Purge Archive
        if ( self::$is_purge_cache_archive ) {
            $purge_data = [];

            // purge category / tag / custom taxonomy
            $purge_terms = self::get_post_terms_urls($post_id);
            if (!empty($purge_terms) && is_array($purge_terms)) {
                $purge_data = array_merge($purge_data, $purge_terms);
            }

            // purge post type archive link, if available
            if (function_exists('get_post_type_archive_link')) {
                $post_type = $post_data->post_type;
                if ($post_type != 'post' && $post_type != 'page') {
                    $post_type_archive_link = get_post_type_archive_link($post_data->post_type);
                    if ($post_type_archive_link) {
                        $purge_data[] = $post_type_archive_link;
                    }
                }
            }

            // purge author page
            $author_id = $post_data->post_author;
            if ($author_id) {
                $author_link = get_author_posts_url($author_id);
                if ($author_link) {
                    $purge_data[] = $author_link;
                }
            }

            // purge dates page
            // we use wildcard, clear year link will also clear month and day link                
            $year  = get_the_time( 'Y', $post_id );
            if ( $year ) {
                $purge_data[] = get_year_link($year);
            }

            // run purge archive
            if (!empty($purge_data) && is_array($purge_data) && count($purge_data) > 0) {
                foreach ($purge_data as $url) {
                    self::nginx_purge_cache_url($url, true, false);
                }
            }

            // purge feed, additional
            self::flush_feed();
        }

        // Purge URL Path
        if (self::$is_purge_cache_urlpath) {
            $options = self::get_setting();
            if (!empty($options['url_path_onn']) && !empty($options['url_path_mch']) && is_array($options['url_path_mch'])) {
                foreach ($options['url_path_mch'] as $path) {
                    if ('/' === $path) {
                        continue;
                    }
                    $path         = strtolower($path);
                    $purge_url    = home_url(ltrim($path, '/'));
                    self::nginx_purge_cache_url($purge_url, true, false);
                }
            }
        }

        return $return;
    }

    /**
     * upgrader_process_complete_callback.
     */
    public static function upgrader_process_complete_callback($wp_upgrader, $options)
    {
        if (self::is_defined_halt()) {
            return;
        }

        if (is_multisite()) {
            self::purge_cache_all_sites();
        }
        else {
            self::purge_cache_all();
        }
    }

    /**
     * upgrade_plugin.
     */
    public static function upgrade_plugin()
    {
        if (self::is_defined_halt()) {
            return;
        }

        $ver = get_option(self::$db_version);
        if ($ver == self::$version) {
            return;
        }

        self::debug(__METHOD__, sprintf(esc_html__('Upgrade to v%s ', 'runcloud-hub'), self::$version));

        self::clean_setup();

        update_option(self::$db_version, self::$version);
    }

    /**
     * clean_setup.
     */
    public static function clean_setup()
    {
        if (is_multisite()) {
            self::purge_cache_all_sites();
        }
        else {
            self::purge_cache_all();
        }

        self::try_setup_dropin();

        self::rcapi_fetch_stats(false);

        self::rcapi_push();
    }

    /**
     * widget_update_callback.
     */
    public static function widget_update_callback($obj)
    {
        if (self::is_defined_halt()) {
            return;
        }

        self::purge_cache_all();
        return $obj;
    }

    /**
     * purge_woo_product_variation.
     */
    public static function purge_woo_product_variation($variation_id)
    {
        if (self::is_defined_halt()) {
            return;
        }

        $product_id = wp_get_post_parent_id($variation_id);

        if (!empty($product_id)) {
            self::$is_purge_cache_home    = true;
            self::$is_purge_cache_content = true;
            self::$is_purge_cache_archive = true;
            self::$is_purge_cache_urlpath = true;

            self::purge_cache_post($product_id);

            self::reset_purge_action();
            self::wakeup_flush('lookup_woourl');
        }
    }

    /**
     * purge_cache_known_plugins.
     */
    private static function purge_cache_known_plugins()
    {

        // Remove Action Hooks
        remove_action('autoptimize_action_cachepurged', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        remove_action('fvm_after_purge_all', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        remove_action('after_rocket_clean_domain', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        remove_action('swift_performance_after_clear_all_cache', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        remove_action('wpfc_delete_cache', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        remove_action('wp_cache_cleared', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        remove_action('w3tc_flush_all', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        remove_action('w3tc_flush_posts', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        remove_action('hyper_cache_flush_all', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        remove_action('hyper_cache_purged', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        remove_action('wpo_cache_flush', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        remove_action('ce_action_cache_cleared', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);

        // Autoptimize
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall_actionless();
        }

        // Fast Velocity Minify
        if (function_exists('fvm_purge_all')) {
            fvm_purge_all();
        }

        // WPRocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }

        // Swift Performance
        if (class_exists('Swift_Performance_Cache')) {
            Swift_Performance_Cache::clear_all_cache();
        }

        // WP Fastest Cache
        if (class_exists('WpFastestCache')) {
            $wpfc = new WpFastestCache();
            $wpfc->deleteCache();
        }
 
        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            if (is_multisite()) {
                $blog_id = get_current_blog_id();
                wp_cache_clear_cache($blog_id);
            } 
            else {
                wp_cache_clear_cache();
            }
        }

        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        // Hyper Cache
        if (class_exists('HyperCache')) {
            $hypercache = new HyperCache();
            $hypercache->clean();
        }

        // WP Optimize
        if (function_exists('wpo_cache_flush')) {
            wpo_cache_flush();
        }

        // Cache Enabler
        if (class_exists('Cache_Enabler')) {
            Cache_Enabler::clear_total_cache();
        }
    }

    /**
     * register_purge_hooks.
     */
    public static function register_purge_hooks()
    {

        // run plugin upgrader if needed after upgrading plugin
        add_action('admin_init', [__CLASS__, 'upgrade_plugin'], self::$ordlast);

        // clear cache when finish upgrading theme / plugin
        add_action('upgrader_process_complete', [__CLASS__, 'upgrader_process_complete_callback'], self::$ordlast, 2);

        // clear cache when switch to different theme
        add_action('switch_theme', [__CLASS__, 'purge_cache_all'], self::$ordlast);

        // clear cache when permalink structure is updated
        add_action('permalink_structure_changed', [__CLASS__, 'purge_cache_all'], self::$ordlast);
        add_action('update_option_category_base', [__CLASS__, 'purge_cache_all'], self::$ordlast);
        add_action('update_option_tag_base', [__CLASS__, 'purge_cache_all'], self::$ordlast);

        // clear cache when customizer / theme_mods is saved
        add_action('customize_save', [__CLASS__, 'purge_cache_all'], self::$ordlast);
        add_action('update_option_theme_mods_' . get_option('stylesheet'), [__CLASS__, 'purge_cache_all'], self::$ordlast);

        // clear cache when add/remove sidebar widgets
        add_action('update_option_sidebars_widgets', [__CLASS__, 'purge_cache_all'], self::$ordlast);

        // clear cache when update a widget parameter
        add_filter('widget_update_callback', [__CLASS__, 'widget_update_callback'], self::$ordlast);

        // clear cache when update a menu item
        add_action('wp_update_nav_menu', [__CLASS__, 'purge_cache_all'], self::$ordlast);

        // clear cache when register/update/delete user
        add_action('user_register', [__CLASS__, 'purge_cache_all'], self::$ordlast);
        add_action('profile_update', [__CLASS__, 'purge_cache_all'], self::$ordlast);
        add_action('deleted_user', [__CLASS__, 'purge_cache_all'], self::$ordlast);

        // clear cache when create/edit/delete trem
        add_action('create_term', [__CLASS__, 'purge_cache_all'], self::$ordlast);
        add_action('edited_terms', [__CLASS__, 'purge_cache_all'], self::$ordlast);
        add_action('delete_term', [__CLASS__, 'purge_cache_all'], self::$ordlast);

        // clear cache on post status change (publish/future/trash)
        add_action('transition_post_status', [__CLASS__, 'purge_cache_on_post_status_change'], self::$ordlast, 3);

        // clear cache on comment
        add_action('wp_insert_comment', [__CLASS__, 'purge_cache_on_comment'], self::$ordlast, 2);

        // clear cache on comment status change
        add_action('transition_comment_status', [__CLASS__, 'purge_cache_on_comment_status_change'], self::$ordlast, 3);

        // clear cache on saving product variation
        add_action('woocommerce_save_product_variation', [__CLASS__, 'purge_woo_product_variation'], self::$ordlast);

        // Autoptimize
        add_action('autoptimize_action_cachepurged', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);

        // Fast Velocity Minify
        add_action('fvm_after_purge_all', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);

        // WPRocket
        add_action('after_rocket_clean_domain', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);

        // Swift Performance
        add_action('swift_performance_after_clear_all_cache', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);

        // WP Fastest Cache
        add_action('wpfc_delete_cache', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);

        // WP Super Cache
        add_action('wp_cache_cleared', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);

        // W3 Total Cache
        add_action('w3tc_flush_all', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        add_action('w3tc_flush_posts', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);

        // Hyper Cache
        add_action('hyper_cache_flush_all', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        add_action('hyper_cache_purged', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);

        // WP Optimize
        add_action('wpo_cache_flush', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);

        // Cache Enabler
        add_action('ce_action_cache_cleared', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);

        // Beaver Builder
        add_action('fl_builder_cache_cleared', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        add_action('fl_builder_before_save_layout', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
        add_action('fl_builder_after_save_user_template', [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);

        // Custom Purger Hooks via filter
        $purge_action_hooks = apply_filters('runcloud_purge_nginx_cache_hooks', array());
        if (!empty($purge_action_hooks)) {
            foreach ($purge_action_hooks as $purge_action_hook) {
                add_action($purge_action_hook, [__CLASS__, 'purge_cache_all_noaction'], self::$ordlast);
            }
        }

        if (!is_admin()) {
            add_action('init', [__CLASS__, 'lookup_wplogin'], self::$ordfirst);
        }

        if (self::$is_redis_debug) {
            add_action('shutdown', [__CLASS__, 'add_redis_stats'], self::$ordlast);
        }

        if (self::$is_html_footprint) {
            add_action('shutdown', [__CLASS__, 'add_html_footprint'], self::$ordlast);
        }
    }

    /**
     * register_integrations.
     */
    public static function register_integrations()
    {
        // WPRocket
        add_action('rocket_htaccess_mod_expires', [__CLASS__, 'wprocket_remove_html_expire'], 5);
        add_filter('do_rocket_generate_caching_files', '__return_false', self::$ordlast);
        add_filter('rocket_cache_mandatory_cookies', '__return_empty_array', self::$ordlast);
        add_filter('rocket_display_varnish_options_tab', '__return_false');
        add_filter('pre_get_rocket_option_cache_mobile', '__return_true');
        add_filter('pre_get_rocket_option_do_caching_mobile_files', '__return_false');
    }

    /**
     * wprocket_remove_html_expire.
     */
    public static function wprocket_remove_html_expire( $rules ) {
        $rules = preg_replace( '@\s*#\s*Your document html@', '', $rules );
        $rules = preg_replace( '@\s*ExpiresByType text/html\s*"access plus \d+ (seconds|minutes|hour|week|month|year)"@', '', $rules );

        return $rules;
    }

    /**
     * add_html_footprint.
     */
    public static function add_html_footprint() {
        if (is_admin()) {
            return;
        }
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }
        if (defined('WP_CLI') && WP_CLI) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        $footprint = "\n<!-- " . 'RunCloud Hub ' . current_time( 'mysql' ) . " -->";
        echo wp_kses( $footprint, array() );
    }

    /**
     * lookup_reset.
     */
    public static function lookup_reset()
    {
        self::wakeup_flush(['lookup_wplogin', 'lookup_woourl']);
    }

    /**
     * lookup_wplogin.
     */
    public static function lookup_wplogin()
    {
        $request_uri = $_SERVER['REQUEST_URI'];

        $path  = self::wakeup_get(__FUNCTION__);
        $match = false;

        if (!empty($path)) {
            $path = self::nw_decrypt($path);
        } else {
            if (isset($GLOBALS['pagenow']) && 'wp-login.php' === $GLOBALS['pagenow']) {
                $match = true;
            } elseif (isset($_SERVER['PHP_SELF']) && '/wp-login.php' === $_SERVER['PHP_SELF']) {
                $match = true;
            } else {
                $files = get_included_files();
                while ($file = @array_shift($files)) {
                    if (ABSPATH . 'wp-login.php' === $file) {
                        $match = true;
                        break;
                    }

                    if (ABSPATH . 'wp-register.php' === $file) {
                        $match = true;
                        break;
                    }
                }
            }

            if ($match) {
                $path = $request_uri;
                if (false === strpos($path, 'wp-login.php')) {
                    self::wakeup_set(__FUNCTION__, self::nw_encrypt($path));
                }
            }
        }

        return $path;
    }

    /**
     * lookup_woourl.
     */
    public static function lookup_woourl()
    {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_page_id')) {
            return null;
        }

        $__varfunc_sanitize = function ($page_id) {
            if ($page_id <= 0 || (int) get_option('page_on_front') === $page_id) {
                return '';
            }

            if ('publish' !== get_post_status($page_id)) {
                return '';
            }

            return parse_url(get_permalink($page_id), PHP_URL_PATH);
        };

        $path = self::wakeup_get(__FUNCTION__);
        if (empty($path)) {
            $path = [];
            foreach (['checkout', 'cart', 'myaccount'] as $name) {
                $url = $__varfunc_sanitize(wc_get_page_id($name));
                if (!empty($url)) {
                    $path[] = $url;
                }
            }

            $path[] = '/wc-api/v*';
            $path   = array_unique($path);
            self::wakeup_set(__FUNCTION__, $path);
        }

        return $path;
    }

    /**
     * is_exclude_woour.
     */
    private static function is_exclude_woourl($string)
    {
        $path = self::lookup_woourl();

        if (!empty($path) && self::match_filter($path, $string)) {
            return true;
        }

        return false;
    }

    /**
     * is_exclude_url.
     */
    private static function is_exclude_url($string)
    {
        $options = self::get_setting();
        if (!empty($options['exclude_url_onn']) && !empty($options['exclude_url_mch'])) {
            return self::match_filter($options['exclude_url_mch'], $string);
        }
        return false;
    }

    /**
     * get_total_posts.
     */
    private static function get_total_posts()
    {
        $post_types = get_post_types(['public' => true]);
        $post_types = array_filter($post_types, 'is_post_type_viewable');

        $count_posts = 0;
        foreach ( $post_types as $post_type ) {
            $count = wp_count_posts($post_type);
            $count_posts += $count->publish;
        }
        return $count_posts;
    }

    /**
     * get_preload_urls.
     */
    private static function get_preload_urls($num, $inc = 1)
    {
        if (!$inc) {
            $inc = 1;
        }

        $urls = [];
        $post_types = get_post_types(['public' => true]);
        $post_types = array_filter($post_types, 'is_post_type_viewable');
        $args = array(
            'fields'              => 'ids',
            'posts_per_page'      => $inc,
            'post_type'           => $post_types,
            'offset'              => $num,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
        );
        $posts = get_posts($args);
        if (!empty($posts)) {
            foreach ($posts as $post_id) {
                $url = get_permalink($post_id);
                if ($url) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    /**
     * get_preload_speed.
     */
    private static function get_preload_speed()
    {
        $options = self::get_setting();
        $preload_speed = intval( $options['preload_speed'] );
        if (!$preload_speed) {
            $preload_speed = 60;
        }
        elseif ($preload_speed > 600) {
            $preload_speed = 600;
        }
        return $preload_speed;
    }

    /**
     * get_preload_inc.
     */
    private static function get_preload_inc()
    {
        $preload_speed = self::get_preload_speed();
        return ceil($preload_speed/60);
    }

    /**
     * get_preload_interval.
     */
    private static function get_preload_interval()
    {
        $preload_speed = self::get_preload_speed();
        $inc = ceil($preload_speed/60);
        $batch = $preload_speed/$inc;
        $interval = 60/$batch*1000;
        $interval = intval($interval)-250;
        return $interval;
    }

    /**
     * match_wildcard.
     */
    public static function match_wildcard($match, $string)
    {
        if (false === strpos($match, '*')) {
            return (false !== strpos($string, $match) ? true : false);
        } else {
            $wildcard_chars = ['\*', '\?'];
            $regexp_chars   = ['.*', '.'];
            $regex          = str_replace($wildcard_chars, $regexp_chars, preg_quote($match, '/'));
            if (preg_match('/^' . $regex . '$/is', $string)) {
                return true;
            }
        }

        return false;
    }

    /**
     * match_rules.
     */
    public static function match_filter($rules, $string)
    {
        if (is_object($rules)) {
            $rules = json_decode(json_encode($rules), true);
        }

        if (!empty($rules) && is_array($rules)) {
            foreach ($rules as $match) {
                if (self::match_wildcard($match, $string)) {
                    return true;
                }
            }
            return false;
        }

        return self::match_wildcard($rules, $string);
    }

    /**
     * deactivate_runcache_purger.
     */
    public static function maybe_deactivate_runcache_purger()
    {
        $file = 'runcache-purger/runcache-purger.php';

        if (self::is_plugin_active($file)) {
            deactivate_plugins($file);
            self::wp_cache_delete();
            return true;
        }

        return false;
    }

    /**
     * late_purge_cache_redis.
     */
    private static function late_purge_cache_redis()
    {
        self::debug(__METHOD__, 'event run');

        add_action(
            'shutdown',
            function () {
                wp_cache_flush();
            },
            self::$ordlast
        );
    }

    /**
     * late_purge_cache_redis_all.
     */
    private static function late_purge_cache_redis_all()
    {
        self::debug(__METHOD__, 'event run');

        add_action(
            'shutdown',
            function () {
                if (function_exists('wp_cache_flush_sites')) {
                    wp_cache_flush_sites();
                } else {
                    wp_cache_flush();
                }
            },
            self::$ordlast
        );
    }

    /**
     * get_schedule_name.
     */
    private static function get_schedule_name($num)
    {
        foreach (wp_get_schedules() as $name => $arr) {
            if (false !== strpos($name, self::$slug . '-')) {
                if ((int) $num === (int) $arr['interval']) {
                    return $name;
                }
            }
        }
        return null;
    }

    /**
     * callback_cron_purge.
     */
    public static function callback_cron_purge()
    {
        self::debug(__METHOD__, 'event run');
        self::purge_cache_all();
    }

    /**
     * schedule_cron_purge.
     */
    private static function schedule_cron_purge($reset = 0)
    {
        $hook_name = 'cron_purge_' . self::nw_hash(self::$slug . 'cron_purge');

        if (!empty($reset)) {
            wp_clear_scheduled_hook($hook_name);
            if (1 === (int) $reset) {
                return true;
            }
        }

        $options = self::get_setting();
        if (!empty($options['schedule_purge_onn'])) {
            $schedule_name = self::get_schedule_name($options['schedule_purge_unt']);
            $duration      = (time() + (int) $options['schedule_purge_var']);
            if (!wp_next_scheduled($hook_name)) {
                return wp_schedule_event($duration, $schedule_name, $hook_name);
            }

            return false;
        }

        return wp_clear_scheduled_hook($hook_name);
    }

    /**
     * callback_cron_preload.
     */
    public static function callback_cron_preload()
    {
        self::debug(__METHOD__, 'event run');
        $total = self::get_total_posts();
        if ($total) {
            self::wakeup_set(
                'lookup_preload_run',
                [
                    'num'   => 0,
                    'total' => $total,
                    'type'  => 'background',
                ]
            );
            self::run_preload();
        }
    }

    /**
     * callback_cron_rcstats.
     */
    public static function callback_cron_rcstats()
    {
        self::debug(__METHOD__, 'event run');
        self::rcapi_fetch_stats(true);
    }

    /**
     * schedule_cron_preload.
     */
    private static function schedule_cron_preload($reset = 0)
    {
        $hook_name = 'cron_preload_' . self::nw_hash(self::$slug . 'cron_preload');

        if (!empty($reset)) {
            wp_clear_scheduled_hook($hook_name);
            if (1 === (int) $reset) {
                return true;
            }
        }

        $options = self::get_setting();
        if (!empty($options['preload_schedule_onn'])) {
            $schedule_name = self::get_schedule_name($options['preload_schedule_unt']);
            $duration      = (time() + (int) $options['preload_schedule_var']);
            if (!wp_next_scheduled($hook_name)) {
                return wp_schedule_event($duration, $schedule_name, $hook_name);
            }

            return false;
        }

        return wp_clear_scheduled_hook($hook_name);
    }

    /**
     * schedule_cron_rcstats.
     */
    private static function schedule_cron_rcstats($reset = 0)
    {
        $hook_name = 'cron_rcstats_' . self::nw_hash(self::$slug . 'cron_rcstats');

        if (!empty($reset)) {
            wp_clear_scheduled_hook($hook_name);
            if (1 === (int) $reset) {
                return true;
            }
        }

        $options = self::get_setting();
        if (!empty($options['stats_onn']) && !empty($options['stats_schedule_onn'])) {
            $schedule_name = self::get_schedule_name($options['stats_schedule_unt']);
            $duration      = (time() + (int) $options['stats_schedule_var']);
            if (!wp_next_scheduled($hook_name)) {
                return wp_schedule_event($duration, $schedule_name, $hook_name);
            }

            return false;
        }

        return wp_clear_scheduled_hook($hook_name);
    }

    /**
     * register_cron_hooks.
     */
    private static function register_cron_hooks($install = true)
    {
        if ($install) {
            self::schedule_cron_purge();
            self::schedule_cron_preload();
            self::schedule_cron_rcstats();
        } else {
            self::schedule_cron_purge(1);
            self::schedule_cron_preload(1);
            self::schedule_cron_rcstats(1);
        }
    }

    /**
     * wakeup_call.
     */
    private static function wakeup_call($name, $pong)
    {
        if (self::wakeup_check($name)) {
            self::debug(
                __METHOD__,
                [
                    'pong' => $pong,
                    'run'  => $name,
                ]
            );
            self::wakeup_flush($name);
            return true;
        }
        return false;
    }

    /**
     * callback_wakeup_pong.
     */
    public static function callback_wakeup_pong()
    {
        if (!isset($_GET['_wpnonce'], $_GET['_wakeup'])) {
            self::debug(__METHOD__, esc_html__('Invalid request', 'runcloud-hub'));
            self::close_exit(esc_html__('Invalid request', 'runcloud-hub'));
        }

        if (!wp_verify_nonce($_GET['_wpnonce'], 'wakeup' . $_GET['_wakeup'])) {
            self::debug(__METHOD__, esc_html__('Invalid nonce', 'runcloud-hub'));
            self::close_exit(esc_html__('Invalid nonce', 'runcloud-hub'));
        }

        echo "request accepted";
        self::fastcgi_close();

        $pong = sanitize_text_field($_GET['_wakeup']);
        self::debug(__METHOD__, ['pong' => $pong]);

        // rcapi push
        if (self::wakeup_call('rcapi_push', $pong)) {
            add_action('shutdown', [__CLASS__, 'rcapi_push'], self::$ordlast, 0);
        }

        // redis
        if (self::wakeup_call('wp_cache_flush', $pong)) {
            add_action(
                'shutdown',
                function () {
                    wp_cache_flush();
                },
                self::$ordlast
            );
        }

        // redis flush sites
        if (self::wakeup_call('wp_cache_flush_sites', $pong)) {
            add_action(
                'shutdown',
                function () {
                    if (function_exists('wp_cache_flush_sites')) {
                        wp_cache_flush_sites();
                    }
                    else {
                        wp_cache_flush();
                    }
                },
                self::$ordlast
            );
        }

        // redis flush db
        if (self::wakeup_call('wp_cache_flush_db', $pong)) {
            add_action(
                'shutdown',
                function () {
                    if (function_exists('wp_cache_flush_db')) {
                        wp_cache_flush_db();
                    } else {
                        wp_cache_flush();
                    }
                },
                self::$ordlast
            );
        }

        // preload
        if (self::wakeup_call('run_preload', $pong)) {
            add_action('shutdown', [__CLASS__, 'run_preload'], self::$ordlast);
        }

        self::close_exit();
    }

    /**
     * callback_wakeup_ping.
     */
    public static function callback_wakeup_ping()
    {
        if ( !is_admin() ) {
            return;
        }
        if ( defined('DOING_AUTOSAVE') ) {
            return;
        }
        if ( self::is_ajax() ) {
            return;
        }
        if ( self::is_wp_cli() ) {
            return;
        }
        if ( self::is_fl_builder() ) {
            return;
        }

        if (self::wakeup_check('wakeup_ping')) {
            self::wakeup_flush('wakeup_ping');

            $time = time();
            $url  = admin_url('admin-post.php?action=' . self::nw_hash(self::$slug . '_queue') . '&_wakeup=' . $time);
            $url  = add_query_arg('_wpnonce', wp_create_nonce('wakeup' . $time), $url);

            echo '<script type="text/javascript" id="' .esc_attr(self::$slug) . '-wakeup-send">';
            echo '(function(){';
            echo 'var u1="'.parse_url($url,PHP_URL_HOST).'";';
            echo 'if ( location.hostname === u1 ) {';
            if (self::is_debug()) {
                echo 'console.log("wakeup_ping: ' . esc_url_raw($url) . '");';
            }
            echo 'var c=new XMLHttpRequest();';
            echo 'c.open("GET","' . esc_url_raw($url) . '",true);';
            echo 'c.timeout=60000;';
            echo 'c.send();';
            echo '}';
            echo '})();';
            echo '</script>';

            self::debug(__METHOD__, ['ping' => $url]);
        }
    }

    /**
     * view_stats_charts.
     */
    public static function view_stats_charts($name)
    {
        $stats = [
            'type' => 'daily',
            'data' => [],
        ];

        if (!in_array($name, array('health','transfer'))) {
            return $stats;
        }

        $options = self::get_setting();
        if (!empty($options['stats_onn']) && !empty($options['stats_'.$name . '_onn'])) {
            if ($name === 'health') {
                $stats['type'] = in_array($options['stats_health_var'], array('daily','hourly')) ? $options['stats_health_var'] : 'hourly';
            }
            elseif ($name === 'transfer') {
                $stats['type'] = in_array($options['stats_transfer_var'], array('daily','monthly')) ? $options['stats_transfer_var'] : 'daily';
            }

            $data = self::rcapi_get_stats();
            if (!empty($data)) {
                if (!empty($data[$name][$stats['type']])) {
                    $stats['data'] = $data[$name][$stats['type']];
                }
            }
        }

        return $stats;
    }

    /**
     * view_stats_lastupdate.
     */
    public static function view_stats_lastupdate()
    {
        $data = get_option(self::$db_stats);
        if (!empty($data) && !empty($data['lastupdate'])) {
            $lastupdate = $data['lastupdate'];
            $time = human_time_diff(strtotime($lastupdate));
            if ($time) {
                $lastupdate .= sprintf(esc_html__(' (%s ago)', 'runcloud-hub'), $time);
            }
            return $lastupdate;
        }
        return '-';
    }

    /**
     * __shutdown.
     */
    private static function __shutdown()
    {
        add_action(
            'shutdown',
            function () {
                self::wp_cache_delete();
                if ( !self::$onfirehooks ) {
                    wp_cache_flush();
                }
                if (function_exists('wp_cache_self_remove') && (defined('RCWP_REDIS_DROPIN') || defined('RUNCACHE_PURGER_DROPIN'))) {
                    wp_cache_self_remove();
                }
            },
            self::$ordlast
        );
    }

    /**
     * activate.
     */
    public static function activate($network_wide)
    {
        self::$onfirehooks = true;
        self::install_options($network_wide);
        self::wakeup_remove();
        self::maybe_deactivate_runcache_purger();
        self::try_setup_dropin();
    }

    /**
     * deactivate.
     */
    public static function deactivate($network_wide)
    {
        self::$onfirehooks = true;
        self::force_site_deactivate_plugin();
        self::uninstall_dropin(true);
        self::wakeup_remove();
        self::__shutdown();
    }

    /**
     * uninstall.
     */
    public static function uninstall($network_wide)
    {
        self::$onfirehooks = true;
        self::uninstall_options($network_wide);
        self::uninstall_dropin(true);
        self::wakeup_remove();
        self::__shutdown();
    }

    /**
     * register_hook.
     */
    public static function register_plugin_hooks()
    {
        register_activation_hook(RUNCLOUD_HUB_HOOK, [__CLASS__, 'activate']);
        register_deactivation_hook(RUNCLOUD_HUB_HOOK, [__CLASS__, 'deactivate']);
        register_uninstall_hook(RUNCLOUD_HUB_HOOK, [__CLASS__, 'uninstall']);
    }

    /**
     * reinstall_options.
     */
    public static function reinstall_options($all_site = false, $status = false)
    {
        self::debug(__METHOD__, 'event run');

        if (self::is_main_site()) {
            $rcapi_key = self::get_setting('rcapi_key');
            $rcapi_secret = self::get_setting('rcapi_secret');
            $rcapi_webapp_id = self::get_setting('rcapi_webapp_id');
        }

        self::uninstall_options($all_site);
        self::install_options($all_site);

        if (self::is_main_site()) {
            self::update_setting_var('rcapi_key', $rcapi_key);
            self::update_setting_var('rcapi_secret', $rcapi_secret);
            self::update_setting_var('rcapi_webapp_id', $rcapi_webapp_id);
        }

        self::clean_setup();

        if ($status) {
            $req_status = [
                'code' => 200,
                'status' => esc_html__('Reset settings was successful.', 'runcloud-hub'),
            ];
            return $req_status;
        }
    }

    /**
     * reinstall_dropin.
     */
    public static function reinstall_dropin($status = false)
    {
        return self::install_dropin(true, $status);
    }

    /**
     * register_wpcli_hooks.
     */
    public static function register_wpcli_hooks()
    {
        if (self::is_wp_cli() && !class_exists('RunCloud_Hub_CLI')) {
            require_once RUNCLOUD_HUB_PATH_LIB . 'wp-cli.php';
            WP_CLI::add_command('runcloud-hub', esc_html__('RunCloud_Hub_CLI', 'runcloud-hub'), ['shortdesc' => esc_html__('Manages RunCloud Hub WordPress Plugin', 'runcloud-hub')]);
            WP_CLI::add_command('runcache-purger', esc_html__('RunCloud_Hub_CLI', 'runcloud-hub'), ['shortdesc' => esc_html__('RunCache Purger for RunCloud Hub', 'runcloud-hub')]);
        }
    }

    /**
     * array_merge_r.
     */
    private static function array_merge_r()
    {
        if (func_num_args() < 2) {
            trigger_error(__FUNCTION__ . ' invalid input', E_USER_WARNING);
            return;
        }

        $arrays = func_get_args();
        $merged = [];

        while ($array = @array_shift($arrays)) {
            if (!is_array($array)) {
                trigger_error(__FUNCTION__ . ' invalid input', E_USER_WARNING);
                return;
            }

            if (empty($array)) {
                continue;
            }

            foreach ($array as $key => $value) {
                if (is_string($key)) {
                    if (is_array($value) && array_key_exists($key, $merged) && is_array($merged[$key])) {
                        $merged[$key] = self::array_merge_r($merged[$key], $value);
                    } else {
                        $merged[$key] = $value;
                    }
                } else {
                    $merged[] = $value;
                }
            }
        }
        return $merged;
    }

    /**
     * get_fileperms.
     */
    private static function get_fileperms($type)
    {
        static $perms = [];

        $type = (string) $type;

        if (isset($perms[$type])) {
            return $perms[$type];
        }

        if ('dir' === $type) {
            if (defined('FS_CHMOD_DIR')) {
                $perms[$type] = FS_CHMOD_DIR;
            } else {
                clearstatcache();
                $perms[$type] = fileperms(ABSPATH) & 0777 | 0755;
            }

            return $perms[$type];
        } elseif ('file' === $type) {
            if (defined('FS_CHMOD_FILE')) {
                $perms[$type] = FS_CHMOD_FILE;
            } else {
                clearstatcache();
                $perms[$type] = fileperms(ABSPATH . 'index.php') & 0777 | 0644;
            }

            return $perms[$type];
        }

        return 0755;
    }

    /**
     * filesystem.
     */
    public static function filesystem()
    {
        static $inst = null;

        if (is_object($inst)) {
            return $inst;
        }

        if (!class_exists('WP_Filesystem_Base')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        }

        if (!class_exists('WP_Filesystem_Direct')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        }

        $inst = new WP_Filesystem_Direct(new StdClass());
        return $inst;
    }

    /**
     * nw_wakeup.
     */
    public static function nw_wakeup($filename, $type, $string = '')
    {
        if (is_string($string)) {
            $string = preg_replace('@\?.*@', '', $string);
        }

        $savepath = self::$cache_path;

        if (strtolower(__FUNCTION__) === $filename && 'remove' === $type) {
            if (is_dir($savepath)) {
                if ('/' !== $savepath && constant('WP_CONTENT_DIR') !== $savepath) {
                    self::filesystem()->rmdir($savepath, true);
                }
            }
            return true;
        }

        if (!file_exists($savepath)) {
            if (!wp_mkdir_p($savepath)) {
                return false;
            }

            @chmod($savepath, self::get_fileperms('dir'));
            @touch($savepath . 'index.html');
        }

        if ( '___network' === substr($filename, 0, 10) ) {
            $filesave = $savepath . md5(self::$version . $filename . 'globals').'txt';
        } else {
            $filesave = $savepath . md5(self::$version . $filename . self::remove_protocol(get_site_url())) . '.txt';
        }

        if ('match' === $type || 'get' === $type) {
            if (is_file($filesave) && is_readable($filesave)) {
                clearstatcache(true, $filesave);

                $data = @file_get_contents($filesave);
                if (is_serialized($data)) {
                    $data = @unserialize($data);
                }

                if ('get' === $type) {
                    return $data;
                } elseif ('match' === $type) {
                    $fname = trim($data);
                    if (!empty($fname) && !empty($string)) {
                        return (md5($string) === md5($fname));
                    }
                    return false;
                }
            }

        } elseif ('save' === $type) {
            $string = maybe_serialize($string);
            if ( @file_put_contents($filesave, $string, LOCK_EX) ) {
                @chmod($filesave, self::get_fileperms('file'));
                return true;
            }
            return false;

        } elseif ('clear' === $type) {
            if (file_exists($filesave)) {
                return @unlink($filesave);
            }
        } elseif ('check' === $type) {
            clearstatcache(true, $filesave);
            return @file_exists($filesave);

        } elseif ('savelog' === $type) {
            return self::debug_log($string, $filesave);
        }

        return false;
    }

    /**
     * wakeup_set.
     */
    public static function wakeup_set($filename, $string = null)
    {
        if (null === $string) {
            $string = $filename;
        }
        return self::nw_wakeup($filename, 'save', $string);
    }

    /**
     * wakeup_get.
     */
    public static function wakeup_get($filename)
    {
        return self::nw_wakeup($filename, 'get');
    }

    /**
     * wakeup_flush.
     */
    public static function wakeup_flush($filename)
    {
        $ok = false;
        if (is_array($filename) && !empty($filename)) {
            foreach ($filename as $fname) {
                $ok1 = self::nw_wakeup($fname, 'clear');
                if ($ok1) {
                    $ok = true;
                }
            }
        } else {
            $ok = self::nw_wakeup($filename, 'clear');
        }
        return $ok;
    }

    /**
     * wakeup_check.
     */
    public static function wakeup_check($filename)
    {
        return self::nw_wakeup($filename, 'check');
    }

    /**
     * wakeup_remove.
     */
    public static function wakeup_remove()
    {
        return self::nw_wakeup('nw_wakeup', 'remove');
    }

    /**
     * wakeup_queue.
     */
    public static function wakeup_queue($filename)
    {
        if (!empty($filename) && is_array($filename)) {
            foreach ($filename as $name) {
                self::debug(__METHOD__, ['add' => $name]);
                self::wakeup_set($name);
            }
        } else {
            self::debug(__METHOD__, ['add' => $filename]);
            self::wakeup_set($filename);
        }

        self::debug(__METHOD__, ['run' => 'wakeup_ping']);
        self::wakeup_set('wakeup_ping');
    }

    /**
     * fetch_preload.
     */
    private static function fetch_preload($url, $blocking = true)
    {
        $args = [
            'timeout'     => $blocking ? 10 : 0.01,
            'redirection' => 5,
            'httpversion' => '1.1',
            'user-agent'  => self::get_user_agent(),
            'blocking'    => $blocking,
            'body'        => null,
            'compress'    => false,
            'decompress'  => true,
            'sslverify'   => false,
            'stream'      => false,
            'filename'    => null,
        ];

        $results = [
            'blocking' => $blocking,
            'code'     => '',
            'status'   => '',
            'host'     => parse_url($url, PHP_URL_HOST),
            'url'      => $url,
            'method'   => 'GET',
            'error'    => '',
        ];

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            $results['code']   = wp_remote_retrieve_response_code($response);
            $results['status'] = wp_remote_retrieve_response_message($response);
            $results['error']  = wp_strip_all_tags($response->get_error_message());
        } else {
            $results['code']   = wp_remote_retrieve_response_code($response);
            $results['status'] = wp_remote_retrieve_response_message($response);
            $results['header'] = (is_object($response['headers']) ? (array) $response['headers'] : null);
        }

        self::debug(__METHOD__, $results);
        return (empty($results['error']) ? true : false);
    }

    /**
     * run_preload.
     */
    public static function run_preload()
    {
        self::fastcgi_close();

        $buff = self::wakeup_get('lookup_preload_run');
        if (empty($buff)) {
            return;
        }

        if (!isset($buff['type'])) {
            return;
        }
        if ($buff['type'] !== 'background') {
            return;
        }

        if (!isset($buff['num'])) {
            return;
        }
        $num = (int) $buff['num'];

        if (!isset($buff['total'])) {
            return;
        }
        $total = (int) $buff['total'];

        if (!$total || ($total && $num > $total)) {
            self::wakeup_flush('lookup_preload_run');
            self::wakeup_flush('preload_process');
            return;
        }

        $pid = getmypid();
        self::wakeup_set('preload_process', $pid);

        if ($num == 0) {
            // preload homepage
            $preload_url = home_url('/');
            self::fetch_preload($preload_url, false);
            // preload path
            $options = self::get_setting();
            if (!empty($options['preload_path_onn']) && !empty($options['preload_path_mch']) && is_array($options['preload_path_mch'])) {
                foreach ($options['preload_path_mch'] as $path) {
                    if ('/' === $path) {
                        continue;
                    }
                    $path    = strtolower($path);
                    $preload_url = home_url('/') . ltrim($path, '/');
                    self::fetch_preload($preload_url, false);
                }
            }
        }

        self::debug(__METHOD__, sprintf( esc_html__('PID%s Background Preload START %s / %s', 'runcloud-hub'), $pid, $num, $total ) );

        $finish = true;
        while ($num <= $total) {
            $oldpid = self::wakeup_set('preload_process', getmypid());
            if (!$oldpid) {
                $finish = false;
                self::debug(__METHOD__, sprintf( esc_html__('Background Preload STOP : %s', 'runcloud-hub'), esc_html__('PID is missing', 'runcloud-hub') ) );
                break;
            }
            $newpid = getmypid();
            if ($oldpid != $newpid) {
                $finish = false;
                self::debug(__METHOD__, sprintf( esc_html__('Background Preload STOP : %s', 'runcloud-hub'), esc_html__('PID is different', 'runcloud-hub') ) );
                break;
            }
            $pid = $newpid;

            $buff = self::wakeup_get('lookup_preload_run');
            if (empty($buff)) {
                $finish = false;
                self::debug(__METHOD__, sprintf( esc_html__('Background Preload STOP : %s', 'runcloud-hub'), esc_html__('No preload run', 'runcloud-hub') ) );
                break;
            }

            if (!isset($buff['type'])) {
                $finish = false;
                self::debug(__METHOD__, sprintf( esc_html__('Background Preload STOP : %s', 'runcloud-hub'), esc_html__('No preload type', 'runcloud-hub') ) );
                break;
            }
            if ($buff['type'] !== 'background') {
                $finish = false;
                self::debug(__METHOD__, sprintf( esc_html__('Background Preload STOP : %s', 'runcloud-hub'), esc_html__('Not background preload', 'runcloud-hub') ) );
                break;
            }

            if (!isset($buff['num'])) {
                $finish = false;
                self::debug(__METHOD__, sprintf( esc_html__('Background Preload STOP : %s', 'runcloud-hub'), esc_html__('No preload number', 'runcloud-hub') ) );
                break;
            }
            $num = (int) $buff['num'];

            if (!isset($buff['total'])) {
                $finish = false;
                self::debug(__METHOD__, sprintf( esc_html__('Background Preload STOP : %s', 'runcloud-hub'), esc_html__('No preload total', 'runcloud-hub') ) );
                break;
            }
            $total = (int) $buff['total'];

            if (!$total || ($total && $num > $total)) {
                $finish = false;
                self::debug(__METHOD__, sprintf( esc_html__('Background Preload STOP : %s', 'runcloud-hub'), esc_html__('Preload total is invalid', 'runcloud-hub') ) );
                self::wakeup_flush('lookup_preload_run');
                self::wakeup_flush('preload_process');
                break;
            }

            $inc = self::get_preload_inc();
            $interval = self::get_preload_interval();
            $interval_s = round($interval/1000);
            $urls = self::get_preload_urls($num, $inc);

            self::wakeup_set(
                'lookup_preload_run',
                [
                    'num'   => $num + $inc,
                    'total' => $total,
                    'type'  => 'background',
                ]
            );

            if (!empty($urls)) {
                $num_fetch = $num;
                foreach ($urls as $url) {
                    $num_fetch++;
                    self::debug(__METHOD__, sprintf( esc_html__('PID%s Background Preload %s / %s : %s', 'runcloud-hub'), $pid, $num_fetch, $total, $url ) );
                    self::fetch_preload($url, false);
                }
            }

            $num = $num + $inc;
            sleep($interval_s);
        }

        if ($finish) {
            self::wakeup_flush('lookup_preload_run');
            self::wakeup_flush('preload_process');
            self::debug(__METHOD__, sprintf( esc_html__('PID%s Background Preload STOP %s / %s', 'runcloud-hub'), $pid, $num, $total ) );
        }
    }

    /**
     * nw_hash.
     */
    public static function nw_hash($string, $len = 12)
    {
        $str = @md5($string);
        if ( strlen($str) > $len ) {
            return @substr($str, 0, $len);
        }
        return $str;
    }

    /**
     * nw_encrypt.
     */
    public static function nw_encrypt($string, $epad = '!!$$sampoernahijau!!')
    {
        $mykey     = '!!$' . $epad . '!!';
        $pad       = base64_decode($mykey);
        $encrypted = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $encrypted .= @chr(ord($string[$i]) ^ ord($pad[$i]));
        }
        return strtr(base64_encode($encrypted), '=/', '-_');
    }

    /**
     * nw_decrypt.
     */
    public static function nw_decrypt($string, $epad = '!!$$sampoernahijau!!')
    {
        $mykey     = '!!$' . $epad . '!!';
        $pad       = base64_decode($mykey);
        $encrypted = base64_decode(strtr($string, '-_', '=/'));
        $decrypted = '';
        for ($i = 0; $i < strlen($encrypted); $i++) {
            $decrypted .= @chr(ord($encrypted[$i]) ^ ord($pad[$i]));
        }
        return $decrypted;
    }

    /**
     * is_debug.
     */
    private static function is_debug()
    {
        return (defined('RUNCLOUD_HUB_DEBUG') && RUNCLOUD_HUB_DEBUG);
    }

    /**
     * debug.
     */
    private static function debug($caller, $data)
    {
        if (!self::is_debug()) {
            return false;
        }

        $log = [
            'timestamp' => gmdate('Y-m-d H:i:s') . ' UTC',
            'caller'    => $caller,
        ];

        if (!empty($data) && is_array($data)) {
            $log = self::array_merge_r($log, $data);
        } else {
            $log['status'] = $data;
        }

        return self::debug_log($log);
    }

    /**
     * array_export.
     */
    public static function array_export($data)
    {
        $data_e = var_export($data, true);
        $data_e = str_replace('Requests_Utility_CaseInsensitiveDictionary::__set_state(', '', $data_e);

        $data_e = preg_replace('/^([ ]*)(.*)/m', '$1$1$2', $data_e);
        $data_r = preg_split("/\r\n|\n|\r/", $data_e);

        $data_r = preg_replace(['/\s*array\s\($/', '/\)(,)?$/', '/\s=>\s$/'], [null, ']$1', ' => ['], $data_r);
        return join(PHP_EOL, array_filter(['['] + $data_r));
    }

    /**
     * debug_log.
     */
    private static function debug_log($data, $filesave = '')
    {
        if (empty($filesave)) {
            $fname    = str_replace(' ', '_', self::$slug);
            $filesave = WP_CONTENT_DIR . '/' . $fname . '.log';
        }

        if (is_dir($filesave)) {
            return false;
        }

        if (!empty($data) && (is_array($data) || is_object($data))) {
            $data = str_replace('\\u0000', '', str_replace('\\u0000*', '', json_encode($data)));
            $data = json_decode($data, true);
            if (isset($data['header']['data'])) {
                $h              = $data['header']['data'];
                $data['header'] = $h;
                unset($h);
            }
        }

        if (!empty($data) && is_array($data)) {
            $data = str_replace('\\u0000', '', str_replace('\\u0000*', '', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))) . ',';
        } else {
            $data = trim($data);
        }

        $output = $data . PHP_EOL;

        $perm = self::get_fileperms('file');
        $is_file_exists = file_exists($filesave);

        if ( $is_file_exists ) {
            @chmod($filesave, $perm);
        }

        if (@file_put_contents($filesave, $output, FILE_APPEND|LOCK_EX)) {
            if ( !$is_file_exists ) {
                @chmod($filesave, $perm);
            }
            return true;
        }

        return false;
    }

    /**
     * wpcli_nginx_purge_all.
     */
    public static function wpcli_nginx_purge_all($type = null, $host_url = null)
    {
        $res = [];

        if ( $host_url ) {
            // reset
            $proto    = parse_url($host_url, PHP_URL_SCHEME);
            $hostname = parse_url($host_url, PHP_URL_HOST);

            // maybe port 443 is open. Then check if we should use the https proto instead
            if (self::is_wp_ssl() && 'https' !== $proto && @fsockopen('tls://' . $hostname, 443)) {
                $proto = 'https';
            }
        }
        else {
            $proto = null;
        }

        return self::nginx_purge_all($type, $proto);
    }

    /**
     * wpcli_nginx_purge_all_sites.
     */
    public static function wpcli_nginx_purge_all_sites($type = null, $host_url = null)
    {
        $res = [];

        if ( $host_url ) {
            // reset
            $proto    = parse_url($host_url, PHP_URL_SCHEME);
            $hostname = parse_url($host_url, PHP_URL_HOST);

            // maybe port 443 is open. Then check if we should use the https proto instead
            if (self::is_wp_ssl() && 'https' !== $proto && @fsockopen('tls://' . $hostname, 443)) {
                $proto = 'https';
            }
        }
        else {
            $proto = null;
        }

        return self::nginx_purge_all_sites($type, $proto);
    }

    /**
     * rcapi_endpoint.
     */
    private static function rcapi_endpoint()
    {
        static $lists;

        if (is_object($lists)) {
            return $lists;
        }

        $options         = self::get_setting();
        $rcapi_key       = $options['rcapi_key'];
        $rcapi_secret    = $options['rcapi_secret'];
        $rcapi_webapp_id = $options['rcapi_webapp_id'];

        // get key/secret from main site
        if (is_multisite() && !self::is_main_site()) {
            switch_to_blog(self::get_main_site_id());
            $options_site = self::get_setting();
            $rcapi_key    = $options_site['rcapi_key'];
            $rcapi_secret = $options_site['rcapi_secret'];
            restore_current_blog();
            unset($options_site);
        }

        $rcapi_url = (defined('RUNCLOUD_HUB_ENDPOINT') ? RUNCLOUD_HUB_ENDPOINT : self::$endpoint);

        $lists = [
            'url'       => $rcapi_url,
            'auth'      => base64_encode($rcapi_key . ':' . $rcapi_secret),
            'webapp_id' => $rcapi_webapp_id,
        ];

        $lists = (object) $lists;
        return $lists;
    }

    /**
     * rcapi_fetch.
     */
    public static function rcapi_fetch($url)
    {
        $args = [
            'timeout'     => 10,
            'httpversion' => '1.1',
            'user-agent'  => self::get_user_agent(),
            'blocking'    => true,
            'headers'     => ['Authorization' => 'Basic ' . self::rcapi_endpoint()->auth],
            'body'        => null,
            'compress'    => false,
            'decompress'  => true,
            'sslverify'   => false,
            'stream'      => false,
            'filename'    => null,
        ];

        $results = [
            'code'   => '',
            'status' => '',
            'host'   => parse_url($url, PHP_URL_HOST),
            'url'    => $url,
            'method' => 'GET',
        ];

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            $results['code']    = wp_remote_retrieve_response_code($response);
            $results['status']  = sprintf(esc_html__('Fetching server stats failed - %s', 'runcloud-hub'), wp_strip_all_tags($response->get_error_message()));
            $results['content'] = '';
        } else {
            $results['code']    = wp_remote_retrieve_response_code($response);
            if ($results['code'] == 200) {
                $results['status']  = esc_html__('Fetching server stats was successful', 'runcloud-hub');
                $results['content'] = $response['body'];
            }
            else {
                $error = 'Unknown';
                $content = json_decode($response['body'], true);
                if (isset($content['message']) && $content['message']) {
                    $error = $content['message'];
                }
                $results['status']  = sprintf(esc_html__('Fetching server stats failed - %s', 'runcloud-hub'), $error);
            }
            $results['header']  = (is_object($response['headers']) ? (array) $response['headers'] : null);
        }

        self::debug(__METHOD__, $results);
        return $results;
    }

    /**
     * rcapi_fetch_stats.
     */
    public static function rcapi_fetch_stats($update = false, $status = false)
    {
        $data = [];

        self::debug(__METHOD__, 'event run');

        // only can fetch stats from main site if multisite
        if (!self::is_main_site() || self::is_subdirectory()) {
            $message = sprintf(esc_html__('Only can fetch stats from main site. Current: %s', 'runcloud-hub'), get_site_url());
            self::debug(__METHOD__, $message);
            if ($status) {
                return array(
                    'code' => 0,
                    'status' => sprintf(esc_html__('Fetching server stats failed - %s', 'runcloud-hub'), $message),
                );
            }
            return $data;
        }

        if (!$update) {
            if (get_option(self::$db_stats)) {
                $message = esc_html__('Stats is not empty, no need to fetch new stats data', 'runcloud-hub');
                self::debug(__METHOD__, $message);
                if ($status) {
                    return array(
                        'code' => 200,
                        'status' => sprintf(esc_html__('Fetching server stats failed - %s', 'runcloud-hub'), $message),
                    );
                }
                return $data;
            }
        }

        $options = self::get_setting();
        if (empty($options['rcapi_key']) || empty($options['rcapi_secret']) || empty($options['rcapi_webapp_id'])) {
            $message = esc_html__('Missing API Key / Secret / WebApp ID', 'runcloud-hub');
            self::debug(__METHOD__, $message);
            if ($status) {
                return array(
                    'code' => 0,
                    'status' => sprintf(esc_html__('Fetching server stats failed - %s', 'runcloud-hub'), $message),
                );
            }
            return $data;
        }

        $ep = self::rcapi_endpoint();

        $success = false;
        if (!empty($ep->webapp_id) && false === strpos($ep->url, '{{PANELURL}}')) {
            $type  = in_array($options['stats_health_var'], array('daily','hourly')) ? $options['stats_health_var'] : 'hourly';
            $url   = $ep->url . '/webapps/' . $ep->webapp_id . '/server/health/periodic?key=' . $type;
            $fetch = self::rcapi_fetch($url);
            if (!empty($fetch['code']) && $fetch['code'] == 200 && !empty($fetch['content'])) {
                $data['health'][$type] = json_decode($fetch['content'], true);
                $success = true;
            }

            $type  = in_array($options['stats_transfer_var'], array('daily','monthly')) ? $options['stats_transfer_var'] : 'daily';
            $url   = $ep->url . '/webapps/' . $ep->webapp_id . '/stats/transfer?key=' . $type;
            $fetch = self::rcapi_fetch($url);
            if (!empty($fetch['code']) && $fetch['code'] == 200 && !empty($fetch['content'])) {
                $data['transfer'][$type] = json_decode($fetch['content'], true);
                $success = true;
            }

            if ($success) {
                $data['lastupdate'] = gmdate('Y-m-d H:i:s') . ' UTC';
                update_option(self::$db_stats, $data);
            }
        }
        else {
            if ($status) {
                $message = esc_html__('Endpoint not configured properly', 'runcloud-hub');
                return array(
                    'code' => 0,
                    'status' => sprintf(esc_html__('Fetching server stats failed - %s', 'runcloud-hub'), $message),
                );
            }
        }

        if ($status) {
            return array(
                'code' => $fetch['code'],
                'status' => $fetch['status'],
            );
        }

        return $data;
    }

    /**
     * rcapi_get_stats.
     */
    public static function rcapi_get_stats()
    {
        $data = get_option(self::$db_stats);
        if (!empty($data) && is_array($data)) {
            return $data;
        }
        return [];
    }

    /**
     * rcapi_push.
     */
    public static function rcapi_push(&$message = '')
    {
        $req_status = '';

        // only update from main site if multisite
        if (!self::is_main_site() || self::is_subdirectory()) {
            $message = sprintf( esc_html__('Only can update from main site. Current: %s', 'runcloud-hub'), get_site_url() );
            self::debug(__METHOD__, $message);
            return false;
        }

        $options = self::get_setting();

        if (empty($options['rcapi_key']) || empty($options['rcapi_secret']) || empty($options['rcapi_webapp_id'])) {
            $message = esc_html__('Missing API Key / Secret / WebApp ID', 'runcloud-hub');
            self::debug(__METHOD__, $message);
            if (!self::is_wp_cli()) {
                $req_status = array(
                    'code' => 0,
                    'status' => sprintf(esc_html__('Updating cache rules to the server failed - %s', 'runcloud-hub'), $message),
                );
                set_transient(self::$transientk . '/callback_purgecache', $req_status, 120);
            }
            return false;
        }

        if (!empty($options) && is_array($options)) {
            $data = [];

            if ( empty($options['exclude_url_onn']) || empty( $options['exclude_url_mch'] ) ) {
                $options['exclude_url_mch'] = array();
            }

            if ( !in_array('/wp-admin/', $options['exclude_url_mch'] ) ) {
                $options['exclude_url_mch'][] = '/wp-admin/';
            }
            if ( !in_array('wp-.*.php', $options['exclude_url_mch'] ) ) {
                $options['exclude_url_mch'][] = 'wp-.*.php';
            }
            if ( !in_array('index.php', $options['exclude_url_mch'] ) ) {
                $options['exclude_url_mch'][] = 'index.php';
            }
            if ( !in_array('/.well-known.*', $options['exclude_url_mch'] ) ) {
                $options['exclude_url_mch'][] = '/.well-known.*';
            }

            $data['exclude']['uri']        = implode("\n", $options['exclude_url_mch']);

            if ( empty($options['exclude_cookie_onn']) || empty( $options['exclude_cookie_mch'] ) ) {
                $options['exclude_cookie_mch'] = array();
            }

            if ( !in_array('wordpress_[a-f0-9]+', $options['exclude_cookie_mch'] ) ) {
                $options['exclude_cookie_mch'][] = 'wordpress_[a-f0-9]+';
            }
            if ( !in_array('wordpress_logged_in', $options['exclude_cookie_mch'] ) ) {
                $options['exclude_cookie_mch'][] = 'wordpress_logged_in';
            }
            if ( !in_array('wordpress_no_cache', $options['exclude_cookie_mch'] ) ) {
                $options['exclude_cookie_mch'][] = 'wordpress_no_cache';
            }
            if ( !in_array('wp-postpass', $options['exclude_cookie_mch'] ) ) {
                $options['exclude_cookie_mch'][] = 'wp-postpass';
            }

            $data['exclude']['cookie']     = implode("\n", $options['exclude_cookie_mch']);

            $data['exclude']['browser']    = (!empty($options['exclude_browser_onn']) && !empty($options['exclude_browser_mch']) ? implode("\n", $options['exclude_browser_mch']) : '');
            $data['exclude']['visitor_ip'] = (!empty($options['exclude_visitorip_onn']) && !empty($options['exclude_visitorip_mch']) ? implode("\n", $options['exclude_visitorip_mch']) : '');

            $data['query_string']['exclude'] = (!empty($options['exclude_query_onn']) && !empty($options['exclude_query_mch']) ? implode("\n", $options['exclude_query_mch']) : '');
            $data['query_string']['include'] = (!empty($options['allow_query_onn']) && !empty($options['allow_query_mch']) ? implode("\n", $options['allow_query_mch']) : '');

            $data['cache_key']['extra'] = (!empty($options['cache_key_extra_onn']) && !empty($options['cache_key_extra_var']) ? trim($options['cache_key_extra_var']) : '');

            $ep = self::rcapi_endpoint();

            if (!empty($ep->webapp_id) && false === strpos($ep->url, '{{PANELURL}}')) {
                $url = $ep->url . '/webapps/' . $ep->webapp_id . '/wordpress/runcloud-hub/runcache-exclusion';

                $args = [
                    'timeout'     => 10,
                    'httpversion' => '1.1',
                    'user-agent'  => self::get_user_agent(),
                    'blocking'    => true,
                    'headers'     => ['Authorization' => 'Basic ' . self::rcapi_endpoint()->auth],
                    'body'        => $data,
                    'compress'    => false,
                    'decompress'  => true,
                    'sslverify'   => false,
                    'stream'      => false,
                    'filename'    => null,
                ];

                $results = [
                    'code'   => '',
                    'status' => '',
                    'host'   => parse_url($url, PHP_URL_HOST),
                    'url'    => $url,
                    'method' => 'POST',
                    'error'  => '',
                    'param'  => [
                        'Authorization' => $args['headers']['Authorization'],
                        'data'          => $args['body'],
                    ],
                ];

                $response = wp_remote_post($url, $args);
                if (is_wp_error($response)) {
                    $results['code']   = wp_remote_retrieve_response_code($response);
                    $results['status'] = wp_remote_retrieve_response_message($response);
                    $results['error']  = wp_strip_all_tags($response->get_error_message());
                } else {
                    $results['code']   = wp_remote_retrieve_response_code($response);
                    $results['status'] = wp_remote_retrieve_response_message($response);
                    $results['header'] = (is_object($response['headers']) ? (array) $response['headers'] : null);
                    if ($results['code'] !== 200) {
                        $response_body = wp_remote_retrieve_body($response);
                        $response_body = json_decode($response_body);
                        $results['error'] = !empty($response_body->message) ? $response_body->message : $results['status'];
                    }
                }

                $message = $results;
                self::debug(__METHOD__, $message);
                if (empty($results['error']) && 200 === $results['code']) {
                    if (!self::is_wp_cli()) {
                        $req_status = array(
                            'code' => $results['code'],
                            'status' => esc_html__('Updating cache rules to the server was successful', 'runcloud-hub'),
                        );
                        set_transient(self::$transientk . '/callback_purgecache', $req_status, 120);
                    }
                    return true;
                }
                else {
                    if (!self::is_wp_cli()) {
                        $req_status = array(
                            'code' => $results['code'],
                            'status' => sprintf(esc_html__('Updating cache rules to the server failed - %s', 'runcloud-hub'), $message['error']),
                        );
                        set_transient(self::$transientk . '/callback_purgecache', $req_status, 120);
                    }
                    return false;
                }
            }

            $message = ['status' => esc_html__('Endpoint not configured properly', 'runcloud-hub'), 'endpoint' => (array) $ep];
            self::debug(__METHOD__, $message);
            if (!self::is_wp_cli()) {
                $req_status = array(
                    'code' => 0,
                    'status' => sprintf(esc_html__('Updating cache rules to the server failed - %s', 'runcloud-hub'), $message['status']),
                );
                set_transient(self::$transientk . '/callback_purgecache', $req_status, 120);
            }
            return false;
        }

        return false;
    }

    /**
     * get_magic_users.
     */
    public static function get_magic_users()
    {
        $__varfunc_getuser = function ($blog_id) {
            $args = [
                'role__in' => ['administrator', 'author', 'editor'],
                'order'    => 'ASC',
            ];

            $results = [];
            $list    = get_users($args);
            if (!empty($list) && is_array($list)) {
                foreach ($list as $num => $arr) {
                    if (!empty($arr->data->deleted)) {
                        continue;
                    }

                    $results[$num]['magiclink']  = (!empty(self::get_setting('rcapi_magiclink_onn')) ? true : false);
                    $results[$num]['blog_id']    = $blog_id;
                    $results[$num]['user_id']    = $arr->data->ID;
                    $results[$num]['user_login'] = $arr->data->user_login;
                    $results[$num]['user_email'] = $arr->data->user_email;
                    $results[$num]['user_role']  = implode(',', $arr->roles);
                    $results[$num]['url']        = get_site_url();
                    $results[$num]['domain']     = wp_parse_url(get_site_url(), PHP_URL_HOST);
                }
            }
            return $results;
        };

        $results = [];
        if (is_multisite()) {
            foreach (get_sites(array('number' => 500)) as $site) {
                switch_to_blog($site->blog_id);
                if (self::is_plugin_active(self::$hook)) {
                    $data    = $__varfunc_getuser($site->blog_id);
                    $results = array_merge($data, $results);
                }
                restore_current_blog();
            }
        } else {
            $blog_id = get_current_blog_id();
            $results = $__varfunc_getuser($blog_id);
        }

        return $results;
    }

    /**
     * get_magic_user_session.
     */
    public static function get_magic_user_session($user_id)
    {
        return get_user_meta($user_id, 'session_tokens', true);
    }

    /**
     * remove_magic_user_session.
     */
    public static function remove_magic_user_session($user_id)
    {
        return delete_user_meta($user_id, 'session_tokens');
    }

    /**
     * remove_magic_user_session_all.
     */
    public static function remove_magic_user_session_all()
    {
        $list = self::get_magic_users();
        if (!empty($list) && is_array($list)) {
            while ($row = @array_shift($list)) {
                $user_id = $row['user_id'];
                self::remove_magic_user_session($user_id);
            }
        }
    }

    /**
     * remove_magic_user_token.
     */
    public static function remove_magic_user_token()
    {
        $list = self::get_magic_users();
        if (!empty($list) && is_array($list)) {
            while ($row = @array_shift($list)) {
                $user_id = $row['user_id'];
                delete_user_meta($user_id, 'magic_link_token');
            }
        }
    }

    /**
     * generate_magic_link.
     */
    public static function generate_magic_link($user_id, $blog_id)
    {
        $url = get_site_url($blog_id, '/?' . self::$magictoken . '=');

        $token  = wp_hash($url . microtime(true) . $user_id . $blog_id, 'auth');
        $url    = $url . $token;
        $expiry = strtotime('+5 minutes');
        $data   = [
            'token'    => $token,
            'expiry'   => $expiry,
            'expiry_h' => gmdate('Y-m-d H:i:s') . ' UTC',
        ];

        self::remove_magic_user_token();
        add_user_meta($user_id, 'magic_link_token', $data, true);
        return [
            'url'    => $url,
            'expiry' => $data['expiry_h'],
        ];
    }

    /**
     * callback_magiclink_login.
     */
    public static function callback_magiclink_login()
    {
        if (!empty($_GET[self::$magictoken])) {
            $is_expired    = false;
            $is_found      = false;
            $found_user_id = null;

            $token = sanitize_text_field($_GET[self::$magictoken]);
            $list  = self::get_magic_users();
            if (!empty($list) && is_array($list)) {
                while ($row = @array_shift($list)) {
                    $user_id = (int) $row['user_id'];
                    $data    = get_user_meta($user_id, 'magic_link_token');

                    if (!empty($data) && is_array($data)) {
                        $data = $data[0];
                        self::debug(__METHOD__, $data);
                        if (!empty($data['token']) && strval($token) === strval($data['token'])) {
                            $is_found      = true;
                            $found_user_id = $user_id;
                            if (!empty($data['expiry']) && time() >= (int) $data['expiry']) {
                                $is_expired = true;
                                self::debug(
                                    __METHOD__,
                                    [
                                        'token'  => $token,
                                        'status' => 'expired',
                                        'data'   => $data,
                                    ]
                                );
                            }
                            break;
                        }
                    }
                }
            }

            if ($is_found && !is_null($found_user_id)) {
                $user_id = $found_user_id;
                delete_user_meta($user_id, 'magic_link_token');

                if ($is_expired) {
                    wp_die( esc_html__('Magic Link: link has expired', 'runcloud-hub'), self::$slug);
                }

                self::remove_magic_user_session($user_id);
                $user_object = get_user_by('id', $user_id);
                wp_set_auth_cookie($user_object->ID, false);
                do_action('wp_login', $user_object->data->user_login, $user_object);
                wp_redirect(admin_url('/'));
                self::close_exit();
            }

            self::debug(
                __METHOD__,
                [
                    'token'  => $token,
                    'status' => 'not exist',
                ]
            );
            wp_die( esc_html__('Magic Link: Invalid token', 'runcloud-hub'), self::$slug);
        }
    }

    /**
     * register_update.
     */
    private static function register_update()
    {
        if ('{{CURRENTVERSION}}' === self::$version || false !== strpos(self::$trunk, '{{PANELURL}}')) {
            return false;
        }

        $__varfunc_check_update = function ($res, $action, $args) {
            if ('plugin_information' !== $action) {
                return false;
            }

            if (self::$slug !== $args->slug) {
                return false;
            }

            $transient_key = self::$transientk . '/checkupdate';
            if (false == $remote = get_transient($transient_key)) {
                $remote = wp_remote_get(
                    self::$trunk,
                    [
                        'timeout' => 10,
                        'headers' => [
                            'Accept' => 'application/json',
                        ],
                    ]
                );

                if (!is_wp_error($remote) && isset($remote['response']['code']) && 200 === $remote['response']['code'] && !empty($remote['body'])) {
                    set_transient($transient_key, $remote, 43200); // 12 hours
                }
            }

            if (!is_wp_error($remote) && isset($remote['response']['code']) && 200 === $remote['response']['code'] && !empty($remote['body'])) {
                $remote = json_decode($remote['body']);
                $res    = new stdClass;

                $res->name     = $remote->name;
                $res->slug     = self::$slug;
                $res->version  = $remote->version;
                $res->tested   = $remote->tested;
                $res->requires = $remote->requires;
                $res->author   = $remote->author;

                if (!empty($remote->author_profile)) {
                    $res->author_profile = $remote->author_profile;
                }

                $res->download_link = $remote->download_url;
                $res->trunk         = $remote->download_url;
                $res->requires_php  = $remote->requires_php;
                $res->last_updated  = $remote->last_updated;

                $res->sections                 = [];
                $res->sections['description']  = $remote->sections->description;
                $res->sections['installation'] = $remote->sections->installation;

                if (!empty($remote->sections->changelog)) {
                    $res->sections['changelog'] = $remote->sections->changelog;
                }

                if (!empty($remote->sections->screenshots)) {
                    $res->sections['screenshots'] = $remote->sections->screenshots;
                }

                $res->banners         = [];
                $res->banners['low']  = $remote->banners->low;
                $res->banners['high'] = $remote->banners->high;

                return $res;
            }

            return false;
        };

        $__varfunc_push_update = function ($transient) {
            if (empty($transient->checked)) {
                return $transient;
            }

            $transient_key = self::$transientk . '/checkupdate';

            if (false == $remote = get_transient($transient_key)) {
                $remote = wp_remote_get(
                    self::$trunk,
                    [
                        'timeout' => 10,
                        'headers' => [
                            'Accept' => 'application/json',
                        ],
                    ]
                );

                if (!is_wp_error($remote) && isset($remote['response']['code']) && 200 === $remote['response']['code'] && !empty($remote['body'])) {
                    set_transient($transient_key, $remote, 43200); // 12 hours
                }
            }

            if ($remote && !is_wp_error($remote) && isset($remote['response']['code']) && 200 === $remote['response']['code'] && !empty($remote['body'])) {
                $remote = json_decode($remote['body']);
                if (is_object($remote) && version_compare(self::$version, $remote->version, '<') && version_compare($remote->requires, get_bloginfo('version'), '<')) {
                    $res              = new stdClass();
                    $res->id          = $remote->id;
                    $res->slug        = self::$slug;
                    $res->plugin      = self::$hook;
                    $res->new_version = $remote->version;
                    $res->tested      = $remote->tested;
                    $res->package     = $remote->download_url;
                    $res->url         = $remote->url;
                    $res->icons['1x'] = $remote->icon1;
                    if (isset($remote->icon2)) {
                        $res->icons['svg'] = $remote->icon2;
                    }
                    $transient->response[$res->plugin] = $res;
                }
            }

            return $transient;
        };

        add_filter('plugins_api', $__varfunc_check_update, 20, 3);
        add_filter('site_transient_update_plugins', $__varfunc_push_update);
    }

    /**
     * attach.
     */
    public static function attach()
    {
        self::register_init();
        self::register_cron_schedules();
        self::register_plugin_hooks();
        self::register_admin_hooks();
        self::register_purge_hooks();
        self::register_integrations();
        self::register_update();
    }
}

RunCloud_Hub::attach();
