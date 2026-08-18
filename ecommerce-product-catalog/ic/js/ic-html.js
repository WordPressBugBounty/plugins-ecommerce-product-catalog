/**
 * Shared HTML interaction helpers.
 *
 * @package ImpleCode
 */

if ( typeof implecode === 'undefined' ) {
	var implecode            = [];
	implecode.is_user_active = true;
}

jQuery( document ).ready(
	function ( $ ) {
		var activityTimeout;

		function resetActivity() {
			implecode.is_user_active = true;
			if ( activityTimeout ) {
				clearTimeout( activityTimeout );
			}
			activityTimeout = setTimeout(
				function () {
					implecode.is_user_active = false;
				},
				120000
			); // 2 minutes.
		}

		$( document ).on( 'mousemove keydown click', resetActivity );
		document.addEventListener(
			'visibilitychange',
			function () {
				if ( document.hidden ) {
					implecode.is_user_active = false;
				} else {
					resetActivity();
				}
			}
		);
		resetActivity();

		$( '.ic-ajax-container' ).on(
			'click',
			'.ic-overlay-container',
			function ( e ) {
				if ( e.target !== this && ! $( e.target ).hasClass( 'ic-modal-close' ) && ! $( e.target ).hasClass( 'ic-popup-cancel' ) ) {
					return;
				}
				$( this ).hide();
				implecode.ic.doAction( 'ic_modal_closed', $( this ) );
			}
		);

		$( '.ic-ajax-container' ).on(
			'input',
			'.ic-search-field-immediate',
			function () {
				var input        = $( this );
				var currentValue = input.val();
				var prevTimeout  = input.data( 'icChangeTimeout' );
				var handle       = null;

				// Clear any previously scheduled change for this input.
				if ( prevTimeout ) {
					clearTimeout( prevTimeout );
				}

				handle = setTimeout(
					function () {
						if ( currentValue === input.val() ) {
							input.trigger( 'change' );
							input.data( 'icChangeTimeout', false );
						}
					},
					500
				);

				input.data( 'icChangeTimeout', handle );
			}
		);

		implecode.ic.addAction(
			'ic_screen_updated',
			function ( container ) {
				var button      = container.find( '.ic-click-onload' );
				var searchInput = container.find( '.ic-search-field-immediate' ).first();
				var value       = '';

				if ( button.length ) {
					button.trigger( 'click' );
				}
				if ( searchInput.length ) {
					value = searchInput.val();
					if ( value ) {
						searchInput.focus();
						searchInput[0].setSelectionRange( value.length, value.length );
					}
				}
			}
		);
	}
);

if ( typeof implecode.disable_body === 'undefined' ) {
	implecode.disable_body = function () {
		jQuery( 'body' ).addClass( 'ic-disabled-body' );
	};
}
if ( typeof implecode.enable_body === 'undefined' ) {
	implecode.enable_body = function () {
		jQuery( 'body' ).removeClass( 'ic-disabled-body' );
	};
}

if ( typeof implecode.disable_container === 'undefined' ) {
	implecode.disable_container = function ( container ) {
		container.addClass( 'ic-disabled-container' );
	};
}

if ( typeof implecode.enable_container === 'undefined' ) {
	implecode.enable_container = function ( container ) {
		container.removeClass( 'ic-disabled-container' );
	};
}
