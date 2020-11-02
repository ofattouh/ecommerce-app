<?php

	/****************************************************************************************************
	* Woo commerce API autheticate script 
	* Only authorize the API call with the correct POST paremeters
	* V.1.0
	******************************************************************************************************/

	// Validate API POST authenticatation parameters
	if ( $consumer_key != $consumer_key_api || $consumer_secret != $consumer_secret_api || $endpoints != $endpoints_api || $api_url != $api_url_config ){	 
		$json_messages = array('status_code' => '403','status_msg' => 'Error! You are not authorized to run this script.');
		debug_msg($json_messages);
		exit;	
	}

?>