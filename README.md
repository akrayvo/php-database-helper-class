# PHP Form Helper Class
[View on GitHub »](php-database-helper-class)


The purpose of this database helper class is to simplify working with databases in PHP. It is currently designed for MySQL, but could be expanded to support other database systems in the future.

The class provides:

- **Functions that process SQL statements.** These functions accept a complete SQL statement along with any values that need to be bound. They handle preparing the statement, binding values, executing the query, checking for errors, and returning the requested result.

- **Functions that create and process SQL statements.** These functions accept information such as table names, field names, and values, build the appropriate SQL statement, and then process it. They are intended to simplify common database operations.

- **Helper functions for other database-related tasks.** These functions provide additional functionality that does not fit into the first two categories, such as displaying information about the most recent query or displaying query results.

The class requires knowledge of SQL and is intended for programmers who want to work with SQL directly. 

This class is **not an Object-Relational Mapper (ORM)**. It does not attempt to manage database design, relationships, data integrity, or application security. These remain the responsibility of the programmer, just as they would when writing PHP and MySQL code directly.

Supports PHP 5.1 through current PHP versions. **PHP 5.1 compatibility is maintained intentionally; this does not indicate that the project is outdated or limited to older PHP versions**.

## Overview
### Main functions that return information
Functions are named after the type of data that they return. Each is passed an sql statement and optionally an array of PDO binds. 

- **all()** returns a 2 dimensional array of multiple records, each is an associative array of key/value pairs.
- **row()** returns a single record in an associative array of key/value pairs.
- **column()** returns an array containing a single field for multiple rows/records.
- **one()** returns a single value from a single field of a single record.

### The query() function
Runs queries that process data, such as INSERT, UPDATE, or DELETE. It can also run any other type of queries such as updating permissions and altering table fields. It is passed an sql statement and optionally an array of PDO bind information. It returns a boolean value, **true** if the query is successful or **false** if the query fails. Note that success only means that the query ran without error, not that data was affected, for example: "DELETE FROM customers where 1=2" could be valid but would not update any records.

### Functions that generate and process SQL
The class avoids functions such as *getRecords($selects, $tables, $joins, $whereConditions, $limits, $sort)* that attempt to build every part of a query and can become confusing. Instead functions in the class are intended to reduce coding while remaining simple and intuitive. For instance, **rowById()** is passed a table name and an ID and returns the record/row as an associative array. **rowById("customers", 15)** would generate SQL **SELECT * FROM customers WHERE id=:id** and bind the **ID** value to 15.

### SQL Injection Security
When any database application processes user-generated information, it is important to protect against SQL injection. This class provides PDO binding to make it easier to safely pass values into SQL, but since SQL statements are sent directly to the class, it is the programmer's responsibility to use them correctly.
    
**Avoid code like this:**
```
$lastName = $_POST['last_name'];
// user generated value from a form added directly from a query - do NOT do this
$db->all("SELECT * FROM customers WHERE last_name='$lastName';");
```
**Use instead:**
```
$lastName = $_POST['last_name'];
// user generated value from a form processed through binding - protected from SQL injection
$db->all("SELECT * FROM customers WHERE last_name=:last_name", array(":last_name" => $lastName));
```

### Query Efficiency
Since SQL passed to the function is processed directly, it is up to the programmer to write efficient queries. For instance the **row()** function will return a single row from the results, so it would be better if SQL only returns one record.

**Avoid code like this:**
```
// the SQL retrieves all rows in the table, but the row() function only returns the first - do NOT do this
$db->row("SELECT * FROM customers ORDER BY username;");
```
**Use instead:**
```
// "LIMIT 1" added to SQL so that only the first row is retrieved from the database
$db->row("SELECT * FROM customers ORDER BY username LIMIT 1;");
```

### Error Handling
The program has several settings that determine how errors are handled.

