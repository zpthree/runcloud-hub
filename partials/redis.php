<?php defined('RUNCLOUD_HUB_INIT') || exit;?>

<!-- redis-cache -->
<?php if(self::redis_can_enable()) : ?>
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="redis" data-tab-page-title="<?php esc_html_e('Redis', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold leading-tight text-base-1000"><?php esc_html_e('Redis Object Cache', 'runcloud-hub');?></h3>
    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>
            <!-- enable -->
            <li class="border-b-0" data-action-css-id="redis_cache_onn" data-action-css="border-b-0">
                <div class="form-checkbox-setting">
                    <input type="checkbox" id="redis_cache_onn" name="<?php self::view_fname('redis_cache_onn');?>" value="1" <?php self::view_checked('redis_cache_onn');?>>
                    <?php if ( is_multisite() && !(defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL) ): ?>
                        <label class="control-label" for="redis_cache_onn"><?php esc_html_e('Enable Redis Object Cache For All Sites', 'runcloud-hub');?></label>
                    <?php else : ?>
                        <label class="control-label" for="redis_cache_onn"><?php esc_html_e('Enable Redis Object Cache', 'runcloud-hub');?></label>
                    <?php endif; ?>
                </div>
                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Enable this option to allow this plugin handle Redis object cache.', 'runcloud-hub');?></p>

                <?php if ( is_multisite() && is_network_admin() ): ?>
                    <?php if(defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL) : ?>
                        <div class="form-checkbox-setting mt-4 ml-8">
                            <input type="checkbox" data-action="showhide" id="redis_cache_net_onn" name="<?php self::view_fname('redis_cache_net_onn');?>" value="1">
                            <label class="control-label" for="redis_cache_net_onn"><?php esc_html_e('Apply To All Network Sites', 'runcloud-hub');?></label>
                        </div>
                    <?php else : ?>
                        <input type="hidden" id="redis_cache_net_onn" name="<?php self::view_fname('redis_cache_net_onn');?>" value="0">
                    <?php endif; ?>
                <?php endif; ?>
            </li>
            <!-- /enable -->
        </ul>
    </fieldset>
</div>
<?php endif; ?>
<!-- /redis-cache -->

