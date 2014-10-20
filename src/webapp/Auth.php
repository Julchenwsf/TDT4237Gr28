<?php

namespace tdt4237\webapp;

use tdt4237\webapp\models\User;
use tdt4237\webapp\Hash;

class Auth
{
    function __construct()
    {
    }

    static function checkCredentials($username, $password)
    {
        $user = User::findByUser($username);

        if ($user === null) {
            return false;
        }

        return Hash::check($username, $password, $user->getPasswordHash());
    }

    /**
     * Check if is logged in.
     */
    static function check()
    {
        return isset($_SESSION['user']);
    }

    /**
     * Check if the person is a guest.
     */
    static function guest()
    {
        return self::check() === false;
    }

    /**
     * Get currently logged in user.
     */
    static function user()
    {
        if (self::check()) {
            return User::findByUser($_SESSION['user']);
        }

        throw new \Exception('Not logged in but called Auth::user() anyway');
    }

    /**
     * Is currently logged in user admin?
     */
    static function isAdmin()
    {
        if (self::check()) {
            return $_SESSION['isadmin'] === true;
        }

        throw new \Exception('Not logged in but called Auth::isAdmin() anyway');
    }

    static function logout()
    {
        session_destroy();
        session_write_close();
        session_start();
        session_regenerate_id();
    }
}
