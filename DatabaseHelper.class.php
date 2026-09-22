<?php

/**
 * Class DatabaseHelper - A lightweight PHP database library that simplifies database operations
 */

class DatabaseHelper
{
    // -----------------------------------------------------------------------------
    // Configuration
    // -----------------------------------------------------------------------------

    private $settings = array(
        // track info on the most recent query
        // useful for testing, but adds some overhead, so normally leave this off in production
        //    unless the information is needed for another purpose, such as analysis
        'save_last_query_info' => false,

        // exit on class error (ex: trying to assign invalid setting)
        // generally good to have on, if a setting is not set up correctly, data could be processed
        //      in an unintended way and cause an issue
        // only will affect class setup, won't be affected by DB connection issue or bad query.
        'exit_on_class_error' => true,

        // exit on initialization error (ex: cannot connect to database)
        // the value will depend on your site. if the site is completely dependent on the database, it should
        //      be set to true, since the page will not likely load if there isn't an initial database connection 
        'exit_on_db_init_error' => true,

        // exit on any error (invalid query, database connection error)
        // if true, the program will stop on any error, otherwise continue loading
        // will allow the page to load, but could cause issues if some queries run and some do not
        'exit_on_error' => true,

        // text that will display with the page load is stopped
        'error_output_text' => 'There was an error loading the page',

        // output details on the error to the screen
        // should only be true in production
        'output_error_details' => false,

        // the unique identifier column tables
        // used by all "ById" functions find a record by id
        // for instance, if this is set to "id", then 
        //  getRowById('users', 12) will run the query "select * from users where id=12;"
        'id_field_name' => 'id'
    );


    // connection to the database
    private $connection = null;

    // information on the most recent query (duration, result count, etc)
    private $lastQueryInfo = array();

    // insert id for the most recent query
    private $lastInsertId = 0;

    /**
     * set the configuration variables
     * values must be valid parameters in the $settings array
     * example usage: $db->updateSetting('save_last_query_info', true);
     * supports chaining: $db->updateSetting('save_last_query_info', true)->updateSetting('output_errors', true);
     */
    public function updateSetting($setting, $value)
    {
        if (!isset($this->settings[$setting])) {
            $this->exitProgramError("class", "updateSetting function received invalid setting: " . $setting);
            return $this;
        }

        $this->settings[$setting] = $value;

        // note add some validations here



        //if ($value) {
        //$this->settings[$setting] = true;
        //} else {
        //    $this->settings[$setting] = false;
        //}
        return $this;
    }



