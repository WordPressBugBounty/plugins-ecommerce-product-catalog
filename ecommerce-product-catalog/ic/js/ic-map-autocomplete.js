/**
 * Shared Google Maps autocomplete helpers.
 *
 * @package ImpleCode
 */

if ( typeof implecode === 'undefined' ) {
	var implecode = [];
}

implecode.gmap = [];

jQuery( window ).on(
	'load',
	function () {
		/* globals google */
		ic_g_map_autocomplete_init();
	}
);

jQuery( document ).ready(
	function ( $ ) {
		implecode.ic.addAction(
			'ic_g_map_address_changed',
			function ( addressContainer ) {
				ic_g_map_update_route( addressContainer );
				implecode.ic.removeAction( 'ic_g_map_address_loaded', ic_g_map_update_route );
			}
		);
		implecode.ic.addAction( 'ic_g_map_address_loaded', ic_g_map_update_route );

		$( '.ic-g-map-autocomplete, .ic-freight-platform-post-load' ).on(
			'change',
			'.ic-locations-selector,.ic-g-map-autocomplete .ic-locations-selector',
			function () {
				var selector    = $( this );
				var newLocation = selector.val();

				$( '.ic-g-map-autocomplete .ic-locations-selector' ).each(
					function () {
						$( this ).val( newLocation );
						ic_g_map_update_locations( $( this ) );
					}
				);
			}
		);
	}
);

function ic_g_map_update_route( addressContainer ) {
	var originLat      = '';
	var originLng      = '';
	var destinationLat = '';
	var destinationLng = '';

	if ( addressContainer.closest( '.ic-g-map-origin' ).length > 0 ) {
		originLat      = addressContainer.closest( '.ic-g-map-origin' ).find( '.ic-map-lat' ).val();
		originLng      = addressContainer.closest( '.ic-g-map-origin' ).find( '.ic-map-lng' ).val();
		destinationLat = jQuery( '.ic-g-map-destination' ).find( '.ic-map-lat' ).val();
		destinationLng = jQuery( '.ic-g-map-destination' ).find( '.ic-map-lng' ).val();
	} else if ( addressContainer.closest( '.ic-g-map-destination' ).length > 0 ) {
		destinationLat = addressContainer.closest( '.ic-g-map-destination' ).find( '.ic-map-lat' ).val();
		destinationLng = addressContainer.closest( '.ic-g-map-destination' ).find( '.ic-map-lng' ).val();
		originLat      = jQuery( '.ic-g-map-origin' ).find( '.ic-map-lat' ).val();
		originLng      = jQuery( '.ic-g-map-origin' ).find( '.ic-map-lng' ).val();
	}

	if ( originLat && originLng && destinationLat && destinationLng ) {
		ic_g_map_route(
			originLat,
			originLng,
			destinationLat,
			destinationLng,
			jQuery( '.ic-route-map-container' )[0]
		);
		implecode.ic.doAction( 'ic_g_map_route_set' );
	} else {
		implecode.ic.doAction( 'ic_g_map_route_not_set' );
	}
}

function ic_g_map_update_locations( selector ) {
	var selectorContainer     = null;
	var newLocation           = '';
	var autocompleteContainer = null;

	implecode.disable_container( selector );
	selectorContainer     = selector.closest( '.ic-g-map-autocomplete' );
	newLocation           = selector.val();
	autocompleteContainer = selector.next( '.ic-g-map-autocomplete-input-container' );

	if ( newLocation && ! isNaN( newLocation ) ) {
		wp.apiFetch(
			{
				path: 'implecode/v1/g-map-autocomplete/location-selected/' + newLocation,
				method: 'POST',
				data: {
					name: selectorContainer.data( 'name' ),
					search_placeholder: selectorContainer.data( 'search_placeholder' ),
				},
			}
		).then(
			function ( response ) {
				if ( autocompleteContainer.length > 0 ) {
					autocompleteContainer.replaceWith( response );
				} else {
					jQuery( response ).insertAfter( selector );
				}
				ic_g_map_autocomplete_init();
				implecode.enable_container( selector );
			}
		);
	} else {
		autocompleteContainer.remove();
		implecode.enable_container( selector );
	}
}

