<?php

namespace tdt4237\webapp\models;

use tdt4237\webapp\Hash;

class ProfilePicture
{
    const INSERT_QUERY = "INSERT INTO profile_pictures (user_id, data) VALUES(:user_id, :data)";
    const UPDATE_QUERY = "UPDATE profile_pictures SET data = :data WHERE user_id = :user_id";
    const FIND_BY_NAME = "SELECT p.data FROM profile_pictures AS p JOIN users AS u u.id = ? WHERE p.user_id = u.id LIMIT 1";
    const FIND_IF_EXISTS = "SELECT COUNT(*) FROM profile_pictures WHERE user_id = ? LIMIT 1"
    const FIND_IF_EXISTS_BY_NAME = "SELECT COUNT(p.id) FROM profile_pictures AS p JOIN users AS u u.id = ? WHERE p.user_id = u.id LIMIT 1"
    const DELETE_BY_NAME = "DELETE FROM profile_pictures WHERE user_id = ?";

    static $app;

    static function exists($user_id, $name = false) {
        $sth = self::$app->db->prepare($name?(self::FIND_IF_EXISTS_BY_NAME):(self::FIND_IF_EXISTS));
        $sth->execute([$user_id]);
        $result = $sth->fetch(\PDO::FETCH_ASSOC);
        return !!$result[0];
    }

    static function save($user_id, $file_location)
    {
        $result = null;
        if (extension_loaded('imagick') || class_exists('\Imagick')) {
            $imagick = new \Imagick();
            if ($imagick->readImage($file_location) === TRUE) {
                $imagick->stripImage();
                list($w, $h) = $imagick->getSize();
                $dimension = min($w, $h);
                $scaling_factor = 200/$dimension;
                if ($imagick->setImageFormat('jpeg') === TRUE) {
                    $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
                    $imagick->setImageCompressionQuality(90);
                    $imagick->resizeImage(round($w * $scaling_factor), round($h * $scaling_factor), \Imagick::FILTER_GAUSSIAN, 1, TRUE);
                    try {
                        $result = $imagick->getImageBlob();
                    } catch (\ImagickException $e) {
                    } finally {
                        $imagick->destroy();
                    }
                }
            }
        }
        if ($result === null) {
            return -1;
        }
        $data = base64_encode($result);
        $sth = self::$app->db->prepare(self::exists($user_id)?(self::UPDATE_QUERY):(self::INSERT_QUERY));
        return $sth->execute(array(':user_id' => $user_id, ':data' => $data));
    }

    static function get($username)
    {
        if (self::exists($username, true)) {
            $sth = self::$app->db->prepare(self::SELECT_QUERY);
            $sth->execute([$username]);
            $result = $sth->fetch(\PDO::FETCH_ASSOC);
            return base64_decode($result[0]);
        }
        return null;
    }

    static function removeProfilePicture($user_id) {
        $sth = self::$app->db->prepare(self::DELETE_BY_NAME);
        $sth->execute([$user_id]);
    }
}
User::$app = \Slim\Slim::getInstance();
