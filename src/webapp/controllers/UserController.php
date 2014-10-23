<?php

namespace tdt4237\webapp\controllers;

use tdt4237\webapp\models\User;
use tdt4237\webapp\models\ProfilePicture;
use tdt4237\webapp\Hash;
use tdt4237\webapp\Auth;
use tdt4237\webapp\Security;

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
        $email = $request->post('email');
        $pass = $request->post('pass');

        if (strlen($pass) < 8) {
            $this->app->flashNow('error', 'The password must be at least 8 characters long.');
            $this->render('newUserForm.twig', ['username' => $username, 'email' => $email]);
            return;
        }

        $hashed = Hash::make($username, $pass);

        $user = User::makeEmpty();
        $user->setUsername($username);
        $user->setEmail($email);
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
            } else if (! User::validateEmail($user)) {
                $this->app->flashNow('error', 'The email address is invalid.');
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

    function passwordRecovery($token = null) {
        if (!Auth::guest()) {
            $username = Auth::user()->getUserName();
            $this->app->flash('info', 'You are already logged in as ' . $username);
            $this->app->redirect('/');
        }
        if (empty($token)) {
            $request = $this->app->request;
            $username = '';
            if ($this->app->request->isPost()) {
                $username = $request->post('user');
                $user = User::findByUser($username);
                if ($user) {
                    $time = time();
                    $secret_message = implode("\1", array($time, $username, $user->getEmail()));
                    $signature = hash_hmac('sha256', $secret_message, $user->getPasswordHash(), TRUE);
                    $plain_token = $username."\2".$time."\2".$signature;
                    $token = openssl_encrypt($plain_token, 'aes-256-cbc', Security::HASH_KEY, 0, hash('md5', Security::HASH_KEY, TRUE));
                    if ($token !== false) {
                        $name = 'Movie Reviews';
                        // NB: Canonical names must be used, or else SERVER_NAME may be inaccurate
                        $email = 'moviereviews-noreply@'.$_SERVER['SERVER_NAME'];
                        $recipient = $user->getEmail();
                        $mail_body = "https://".$_SERVER['SERVER_NAME'].'/user/reset/'.bin2hex($token);
                        $subject = 'Reset your account password at Movie Reviews';
                        $header = 'From: '.$name.' <'.$email.'>';
                        mail($recipient, $subject, $mail_body, $header);
                        // TODO: Mail body should *not* be here, but it is for debugging purposes.
                        $this->app->flash('info', 'Password recovery email sent. [EMAIL CONTENT: '.$mail_body.']');
                        $this->app->redirect('/');
                    }
                    $this->app->flashNow('error', 'Unexpected error.');
                } else {
                    $this->app->flashNow('error', 'User not found.');
                }
            }
            $this->render('recoverPassword.twig', ['username' => htmlspecialchars($username)]);
        } else {
            $token = hex2bin($token);
            if (!empty($token)) {
                $plain_token = openssl_decrypt($token, 'aes-256-cbc', Security::HASH_KEY, 0, hash('md5', Security::HASH_KEY, TRUE));
                if ($plain_token !== false) {
                    $parts = explode("\2", $plain_token, 3);
                    if (count($parts) === 3) {
                        $time = time();
                        if ($time > $parts[1] && ($time - $parts[1]) < 84600 /* 24 hours */) {
                            $username = $parts[0];
                            $user = User::findByUser($username);
                            if ($user) {
                                $secret_message = implode("\1", array($parts[1], $username, $user->getEmail()));
                                $signature = hash_hmac('sha256', $secret_message, $user->getPasswordHash(), TRUE);
                                if (hash_equals($signature, $parts[2])) {
                                    $_SESSION['passwordResetUser'] = $username;
                                    $this->app->redirect('/user/newpassword');
                                }
                            }
                        }
                    }
                }
            }
            $this->app->flash('info', 'Invalid or expired password reset token.');
            $this->app->redirect('/');           
        }
    }

    function newpassword() {
        if (!Auth::guest()) {
            $username = Auth::user()->getUserName();
            $this->app->flash('info', 'You are already logged in as ' . htmlspecialchars($username));
            $this->app->redirect('/');
        }
        if (!array_key_exists('passwordResetUser', $_SESSION) && !empty($_SESSION['passwordResetUser'])) {
            $username = Auth::user()->getUserName();
            $this->app->flash('info', 'You are already logged in as ' . htmlspecialchars($username));
            $this->app->redirect('/user/reset');
        }
        $username = $_SESSION['passwordResetUser'];
        if ($this->app->request->isPost()) {
            $request = $this->app->request;
            $pass = $request->post('pass');

            if (strlen($pass) < 8) {
                $this->app->flashNow('error', 'The password must be at least 8 characters long.');
                $this->render('newPasswordForm.twig', ['username' => htmlspecialchars($username)]);
                return;
            }

            $hashed = Hash::make($username, $pass);
            $user = User::findByUser($username);
            if ($user) {
                $user->setHash($hashed);
                $user->save();
                $_SESSION['passwordResetUser'] = '';
                unset($_SESSION['passwordResetUser']);
                $this->app->flash('info', 'Password updated. Now log in.');
                $this->app->redirect('/login');
            }
        }
        $this->render('newPasswordForm.twig', ['username' => htmlspecialchars($username)]);
    }
}
