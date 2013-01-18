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

        global $COURSE, $OUTPUT, $USER, $DB;

        $content = new stdClass();
        $content->items = array();
        $content->icons = array();
        $content->footer = '';

        if (is_siteadmin($USER->id)) {
            $url = new moodle_url('/admin/settings.php', array(
                'section' => 'blocksettingup_grade_export',
            ));
            $content->footer = html_writer::link($url, get_string('settings'));
        }

        $context = $COURSE->id === SITEID ?
            get_context_instance(CONTEXT_SYSTEM) :
            get_context_instance(CONTEXT_COURSE, $COURSE->id);

        if (has_capability('block/up_grade_export:canbuildquery', $context)) {
            $params = array('class' => 'icon');

            if ($DB->count_records('block_up_export_queries')) {
                $url = new moodle_url('/blocks/up_grade_export/list.php');
                $str = get_string('list_queries', 'block_up_grade_export');

                $content->items[] = html_writer::link($url, $str);
                $content->icons[] = $OUTPUT->pix_icon('i/settings', $str, 'moodle', $params);
            }

            $url = new moodle_url('/blocks/up_grade_export/build.php');
            $str = get_string('build_query', 'block_up_grade_export');

            $content->items[] = html_writer::link($url, $str);
            $content->icons[] = $OUTPUT->pix_icon('i/settings', $str, 'moodle', $params);
        }

        $this->content = $content;

        return $this->content;
    }

    /**
     * Checks to see if cron run within configured params
     *
     * @param DateTime $now
     * @return boolean
     */
    public function is_cron_ready(DateTime $now) {
        global $DB;

        $lastcron = $DB->get_field('block', 'lastcron', array('name' => 'up_grade_export'));
        $cron_interval = (int) get_config('block_up_grade_export', 'cron_interval');

        if ($now->getTimestamp() - $lastcron < $cron_interval) {
            return false;
        }

        $cron_target = get_config('block_up_grade_export', 'cron_target');

        list($start, $end) = preg_split('/\s*\-\s*/', $cron_target);

        $start_time = new DateTime();
        $start_time->setTime($start, 0, 0);

        $end_time = new DateTime();
        $end_time->setTime($end, 0, 0);

        if ($now < $start_time || $now > $end_time) {
            return false;
        }

        return true;
    }

    /**
     * @see parent
     * @return boolean
     */
    public function cron() {
        global $CFG;

        $now = new DateTime();

        if (!$this->is_cron_ready($now)) {
            return false;
        }

        require_once $CFG->dirroot . '/blocks/up_grade_export/classes/lib.php';

        $queries = query_connector::get_all(array('automated' => true));

        foreach ($queries as $query) {
            $grade_item = $query->get_grade_item();

            mtrace("Run query {$query->externalid} on {$grade_item->get_name()}");
        }

        return true;
    }
}
