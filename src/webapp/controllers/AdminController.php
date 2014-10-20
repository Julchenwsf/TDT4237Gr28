<?php

namespace tdt4237\webapp\controllers;

use tdt4237\webapp\Auth;
use tdt4237\webapp\models\User;

class AdminController extends Controller
{
    function __construct()
    {
        parent::__construct();
        if (Auth::guest()) {
            $this->app->flash('info', "You must be logged in to view the admin page.");
            $this->app->redirect('/');
        }

        if (! Auth::isAdmin()) {
            $this->app->flash('info', "You must be administrator to view the admin page.");
            $this->app->redirect('/');
        }
    }

    function index()
    {
        $variables = [
            'users' => User::all()
        ];
        $this->render('admin.twig', $variables);
    }

    function delete($username)
    {
        if (User::deleteByUsername($username) === 1) {
            $this->app->flash('info', "Sucessfully deleted ".htmlspecialchars($username));
        } else {
            $this->app->flash('info', "An error ocurred. Unable to delete user ".htmlspecialchars($username));
        }

        $this->app->redirect('/admin');
    }
}