function ic_g_map_autocomplete_init() {
	jQuery( '.ic-map-autocomplete-input' ).each(
		function () {
			var selectedPlaceId = '';
			var container       = null;
			var forcedLat       = '';
			var forcedLng       = '';
			var placesService   = null;
			var request         = null;

			if ( jQuery( this ).hasClass( 'pac-target-input' ) ) {
				return true;
			}

			ic_g_map_autocomplete( jQuery( this )[0] );
			selectedPlaceId = jQuery( this ).next( '.ic-map-autocomplete-place_id' ).val();
			container       = jQuery( this ).closest( '.ic-g-map-autocomplete' );

			if ( jQuery( this ).val().length > 1 && selectedPlaceId.length > 1 ) {
				forcedLat     = container.find( '.ic-map-lat' ).val();
				forcedLng     = container.find( '.ic-map-lng' ).val();
				placesService = new google.maps.places.PlacesService( container.find( '.ic-map' )[0] );
				request       = {
					placeId: selectedPlaceId,
					fields: [ 'address_components', 'formatted_address', 'geometry', 'name', 'place_id' ],
				};

				placesService.getDetails(
					request,
					function ( initPlace ) {
						ic_g_map_autocomplete_init_map(
							container,
							initPlace,
							parseFloat( forcedLat ),
							parseFloat( forcedLng )
						);
					}
				);
			} else {
				ic_g_map_clear( container );
			}
		}
	);
}

function ic_g_map_autocomplete( input ) {
	var options      = {
		componentRestrictions: { country: jQuery( input ).data( 'countries' ) },
		fields: [ 'address_components', 'formatted_address', 'geometry', 'name', 'place_id' ],
		strictBounds: false,
	};
	var autocomplete = new google.maps.places.Autocomplete( input, options );

	autocomplete.addListener(
		'place_changed',
		function () {
			ic_g_map_autocomplete_fill( autocomplete, input );
		}
	);
}

function ic_g_map_autocomplete_fill( autocomplete, input ) {
	var container            = jQuery( input ).closest( '.ic-g-map-autocomplete' );
	var addressContainer     = container.find( '.ic-address-form' );
	var coordinatesContainer = container.find( '.ic-map-coordinates' );
	var place                = autocomplete.getPlace();

	jQuery( input ).next( '.ic-map-autocomplete-place_id' ).val( place.place_id );
	coordinatesContainer.find( '.ic-map-lat' ).val( place.geometry.location.lat() );
	coordinatesContainer.find( '.ic-map-lng' ).val( place.geometry.location.lng() );
	coordinatesContainer.find( '.ic-map-lat-text' ).text( place.geometry.location.lat() );
	coordinatesContainer.find( '.ic-map-lng-text' ).text( place.geometry.location.lng() );
	jQuery( input ).val( place.name );

	if ( addressContainer.length > 0 ) {
		ic_g_map_fill_address( addressContainer, place );
		addressContainer.find( '.ic-address2' ).trigger( 'focus' );
	}

	ic_g_map_autocomplete_init_map( container, place );
}

function ic_g_map_fill_address( addressContainer, place ) {
	var address1    = '';
	var postcode    = '';
	var city        = '';
	var state       = '';
	var country     = '';
	var countryCode = '';

	if ( 0 === addressContainer.length ) {
		return;
	}

	place.address_components.forEach(
		function ( component ) {
			var componentType = component.types[0];

			switch ( componentType ) {
				case 'street_number':
					address1 = component.long_name + ' ' + address1;
					break;
				case 'route':
					address1 += component.short_name;
					break;
				case 'postal_code':
					postcode = component.long_name + postcode;
					break;
				case 'postal_code_suffix':
					postcode = postcode + '-' + component.long_name;
					break;
				case 'locality':
					city = component.long_name;
					break;
				case 'administrative_area_level_1':
					state = component.short_name;
					break;
				case 'country':
					country     = component.long_name;
					countryCode = component.short_name;
					break;
			}
		}
	);

	addressContainer.find( '.ic-address1' ).val( address1 );
	addressContainer.find( '.ic-postcode' ).val( postcode );
	addressContainer.find( '.ic-state' ).val( state );
	addressContainer.find( '.ic-city' ).val( city );
	addressContainer.find( '.ic-country' ).val( country );
	addressContainer.find( '.ic-country_code' ).val( countryCode );
	addressContainer.find( '.ic-formatted-address' ).val( place.formatted_address );
	addressContainer.closest( '.ic-g-map-autocomplete' ).find( '.ic-g-map-address' ).text( place.formatted_address );
	implecode.ic.doAction( 'ic_g_map_address_changed', addressContainer );
}

function ic_g_map_clear( container ) {
	container.find( '.ic-address1' ).val( '' );
	container.find( '.ic-postcode' ).val( '' );
	container.find( '.ic-state' ).val( '' );
	container.find( '.ic-city' ).val( '' );
	container.find( '.ic-country' ).val( '' );
	container.find( '.ic-formatted-address' ).val( '' );
	container.closest( '.ic-g-map-autocomplete' ).find( '.ic-g-map-address' ).text( '' );
	container.find( '.ic-map-lat' ).val( '' );
	container.find( '.ic-map-lng' ).val( '' );
	container.find( '.ic-map-lat-text' ).text( '' );
	container.find( '.ic-map-lng-text' ).text( '' );
	container.find( '.ic-show-with-g-map' ).hide();
}

