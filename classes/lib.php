<?php

require_once $CFG->libdir . '/gradelib.php';
require_once $CFG->dirroot . '/blocks/up_grade_export/classes/connection.php';

/**
 * Class wrapper containing convenience methods for a single
 * export entity
 */
class query_exporter {
    public $id;
    public $query;
    public $itemid;
    public $automated;

    // Cached results
    public $grade_item;
    public $course;

    // Cached results
    public $users;
    public $grades;

    public static function find_by_course($course) {
        global $DB;

        $sql = 'SELECT export.*
          FROM {block_up_export_exports} export,
               {course} c,
               {grade_items} gi
          WHERE gi.id = export.itemid AND gi.courseid = c.id
            AND c.id = :id AND export.automated = 0';

        $dbs = $DB->get_records_sql($sql, array('id' => $course->id));

        $return = array();
        foreach ($dbs as $db) {
            $return[$db->id] = new query_exporter($db);
        }

        return $return;
    }

    /**
     * Gets all the export matching these params
     *
     * @param array $params
     * @return array query_exporter
     */
    public static function get_all(array $params = null, $offset = 0, $limit = 0) {
        global $DB;

        $exports = $DB->get_records('block_up_export_exports', $params, '', '*', $offset, $limit);

        $return = array();
        foreach ($exports as $export) {
            $return[$export->id] = new query_exporter($export);
        }

        return $return;
    }

    /**
     * Gets a single query_exporter
     *
     * @param array $params
     * @return query_exporter | null
     */
    public static function get(array $params) {
        return current(self::get_all($params));
    }

    /**
     * Takes an export from the DB
     */
    public function __construct($db_item) {
        $this->id = $db_item->id ?: null;
        $this->queryid = $db_item->queryid ?: null;
        $this->itemid = $db_item->itemid ?: null;
        $this->automated = isset($db_item->automated) ? $db_item->automated : false;
    }

    /**
     * Is this export automated?
     *
     * @return boolean
     */
    public function is_automated() {
        return $this->automated;
    }

    /**
     * Returns the query associated with this export
     *
     * @return oracle_query | null
     */
    public function get_query() {
        if (empty($this->query)) {
            // TODO: would be nice to de couple this
            $this->query = oracle_query::get(array('id' => $this->queryid));
        }

        return $this->query;
    }

    /**
     * Gets the grade_item associated with this export
     *
     * @return grade_item | null
     */
    public function get_grade_item() {
        if (empty($this->grade_item)) {
            $this->grade_item = grade_item::fetch(array('id' => $this->itemid));
        }

        return $this->grade_item;
    }

    /**
     * Gets the moodle course associated with this course
     *
     * @return stdClass | null
     */
    public function get_course() {
        global $DB;

        if (empty($this->course)) {
            $grade_item = $this->get_grade_item();
            if ($grade_item) {
                $this->course = $DB->get_record('course', array('id' => $grade_item->courseid));
            }
        }

        return $this->course;
    }

    /**
     * Checks whether or not this export can pull grades from Moodle
     *
     * @return boolean
     */
    public function can_pull_grades() {
        $grade_item = $this->get_grade_item();
        return !empty($grade_item);
    }

    /**
     * Pulls the gradable users with their grades in
     * the course associated with this export
     *
     * @return array (array $users, array $grades)
     */
    public function pull_user_grades() {
        return array($this->pull_users(), $this->pull_grades());
    }

    /**
     * Pulls the gradeable users in the course associated with this export
     *
     * @return array stdClass
     */
    public function pull_users() {
        if ($this->users) {
            return $this->users;
        }

        if (!$this->can_pull_grades()) {
            return null;
        }

        $context = get_context_instance(CONTEXT_COURSE, $this->grade_item->courseid);

        $gradebook_roles = get_config('moodle', 'gradebookroles');

        $this->users = get_role_users(explode($gradebook_roles), $context, false, 'u.*');

        return $this->users;
    }

