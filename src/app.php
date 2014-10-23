<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
header('Strict-Transport-Security: max-age=31536000');
require_once __DIR__ . '/../vendor/autoload.php';

use tdt4237\webapp\IPThrottlingGeneral;

if (!function_exists('hash_equals')) {
    function hash_equals($str1, $str2) {
        $res = $str1 ^ $str2;
        $l = strlen($res);
        $rv = strlen($str1) ^ strlen($str2);
        for ($i = 0; $i < $l; $i++) {
            $rv |= ord($res[$i]);
        }
        return !$rv;
    }
}

// Function to get the client IP address
function get_client_ip() {
    $ipaddress = '';
	if(isset($_SERVER['REMOTE_ADDR']))
		$ipaddress = $_SERVER['REMOTE_ADDR'];
	else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
		$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
	else if(isset($_SERVER['HTTP_X_FORWARDED']))
		$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
	else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
		$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
	else if(isset($_SERVER['HTTP_FORWARDED']))
		$ipaddress = $_SERVER['HTTP_FORWARDED'];
	else if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
	else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

$app = new \Slim\Slim([
    'templates.path' => __DIR__.'/webapp/templates/',
    'debug' => false,
    'view' => new \Slim\Views\Twig()
]);

$view = $app->view();
$view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
    new \tdt4237\webapp\SecureTwigExtension(),
);

try {
    // Create (connect to) SQLite database in file. Disable emulate prepares for enhanced security.
    $app->db = new PDO('sqlite:app.db', NULL, NULL, array(PDO::ATTR_EMULATE_PREPARES => false));
    // Set errormode to exceptions
    $app->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo $e->getMessage();
    exit();
}

/** @var $BFBresponse answer about if the IP should be throttled/banned or not */
$BFBresponse = IPThrottlingGeneral::getLoginStatus(get_client_ip());
if ($BFBresponse['status']=='delay') {
	//time delay required before next login (or general request)
	$this->app->flash('error', "Wait $BFBresponse[message] seconds before next request.");
	$this->app->redirect('/');
	die();
}

IPThrottlingGeneral::addFailedLoginAttempt(get_client_ip());

$ns ='tdt4237\\webapp\\controllers\\'; 

// Home page at http://localhost/
$app->get('/', $ns . 'IndexController:index');

// Login form
$app->get('/login', $ns . 'LoginController:index');
$app->post('/login', $ns . 'LoginController:login');

// New user
$app->get('/user/new', $ns . 'UserController:index')->name('newuser');
$app->post('/user/new', $ns . 'UserController:create');

// Edit logged in user
$app->get('/user/edit', $ns . 'UserController:edit')->name('editprofile');
$app->post('/user/edit', $ns . 'UserController:edit');

// Set new password
$app->get('/user/newpassword', $ns . 'UserController:newpassword')->name('newpassword');
$app->post('/user/newpassword', $ns . 'UserController:newpassword');

// Password reset token
$app->get('/user/reset', $ns . 'UserController:passwordRecovery')->name('passwordreset');
$app->post('/user/reset', $ns . 'UserController:passwordRecovery');

$app->get('/user/reset/:token', $ns . 'UserController:passwordRecovery');

// Show a user by name
$app->get('/user/:username', $ns . 'UserController:show')->name('showuser');

// Show profile picture
$app->get('/profile_picture/:username', $ns . 'UserController:showProfilePicture')->name('profilepicture');

// Show all users
$app->get('/users', $ns . 'UserController:all');

// Log out
$app->get('/logout', $ns . 'UserController:logout')->name('logout');

// Admin restricted area
$app->get('/admin', $ns . 'AdminController:index')->name('admin');
$app->post('/admin/delete/:username', $ns . 'AdminController:delete');

// Movies
$app->get('/movies', $ns . 'MovieController:index')->name('movies');
$app->get('/movies/:movieid', $ns . 'MovieController:show');
$app->post('/movies/:movieid', $ns . 'MovieController:addReview');

return $app;
