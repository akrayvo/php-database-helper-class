<?php

// include the class file
require_once('../DatabaseHelper.class.php');
// initialize class
$db = new DatabaseHelper(
    "db_class_movies",      // db name
    "127.0.0.1",    // host
    "root",         // user
    ""
);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Helper Examples - Basic</title>
    <link rel="stylesheet" href="./style.cs?z">
</head>

<body>
    <h1>Examples - Basic</h1>
    <div><a href="./">&laquo; back to All Examples</a></div><br><br>


    <?php
    if (!$db->getHasDbConnection()) {
        echo '<p>*note: This page provides code examples that can be viewed and used without a database setup/connection. ' . 
            'The examples can also be run interactively if desired. To do so: <ul><p>'.
            '<li>create the example database called <b>db_class_movies</b></li>'.
            '<li>run the supplied sql data import to create and populate tables.</li>'.
            '<li>set up the connection at the top of the page (if on a local host, it may work as is.</li>'.
            '</lu>';
    }
    ?>

    <h2>Get all movie records sorted alphabetically</h2>
    <code>$movies = $db->all('select * from movies order by title;');</code>

    <?php
    $movies = $db->all('select * from movies order by title limit 3;');
    $db->display($movies);
    echo '<pre>';
    var_dump($movies);
    echo '</pre>';

    ?>
    

    <br><br><br>

    <h2>Get all info for the first movie alphabetically</h2>
    <code>$movie = $db->row('select * from movies order by title limit 1;');</code>

    <?php

    $movie = $db->row('select * from movies order by title limit 1;');
    $db->display($movie);

    ?>

    <br><br><br>

    <h2>Get the title only for the first movie alphabetically</h2>
    <code>$title = $db->one('select title from movies order by title limit 1;');</code>

    <?php

    $title = $db->one('select title from movies order by title limit 1;');

    $db->display($title);

    ?>

    <br><br><br>

    <h2>Insert, update, and delete</h2>

    <h3>Insert</h3>
    <code>
    $values = array('title' => 'Top Gunner', 'release_date' => '2986-05-16');<br>
    $insertId = $db->insert('movies', $values);
    </code>

    <?php

        // data is intentionally wrong so that it can be updated
        $values = array('title' => 'Top Gunner', 'release_date' => '2986-05-16');
        $insertId = $db->insert('movies', $values);
        if ($insertId) {
            echo '<br><div>' . $values['title'] . " inserted with ID of $insertId</div>\n";
        }

        //if ($insertId) {
        ?><br>
        <h3>Get newly inserted record by ID</h3>
        <code>$movie = $db->rowById('movies', $insertId);</code>
        <?php
            $movie = $db->rowById('movies', $insertId);
            $db->display($movie);

        ?><br>
        <h3>Update release_date using update function</h3>
        <code>$values = array('release_date' => '1986-05-16');<br>
            $isSuccess = $db->update('movies', $values, 'id=:id', array(':id' => $insertId));</code>
        <?php
            $values = array('release_date' => '1986-05-16');
            $isSuccess = $db->update('movies', $values, 'id=:id', array(':id' => $insertId));
            $db->display($isSuccess);

        ?><br>
        <h3>Update title using updateById function</h3>

        <?php
            $values = array('title' => 'Top Gun');
            $isSuccess = $db->updateById('movies', $values, $insertId);
            $db->display($isSuccess);

        ?><br>
        <h3>Check updated record</h3>
        <code>$movie = $db->rowById('movies', $insertId);</code>
        <?php
            $movie = $db->rowById('movies', $insertId);
            $db->display($movie);

        ?><br>
        <h3>delete the record</h3>
        <code>$isSuccess = $db->deleteById('movies', $insertId);</code>
    <?php
            $beforeDeleteCount = $db->one('select count(*) as total from movies;');
            $isSuccess = $db->deleteById('movies', $insertId);
            $db->display($isSuccess);
            if ($isSuccess) {
                $afterDeleteCount = $db->one('select count(*) as total from movies;');
                echo "Record Deleted; record count before update = $beforeDeleteCount; after update = " . $afterDeleteCount;
            }
        //}

    ?>

</body>

</html>