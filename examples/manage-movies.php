<?php

/**
 * DatabaseHelper example - Movie database
 *
 * this page is an example of using the DatabaseHelper class to create a simple
 * movie database management application. it demonstrates adding, editing, viewing,
 * and deleting movies, as well as managing the associated actors and genres.
 *
 * you do not need to set up a database or web server to look through this file.
 * the page is intended primarily as an example of how to use the DatabaseHelper
 * class, so you can read the code and see how the class is used without running it.
 *
 * the page is fully functional. if you want to run it, you will need a PHP-enabled
 * web server and MySQL.
 *
 *      1. copy this file and the DatabaseHelper class file (DatabaseHelper.class.php)
 *          to your web server.
 *
 *      2. create a new MySQL database. the database can have any name. ex: db_class_movies
 *
 *      3. import the provided db_class_movies.sql into the new database. this SQL 
 *          file creates the tables and inserts test data.
 *
 *      4. update the database connection information below. make sure the DatabaseHelper
 *          class file include points to the correct location, and set the database name,
 *          host, username, and password for your MySQL server.
 *
 *      5. open this page through your web server.
 *
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once('../DatabaseHelper.class.php');

// database connection
$db = new DatabaseHelper(
    'db_class_movies',  // table
    'localhost',        // host
    'root',             // username
    ''                  // password
);


function getMovies()
{
    global $db;

    // a query to get all movies, plus a list of actors and genres for each
    $sql = 'SELECT
            M.*,
            GROUP_CONCAT(DISTINCT A.name ORDER BY A.name SEPARATOR ", ") AS actor_list,
            GROUP_CONCAT(DISTINCT G.genre ORDER BY G.genre SEPARATOR ", ") AS genre_list
        FROM movies AS M
        LEFT JOIN movie_actor AS MA ON MA.movie_id = M.id
        LEFT JOIN actors AS A ON A.id = MA.actor_id
        LEFT JOIN movie_genre AS MG ON MG.movie_id = M.id
        LEFT JOIN genres AS G ON G.id = MG.genre_id
        GROUP BY M.id
        ORDER BY M.title ASC, M.release_date DESC';

    return $db->all($sql);
}

/**
 * @param int $movieId
*/
function getMovie($movieId)
{
    global $db;

     // query all information for a single movie, then add genres and actors
    $movie = $db->rowById('movies', $movieId);
    $movie['actors'] = $db->col('SELECT actor_id FROM movie_actor WHERE movie_id=:movie_id;', array('movie_id' => $movieId));
    $movie['genres'] = $db->col('SELECT genre_id FROM movie_genre WHERE movie_id=:movie_id;', array('movie_id' => $movieId));

    // check that the movie actually exists. show an error if not.
    if (empty($movie)) {
        pageHeader();
        echo '<div class="message error_message">Movie Not Found</div>';
        echo '<br><br><a href="' . htmlspecialchars($_SERVER['PHP_SELF']) . '">Back To Movie List</a>';
        pageFooter();
        die();
    }

    return $movie;
}

function getActorList()
{
    global $db;

    // make an array of all actors, the array key is the id
    $sql = 'SELECT id, name FROM actors ORDER BY name;';
    $binds = array();
    $keyField = 'id';
    $valueField = 'name';

    return $db->col($sql, $binds, $keyField, $valueField);
}

function getGenreList()
{
    global $db;

    // make an array of all genres, the array key is the id
    $sql = 'SELECT id, genre FROM genres ORDER BY genre;';
    $binds = array();
    $keyField = 'id';
    $valueField = 'genre';

    return $db->col($sql, $binds, $keyField, $valueField);
}