<!-- redis-server -->
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="redis" data-tab-page-title="<?php esc_html_e('Redis', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold leading-tight text-base-1000"><?php esc_html_e('Redis Object Cache Status', 'runcloud-hub');?></h3>
    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>
            <!-- status -->
            <li>
                <div class="pb-2 font-bold"><?php esc_html_e('Status', 'runcloud-hub');?></div>
                <?php if ( self::redis_is_enabled() ): ?>
                    <?php if ( self::is_dropin_exists() ):?>
                        <?php if( self::is_dropin_hub() ): ?>
                            <?php if ( defined('RCWP_REDIS_DISABLED') && RCWP_REDIS_DISABLED ): ?>
                                <p class="text-red-800"><?php esc_html_e('Object cache is disabled via RCWP_REDIS_DISABLED constant.', 'runcloud-hub');?></p>
                            <?php else: ?>
                                <?php if ( self::is_dropin_valid() ):?>
                                    <?php if ( self::is_dropin_config_need_update() ): ?>
                                        <p class="text-red-800"><?php esc_html_e('Object cache should be enabled for this site, but redis site config drop-in file is not valid and needs update.', 'runcloud-hub');?></p>
                                    <?php else: ?>
                                        <?php if ( self::redis_is_connect() ): ?>
                                            <p class="text-green-800"><?php esc_html_e('Object cache is enabled for this site.', 'runcloud-hub');?></p>
                                        <?php else: ?>
                                            <p class="text-red-800"><?php esc_html_e('Object cache is not active because Redis is not connected.', 'runcloud-hub');?></p>
                                            <?php if ( self::redis_is_enabled() && self::redis_can_enable() ): ?>
                                                <p class="text-red-800"><?php esc_html_e('Please disable Redis object cache option to completely disable object cache.', 'runcloud-hub');?></p>
                                            <?php endif;?>
                                        <?php endif;?>
                                    <?php endif;?>
                                <?php else: ?>
                                    <p class="text-red-800"><?php esc_html_e('Object cache is enabled for this site but drop-in file needs update.', 'runcloud-hub');?></p>
                                <?php endif;?>
                            <?php endif;?>
                        <?php else: ?>
                            <p class="text-red-800"><?php esc_html_e('Unknown object cache drop-in file. Object cache is handled by other plugin.', 'runcloud-hub');?></p>
                        <?php endif;?>
                    <?php else: ?>
                        <?php if ( defined('RUNCLOUD_HUB_INSTALL_DROPIN') && !RUNCLOUD_HUB_INSTALL_DROPIN ): ?>
                            <p class="text-red-800"><?php esc_html_e('Object cache drop-in is not installed because RUNCLOUD_HUB_INSTALL_DROPIN constant is false.', 'runcloud-hub');?></p>
                        <?php else: ?>
                            <p class="text-red-800"><?php esc_html_e('Object cache drop-in has not been installed.', 'runcloud-hub');?></p>
                        <?php endif;?>
                    <?php endif;?>
                <?php else: ?>
                    <?php if ( self::is_dropin_exists() && self::is_dropin_hub() && !(defined('RCWP_REDIS_DISABLED') && RCWP_REDIS_DISABLED) && self::is_dropin_config_need_update() ): ?>
                        <p class="text-green-800"><?php esc_html_e('Object cache is still enabled for this site.', 'runcloud-hub');?></p>
                        <p class="text-red-800"><?php esc_html_e('Redis site config drop-in file needs update to disable object cache for this site.', 'runcloud-hub');?></p>
                    <?php else: ?>
                        <p class="text-red-800"><?php esc_html_e('Object cache is disabled for this site.', 'runcloud-hub');?></p>
                    <?php endif;?>
                <?php endif;?>

                <?php if ( self::is_dropin_need_install() ): ?>
                    <?php if (self::is_main_site()) : ?>
                        <p class="pt-2"><a href="<?php self::view_purge_link('installdropin');?>#redis" class="button"><?php esc_html_e('Install Object Cache Drop-in', 'runcloud-hub'); ?></a></p>
                    <?php else : ?>
                        <p class="text-red-800"><?php esc_html_e('Please contact super administrator of this website to fix this issue.', 'runcloud-hub');?></p>
                    <?php endif; ?>
                <?php elseif ( self::is_dropin_need_update() ): ?>
                    <?php if (self::is_main_site()) : ?>
                        <p class="pt-2"><a href="<?php self::view_purge_link('installdropin');?>#redis" class="button"><?php esc_html_e('Update Object Cache Drop-in', 'runcloud-hub'); ?></a></p>
                    <?php else : ?>
                        <p class="text-red-800"><?php esc_html_e('Please contact super administrator of this website to fix this issue.', 'runcloud-hub');?></p>
                    <?php endif; ?>
                <?php elseif ( self::is_dropin_need_replace() ): ?>
                    <?php if (self::is_main_site()) : ?>
                        <p class="pt-2"><a href="<?php self::view_purge_link('installdropin');?>#redis" class="button"><?php esc_html_e('Replace Object Cache Drop-in', 'runcloud-hub'); ?></a></p>
                    <?php else : ?>
                        <p class="text-red-800"><?php esc_html_e('Please contact super administrator of this website to fix this issue.', 'runcloud-hub');?></p>
                    <?php endif; ?>
                <?php elseif ( self::is_dropin_config_need_update() ): ?>
                    <?php if (self::is_main_site()) : ?>
                        <p class="pt-2"><a href="<?php self::view_purge_link('installdropin');?>#redis" class="button"><?php esc_html_e('Fix Redis Site Config Drop-in', 'runcloud-hub'); ?></a></p>
                    <?php else : ?>
                        <p class="text-red-800"><?php esc_html_e('Please contact super administrator of this website to fix this issue.', 'runcloud-hub');?></p>
                    <?php endif; ?>
                <?php endif;?>

            </li>
            <!-- /status -->

            <?php if ( self::is_main_site() && self::is_dropin_valid() && self::redis_is_connect() ): ?>
            <li class="leading-loose">
                <?php
                global $wp_object_cache;
                $redis_client = '';
                if ( isset( $wp_object_cache->diagnostics[ 'client' ] ) ) {
                    $redis_client = $wp_object_cache->diagnostics[ 'client' ];
                }
                ?>
                <span class="font-bold"><?php esc_html_e('Client', 'runcloud-hub');?></span> : <code><?php echo esc_html( $redis_client ); ?></code>

                <?php if ( method_exists( $wp_object_cache, 'redis_version' ) ) : ?>
                    <br/><span class="font-bold"><?php esc_html_e('Redis Version', 'runcloud-hub');?></span> : <code><?php echo esc_html( $wp_object_cache->redis_version() ); ?></code>
                <?php endif;?>

                <?php if ( property_exists( $wp_object_cache, 'diagnostics' ) ) : $diagnostics = $wp_object_cache->diagnostics; ?>
                    <?php if ( ! empty( $diagnostics['host'] ) ) : ?>
                        <br/><span class="font-bold"><?php esc_html_e('Host', 'runcloud-hub');?></span> : <code><?php echo esc_html( $diagnostics['host'] ); ?></code>
                    <?php endif; ?>

                    <?php if ( isset( $diagnostics['cluster'] ) && is_array( $diagnostics['cluster'] ) ) : ?>
                        <br/><span class="font-bold"><?php esc_html_e('Cluster', 'runcloud-hub');?></span> : <code><?php echo esc_html(implode( ', ', $diagnostics['cluster'] )); ?></code>
                    <?php endif; ?>

                    <?php if ( isset( $diagnostics['shards'] ) && is_array( $diagnostics['shards'] ) ) : ?>
                        <br/><span class="font-bold"><?php esc_html_e('Shards', 'runcloud-hub');?></span> : <code><?php echo esc_html(implode( ', ', $diagnostics['shards'] )); ?></code>
                    <?php endif; ?>

                    <?php if ( isset( $diagnostics['servers'] ) && is_array( $diagnostics['servers'] ) ) : ?>
                        <br/><span class="font-bold"><?php esc_html_e('Servers', 'runcloud-hub');?></span> : <code><?php echo esc_html(implode( ', ', $diagnostics['servers'] )); ?></code>
                    <?php endif; ?>

                    <?php if ( ! empty( $diagnostics['port'] ) ) : ?>
                        <br/><span class="font-bold"><?php esc_html_e('Port', 'runcloud-hub');?></span> : <code><?php echo esc_html( $diagnostics['port'] ); ?></code>
                    <?php endif; ?>

                    <?php if ( isset( $diagnostics['password'][0] ) ) : ?>
                        <br/><span class="font-bold"><?php esc_html_e('Username', 'runcloud-hub');?></span> : <code><?php echo esc_html( $diagnostics['password'][0] ); ?></code>
                    <?php endif; ?>

                    <?php if ( isset( $diagnostics['password'] ) ) : ?>
                        <br/><span class="font-bold"><?php esc_html_e('Password', 'runcloud-hub');?></span> : <code><?php echo str_repeat( '&#8226;', 8 ); ?></code>
                    <?php endif; ?>

                    <?php if ( isset( $diagnostics['database'] ) ) : ?>
                        <br/><span class="font-bold"><?php esc_html_e('Database', 'runcloud-hub');?></span> : <code><?php echo esc_html( $diagnostics['database'] ); ?></code>
                    <?php endif; ?>

                    <?php if ( isset( $diagnostics['timeout'] ) ) : ?>
                        <br/><span class="font-bold"><?php esc_html_e('Connection Timeout', 'runcloud-hub');?></span> : <code><?php echo esc_html( $diagnostics['timeout'] ); ?>s</code>
                    <?php endif; ?>

                    <?php if ( isset( $diagnostics['read_timeout'] ) ) : ?>
                        <br/><span class="font-bold"><?php esc_html_e('Read Timeout', 'runcloud-hub');?></span> : <code><?php echo esc_html( $diagnostics['read_timeout'] ); ?>s</code>
                    <?php endif; ?>

                    <?php if ( isset( $diagnostics['retry_interval'] ) ) : ?>
                        <br/><span class="font-bold"><?php esc_html_e('Retry Interval', 'runcloud-hub');?></span> : <code><?php echo esc_html( $diagnostics['retry_interval'] ); ?>ms</code>
                    <?php endif; ?>

                    <?php if ( defined( 'RCWP_REDIS_MAXTTL' ) ) : ?>
                        <br/><span class="font-bold"><?php esc_html_e('Max TTL', 'runcloud-hub');?></span> : <code><?php echo esc_html( RCWP_REDIS_MAXTTL ); ?>s</code>
                    <?php endif; ?>

                    <?php if ( isset( $diagnostics['prefix'] ) ) : ?>
                        <br/><span class="font-bold"><?php esc_html_e('Key Prefix', 'runcloud-hub');?></span> : <code><?php echo esc_html( $diagnostics['prefix'] ); ?></code>
                    <?php endif; ?>
                <?php endif;?>
            </li>
            <?php endif;?>
        </ul>
    </fieldset>
