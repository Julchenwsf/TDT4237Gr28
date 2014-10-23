<?php

namespace tdt4237\webapp\controllers;
use tdt4237\webapp\Auth;
use tdt4237\webapp\Security;
use tdt4237\webapp\IPThrottlingGeneral;

abstract class Controller
{
    protected $app;

    function __construct()
    {
        $this->app = \Slim\Slim::getInstance();
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            Security::validateToken(get_class($this));
        }

		$BFBresponse = IPThrottlingGeneral::getRequestStatus(get_client_ip());
		if ($BFBresponse['status']=='delay') {
			//time delay required before next login (or general request)
			//$this->app->flashNow('error', "Wait $BFBresponse[message] seconds before next request.");
			throw new \Exception;
			//$this->app->redirect('/');
			//die();
		}

		IPThrottlingGeneral::addRequest(get_client_ip());
    }

    function render($template, $variables = [])
    {
        $variables['__controller'] = get_class($this);
        if (! Auth::guest()) {
            $variables['isLoggedIn'] = true;
            $variables['logoutToken'] = $_SESSION['logouttoken'];
            $variables['isAdmin'] = Auth::isAdmin();
            $variables['loggedInUsername'] = $_SESSION['user'];
        }

        print $this->app->render($template, $variables);
    }
}
