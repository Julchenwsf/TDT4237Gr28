<?php
namespace tdt4237\webapp;
    /**
     	Inspiration has been taken from code found on Internet here:

	  Brute Force Block class

		Implementation by Evan Francis for use in AlpineAuth library, 2014.
       Inspired by work of Corey Ballou, http://stackoverflow.com/questions/2090910/how-can-i-throttle-user-login-attempts-in-php.
      	MIT License http://opensource.org/licenses/MIT

	  and changed and adapted by Group 28 of Software Security

    ======================== 	Setup 	  ========================
    1) setup database connection in $_db array.
    1a. The 'auto_clear' option determines whether or not older database entries are cleared automatically
    2) (optional) set default throttle settings in $default_throttle_settings_array

    ==================== To Create SQLite Database ===============
    CREATE TABLE IF NOT EXISTS `user_failed_logins` (
    `id` integer PRIMARY KEY,
    `ip_address` string DEFAULT NULL,
    `attempted_at` datetime NOT NULL
    );

    ==================== 	Usage	 ====================
    === get login status. use this when building your login form ==
    $BFBresponse = BruteForceBlock::getLoginStatus();
    switch ($BFBresponse['status']){
		case 'safe':
		//safe to login
		break;
		case 'error':
		//error occured. get message
		$error_message = $BFBresponse['message'];
		break;
		case 'delay':
		//time delay required before next login
		$remaining_delay_in_seconds = $BFBresponse['message'];
		break;
		case 'captcha': //captcha required
		break;
    }

    == add a failed login attempt ==
    $BFBresponse = BruteForceBlock::addFailedLoginAttempt($ip_address);

    == clear the database ==
    $BFBresponse = BruteForceBlock::clearDatabase();
    if($BFBresponse !== true){
    $error_message = $BFBresponse;
    }
     */
//brute force block for logins and password resets
class BruteForceBlock {
    // array of throttle settings. # failed_attempts => response
    private static $default_throttle_settings = [
        5 => 20, 			//delay in seconds
        1000 => 3600	//delay of one hour if more than 1000 requests in the $time_frame_minutes
    ];

	static $app;

    //database config
    private static $_db = [
        'auto_clear' => true
    ];

    //time frame to use when retrieving the number of recent failed logins from database
    private static $time_frame_minutes = 5;

    //setup and return database connection
    private static function _databaseConnect() {
        //return the db connection object
        return self::$app->db;
    }
    //add a failed login attempt to database. returns true, or error
    public static function addFailedLoginAttempt($ip_address){
        //get db connection
        $db = BruteForceBlock::_databaseConnect();

        //attempt to insert failed login attempt
        try{
	        $db->query('INSERT INTO user_failed_logins VALUES (null,"'.$ip_address.'", datetime(\'now\'))');
            return true;
        } catch(PDOException $ex){
            return $ex;
        }
    }
    //get the current login status. either safe, delay, catpcha, or error
    public static function getLoginStatus($ip, $options = null){
        //get db connection
        $db = BruteForceBlock::_databaseConnect();

        //setup response array
        $response_array = array(
            'status' => 'safe',
            'message' => null
        );

        //attempt to retrieve latest failed login attempts
        $stmt = null;
        $latest_failed_logins = null;
        $row = null;
        $latest_failed_attempt_datetime = null;
        try{
			//$stmt = $db->query('DROP TABLE `user_failed_logins`');
			$db->query('CREATE TABLE IF NOT EXISTS `user_failed_logins` (`id` integer PRIMARY KEY,`ip_address` string DEFAULT NULL,`attempted_at` datetime NOT NULL)');
			$stmt = $db->query('SELECT MAX(attempted_at) AS attempted_at FROM user_failed_logins');
            $row = $stmt->fetch();
			date_default_timezone_set('UTC');
			$latest_failed_attempt_datetime = (int) date('U', strtotime($row['attempted_at'])); //get latest attempt's timestamp
			//echo $latest_failed_attempt_datetime;
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
			$get_number = $db->query('SELECT count(*) as conta FROM user_failed_logins WHERE ip_address = "' . $ip . '" AND attempted_at > DATETIME(\'now\', \'-'.self::$time_frame_minutes.' minutes\')');
            $number_recent_failed = $get_number->fetchColumn(0);
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
                            $next_login_minimum_time = $latest_failed_attempt_datetime + $delay;
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
                            //$remaining_delay = $delay - (time() - $latest_failed_attempt_datetime); //correct
                            //echo 'You must wait ' . $remaining_delay . ' seconds before your next login attempt';
		              } else {
                            // add status to response array (in our case, we have no captcha)
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
                    $stmt = $db->query('DELETE from user_failed_logins WHERE attempted_at < DATETIME(\'NOW\', \'-'.(self::$time_frame_minutes * 2).' MINUTES\')');
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
        $db = BruteForceBlock::_databaseConnect();

        //attempt to delete all records
        try{
            $stmt = $db->query('DELETE from user_failed_logins');
            return true;
        } catch(PDOException $ex){
            //return errors
            return $ex;
        }
    }
}
BruteForceBlock::$app = \Slim\Slim::getInstance();