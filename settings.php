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
        $_s('username_desc'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'block_up_grade_export/password',
        $_s('password'),
        $_s('password_desc'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'block_up_grade_export/cron_target',
        $_s('cron_target'),
        $_s('cron_target_desc'),
        '2 - 4'
    ));

    $settings->add(new admin_setting_configtext(
        'block_up_grade_export/cron_interval',
        $_s('cron_interval'),
        $_s('cron_interval_desc'),
        24 * 60 * 60
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_up_grade_export/mocked_connection',
        $_s('mocked_connection'),
        $_s('mocked_connection_desc'),
        0
    ));

    $connection_url = new moodle_url($PAGE->url, array('connect' => 1));
    $connection_button = new single_button($connection_url, $_s('test_connection'));
    $connection_button->class = 'continuebutton';

    $settings->add(new admin_setting_heading(
        'block_up_grade_export_test_heading',
        $_s('test_connection'),
        $_s('test_connection_desc', $OUTPUT->render($connection_button))
    ));

    // Kind of a crappy place to put this, but better than nowhere, I suppose
    if (optional_param('connect', null, PARAM_INT)) {
        require_once $CFG->dirroot . '/blocks/up_grade_export/classes/lib.php';

        $query = new oracle_query();

        try {
            // It'll either connect or throw an exception
            if ($query->connect(true)) {
                $query->close();

                $result = $OUTPUT->notification($_s('test_connection_success'), 'notifysuccess');
            }
        } catch (Exception $e) {
            $result = $OUTPUT->notification($_s('test_connection_failed', $e->getMessage()));
        }

        $settings->add(new admin_setting_heading(
            'block_up_grade_export_test_result_heading',
            $_s('test_connection_results'),
            $result
        ));
    }
}
