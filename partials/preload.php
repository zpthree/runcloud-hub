<?php defined('RUNCLOUD_HUB_INIT') || exit;?>

<!-- preload -->
<div class="p-8 bg-white rounded-sm shadow mb-6 display-none" data-tab-page="runcache-preload" data-tab-page-title="<?php esc_html_e('RunCache Preload', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Cache Preload', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid border-base-2-200 bg-body rounded-sm rci-field">
        <ul>
            <li>
                <div class="ml-8-label"><?php esc_html_e('Preload speed (posts/pages per minute)', 'runcloud-hub');?></div>
                <input type="number" min="1" max="600" placeholder="60" id="preload_speed" name="<?php self::view_fname('preload_speed');?>" value="<?php self::view_fvalue('preload_speed');?>">
            </li>
        </ul>
    </fieldset>
</div>
<!-- /preload -->

<!-- preload_onn -->
<div class="p-8 bg-white rounded-sm shadow mb-6 display-none" data-tab-page="runcache-preload" data-tab-page-title="<?php esc_html_e('RunCache Preload', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Automatic Preload', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid border-base-2-200 bg-body rounded-sm rci-field">
        <ul>
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" data-action="disabled" id="preload_onn" name="<?php self::view_fname('preload_onn');?>" value="1" <?php self::view_checked('preload_onn');?>>
                    <label class="control-label" for="preload_onn"><?php esc_html_e('Automatic Cache Preload', 'runcloud-hub');?></label>
                </div>

                <p class="ml-8 text-base-800 pt-1"><?php esc_html_e('Automatically run cache preload when purge cache action is triggered.', 'runcloud-hub');?></p>
            </li>
        </ul>
    </fieldset>
</div>
<!-- /preload_onn -->

<!-- preload_schedule_onn -->
<div class="p-8 bg-white rounded-sm shadow mb-6 display-none" data-tab-page="runcache-preload" data-tab-page-title="<?php esc_html_e('RunCache Preload', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Scheduled Preload', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid border-base-2-200 bg-body rounded-sm rci-field">
        <ul>
            <li>
                <div class="form-checkbox-setting">
                    <input type="checkbox" data-action="disabled" id="preload_schedule_onn" name="<?php self::view_fname('preload_schedule_onn');?>" value="1" <?php self::view_checked('preload_schedule_onn');?>>
                    <label class="control-label" for="preload_schedule_onn"><?php esc_html_e('Enable Scheduled Preload', 'runcloud-hub');?></label>
                </div>

                <p class="ml-8 mb-4 text-base-800 pt-1"><?php esc_html_e('Automatically preload cache based on schedule time.', 'runcloud-hub');?></p>

                <div class="ml-8-label"><?php esc_html_e('The frequency of time set to preload', 'runcloud-hub');?></div>
                <input type="number" min="1" id="preload_schedule_int" name="<?php self::view_fname('preload_schedule_int');?>" value="<?php self::view_fvalue('preload_schedule_int');?>" data-parent="preload_schedule_onn" data-parent-action="disabled" disabled>
                <select id="preload_schedule_unt" name="<?php self::view_fname('preload_schedule_unt');?>" data-parent="preload_schedule_onn" data-parent-action="disabled" disabled>
                    <?php self::view_timeduration_select(self::view_rvalue('preload_schedule_unt'));?>
                </select>

            </li>
        </ul>
    </fieldset>
</div>
<!-- /preload_schedule_onn -->

<!-- preload_path_onn -->
<div class="p-8 bg-white rounded-sm shadow mb-6 display-none" data-tab-page="runcache-preload" data-tab-page-title="<?php esc_html_e('RunCache Preload', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Preload URL Path', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid border-base-2-200 bg-body rounded-sm rci-field">
        <ul>
            <li>
                <div class="form-checkbox-setting">
                    <input type="checkbox" data-action="disabled" id="preload_path_onn" name="<?php self::view_fname('preload_path_onn');?>" value="1" <?php self::view_checked('preload_path_onn');?>>
                    <label class="control-label" for="preload_path_onn"><?php esc_html_e('Enable Path Preload', 'runcloud-hub');?></label>
                </div>

                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Always preload cache for matching URL Path, one per line.', 'runcloud-hub');?></p>
                <textarea data-parent="preload_path_onn" data-parent-action="disabled" id="preload_path_mch" name="<?php self::view_fname('preload_path_mch');?>" placeholder="/login/" <?php self::view_fattr();?> disabled><?php echo sanitize_textarea_field(self::view_rvalue('preload_path_mch'));?></textarea>
            </li>
        </ul>
    </fieldset>
</div>
<!-- /preload_path_onn -->
