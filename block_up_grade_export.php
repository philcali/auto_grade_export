<?php

require_once $CFG->dirroot . '/blocks/up_grade_export/classes/lib.php';

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
            'course' => true,
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

        $params = array('class' => 'icon');

        $context = $COURSE->id === SITEID ?
            get_context_instance(CONTEXT_SYSTEM) :
            get_context_instance(CONTEXT_COURSE, $COURSE->id);

        if ($COURSE->id !== SITEID and has_capability('block/up_grade_export:canexport', $context)) {
            $exports = query_exporter::find_by_course($COURSE);

            foreach ($exports as $export) {
                $args = array('id' => $export->id, 'courseid' => $COURSE->id);
                $url = new moodle_url('/blocks/up_grade_export/export.php', $args);

                $a = new stdClass;
                $a->name = $export->get_grade_item()->get_name();
                $a->table = 'Banner';

                $str = get_string('export_to', 'block_up_grade_export', $a);

                $content->items[] = html_writer::link($url, $str);
                $content->icons[] = $OUTPUT->pix_icon('i/backup', $str, 'moodle', $params);
            }
        }

        if (has_capability('block/up_grade_export:canbuildquery', $context)) {

            $url = new moodle_url('/blocks/up_grade_export/build.php');
            $str = get_string('build_query', 'block_up_grade_export');

            $content->items[] = html_writer::link($url, $str);
            $content->icons[] = $OUTPUT->pix_icon('i/settings', $str, 'moodle', $params);

            if ($DB->count_records('block_up_export_queries')) {
                $url = new moodle_url('/blocks/up_grade_export/build_export.php');
                $str = get_string('build_export', 'block_up_grade_export');

                $content->items[] = html_writer::link($url, $str);
                $content->icons[] = $OUTPUT->pix_icon('i/settings', $str, 'moodle', $params);

                $url = new moodle_url('/blocks/up_grade_export/list.php');
                $str = get_string('list_queries', 'block_up_grade_export');

                $content->items[] = html_writer::link($url, $str);
                $content->icons[] = $OUTPUT->pix_icon('i/settings', $str, 'moodle', $params);

                if ($DB->count_records('block_up_export_exports')) {
                    $url = new moodle_url('/blocks/up_grade_export/list_exports.php');
                    $str = get_string('list_exports', 'block_up_grade_export');

                    $content->items[] = html_writer::link($url, $str);
                    $content->icons[] = $OUTPUT->pix_icon('i/settings', $str, 'moodle', $params);
                }

            }

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

        $emaillog = array();
        $queries = query_exporter::get_all(array('automated' => true));

        foreach ($queries as $query) {
            if (!$query->can_pull_grades()) {
                $emaillog[] = "Export with id {$query->id} cannot find grade_item id {$query->itemid}";
                continue;
            }

            $connection = $query->get_query();

            if (!$connection) {
                $emaillog[] = "Export with id {$query->entry->queryid} for course {$query->get_course()->shortname} could not be found.";
                continue;
            }

            $errors = $query->export_grades($connection);
            if ($errors) {
                $emaillog[] = sprintf("Query on %s failed for %s on course %d: %d", $connection->get_name(), $query->get_grade_item()->get_name(), $query->get_course()->shortname, count($errors));
            }
        }

        if (!empty($emaillog)) {
            $body = implode("\n", $emaillog);
            $admins = get_admins();
            foreach ($admins as $admin) {
                email_to_user($admin, $CFG->noreplyaddress, 'UP grade export: ERRORS', $body);
            }
        }

        return true;
    }
}
