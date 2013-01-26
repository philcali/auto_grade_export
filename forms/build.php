<?php

require_once $CFG->libdir . '/formslib.php';

class build_form extends moodleform {
    public function definition() {
        $m =& $this->_form;

        $m->addElement('hidden', 'id', '');
        $m->addElement('hidden', 'created_timestamp', '');

        // This could eventually be changed or set
        $m->addElement('hidden', 'type', '');
        $m->setDefault('type', 'oracle_query');

        $m->setType('created_timestamp', PARAM_INT);
        $m->setDefault('created_timestamp', time());

        $label = get_string('query_header', 'block_up_grade_export');
        $m->addElement('header', 'query_header', $label);

        $label = get_string('query_name', 'block_up_grade_export');
        $m->addElement('text', 'name', $label);

        $m->setType('name', PARAM_MULTILANG);
        $m->addRule('name', null, 'required', null, 'client');

        $label = get_string('query_sql', 'block_up_grade_export');
        $m->addElement('textarea', 'external', $label, 'rows="5" cols="80"');

        $m->setType('external', PARAM_MULTILANG);
        $m->addRule('external', null, 'required', null, 'client');

        $fields = $this->_customdata['fields'];

        if ($fields) {
            $label = get_string('query_fields', 'block_up_grade_export');

            $m->addElement('header', 'query_field_header', $label);
            foreach ($fields as $i => $field) {
                $m->addElement('text', "query_$field", $field);
                // $m->setDefault("query_$field", $field);
            }
        }

        $m->closeHeaderBefore('buttons');

        $label = $fields ? 'submit' : 'next';
        $buttons = array(
            $m->createElement('submit', 'submit', get_string($label)),
            $m->createElement('cancel'),
        );

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
    }
}
