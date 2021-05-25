<?php defined('RUNCLOUD_HUB_INIT') || exit;?>

<!-- purge-status -->
<?php 
if (self::is_srcache()) {
    $cache_type = esc_html__('Redis Full-Page Caching', 'runcloud-hub');
    $cache_switch = esc_html__('FastCGI/Proxy Page Caching', 'runcloud-hub');
}
else {
    $cache_type = esc_html__('FastCGI/Proxy Page Caching', 'runcloud-hub');
    $cache_switch = esc_html__('Redis Full-Page Caching', 'runcloud-hub');
}
?>
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="runcache" data-tab-page-title="<?php esc_html_e('RunCache', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('RunCache Status', 'runcloud-hub');?></h3>
    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <p class="leading-loose">
            <strong><?php esc_html_e('NGINX Page Caching Method:', 'runcloud-hub');?> <span class="text-green-800"><?php echo esc_html( $cache_type ); ?></span></strong>
        </p>
        <p class="pt-1 text-base-800">
            <?php echo esc_html( sprintf( esc_html__( 'If this information is not correct and %s is installed in the server,', 'runcloud-hub'), $cache_switch) ) ;?> 
            <br/><a href="<?php self::view_purge_link('switchpurger');?>#runcache"><span class="text-red-800"><?php echo esc_html( sprintf( esc_html__( 'Switch Cache Purger To %s', 'runcloud-hub'), $cache_switch) ) ;?></span></a>
        </p>
    </fieldset>
</div>
<!-- /purge-status -->

<!-- purge-homepage -->
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="runcache-purger" data-tab-page-title="<?php esc_html_e('RunCache Purger', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Purge Homepage Settings', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" id="homepage_post_onn" name="<?php self::view_fname('homepage_post_onn');?>" value="1" <?php self::view_checked('homepage_post_onn');?>>
                    <label class="control-label" for="homepage_post_onn"><?php esc_html_e('Post Updated/Added', 'runcloud-hub');?></label>
                </div>

                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Automatically clear cache of homepage when a post / page / CPT is updated/added.', 'runcloud-hub');?></p>
            </li>

            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" id="homepage_removed_onn" name="<?php self::view_fname('homepage_removed_onn');?>" value="1" <?php self::view_checked('homepage_removed_onn');?>>
                    <label class="control-label" for="homepage_removed_onn"><?php esc_html_e('Post Removed', 'runcloud-hub');?></label>
                </div>

                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Automatically clear cache of homepage when a post / page / CPT is removed.', 'runcloud-hub');?></p>
            </li>
        </ul>
    </fieldset>
</div>
<!-- /purge-homepage -->

<!-- purge-content -->
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="runcache-purger" data-tab-page-title="<?php esc_html_e('RunCache Purger', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Purge Post/Page/CPT Settings', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" id="content_publish_onn" name="<?php self::view_fname('content_publish_onn');?>" value="1" <?php self::view_checked('content_publish_onn');?>>
                    <label class="control-label" for="content_publish_onn"><?php esc_html_e('Post Published/Removed', 'runcloud-hub');?></label>
                </div>

                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Automatically clear cache of a post / page / CPT when published or removed.', 'runcloud-hub');?></p>
            </li>

            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" id="content_comment_approved_onn" name="<?php self::view_fname('content_comment_approved_onn');?>" value="1" <?php self::view_checked('content_comment_approved_onn');?>>
                    <label class="control-label" for="content_comment_approved_onn"><?php esc_html_e('Comment Approved', 'runcloud-hub');?></label>
                </div>

                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Automatically clear cache of a post / page / CPT when a comment is approved or published.', 'runcloud-hub');?></p>
            </li>
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" id="content_comment_removed_onn" name="<?php self::view_fname('content_comment_removed_onn');?>" value="1" <?php self::view_checked('content_comment_removed_onn');?>>
                    <label class="control-label" for="content_comment_removed_onn"><?php esc_html_e('Comment Removed', 'runcloud-hub');?></label>
                </div>

                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Automatically clear cache of a post / page / CPT when a comment is unapproved or removed.', 'runcloud-hub');?></p>
            </li>
        </ul>
    </fieldset>
</div>
<!-- /purge-content -->

