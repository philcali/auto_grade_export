<?php

/**
 * The query_connector hands off its results to import into this
 */
interface external_database {
    public function close();
    public function is_connected();
    public function connect($trigger = false);
    public function with($callback);
    public function import($data);
}

/**
 * Using the configuration details, this helper provides some basic
 * implementation
 */
abstract class moodle_external_config implements external_database {
    public static $username;
    public static $password;
    public static $host;

    protected $resource;

    /**
     * Tests whether or not this connection is established
     *
     * @return boolean
     */
    public function is_connected() {
        return !empty($this->resource);
    }

    /**
     * Create a connection with an external id
     */
    public function __construct() {
        $this->username = self::load_cache('username');
        $this->password = self::load_cache('password');
        $this->host = self::load_cache('host');
    }

    /**
     * Loads the config statically to increase multiple connections performance
     *
     * @param string $config_value
     */
    private static function load_cache($config_value) {
        if (!self::${$config_value}) {
            self::${$config_value} = get_config('block_up_grade_export', $config_value);
        }

        return self::${$config_value};
    }
}

/**
 * Oracle database query using moodle connection config
 */
class oracle_query extends moodle_external_config {
    public $id;
    public $name;
    public $external;
    public $created_timestamp;

    private $fields;

    // One statement per query
    private $statement;
    private $to_commit;

    /**
     * Simple wrapper around moodle DB and publishes query events
     *
     * @param boolean $created
     * @return boolean
     */
    public function save(&$created) {
        global $DB;

        $success = true;
        if (empty($this->id)) {
            $created = true;

            $this->created_timestamp = time();
            $this->id = $DB->insert_record('block_up_export_queries', $this);
        } else {
            $created = false;

            $data = new stdClass;
            $data->old_query = self::get(array('id' => $this->id));
            $data->old_query->get_fields();
            $data->new_query = $this;

            $DB->update_record('block_up_export_queries', $this);
        }

        $params = array('queryid' => $this->id);
        $current_fields = $DB->get_records('block_up_export_fields', $params);

        if ($this->fields) {

            foreach ($this->fields as $field) {
                $params['external'] = $field->external;

                $field->queryid = $this->id;
                $db_field = $DB->get_record('block_up_export_fields', $params);

                if (!$db_field) {
                    $field->id = $DB->insert_record('block_up_export_fields', $field);
                } else {
                    $field->id = $db_field->id;
                    $DB->update_record('block_up_export_fields', $field);
                }

                unset($current_fields[$field->id]);
            }
        }

        if ($current_fields) {
            $ids = implode(',', array_keys($current_fields));
            $DB->delete_records_select('block_up_export_fields', "id in ($ids)");
        }

        $created ?
            events_trigger('query_created', $this) :
            events_trigger('query_updated', $data);

        return $success;
    }

