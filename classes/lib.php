<?php

require_once $CFG->libdir . '/gradelib.php';

/**
 * Class wrapper containing convenience methods for a single
 * query entry
 */
class query_connector {
    public $id;
    public $externalid;
    public $itemid;
    public $automated;

    public $grade_item;
    public $course;

    public $users;
    public $grades;

    /**
     * Gets all the queries matching these params
     *
     * @param array $params
     * @return array query_connector
     */
    public static function get_all(array $params = null) {
        global $DB;

        $queries = $DB->get_records('block_up_export_queries', $params);

        $return = array();
        foreach ($queries as $query) {
            $return[$query->id] = new query_connector($query);
        }

        return $return;
    }

    /**
     * Gets a single query_connector
     *
     * @param array $params
     * @return query_connector | null
     */
    public static function get(array $params) {
        return current(self::get_all($params));
    }

    /**
     * Takes a query from the DB
     */
    public function __construct($db_item) {
        $this->id = $db_item->id ?: null;
        $this->externalid = $db_item->externalid ?: null;
        $this->itemid = $db_item->itemid ?: null;
        $this->automated = isset($db_item->automated) ? $db_item->automated : false;
    }

    /**
     * Returns the external name of this query
     *
     * @return string
     */
    public function get_external_name() {
        return $this->externalid;
    }

    /**
     * Is this query automated?
     *
     * @return boolean
     */
    public function is_automated() {
        return $this->automated;
    }

    /**
     * Gets the grade_item associated with this query
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
     * Checks whether or not this query can pull grades from Moodle
     *
     * @return boolean
     */
    public function can_pull_grades() {
        $grade_item = $this->get_grade_item();
        return !empty($grade_item);
    }

    /**
     * Pulls the gradable users with their grades in
     * the course associated with this query
     *
     * @return array (array $users, array $grades)
     */
    public function pull_user_grades() {
        return array($this->pull_users(), $this->pull_grades());
    }

    /**
     * Pulls the gradeable users in the course associated with this query
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
     * Pulls grades associated with the grade items associated with the query
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
}
