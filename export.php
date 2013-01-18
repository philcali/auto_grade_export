<?php

require_once '../../config.php';
require_once $CFG->libdir . '/gradelib.php';
require_once 'classes/lib.php';

require_login();

$queryid = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

$context = empty($courseid) ?
    get_context_instance(CONTEXT_SYSTEM) :
    get_context_instance(CONTEXT_COURSE, $courseid);

$can_build = has_capability('block/up_grade_export::can_build', $context);

if (!has_capability('block/up_grade_export:canexport', $context)) {
    print_error('no_permission', 'block_up_grade_export');
}

$query = query_connector::get(array('id' => $queryid));

if (empty($query)) {
    print_error('no_query', 'block_up_grade_export');
}

$blockname = get_string('pluginname', 'block_up_grade_export');
$heading = get_string('export', 'block_up_grade_export');

$PAGE->set_context($context);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($heading);
$PAGE->set_title("$blockname: $heading");
$PAGE->set_heading("$blockname: $heading");

if (!$query->can_pull_grades()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading($heading);

    echo $OUTPUT->notification(get_string('can_pull', 'block_up_grade_export'));

    if ($can_build) {
        $build_url = new moodle_url('/blocks/up_grade_export/build.php', array('id' => $query));
        echo $OUTPUT->continue_button($build_url);
    }

    echo $OUTPUT->footer();
    exit;
}

$a = new stdClass;
$a->name = "{$query->get_course()->shortname}: {$query->get_grade_item()->get_name()}";
$a->table = $query->get_external_name();

$heading_str = get_string('export_to', 'block_up_grade_export', $a);

echo $OUTPUT->header();
echo $OUTPUT->heading($heading_str);

list($users, $grades) = $query->pull_user_grades();

$table = new html_table();
$table->head = array(
  '',
  get_string('lastname') . ' / ' . get_string('firstname'),
  get_string('grade', 'grades'),
);

$grade_item = $query->get_grade_item();

foreach ($grades as $grade) {
    $user = $users[$grade->userid];

    $user_link = new moodle_url('/grade/report/user/index.php', array(
        'id' => $query->get_course()->id,
        'userid' => $user->id,
    ));

    $line = array();
    $line[] = $OUTPUT->user_picture($user, array('courseid' => $query->get_course()->id));
    $line[] = html_writer::link($user_link, "$user->lastname, $user->firstname");
    $line[] = grade_format_gradevalue($grade->finalgrade, $grade_item, true, $grade_item->get_displaytype());

    $table->data[] = new html_table_row($line);
}

echo html_writer::start_tag('div', array('class' => 'query_table'));
echo html_writer::table($table);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
