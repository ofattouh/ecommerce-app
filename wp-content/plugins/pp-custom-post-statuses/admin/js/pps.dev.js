jQuery(document).ready( function($) {
	$('#submitdiv').attr("id","submitdiv_pps");
	$('#adminmenu .wp-submenu a[href*="page=pp-stati"]').attr('href','admin.php?page=pp-stati&attrib_type=private');
});