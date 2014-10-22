<?php

namespace tdt4237\webapp\controllers;

use tdt4237\webapp\models\User;
use tdt4237\webapp\models\ProfilePicture;
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

        if (strlen($pass) < 8) {
            $this->app->flashNow('error', 'The password must be at least 8 characters long.');
            $this->render('newUserForm.twig', ['username' => $username]);
            return;
        }

        $hashed = Hash::make($username, $pass);

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
        if (hash_equals($this->app->request->get('t'), $_SESSION['logouttoken'])) {
            Auth::logout();
            $this->app->flash('info', "Successfully logged out.");
        }
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

    function showProfilePicture($username) {
        $profile_picture = ProfilePicture::get($username);
        if ($profile_picture === null) {
            header($_SERVER['SERVER_PROTOCOL'].' 302 Temporary Redirect');
            header('Location: /images/empty_profile.jpg');
        } else {
            header('Content-type: image/jpeg');
            die($profile_picture);
        }
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
            $profile_picture = null;
            if (isset($_FILES['profile_picture']['error']) && !is_array($_FILES['profile_picture']['error'])) {
                if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    if ($_FILES['profile_picture']['size'] <= 1000000) {
                        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
			$type = $finfo->file($_FILES['profile_picture']['tmp_name']);
                        if (in_array($type, array('image/jpeg', 'image/png', 'image/gif'), true)) {
                            $profile_picture = ProfilePicture::save($user->getId(), $_FILES['profile_picture']['tmp_name'], $type);
                        } else {
                            $this->app->flashNow('error', 'Invalid file type. Please submit a GIF, PNG or JPEG file.');
                        }
                    } else {
                        $this->app->flashNow('error', 'Your profile picture may not exceed 1MB.');
                    }
                } else if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $this->app->flashNow('error', 'There was an error uploading your profile picture.');
                }
            }
            if ($profile_picture !== true) {
                if ($profile_picture !== null) {
                    $this->app->flashNow('error', 'There was an error saving your profile picture.');
                }
                $remove_profile_picture = $request->post('remove_profile_picture');
                if ($remove_profile_picture === 'yes') {
                    ProfilePicture::removeProfilePicture($user->getId());
                }
            }
        }

        $this->render('edituser.twig', ['user' => $user]);
    }
}