Errors have 2 types:
- **connection** - errors with connection to the database or updating settings (using updateSetting function). A settings error is treated at the same level as a connection error because if a setting is supposed to be set but is not valid, it could have consequences.
- **query** - errors during query initialization or in the query itself. for instance $db->rowById('invalid table name', 15) will produce an error in initialization since the table name has spaces and is invalid. $db->rowById('table_does_not_exist', 15) has a valid table name so it will process, but since the table doesn't exist, it will produce a database error.

Options for errors are:
- **exit** - completely end the program
- **continue_without_database** - the program continues, but subsequent database functions will not execute. functions that modify data will do nothing and functions
        that return data will return empty results
- **continue_with_database** - the program continues and subsequent database functions will continue normally

Settings
- **connection_error_action** - how to handle a connection or initialization error. valid values: **exit**, **continue_without_database**. note that there is no **continue_with_database**
- **query_error_action** - how to handle a query error. valid values: **exit**, **continue_without_database**, **continue_with_database**
- **error_output_html** - HTML to output when the program exits due to a query error.
- **output_error_debugging** - should debugging information display on the screen regarding the last query. true or false. this should only be set to true in development


## Requirements
* PHP >= 5.1.3

## Installation
Move the **DatabaseHelper.class.php** file to your project.
**include** or **require** the file in your code.

## Basic Example

### Initialization
```
// include the class file
require_once('../DatabaseHelper.class.php');
// initialize class
$db = new DatabaseHelper(
"your_db",          // db name
"127.0.0.1",        // host
"your_username",    // user
"your_password"     // password
);
```

### Get all customer records sorted alphabetically and display information
```
// returns an array of rows, each row is an array of key/value pairs
$customers = $db->all('select * from customers order by last_name, first_name;');

// display information about the last query, such as the SQL, errors, binds, etc
$db->info();
// display the value of the variable for debugging
// works especially well for displaying a 2-dimensional array with matching keys as a table
$db->display($customers);
```

### Get all fields for the first customer sorted alphabetically
```
$customer = $db->row('select * from customers order by last_name, first_name limit 1;');
```

### Get the last name only for the first customer sorted alphabetically
```
$lastName = $db->one('select last_name from customers order by last_name, first_name limit 1;');
```

### Insert
```
$values = array('first_name'=>'Bob', 'last_name'=>'Jones');
$insertId = $db->insert('customers', $values);
```

### Update
```
$values = array('first_name' => 'Robert');
$id = 15;
$isSuccess = $db->update('customers', $values, 'id=:id', array(':id' => $id));
```

### Update using the updateById function
```
$values = array('last_name' => 'Johnson');
$id = 15;
$isSuccess = $db->updateById('customers', $values, $id);
```

### Delete
```
$id = 15;
$isSuccess = $db->deleteById('customers', $id);
```

### Add an SQL statement to an array of values to INSERT or UPDATE
```
// the raw() function makes the string process as raw SQL rather than as a literal value
$values = array(
    'last_name' => 'Johnson', 
    'signup_date'=> $db->raw('CURDATE()')
    );
$insertId = $db->insert('customers', $values);
```

## Using the class vs. standard HP/PDO

### Connect to Database

without class
```
### Connect to Database
// connect to the database
$pdo = new PDO(
    'mysql:host=localhost;dbname=your_db;charset=utf8mb4',
    'your_username',
    'your_password'
);
```
with class
```
// include the class file
require_once('../DatabaseHelper.class.php');
// initialize class
$db = new DatabaseHelper(
    "your_db",          // db name
    "127.0.0.1",        // host
    "your_username",    // user
    "your_password"     // password
);
```

### Get Records (basic)

without class
```
$sql = 'select * from customers';

// prepare SQL statement
$statement = $pdo->prepare($sql);

// execute query
$statement->execute();

// get all records
$customers = $statement->fetchAll(PDO::FETCH_ASSOC);

```

