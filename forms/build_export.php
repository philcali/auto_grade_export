<?php

require_once $CFG->libdir . '/formslib.php';

class build_export_form extends moodleform {
    /**
     * This conditionally defines the form based on builder workflow
     * @see parent
     */
    public function definition() {
        global $DB;

        $m =& $this->_form;

        $m->addElement('hidden', 'id', '');

        $queries = $DB->get_records_menu('block_up_export_queries', null, 'name desc', 'id, name');

        $label = get_string('query_name', 'block_up_grade_export');
        $m->addElement('select', 'queryid', $label, $queries);

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
                $m->addElement('radio', 'course', '', $course->fullname, $course->id);
            }
            $m->addElement('static', 'page_bottom', '', $this->_customdata['pagination']);

            $m->addRule('course', null, 'required', null, 'client');
            $m->setType('course', PARAM_INT);
        }

        $grade_seq = $this->_customdata['grade_seq'];

        if ($grade_seq and $grade_seq->items) {
            $label = get_string('clear_course', 'block_up_grade_export');
            $m->addElement('checkbox', 'clear_course', $label, '');

            $structure = $this->_customdata['structure'];
            $struct_params = function ($item) {
                return array('type' => 'item', 'object' => $item);
            };

            $label = get_string('select_grade', 'block_up_grade_export');
            $m->addElement('static', 'selected_course', $label, $course->fullname);

            foreach ($grade_seq->items as $grade_item) {
                $grade_icon = $structure->get_element_icon($struct_params($grade_item));

                $name = $grade_item->is_category_item() ?
                    $grade_item->get_item_category()->get_name() :
                    $grade_item->get_name();

                $label = " $grade_icon{$name}";

                $m->addElement('radio', 'itemid', '', $label, $grade_item->id);
            }

            $m->addElement('hidden', 'course', $course->id);
            $m->addRule('queryid', null, 'required', null, 'client');
            $m->setType('queryid', PARAM_MULTILANG);

            $m->disabledIf('itemid', 'clear_course', 'checked');
        }

        $label = $grade_seq ? 'submit' : 'next';
        $buttons = array(
            $m->createElement('submit', 'submit', get_string($label)),
            $m->createElement('cancel'),
        );

        $m->addGroup($buttons, 'buttons', '&nbsp;', array(' '), false);
    }
}
