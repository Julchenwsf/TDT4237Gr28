<?php

namespace tdt4237\webapp;

class Hash
{
    function __construct()
    {
    }

    static function make($username, $plaintext)
    {
        $tmp_password = hash_hmac('sha512', $plaintext, $username, TRUE);
        $prefix = '';
        if (CRYPT_BLOWFISH == 1) {
            $salt = strtr(substr(base64_encode(openssl_random_pseudo_bytes(17)), 0, 22), '+', '.');
            $prefix = '$2y$11$'.$salt.'$';
        } else if (CRYPT_SHA512 == 1 || CRYPT_SHA256 == 1) {
            $salt = strtr(substr(base64_encode(openssl_random_pseudo_bytes(12)), 0, 16), '+', '.');
            $prefix = '$'.(CRYPT_SHA512 == 1?'6':'5').'$rounds=9999$'.$salt.'$';
        }
        return crypt($tmp_password, $prefix);
    }

    static function ctstrcmp($str1, $str2) {
        $res = $str1 ^ $str2;
        $l = strlen($res);
        $rv = strlen($str1) ^ strlen($str2);
        for ($i = 0; $i < $l; $i++) {
            $rv |= ord($res[$i]);
        }
        return !!$rv;
    }

    static function check($username, $plaintext, $hash)
    {
        $tmp_password = hash_hmac('sha512', $plaintext, $username, TRUE);
        return !self::ctstrcmp(crypt($tmp_password, $hash), $hash);
    }
}
