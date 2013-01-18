<?php

/**
 * The query_connector hands off its results to import into this
 */
interface external_database {
    public function close();
    public function is_connected();
    public function connect();
    public function with($callback);
    public function import($user, $grade);
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
    protected $externalid;

    /**
     * Gets the external id to import into
     *
     * @return string
     */
    public function get_external() {
        return $this->externalid;
    }

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
     *
     * @param string $externalid (optional)
     */
    public function __construct($externalid = null) {
        $this->username = self::load_cache('username');
        $this->password = self::load_cache('password');
        $this->host = self::load_cache('host');
        $this->externalid = $externalid;
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
 * Oracle database connection using moodle connection config
 * @todo: turn this black box into real connections
 */
class oracle_connection extends moodle_external_config {

    /**
     * Opens a connection to the database
     */
    public function connect() {
        $this->resource = function($statement) {
            mtrace($statement);
            return true;
        };
    }

    /**
     * Closes the connection and releases the resource
     */
    public function close() {
        if ($this->is_connected()) {
            unset($this->resource);
        }
    }

    /**
     * Executes a SQL statement
     *
     * @param string $statement
     * @return mixed
     */
    public function import($user, $grade) {
        $sql = "DEBUG: UPDATE $this->externalid SET finalgrade = '$grade->finalgrade' WHERE idnumber = '$user->idnumber'";
        return $this->resource->__invoke($sql);
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
