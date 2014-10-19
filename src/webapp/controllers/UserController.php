<?php

namespace tdt4237\webapp\controllers;

use tdt4237\webapp\models\User;
use tdt4237\webapp\Hash;
use tdt4237\webapp\Auth;

class UserController extends Controller
{
    function __construct()
    {
        parent::__construct();
    }

    function index()
    {
        if (Auth::guest()) {
            $this->render('newUserForm.twig', []);
        } else {
            $username = Auth::user()->getUserName();
            $this->app->flash('info', 'You are already logged in as ' . $username);
            $this->app->redirect('/');
        }
    }

    function create()
    {
        $request = $this->app->request;
        $username = $request->post('user');
        $pass = $request->post('pass');

        $hashed = Hash::make($pass);

        $user = User::makeEmpty();
        $user->setUsername($username);
        $user->setHash($hashed);

        $validationErrors = User::validate($user);

        if (sizeof($validationErrors) > 0) {
            $errors = join("<br>\n", $validationErrors);
            $this->app->flashNow('error', $errors);
            $this->render('newUserForm.twig', ['username' => $username]);
        } else {
            $user->save();
            $this->app->flash('info', 'Thanks for creating a user. Now log in.');
            $this->app->redirect('/login');
        }
    }

    function all()
    {
        $users = User::all();
        $this->render('users.twig', ['users' => $users]);
    }

    function logout()
    {
        Auth::logout();
        $this->app->flash('info', 'Successfully logged out.');
        $this->app->redirect('/');
    }

    function show($username)
    {
        $user = User::findByUser($username);

        $this->render('showuser.twig', [
            'user' => $user,
            'username' => $username
        ]);
    }

    function edit()
    {
        if (Auth::guest()) {
            $this->app->flash('info', 'You must be logged in to edit your profile.');
            $this->app->redirect('/login');
            return;
        }

        $user = Auth::user();

        if (! $user) {
            throw new \Exception("Unable to fetch logged in user's object from db.");
        }

        if ($this->app->request->isPost()) {
            $request = $this->app->request;
            $email = $request->post('email');
            $bio = $request->post('bio');
            $age = $request->post('age');

            $user->setEmail($email);
            $user->setBio($bio);
            $user->setAge($age);

            if (! User::validateAge($user)) {
                $this->app->flashNow('error', 'Age must be between 0 and 150.');
            } else {
                $user->save();
                $this->app->flashNow('info', 'Your profile was successfully saved.');
            }
        }

        $this->render('edituser.twig', ['user' => $user]);
    }
}
