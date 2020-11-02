<?php

	// Force the browser to download the file so it can be tracked in GA as an event download
	
	if ( ! isset($_GET['filepath']) ) exit; // Exit if accessed directly
	
	$file_extension = strtolower(substr(strrchr($_GET['filepath'],"."),1)); // file extension
	
	// Set the MIME content type based on the file extension
	switch ($file_extension) {
        case "pdf": 
        	$ctype="application/pdf"; break;
        	
        case "exe": 
        	$ctype="application/octet-stream"; break;
        	
        case "zip": 
        	$ctype="application/zip"; break;
        	
        case "doc": case "docx": 
        	$ctype="application/msword"; break;
        	
        case "xls":  
        	$ctype="application/vnd.ms-excel"; break;
        	
        case "ppt":  
    		$ctype="application/vnd.ms-powerpoint"; break;
    		
        case "gif":  
 			$ctype="image/gif"; break;
 			
        case "png":  
    		$ctype="image/png"; break;
    		
        case "jpe": case "jpeg": case "jpg": 
        	$ctype="image/jpg"; break;
        	
        default: 
        	$ctype="application/force-download";
    }
            
	header('Content-Description: File Transfer');
	header('Content-Type: '. $ctype);
	header('Content-Disposition: attachment; filename="'.basename($_GET['filepath']).'"');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: ' . filesize($_SERVER['DOCUMENT_ROOT'].dirname($_GET['filepath']).'/'.basename($_GET['filepath'])));
	readfile($_SERVER['DOCUMENT_ROOT'].dirname($_GET['filepath']).'/'.basename($_GET['filepath']));
	exit;
	

?>