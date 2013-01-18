<?php

require_once '../../config.php';

require_login();

$queryid = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$context = get_context_instance(CONTEXT_SYSTEM);

if (!has_capability('block/up_grade_export:canbuildquery', $context)) {
    print_error('no_permission', 'block_up_grade_export');
}

$query = $DB->get_record('block_up_export_queries', array('id' => $queryid));

if (empty($query)) {
    print_error('no_query', 'block_up_grade_export');
}

$blockname = get_string('pluginname', 'block_up_grade_export');
$heading = get_string('delete_query', 'block_up_grade_export');

$build_str = get_string('build_query', 'block_up_grade_export');
$build_url = new moodle_url('/blocks/up_grade_export/build.php', array('id' => $queryid));

$manage_str = get_string('list_queries', 'block_up_grade_export');
$manage_url = new moodle_url('/blocks/up_grade_export/list.php');

$PAGE->set_context($context);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($manage_str, $manage_url);
$PAGE->navbar->add($build_str, $build_url);
$PAGE->navbar->add($heading);
$PAGE->set_title("$blockname: $heading");
$PAGE->set_heading("$blockname: $heading");

if ($confirm) {
    $DB->delete_records('block_up_export_queries', array('id' => $queryid));
    $SESSION->query_updated = 'query_deleted';

    redirect($manage_url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$confirm_str = get_string('delete_confirm', 'block_up_grade_export');
$confirm_url = new moodle_url(basename(__FILE__), array(
    'id' => $queryid,
    'confirm' => true,
));

echo $OUTPUT->confirm($confirm_str, $confirm_url, $manage_url);

echo $OUTPUT->footer();
