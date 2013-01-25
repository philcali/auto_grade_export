<?php

require_once '../../config.php';
require_once $CFG->libdir . '/gradelib.php';

require_login();

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);

$context = get_context_instance(CONTEXT_SYSTEM);

if (!has_capability('block/up_grade_export:canbuildquery', $context)) {
    print_error('no_permission', 'block_up_grade_export');
}

$blockname = get_string('pluginname', 'block_up_grade_export');
$heading = get_string('list_queries', 'block_up_grade_export');

$PAGE->set_context($context);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($heading);
$PAGE->set_title("$blockname: $heading");
$PAGE->set_heading("$blockname: $heading");

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

if (isset($SESSION->query_updated)) {
    $label = get_string($SESSION->query_updated, 'block_up_grade_export');
    echo $OUTPUT->notification($label, 'notifysuccess');

    unset($SESSION->query_updated);
} else if (isset($SESSION->query_failed)) {
    $label = get_string($SESSION->query_failed, 'block_up_grade_export');
    echo $OUTPUT->notification($label);

    unset($SESSION->query_failed);
}

$query_count = $DB->count_records('block_up_export_queries');
$queries = $DB->get_records('block_up_export_queries', null, 'name DESC', '*', $perpage * $page, $perpage);

$build_url = new moodle_url('/blocks/up_grade_export/build.php');

if (empty($query_count)) {
    echo $OUTPUT->notification(get_string('no_queries', 'block_up_grade_export'));

    echo $OUTPUT->continue_button($build_url);
    echo $OUTPUT->footer();
    exit;
}

$edit_str = get_string('edit');
$edit_icon = $OUTPUT->pix_icon('i/edit', $edit_str, 'moodle', array('class' => 'icon'));

$delete_str = get_string('delete');
$delete_icon = $OUTPUT->pix_icon('t/delete', $delete_str, 'moodle', array('class' => 'icon'));

$export_str = get_string('export', 'block_up_grade_export');
$export_icon = $OUTPUT->pix_icon('i/backup', $export_str, 'moodle', array('class' => 'icon'));

$edit_link = function($query, $title) use ($edit_str) {
    $url = new moodle_url('/blocks/up_grade_export/build.php', array('id' => $query->id));
    return html_writer::link($url, $title);
};

$delete_link = function($query) use ($delte_str, $delete_icon) {
    $url = new moodle_url('/blocks/up_grade_export/delete.php', array('id' => $query->id));
    return html_writer::link($url, $delete_icon);
};

$export_link = function($query) use ($export_str, $export_icon) {
    $url = new moodle_url('/blocks/up_grade_export/build_export.php', array(
        'queryid' => $query->id,
    ));
    return html_writer::link($url, $export_icon);
};

$table = new html_table();
$table->head = array(
    get_string('query_name', 'block_up_grade_export'),
    get_string('query_sql', 'block_up_grade_export'),
    get_string('created', 'block_up_grade_export'),
    get_string('action'),
);

foreach ($queries as $query) {

    $line = array();
    $line[] = $edit_link($query, $query->name);
    $line[] = strlen($query->external) > 60 ? substr($query->external, 0, 57) . '...' : $query->external;
    $line[] = userdate($query->created_timestamp, '%d/%m/%Y %r');
    $line[] = $export_link($query) . ' ' . $edit_link($query, $edit_icon) . ' ' . $delete_link($query);

    $table->data[] = new html_table_row($line);
}

$list_url = new moodle_url('/blocks/up_grade_export/list.php');
$list_export_url = new moodle_url('/blocks/up_grade_export/list_exports.php');

$pagination = $OUTPUT->paging_bar($query_count, $page, $perpage, $list_url);

$build_query_button = new single_button($build_url, get_string('build_query', 'block_up_grade_export'), 'get');
$manage_exports_button = new single_button($list_export_url, get_string('list_exports', 'block_up_grade_export'), 'get');
$build_query_button->class = "left";
$manage_exports_button->class = "right";

echo $pagination;
echo html_writer::start_tag('div', array('class' => 'query_table'));
echo html_writer::table($table);
echo html_writer::end_tag('div');
echo html_writer::tag('div',
  $OUTPUT->render($build_query_button) . $OUTPUT->render($manage_exports_button),
  array('class' => 'centerbuttons'));
echo $pagination;
echo $OUTPUT->footer();