function ic_g_map_autocomplete_init_map( container, initPlace, lat, lng ) {
	var position             = null;
	var map                  = null;
	var request              = null;
	var geocoder             = null;
	var service              = null;
	var coordinatesContainer = null;

	if ( typeof lat === 'undefined' ) {
		lat = initPlace.geometry.location.lat();
	}
	if ( typeof lng === 'undefined' ) {
		lng = initPlace.geometry.location.lng();
	}

	position             = { lat: lat, lng: lng };
	map                  = new google.maps.Map(
		container.find( '.ic-map' )[0],
		{
			center: position,
			zoom: 18,
			streetViewControl: false,
			fullscreenControl: false,
			mapId: 'bae2dc6ccae31c5d',
		}
	);
	request              = {
		placeId: initPlace.place_id,
		fields: [ 'name', 'formatted_address', 'place_id', 'geometry' ],
	};
	geocoder             = new google.maps.Geocoder();
	service              = new google.maps.places.PlacesService( map );
	coordinatesContainer = container.find( '.ic-map-coordinates' );

	service.getDetails(
		request,
		function ( place, status ) {
			var radius           = null;
			var radiusValue      = null;
			var radiusCircle     = null;
			var draggable        = true;
			var marker           = null;
			var addressContainer = null;

			if (
				status !== google.maps.places.PlacesServiceStatus.OK ||
				! place ||
				! place.geometry ||
				! place.geometry.location
			) {
				return;
			}

			radius = container.find( '.ic-map-radius-input' );
			if ( radius && radius.length > 0 ) {
				radiusValue = radius.val();
				if ( radiusValue ) {
					radiusCircle = new google.maps.Circle(
						{
							strokeColor: '#FF0000',
							strokeOpacity: 0.8,
							strokeWeight: 2,
							fillColor: '#FF0000',
							fillOpacity: 0.35,
							map: map,
							center: position,
							radius: Math.round( radiusValue ),
							editable: true,
						}
					);
					google.maps.event.addListener(
						radiusCircle,
						'radius_changed',
						function () {
							radius.val( Math.round( radiusCircle.getRadius() ) );
						}
					);
					google.maps.event.addListener(
						radiusCircle,
						'center_changed',
						function () {
							var centerChanged = radiusCircle.getCenter();

							coordinatesContainer.find( '.ic-map-lat' ).val( centerChanged.lat() );
							coordinatesContainer.find( '.ic-map-lng' ).val( centerChanged.lng() );
							coordinatesContainer.find( '.ic-map-lat-text' ).text( centerChanged.lat() );
							coordinatesContainer.find( '.ic-map-lng-text' ).text( centerChanged.lng() );
						}
					);
				}
			} else {
				marker           = new google.maps.marker.AdvancedMarkerElement(
					{
						map: map,
						position: position,
						gmpDraggable: draggable,
					}
				);
				addressContainer = container.find( '.ic-address-form' );

				if ( draggable ) {
					google.maps.event.addListener(
						marker,
						'dragend',
						function ( evt ) {
							coordinatesContainer.find( '.ic-map-lat' ).val( evt.latLng.lat() );
							coordinatesContainer.find( '.ic-map-lng' ).val( evt.latLng.lng() );
							coordinatesContainer.find( '.ic-map-lat-text' ).text( evt.latLng.lat() );
							coordinatesContainer.find( '.ic-map-lng-text' ).text( evt.latLng.lng() );

							if ( addressContainer.length > 0 ) {
								geocoder.geocode(
									{
										location: marker.position,
									},
									function ( responses ) {
										if ( responses && responses.length > 0 ) {
											ic_g_map_fill_address( container, responses[0] );
										}
									}
								);
							}
						}
					);
				}

				if ( addressContainer.length > 0 ) {
					addressContainer.closest( '.ic-g-map-autocomplete' ).find( '.ic-g-map-address' ).text( place.formatted_address );
					implecode.ic.doAction( 'ic_g_map_address_loaded', addressContainer );
				}
			}

			container.find( '.ic-show-with-g-map' ).show();
		}
	);
}