    // The Constructor
    public function __construct($dbName, $host, $user, $pass)
    {
        $opt = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_SILENT,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        );
        $this->connection = new PDO('mysql:host=' . $host . ';dbname=' . $dbName . ';charset=utf8mb4', $user, $pass, $opt);
    }


    // execute SQL when no result values are needed
    // generally only needed for delete or update
    // returns null if the query fails (invalid queries only, valid queries with no results are success)
    public function query($sql, $binds = array(), $flags = array())
    {
        $returnValue = $this->makeQuery($sql, 'isSuccess', $binds, $flags);
        return $returnValue;
    }

    // return all results in a 2 dimensional array
    public function all($sql, $binds = array(), $flags = array())
    {
        $returnValue = $this->makeQuery($sql, 'all', $binds, $flags);
        if (is_array($returnValue)) {
            return $returnValue;
        }
        return array();
    }

    // returns a single result in a key/value array
    // if multiple rows are retrieve from the query, only the first will be returned by this function
    public function row($sql, $binds = array(), $flags = array())
    {
        $return_value = $this->makeQuery($sql, 'row', $binds, $flags);
        if (is_array($return_value)) {
            return $return_value;
        }
        return array();
    }

    // returns an array of all values for a single column
    public function column($sql, $binds = array(), $flags = array())
    {
        $return_value = $this->makeQuery($sql, 'col', $binds, $flags);
        if (is_array($return_value)) {
            return $return_value;
        }
        return array();
    }    

    // shortcut for the column function
    public function col($sql, $binds = array(), $flags = array())
    {
        return $this->column($sql, $binds, $flags);
    }

    // returns a single result as a single value
    // if multiple rows or columns are retrieve from the query, only the value of the 
    //      first column of the first row will be returned by this function
    public function one($sql, $binds = array(), $flags = array())
    {
        $return_value = $this->makeQuery($sql, 'one', $binds, $flags);
        if (is_string($return_value)) {
            return $return_value;
        }
        if (is_numeric($return_value)) {
            return strval($return_value);
        }
        return '';
    }

    public function rowById($table, $id)
	{
		$sql = 'SELECT * FROM `'.$this->cleanIdentifier($table).'` WHERE `'.$this->cleanIdentifier($this->settings['id_field_name']).'` = :id_field;';
        $binds = array(':id'=>$id);
		$return_value = $this->makeQuery($sql, 'row', $binds);
		return $this->lastInsertId;
	}


    // execute SQL when no result values are needed
    // generally only needed for delete or update
    // returns null if the query fails (invalid queries only, valid queries with no results are success)
    public function query($sql, $binds = array(), $flags = array())
    {
        return $this->makeQuery($sql, 'isSuccess', $binds, $flags);
    }


    /*public function esc($str)
	{
		$str = $this->connection->quote($str);
		$new_len = strlen($str)-2;
		$str = substr($str,1,$new_len);
		
		return $str;
	}*/

    /**
     * Clean a database identifier such as a table or field name
     * by removing characters other than letters, numbers, and underscores.
     */
    private function cleanIdentifier($identifier)
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
    }

    // prepare and execute SQL
    // used internally by the public methods process queries
    private function makeQuery($sql, $type, $binds, $flags)
    {
        // convert any falsey value to an empty array. this way a user can pass null
        if (empty($binds)) {
            $binds = array();
        }
        if (empty($flags)) {
            $flags = array();
        }

        $this->resetInfo($sql, $type, $binds, $flags);

        if (!is_string($sql)) {
            return null;
        }

        //if (!$this->validate_where($sql))
        //{
        //	return null;
        //}

        $statement = $this->connection->prepare($sql);
        if (!$statement) {
            $this->setInfo($statement);
            return null;
        }

        foreach ($binds as $field => $value) {
            $field = ':' . $this->cleanIdentifier($field);
            $statement->bindValue($field, $value);
            if (!$statement) {
                //$this->set_con_error();
                $this->setInfo($statement);
                return null;
            }
        }
        $statement->execute();

        $this->setInfo($statement);

        if (!$statement) {
            //$this->set_con_error();
            return null;
        }

        return $this->getReturnValue($statement, $type, $flags);
    }

    private function resetInfo($sql, $type, $binds, $flags)
    {
        if ($this->settings['save_last_query_info']) {
            $this->lastQueryInfo['insert_id'] = 0;
            $this->lastQueryInfo['sql'] = $sql;
            $this->lastQueryInfo['type'] = $type;
            $this->lastQueryInfo['bind_ar'] = $binds;
            $this->lastQueryInfo['flag_ar'] = $flags;
            $this->lastQueryInfo['row_count'] = $this->lastQueryInfo['duration'] = 0;
            $this->lastQueryInfo['db_class_error'] = $this->lastQueryInfo['error'] = '';
            $this->lastQueryInfo['start_time'] = microtime();
            $this->lastQueryInfo['end_time'] = null;
        }
    }

    private function setInfo($stmt)
    {
        $this->lastInsertId = $this->connection->lastInsertId();
        if ($this->settings['save_last_query_info']) {
            $this->lastQueryInfo['end_time'] = microtime();
            $this->lastQueryInfo['duration'] = number_format(($this->lastQueryInfo['end_time'] - $this->lastQueryInfo['start_time']), 5);

            if (!$stmt) {
                //$this->set_con_error();
            } else {
                $this->lastQueryInfo['row_count'] = $stmt->rowCount();
                $this->lastQueryInfo['insert_id'] = $this->lastInsertId;
            }
        }
    }

    public function display($value)
    {
       

        if (!is_array($value)) {
             echo "\n<style>" .
            "pre.db_class_preview1 {}" .
            "</style>\n";
            echo "\n<pre class=\"db_class_preview1\">";
            var_dump($value);
            echo "</pre>\n";
            return;
        }

        echo "\n<style>" .
            "pre.db_class_preview1 {}" .
            "</style>\n";
        echo "\n<table class=\"db_class_preview1\">\n";

        $is2DArray = false;
        foreach ($value as $rowNKey => $row) {
            if (is_array($row)) {
                $is2DArray = true;
            }
            break;
        }
        if ($is2DArray) {
            echo "<thead><tr>";
            foreach ($value as $rowNKey => $row) {
                echo "<td>&nbsp;</td>";
                foreach ($row as $k => $v) {
                    echo "<td>" . htmlspecialchars($k) . "</td>";
                }
                break;
            }
            echo "</tr></thead>\n";
        }

        echo "<tbody>\n";
        foreach ($value as $k => $v) {
            echo "<tr><td>" . $k . "</td>";
            if (is_array($v)) {
                foreach ($v as $v2) {
                    echo "<td>" . htmlspecialchars($v2) . "</td>";
                }
            } else {
                echo "<td>";
                if (is_array($v)) {
                    echo (print_r($v, 1));
                } else {
                    echo htmlspecialchars($v);
                }
                echo "</td>";
            }
            echo "</tr>\n";
        }
        echo "</tbody>\n";
        echo "</table>\n";
    }

    /**
     * end the program on setup error (invalid settings)
     * only ends the program if exitProgramOnFailure is true
     * completely stops the page from loading, not just the form
     * note that errors types have a hierarchy, class, init, or query. 
     *      so if exit_on_class_error is true, we will exit on query error
     * $errorType will be class, init, or query
     */
    private function exitProgramError($errorType, $message = '')
    {
        $endProgram = false;
        if ($this->settings['exit_on_error']) {
            $endProgram = true;
        }
        if ($this->settings['exit_on_db_init_error'] && ($errorType == 'init' || $errorType == 'class')) {
            $endProgram = true;
        }
        if ($this->settings['exit_on_class_error'] || ($errorType == 'class')) {
            $endProgram = true;
        }

        if (!$endProgram) {
            return;
        }

        $errorText = 'Error';
        if (!empty($this->settings['error_output_text'])) {
            $errorText = $this->settings['error_output_text'];
        }

        // display message
        echo "\n<br><div>" . htmlspecialchars($errorText) . "<br>\n";
        if (!empty($message) && $this->settings['output_error_details']) {
            echo "<br>\n<br>\n" . htmlspecialchars($message);
        }
        echo "\n</div><br>\n";

        die();
    }

    // get the return value for the query
    // type returns of the function called
    // return values can be affected by flags
    private function getReturnValue($statement, $type, $flags)
    {
        if (!$statement) {
            return null;
        }

        if ($type == 'all') {
            if (empty($flags['key'])) {
                return $statement->fetchAll();
            }
            $fetchAll = $statement->fetchAll(PDO::FETCH_ASSOC);
            $ar = array();
            foreach ($fetchAll as $row) {
                if (isset($row[$flags['key']])) {
                    $ar[$row[$flags['key']]] = $row;
                } else {
                    $ar[] = $row;
                }
            }
            return $ar;
        }

        if ($type == 'row') {
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }
            return $row;
        }

        if ($type == 'col') {
            $ar = array();
            if (empty($flags['key']) && empty($flags['field'])) {
                $val = $statement->fetchColumn();
                while ($val !== false) {
                    $ar[] = $val;
                    $val = $statement->fetchColumn();
                }
                return $ar;
            }

            $fetchAll = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fetchAll as $row) {
                $k = null;
                $v = reset($row);

                if (!empty($flags['field'])) {
                    if (isset($row[$flags['field']])) {
                        $v = $row[$flags['field']];
                    }
                }

                if (!empty($flags['key'])) {
                    if (isset($row[$flags['key']])) {
                        $k = $row[$flags['key']];
                    }
                }

                if (!is_null($k)) {
                    $ar[$k] = $v;
                } else {
                    $ar[] = $v;
                }
            }
            return $ar;
        }

        if ($type == 'one') {
            $row = $statement->fetch(PDO::FETCH_NUM);
            if ($row) {
                return $row[0];
            }
            return '';
        }

        return null;
    }
}
