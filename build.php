<?php

require_once '../../config.php';
require_once 'forms/build.php';

require_login();

$queryid = optional_param('id', null, PARAM_INT);
$courseid = optional_param('fromid', SITEID, PARAM_INT);
$shortname = optional_param('shortname', '', PARAM_TEXT);
$selected_course = optional_param('course', 0, PARAM_INT);

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 10, PARAM_INT);

$context = $courseid === SITEID ?
    get_context_instance(CONTEXT_SYSTEM) :
    get_context_instance(CONTEXT_COURSE, $courseid);

if (!has_capability('block/up_grade_export:canbuildquery', $context)) {
    print_error('no_permission', 'block_up_grade_export');
}

$blockname = get_string('pluginname', 'block_up_grade_export');
$heading = get_string('build_query', 'block_up_grade_export');

$PAGE->set_context($context);
$PAGE->navbar->add($blockname);
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
}

if ($selected_course) {
    $course = $DB->get_record('course', array('id' => $selected_course));
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

if ($form->is_cancelled()) {
    $url = $courseid === SITEID ?
        new moodle_url('/my') :
        new moodle_url('/course/view.php', array('id' => $courseid));

    redirect($url);
} else if ($data = $form->get_data()) {

    if ($data->itemid) {
        $query = new stdClass;
        $query->itemid = $data->itemid;
        $query->externalid = $data->externalid;
        $query->automated = $data->automated ?: false;

        $DB->insert_record('block_up_export_queries', $query);

        redirect(new moodle_url('/my'));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);

$form->display();

echo $OUTPUT->footer();