with class
```
$sql = 'select * from customers';

// get all records
$customers = $db->all($sql);
```

### Get Records (filtered, sorted, and limited)

without class
```
$sql = 'select id, first_name, username from customers where last_name = :last_name order by first_name limit 10;';
$binds = array(':last_name' => 'Smith');

// prepare SQL statement
$statement = $pdo->prepare($sql);

// execute query with bound values
$statement->execute($binds);

// get records
$customers = $statement->fetchAll(PDO::FETCH_ASSOC);
```

with class
```
$sql = 'select id, first_name, username from customers where last_name = :last_name order by first_name limit 10;';
$binds = array(':last_name' => 'Smith');

// get records
$customers = $db->all($sql, $binds);
```

### Get Record By ID

without class
```
$sql = 'select * from customers where id = :id;';
$binds = array(':id' => 15);

// prepare SQL statement
$statement = $pdo->prepare($sql);

// execute query with bound values
$statement->execute($binds);

// get the first record
$customer = $statement->fetch(PDO::FETCH_ASSOC);
```

with class
```
$table = 'customers';
$customerId = 15;

// get the record
$customer = $db->rowById($table, $customerId);
```
### Insert Record and get ID

without class
```
$sql = 'insert into customers (first_name, last_name, username) values (:first_name, :last_name, :username);';
$binds = array(
    ':first_name' => 'John',
    ':last_name' => 'Jones',
    ':username' => 'john_jones'
);

// prepare SQL statement
$statement = $pdo->prepare($sql);

// execute query with bound values
$statement->execute($binds);

// get the identifier of the newly inserted record
$newCustomerId = $pdo->lastInsertId();
```

with class
```
$table = 'customers';
$fields = array(
    'first_name' => 'John',
    'last_name' => 'Jones',
    'username' => 'john_jones'
);

// insert a new record and get its identifier
$newCustomerId = $db->insert($table, $fields);
```

## Settings

All settings can be set using the updateSetting() function. for example:
```
$db->updateSetting('output_error_debugging', true);
```

### connection_error_action

* how to handle a connection or initialization error
* invalid configuration settings are also treated as initialization errors since they could keep data from processing as expected
* if changing the default value, change it in the settings array of the class file for connection errors. the database connection is made in the constructor, so updateSetting() cannot be used to change this setting until after the connection has been established.
* valid values: 'exit', 'continue_without_database'
    * **exit**: completely end the program
    * **continue_without_database**: the program continues, but subsequent database functions will not execute. functions that modify data will do nothing and functions that return data will return empty results
* note that there is no option to continue_with_database
* predefined options; default = **exit**


### query_error_action

* how to handle a query error
* valid values: 'exit', 'continue_without_database', 'continue_with_database'
    * **exit**: completely end the program
    * **continue_without_database**: the program continues, but subsequent database functions will not execute. functions that modify data will do nothing and functions that return data will return empty results
    * **continue_with_database**: the program continues and subsequent database functions will continue normally      
* predefined options; default = **continue_without_database**


###error_output_html

* HTML that will be displayed when an error causes the program to exit
* is only displayed when **query_error_action** or **connection_error_action** are set to "**exit**"
* text; default = "**&lt;div&gt;There was an error loading the page&lt;/div&gt;**"


###output_error_debugging

* output debugging information to the screen when an error causes the program to exit
* debugging information is only displayed when **query_error_action** or **connection_error_action** are set to "**exit**"
* should be set to false in production environments
* true or false (boolean); default = **false**


###return_null_on_error

* on query error, return null
* affects all(), row(), column(), one(), rowById(), and oneById()
* default behavior is to return an empty, a blank string, or false depending on the function
* allows the developer to distinguish between a valid query that returns no results and a query error
* true or false (boolean); default = **false**


###id_field_name

* the unique identifier column in tables
* used by all "ById" functions to find a record by identifier
* will usually not need to be changed since most databases use "id" as the identifier field name
* text; default = "**id**"

