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

?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HTML Form Example - Basic</title>
    <link rel="stylesheet" href="./style.css?x=2">
</head>

<body>
    <h1>Examples - Basic</h1>
    <div><a href="./">&laquo; back to All Examples</a></div><br><br>



    <h2>Display all movie records</h2>
    <code>$movies = $db->all('select * from movies order by title;');</code>
    
    <?php 

    $movies = $db->all('select * from movies order by title;');
    
    echo "<br><br><table>\n";
    foreach ($movies as $movie) {
        echo "<tr>".
            "<td>".htmlentities($movie['title'])."</td>".
            "<td>".htmlentities($movie['release_date'])."</td>";
            "</tr>\n";
    }
    echo "</table>\n";
    
    ?>

    <br><br>    

    <h2>Display a list of movies</h2>
    <code>$titles = $db->col('select title from movies order by title;');</code>

    <?php 

    $titles = $db->column('select title from movies order by title;');
    
    echo "<br><br><ul>\n";
    foreach ($titles as $title) {
        echo "<li>".htmlentities($title)."</li>\n";
    }
    echo "</ul>\n";
    
    ?>

    <br><br>    

    <h2>Display all info for the first movie alphabetically</h2>
    <code>$movie = $db->row('select * from movies order by title limit 1;');</code>

    <?php 

    $movie = $db->row('select * from movies order by title limit 1;');
    
    echo "<br><br><table>\n";
    foreach ($movie as $k=>$v) {
        echo "<tr>".
            "<td>".htmlentities($k)."</td>".
            "<td>".htmlentities($v)."</td>";
            "</tr>\n";
    }
    echo "</table>\n";
    
    ?>

    <br><br>    

    <h2>Display only the title for the first movie alphabetically</h2>
    <code>$title = $db->one('select title from movies order by title limit 1;');</code>

    <?php 

    $title = $db->one('select title from movies order by title limit 1;');
    
    echo "<br><br><ul>\n";
        echo "<li>".htmlentities($title)."</li>\n";
    echo "</ul>\n";
    
    

</body>

</html>