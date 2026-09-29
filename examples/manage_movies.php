<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once('../DatabaseHelper.class.php');




$db = new DatabaseHelper(
    'db_class_movies',
    'localhost',
    'root',
    ''
);


function getMovies()
{
    global $db;

    //$sql = 'SELECT * FROM movies ORDER BY title ASC, release_date DESC';
    $sql = 'SELECT
            M.*,
            GROUP_CONCAT(DISTINCT A.name ORDER BY A.name SEPARATOR ", ") AS actor_list,
            GROUP_CONCAT(DISTINCT G.genre ORDER BY G.genre SEPARATOR ", ") AS genre_list
        FROM movies as M
        LEFT JOIN movie_actor as MA ON MA.movie_id = M.id
        LEFT JOIN actors as A ON a.id = MA.actor_id
        LEFT JOIN movie_genre as MG ON MG.movie_id = M.id
        LEFT JOIN genres as G ON G.id = MG.genre_id
        GROUP BY M.id
        ORDER BY M.title ASC, M.release_date DESC';

    return $db->all($sql);
}

function getMovie($movieId)
{
    global $db;

    $movie = $db->rowById('movies', $movieId);
    $movie['actors'] = $db->col('SELECT actor_id FROM movie_actor WHERE movie_id=:movie_id;', array('movie_id' => $movieId));
    $movie['genres'] = $db->col('SELECT genre_id FROM movie_genre WHERE movie_id=:movie_id;', array('movie_id' => $movieId));

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

    $sql = 'SELECT id, name FROM actors ORDER BY name;';
    $binds = array();
    $keyField = 'id';
    $valueField = 'name';

    return $db->col($sql, $binds, $keyField, $valueField);
}

function getGenreList()
{
    global $db;

    $sql = 'SELECT id, genre FROM genres ORDER BY genre;';
    $binds = array();
    $keyField = 'id';
    $valueField = 'genre';

    return $db->col($sql, $binds, $keyField, $valueField);
}

function deleteMovie($movieId)
{
    global $db;

    $movie = getMovie($movieId);

    $isSuccess = $db->deleteById('movies', $movieId);

    $message = '';
    if ($isSuccess) {
        $db->query('DELETE FROM movie_actor WHERE movie_id=:movie_id;', array('movie_id' => $movieId));
        $db->query('DELETE FROM movie_genre WHERE movie_id=:movie_id;', array('movie_id' => $movieId));
        $message = 'Movie Deleted: ' . $movie['title'];
    }

    viewMovies($message);
}


function saveMovie($movieId = 0)
{
    global $db;

    $actors = $genres = array();

    $movieId = $_POST['movie_id'];

    $releaseDate = $_POST['release_date'];

    $title = trim($_POST['title']);
    $releaseDate = $_POST['release_date'];

    $errors = array();
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    if (empty($releaseDate)) {
        $errors[] = 'Release Date is required';
    }

    if (empty($errors)) {
        $sql = 'SELECT id FROM movies WHERE title=:title AND release_date=:release_date AND id<>:id limit 1;';
        $binds = array(':title' => $title, ':release_date' => $releaseDate, ':id' => $movieId);
        $existingMovieId = $db->one($sql, $binds);
        if ($existingMovieId) {
            $errors[] = 'Movie already exists.';
        }
    }

    if (!empty($errors)) {
        movieForm($errors);
        return;
    }

    $values = array(
        'title' => $title,
        'release_date' => $releaseDate,
        'updated_date' => $db->raw('NOW()')
    );

    $message = '';
    if ($movieId) {
        $isSuccess = $db->updateById('movies', $values, $movieId);
        if ($isSuccess) {
            $message = "Movie Updated: " . $title;
        }
    } else {
        $movieId = $db->insert('movies', $values);
        if ($movieId) {
            $message = "Movie Added: " . $title;
        }
    }

    if ($movieId) {

        $sql = 'SELECT actor_id FROM movie_actor WHERE movie_id=:movie_id;';
        $binds = array(':movie_id' => $movieId);
        $movieActorIds = $db->col($sql, $binds);

        $keepIds = array();
        $values = array();
        if (!empty($_POST['actors'])) {
            foreach ($_POST['actors'] as $actorId) {
                $keepIds[] = intval($actorId);
                if (!in_array($actorId, $movieActorIds)) {
                    // record does not yet exist
                    $values[] = array('movie_id' => $movieId, 'actor_id' => $actorId);
                }
            }
        }
        if (!empty($values)) {
            $db->insertMultiple('movie_actor', $values);
        }

        $binds = array(':movie_id' => $movieId);
        if (empty($keepIds)) {
            $db->query('DELETE FROM movie_actor where movie_id=:movie_id;', $binds);
        } else {
            $db->query('DELETE FROM movie_actor where movie_id=:movie_id and actor_id NOT IN (' . implode(',', $keepIds) . ');', $binds);
        }

        $sql = 'SELECT actor_id FROM movie_actor WHERE movie_id=:movie_id;';
        $binds = array(':movie_id' => $movieId);
        $movieActorIds = $db->col($sql, $binds);


        $sql = 'SELECT genre_id FROM movie_genre WHERE movie_id=:movie_id;';
        $binds = array(':movie_id' => $movieId);
        $movieGenreIds = $db->col($sql, $binds);

        $keepIds = array();
        $values = array();
        if (!empty($_POST['genres'])) {
            foreach ($_POST['genres'] as $genreId) {
                $keepIds[] = intval($genreId);
                if (!in_array($genreId, $movieGenreIds)) {
                    // record does not yet exist
                    $values[] = array('movie_id' => $movieId, 'genre_id' => $genreId);
                }
            }
        }
        if (!empty($values)) {
            $db->insertMultiple('movie_genre', $values);
        }

        $binds = array(':movie_id' => $movieId);
        if (empty($keepIds)) {
            $db->query('DELETE FROM movie_genre where movie_id=:movie_id;', $binds);
        } else {
            $db->query('DELETE FROM movie_genre where movie_id=:movie_id and genre_id NOT IN (' . implode(',', $keepIds) . ');', $binds);
        }
    }

    viewMovies($message);
}

function viewMovies($message = '')
{
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
                    <td><?php echo htmlspecialchars($movie['actor_list']); ?></td>
                    <td><?php echo htmlspecialchars($movie['genre_list']); ?></td>
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
    global $db;

    $actors = getActorList();
    $genres = getGenreList();

    $movieId = 0;
    if (!empty($_POST['movie_id'])) {
        $movieId = $_POST['movie_id'];
    } elseif (!empty($_GET['movie_id'])) {
        $movieId = $_GET['movie_id'];
    } elseif (!empty($_GET['movie_id'])) {
        $movieId = $_GET['movie_id'];
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
        <div><a href="./">&laquo; back to All Examples</a></div><br><br>
        <br>
    <?php
}

function pageFooter()
{
    ?>
    </body>

    </html>
<?php
}



$method = '';
if (!empty($_POST['method'])) {
    $method = $_POST['method'];
} elseif (!empty($_GET['method'])) {
    $method = $_GET['method'];
}

$movieId = '';
if (!empty($_POST['movie_id'])) {
    $movieId = intval($_POST['movie_id']);
} elseif (!empty($_GET['movie_id'])) {
    $movieId = intval($_GET['movie_id']);
}

switch ($method) {
    case "add_movie":
        movieForm();
        break;
    case "edit_movie":
        movieForm();
        break;
    case "save_movie":
        saveMovie($movieId);
        break;
    case "delete_movie":
        deleteMovie($movieId);
        break;
    default:
        viewMovies();
        break;
}