    /**
     * Pulls grades associated with the grade items associated with the export
     *
     * @return array stdClass
     */
    public function pull_grades() {
        if ($this->grades) {
            return $this->grades;
        }

        if (!$this->can_pull_grades()) {
            return null;
        }

        $users = $this->pull_users();
        $userids = array_keys($users);

        $this->grades = grade_grade::fetch_users_grades($this->get_grade_item(), $userids);
        return $this->grades;
    }

    /**
     * Simple wrapper around moodle DB and publishes export events
     *
     * @param boolean $created
     * @return boolean
     */
    public function save(&$created) {
        global $DB;

        $success = true;
        if (empty($this->id)) {
            $created = true;

            try {
                $this->id = $DB->insert_record('block_up_export_exports', $this);
                events_trigger('export_created', $this);
            } catch (Exception $e) {
                return false;
            }
        } else {
            $created = false;

            $data = new stdClass;
            $data->old_export = self::get(array('id' => $this->id));
            $data->new_export = $this;

            try {
                $DB->update_record('block_up_export_exports', $this);
                events_trigger('export_updated', $data);
            } catch (Exception $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Simple wrapper around moodle DB and publishes export events
     *
     * @return boolean
     */
    public function delete() {
        global $DB;

        try {
            events_trigger('export_deleted', $this);
            return $DB->delete_records('block_up_export_exports', array('id' => $this->id));
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Pops the latest export history from the DB
     *
     * @param boolean $last_success (only pull the last successful one)
     */
    public function get_last_export($last_success = false) {
        global $DB;

        $params = array();
        if ($last_success) {
            $params['success'] = true;
        }

        $results = $DB->get_records('block_up_export_history', $params, 'timestamp DESC', '*', 0, 1);
        return current($results);
    }

    /**
     * Wipes the export history for this export
     */
    public function wipe_history() {
        global $DB;

        $exports = $DB->get_records('block_up_export_history', array('exportid' => $this->id));

        $historyids = implode(',', array_keys($exports));

        if ($historyids) {
            $DB->delete_records_select('block_up_export_items', "historyid IN ($historyids)");
            $DB->delete_records_select('block_up_export_history', "id IN ($historyids)");
        }
    }

    /**
     * Exports moodle grades to external database
     *
     * @param external_database $connection
     * @param int $userid (Optional)
     * @return array $errors { userid, finalgrade }
     */
    public function export_grades(external_database $connection, $userid = null) {
        if (!$this->can_pull_grades()) {
            return null;
        }

        list($users, $grades) = $this->pull_user_grades();

        $data = new stdClass;
        $data->export = $this;
        $data->users = $users;
        $data->grades = $grades;

        events_trigger('pre_export_grades', $data);

        $users = $data->users;
        $grades = $data->grades;
        $self = $this;

        $data = $connection->with(function($conn) use ($self, $users, $grades) {
            $successes = array();
            $errors = array();
            foreach ($grades as $grade) {
                $user = $users[$grade->userid];

                $data = array(
                    'c' => $self->get_course(),
                    'gi' => $self->get_grade_item(),
                    'u' => $user,
                    'gg' => $grade,
                );

                $result = new stdClass;
                $result->userid = $user->id;
                $result->finalgrade = $grade->finalgrade;

                if ($conn->import($data)) {
                    $successes[] = $result;
                } else {
                    $errors[] = $result;
                }
            }

            $data = new stdClass;
            $data->results = $successes;
            $data->errors = $errors;
            return $data;
        });

        $data->export = $this;
        $data->userid = $userid;

        events_trigger('exported_grades', $data);

        return $data->errors;
    }

    public function get_exported_items($history) {
        global $DB;

        $users = $this->pull_users();

        $sql = 'SELECT userid as id, grade FROM {block_up_export_items} where historyid = :id';

        return $DB->get_records_sql($sql, array('id' => $history->id));
    }
}