###max_records_per_insert_query

* maximum number of records to include in each SQL query generated by insertMultiple() and insertMultipleFieldsValues()
* higher values can be more efficient because fewer queries are needed, but use more system resources and increase the chance of exceeding system limitations
* the default (100) is a very conservative value; values in the tens of thousands
            will likely work fine for typical data
* integer; default = **100**


###delete_and_update_require_where

* DELETE and UPDATE queries require a WHERE clause
* used to avoid accidentally deleting or updating all rows in a table
* just a simple check that the string "WHERE" is in the query, could still allow a DELETE or UPDATE in certain rare cases: "DELETE from places_where_ive_been;" or "UPDATE books SET title='Where the Wild Things Are';"
* to affect all rows in a table, set WHERE clause to "true", ex: "DELETE FROM places_where_ive_been WHERE true;"     
* true or false (boolean); default = **true**


###max_rows_for_table_display

* the maximum number of rows to display as an HTML table when outputting an array using the display() function
* if an array has more rows, the array is displayed using PHP's var_dump() function
* integer; default = **1000**


###save_query_run_time

* track processing start and end times (to calculate duration later)
* useful for testing, but adds some overhead, so normally set to false in production unless the information
            is needed for another purpose, such as analysis
* true or false (boolean); default = **false**




## Functions

### Settings
* `updateSetting($setting, $value)` - set configuration variables

### Get Last Error and Connection Info
* `lastError()` - return the last (most recent) error
* `hasDbConnection()` - return if a database connection has been made or not

### Get Last Query Info
* `insertId()` -  get the identifier of the last query
* `rowCount()` - get the number of rows affected by the last query
* `error()` - get the error from the last query
* `success()` - return if the last query was successful
* `duration()` - return the duration of the last query in seconds
* `sql()` - return the last SQL query
* `binds()` - return the PDO binds for the last query

### Development / Debugging

* `displayLastQueryInfo()` - display information about the last query
* `info()` - shortcut for displayLastQuery
* `display($value)` - display a value for debugging
* `tables()` - output a list of all tables in the database
* `fields($table)` - output a list of all fields in a table

### Retrieve Data From Raw SQL
* `all($sql, $binds = array(), $keyField = '')` - execute SQL and return all result rows
* `row($sql, $binds = array())` - execute SQL and return the first row
* `column($sql, $binds = array(), $keyField = '', $valueField = '')` - execute SQL and return all values from a single column
* `col($sql, $binds = array(), $keyField = '', $valueField = '')` - shortcut for the column function
* `one($sql, $binds = array(), $valueField = '')` - execute SQL and return a single value from the first result row

### Modify Database (Insert, Update, Delete, Alter, etc) From Raw SQL
* `query($sql, $binds = array())` - execute SQL when no result values are needed

### Retrieve Data From Parameters (converted to SQL)
* `count($table)` - return the number of rows in a table
* `rowById($table, $id)` - get a row from a table using its identifier field (usually "id")
* `rowWhereEqual($table, $field, $value)` - get a row from a table where a specified field matches a value
* `oneById($table, $id, $field)` - get a single value from a table using its identifier field (usually "id")

### Modify Database (Insert, Update, Delete, Alter, etc) From Parameters (converted to SQL)
* `insert($table, $values)` - insert a new row into a table
* `insertMultiple($table, $rows)` - insert a new row into a table
* `insertMultipleFieldsValues($table, $fields, $dataRows)` - insert multiple new rows into a table using a separate array of field names
* `update($table, $values, $whereSql, $whereBinds = array())` - update rows in a table
* `updateById($table, $values, $id)` - update a row in a table using its identifier field (usually "id")
* `deleteById($table, $id)` - delete a row from a table using its identifier field (usually "id")

### SQL Expression Helpers
* `raw($value)` - mark a value as raw SQL instead of a value to be bound