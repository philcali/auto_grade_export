<?php

require_once $CFG->libdir . '/formslib.php';

class build_form extends moodleform {
    /**
     * This conditionally defines the form based on builder workflow
     * @see parent
     */
    public function definition() {
        $m =& $this->_form;

        $label = get_string('externalid', 'block_up_grade_export');
        $m->addElement('text', 'externalid', $label);

        $label = get_string('automated', 'block_up_grade_export');
        $m->addElement('checkbox', 'automated', $label, '');

        $course = $this->_customdata['course'];

        if (empty($course)) {
            $label = get_string('course') . ' ' . get_string('shortname');
            $m->addElement('text', 'shortname', $label);
        }

        $courses = $this->_customdata['courses'];

        if ($courses) {
            $m->addElement('static', 'page_top', '', $this->_customdata['pagination']);
            foreach ($courses as $course) {
                $m->addElement('radio', 'selected_course', '', $course->fullname, $course->id);
            }
            $m->addElement('static', 'page_bottom', '', $this->_customdata['pagination']);

            $m->addRule('selected_course', null, 'required', null, 'client');
            $m->setType('selected_course', PARAM_INT);
        }

        $grade_seq = $this->_customdata['grade_seq'];

        if ($grade_seq and $grade_seq->items) {
            $structure = $this->_customdata['structure'];
            $struct_params = function ($item) {
                return array('type' => 'item', 'object' => $item);
            };

            $label = get_string('select_grade', 'block_up_grade_export');
            $m->addElement('static', 'selected_course', $label, $course->fullname);

            foreach ($grade_seq->items as $grade_item) {
                $grade_icon = $structure->get_element_icon($struct_params($grade_item));
                $label = " $grade_icon{$grade_item->get_name()}";

                $m->addElement('radio', 'itemid', '', $label, $grade_item->id);
            }

            $m->addElement('hidden', 'course', $course->id);
            $m->addRule('externalid', null, 'required', null, 'client');
            $m->setType('externalid', PARAM_MULTILANG);
        }

        $label = $grade_seq ? 'submit' : 'next';
        $buttons = array(
            $m->createElement('submit', 'submit', get_string($label)),
            $m->createElement('cancel'),
        );

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
    }
}