    /**
     * Simple wrapper around moodle DB and publishes query events
     *
     * @return boolean
     */
    public function delete() {
        global $DB;

        try {
            events_trigger('query_deleted', $this);
            return (
                $DB->delete_records('block_up_export_fields', array('queryid' => $this->id)) and
                $DB->delete_records('block_up_export_queries', array('id' => $this->id))
            );
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Creates an oracle query from a form submission
     *
     * @param stdClass $db_item
     */
    public function __construct($db_item = null) {
        // loads the cache
        parent::__construct();

        if ($db_item) {
            foreach (get_object_vars($db_item) as $field => $value) {
                if (preg_match('/^query_(.+)/', $field, $matches)) {
                    $fieldClass = new stdClass;
                    $fieldClass->external = $matches[1];
                    $fieldClass->moodle = $value;
                    $this->fields[] = $fieldClass;
                    continue;
                }

                $this->$field = $value;
            }
        }
    }

    /**
     * Retrieves mapped fields to this dynamic query
     */
    public function get_fields() {
        global $DB;

        if (empty($this->fields)) {
            $this->fields = $DB->get_records('block_up_export_fields', array(
                'queryid' => $this->id,
            ));
        }

        return $this->fields;
    }

    /**
     * Gets the name of this query
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Finds all fields to evaluate
     *
     * @return array [i] => field_name
     */
    public static function parse_sql($sql) {
        if (preg_match_all('/:([^ \';]+)/s', $sql, $matches)) {
            return $matches[1];
        }

        return array();
    }

    /**
     * Finds all connections that match these params
     *
     * @param array $params (Optional)
     */
    public static function get_all(array $params = null) {
        global $DB;

        $queries = $DB->get_records('block_up_export_queries', $params);

        $return = array();
        foreach ($queries as $query) {
            $return[$query->id] = new oracle_query($query);
        }

        return $return;
    }

    /**
     * Finds a single connection that matches these params
     *
     * @params array $params
     */
    public static function get(array $params) {
        return current(self::get_all($params));
    }

    /**
     * Opens a connection to the database
     *
     * @return boolean
     */
    public function connect($trigger = false) {
        if ($this->is_connected()) {
            return true;
        }

        $this->resource = oci_connect($this->username, $this->password, $this->host);
        if ($error = $this->get_error()) {

            if ($trigger) {
                throw new Exception(sprintf("Message [%s] Code [%d]", $error['message'], $error['code']));
            }
        } else {
            $this->statement = oci_parse($this->resource, $this->external);
        }

        return empty($error);
    }

    /**
     * Returns a connection error, if any
     * @return false|array
     */
    public function get_error() {
        return oci_error();
    }

    /**
     * Closes the connection and releases the resource
     *
     * @return boolean
     */
    public function close() {
        if ($this->is_connected()) {
            // Execute batched statement and cleanup
            oci_commit($this->resource);
            if ($this->get_error()) {
                oci_rollback($this->resource);
            }

            oci_free_statement($this->statement);
            oci_close($this->resource);
            unset($this->resource);
        }

        return true;
    }

    /**
     * Transforms the fields into a key value pair with real data
     *
     * @param array $data
     * @return array
     */
    public function map_fields($data) {
        $mapped = array();
        foreach ($this->get_fields() as $field) {
            $sub_fields = explode('.', $field->moodle);

            $field_value = null;
            foreach ($data as $key => $object) {
                if ($key === $sub_fields[0]) {
                    $field_value = $object->{$sub_fields[1]};
                    break;
                }

                if (isset($object->{$field->moodle})) {
                    $field_value = $object->{$field->moodle};
                    break;
                }
            }

            $mapped[$field->external] = $field_value;
        }

        return $mapped;
    }

    /**
     * Executes a SQL statement
     *
     * @param array ('c' => course, 'u' => user, 'gg' => grade, 'gi' => item)
     * @return boolean
     */
    public function import($data) {
        foreach ($this->map_fields($data) as $external => $value) {
            oci_bind_by_name($this->statement, $external, $value);
        }

        return oci_execute($this->statement, OCI_NO_AUTO_COMMIT);
    }

    /**
     * This is a resource handler that properly opens and closes the connection
     *
     * @param callable $callback
     * @return mixed
     */
    public function with($callback) {
        $this->connect();
        if ($this->is_connected() and is_callable($callback)) {
            $result = $callback($this);
            $this->close();
        }

        return $result;
    }
}

/**
 * This mocked connection can be used to test query building and process
 */
class mocked_connection extends oracle_query {
    /**
     * @see parent
     */
    public function connect($trigger = false) {
        $password = implode('', array_map(function($c) { return '*'; }, str_split($this->password)));
        mtrace("Connection: {$this->host}:{$this->username}:{$password}\n");

        $self = $this;
        $this->resource = function ($data) use ($self) {
            $sql = $self->external;
            foreach ($self->map_fields($data) as $external => $value) {
                $sql = str_replace(":$external", "'$value'", $sql);
            }
            return $sql;
        };

        return true;
    }

    /**
     * @see parent
     */
    public function import($data) {
        mtrace($this->resource->__invoke($data) . "\n");
        return true;
    }

    /**
     * @see parent
     */
    public function close() {
        mtrace("Closing connection\n");
        unset($this->resource);
    }
}
