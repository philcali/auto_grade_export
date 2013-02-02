<?php

require_once '../../config.php';
require_once $CFG->libdir . '/gradelib.php';
require_once 'classes/lib.php';

require_login();

$queryid = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$export = optional_param('export', 0, PARAM_INT);

$context = empty($courseid) ?
    get_context_instance(CONTEXT_SYSTEM) :
    get_context_instance(CONTEXT_COURSE, $courseid);

$can_build = has_capability('block/up_grade_export:canbuildquery', $context);

if (!has_capability('block/up_grade_export:canexport', $context)) {
    print_error('no_permission', 'block_up_grade_export');
}

$query = query_exporter::get(array('id' => $queryid));

if (empty($query)) {
    print_error('no_export', 'block_up_grade_export');
}

if (!$query->get_query()) {
    print_error('no_query', 'block_up_grade_export');
}

$blockname = get_string('pluginname', 'block_up_grade_export');
$heading = get_string('export', 'block_up_grade_export');

$PAGE->set_context($context);
$PAGE->navbar->add($blockname);
$PAGE->set_title("$blockname: $heading");
$PAGE->set_heading("$blockname: $heading");

if ($courseid) {
    $back_url = new moodle_url('/course/view.php', array('id' => $courseid));
    $course = $DB->get_record('course', array('id' => $courseid));

    $PAGE->set_course($course);
} else {
    $back_url = new moodle_url('/blocks/up_grade_export/list_exports.php');

    $PAGE->navbar->add(get_string('list_queries', 'block_up_grade_export'), $back_url);
}

$PAGE->navbar->add($heading);

if (!$query->can_pull_grades()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading($heading);

    echo $OUTPUT->notification(get_string('can_pull', 'block_up_grade_export'));

    if ($can_build) {
        $build_url = new moodle_url('/blocks/up_grade_export/build_export.php', array('id' => $query));
        echo $OUTPUT->continue_button($build_url);
    } else {
        echo $OUTPUT->continue_button($back_url);
    }

    echo $OUTPUT->footer();
    exit;
}

$a = new stdClass;
$a->name = "{$query->get_course()->shortname}: {$query->get_grade_item()->get_name()}";
$a->table = $query->get_query()->get_name();

$heading_str = get_string('export_to', 'block_up_grade_export', $a);

echo $OUTPUT->header();
echo $OUTPUT->heading($heading_str);

if ($export) {
    $connection = $query->get_query();

    $errors = $query->export_grades($connection, $USER->id);
    if (empty($errors)) {
        echo $OUTPUT->notification(get_string('export_success', 'block_up_grade_export'), 'notifysuccess');
    } else if ($errors === true) {
        $error = new stdClass;
        $error->fullname = $query->get_course()->fullname;
        $error->finalgrade = $query->get_grade_item()->get_name();

        echo $OUTPUT->notification(get_string('export_failed', 'block_up_grade_export', $error));
    } else {
        $users = $query->pull_users();
        foreach ($errors as $error) {
            $user = $users[$error->userid];
            $error->fullname = fullname($user);

            echo $OUTPUT->notification(get_string('export_failed', 'block_up_grade_export', $error));
        }
    }

    echo $OUTPUT->continue_button($back_url);
    echo $OUTPUT->footer();
    exit;
}

if ($last_export = $query->get_last_export()) {
    $a = new stdClass;
    $a->date = userdate($last_export->timestamp);
    $a->status = $last_export->success ? 'Success' : 'Failed';

    $str = get_string('last_export', 'block_up_grade_export', $a);

    if ($last_export->success) {
        echo $OUTPUT->notification($str, 'notifysuccess');
    } else {
        echo $OUTPUT->notification($str);
    }
}

list($users, $grades) = $query->pull_user_grades();

$table = new html_table();
$table->head = array(
  '',
  get_string('lastname') . ' / ' . get_string('firstname'),
  get_string('grade', 'grades'),
  get_string('status'),
);

$grade_item = $query->get_grade_item();

if ($last_export) {
    $exported = $query->get_exported_items($last_export);
}

$exported_icon = $OUTPUT->pix_icon('i/completion-manual-enabled', '', 'moodle', array('class' => 'icon'));

foreach ($users as $user) {
    $grade = $query->get_grade_for_user($user);

    $user_link = new moodle_url('/grade/report/user/index.php', array(
        'id' => $query->get_course()->id,
        'userid' => $user->id,
    ));

    $line = array();
    $line[] = $OUTPUT->user_picture($user, array('courseid' => $query->get_course()->id));
    $line[] = html_writer::link($user_link, "$user->lastname, $user->firstname");
    $line[] = grade_format_gradevalue($grade->finalgrade, $grade_item, true, $grade_item->get_displaytype());

    if (empty($exported[$user->id])) {
        $line[] = 'NA';
    } else if ($exported[$user->id]->grade != (float) $grade->finalgrade) {
        $display = grade_format_gradevalue($exported[$user->id]->grade, $grade_item, true, $grade_item->get_displaytype());
        $line[] = html_writer::tag('span', "Changed from $display", array('class' => 'error'));
    } else {
        $line[] = $exported_icon;
    }

    $table->data[] = new html_table_row($line);
}

$export_url = new moodle_url(basename(__FILE__), array(
    'id' => $queryid,
    'courseid' => $courseid,
    'export' => 1,
));

$export_query_button = new single_button($export_url, $heading);
$export_query_button->class = 'continuebutton';

echo html_writer::start_tag('div', array('class' => 'query_table'));
echo html_writer::table($table);
echo html_writer::end_tag('div');
echo $OUTPUT->render($export_query_button);

echo $OUTPUT->footer();
