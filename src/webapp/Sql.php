<?php

namespace tdt4237\webapp;

use tdt4237\webapp\models\User;

class Sql
{
    static $pdo;

    function __construct()
    {
    }

    /**
     * Create tables.
     */
    static function up() {
        $q1 = "CREATE TABLE users (id INTEGER PRIMARY KEY, user VARCHAR(50), pass VARCHAR(50), email varchar(50), age varchar(50), bio varhar(50), isadmin INTEGER);";
        $q4 = "CREATE TABLE movies (id INTEGER PRIMARY KEY, name VARVHAR(50), imageurl VARCHAR(100) );";
        $q5 = "CREATE TABLE moviereviews (id INTEGER PRIMARY KEY, movieid INTEGER, author VARVHAR(50), text VARCHAR(500) );";

        self::$pdo->exec($q1);
        self::$pdo->exec($q4);
        self::$pdo->exec($q5);

        print "[tdt4237] Done creating all SQL tables.".PHP_EOL;

        self::insertDummyUsers();
        self::insertMovies();
    }

    static function insertDummyUsers() {
        $admin_pw = bin2hex(openssl_random_pseudo_bytes(2));
        print "[tdt4237] Admin Password: ".$admin_pw.PHP_EOL;
        $hash1 = Hash::make('admin', $admin_pw);
        $hash2 = Hash::make('bob', 'bobdylan');
        $hash3 = Hash::make('mike', 'liverpool');

        $q1 = "INSERT INTO users(user, pass, isadmin) VALUES ('admin', '$hash1', 1)";
        $q2 = "INSERT INTO users(user, pass) VALUES ('bob', '$hash2')";
        $q3 = "INSERT INTO users(user, pass) VALUES ('mike', '$hash3')";

        self::$pdo->exec($q1);
        self::$pdo->exec($q2);
        self::$pdo->exec($q3);

        print "[tdt4237] Done inserting dummy users.".PHP_EOL;
    }

    static function insertMovies() {
        $movies = [
            ['American Psycho', 'psycho.jpg'],
            ['Open Your Eyes', 'eyes.jpg'],
            ['Wild Strawberries', 'strawberries.jpg'],
            ['The Seventh Seal', 'seal.jpg'],
            ['Cube', 'cube.jpg'],
            ['Sin City', 'sincity.jpg'],
            ['Signs', 'signs.jpg'],
            ['A.I. Artificial Intelligence', 'ai.jpg'],
        ];

        foreach ($movies as $movie) {
            $name = $movie[0];
            $imageUrl = $movie[1];

            $q = "INSERT INTO movies(name, imageurl) VALUES ('$name', '$imageUrl') ";
            self::$pdo->exec($q);
        }

        print "[tdt4237] Done inserting dummy movies.".PHP_EOL;
    }

    static function down() {
        $q1 = "DROP TABLE users";
        $q4 = "DROP TABLE movies";
        $q5 = "DROP TABLE moviereviews";

        self::$pdo->exec($q1);
        self::$pdo->exec($q4);
        self::$pdo->exec($q5);

        print "[tdt4237] Done deleting all SQL tables.".PHP_EOL;
    }
}
try {
    // Create (connect to) SQLite database in file
    Sql::$pdo = new \PDO('sqlite:app.db');
    // Set errormode to exceptions
    Sql::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
} catch(\PDOException $e) {
    echo $e->getMessage();
    exit();
}
