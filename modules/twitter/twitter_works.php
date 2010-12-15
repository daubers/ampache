<?php
	require_once '../../lib/init.php';
        require_once( Config::get('prefix') . "/modules/twitter/twitteroauth/twitteroauth.php");

	session_start();
	
	if(!empty($_SESSION['twitterusername'])) {
		header('Location: ' . Config::Get('web_path') . '/modules/twitter/twitter_update.php');
		debug_event("Twitter", "Twitter user has logged in this session.", "5");
	}

	if(!empty($_GET['oauth_verifier']) && !empty($_SESSION['oauth_token']) && !empty($_SESSION['oauth_token_secret'])){
		debug_event("Twitter", "Got all 3 pieces for auth", "5");
	} else {
		if( $_SESSION['twitterCount'] < 4 ) {
			debug_event("Twitter", "Didn't get all 3 auth pieces, going to try again.  Try #" . $_SESSION['twitterCount'], "5");
			$_SESSION['twitterCount']++;
			header('Location: ' . Config::Get('web_path') . '/modules/twitter/twitter_login.php');
		} else {
			debug_event("Twitter", "Failed to auth too many times.  Giving up.", "5");
			header('Location: ' . Config::Get('web_path') );
		}
	}

	// TwitterOAuth instance, with two new parameters we got in twitter_login.php
	$twitteroauth = new TwitterOAuth( Config::get('twitter_consumer_key'), Config::get('twitter_consumer_secret'), $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
	if( !isset($twitteroauth) ) {
		debug_event("Twitter", "Couldn't create OAuth object.", "5");
		header('Location: ' . Config::get('web_path'));
	}
	// Let's request the access token
	$access_token = $twitteroauth->getAccessToken($_GET['oauth_verifier']);
	if( !isset($access_token) ) {
		debug_event("Twitter", "Couldn't get access token", "5");
		header('Location: ' . Config::get('web_path'));
	}
	// Save it in a session var
	$_SESSION['access_token'] = $access_token;
	

	// Let's get the user's info
	$user_info = $twitteroauth->get('account/verify_credentials');
	
	debug_event("Twttier", "User ID:{$user_info->id}. ScreenName:{$user_info->screen_name}.", "5");
	debug_event("Twitter", "access token:" . $access_token['oauth_token'], "5");
	debug_event("Twitter", "access token secret:" .  $access_token['oauth_token_secret'], "5");

        if( isset($user_info->error)) {
		debug_event("Twitter", "Error verifying credentials", "5");
		session_destroy();
		header('Location: ' . Config::get('web_path'));
        } else {
		
		$link = mysql_connect(Config::get('database_hostname'), Config::get('database_username') , Config::get('database_password') );
        	mysql_select_db( Config::get('database_name') , $link);
                
		// Let's find the user by its ID
                $query = mysql_query("SELECT * FROM twitter_users WHERE oauth_provider = 'twitter' AND oauth_uid = ". $user_info->id . " AND ampache_id = " . $_SESSION['userdata']['uid']) or die( mysql_error() );
                $result = mysql_fetch_array($query);

		echo "<br>ampache_id: {$_SESSION['userdata']['uid']}";
		echo "<br>oauth_uid: {$user_info->id}";
		echo "<br>oauth_token: {$access_token['oauth_token']}";
		echo "<br>oauth_secret: {$access_token['oauth_token_secret']}";
		echo "<br>username: {$user_info->screen_name} <br>";

                // If not, let's add it to the database
                if(empty($result)){
			debug_event("Twitter", "First time user.  Add them to the DB.", "5");
			$insert_query ="INSERT INTO twitter_users (ampache_id, oauth_provider, oauth_uid, oauth_token, oauth_secret, username) VALUES ( '{$_SESSION['userdata']['uid']}', 'twitter', '{$user_info->id}', '{$access_token['oauth_token']}', '{$access_token['oauth_token_secret']}', '{$user_info->screen_name}')";

			debug_event("Twitter", "Insert query: " . $insert_query, "5");
			$insert_run = mysql_query($insert_query) or die( mysql_error() );

                        $select_query = "SELECT * FROM twitter_users WHERE username = '" . $user_info->screen_name . "' AND ampache_id = " . $_SESSION['userdata']['uid']; 
			debug_event("Twitter", "Select query: {$query}", "5");
                        $select_run = mysql_query( $select_query ) or die( mysql_error() );
			$result = mysql_fetch_array($select_run);
                } else {
                        debug_event("Twitter", "Update the DB to hold current tokens", "5");

			$update_query = "UPDATE twitter_users SET oauth_token = '{$access_token['oauth_token']}', oauth_secret = '{$access_token['oauth_token_secret']}' WHERE oauth_provider = 'twitter' AND oauth_uid = {$user_info->id} AND ampache_id = {$_SESSION['userdata']['uid']}";
			debug_event("Twitter", "update query: " . $update_query, "6");
			$update_run = mysql_query($update_query) or die( mysql_error);

			$select_query = "SELECT * FROM twitter_users WHERE username = '" . $user_info->screen_name . "'";
			debug_event("Twitter", "select query: " . $select_query, "6");
			$select_run = mysql_query($select_query) or die( mysql_error() );

                        $result = mysql_fetch_array($select_run);
                }

	        $_SESSION['id'] = $result['id'];
        	$_SESSION['twitterusername'] = $result['username'];
        	$_SESSION['oauth_uid'] = $result['oauth_uid'];
        	$_SESSION['oauth_provider'] = $result['oauth_provider'];
        	$_SESSION['oauth_token'] = $result['oauth_token'];
        	$_SESSION['oauth_secret'] = $result['oauth_secret'];

		mysql_close($link);

		header('Location: ' . Config::get('web_path') . '/modules/twitter/twitter_update.php');
        }
?>
