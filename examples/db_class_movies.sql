--
-- simple test database used in Database Helper Class examples
--


CREATE TABLE `actors` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `birth_date` date NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT INTO `actors` (`id`, `name`, `birth_date`) VALUES
(1, 'Arnold Schwarzenegger', '1947-07-30'),
(2, 'Matt Damon', '1970-10-08'),
(3, 'Julia Roberts', '1967-10-28'),
(4, 'Tom Hanks', '1956-07-09'),
(5, 'Bruce Willis', '1955-03-19'),
(6, 'Ben Affleck', '1972-08-15'),
(7, 'George Clooney', '1961-05-06'),
(8, 'Brad Pitt', '1963-12-18');

CREATE TABLE `genres` (
  `id` int(10) UNSIGNED NOT NULL,
  `genre` varchar(255) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT INTO `genres` (`id`, `genre`) VALUES
(1, 'Action'),
(2, 'Science Fiction'),
(3, 'Comedy'),
(4, 'Romance'),
(5, 'Drama'),
(6, 'Crime'),
(7, 'Animation'),
(8, 'Family');

CREATE TABLE `movies` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `release_date` date NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT INTO `movies` (`id`, `title`, `release_date`) VALUES
(1, 'The Terminator', '1984-10-26'),
(2, 'Terminator 2: Judgment Day', '1991-07-03'),
(3, 'The Bourne Identity', '2002-06-14'),
(4, 'Pretty Woman', '1990-03-23'),
(5, 'Forrest Gump', '1994-07-06'),
(6, 'Armageddon', '1998-07-01'),
(7, 'Ocean\'s Eleven', '2001-12-07'),
(8, 'Die Hard', '1988-07-20'),
(9, 'Toy Story', '1995-11-22'),
(10, 'My Big Fat Greek Wedding', '2002-04-19');

CREATE TABLE `movie_actor` (
  `movie_id` int(10) UNSIGNED NOT NULL,
  `actor_id` int(10) UNSIGNED NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT INTO `movie_actor` (`movie_id`, `actor_id`) VALUES
(1, 1),
(2, 1),
(3, 2),
(4, 3),
(5, 4),
(6, 5),
(6, 6),
(7, 2),
(7, 3),
(7, 7),
(7, 8),
(8, 5),
(9, 4);

CREATE TABLE `movie_genre` (
  `movie_id` int(10) UNSIGNED NOT NULL,
  `genre_id` int(10) UNSIGNED NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT INTO `movie_genre` (`movie_id`, `genre_id`) VALUES
(1, 1),
(1, 2),
(2, 1),
(2, 2),
(3, 1),
(4, 3),
(4, 4),
(5, 3),
(5, 4),
(5, 5),
(6, 1),
(6, 2),
(7, 3),
(7, 6),
(8, 1),
(9, 3),
(9, 7),
(9, 8),
(10, 3),
(10, 4);


ALTER TABLE `actors`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `genres`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `movies`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `movie_actor`
  ADD UNIQUE KEY `movie_id` (`movie_id`,`actor_id`);

ALTER TABLE `movie_genre`
  ADD UNIQUE KEY `movie_id` (`movie_id`,`genre_id`);


ALTER TABLE `actors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `genres`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `movies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;COMMIT;
