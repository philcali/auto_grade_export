<?php

require_once '../../config.php';
require_once 'forms/build.php';
require_once 'classes/lib.php';

require_login();

$queryid = optional_param('id', null, PARAM_INT);
$sql = optional_param('external', null, PARAM_MULTILANG);

$context = get_context_instance(CONTEXT_SYSTEM);

if (!has_capability('block/up_grade_export:canbuildquery', $context)) {
    print_error('no_permission', 'block_up_grade_export');
}

if ($queryid) {
    $query = oracle_query::get(array('id' => $queryid));

    if ($query and empty($sql)) {
        $sql = $query->external;
    }
}

$blockname = get_string('pluginname', 'block_up_grade_export');
$heading = get_string('build_query', 'block_up_grade_export');

$manage_str = get_string('list_queries', 'block_up_grade_export');
$manage_url = new moodle_url('/blocks/up_grade_export/list.php');

$PAGE->set_context($context);
$PAGE->navbar->add($blockname);
$PAGE->navbar->add($manage_str, $manage_url);
$PAGE->navbar->add($heading);
$PAGE->set_title("$blockname: $heading");
$PAGE->set_heading("$blockname: $heading");

$fields = array();
if ($sql) {
    $fields = oracle_query::parse_sql($sql);
}

$form = new build_form(null, array('fields' => $fields));

if (isset($query)) {
    $index = 0;
    foreach ($query->get_fields() as $field) {
        $query->{"query_{$field->external}"} = $field->moodle;
    }
    $form->set_data($query);
}

if ($form->is_cancelled()) {
    redirect($manage_url);
} else if ($data = $form->get_data()) {

    $invalid = false;
    foreach ($fields as $i => $field) {
        if (empty($data->{"query_$field"})) {
            $invalid = true;
        }
    }

    if (!$invalid) {
        $query = new oracle_query($data);

        $success = $query->save($created);

        if ($success and $created) {
            $SESSION->query_updated = 'query_updated';
        } else if ($success) {
            $SESSION->query_updated = 'query_updated';
        } else {
            $SESSION->query_failed = 'query_failed';
        }

        redirect($manage_url);
    }
}

$output = $PAGE->get_renderer('block_up_grade_export');

$navigation = new guiding_navigation('build_query');

echo $output->header();
echo $output->render($navigation);

echo $output->heading($heading);

$form->display();

echo $output->footer();