function ic_g_map_route( originLat, originLng, destinationLat, destinationLng, mapContainer ) {
	var preOriginLat        = jQuery( mapContainer ).data( 'pre_origin_lat' );
	var preOriginLng        = jQuery( mapContainer ).data( 'pre_origin_lng' );
	var preDestinationLat   = jQuery( mapContainer ).data( 'pre_destination_lat' );
	var preDestinationLng   = jQuery( mapContainer ).data( 'pre_destination_lng' );
	var directionsService   = null;
	var origin              = null;
	var destination         = null;
	var directionsContainer = null;
	var routeSummary        = [];
	var map                 = null;
	var request             = null;
	var directionsDisplay   = null;

	if (
		preOriginLat === originLat &&
		preOriginLng === originLng &&
		preDestinationLat === destinationLat &&
		preDestinationLng === destinationLng
	) {
		return;
	}

	jQuery( mapContainer ).data( 'pre_origin_lat', originLat );
	jQuery( mapContainer ).data( 'pre_origin_lng', originLng );
	jQuery( mapContainer ).data( 'pre_destination_lat', destinationLat );
	jQuery( mapContainer ).data( 'pre_destination_lng', destinationLng );

	directionsService   = new google.maps.DirectionsService();
	origin              = new google.maps.LatLng( originLat, originLng );
	destination         = new google.maps.LatLng( destinationLat, destinationLng );
	directionsContainer = jQuery( mapContainer ).closest( '.ic-route-container' ).find( '.ic-route-map-directions' );

	if ( jQuery( mapContainer ).closest( 'form' ).length > 0 ) {
		routeSummary = jQuery( mapContainer ).closest( 'form' ).find( '.ic-route-summary' );
	} else {
		routeSummary = jQuery( mapContainer ).closest( '.ic-ajax-container' ).find( '.ic-route-summary' );
	}

	directionsContainer.html( '' );
	map               = new google.maps.Map(
		mapContainer,
		{
			center: origin,
			zoom: 18,
			streetViewControl: false,
			fullscreenControl: false,
			mapId: 'bae2dc6ccae31c5d',
		}
	);
	request           = {
		origin: origin,
		destination: destination,
		travelMode: google.maps.DirectionsTravelMode.DRIVING,
	};
	directionsDisplay = new google.maps.DirectionsRenderer();
	directionsDisplay.setMap( map );
	directionsDisplay.setPanel( directionsContainer[0] );

	directionsService.route(
		request,
		function ( response, status ) {
			if ( status !== google.maps.DirectionsStatus.OK ) {
				return;
			}

			directionsDisplay.setDirections( response );
			if ( routeSummary.length > 0 ) {
				if ( routeSummary.find( '.ic-route-summary-distance' ).length > 0 ) {
					routeSummary.find( '.ic-route-summary-distance' ).html( response.routes[0].legs[0].distance.text );
					routeSummary.find( '.ic-route-summary-distance' ).next( 'input' ).val( response.routes[0].legs[0].distance.text );
				}
				if ( routeSummary.find( '.ic-route-summary-time' ).length > 0 ) {
					routeSummary.find( '.ic-route-summary-time' ).html( response.routes[0].legs[0].duration.text );
					routeSummary.find( '.ic-route-summary-time' ).next( 'input' ).val( response.routes[0].legs[0].duration.text );
				}
				if ( routeSummary.find( '.ic-route-summary-ascent' ).length > 0 || routeSummary.find( '.ic-route-summary-descent' ).length > 0 ) {
					ic_g_map_elevator( origin, destination, response.routes[0].legs[0].distance.value / 1000 * 2 );
				}
				implecode.ic.doAction( 'ic_g_map_generate_route_data', [ origin, destination ] );
			}
		}
	);
}

function ic_g_map_elevator( origin, destination, samples ) {
	var elevator = null;

	samples = Math.ceil( samples );
	if ( samples < 2 ) {
		samples = 2;
	}

	elevator = new google.maps.ElevationService();
	elevator.getElevationAlongPath(
		{
			path: [ origin, destination ],
			samples: samples,
		},
		ic_g_map_elevator_process
	);
}

function ic_g_map_elevator_process( results, status ) {
	var routeSummary  = jQuery( '.ic-route-summary' );
	var totalAscent   = 0;
	var totalDescent  = 0;
	var prevElevation = 0;

	if ( status !== google.maps.ElevationStatus.OK ) {
		return;
	}
	if ( 0 === routeSummary.length ) {
		return;
	}

	results.forEach(
		function ( result ) {
			if ( 0 !== prevElevation ) {
				if ( prevElevation > result.elevation ) {
					totalDescent += prevElevation - result.elevation;
				} else {
					totalAscent += result.elevation - prevElevation;
				}
			}
			prevElevation = result.elevation;
		}
	);

	routeSummary.find( '.ic-route-summary-ascent' ).html( Math.ceil( totalAscent ) );
	routeSummary.find( '.ic-route-summary-ascent' ).next( 'input' ).val( Math.ceil( totalAscent ) );
	routeSummary.find( '.ic-route-summary-descent' ).html( Math.ceil( totalDescent ) );
	routeSummary.find( '.ic-route-summary-descent' ).next( 'input' ).val( Math.ceil( totalDescent ) );
}
