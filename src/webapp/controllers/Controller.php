<?php

namespace tdt4237\webapp\controllers;
use tdt4237\webapp\Auth;
use tdt4237\webapp\Security;

abstract class Controller
{
    protected $app;

    function __construct()
    {
        $this->app = \Slim\Slim::getInstance();
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            Security::validateToken(get_class($this));
        }
    }

    function render($template, $variables = [])
    {
        $variables['__controller'] = get_class($this);
        if (! Auth::guest()) {
            $variables['isLoggedIn'] = true;
            $variables['isAdmin'] = Auth::isAdmin();
            $variables['loggedInUsername'] = $_SESSION['user'];
        }

        print $this->app->render($template, $variables);
    }
}
