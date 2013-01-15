<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $_s = function($key, $a = null) {
        return get_string($key, 'block_up_grade_export', $a);
    };

    $settings->add(new admin_setting_heading(
        'block_up_grade_export_settings',
        '',
        $_s('pluginname_desc')
    ));

    $default_url = new moodle_url('/');

    $settings->add(new admin_setting_configtext(
        'block_up_grade_export/host',
        $_s('host'),
        $_s('host_desc'),
        $default_url->out()
    ));

    $settings->add(new admin_setting_configtext(
        'block_up_grade_export/username',
        $_s('username'),
        $_s('username_desc')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'block_up_grade_export/password',
        $_s('password'),
        $_s('password_desc')
    ));
}
