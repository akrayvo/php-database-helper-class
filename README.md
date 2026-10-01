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

all settings are boolean (true or false) and can be set using the updateSetting function
```
<?php
$form = new FormHelper();
$form->updateSetting('addIdAttributeFromName', true);
$form->updateSetting('passedStripTags', false);
?>
```

### exitProgramOnFailure
* end program on settings/configuration error?
* helpful for development, should be false in production
* default = false


### addIdAttributeFromName
* automatically add an "id" attribute with the same value as "name"?
* does not affect radio inputs because they can have multiple elements with the same "name" attribute
* does not affect buttons because the class does not automatically add a name "attribute" to buttons
* default = false

```
// addIdAttributeFromName = false
// trying to retrieve a passed variable with invalid parameters (both POST and GET)
$form->updateSetting('exitProgramOnFailure', false);
$name = $form->getPassed('first_name', array('post','get'));
// despite error, program will continue with no output error message

// addIdAttributeFromName = true
// trying to retrieve a passed variable with invalid parameters (both POST and GET)
$form->updateSetting('exitProgramOnFailure', true);
$name = $form->getPassed('first_name', array('post','get'));
// will output an error message and end the program
```

### selectOptionValueEqualsDisplayText
* in a select (dropdown), use each option's display text as its value
* if false, the passed options parameter should be an associative array: $options = array('blue'=>'Blue', 'light_green'=>'Light Green');
* if true, the passed options parameter can be an indexed (non-associative) array since the key is ignored: $options = array('Blue', 'Light Green');
* default = false
```
$options = array('NY'=>'New York', 'OH'=>'Ohio');

// selectOptionValueEqualsDisplayText = false
$form->updateSetting('selectOptionValueEqualsDisplayText', false);
$form->select('state', $options);
// <select name="state"><option value="NY">New York</option><option value="OH">Ohio</option></select>

$form->updateSetting('selectOptionValueEqualsDisplayText', true);
$form->select('state', $options);
// <select name="state"><option value="New York">New York</option><option value="Ohio">Ohio</option></select>
```

### passedTrim
* trim whitespace from the beginning and end of passed values
* used in the getPassed() function
* default = true
```
// my_text = "  My Text  " was passed from form

// passedTrim = false | beginning and end whitespace will be retained
$form->updateSetting('passedTrim', false);
var_dump($form->getPassed("my_text"));
// output string(11) "   My Text   "

// passedTrim = true | beginning and end whitespace will be removed
$form->updateSetting('passedTrim', true);
var_dump($form->getPassed("my_text"));
// output string(7) "My Text"
```


### passedStripTags
* remove HTML tags and script/style blocks from a string
* used in the getPassed() function
* default = true
```
// my_text = "<b><i>My Text</i></b>" was passed from form

// passedStripTags = false | HTML tags will be retained
$form->updateSetting('passedStripTags', false);
var_dump($form->getPassed("my_text"));
// output string(21) "<b><i>My Text</i></b>"

// passedStripTags = true | HTML tags will be removed
$form->updateSetting('passedStripTags', true);
var_dump($form->getPassed("my_text"));
// output string(7) "My Text"
```


### passedConvertToStandardCharacters
* converts non-standard (non-ASCII) characters in passed values
* used in the getPassed() function
* replaces characters with equivalents when possible, otherwise replaces the character with a dash
* default = false
```
// my_text = "Déjà Vu" was passed from form

// passedConvertToStandardCharacters = false | non-standard will be retained
$form->updateSetting('passedConvertToStandardCharacters', false);
var_dump($form->getPassed("my_text"));
// output string(9) "Déjà Vu"

// passedConvertToStandardCharacters = true | non-standard will be replaced
$form->updateSetting('passedConvertToStandardCharacters', true);
var_dump($form->getPassed("my_text"));
// output string(7) "Deja Vu"
```


### returnNullIfUnavailable
* when retrieving a passed value, return NULL when variable is not available (not set or invalid)
* used in the getPassed() function
* by default, when a variable is not set, the return value is "" (empty string), 0, or an empty array depending on if a flag is set to return as an int, float, or array. if returnNullIfUnavailable is set to true, null will be returned instead
* will also return NULL when a variable doesn't match the settings. for instance, the 'array' flag is set, but the value is not an array
* default = false
```
// no POST or GET data passed

// returnNullIfUnavailable = false
$form->updateSetting('returnNullIfUnavailable', false);
$value = $form->getPassed('variable_is_not_set');
var_dump($value);
// output: string(0) ""

// returnNullIfUnavailable = true
$form->updateSetting('returnNullIfUnavailable', true);
$value = $form->getPassed('variable_is_not_set');
var_dump($value);
// output: NULL
```


### returnHtml
* return the HTML elements as a string?
* default = false

