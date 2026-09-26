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
        'save_last_query_info' => true,

        // how to handle a connection or initialization error
        // valid values: 'exit', 'continue_without_database' 
        // - exit: completely end the program
        // - continue_without_database: he program continues, but subsequent database functions
        //      will not execute. functions that modify data will do nothing and functions
        //      that return data will return empty results
        'connection_error_action' => 'exit',

        // how to handle a query error - failure while executing a database query
        // valid values: 'exit', 'continue_without_database', 'continue_with_database'
        // - exit: completely end the program
        // - continue_without_database: he program continues, but subsequent database functions
        //      will not execute. functions that modify data will do nothing and functions
        //      that return data will return empty results
        // - continue_with_database: the program continues and subsequent database functions will continue normally
        'query_error_action' => 'exit',

        // html that will display with the page load is stopped
        // is only displayed when query_error_action or connection_error_action are set to "exit"
        'error_output_html' => '<div>There was an error loading the page</div>',

        // output details on the error to the screen
        // should only be true in production
        'output_error_debugging' => true,

        // the unique identifier column tables
        // used by all "ById" functions find a record by id
        // for instance, if this is set to "id", then 
        //  getRowById('users', 12) will run the query "select * from users where id=12;"
        'id_field_name' => 'id',

        // the maximum number of rows inserted per query
        // used by the insertMultiple and insertMultipleFieldsValues functions
        // higher values will be more efficient (fewer queries), but may increase memory usage
        //     and the chance of exceeding database or connection limits
        // the default (100) is a very conservative value; values in the tens of thousands
        //     will likely work fine for typical data
        'max_rows_per_insert_multiple_query' => 100,

        // the maximum number of rows to display when outputting an array using the display() function
        // if an array has more rows, values are displayed using PHP's var_dump function
        'max_debugging_display_data_rows' => 1000,

        // on query error, return null
        // affects all(), row(), column(), one(), rowById(), and oneById()
        // default behavior is to return either an empty array or empty value depending on the function
        // allows developer to distinguish between a valid query that returns no results 
        //      ("select * from users where false;") and a query error
        //      ("select * from table_does_not_exist where true")
        'return_null_on_error' => false,

        // on DELETE and UPDATE queries, make sure there is a WHERE statement
        // used to avoid accidental delete or update of all rows in a table
        // just a simple check that the string "WHERE" is in the query, could still allow delete or update on 
        //      edge cases: "DELETE from places_where_ive_been;" or "UPDATE books SET title='Where the Wild Things Are';"
        // to affect all table row, set WHERE statement to "true", ex: "DELETE FROM places_where_ive_been WHERE true;"
        'delete_and_update_require_where' => true
    );


    // connection to the database
    private $connection = null;

    // information on the most recent query (duration, result count, etc)
    private $lastQueryInfo = array();


    // errors - note that errors can come from the database itself for bad queries or from the class receiving invalid parameters
    // last (most recent) error connecting to the database. if set, database queries will be skipped because the database connection failed  
    private $lastConnectionError = '';
    // last (most recent) error from a query. could be due to an invalid parameter passed or an error from the database 
    private $lastQueryError = '';

    private $skipQueries = true;

    public function lastError()
    {
        // note that there will not be both since connection errors will stop queries from running
        if (!empty($this->lastConnectionError)) {
            return $this->lastConnectionError;
        }
        if (!empty($this->lastQueryError)) {
            return $this->lastQueryError;
        }
        return '';
    }

    private $hasDbConnection = false;

    public function hasDbConnection()
    {
        return $this->hasDbConnection;
    }



    /**
     * set a configuration setting
     * the setting name must exist in the $settings array
     * the value must be valid for the specified setting
     * example: $db->updateSetting('save_last_query_info', true);
     * supports chaining: $db->updateSetting('save_last_query_info', true)->updateSetting('output_errors', true);
     */
    public function updateSetting($setting, $value)
    {
        if (!isset($this->settings[$setting])) {
            $this->exitProgramError("DatabaseHelper updateSetting() received invalid setting: " . $setting, true);
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



    /**
     * constructor
     * connects to the database. sets the connection variable
     * may set hasDbConnection or lastConnectionError if needed
     */
    public function __construct($dbName, $host, $user, $pass)
    {
        $dbName = $this->checkIdentifier($dbName, '__construct() dbname');
        if (empty($dbName)) {
            $this->exitProgramError('empty dbname in class constructor', true);
            return false;
        }

        $opt = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_SILENT,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        );

        try {
            $this->connection = new PDO(
                'mysql:host=' . $host . ';dbname=' . $dbName . ';charset=utf8mb4',
                $user,
                $pass,
                $opt
            );
        } catch (PDOException $e) {
            $this->connection = null;
            $this->exitProgramError($e->getMessage(), true);
            return;
        }

        $this->hasDbConnection = true;
        $this->skipQueries = false;
    }


    /**
     * execute SQL when no result values are needed
     * generally only needed for delete or update
     * returns true if the query succeeds and false if it fails; 
     *      success means the query executed without errors, not that it returned results or changed any rows
     * example: $db->query('delete from customers where last_name = :last_name;', array(':last_name' => 'Jones'));
     */
    public function query($sql, $binds = array())
    {
        $isSuccess = $this->makeQuery($sql, 'query', $binds);
        return $isSuccess;
    }

    /**
     * execute SQL and return all result rows
     * returns a 2-dimensional array, with each row as an associative key/value array
     * optionally use $keyField to use a field as the array key. * note that array keys are required and unique, so null or duplicate values may result in rows being overwritten or omitted.
     * example: $customers = $db->all('select * from customers where last_name = :last_name;', array(':last_name' => 'Jones'), 'id');
     */
    public function all($sql, $binds = array(), $keyField = '')
    {
        $returnValue = $this->makeQuery($sql, 'all', $binds, $keyField);
        if (is_array($returnValue)) {
            return $returnValue;
        }
        return array();
    }

    public function all($sql, $binds = array(), $keyField = '')
    {
        $this->sql = $sql;
        $this->type = 'all';
        $this->binds = $binds;
        $this->keyField = $keyField;
        $returnValue = $this->makeQuery();
    }

    /**
     * execute SQL and return the first result row
     * returns a 1-dimensional associative array containing the row's fields and values
     * example: $customer = $db->row('select * from customers where last_name = :last_name order by first_name limit 1;', array(':last_name' => 'Jones'));
     */
    public function row($sql, $binds = array())
    {
        $return_value = $this->makeQuery($sql, 'row', $binds);
        if (is_array($return_value)) {
            return $return_value;
        }
        return array();
    }

    /**
     * get a row from a table using its identifier field (usually "id")
     * returns a 1-dimensional associative array containing the row's fields and values
     * uses the id_field_name setting to determine the identifier field
     * runs query "select * from [table] where id=[id]"
     * example: $customer = $db->rowById('customers', 25);
     */
    public function rowById($table, $id)
    {
        $table = $this->checkIdentifier($table, 'rowById() table');
        $idFieldName = $this->checkIdentifier($this->settings['id_field_name'], 'rowById() id_field_name setting');
        if (empty($table) || empty($idFieldName)) {
            return $this->getReturnInvalid('row');
        }

        $sql = 'SELECT * FROM `' . $table . '` WHERE `' . $idFieldName . '` = :id limit 1;';
        $binds = array(':id' => $id);
        return $this->row($sql, $binds);
    }

    /**
     * execute SQL and return all values from a single column
     * optionally use $keyField to use a field as the array key. * note that array keys are required and unique, so null or duplicate values may result in rows being overwritten or omitted.
     * optionally use $valueField to specify which field's values to return
     * if $valueField is not set, the first selected field is used for the values
     * for a non-associative array, leave $keyField and $valueField empty and select only one field
     * associative example: $customerNames = $db->column('select id, first_name from customers where last_name = :last_name order by first_name;', array(':last_name' => 'Jones'), 'id', 'first_name');
     * non-associative example: $customerNames = $db->col('select first_name from customers where last_name = :last_name order by first_name;', array(':last_name' => 'Jones'));
     */
    public function column($sql, $binds = array(), $keyField = '', $valueField = '')
    {
        $return_value = $this->makeQuery($sql, 'col', $binds, $keyField, $valueField);
        if (is_array($return_value)) {
            return $return_value;
        }
        return array();
    }

    /**
     * shortcut for the column function
     * example: $customerNames = $db->col('select id, first_name from customers where last_name = :last_name order by first_name;', array(':last_name' => 'Jones'), 'id', 'first_name');
     */
    public function col($sql, $binds = array(), $keyField = '', $valueField = '')
    {
        return $this->column($sql, $binds, $keyField, $valueField);
    }

    /**
     * execute SQL and return a single value from the first result row
     * optionally use $valueField to specify which field's value to return
     * if $valueField is not set, the first field's value is returned
     * it is generally better to leave $valueField empty and select only one field
     * it is better to add LIMIT 1 to the SQL query to avoid retrieving unnecessary rows
     * example usage: $firstName = $db->one('select first_name from customers where last_name = :last_name order by first_name limit 1;', array(':last_name' => 'Jones'));
     */
    public function one($sql, $binds = array(), $valueField = '')
    {
        $return_value = $this->makeQuery($sql, 'one', $binds, '', $valueField);
        if (is_string($return_value)) {
            return $return_value;
        }
        if (is_numeric($return_value)) {
            return strval($return_value);
        }
        return '';
    }

    /**
     * get a single value from a table using its identifier field (usually "id")
     * returns a single value from the specified field
     * uses the id_field_name setting to determine the identifier field
     * runs query "select [field] from [table] where id=[id]"
     * example usage: $firstName = $db->oneById('customers', 25, 'first_name');
     */
    public function oneById($table, $id, $field)
    {
        $table = $this->checkIdentifier($table, 'oneById() table');
        $idFieldName = $this->checkIdentifier($this->settings['id_field_name'], 'oneById() id_field_name setting');
        if (empty($table) || empty($idFieldName)) {
            return $this->getReturnInvalid('one');
        }

        $sql = 'SELECT `' . $field . '` FROM `' . $table . '` WHERE `' . $idFieldName . '` = :id LIMIT 1;';
        $binds = array(':id' => $id);
        return $this->one($sql, $binds);
    }

    /**
     * insert a new row into a table
     * returns the identifier (id) of the newly inserted row
     * uses the array keys as field names and the array values as field values
     * example: $newCustomerId = $db->insert('customers', array('first_name' => 'John', 'last_name' => 'Jones'));
     */
    public function insert($table, $fields)
    {
        $returnInvalid = 0;
        $table = $this->checkIdentifier($table, 'insert() table');
        if (empty($table)) {
            return $returnInvalid;
        }

        if (!is_array($fields)) {
            $this->exitProgramError('insert() - $fields is not an array (' . gettype($fields) . ' passed)');
            return $returnInvalid;
        }
        if (count($fields) < 1) {
            $this->exitProgramError('insert() - $fields array is empty (0 fields)');
            return $returnInvalid;
        }

        $fieldStr = $valueStr = '';
        $binds = array();
        $sep = '';
        foreach ($fields as $field => $value) {
            $field = $this->checkIdentifier($field, 'insert() field (array key)');
            if (empty($field)) {
                return $returnInvalid;
            }
            $fieldStr .= $sep . '`' . $field . '`';
            $valueStr .= $sep . ':' . $field;
            $binds[':' . $field] = $value;
            $sep = ', ';
        }
        $sql = 'INSERT INTO `' . $table . '` (' . $fieldStr . ') VALUES (' . $valueStr . ');';
        $this->makeQuery($sql, 'query', $binds);
        return $this->lastInsertId();
    }

    /**
     * insert a new row into a table
     * returns true if all rows are inserted successfully and false if any query fails
     * uses the array keys as field names and the array values as field values
     * all rows must contain the same field names
     * splits the insert into multiple queries when the number of rows exceeds the max_rows_per_insert_multiple_query setting
     * pre-validates all rows before inserting, so a misformatted row prevents any rows from being inserted
     * example: 
     * $isSuccess = $db->insertMultiple('customers', array(
     *     array('first_name' => 'John', 'last_name' => 'Jones'),
     *     array('first_name' => 'Susan', 'last_name' => 'Smith')
     *  ));
     */
    public function insertMultiple($table, $rows)
    {
        $returnInvalid = false;

        $table = $this->checkIdentifier($table, 'insertMultiple() table');
        if (empty($table)) {
            return $returnInvalid;
        }

        if (!is_array($rows)) {
            $this->exitProgramError('insertMultiple() - $rows is not an array (' . gettype($rows) . ' passed)');
            return $returnInvalid;
        }
        if (count($rows) < 1) {
            $this->exitProgramError('InsertMultiple() - $rows array is empty (0 rows)');
            return $returnInvalid;
        }

        // pre-check all rows

        $matchRow = array();
        $rowKeys = array();
        $matchRowCount = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $this->exitProgramError('InsertMultiple() - $rows is not a 2 dimensional array');
                return $returnInvalid;
            }

            if (empty($matchRow)) {
                // our first row that we will use to compare to others has not been set yet
                // do some validation and set it

                if (count($row) < 1) {
                    $this->exitProgramError('InsertMultiple() - the first row in $rows is empty (0 fields)');
                    return $returnInvalid;
                }

                foreach ($row as $field => $value) {
                    $fieldClean = $this->checkIdentifier($field, 'insertMultiple() row field (array key)');
                    if (empty($fieldClean)) {
                        $this->exitProgramError('InsertMultiple() - $row has a row with an invalid field name (array key)');
                        return $returnInvalid;
                    }
                }

                // $matchRow will be compared with every other row, in these checks keys must be the same
                $matchRow = $row;
                $matchRowCount = count($matchRow);
                $rowKeys = array_keys($matchRow);
            } else {
                if ($matchRowCount !== count($row)  || array_diff_key($row, $matchRow)) {
                    $this->exitProgramError('InsertMultiple() - mismatched field name (array key) in $rows');
                    return $returnInvalid;
                }
            }
        }

        $fieldStr = '';
        foreach ($rowKeys as $key) {
            if (!empty($fieldStr)) {
                $fieldStr .= ',';
            }
            $fieldStr .= '`' . $key . '`';
        }

        $maxInsertRows = $this->settings['max_rows_per_insert_multiple_query'];

        $valStr = '';
        $binds = array();
        $ctr = 0;
        $isFirstRow = true;
        foreach ($rows as $row) {
            $ctr++;
            if ($isFirstRow) {
                $isFirstRow = false;
            } else {
                $valStr .= ',';
            }

            $isFirstField = true;
            $valStr .= '(';
            foreach ($rowKeys as $field) {
                if ($isFirstField) {
                    $isFirstField = false;
                } else {
                    $valStr .= ',';
                }
                $bindKey = ':' . $field . '_' . $ctr;
                $valStr .= $bindKey;
                $binds[$bindKey] = $row[$field];
            }
            $valStr .= ')';

            if ($ctr >= $maxInsertRows) {
                $sql = 'insert into `' . $table . '` (' . $fieldStr . ') values ' . $valStr . ';';
                $returnValue = $this->makeQuery($sql, 'query', $binds);
                if (!$returnValue) {
                    return false;
                }
                $isFirstRow = true;
                $valStr = '';
                $binds = array();
                $ctr = 0;
            }
        }

        if (!empty($valStr)) {
            // insert remaining unprocessed records
            $sql = 'insert into `' . $table . '` (' . $fieldStr . ') values ' . $valStr . ';';
            $this->display($sql);
            $this->display($binds);
            $returnValue = $this->makeQuery($sql, 'query', $binds);
            if (!$returnValue) {
                return $returnInvalid;
            }
        }

        return true;
    }

    /**
     * insert multiple new rows into a table using a separate array of field names
     * returns true if all rows are inserted successfully and false if any query fails
     * uses $fields for the field names and $dataRows for the row values
     * all rows must contain the same number of values as the number of fields
     * pre-validates all rows before inserting, so a misformatted row prevents any rows from being inserted
     * splits the insert into multiple queries when the number of rows exceeds the max_rows_per_insert_multiple_query setting
     * example: 
     * $isSuccess = $db->insertMultipleFieldsValues(
     *      'customers', 
     *      array('first_name', 'last_name'), 
     *      array(
     *          array('John', 'Jones'),
     *          array('Jane', 'Smith')
     *      )
     * );
     */
    public function insertMultipleFieldsValues($table, $fields, $dataRows)
    {
        $returnInvalid = false;

        $table = $this->checkIdentifier($table, 'insertMultipleFieldsValues() table');
        if (empty($table)) {
            return $returnInvalid;
        }

        if (!is_array($fields)) {
            $this->exitProgramError('insertMultipleFieldsValues() - $fields is not an array (' . gettype($fields) . ' passed)');
            return $returnInvalid;
        }

        $fieldCount = count($fields);
        if ($fieldCount < 1) {
            $this->exitProgramError('insertMultipleFieldsValues() - $fields array is empty (0 rows)');
            return $returnInvalid;
        }

        if (!is_array($dataRows)) {
            $this->exitProgramError('insertMultipleFieldsValues() - $dataRows is not an array (' . gettype($dataRows) . ' passed)');
            return $returnInvalid;
        }
        if (count($dataRows) < 1) {
            $this->exitProgramError('insertMultipleFieldsValues() - $dataRows array is empty (0 rows)');
            return $returnInvalid;
        }

        // pre-check all rows

        foreach ($dataRows as $row) {
            if (!is_array($row)) {
                $this->exitProgramError('insertMultipleFieldsValues() - $dataRows is not a 2 dimensional array');
                return $returnInvalid;
            }

            if ($fieldCount !== count($row)) {
                $this->exitProgramError('insertMultipleFieldsValues() - mismatched field count in $dataRows rows');
                return $returnInvalid;
            }
        }

        $fieldStr = '';
        foreach ($fields as $field) {
            $field = $this->checkIdentifier($field, 'insertMultipleFieldsValues() field');
            if (empty($field)) {
                return $returnInvalid;
            }

            if (!empty($fieldStr)) {
                $fieldStr .= ',';
            }
            $fieldStr .= '`' . $field . '`';
        }

        $maxInsertRows = $this->settings['max_rows_per_insert_multiple_query'];

        $valStr = '';
        $binds = array();
        $ctr = 0;
        $isFirstRow = true;
        foreach ($dataRows as $row) {
            $ctr++;
            if ($isFirstRow) {
                $isFirstRow = false;
            } else {
                $valStr .= ',';
            }

            $isFirstField = true;
            $valStr .= '(';
            $valCtr = 0;
            foreach ($row as $value) {
                if ($isFirstField) {
                    $isFirstField = false;
                } else {
                    $valStr .= ',';
                }
                $valCtr++;
                $bindKey = ':v_' . $ctr . '_' . $valCtr;
                $valStr .= $bindKey;
                $binds[$bindKey] = $value;
            }
            $valStr .= ')';

            if ($ctr >= $maxInsertRows) {
                $sql = "insert into `" . $table . "` (" . $fieldStr . ") values " . $valStr . ";";
                $returnValue = $this->makeQuery($sql, 'query', $binds);
                if (!$returnValue) {
                    return false;
                }
                $isFirstRow = true;
                $valStr = '';
                $binds = array();
                $ctr = 0;
            }
        }

        if (!empty($valStr)) {
            $sql = "insert into `" . $table . "` (" . $fieldStr . ") values " . $valStr . ";";
            $returnValue = $this->makeQuery($sql, 'query', $binds);
            if (!$returnValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * update rows in a table
     * returns true if the query succeeds and false if it fails
     * uses $values to specify the fields and values to update
     * uses $whereSql to specify which rows to update
     * $whereSql is required to avoid mistakes; if all rows need to be updated, set $whereSql to "true" to match all rows.
     * optionally use $whereBinds to bind values to the $whereSql statement
     * example: $isSuccess = $db->update('customers', array('first_name' => 'John'), 'last_name = :last_name', array(':last_name' => 'Jones'));
     */
    public function update($table, $values, $whereSql, $whereBinds = array())
    {
        $returnInvalid = false;

        $table = $this->checkIdentifier($table, 'table');
        if (empty($table)) {
            return $returnInvalid;
        }

        if (!is_array($values)) {
            $this->exitProgramError('update() - $values is not an array (' . gettype($values) . ' passed)');
            return $returnInvalid;
        }
        if (count($values) < 1) {
            $this->exitProgramError('update() - an empty values array (0 values) was passed to update function function');
            return $returnInvalid;
        }

        if (empty($whereSql)) {
            $this->exitProgramError('update() - "where" statement was not passed to update function');
            return $returnInvalid;
        }

        $setStr = '';
        $ctr = 0;
        $binds = $whereBinds;
        $currentUnixTime = time();
        foreach ($values as $field => $value) {
            $field = $this->checkIdentifier($field, 'update() field');
            if (empty($field)) {
                return $returnInvalid;
            }

            if (!empty($setStr)) {
                $setStr .= ',';
            }
            $ctr++;
            $bindKey = ':set_v_' . $field . '_' . $ctr . '_' . $currentUnixTime;
            $setStr .= '`' . $field . '`=' . $bindKey;
            $binds[$bindKey] = $value;
        }
        $sql = 'UPDATE `' . $table . '` SET ' . $setStr . ' WHERE ' . $whereSql . ';';
        return $this->makeQuery($sql, 'query', $binds);
    }

    /**
     * update a row in a table using its identifier field (usually "id")
     * returns true if the query succeeds and false if it fails
     * uses the id_field_name setting to determine the identifier field
     * example: $isSuccess = $db->updateById('customers', array('first_name' => 'John'), 25);
     */
    public function updateById($table, $values, $id)
    {
        $table = $this->checkIdentifier($table, 'updateById() table');
        if (empty($table)) {
            return $this->getReturnInvalid('query');
        }
        $idFieldName = $this->checkIdentifier($this->settings['id_field_name'], 'updateById() id_field_name setting');
        if (empty($idFieldName)) {
            return $this->getReturnInvalid('query');
        }


        $whereSql = '`' . $idFieldName . '`=:id';
        $binds = array(':id' => $id);
        return $this->update($table, $values, $whereSql, $binds);
    }

    /**
     * delete rows from a table
     * returns true if the query succeeds and false if it fails
     * uses $whereSql to specify which rows to delete
     * $whereSql is required to avoid mistakes; if all rows need to be deleted, set $whereSql to "true" to match all rows.
     * optionally use $whereBinds to bind values to the $whereSql statement
     * example: $isSuccess = $db->delete('customers', 'last_name = :last_name', array(':last_name' => 'Jones'));
     */
    public function delete($table, $whereSql, $whereBinds = array())
    {
        $table = $this->checkIdentifier($table, 'delete() table');
        if (empty($table)) {
            return $this->getReturnInvalid('query');
        }

        if (empty($whereSql)) {
            $this->exitProgramError('delete() - $whereSql is required');
            return $this->getReturnInvalid('query');
        }

        $sql = 'DELETE FROM `' . $table . '` WHERE ' . $whereSql . ';';
        return $this->makeQuery($sql, 'query', $whereBinds);
    }

    /**
     * delete a row from a table using its identifier field (usually "id")
     * returns true if the query succeeds and false if it fails
     * uses the id_field_name setting to determine the identifier field
     * runs query "delete from [table] where id=[id]"
     * example: $isSuccess = $db->deleteById('customers', 25);
     */
    public function deleteById($table, $id)
    {
        $table = $this->checkIdentifier($table, 'deleteById() table');
        if (empty($table)) {
            return $this->getReturnInvalid('query');
        }
        $idFieldName = $this->checkIdentifier($this->settings['id_field_name'], 'deleteById() id_field_name setting');
        if (empty($table) || empty($idFieldName)) {
            return $this->getReturnInvalid('query');
        }

        $whereSql = '`' . $idFieldName . '`=:id';
        $binds = array(':id' => $id);
        return $this->delete($table, $whereSql, $binds);
    }

    /**
     * prepare and execute SQL
     * used internally by public methods to process queries
     * uses $type to determine how query results are returned
     * uses $binds to bind values to the query
     * uses $keyField and $valueField to determine how returned data is structured
     */
    private function makeQuery($sql, $type, $binds = array(), $keyField = '', $valueField = '')
    {
        $returnInvalid = $this->getReturnInvalid($type);

        if ($this->skipQueries) {
            return  $returnInvalid;
        }

        // convert any falsy value to an empty array. this way a user can pass null
        if (empty($binds)) {
            $binds = array();
        }

        // trim the sql, start or end white space will not affect the query so we don't need it
        if (is_string($sql)) {
            // only trim string type. $sql should be a string, but if not we want to maintain the original $sql value for debugging.
            $sql = trim($sql);
        }

        $this->resetInfo($sql, $type, $binds, $keyField, $valueField);

        if (empty($sql)) {
            $this->exitProgramError('makeQuery() - $sql is empty');
            return $returnInvalid;
        }

        if (!is_string($sql)) {
            $this->exitProgramError('makeQuery() - $sql is not a string');
            return $returnInvalid;
        }

        if (!empty($this->settings['delete_and_update_require_where'])) {
            // check if an UPDATE or DELETE query 
            $sqlFirst6Lc = strtolower(substr($sql, 0, 6));
            if ($sqlFirst6Lc === 'update' || $sqlFirst6Lc === 'delete') {
                if (stripos($sql, 'where') === false) {
                    $this->exitProgramError(strtoupper($sqlFirst6Lc) . ' query requires a WHERE statement');
                    return $returnInvalid;
                }
            }
        }

        $statement = $this->connection->prepare($sql);
        if (!$statement) {
            $this->setInfo(false, $statement);
            return $returnInvalid;
        }

        foreach ($binds as $field => $value) {
            $field = $this->checkIdentifier($field, 'field', true);
            if (empty($field)) {
                return $returnInvalid;
            }
            $success =  $statement->bindValue($field, $value);
            if (!$success) {
                //$this->set_con_error();
                $this->setInfo(false, $statement);
                return $returnInvalid;
            }
        }
        $success = $statement->execute();

        $this->setInfo($success, $statement);

        if (!$success) {
            //$this->set_con_error();
            return $returnInvalid;
        }

        return $this->getReturnValue($statement, $type, $keyField, $valueField);
    }



    /**
     * process query results and return the appropriate value
     * used internally by makeQuery() to process returned data
     * uses $type to determine how query results are returned
     * uses $keyField and $valueField to determine how returned data is structured
     * returns the appropriate invalid value when the query cannot be processed or fails
     */
    private function getReturnValue($statement, $type, $keyField = '', $valueField = '')
    {
        $returnInvalid = $this->getReturnInvalid($type);

        if (!$statement) {
            return $returnInvalid;
        }

        if ($type == 'all') {
            $fetchAll = $statement->fetchAll(PDO::FETCH_ASSOC);
            if (empty($keyField)) {
                return $fetchAll;
            }

            $ar = array();
            foreach ($fetchAll as $row) {
                if (!array_key_exists($keyField, $row)) {
                    $this->exitProgramError("key field ($keyField) is not found. available fields: " . implode(', ', array_keys($row)));
                    return $returnInvalid;
                }
                $ar[$row[$keyField]] = $row;
            }
            return $ar;
        }

        if ($type == 'row') {
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return array();
            }
            return $row;
        }

        if ($type == 'col') {
            $ar = array();
            if (empty($keyField) && empty($valueField)) {
                $val = $statement->fetchColumn();
                while ($val !== false) {
                    $ar[] = $val;
                    $val = $statement->fetchColumn();
                }
                return $ar;
            }

            $fetchAll = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($fetchAll as $row) {
                $k = $v = null;

                if (!empty($valueField)) {
                    if (!array_key_exists($valueField, $row)) {
                        $this->exitProgramError("value field ($valueField) is not found. available fields: " . implode(', ', array_keys($row)));
                        return $returnInvalid;
                    }
                    $v = $row[$valueField];
                } else {
                    $v = reset($row);
                }

                if (!empty($keyField)) {
                    if (!array_key_exists($keyField, $row)) {
                        $this->exitProgramError("key field ($keyField) is not found. available fields: " . implode(', ', array_keys($row)));
                        return $returnInvalid;
                    }
                    $k = $row[$keyField];
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
            if (empty($valueField)) {
                $row = $statement->fetch(PDO::FETCH_NUM);
                if ($row) {
                    return $row[0];
                }
            } else {
                $row = $statement->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    if (!array_key_exists($valueField, $row)) {
                        $this->exitProgramError("value field ($valueField) is not found. available fields: " . implode(', ', array_keys($row)));
                        return $returnInvalid;
                    }
                    return $row[$valueField];
                }
            }
            return '';
        }

        return true;
    }

    /**
     * check a database identifier such as a table or field name
     * should only be letters, numbers, and underscores.
     * if invalid, produce an error (will end the program depending on settings)
     * if invalid and the program settings do not end the program on error, return an empty string.
     */
    private function checkIdentifier($identifier, $type, $isBind = false)
    {
        if ($isBind) {
            $identifier = ltrim($identifier, ':');
        }

        if (empty($identifier)) {
            $message = $type . ' is empty';
            $this->exitProgramError($message);
            return '';
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            $message = $type . ' is invalid ' . var_export($identifier, true)
                . ' | must be only letters, numbers, and underscores and cannot start with a number';
            $this->exitProgramError($message);
            return '';
        }

        if ($isBind) {
            $identifier = ':' . $identifier;
        }

        return $identifier;
    }

    /**
     * determine the value to return when a query fails or returns no results
     * used internally by makeQuery() and getReturnValue()
     * returns null for errors when the return_null_on_error setting is enabled
     * otherwise returns a value based on the requested return type
     */
    private function getReturnInvalid($type)
    {
        if ($type === 'query') {
            //  note that queries have boolean return values (success or fail). no need for them to be 
            //      null even if return_null_on_error is set
            return false;
        }
        if ($this->settings['return_null_on_error']) {
            return null;
        }
        if ($type === 'one') {
            return '';
        }

        return array();
    }


    /**
     * reset information for the current query
     * function is called when a new query begins
     */
    private function resetInfo($sql, $type, $binds, $keyField, $valueField)
    {
        $this->lastQueryInfo['insert_id'] = 0;
        $this->lastQueryInfo['sql'] = $sql;
        $this->lastQueryInfo['is_success'] = false;
        $this->lastQueryInfo['row_count'] = $this->lastQueryInfo['duration'] = 0;
        $this->lastQueryInfo['type'] = $type;

        if ($this->settings['save_last_query_info']) {
            $this->lastQueryInfo['bind_ar'] = $binds;
            $this->lastQueryInfo['key_field'] = $keyField;
            $this->lastQueryInfo['value_field'] = $valueField;
            $this->lastQueryInfo['db_class_error'] = $this->lastQueryInfo['error'] = '';
            $this->lastQueryInfo['start_time'] = microtime(true);
            $this->lastQueryInfo['end_time'] = null;
        }
    }




    /**
     * set information for the current query
     * function is called after the query has processed
     */
    private function setInfo($success, $statement = null)
    {
        $this->lastQueryInfo['row_count'] = 0;
        $this->lastQueryInfo['insert_id'] = 0;
        $this->lastQueryInfo['is_success'] = false;

        if ($success) {
            $this->lastQueryInfo['is_success'] = true;
            if ($statement) {
                $rowCount = $statement->rowCount();
                if (!empty($rowCount)) {
                    $this->lastQueryInfo['row_count'] = $rowCount;
                }
            }
        } else {
            $errorMsg = 'Query Failed';
            if ($statement) {
                $errorMsg  = $statement->errorInfo();
            } else {
                $e = $this->connection->errorInfo();
                if (!empty($e)) {
                    $errorMsg  = $e;
                }
            }
            $this->lastQueryInfo['error'] = implode(' | ', $errorMsg);
            $this->exitProgramError($this->lastQueryInfo['error']);
        }

        $sqlFirst6Lc = strtolower(substr($this->lastQueryInfo['sql'], 0, 6));
        if ($success && ($this->lastQueryInfo['type'] === 'insert' || $sqlFirst6Lc == 'insert')) {
            $this->lastQueryInfo['insert_id'] = $this->connection->lastInsertId();
        }

        if ($this->settings['save_last_query_info']) {
            $this->lastQueryInfo['end_time'] = microtime(true);
            $this->lastQueryInfo['duration'] = (($this->lastQueryInfo['end_time'] - $this->lastQueryInfo['start_time']));
        }
    }

    /**
     * get the number of rows affected by the last query
     * example: $rowCount = $db->lastRowCount();
     */
    public function lastRowCount()
    {
        if (empty($this->lastQueryInfo['row_count'])) {
            return 0;
        }
        return $this->lastQueryInfo['row_count'];
    }

    /**
     * get the identifier of the last query
     * will be 0 unless the last query was a successful insert
     * example: $id = $db->lastInsertId();
     */
    public function lastInsertId()
    {
        if (empty($this->lastQueryInfo['insert_id'])) {
            return 0;
        }
        return $this->lastQueryInfo['insert_id'];
    }

    /**
     * return if the last query was successful
     * will be boolean (true or false)
     * example: $isSuccess = $db->lastQuerySuccessful();
     */
    public function lastQuerySuccessful()
    {
        if (!empty($this->lastQueryInfo['is_success'])) {
            return true;
        }
        return false;
    }

    /**
     * return if the last query was successful
     * will be boolean (true or false)
     * example: $isSuccess = $db->success();
     */
    public function success()
    {
        return $this->lastQuerySuccessful();
    }

    /**
     * get information on the last SQL query executed
     * data includes: raw SQL, binds, duration, errors, etc
     * 
     * example: $sql = $db->lastQueryInfo();
     */
    public function lastQueryInfo()
    {
        return $this->lastQueryInfo;
    }

    /**
     * display information about the last query
     * data includes: raw SQL, binds, duration, errors, etc
     * example: $db->displayLastQuery();
     */
    public function displayLastQueryInfo()
    {
        $this->display($this->lastQueryInfo);
    }

    /**
     * shortcut for displayLastQuery
     * example: $db->info();
     */
    public function info()
    {
        $this->displayLastQueryInfo();
    }

    /**
     * display a value for debugging
     * displays arrays as an HTML table to make query results easier to read
     * displays other values using var_dump
     * example: $db->display($customers);
     */
    public function display($value)
    {
        $this->displayInternal($value, true);
    }

    /**
     * display a value for debugging
     * called by display() to process and display the value
     * displays arrays with matching row fields as an HTML table
     * recursively displays other arrays as HTML tables
     * uses var_dump for values that cannot be displayed as a table
     */
    private function displayInternal($value, $isTopLevel)
    {
        if ($isTopLevel) {
            echo "\n<style>" .
                "pre.db_class_preview1 {} " .
                "table.db_class_preview1 { border-collapse: collapse; } " .
                "table.db_class_preview1 td, table.db_class_preview1 th { padding: 6px 10px; border: 1px solid #777; } " .
                "table.db_class_preview1 td td { padding: 4px; border: 1px solid #AAA; } " .
                "</style>\n";
        }

        if (!is_array($value)) {

            if (
                $isTopLevel ||
                (empty($value) && $value !== 0 && $value !== 0.0 && $value !== '0') ||
                (!is_string($value) && !is_int($value) && !is_float($value))
            ) {
                echo "\n<pre class=\"db_class_preview1\">";
                var_dump($value);
                echo "</pre>\n";
            } else {
                echo htmlspecialchars($value);
            }

            return;
        }

        $count = count($value);
        if ($count < 1 || $count > $this->settings['max_debugging_display_data_rows']) {
            echo "\n<pre class=\"db_class_preview1\">";
            var_dump($value);
            echo "</pre>\n";
            return;
        }

        $is2DArray = true;
        $matchCols = $colKeys = array();
        $colCount  = 0;
        foreach ($value as $row) {

            if (!is_array($row) || count($row) < 1) {
                $is2DArray = false;
                break;
            }

            if ($colCount === 0) {
                // $matchCols will be compared with columns in every other row, in these checks keys must be the same
                $matchCols = $row;
                $colCount = count($row);
                $colKeys = array_keys($row);
            } else {
                if ($colCount !== count($row) || array_diff_key($row, $matchCols)) {
                    $is2DArray = false;
                    break;
                }
            }

            foreach ($row as $col) {
                if (!is_string($col) && !is_int($col) && !is_float($col)) {
                    $is2DArray = false;
                    break 2;
                }
            }
        }


        if ($is2DArray) {
            echo "\n<table class=\"db_class_preview1\"><thead><tr><th><i>key</i></th>";
            foreach ($colKeys as $colKey) {
                echo "<th>" . htmlspecialchars($colKey) . "</th>";
            }
            echo "</tr></thead>\n";
            echo "<tbody>\n";
            foreach ($value as $k => $v) {
                echo "<tr><td>" . htmlspecialchars($k) . "</td>";
                foreach ($v as $v2) {
                    echo "<td>" . htmlspecialchars($v2) . "</td>";
                }
                echo "</tr>\n";
            }

            echo "</tbody></table>\n";
            return;
        }

        // an array, but not 2d (not easily converted to a row/column table)

        echo "\n<table class=\"db_class_preview1\"><tbody>\n";
        foreach ($value as $k => $v) {
            echo "<tr><td>" . htmlspecialchars($k) . "</td><td>";
            $this->displayInternal($v, false);
            echo "</td></tr>\n";
        }
        echo "</tbody></table>\n";
    }


    /**
     * handle a database connection or query error.
     * end program (with optional output), continue without database, or continue with database based on settings
     * see connection_error_action, query_error_action, error_output_html, and output_error_debugging settings
     */
    private function exitProgramError($errorMessage = '', $isConnectionError = true)
    {
        $endProgram = false;
        if ($isConnectionError) {
            $this->lastConnectionError = $errorMessage;
            $this->skipQueries = true;
            if ($this->settings['connection_error_action'] === 'continue_without_database') {
                // continue
            } else {
                $endProgram = true;
            }
        } else {
            $this->lastQueryError = $errorMessage;
            if ($this->settings['query_error_action'] === 'continue_with_database') {
                // continue
            } elseif ($this->settings['query_error_action'] === 'continue_without_database') {
                $this->skipQueries = true;
            } else {
                $endProgram = true;
            }
        }

        if (!$endProgram) {
            return;
        }

        // output and end the program

        $errorHtml = 'Database Error';
        if (!empty($this->settings['error_output_html'])) {
            $errorHtml = $this->settings['error_output_html'];
        }
        echo "\n\n<br>\n<br>\n<div>\n" . $errorHtml . "</div>";

        if ($this->settings['output_error_debugging']) {
            echo "\n\n<br>\n<br>\n<div>\n" . htmlentities($errorMessage) . "</div>\n\n";

            $this->displayLastQueryInfo();

            echo "\n\n";

            $backtrace = debug_backtrace();
            $backtraceData = array();
            foreach ($backtrace as $bt) {
                $file = $line = $function = $class = $args = '';
                if (!empty($bt['file'])) {
                    $file = $bt['file'];
                }
                if (!empty($bt['line'])) {
                    $line = $bt['line'];
                }
                if (!empty($bt['function'])) {
                    $function = $bt['function'];
                }
                if (!empty($bt['class'])) {
                    $class = $bt['class'];
                }
                $backtraceData[] = array(
                    'file' => $file,
                    'line' =>  $line,
                    'function' => $function,
                    'class' => $class
                );
            }
            $this->display($backtraceData);
        }

        die();
    }
}
