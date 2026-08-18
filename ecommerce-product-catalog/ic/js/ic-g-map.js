/**
 * Manages impleCode Locations plugin scripts
 *
 * @package impleCode\IC
 */

if (is_google_map_active()) {
	jQuery(
		function () {
			// Google map.
			if (jQuery( '.gmap' ).length > 0) {
				jQuery( '.gmap' ).each(
					function () {
						var id = jQuery( this ).attr( 'id' );

						id = id.replace( 'gmap-', '' );
						initialize( id );
					}
				);
			}

			if (jQuery( '#map' ).length > 0) {
				loadMap();
			}

		}
	);


	var directionsDisplay = new Array();
	var directionsService = new google.maps.DirectionsService();
	var map;
	var infowindow;
}

function is_google_map_active() {
	if (implecode_object.coordinates != '') {
		return true;
	}
	return false;
}

function initialize(id) {
	var coords = implecode_object.coordinates.split( "," );
	var point  = new google.maps.LatLng( parseFloat( coords[0] ), parseFloat( coords[1] ) );

	directionsDisplay['map-' + id] = new google.maps.DirectionsRenderer();
	var myOptions                  = {
		zoom: 15,

		panControl: false,
		zoomControl: false,
		scaleControl: false,

		mapTypeControl: true,
		mapTypeControlOptions: {
			style: google.maps.MapTypeControlStyle.DROPDOWN_MENU
		},
		navigationControl: true,
		navigationControlOptions: {
			style: google.maps.NavigationControlStyle.ZOOM_PAN,
			position: google.maps.ControlPosition.TOP_LEFT
		},

		mapTypeId: google.maps.MapTypeId.ROADMAP,
		center: point,
		zoom: 17
	}
	map = new google.maps.Map( document.getElementById( "gmap-" + id ), myOptions );

	var data = {
		'action': 'get_address'
	};

	jQuery.post(
		implecode_object.ajax_url,
		data,
		function (response) {
			infowindow = new google.maps.InfoWindow(
				{
					content: response
				}
			);
		}
	);

	var marker = new google.maps.Marker(
		{
			position: point,
			icon: implecode_object.map_marker,
			map: map,
			title: implecode_object.company_name
		}
	);

	// Home button.
	var homeControlDiv = document.createElement( 'DIV' );
	var homeControl    = new HomeControl( homeControlDiv, map, id );

	homeControlDiv.index = 1;
	map.controls[google.maps.ControlPosition.TOP_RIGHT].push( homeControlDiv );

	// Directions.
	directionsDisplay['map-' + id].setMap( map );
	directionsDisplay['map-' + id].setPanel( document.getElementById( "gdirections-" + id ) );

	google.maps.event.addListener(
		marker,
		'click',
		function () {
			infowindow.open( map, marker );
		}
	);

}

function calcRoute(id) {
	var start   = jQuery( '#routefrom-' + id ).val();
	var end     = implecode_object.company_inline_address;
	var request = {
		origin: start,
		destination: end,
		travelMode: google.maps.DirectionsTravelMode.DRIVING
	};
	directionsService.route(
		request,
		function (response, status) {
			if (status == google.maps.DirectionsStatus.OK) {
				directionsDisplay['map-' + id].setDirections( response );
			}
		}
	);
}

function loadMap() {
	var count      = 0;
	var zoom_rate  = 8;
	var infowindow = null;

	if (jQuery( '#map_zoom' ).length > 0) {
		zoom_rate = parseInt( jQuery( '#map_zoom' ).val() );
	}
	var myOptions = {
		zoom: zoom_rate,
		mapTypeId: google.maps.MapTypeId.ROADMAP
	}

	map = new google.maps.Map( document.getElementById( "map" ), myOptions );

	jQuery( ".map-address" ).each(
		function () {
			count++;
			var coords = jQuery( this ).val().split( "," );
			var point  = new google.maps.LatLng( parseFloat( coords[0] ), parseFloat( coords[1] ) );

			map.setCenter( point );
			var marker = new google.maps.Marker(
				{
					map: map,
					title: coords[2],
					position: point
				}
			);
			google.maps.event.addListener(
				marker,
				'click',
				function () {

					document.location = coords[3];

				}
			);

		}
	);
}

function HomeControl(controlDiv, map, id) {

	var coords               = implecode_object.coordinates.split( "," );
	var point                = new google.maps.LatLng( parseFloat( coords[0] ), parseFloat( coords[1] ) );
	controlDiv.style.padding = '5px';

	var controlUI                   = document.createElement( 'DIV' );
	controlUI.style.backgroundColor = 'white';
	controlUI.style.borderStyle     = 'solid';
	controlUI.style.borderWidth     = '2px';
	controlUI.style.cursor          = 'pointer';
	controlUI.style.textAlign       = 'center';
	controlUI.title                 = 'Click to set the map to Home';
	controlDiv.appendChild( controlUI );

	var controlText                = document.createElement( 'DIV' );
	controlText.style.fontFamily   = 'Arial,sans-serif';
	controlText.style.fontSize     = '12px';
	controlText.style.paddingLeft  = '4px';
	controlText.style.paddingRight = '4px';
	controlText.innerHTML          = '<b>Home</b>';
	controlUI.appendChild( controlText );

	google.maps.event.addDomListener(
		controlUI,
		'click',
		function () {
			map.setCenter( point )
		}
	);
}
