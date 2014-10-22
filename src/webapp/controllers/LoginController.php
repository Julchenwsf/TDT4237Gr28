<?php

namespace tdt4237\webapp\controllers;

use tdt4237\webapp\Auth;
use tdt4237\webapp\BruteForceBlock;

class LoginController extends Controller
{
    function __construct()
    {
        parent::__construct();
    }

    function index()
    {
        if (Auth::check()) {
            $username = Auth::user()->getUserName();
            $this->app->flash('info', 'You are already logged in as ' . $username);
            $this->app->redirect('/');
        } else {
            $this->render('login.twig', []);
        }
    }

    function login()
    {
        $BFBresponse = BruteForceBlock::getLoginStatus();
        if ($BFBresponse['status']=='delay') {
            //time delay required before next login
            $this->app->flash('error', "Wait $BFBresponse[message] seconds before login.");
            $this->app->redirect('/');
            die();
        }

        $request = $this->app->request;
        $user = $request->post('user');
        $pass = $request->post('pass');

        if (Auth::checkCredentials($user, $pass)) {
            session_destroy();
            session_write_close();
            session_start();
            session_regenerate_id();

            $_SESSION['user'] = $user;
            $_SESSION['logouttoken'] = hash_hmac('md5', uniqid(true), $user.$pass);
            $_SESSION['isadmin'] = Auth::user()->isAdmin();

            $this->app->flash('info', "You are now successfully logged in as $user.");
            $this->app->redirect('/');
        } else {
            $userFound = User::findByUser($user);
            $user_id = $userFound == null ? 0 : $userFound->id;
            $ip_addressget = get_client_ip();
            BruteForceBlock::addFailedLoginAttempt($user_id, $ip_addressget);
            $this->app->flashNow('error', 'Incorrect user/pass combination.');
            $this->render('login.twig', []);
        }
    }
}
