<?php
		
	/**
	** Test the affiliate script created by Suthan
	*/
	
	// Decode the JSON object and fetch the POST parameters
 	//$_POST = json_decode(file_get_contents("php://input"), true);
 	
 	$url              = 'dev.pshsa.ca';
 	$script_affiliate = ( $_POST['script_affiliate'] != '' && isset($_POST['script_affiliate']) )? $_POST['script_affiliate'] : '//affiliate.pshsa.ca/affiliateFiles/OLTCA.js';
 	
 	// Validate
	if ( $script_affiliate != '' && $_SERVER['HTTP_HOST'] == $url ) : ?>
		 <script type='text/javascript' src='<?php echo $script_affiliate; ?>'></script>      
    <?php endif; ?>