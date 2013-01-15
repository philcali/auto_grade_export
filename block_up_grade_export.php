<?php

/**
 * Entry point for the block display
 */
class block_up_grade_export extends block_list {
    /**
     * @see parent
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_up_grade_export');
    }

    /**
     * @see parent
     * @return array string => boolean
     */
    public function applicable_formats() {
        return array(
            'site' => true,
            'my' => true,
            'course' => false,
        );
    }

    /**
     * @see parent (2.4)
     * @return boolean
     */
    public function has_config() {
        return true;
    }

    /**
     * Set up block display display
     *
     * @see parent
     */
    public function get_content() {
        if ($this->content !== NULL) {
            return $this->content;
        }

        global $COURSE, $OUTPUT, $USER;

        $content = new stdClass();
        $content->items = array();
        $content->icons = array();
        $content->footer = '';

        if (is_siteadmin($USER->id)) {
            $url = new moodle_url('/admin/settings.php', array(
                'section' => 'block_up_grade_export',
            ));
            $content->footer = html_writer::link($url, get_string('settings'));
        }

        $context = $COURSE->id === SITEID ?
            get_context_instance(CONTEXT_SYSTEM) :
            get_context_instance(CONTEXT_COURSE, $COURSE->id);

        if (has_capability('block/up_grade_export:canbuildquery', $context)) {
            $url = new moodle_url('/blocks/up_grade_export/build.php');
            $str = get_string('build_query', 'block_up_grade_export');
            $params = array('class' => 'icon');

            $content->items[] = html_writer::link($url, $str);
            $content->icons[] = $OUTPUT->pix_icon('i/settings', $str, 'moodle', $params);
        }

        $this->content = $content;

        return $this->content;
    }
}
