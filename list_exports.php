<?php

require_once '../../config.php';
require_once 'classes/lib.php';

require_login();

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);

$context = get_context_instance(CONTEXT_SYSTEM);

if (!has_capability('block/up_grade_export:canbuildquery', $context)) {
    print_error('no_permission', 'block_up_grade_export');
}

$blockname = get_string('pluginname', 'block_up_grade_export');
$heading = get_string('list_exports', 'block_up_grade_export');

$manage_queries = get_string('list_queries', 'block_up_grade_export');
$manage_url = new moodle_url('/blocks/up_grade_export/list.php');

$PAGE->set_context($context);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($manage_queries, $manage_url);
$PAGE->navbar->add($heading);
$PAGE->set_title("$blockname: $heading");
$PAGE->set_heading("$blockname: $heading");

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

if (isset($SESSION->export_updated)) {
    $label = get_string($SESSION->export_updated, 'block_up_grade_export');
    echo $OUTPUT->notification($label, 'notifysuccess');

    unset($SESSION->export_updated);
} else if (isset($SESSION->export_failed)) {
    $label = get_string($SESSION->export_failed, 'block_up_grade_export');
    echo $OUTPUT->notification($label);

    unset($SESSION->export_failed);
}

$export_count = $DB->count_records('block_up_export_exports');
$exports = query_exporter::get_all(null, $perpage * $page, $perpage);

$build_url = new moodle_url('/blocks/up_grade_export/build_export.php');

if (empty($export_count)) {
    echo $OUTPUT->notification(get_string('no_exports', 'block_up_grade_export'));

    echo $OUTPUT->continue_button($build_url);
    echo $OUTPUT->footer();
    exit;
}

$deleted_str = html_writer::tag('span', get_string('deleted', 'block_up_grade_export'), array('class' => 'deleted'));

$edit_str = get_string('edit');
$edit_icon = $OUTPUT->pix_icon('i/edit', $edit_str, 'moodle', array('class' => 'icon'));

$delete_str = get_string('delete');
$delete_icon = $OUTPUT->pix_icon('t/delete', $delete_str, 'moodle', array('class' => 'icon'));

$export_str = get_string('export', 'block_up_grade_export');
$export_icon = $OUTPUT->pix_icon('i/backup', $export_str, 'moodle', array('class' => 'icon'));

$query_link = function($query) {
    $url = new moodle_url('/blocks/up_grade_export/build.php', array('id' => $query->id));
    return html_writer::link($url, $query->get_name());
};

$edit_link = function($export, $title) use ($edit_str) {
    $url = new moodle_url('/blocks/up_grade_export/build_export.php', array('id' => $export->id));
    return html_writer::link($url, $title);
};

$delete_link = function($export) use ($delte_str, $delete_icon) {
    $url = new moodle_url('/blocks/up_grade_export/delete_export.php', array('id' => $export->id));
    return html_writer::link($url, $delete_icon);
};

$export_link = function($export) use ($export_str, $export_icon) {
    $url = new moodle_url('/blocks/up_grade_export/export.php', array('id' => $export->id));
    return html_writer::link($url, $export_icon);
};

$course_link = function($item) use ($DB) {
    $course = $DB->get_record('course', array('id' => $item->courseid));
    $url = new moodle_url('/course/view.php', array('id' => $course->id));
    return html_writer::link($url, "$course->fullname / $course->shortname");
};

$grade_link = function($item) {
    if ($item->is_course_item()) {
        return $item->get_name();
    } else if ($item->is_category_item()) {
        $type = 'category';
        $id = $item->get_item_category()->id;
        $name = "{$item->get_item_category()->get_name()} (Category)";
    } else {
        $type = 'item';
        $id = $item->id;
        $name = $item->get_name();
    }

    $params = array('courseid' => $item->courseid, 'id' => $id);
    $url = new moodle_url("/grade/edit/tree/$type.php", $params);
    return html_writer::link($url, $name);
};

$table = new html_table();
$table->head = array(
    get_string('query_name', 'block_up_grade_export'),
    get_string('automated', 'block_up_grade_export'),
    get_string('fullname'),
    get_string('itemname', 'grades'),
    get_string('action'),
);

$automated_icon = $OUTPUT->pix_icon('i/completion-manual-enabled', '', 'moodle', array('class' => 'icon'));
$manual_icon = $OUTPUT->pix_icon('i/completion-manual-n', '', 'moodle', array('class' => 'icon'));

foreach ($exports as $export) {
    $query = $export->get_query();
    $grade_item = $export->get_grade_item();

    $line = array();
    $line[] = $query ? $query_link($query) : $deleted_str;
    $line[] = $export->automated ? $automated_icon : $manual_icon;
    $line[] = $grade_item ? $course_link($grade_item) : $deleted_str;
    $line[] = $grade_item ? $grade_link($grade_item) : $deleted_str;
    $line[] = ($grade_item ? $export_link($export) : $export_icon) . ' '
            . $edit_link($export, $edit_icon) . ' ' . $delete_link($export);

    $table->data[] = new html_table_row($line);
}

$list_url = new moodle_url('/blocks/up_grade_export/list_exports.php');
$pagination = $OUTPUT->paging_bar($export_count, $page, $perpage, $list_url);

$build_export_button = new single_button($build_url, get_string('build_export', 'block_up_grade_export'), 'get');
$build_export_button->class = 'continuebutton';

echo $pagination;
echo html_writer::start_tag('div', array('class' => 'query_table'));
echo html_writer::table($table);
echo html_writer::end_tag('div');
echo $OUTPUT->render($build_export_button);
echo $pagination;

echo $OUTPUT->footer();
