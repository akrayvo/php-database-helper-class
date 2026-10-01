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

$db->updateSetting('connection_error_action', 'continue_without_database');

$db->updateSetting('query_error_action', 'continue_without_database');

$sql = 'select * from movies limit 100';
$movies = $db->all($sql);
$db->display($movies);
$db->displayLastQueryInfo();

//$db->updateSetting('save_query_run_timez', true);

$db->updateSetting('save_query_run_time', true);
$db->updateSetting('output_error_debugging', true);
$db->updateSetting('return_null_on_error', true);
$db->updateSetting('delete_and_update_require_where', true);

$sql = 'select * from movies limit 100';
$movies = $db->all($sql);
$db->display($movies);
$db->displayLastQueryInfo();

$sql = 'update movies set updated_date = "1980-01-05";';
$movies = $db->all($sql);
$db->display($movies);
$db->displayLastQueryInfo();


// $sql = 'select M1.title as m1t, M2.title as m2t, M3.title as m3t
//     from movies as M1, movies as M2, movies as M3 limit 10';
// $movies = $db->row($sql);

// $db->display($movies);


// $sql = 'select title, id from movies where title like :title limit 1;';
// $x = $db->all($sql);
// $x = $db->insert('movies', array('title'=>'mr test 2', 'release_date'=>'2001-02-03', 'updated_date'=>$db->raw('NOW()')));
// $db->display($x);
// $db->info();

//$fields = array('title', 'release_date', 'updated_date');
//$ar = array();
//$ar = array('tit le'=>'this is updated title 2', 'release_date'=>'1999-02-03', 'updated_date'=>$db->raw('NOW()'));
//$ar[] = array('title'=>'my fun 13zzz', 'release_date'=>'2001-02-03', 'updated_date'=>$db->raw('NOW()'));

///$x = $db->update('movies', $ar, 'id=:id', array(':id'=>24));
//$db->display($x);
//$db->info();
$x = $db->deleteById('movies', 21);
$db->display($x);
$db->info();

// $sql = 'select * from movies limit 100';
// $movies = $db->all($sql);
// $db->display($movies);
// $db->displayLastQueryInfo();

$sql = 'select * from movies limit 100';
$movies = $db->all($sql);
$db->display($movies);
$db->info();