/**
 * @param int $movieId
*/
function deleteMovie()
{
    global $db;

    $movieId = intval($_POST['movie_id']);

    // get information on the current movie so that we know it exists.
    // we also have the movie name for the confirmation message
    $movie = getMovie($movieId);

    $message = '';
    if (!empty($movie)) {
        // delete the movie
        $isSuccess = $db->deleteById('movies', $movieId);
        
        if ($isSuccess) {
            // the movie was successfully deleted, delete associated data and set the message.
            $db->query('DELETE FROM movie_actor WHERE movie_id=:movie_id;', array('movie_id' => $movieId));
            $db->query('DELETE FROM movie_genre WHERE movie_id=:movie_id;', array('movie_id' => $movieId));
            $message = 'Movie Deleted: ' . $movie['title'];
        }
    }

    viewMovies($message);
}

/**
 * @param int $movieId
*/
function saveMovie()
{
    global $db;

    // get information on the movie passed through the form
    $movieId = intval($_POST['movie_id']);
    $releaseDate = $_POST['release_date'];
    $title = trim($_POST['title']);

    // error checking
    $errors = array();
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    if (empty($releaseDate)) {
        $errors[] = 'Release Date is required';
    }
    if (empty($errors)) {
        // check the movie was not already added.
        // we're using the title and release data as unique data, so if they are the same, it's a duplicate
        // we're checking the id so we won't consider the same record as a duplicate
        // for new records the id will be 0, since no record has an id of 0, "id<>:id" will always be TRUE.
        //      we could also send different queries depending on we have to check id (edit) or not (add)
        $sql = 'SELECT id FROM movies WHERE title=:title AND release_date=:release_date AND id<>:id limit 1;';
        $binds = array(':title' => $title, ':release_date' => $releaseDate, ':id' => $movieId);
        $existingMovieId = $db->one($sql, $binds);
        if ($existingMovieId) {
            $errors[] = 'Movie already exists.';
        }
    }

    if (!empty($errors)) {
        // there as an error, send error message to the form and skip processing
        movieForm($errors);
        return;
    }

    // values for the database
    // the same values will be used whether the movie exists (edit) or is new (add)
    $values = array(
        'title' => $title,
        'release_date' => $releaseDate,
        'updated_date' => $db->raw('NOW()')
    );

    $message = '';
    if ($movieId) {
        // update existing
        $isSuccess = $db->updateById('movies', $values, $movieId);
        if ($isSuccess) {
            $message = "Movie Updated: " . $title;
        }
    } else {
        // add new
        // the insert ID is returned, we will use this to add associated data
        $movieId = $db->insert('movies', $values);
        if ($movieId) {
            $message = "Movie Added: " . $title;
        }
    }

    if ($movieId) {
        // $movieId is set, either record existed before or we just successfully added it

        // get all actor id's with records already in the movie_actor table for this movie
        $sql = 'SELECT actor_id FROM movie_actor WHERE movie_id=:movie_id;';
        $binds = array(':movie_id' => $movieId);
        $movieActorIds = $db->col($sql, $binds);

        // after adding we're going to delete all records not in the $keepIds array (associated records removed)
        $keepIds = array();
        // arrays of values, well save this data and then insert all needed records at once
        $inserts = array();
        
        if (!empty($_POST['actors'])) {
            // some data has been passed through the form (at least one item checked)
            foreach ($_POST['actors'] as $actorId) {
                // convert values to integers. they will later be entered directly into the query rather than using
                //     binding, so this is required to prevent possible SQL injection.
                $keepIds[] = intval($actorId);
                if (!in_array($actorId, $movieActorIds)) {
                    // record does not yet exist, put in array to be added
                    $inserts[] = array('movie_id' => $movieId, 'actor_id' => $actorId);
                }
            }
        }
        if (!empty($inserts)) {
            // at least 1 record is to be inserted
            // insert records
            $db->insertMultiple('movie_actor', $inserts);
        }

        $binds = array(':movie_id' => $movieId);
        if (empty($keepIds)) {
            // no records are set (no checkboxes clicked), remove all records for the movie
            $db->query('DELETE FROM movie_actor where movie_id=:movie_id;', $binds);
        } else {
            // some records are set(checkboxes clicked), remove all records for the movie other than the set ones
            // note that values originally generated by the user are added directly to the query rather than using PDO binding. in this case, all
            //      values have been converted to integers, so this isn't a security issue (no chance of SQL injection).
            $db->query('DELETE FROM movie_actor where movie_id=:movie_id and actor_id NOT IN (' . implode(',', $keepIds) . ');', $binds);
        }


        // get all genre id's with records already in the movie_genre table for this movie
        $sql = 'SELECT genre_id FROM movie_genre WHERE movie_id=:movie_id;';
        $binds = array(':movie_id' => $movieId);
        $movieGenreIds = $db->col($sql, $binds);

        // after adding we're going to delete all records not in this $keepIds array (associated records removed)
        $keepIds = array();
        // arrays of values, we'll save this data and then insert all needed records at once
        $inserts = array();
        
        if (!empty($_POST['genres'])) {
            // some data has been passed through the form (at least one item checked)
            foreach ($_POST['genres'] as $genreId) {
                $keepIds[] = intval($genreId);
                if (!in_array($genreId, $movieGenreIds)) {
                    // record does not yet exist, put in array to be added
                    $inserts[] = array('movie_id' => $movieId, 'genre_id' => $genreId);
                }
            }
        }
        if (!empty($inserts)) {
            // at least 1 record is to be inserted
            // insert records
            $db->insertMultiple('movie_genre', $inserts);
        }

        $binds = array(':movie_id' => $movieId);
        if (empty($keepIds)) {
            // no records are set (no checkboxes clicked), remove all records for the movie
            $db->query('DELETE FROM movie_genre where movie_id=:movie_id;', $binds);
        } else {
            // some records are set(checkboxes clicked), remove all records for the movie other than the set ones
            $db->query('DELETE FROM movie_genre where movie_id=:movie_id and genre_id NOT IN (' . implode(',', $keepIds) . ');', $binds);
        }
    }

    // display movies list with message
    viewMovies($message);
}

