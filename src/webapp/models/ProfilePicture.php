<?php

namespace tdt4237\webapp\models;

use tdt4237\webapp\Hash;

class ProfilePicture
{
    const INSERT_QUERY = "INSERT INTO profile_pictures (user_id, data) VALUES(:user_id, :data)";
    const UPDATE_QUERY = "UPDATE profile_pictures SET data = :data WHERE user_id = :user_id";
    const SELECT_QUERY = "SELECT p.data FROM profile_pictures AS p JOIN users AS u ON u.user = ? WHERE p.user_id = u.id LIMIT 1";
    const FIND_IF_EXISTS = "SELECT COUNT(*) FROM profile_pictures WHERE user_id = ? LIMIT 1";
    const FIND_IF_EXISTS_BY_NAME = "SELECT COUNT(p.user_id) FROM profile_pictures AS p JOIN users AS u ON u.user = ? WHERE p.user_id = u.id LIMIT 1";
    const DELETE_QUERY = "DELETE FROM profile_pictures WHERE user_id = ?";

    static $app;

    static function exists($user_id, $name = false) {
        $sth = self::$app->db->prepare($name?(self::FIND_IF_EXISTS_BY_NAME):(self::FIND_IF_EXISTS));
        $sth->execute([$user_id]);
        $result = $sth->fetch(\PDO::FETCH_NUM);
        return !!$result[0];
    }

    static function save($user_id, $file_location, $mime)
    {
        $result = null;
        if (extension_loaded('imagick') || class_exists('\Imagick')) {
            $imagick = new \Imagick();
            if ($imagick->readImage($file_location) === TRUE) {
                $imagick->stripImage();
                list($w, $h) = $imagick->getSize();
                if (min($w, $h) < 1) return -1;
                $dimension = max($w, $h);
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
        } else if (extension_loaded('gd')) {
            $type = explode('/', $mime, 2);
            $fn = 'imagecreatefrom'.$type[1];
            list($w, $h) = getimagesize($file_location);
            if (min($w, $h) < 1) return -1;
            $dimension = max($w, $h);
            $scaling_factor = 200/$dimension;
            $source = $fn($file_location);
            $profile = imagecreatetruecolor(round($w * $scaling_factor), round($h * $scaling_factor));
            imagecopyresampled($profile, $source, 0, 0, 0, 0, round($w * $scaling_factor), round($h * $scaling_factor), $w, $h);
            ob_start();
            imagejpeg($profile, NULL, 90);
            $result = ob_get_contents();
            ob_end_clean();
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
            $result = $sth->fetch(\PDO::FETCH_NUM);
            return base64_decode($result[0]);
        }
        return null;
    }

    static function removeProfilePicture($user_id) {
        $sth = self::$app->db->prepare(self::DELETE_QUERY);
        $sth->execute([$user_id]);
    }
}
ProfilePicture::$app = \Slim\Slim::getInstance();
