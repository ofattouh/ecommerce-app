jQuery(document).ready( function($) {
	$('a.show-cap-map').click( function(e) {
		$('div.pp-conditions table th.column-cap_map').show();
		$('div.pp-conditions table td.cap_map').show();
		$('span.cap-map-note').show();
		e.preventDefault();
	});
});