/**
 * @param string $message
*/
function viewMovies($message = '')
{
    // display the list of movies

    $movies = getMovies();
    pageHeader();
?>
    <h2>Movies</h2><br>

    <?php if (!empty($message)) { ?>
        <div class="message">
            <?php echo htmlspecialchars($message); ?>
        </div><br>
    <?php } ?>

    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?method=add_movie">Add Movie</a><br><br>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Release Date</th>
                <th>Genres</th>
                <th>Actors</th>
                <th>Update Date</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>

            <?php foreach ($movies as $movie) { ?>

                <tr>
                    <td><?php echo htmlspecialchars($movie['id']); ?></td>
                    <td><?php echo htmlspecialchars($movie['title']); ?></td>
                    <td><?php echo htmlspecialchars($movie['release_date']); ?></td>
                    <td><?php echo htmlspecialchars($movie['genre_list']); ?></td>
                    <td><?php echo htmlspecialchars($movie['actor_list']); ?></td>
                    <td><?php echo htmlspecialchars($movie['updated_date']); ?></td>
                    <td class="actions">

                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?method=edit_movie&amp;movie_id=<?php echo urlencode($movie['id']); ?>">Edit</a> &nbsp;


                        <form class="inline" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                            onsubmit="return confirm('Delete this movie?');">

                            <input type="hidden" name="method" value="delete_movie">
                            <input type="hidden" name="movie_id"
                                value="<?php echo htmlspecialchars($movie['id']); ?>">

                            <button type="submit">Delete</button>

                        </form>

                    </td>
                </tr>

            <?php } ?>

        </tbody>
    </table>
<?php
    pageFooter();
}

