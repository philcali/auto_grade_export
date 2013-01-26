<?php

require_once $CFG->libdir . '/gradelib.php';
require_once $CFG->dirroot . '/blocks/up_grade_export/classes/connection.php';

/**
 * Class wrapper containing convenience methods for a single
 * export entity
 */
class query_exporter {
    public $id;
    public $itemid;
    public $automated;

    // No direct access
    private $query;

    // Cached results
    public $grade_item;
    public $course;

    // Cached results
    public $users;
    public $grades;

    /**
     * Find all of the defined exports by a given course
     *
     * @param mixed $course
     * @return array query_exporter
     */
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
     * Returns a key => value pair for the build_export form
     *
     * @return array
     */
    public static function get_export_types() {
        $data = new stdClass;
        $data->types = array();

        events_trigger('export_entry_types', $data);

        return $data->types;
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
        $this->entry = new stdClass;

        $this->id = $db_item->id ?: null;
        $this->itemid = $db_item->itemid ?: null;
        $this->automated = isset($db_item->automated) ? $db_item->automated : false;

        $this->entry->queryid = isset($db_item->queryid) ? $db_item->queryid : null;

        // needed for form building :(
        $this->queryid = $this->entry->queryid;
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
     * Sets a query for this export
     *
     * @param external_database $connection
     * @return query_exporter
     */
    public function set_query(external_database $connection) {
        $this->query = $connection;
        return $this;
    }

    /**
     * Returns the query associated with this export
     *
     * @return external_database | null
     */
    public function get_query() {
        global $DB;

        if (empty($this->query)) {
            // This can be pluralized in the future
            $entry = $DB->get_record('block_up_export_entry', array('exportid' => $this->id));

            // Allow external manipulation
            if ($entry) {
                $this->entry = $entry;
                // TODO: this can probably be better
                $this->entry->type = $DB->get_field('block_up_export_queries', 'type', array('id' => $entry->queryid));

                events_trigger("{$this->entry->type}_entry", $this);
            }

            if ($this->query and get_config('block_up_grade_export', 'mocked_connection')) {
                $this->query = new mocked_connection($this->query);
            }
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

        $this->users = get_role_users(explode(',', $gradebook_roles), $context, false, 'u.*');

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

            $this->id = $DB->insert_record('block_up_export_exports', $this);
        } else {
            $created = false;

            $data = new stdClass;
            $data->old_export = self::get(array('id' => $this->id));
            $data->old_export->get_query();

            $data->new_export = $this;

            $DB->update_record('block_up_export_exports', $this);
        }

        if ($this->entry->queryid) {
            $this->entry->exportid = $this->id;

            $db_item = $DB->get_record('block_up_export_entry', array(
                'exportid' => $this->id,
            ));

            if (!$db_item) {
                $this->entry->id = $DB->insert_record('block_up_export_entry', $this->entry);
            } else {
                $this->entry->id = $db_item->id;
                $DB->update_record('block_up_export_entry', $this->entry);
            }
        }

        $created ?
            events_trigger('export_created', $this) :
            events_trigger('export_updated', $data);

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
            return (
                $DB->delete_records('block_up_export_entry', array('exportid' => $this->id)) &&
                $DB->delete_records('block_up_export_exports', array('id' => $this->id))
            );
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

        $params = array('exportid' => $this->id);
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

    /**
     * Gets the exported items for each history
     *
     * @param stdClass $history
     */
    public function get_exported_items($history) {
        global $DB;

        $users = $this->pull_users();

        $sql = 'SELECT userid as id, grade FROM {block_up_export_items} where historyid = :id';

        return $DB->get_records_sql($sql, array('id' => $history->id));
    }
}