<!-- purge-archives -->
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="runcache-purger" data-tab-page-title="<?php esc_html_e('RunCache Purger', 'runcloud-hub');?>">
    <h3 class="pb-2 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Purge Archives Settings', 'runcloud-hub');?></h3>

    <p class="pb-6 text-sm text-base-1000"><?php esc_html_e('Archives are categories, tags, custom taxonomies, author page, and date pages of current post / page / CPT', 'runcloud-hub');?></p>

    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" id="archives_homepage_onn" name="<?php self::view_fname('archives_homepage_onn');?>" value="1" <?php self::view_checked('archives_homepage_onn');?>>
                    <label class="control-label" for="archives_homepage_onn"><?php esc_html_e('Homepage Purged', 'runcloud-hub');?></label>
                </div>

                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Automatically clear cache of archives when running any action of purge homepage setting.', 'runcloud-hub');?></p>
            </li>

            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" id="archives_content_onn" name="<?php self::view_fname('archives_content_onn');?>" value="1" <?php self::view_checked('archives_content_onn');?>>
                    <label class="control-label" for="archives_content_onn"><?php esc_html_e('Post Purged', 'runcloud-hub');?></label>
                </div>

                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Automatically clear cache of archives when running any action of purge post / page / CPT setting.', 'runcloud-hub');?></p>
            </li>
        </ul>
    </fieldset>
</div>
<!-- /purge-archives -->

<!-- purge-url-path -->
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="runcache-purger" data-tab-page-title="<?php esc_html_e('RunCache Purger', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Purge URL Path', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>
            <li>
                <div class="form-checkbox-setting">
                    <input type="checkbox" data-action="disabled" id="url_path_onn" name="<?php self::view_fname('url_path_onn');?>" value="1" <?php self::view_checked('url_path_onn');?>>
                    <label class="control-label" for="url_path_onn"><?php esc_html_e('Enable purge URL path', 'runcloud-hub');?></label>
                </div>

                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Automatically clear cache of matching URL path, one per line, when a post / page / CPT is published/removed or a comment is approved/removed.', 'runcloud-hub');?></p>
                <textarea data-parent="url_path_onn" data-parent-action="disabled" id="url_path_mch" name="<?php self::view_fname('url_path_mch');?>" placeholder="/login/" <?php self::view_fattr();?> disabled><?php echo sanitize_textarea_field(self::view_rvalue('url_path_mch'));?></textarea>
            </li>
        </ul>
    </fieldset>
</div>
<!-- /purge-url-path -->

<!-- auto-purge -->
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="runcache-purger" data-tab-page-title="<?php esc_html_e('RunCache Purger', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Scheduled Purge', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" data-action="disabled" id="schedule_purge_onn" name="<?php self::view_fname('schedule_purge_onn');?>" value="1" <?php self::view_checked('schedule_purge_onn');?>>
                    <label class="control-label" for="schedule_purge_onn"><?php esc_html_e('Enable Scheduled Purge', 'runcloud-hub');?></label>
                </div>

                <p class="pt-1 ml-8 mb-4 text-base-800"><?php esc_html_e('Automatically clear cache based on schedule time.', 'runcloud-hub');?></p>

                <div class="ml-8-label"><?php esc_html_e('The frequency of time set to purge', 'runcloud-hub');?></div>
                <input type="number" min="1" id="schedule_purge_int" name="<?php self::view_fname('schedule_purge_int');?>" value="<?php self::view_fvalue('schedule_purge_int');?>" data-parent="schedule_purge_onn" data-parent-action="disabled" disabled>
                <select id="schedule_purge_unt" name="<?php self::view_fname('schedule_purge_unt');?>" data-parent="schedule_purge_onn" data-parent-action="disabled" disabled>
                    <?php self::view_timeduration_select(self::view_rvalue('schedule_purge_unt'));?>
                </select>
            </li>

        </ul>
    </fieldset>
</div>
<!-- /auto-purge -->

<!-- debug-purge -->
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="runcache-purger" data-tab-page-title="<?php esc_html_e('RunCache Purger', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Debug Purge', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" data-action="disabled" id="html_footprint_onn" name="<?php self::view_fname('html_footprint_onn');?>" value="1" <?php self::view_checked('html_footprint_onn');?>>
                    <label class="control-label" for="html_footprint_onn"><?php esc_html_e('Enable HTML Footprint in HTML', 'runcloud-hub');?></label>
                </div>

                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Automatically add RunCloud Hub HTML footprint at the bottom of HTML output. Please clear all cache after enabling this option.', 'runcloud-hub');?></p>
            </li>

        </ul>
    </fieldset>
</div>
<!-- /debug-purge -->
