<?php defined('RUNCLOUD_HUB_INIT') || exit;?>

<!-- exclude-cache -->
<div class="p-8 bg-white rounded-sm shadow mb-6 display-none" data-tab-page="runcache-rules" data-tab-page-title="<?php esc_html_e('RunCache Rules', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Cache Exclusion Settings', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid border-base-2-200 bg-body rounded-sm rci-field">
        <ul>
            <!-- url -->
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" data-action="disabled" id="exclude_url_onn" name="<?php self::view_fname('exclude_url_onn');?>" value="1" <?php self::view_checked('exclude_url_onn');?>>
                    <label class="control-label" for="exclude_url_onn"><?php esc_html_e('Exclude URL Path', 'runcloud-hub');?></label>
                </div>

                <p class="ml-8 text-base-800 pt-1"><?php esc_html_e('Exclude page cache based on matching URL path, one per line.', 'runcloud-hub');?></p>
                <textarea data-parent="exclude_url_onn" data-parent-action="disabled" id="exclude_url_mch" name="<?php self::view_fname('exclude_url_mch');?>" placeholder="/checkout/" <?php self::view_fattr();?> disabled><?php echo sanitize_textarea_field(self::view_rvalue('exclude_url_mch'));?></textarea>
            </li>
            <!-- /url -->

            <!-- cookie -->
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" data-action="disabled" id="exclude_cookie_onn" name="<?php self::view_fname('exclude_cookie_onn');?>" value="1" <?php self::view_checked('exclude_cookie_onn');?>>
                    <label class="control-label" for="exclude_cookie_onn"><?php esc_html_e('Exclude Cookie', 'runcloud-hub');?></label>
                </div>

                <p class="ml-8 text-base-800 pt-1"><?php esc_html_e('Exclude page cache based on matching cookie name, one per line.', 'runcloud-hub');?></p>
                <textarea data-parent="exclude_cookie_onn" data-parent-action="disabled" id="exclude_cookie_mch" name="<?php self::view_fname('exclude_cookie_mch');?>" placeholder="tracking_" <?php self::view_fattr();?> disabled><?php echo sanitize_textarea_field(self::view_rvalue('exclude_cookie_mch'));?></textarea>
            </li>
            <!-- /cookie -->

            <!-- browser -->
            <li>
                <div class="form-checkbox-setting">
                    <input type="checkbox" data-action="disabled" id="exclude_browser_onn" name="<?php self::view_fname('exclude_browser_onn');?>" value="1" <?php self::view_checked('exclude_browser_onn');?>>
                    <label class="control-label" for="exclude_browser_onn"><?php esc_html_e('Exclude Browser User-Agent', 'runcloud-hub');?></label>
                </div>

                <p class="ml-8 text-base-800 pt-1"><?php esc_html_e('Exclude page cache based on matching browser user-agent, one per line.', 'runcloud-hub');?></p>
                <textarea data-parent="exclude_browser_onn" data-parent-action="disabled" id="exclude_browser_mch" name="<?php self::view_fname('exclude_browser_mch');?>" placeholder="googlebot" <?php self::view_fattr();?> disabled><?php echo sanitize_textarea_field(self::view_rvalue('exclude_browser_mch'));?></textarea>
            </li>
            <!-- /browser -->

            <!-- visitorip -->
            <li>
                <div class="form-checkbox-setting">
                    <input type="checkbox" data-action="disabled" id="exclude_visitorip_onn" name="<?php self::view_fname('exclude_visitorip_onn');?>" value="1" <?php self::view_checked('exclude_visitorip_onn');?>>
                    <label class="control-label" for="exclude_visitorip_onn"><?php esc_html_e('Exclude Visitor IP Address', 'runcloud-hub');?></label>
                </div>

                <p class="ml-8 text-base-800 pt-1"><?php esc_html_e('Exclude page cache based on matching visitor IP address, one per line.', 'runcloud-hub');?></p>

                <textarea data-parent="exclude_visitorip_onn" data-parent-action="disabled" id="exclude_visitorip_mch" name="<?php self::view_fname('exclude_visitorip_mch');?>" placeholder="8.8.8.8" <?php self::view_fattr();?> disabled><?php echo sanitize_textarea_field(self::view_rvalue('exclude_visitorip_mch'));?></textarea>
            </li>
            <!-- /visitorip -->

        </ul>
    </fieldset>
