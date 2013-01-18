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
}

$query_count = $DB->count_records('block_up_export_queries');
$queries = $DB->get_records('block_up_export_queries', null, 'externalid DESC', '*', $perpage * $page, $perpage);

$build_url = new moodle_url('/blocks/up_grade_export/build.php');

if (empty($query_count)) {
    echo $OUTPUT->notification(get_string('no_queries', 'block_up_export_queries'));

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

$edit_link = function($query, $title) use ($edit_str) {
    $url = new moodle_url('/blocks/up_grade_export/build.php', array('id' => $query->id));
    return html_writer::link($url, $title);
};

$delete_link = function($query) use ($delte_str, $delete_icon) {
    $url = new moodle_url('/blocks/up_grade_export/delete.php', array('id' => $query->id));
    return html_writer::link($url, $delete_icon);
};

$export_link = function($query) use ($export_str, $export_icon) {
    $url = new moodle_url('/blocks/up_grade_export/export.php', array('id' => $query->id));
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
    get_string('externalid', 'block_up_grade_export'),
    get_string('automated', 'block_up_grade_export'),
    get_string('fullname'),
    get_string('itemname', 'grades'),
    get_string('action'),
);

foreach ($queries as $query) {
    $grade_item = grade_item::fetch(array('id' => $query->itemid));

    $line = array();
    $line[] = $edit_link($query, $query->externalid);
    $line[] = html_writer::checkbox('automated', 1, $query->automated, '', array(
      'disabled' => disabled,
    ));
    $line[] = $grade_item ? $course_link($grade_item) : $deleted_str;
    $line[] = $grade_item ? $grade_link($grade_item) : $deleted_str;
    $line[] = ($grade_item ? $export_link($query) : $export_icon) . ' '
            . $edit_link($query, $edit_icon) . ' ' . $delete_link($query);

    $table->data[] = new html_table_row($line);
}

$list_url = new moodle_url('/blocks/up_grade_export/list.php');
$pagination = $OUTPUT->paging_bar($query_count, $page, $perpage, $list_url);

$build_query_button = new single_button($build_url, get_string('build_query', 'block_up_grade_export'), 'get');
$build_query_button->class = 'continuebutton';

echo $pagination;
echo html_writer::start_tag('div', array('class' => 'query_table'));
echo html_writer::table($table);
echo html_writer::end_tag('div');
echo $OUTPUT->render($build_query_button);
echo $pagination;

echo $OUTPUT->footer();
