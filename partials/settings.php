<?php defined('RUNCLOUD_HUB_INIT') || exit;?>

<!-- runcloud-api -->
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="setting" data-tab-page-title="<?php esc_html_e('Settings','runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold leading-tight text-base-1000"><?php esc_html_e('RunCloud Panel', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>
            <li>
				<div class="form-group">
				    <label class="control-label" for="rcapi_key"><?php esc_html_e('HUB API Key', 'runcloud-hub');?></label>
				    <input type="text" id="rcapi_key" name="<?php self::view_fname('rcapi_key');?>" value="<?php self::view_fvalue('rcapi_key');?>" <?php self::view_fattr();?>>
				</div>

				<div class="form-group">
				    <label class="control-label" for="rcapi_secret"><?php esc_html_e('HUB API Secret', 'runcloud-hub');?></label>
				    <input type="text" id="rcapi_secret" name="<?php self::view_fname('rcapi_secret');?>" value="<?php self::view_fvalue('rcapi_secret');?>" <?php self::view_fattr();?>>
				</div>

				<div class="form-group">
				    <label class="control-label" for="rcapi_webapp_id"><?php esc_html_e('WebApp ID', 'runcloud-hub');?></label>
				    <input type="number" min="1" id="rcapi_webapp_id" name="<?php self::view_fname('rcapi_webapp_id');?>" value="<?php self::view_fvalue('rcapi_webapp_id');?>" <?php self::view_fattr();?>>
				</div>
            </li>
        </ul>
    </fieldset>
</div>
<!-- /runcloud-api -->

<!-- magic-link -->
<div class="p-8 mb-6 bg-white rounded-sm shadow display-none" data-tab-page="setting" data-tab-page-title="<?php esc_html_e('Settings','runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold leading-tight text-base-1000"><?php esc_html_e('Magic Link', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid rounded-sm border-base-2-200 bg-body rci-field">
        <ul>

            <li>
                <div class="form-checkbox-setting">
                    <input type="checkbox" id="rcapi_magiclink_onn" name="<?php self::view_fname('rcapi_magiclink_onn');?>" value="1" <?php self::view_checked('rcapi_magiclink_onn');?>>
                    <label class="control-label" for="rcapi_magiclink_onn"><?php esc_html_e('Allow Magic Link', 'runcloud-hub');?></label>
                </div>

                <p class="pt-1 ml-8 text-base-800"><?php esc_html_e('Enable this option to allow login automatically from RunCloud Panel.', 'runcloud-hub');?></p>
            </li>

        </ul>
    </fieldset>
</div>
<!-- /magic-link -->

<!-- misc -->
<div class="p-8 bg-white rounded-sm shadow mb-6 display-none" data-tab-page="setting" data-tab-page-title="<?php esc_html_e('Settings', 'runcloud-hub');?>">
    <h3 class="pb-6 text-2xl font-bold text-base-1000 leading-tight"><?php esc_html_e('Stats', 'runcloud-hub');?></h3>

    <fieldset class="p-4 border border-solid border-base-2-200 bg-body rounded-sm rci-field">
        <ul>
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" data-action="disabled" id="stats_onn" name="<?php self::view_fname('stats_onn');?>" value="1" <?php self::view_checked('stats_onn');?>>
                    <label class="control-label" for="stats_onn"><?php esc_html_e('Enable Stats Panel', 'runcloud-hub');?></label>
                </div>

                <p class="ml-8 text-base-800 pt-1"><?php esc_html_e('Enable this option to show stats panel.', 'runcloud-hub');?></p>
            </li>
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" data-action="disabled" id="stats_health_onn" name="<?php self::view_fname('stats_health_onn');?>" value="1" <?php self::view_checked('stats_health_onn');?> data-parent="stats_onn" data-parent-action="disabled" disabled>
                    <label class="control-label" for="stats_health_onn"><?php esc_html_e('Display Server Health', 'runcloud-hub');?></label>
                </div>

                <p class="ml-8 text-base-800 pt-1 mb-4"><?php esc_html_e('Enable this option to show stats of server health.', 'runcloud-hub');?></p>

                <label for="stats_schedule_int"><?php esc_html_e('Type of server health stats data to display', 'runcloud-hub');?></label>
                <select id="stats_health_var" name="<?php self::view_fname('stats_health_var');?>" data-parent="stats_health_onn" data-parent-action="disabled" disabled>
                    <?php self::view_stats_select(self::view_rvalue('stats_health_var'), 'monthly');?>
                </select>
            </li>
            <li>
                <div class="form-checkbox-setting ">
                    <input type="checkbox" data-action="disabled" id="stats_transfer_onn" name="<?php self::view_fname('stats_transfer_onn');?>" value="1" <?php self::view_checked('stats_transfer_onn');?> data-parent="stats_onn" data-parent-action="disabled" disabled>
                    <label class="control-label" for="stats_transfer_onn"><?php esc_html_e('Display Traffic Stats', 'runcloud-hub');?></label>
                </div>

                <p class="ml-8 text-base-800 pt-1 mb-4"><?php esc_html_e('Enable this option to show stats of webapp traffic, the amount of data served for this web application to your users.', 'runcloud-hub');?></p>

                <label for="stats_schedule_int"><?php esc_html_e('Type of webapp traffic stats data to display', 'runcloud-hub');?></label>
                <select id="stats_transfer_var" name="<?php self::view_fname('stats_transfer_var');?>" data-parent="stats_transfer_onn" data-parent-action="disabled" disabled>
                    <?php self::view_stats_select(self::view_rvalue('stats_transfer_var'), 'hourly');?>
                </select>
            </li>
            <li>
                <label for="stats_schedule_int"><?php esc_html_e('Update stats data based on schedule time', 'runcloud-hub');?></label>

                <input type="number" min="1" id="stats_schedule_int" name="<?php self::view_fname('stats_schedule_int');?>" value="<?php self::view_fvalue('stats_schedule_int');?>" data-parent="stats_onn" data-parent-action="disabled" disabled>
                <select id="stats_schedule_unt" name="<?php self::view_fname('stats_schedule_unt');?>" data-parent="stats_onn" data-parent-action="disabled" disabled>
                    <?php self::view_timeduration_select(self::view_rvalue('stats_schedule_unt'), ['minute','week','year']);?>
                </select>
            </li>
        </ul>
    </fieldset>
</div>
<!-- /misc -->