```
// returnHtml = false | no echo is required to display output
$form->updateSetting('returnHtml', false);
echo $form->text("first_name");
// output: <input type="text" name="first_name" value="">

// returnHtml = true | echo is required to display output
$form->updateSetting('returnHtml', true);
echo $form->text("first_name");
// output: <input type="text" name="first_name" value="">
```

     
### xhtmlStyleOutput
* output XHTML-style HTML
* closes self-closing elements and boolean attributes (selected, readonly, etc) will have values that match the attribute
* default = false

```
// xhtmlStyleOutput = false
$form->updateSetting('xhtmlStyleOutput', false);
$form->text('first_name', '', array('readonly'));
// <input type="text" name="first_name" value="" readonly>

// xhtmlStyleOutput = true
$form->updateSetting('xhtmlStyleOutput', true);
$form->text('first_name', '', array('readonly'));
// <input type="text" name="first_name" value="" readonly="readonly" />
```


## Using form tag attributes
All form element functions include a `$moreAttributes` parameter. It takes an array of attributes with the $key as the attribute name and the value being the value.

If the key is numeric, it will be handled as a boolean attribute (with no value such as `readonly`, `disabled`, `checked`, etc.).

Common attributes would include `id`, `class`, `style`, `placeholder`, etc.

```
$moreAttributes = array('style'=>'padding:20px;', 'placeholder'=>'Name', 'readonly');
$form->text('name', '', $moreAttributes);
```
HTML output
```
<input type="text" name="name" value="" style="padding:20px" placeholder="Name" readonly>
```


## Passing Variables

Processing forms generally requires handling data passed from POST or GET. These functions check that passed data exists, get the value, manipulate it, and return it.

* `getPassed($var, $flags = array())` - get variable passed through POST, GET, or COOKIE. by default will check POST and return the value if set, then check GET and return the value if set. a COOKIE value is only returned when the `cookie` flag is set 
* `getPost($var, $flags = array())` - get variable passed through POST
* `getGet($var, $flags = array())` - get variable passed through GET

# Flags
* `post` - retrieve the variable from POST only
* `get` - retrieve the variable from GET only
* `cookie` - retrieve the variable from COOKIE only. note that COOKIE values are retrievable since they can be processed along with form data. For instance when saving form data to a database or processing an email form, a COOKIE value can be checked to determine if the user is logged in and that info can be processed.
* `int` - convert retrieved value to an integer
* `float` - convert retrieved value to a float
* `array` - process value as an array, can be used with int or float to process an array of integers or floats
* `strip-tags`, `no-strip-tags` - override the "passedStripTags" setting. see setting for details
* `trim`, `no-trim` - override the "passedTrim" setting. see setting for details
* `convert`, `no-convert` - override the "passedConvertToStandardCharacters" setting. see setting for details

# Usage

* flags can be passed as an array or a string separated by commas or spaces. ex: `$flags = array('post', 'float');  or  $flags = "post float";  or   $flags = "post,float";`

## Functions

### Settings
* `updateSetting($setting, $value)` - set configuration variables
### String Manipulation
* `htmlEscape($string)` - escape a string to display in HTML
### Get Passed Data
* `getPassed($var, $flags = array())` - retrieve a value from $_GET, $_POST, or $_COOKIE. default functionality is check $_POST, then check $_GET
* `getPost($var, $flags = array())` - retrieve a value from $_POST
* `getGet($var, $flags = array())` - retrieve a value from $_GET
### input elements
* `hidden($name, $value = '', $moreAttributes = array())` - `<input type="hidden">`
* `text($name, $value = '', $moreAttributes = array())` - `<input type="text">`
* `color($name, $value = '', $moreAttributes = array())` - `<input type="color">`
* `number($name, $value = '', $moreAttributes = array())` - `<input type="number">`
* `range($name, $min, $max, $value = '', $moreAttributes = array())` - `<input type="range">`
* `email($name, $value = '', $moreAttributes = array())` - `<input type="email">`
* `tel($name, $value = '', $moreAttributes = array())` - `<input type="tel">`
* `date($name, $value = '', $moreAttributes = array())` - `<input type="date">`
* `password($name, $moreAttributes = array())` - `<input type="password">`
* `checkbox($name, $isChecked = false, $value = 1, $moreAttributes = array())` - `<input type="checkbox">`
* `radio($name, $value, $selectedValue = '', $moreAttributes = array())` - `<input type="radio">`
* `submit($value = '', $name = '',  $moreAttributes = array())` - `<input type="submit">`
* `reset($value = '', $name = '',  $moreAttributes = array())` - `<input type="reset">`
* `input($type, $name, $value = '', $moreAttributes = array())` - `<input>` (used for other HTML inputs: url, phone, etc)

### Other form elements
* `formStart($action = '', $method = '', $moreAttributes = array())` - `<form>`
* `formEnd()` - `</form>`
* `textarea($name, $value = '', $moreAttributes = array())` - `<textarea>`
* `button($html = 'Submit', $moreAttributes = array())` - `<button>`
* `select($name, $options, $value = null, $moreAttributes = array())` - `<select><option>`
* `selectByRecordSet($name, $records, $valueKey, $displayKey, $emptyText = '', $value = null, $moreAttributes = array())` - `<select><option>`