</div>
<!-- /exclude-cache -->

<!-- query-cache -->
<div class="p-8 bg-white rounded-sm shadow mb-6 display-none" data-tab-page="runcache-rules" data-tab-page-title="<?php esc_html_e('RunCache Rules', 'runcloud-hub');?>">
    <h3 class="pb-2 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Cache Query String Settings', 'runcloud-hub');?></h3>
    <p class="text-base-800 etxt-xl pb-6"><?php esc_html_e('By default a page with query string will not be cached, you need to enable either one of the options below to activate it.', 'runcloud-hub');?></p>
    <fieldset class="p-4 border border-solid border-base-2-200 bg-body rounded-sm rci-field">
        <ul>
            <!-- allow-query -->
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" data-action="disabled" id="allow_query_onn" name="<?php self::view_fname('allow_query_onn');?>" value="1" <?php self::view_checked('allow_query_onn');?>>
                    <label class="control-label" for="allow_query_onn"><?php esc_html_e('Allow Cache Query String', 'runcloud-hub');?></label>
                </div>

                <p class="ml-8 text-base-800 pt-1"><?php esc_html_e('Allow cache based on matching query string, one per line.', 'runcloud-hub');?></p>
                <textarea data-parent="allow_query_onn" data-parent-action="disabled" id="allow_query_mch" name="<?php self::view_fname('allow_query_mch');?>" placeholder="utm_source" <?php self::view_fattr();?> disabled><?php echo sanitize_textarea_field(self::view_rvalue('allow_query_mch'));?></textarea>
            </li>
            <!-- /allow-query -->
            <!-- exlude-query -->
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" data-action="disabled" id="exclude_query_onn" name="<?php self::view_fname('exclude_query_onn');?>" value="1" <?php self::view_checked('exclude_query_onn');?>>
                    <label class="control-label" for="exclude_query_onn"><?php esc_html_e('Exclude Cache Query String', 'runcloud-hub');?></label>
                </div>

                <p class="ml-8 text-base-800 pt-1"><?php esc_html_e('Exclude page cache based on matching query string, one per line.', 'runcloud-hub');?></p>
                <textarea data-parent="exclude_query_onn" data-parent-action="disabled" id="exclude_query_mch" name="<?php self::view_fname('exclude_query_mch');?>" placeholder="id" <?php self::view_fattr();?> disabled><?php echo sanitize_textarea_field(self::view_rvalue('exclude_query_mch'));?></textarea>
            </li>
            <!-- /exlude-query -->
        </ul>
    </fieldset>
</div>
<!-- /query-cache -->

<!-- cache-key -->
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="runcache-rules" data-tab-page-title="<?php esc_html_e('RunCache Rules', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Cache Key', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>
            <li>
                <div class="form-checkbox-setting">
                    <input type="checkbox" data-action="disabled" id="cache_key_extra_onn" name="<?php self::view_fname('cache_key_extra_onn');?>" value="1" <?php self::view_checked('cache_key_extra_onn');?>>
                    <label class="control-label" for="cache_key_extra_onn"><?php esc_html_e('Set Cache Key', 'runcloud-hub');?></label>
                </div>
                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Enable this option to add additional cache keys. The input should as recognized and valid NGINX variables.', 'runcloud-hub');?></p>
                <input type="text" data-parent="cache_key_extra_onn" data-parent-action="disabled" id="cache_key_extra_var" name="<?php self::view_fname('cache_key_extra_var');?>" value="<?php self::view_fvalue('cache_key_extra_var');?>" <?php self::view_fattr();?> disabled>
            </li>
        </ul>
    </fieldset>
</div>
<!-- /cache-key -->
