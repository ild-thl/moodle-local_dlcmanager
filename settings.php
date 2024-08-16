<?php
// settings.php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_dlcmanager', get_string('pluginname', 'local_dlcmanager'));

    $settings->add(new admin_setting_configtext(
        'local_dlcmanager/api_base_url',
        get_string('api_base_url', 'local_dlcmanager'),
        get_string('api_base_url_desc', 'local_dlcmanager'),
        'http://laravel:8081/api/',
        PARAM_URL
    ));

    $ADMIN->add('localplugins', $settings);
}