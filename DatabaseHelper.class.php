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

        // text that will display with the page load is stopped
        'error_output_text' => 'There was an error loading the page',

        // output details on the error to the screen
        // should only be true in production
        'output_error_details' => true,

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

        // on query error, return null
        // affects all(), row(), column(), one(), rowById(), and oneById()
        // default behavior is to return either an empty array or empty value depending on the function
        // allows developer to distinguish between a valid query that returns no results 
        //      ("select * from users where false;") and a query error
        //      ("select * from table_does_not_exist where true")
        'return_null_on_error' => false
    );


    // connection to the database
    private $connection = null;

    // information on the most recent query (duration, result count, etc)
    private $lastQuery = array();

    // insert id for the most recent query
    private $lastInsertId = 0;
    private $lastRowCount = null;

    private $lastConnectionError = '';
    private $lastQueryError = '';


    private $hasDbConnection = false;

    public function getHasDbConnection()
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



    /**
     * constructor
     * connects to the database. sets the connection variable
     * may set hasDbConnection or lastConnectionError if needed
     */
    public function __construct($dbName, $host, $user, $pass)
    {
        $dbName = $this->checkIdentifier($dbName, 'table');
        if (empty($dbName)) {
            $this->lastConnectionError = 'empty table name in class initiation';
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
            $this->lastConnectionError = $e->getMessage();
            return;
        }

        $this->hasDbConnection = true;
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
        $isSuccess = $this->makeQuery($sql, 'isSuccess', $binds);
        return $isSuccess;
    }

    /**
     * execute SQL and return all result rows
     * returns a 2-dimensional array, with each row as an associative key/value array
     * optionally use $keyField to use a field as the key of the main array
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
        $table = $this->checkIdentifier($table, 'table');
        $idFieldName = $this->checkIdentifier($this->settings['id_field_name'], 'id field');
        if (empty($table) || empty($idFieldName)) {
            return array();
        }

        $sql = 'SELECT * FROM `' . $table . '` WHERE `' . $idFieldName . '` = :id limit 1;';
        $binds = array(':id' => $id);
        return $this->row($sql, $binds);
    }

    /**
     * execute SQL and return all values from a single column
     * optionally use $keyField to use a field as the array key
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
        $table = $this->checkIdentifier($table, 'table');
        $idFieldName = $this->checkIdentifier($this->settings['id_field_name'], 'id field');
        $field = $this->checkIdentifier($field, 'field');
        if (empty($table) || empty($idFieldName) || empty($field)) {
            return '';
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
        $table = $this->checkIdentifier($table, 'table');
        if (empty($table)) {
            return 0;
        }

        if (!is_array($fields)) {
            $this->exitProgramError('class', 'fields passed to insert are not an array');
            return 0;
        }
        if (count($fields) < 1) {
            $this->exitProgramError('class', 'empty array of fields (0 fields) were passed to insert function');
            return 0;
        }

        $fieldStr = $valueStr = '';
        $binds = array();
        $sep = '';
        foreach ($fields as $field => $value) {
            $field = $this->checkIdentifier($field, 'field');
            if (empty($field)) {
                return 0;
            }
            $fieldStr .= $sep . '`' . $field . '`';
            $valueStr .= $sep . ':' . $field;
            $binds[':' . $field] = $value;
            $sep = ', ';
        }
        $sql = 'INSERT INTO `' . $table . '` (' . $fieldStr . ') VALUES (' . $valueStr . ');';
        $this->makeQuery($sql, 'query', $binds);
        return $this->lastInsertId;
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
        $table = $this->checkIdentifier($table, 'table');
        if (empty($table)) {
            return false;
        }

        if (!is_array($rows)) {
            $this->exitProgramError('class', 'rows passed to InsertMultiple are not an array');
            return false;
        }
        if (count($rows) < 1) {
            $this->exitProgramError('class', 'empty array of rows (0 rows) were passed to InsertMultiple function');
            return false;
        }

        // pre-check all rows

        $matchRow = array();
        $rowKeys = array();
        $matchRowCount = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $this->exitProgramError('class', '$rows passed to insertMultiple must be a 2 dimensional array');
                return false;
            }

            if (empty($matchRow)) {
                // our first row that we will use to compare to others has not been set yet
                // do some validation and set it

                if (count($row) < 1) {
                    $this->exitProgramError('class', 'the first row of $rows is empty (0 fields) in the InsertMultiple function');
                    return false;
                }

                foreach ($row as $field => $value) {
                    $fieldClean = $this->checkIdentifier($field, 'field');
                    if (empty($fieldClean)) {
                        return false;
                    }
                }

                // $matchRow will be compared with every other row, in these checks keys must be the same
                $matchRow = $row;
                $matchRowCount = count($matchRow);
                $rowKeys = array_keys($matchRow);
            } else {
                if ($matchRowCount !== count($row)  || array_diff_key($row, $matchRow)) {
                    $this->exitProgramError('class', 'mismatched field names (array keys) in InsertMultiple');
                    return false;
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
            $this->display($sql);
            $this->display($binds);
            $returnValue = $this->makeQuery($sql, 'query', $binds);
            if (!$returnValue) {
                return false;
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
        $table = $this->checkIdentifier($table, 'table');
        if (empty($table)) {
            return false;
        }

        if (!is_array($fields)) {
            $this->exitProgramError('class', 'fields passed to insertMultipleFieldsValues are not an array');
            return false;
        }

        $fieldCount = count($fields);
        if ($fieldCount < 1) {
            $this->exitProgramError('class', 'empty array of fields (0 fields) were passed to insertMultipleFieldsValues function');
            return false;
        }

        if (!is_array($dataRows)) {
            $this->exitProgramError('class', 'dataRows passed to insertMultipleFieldsValues are not an array');
            return false;
        }
        if (count($dataRows) < 1) {
            $this->exitProgramError('class', 'empty array of dataRows (0 rows) were passed to insertMultipleFieldsValues function');
            return false;
        }

        // pre-check all rows

        foreach ($dataRows as $row) {
            if (!is_array($row)) {
                $this->exitProgramError('class', '$dataRows passed to insertMultipleFieldsValues must be a 2 dimensional array');
                return false;
            }

            if ($fieldCount !== count($row)) {
                $this->exitProgramError('class', 'mismatched number of values in a row in insertMultipleFieldsValues');
                return false;
            }
        }

        $fieldStr = '';
        foreach ($fields as $field) {
            $field = $this->checkIdentifier($field, 'field');
            if (empty($field)) {
                return false;
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
     * uses $where to specify which rows to update
     * $where is required to avoid mistakes; if all rows need to be updated, set $where to "true" to match all rows.
     * optionally use $whereBinds to bind values to the $where statement
     * example: $isSuccess = $db->update('customers', array('first_name' => 'John'), 'last_name = :last_name', array(':last_name' => 'Jones'));
     */
    public function update($table, $values, $where, $whereBinds = array())
    {
        $table = $this->checkIdentifier($table, 'table');
        if (empty($table)) {
            return false;
        }

        if (!is_array($values)) {
            $this->exitProgramError('class', 'values passed to update function are not an array');
            return false;
        }
        if (count($values) < 1) {
            $this->exitProgramError('class', 'an empty values array (0 values) was passed to update function function');
            return false;
        }

        if (empty($where)) {
            $this->exitProgramError('class', '"where" statement was not passed to update function');
            return false;
        }

        $setStr = '';
        $ctr = 0;
        $binds = $whereBinds;
        $currentUnixTime = time();
        foreach ($values as $field => $value) {
            $field = $this->checkIdentifier($field, 'field');
            if (empty($field)) {
                return false;
            }

            if (!empty($setStr)) {
                $setStr .= ',';
            }
            $ctr++;
            $bindKey = ':set_v_' . $field . '_' . $ctr . '_' . $currentUnixTime;
            $setStr .= '`' . $field . '`=' . $bindKey;
            $binds[$bindKey] = $value;
        }
        $sql = 'UPDATE `' . $table . '` SET ' . $setStr . ' WHERE ' . $where . ';';
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
        $table = $this->checkIdentifier($table, 'table');
        $idFieldName = $this->checkIdentifier($this->settings['id_field_name'], 'id field');
        if (empty($table) || empty($idFieldName)) {
            return false;
        }

        $where = '`' . $idFieldName . '`=:id';
        $binds = array(':id' => $id);
        return $this->update($table, $values, $where, $binds);
    }

    /**
     * delete rows from a table
     * returns true if the query succeeds and false if it fails
     * uses $where to specify which rows to delete
     * $where is required to avoid mistakes; if all rows need to be deleted, set $where to "true" to match all rows.
     * optionally use $whereBinds to bind values to the $where statement
     * example: $isSuccess = $db->delete('customers', 'last_name = :last_name', array(':last_name' => 'Jones'));
     */
    public function delete($table, $where, $whereBinds = array())
    {
        $table = $this->checkIdentifier($table, 'table');
        if (empty($table)) {
            return false;
        }

        if (empty($where)) {
            $this->exitProgramError('query', '"where" statement was not passed to delete function');
            return false;
        }

        $sql = 'DELETE FROM `' . $table . '` WHERE ' . $where . ';';
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
        $table = $this->checkIdentifier($table, 'table');
        $idFieldName = $this->checkIdentifier($this->settings['id_field_name'], 'id field');
        if (empty($table) || empty($idFieldName)) {
            return false;
        }

        $where = '`' . $idFieldName . '`=:id';
        $binds = array(':id' => $id);
        return $this->delete($table, $where, $binds);
    }

    

    // prepare and execute SQL
    // used internally by the public methods process queries
    private function makeQuery($sql, $type, $binds = array(), $keyField = '', $valueField = '')
    {
        $returnInvalid = $this->getReturnInvalid($type);

        if (!empty($this->lastConnectionError) || !$this->hasDbConnection) {
            return  $returnInvalid;
        }
        if (!empty($this->lastQueryError) && $this->settings['query_error_action'] != 'continue_with_database') {
            return  $returnInvalid;
        }

        // convert any falsy value to an empty array. this way a user can pass null
        if (empty($binds)) {
            $binds = array();
        }

        $this->resetInfo($sql, $type, $binds, $keyField, $valueField);

        if (!is_string($sql)) {
            return $returnInvalid;
        }

        //if (!$this->validate_where($sql))
        //{
        //	return null;
        //}

        $statement = $this->connection->prepare($sql);
        if (!$statement) {
            $this->setInfo($statement);
            return $returnInvalid;
        }

        foreach ($binds as $field => $value) {
            $field = $this->checkIdentifier($field, 'field', true);
            $statement->bindValue($field, $value);
            if (!$statement) {
                //$this->set_con_error();
                $this->setInfo($statement);
                return $returnInvalid;
            }
        }
        $statement->execute();

        $this->setInfo($statement);

        if (!$statement) {
            //$this->set_con_error();
            return $returnInvalid;
        }

        return $this->getReturnValue($statement, $type, $keyField, $valueField);
    }

    // get the return value for the query
    // type returns of the function called
    private function getReturnValue($statement, $type, $keyField = '', $valueField = '')
    {
        $returnInvalid = $this->getReturnInvalid($type);

        if (!$statement) {
            return $returnInvalid;
        }

        if ($type == 'all') {
            if (empty($keyField)) {
                return $statement->fetchAll();
            }
            $fetchAll = $statement->fetchAll(PDO::FETCH_ASSOC);
            $ar = array();
            foreach ($fetchAll as $row) {
                if (isset($row[$keyField])) {
                    $ar[$row[$keyField]] = $row;
                } else {
                    $ar[] = $row;
                }
            }
            return $ar;
        }

        if ($type == 'row') {
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return $returnInvalid;
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
                $k = null;
                $v = reset($row);

                if (!empty($valueField)) {
                    if (isset($row[$valueField])) {
                        $v = $row[$valueField];
                    }
                }

                if (!empty($keyField)) {
                    if (isset($row[$keyField])) {
                        $k = $row[$keyField];
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
            if (empty($valueField)) {
                $row = $statement->fetch(PDO::FETCH_NUM);
                if ($row) {
                    return $row[0];
                }
            } else {
                $row = $statement->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    if (isset($row[$valueField])) {
                        return $row[$valueField];
                    }
                    $v = reset($row);
                    return $v;
                }
            }
            return $returnInvalid;
        }

        return true;
    }



    /**
     * check a database identifier such as a table or field name
     * should only be letters, numbers, and underscores.
     * if invalid, produce a class error (will end the program depending on settings)
     * if invalid and the program settings do not end the program on error, return an empty string.
     */
    private function checkIdentifier($identifier, $type, $isBind = false)
    {
        if ($isBind) {
            $identifier = ltrim($identifier, ':');
        }

        if (empty($identifier)) {
            $message = 'empty ' . $type . ' name';
            $this->exitProgramError('query', $message);
            return '';
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            $message = 'invalid ' . $type . ' name: ' . $identifier
                . ' | must be only letters, numbers, and underscores and cannot start with a number';
            $this->exitProgramError('query', $message);
            return '';
        }

        if ($isBind) {
            $identifier = ':' . $identifier;
        }

        return $identifier;
    }

    private function getReturnInvalid($type)
    {
        if ($this->settings['return_null_on_error']) {
            return null;
        }
        if ($type === 'one') {
            return '';
        }
        if ($type === 'query') {
            return false;
        }
        return array();
    }



    private function resetInfo($sql, $type, $binds, $keyField, $valueField)
    {
        if ($this->settings['save_last_query_info']) {
            $this->lastQuery['insert_id'] = 0;
            $this->lastQuery['sql'] = $sql;
            $this->lastQuery['type'] = $type;
            $this->lastQuery['bind_ar'] = $binds;
            $this->lastQuery['key_field'] = $keyField;
            $this->lastQuery['value_field'] = $valueField;
            $this->lastQuery['row_count'] = $this->lastQuery['duration'] = 0;
            $this->lastQuery['db_class_error'] = $this->lastQuery['error'] = '';
            $this->lastQuery['start_time'] = microtime(true);
            $this->lastQuery['end_time'] = null;
        }
    }

    private function setInfo($statement)
    {
        $this->lastInsertId = $this->connection->lastInsertId();
        if ($this->settings['save_last_query_info']) {
            $this->lastQuery['end_time'] = microtime(true);

            //$this->lastQuery['duration'] = number_format(($this->lastQuery['end_time'] - $this->lastQuery['start_time']), 8);
            $this->lastQuery['duration'] = (($this->lastQuery['end_time'] - $this->lastQuery['start_time']));

            if (!$statement) {
                //$this->set_con_error();
            } else {
                $this->lastQuery['row_count'] = $statement->rowCount();
                $this->lastQuery['insert_id'] = $this->lastInsertId;
            }
        }
    }

    /**
     * get the number of rows affected by the last query
     * example: $rowCount = $db->getLastRowCount();
     */
    public function getLastRowCount()
    {
        return $this->lastRowCount;
    }

    /**
     * get the identifier of the last query
     * will be 0 unless the last query was a successful insert
     * example: $id = $db->getLastInsertId();
     */
    public function getLastInsertId()
    {
        return $this->lastInsertId;
    }

    /**
     * get information on the last SQL query executed
     * data includes: raw SQL, binds, duration, errors, etc
     * 
     * example: $sql = $db->getLastQuery();
     */
    public function getLastQuery()
    {
        return $this->lastQuery;
    }

    /**
     * display information about the last query
     * data includes: raw SQL, binds, duration, errors, etc
     * example: $db->displayLastQuery();
     */
    public function displayLastQuery()
    {
        $this->display($this->lastQuery);
    }

    /**
     * shortcut for displayLastQuery
     * example: $db->info();
     */
    public function info()
    {
        $this->displayLastQuery();
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
        if ($count < 1 || $count > 1000) {
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
     * end the program on setup error (invalid settings)
     * only ends the program if exitProgramOnFailure is true
     * completely stops the page from loading, not just the form
     * note that errors types have a hierarchy, class, init, or query. 
     *      so if exit_on_class_error is true, we will exit on query error
     * $errorType will be class, init, or query
     */
    private function exitProgramError($errorType, $message = '')
    {
        $bts = debug_backtrace();
        echo '<table>';
        foreach ($bts as $bt) {
            echo "<tr>" .
                "<td>" . $bt['file'] . "</td>" .
                "<td>" . $bt['line'] . "</td>" .
                "<td>" . $bt['function'] . "</td>" .
                "<td>" . $bt['class'] . "</td>" .
                "<td>" . var_export($bt['args'], 1) . "</td>" .
                "</tr>";
        }
        echo '</table>';
        var_dump($errorType);
        var_dump($message);
        die();
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
}