function movieForm($errors = array())
{
    // display the movie add/update form

    global $db;

    $actors = getActorList();
    $genres = getGenreList();

    $movieId = 0;
    if (!empty($_POST['movie_id'])) {
        $movieId = $_POST['movie_id'];
    } elseif (!empty($_GET['movie_id'])) {
        $movieId = $_GET['movie_id'];
    }

    $title = $release_date = '';
    $setActors = $setGenres = array();
    if ($movieId) {
        $movie = getMovie($movieId);
        $title = $movie['title'];
        $release_date = $movie['release_date'];
        $setActors = $movie['actors'];
        $setGenres = $movie['genres'];
    } else {
        if (!empty($_POST['title'])) {
            $title = $_POST['title'];
        }
        if (!empty($_POST['release_date'])) {
            $release_date = $_POST['release_date'];
        }
        if (!empty($_POST['actors'])) {
            $setActors = $_POST['actors'];
        }
        if (!empty($_POST['genres'])) {
            $setGenres = $_POST['genres'];
        }
    }

    $actionLabel = "Add";
    if (!empty($movieId)) {
        $actionLabel = "Edit";
    }

    pageHeader();
?>

    <h2><?php echo htmlspecialchars($actionLabel); ?> Movie</h2><br>
    <?php if (!empty($errors)) { ?>
        <div class="message error_message">
            <ul>
                <?php
                foreach ($errors as $error) {
                    echo '<li>' . htmlspecialchars($error) . '</li>';
                }
                ?>
            </ul>
        </div><br>
    <?php } ?>


    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">

        <input type="hidden" name="method" value="save_movie">
        <input type="hidden" name="movie_id" value="<?php echo htmlspecialchars($movieId); ?>">

        <div class="field">
            <label for="title">Title</label>

            <input type="text"
                id="title"
                name="title"
                value="<?php echo htmlspecialchars($title); ?>"
                required>
        </div>

        <div class="field">
            <label for="release_date">Release Date</label>

            <input type="date"
                id="release_date"
                name="release_date"
                value="<?php echo htmlspecialchars($release_date); ?>"
                required>
        </div>

        <div class="field">
            <label>Actors</label>

            <div class="checkbox-list">

                <?php foreach ($actors as $actorId => $actorName) { ?>

                    <label>
                        <input type="checkbox"
                            name="actors[]"
                            value="<?php echo htmlspecialchars($actorId); ?>"
                            <?php if (in_array($actorId, $setActors)) echo "checked" ?>>

                        <?php echo htmlspecialchars($actorName); ?>
                    </label>

                <?php } ?>

            </div>
        </div>

        <div class="field">
            <label>Genres</label>

            <div class="checkbox-list">

                <?php foreach ($genres as $genreId => $genreName) { ?>

                    <label>
                        <input type="checkbox"
                            name="genres[]"
                            value="<?php echo htmlspecialchars($genreId); ?>"
                            <?php if (in_array($genreId, $setGenres)) echo "checked" ?>>

                        <?php echo htmlspecialchars($genreName); ?>
                    </label>

                <?php } ?>

            </div>
        </div>

        <button type="submit"><?php echo htmlspecialchars($actionLabel); ?> Movie</button>

        <br><br><a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Cancel</a>

    </form>
<?php
    pageFooter();
}

function pageHeader()
{
    // display the HTML page header

?>
    <!DOCTYPE html>
    <html>

    <head>
        <meta charset="utf-8">
        <title>Database Helper Examples - Working</title>
        <link rel="stylesheet" href="./style.css">
    </head>

    <body>

        <h1>Database Helper Examples - Working</h1>
        <div><a href="./">&laquo; back to All Examples</a></div>
        <br>
    <?php
}

function pageFooter()
{
    // display the HTML page header
    ?>
    </body>
    </html>
<?php
}


// get the page method: add_movie, edit_movie, save_movie, or delete_movie
// could be passed by POST or GET
$method = '';
if (!empty($_POST['method'])) {
    $method = $_POST['method'];
} elseif (!empty($_GET['method'])) {
    $method = $_GET['method'];
}

// load the function based on the page method
switch ($method) {
    case "add_movie":
    case "edit_movie":
        movieForm();
        break;
    case "save_movie":
        saveMovie();
        break;
    case "delete_movie":
        deleteMovie();
        break;
    default:
        viewMovies();
        break;
}