</div>
<!-- /redis-server -->

<?php if (self::is_main_site()): ?>

<!-- redis-cache-groups -->
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="redis" data-tab-page-title="<?php esc_html_e('Redis', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold leading-tight text-base-1000"><?php esc_html_e('Redis Object Cache Groups', 'runcloud-hub');?></h3>
    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>
            <!-- ignored-groups -->
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" data-action="disabled" id="redis_ignored_groups_onn" name="<?php self::view_fname('redis_ignored_groups_onn');?>" value="1" <?php self::view_checked('redis_ignored_groups_onn');?>>
                    <label class="control-label" for="redis_ignored_groups_onn"><?php esc_html_e('Exclude Object Cache Groups', 'runcloud-hub');?></label>
                </div>

                <p class="ml-8 text-base-800 pt-1"><?php esc_html_e('Exclude object cache groups that should not be cached in Redis, one per line.', 'runcloud-hub');?></p>
                <textarea data-parent="redis_ignored_groups_onn" data-parent-action="disabled" id="redis_ignored_groups_mch" name="<?php self::view_fname('redis_ignored_groups_mch');?>" placeholder="utm_source" <?php self::view_fattr();?> disabled><?php echo sanitize_textarea_field(self::view_rvalue('redis_ignored_groups_mch'));?></textarea>
            </li>
            <!-- /ignored-groups -->
        </ul>
    </fieldset>
