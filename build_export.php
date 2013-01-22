<?php

require_once '../../config.php';
require_once 'forms/build.php';
require_once 'classes/lib.php';
require_once $CFG->libdir . '/gradelib.php';

require_login();

$queryid = optional_param('id', null, PARAM_INT);
$shortname = optional_param('shortname', '', PARAM_TEXT);
$selected_course = optional_param('course', 0, PARAM_INT);
$clear_course = optional_param('clear_course', 0, PARAM_INT);

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 10, PARAM_INT);

$context = get_context_instance(CONTEXT_SYSTEM);

if (!has_capability('block/up_grade_export:canbuildquery', $context)) {
    print_error('no_permission', 'block_up_grade_export');
}

if ($queryid) {
    $query = query_connector::get(array('id' => $queryid));

    if (empty($query)) {
        print_error('no_query', 'block_up_grade_export');
    }

    $grade_item = $query->get_grade_item();

    if ($grade_item) {
        $selected_course = $grade_item->courseid;
    }
}

$blockname = get_string('pluginname', 'block_up_grade_export');
$heading = get_string('build_query', 'block_up_grade_export');
$manage_str = get_string('list_queries', 'block_up_grade_export');

$PAGE->set_context($context);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($manage_str, new moodle_url('/blocks/up_grade_export/list.php'));
$PAGE->navbar->add($heading);
$PAGE->set_title("$blockname: $heading");
$PAGE->set_heading("$blockname: $heading");

$pagination = '';
$courses = array();

if ($shortname) {
    $sql_like = $DB->sql_like('shortname', "'%$shortname%'", false);
    $offset = $perpage * $page;
    $sort = 'shortname DESC';

    $course_count = $DB->count_records_select('course', $sql_like);
    $courses = $DB->get_records_select('course', $sql_like, null, $sort, '*', $offset, $perpage);

    if ($course_count === 1) {
        $course = current($courses);
        unset($courses);
    } else {
        $base_url = new moodle_url('/blocks/up_grade_export/build.php');
        $pagination = $OUTPUT->paging_bar($course_count, $page, $perpage, $base_url);
    }

    unset($query->itemid);
}

if ($selected_course) {
    $course = $DB->get_record('course', array('id' => $selected_course));
}

if ($clear_course) {
    unset($course);
}

if ($course) {
    require_once $CFG->dirroot . '/grade/lib.php';

    $structure = new grade_structure();
    $structure->modinfo = get_fast_modinfo($course);

    $grade_seq = new grade_seq($course->id, true);
}

$form = new build_form(basename(__FILE__), array(
    'course' => $course,
    'courses' => $courses,
    'pagination' => $pagination,
    'grade_seq' => $grade_seq,
    'structure' => $structure,
));

if ($query) {
    $form->set_data($query);
}

if ($form->is_cancelled()) {
    $url = new moodle_url('/blocks/up_grade_export/list.php');

    redirect($url);
} else if ($data = $form->get_data()) {

    if ($data->itemid) {
        $query = new query_connector($data);

        $success = $query->save($created);

        if ($success and $created) {
            $SESSION->query_updated = 'query_created';
        } else if ($success) {
            $SESSION->query_updated = 'query_updated';
        } else {
            $SESSION->query_failed = 'query_failed';
        }

        redirect(new moodle_url('/blocks/up_grade_export/list.php'));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$form->display();

echo $OUTPUT->footer();
