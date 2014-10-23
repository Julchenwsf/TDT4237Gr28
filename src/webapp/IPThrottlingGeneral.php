<?php
namespace tdt4237\webapp;


class IPThrottlingGeneral {
    // array of throttle settings. # failed_attempts => response
    private static $default_throttle_settings = [
        100 => 2, 			//delay in seconds
        1000 => 3600	//delay of one hour if more than 1000 requests in the $time_frame_minutes
    ];

	static $app;


    //database config
    private static $_db = [
        'auto_clear' => true
    ];

    //time frame
    private static $time_frame_minutes = 5;

    /** setup and return database connection */
    private static function _databaseConnect(){
        return self::$app->db;
    }

    /** add a failed login attempt to database. returns true, or error */
    public static function addRequest($ip_address){
        $db = IPThrottlingGeneral::_databaseConnect(); //get db connection

		//attempt to insert failed login attempt
        try{
            $stmt = $db->query('INSERT INTO requests VALUES (null,"'.$ip_address.'", date(\'now\'))');
            return true;
        } catch(PDOException $ex){
            //return errors
            return $ex;
        }
    }
    //get the current login status. either safe, delay, catpcha, or error
    public static function getRequestStatus($ip, $options = null){
        //get db connection
        $db = IPThrottlingGeneral::_databaseConnect();

        //setup response array
        $response_array = array(
            'status' => 'safe',
            'message' => null
        );

        //attempt to retrieve latest failed login attempts
        $stmt = null;
		$row = null;
		$latest_request_datetime = null;
        try{
			//$stmt = $db->query('DROP TABLE `requests`');
			$stmt = $db->query('CREATE TABLE IF NOT EXISTS `requests` (`id` integer PRIMARY KEY,`ip_address` string DEFAULT NULL,`attempted_at` datetime NOT NULL)');
			$stmt = $db->query('SELECT MAX(attempted_at) AS attempted_at FROM user_failed_logins');
            $row = $stmt-> fetch();
            $latest_request_datetime = (int) date('U', strtotime($row['attempted_at'])); //get latest request's timestamp
		} catch(PDOException $ex){
            //return error
            $response_array['status'] = 'error';
            $response_array['message'] = $ex;
        }

        //get local var of throttle settings. check if options parameter set
        if($options == null){
            $throttle_settings = self::$default_throttle_settings;
        }else{
            //use options passed in
            $throttle_settings = $options;
        }
        //grab first throttle limit from key
        reset($throttle_settings);
        $first_throttle_limit = key($throttle_settings);

        //attempt to retrieve latest failed login attempts
        try{
            //get all failed attempst within time frame
			echo $ip . " " . inet_pton($ip) . "\n";
			echo "SELECT * FROM requests WHERE ip_address = '" . $ip . "' AND attempted_at > DATE('now', '-".self::$time_frame_minutes." minutes')";
            $get_number = $db->query("SELECT * FROM requests WHERE ip_address = '" . $ip . "' AND attempted_at > DATE('now', '-".self::$time_frame_minutes." minutes')");
            $number_recent_failed = $get_number->rowCount();
            //reverse order of settings, for iteration
            krsort($throttle_settings);

            //if number of failed attempts is >= the minimum threshold in throttle_settings, react
            if($number_recent_failed >= $first_throttle_limit ){
                //it's been decided the # of failed logins is troublesome. time to react accordingly, by checking throttle_settings
                foreach ($throttle_settings as $attempts => $delay) {
                    if ($number_recent_failed > $attempts) {
                        // we need to throttle based on delay
                        if (is_numeric($delay)) {
                            //find the time of the next allowed login
                            $next_login_minimum_time = $latest_request_datetime + $delay;

                            //if the next allowed login time is in the future, calculate the remaining delay
                            if(time() < $next_login_minimum_time){
                                $remaining_delay = $next_login_minimum_time - time();
                                // add status to response array
                                $response_array['status'] = 'delay';
                                $response_array['message'] = $remaining_delay;
                            }else{
                                // delay has been passed, safe to login
                                $response_array['status'] = 'safe';
                            }
                        } else {
                            // add status to response array
                            $response_array['status'] = 'captcha';
                        }
                        break;
                    }
                }

            }
            //clear database if config set
            if(self::$_db['auto_clear'] == true){
                //attempt to delete all records that are no longer recent/relevant
                try{
                    $stmt = $db->query('DELETE from requests WHERE attempted_at < DATE(\'NOW\', \'-'.(self::$time_frame_minutes * 2).' MINUTES\')');
                    $stmt->execute();

                } catch(PDOException $ex){
                    $response_array['status'] = 'error';
                    $response_array['message'] = $ex;
                }
            }

        } catch(PDOException $ex){
            //return error
            $response_array['status'] = 'error';
            $response_array['message'] = $ex;
        }

        //return the response array containing status and message
        return $response_array;
    }

    //clear the database
    public static function clearDatabase(){
        //get db connection
        $db = IPThrottlingGeneral::_databaseConnect();

        //attempt to delete all records
        try{
            $stmt = $db->query('DELETE from requests');
            return true;
        } catch(PDOException $ex){
            //return errors
            return $ex;
        }
    }
}
IPThrottlingGeneral::$app = \Slim\Slim::getInstance();