</div>
<!-- /redis-cache-groups -->

<!-- redis-cache-advanced -->
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="redis" data-tab-page-title="<?php esc_html_e('Redis', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold leading-tight text-base-1000"><?php esc_html_e('Advanced Redis Options', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>
            <!-- key-prefix -->
            <li>
                <div class="form-group pb-0">
                    <label class="control-label" for="redis_prefix"><?php esc_html_e('Object Cache Key Prefix', 'runcloud-hub');?></label>
                    <p class="mb-4 text-base-800 pt-1"><?php esc_html_e('Set the prefix for object cache keys. Leave blank for auto generated prefix.', 'runcloud-hub');?></p>
                    <input type="text" id="redis_prefix" name="<?php self::view_fname('redis_prefix');?>" value="<?php self::view_fvalue('redis_prefix');?>" <?php self::view_fattr();?>>
                </div>
            </li>
            <!-- /key-prefix -->

            <!-- maxttl -->
            <li>
                <div class="form-checkbox-setting">
                    <input type="checkbox" data-action="disabled" id="redis_maxttl_onn" name="<?php self::view_fname('redis_maxttl_onn');?>" value="1" <?php self::view_checked('redis_maxttl_onn');?>>
                    <label class="control-label" for="redis_maxttl_onn"><?php esc_html_e('Maximum Time-To-Live', 'runcloud-hub');?></label>
                </div>

                <p class="ml-8 mb-4 text-base-800 pt-1"><?php esc_html_e('Enable this option to set maximum time-to-live for cached objects.', 'runcloud-hub');?></p>

                <div class="ml-8-label"><?php esc_html_e('The amount of time, a cache will be served before automatically released', 'runcloud-hub');?></div>
                <input type="number" min="1" id="redis_maxttl_int" name="<?php self::view_fname('redis_maxttl_int');?>" value="<?php self::view_fvalue('redis_maxttl_int');?>" data-parent="redis_maxttl_onn" data-parent-action="disabled" disabled>
                <select id="redis_maxttl_unt" name="<?php self::view_fname('redis_maxttl_unt');?>" data-parent="redis_maxttl_onn" data-parent-action="disabled" disabled>
                    <?php self::view_timeduration_select(self::view_rvalue('redis_maxttl_unt'));?>
                </select>
            </li>
            <!-- /maxttl -->

        </ul>
    </fieldset>
</div>
<!-- /redis-cache-advanced -->

<!-- redis-debug -->
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="redis" data-tab-page-title="<?php esc_html_e('Redis', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Debug Redis', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" data-action="disabled" id="redis_debug_onn" name="<?php self::view_fname('redis_debug_onn');?>" value="1" <?php self::view_checked('redis_debug_onn');?>>
                    <label class="control-label" for="redis_debug_onn"><?php esc_html_e('Enable Redis statistics in the footer', 'runcloud-hub');?></label>
                </div>

                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Automatically show statistics of Redis object cache (hits, misses, size) in the footer, both admin and frontend, for administrator user only.', 'runcloud-hub');?></p>
            </li>

        </ul>
    </fieldset>
</div>
<!-- /debug-purge -->

<?php endif; ?>
