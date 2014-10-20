<?php

namespace tdt4237\webapp;

use tdt4237\webapp\models\User;
use tdt4237\webapp\Hash;

class SecurityException extends \Exception {}

class SecurityException extends \Exception {}

class Security
{
	const SESSION_BASE = '1deb4ab1-3a03-4c9c-9906-84f0e5c66d09';
	const HASH_KEY = "\xec\x32\x29\x80\x6c\xaa\xd5\xb0\x88\xea\xb5\x62\x47\x2e\x76\xa4\x33\x23\x41\xa8\xf4\x2b\x4c\xa7\x8a\xa6\x4c\x10\x12\xd3\x0c\x34";
	const MAX_TOKENS = 64;
	const POST_NAME = '__form_token';

    function __construct()
    {
	}

	static function getRandomToken() {
		$str = '';
		if (function_exists('openssl_random_pseudo_bytes')) {
			$str = openssl_random_pseudo_bytes(8);
		} else {
			for($i=0; $i < 8; $i++) {
				$x = mt_rand(0, 255);
				$str .= chr($x);
			}
		}
		return bin2hex($str);
	}

	static function getToken($action, $fields = []) {
		$action_hash = md5($action);

		if (!array_key_exists(self::SESSION_BASE, $_SESSION)) {
			$_SESSION[self::SESSION_BASE] = array($action_hash => array());
		} else if (!array_key_exists($action_hash, $_SESSION[self::SESSION_BASE])) {
			$_SESSION[self::SESSION_BASE][$action_hash] = array();
		}
		$token = self::getRandomToken();

		if (count($_SESSION[self::SESSION_BASE][$action_hash]) > self::MAX_TOKENS) {
			array_shift($_SESSION[self::SESSION_BASE][$action_hash]);
		}

		$_SESSION[self::SESSION_BASE][$action_hash][$token] = true;

		$fields[] = self::POST_NAME;

		asort($fields);
		$field_string = implode("\1\0", $fields);

		return $token.'-'.hash_hmac('sha256', $field_string, hash_hmac('sha256', $token, self::HASH_KEY));
	}

	static function validateToken($action) {
		$action_hash = md5($action);
		$validation_string = filter_input(INPUT_POST, self::POST_NAME);

		list($token, $field_hash) = explode('-', $validation_string.'--', 3);

		$fields = array_keys($_POST);
		asort($fields);
		$field_string = implode("\1\0", $fields);

		if (!empty($token) && !empty($field_string) && array_key_exists(self::SESSION_BASE, $_SESSION) && array_key_exists($action_hash, $_SESSION[self::SESSION_BASE]) && array_key_exists($token, $_SESSION[self::SESSION_BASE][$action_hash])) {
			unset($_SESSION[self::SESSION_BASE][$action_hash][$token]);
			if (hash_hmac('sha256', $field_string, hash_hmac('sha256', $token, self::HASH_KEY)) === $field_hash) {
				return true;
			}
			throw new SecurityException("Form has been tampered with");
		}
		// No valid token provided
		throw new SecurityException("Invalid or missing token");
	}
}
