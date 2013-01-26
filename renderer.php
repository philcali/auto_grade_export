<?php

/**
 * Navigation component that represents the guide to send your around
 */
class guiding_navigation implements renderable {

    private $locations;
    private $current;

    /**
     * Build a navigation
     *
     * @param string $current
     */
    public function __construct($current = 'build_query') {
        $base = '/blocks/up_grade_export';

        $this->locations = array(
            'build_query' => new moodle_url("$base/build.php"),
            'build_export' => new moodle_url("$base/build_export.php"),
            'list_queries' => new moodle_url("$base/list.php"),
            'list_exports' => new moodle_url("$base/list_exports.php"),
        );

        $this->current = $current;
    }

    /**
     * Gets all of the locations this navigation can take you
     *
     * @return array string
     */
    public function get_locations() {
        return $this->locations;
    }

    /**
     * Tests whethre this url is the current url
     *
     * @param moodle_url $location
     * @return boolean
     */
    public function is_current(moodle_url $location) {
        return $location == $this->locations[$this->current];
    }

    /**
     * Generats the html to render this nav links
     *
     * @return string
     */
    public function html() {
        $html = html_writer::start_tag('ul', array('class' => 'nav'));
        foreach ($this->get_locations() as $key => $location) {
            $params = array();
            $label = get_string($key, 'block_up_grade_export');

            if (!$this->is_current($location)) {
                $label = html_writer::link($location, $label);
            } else {
                $params['class'] = 'selected';
            }

            $html .= html_writer::tag('li', $label, $params);
        }
        $html .= html_writer::end_tag('ul');

        return $html;
    }
}

/**
 * Plugin output renderer to render the special guiding navigation
 */
class block_up_grade_export_renderer extends plugin_renderer_base {

    /**
     * @see parent
     *
     * @param guiding_navigation $navigation
     */
    protected function render_guiding_navigation(guiding_navigation $navigation) {
        return html_writer::start_tag('div', array('class' => 'up_nav_container'))
             . $navigation->html()
             . html_writer::end_tag('div');
    }
}
