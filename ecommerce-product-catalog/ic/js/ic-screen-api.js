/**
 * Shared AJAX screen helpers.
 *
 * @package ImpleCode
 */

if ( typeof implecode === 'undefined' ) {
	var implecode = [];
}

jQuery( document ).ready(
	function ( $ ) {
		/* globals ic_screen_api */

		function get_ajax_container( element ) {
			var ajaxContainer = $( element ).closest( '.ic-ajax-container' );

			if ( 0 === ajaxContainer.length ) {
				return false;
			}

			return ajaxContainer;
		}

		function get_screen_name( ajaxContainer ) {
			var screenName = ajaxContainer.data( 'ic_screen_name' );

			if ( ! screenName ) {
				return false;
			}

			return screenName;
		}

		$( '.ic-ajax-container' ).on(
			'click',
			'.ic-ajax-link',
			function ( e ) {
				var ajaxContainer = get_ajax_container( this );
				var screenName    = false;
				var params        = null;
				var urlParams     = null;
				var apiParams     = null;

				e.preventDefault();
				if ( false === ajaxContainer ) {
					return false;
				}

				screenName = get_screen_name( ajaxContainer );
				if ( false === screenName ) {
					return false;
				}

				params    = new URL( $( this ).attr( 'href' ) ).searchParams;
				urlParams = new URLSearchParams( params );
				if ( ! urlParams.has( 'ic_action' ) ) {
					return false;
				}

					apiParams             = Object.fromEntries( urlParams.entries() );
					apiParams.screen_name = screenName;

				wp.apiFetch(
					{
						path: 'implecode/v1/screen-update/',
						data: apiParams,
						method: 'POST',
						nonce: ic_screen_api.nonce,
					}
				).then(
					function ( response ) {
						ajaxContainer.html( $( response[ screenName ] ).html() );
						implecode.ic.doAction( 'ic_screen_updated', ajaxContainer );
					}
				);

				return false;
			}
		);

		$( '.ic-ajax-container' ).on(
			'click',
			'.ic-ajax-button',
			function ( e ) {
				var ajaxContainer = get_ajax_container( this );
				var screenName    = false;
				var apiParams     = null;

				e.preventDefault();
				if ( false === ajaxContainer ) {
					return false;
				}

				screenName = get_screen_name( ajaxContainer );
				if ( false === screenName ) {
					return false;
				}

				apiParams             = $( this ).data();
				apiParams.screen_name = screenName;

				wp.apiFetch(
					{
						path: 'implecode/v1/screen-update/',
						data: apiParams,
						method: 'POST',
						nonce: ic_screen_api.nonce,
					}
				).then(
					function ( response ) {
						if ( 'boolean' === typeof response[ screenName ] ) {
							location.reload();
							return;
						}

						ajaxContainer.html( $( response[ screenName ] ) );
						implecode.ic.doAction( 'ic_screen_updated', ajaxContainer );
					}
				);

				return false;
			}
		);

		$( '.ic-ajax-container' ).on(
			'click',
			'.ic-container-submit',
			function ( e ) {
				var ajaxContainer   = get_ajax_container( this );
				var screenName      = false;
				var apiParams       = null;
				var containerFields = null;
				var isValid         = true;
				var formValues      = null;

				e.preventDefault();
				if ( false === ajaxContainer ) {
					return false;
				}

				screenName = get_screen_name( ajaxContainer );
				if ( false === screenName ) {
					return false;
				}

				apiParams       = $( this ).data();
				containerFields = ajaxContainer.find( 'input, select, textarea' );

				// Trigger browser validation before AJAX submit.
				containerFields.each(
					function () {
						if ( ! this.checkValidity() ) {
							isValid = false;
							this.reportValidity();
							return false;
						}
					}
				);

				if ( ! isValid ) {
					return false;
				}

				formValues = containerFields.serializeArray();
				formValues.forEach(
					function ( item ) {
						if ( item.name.includes( '[]' ) ) {
							item.name = item.name.replace( '[]', '' );
							if ( ! apiParams[ item.name ] ) {
								apiParams[ item.name ] = [];
							}
							apiParams[ item.name ].push( item.value );
						} else {
							apiParams[ item.name ] = item.value;
						}
					}
				);
				apiParams.screen_name = screenName;

				wp.apiFetch(
					{
						path: 'implecode/v1/screen-update/',
						data: apiParams,
						method: 'POST',
						nonce: ic_screen_api.nonce,
					}
				).then(
					function ( response ) {
						ajaxContainer.html( $( response[ screenName ] ) );
						implecode.ic.doAction( 'ic_screen_updated', ajaxContainer );
					}
				);

				return false;
			}
		);

		$( '.ic-ajax-container' ).on(
			'change',
			'form .ic-ajax-update-form',
			function ( e ) {
				var ajaxContainer = get_ajax_container( this );
				var form          = null;
				var screenName    = '';
				var userId        = null;

				e.preventDefault();
				if ( false === ajaxContainer ) {
					return false;
				}

				form       = ajaxContainer.find( 'form' );
				screenName = form.find( 'input.ic-screen-name' ).val();
				if ( ! screenName ) {
					return false;
				}

				userId = $( this ).data( 'user_id' );

				wp.apiFetch(
					{
						path: 'implecode/v1/screen-update/',
						data: {
							user_id: userId,
							form_selected: form.serialize(),
							screen_name: screenName,
						},
						method: 'POST',
						nonce: ic_screen_api.nonce,
					}
				).then(
					function ( response ) {
						form.replaceWith( response[ screenName ] );
						implecode.ic.doAction( 'ic_screen_updated', form );
					}
				);

				return false;
			}
		);

		$( 'form.ic-ajax-container' ).on(
			'submit',
			function ( e ) {
				e.preventDefault();
				if ( ! e.target.checkValidity() ) {
					e.target.reportValidity();
					return false;
				}

				return ic_screen_api_submit_form( $( this ) );
			}
		);

		$( '.ic-ajax-container' ).on(
			'submit',
			'form.ic-ajax-container',
			function ( e ) {
				e.preventDefault();
				if ( ! e.target.checkValidity() ) {
					e.target.reportValidity();
					return false;
				}

				return ic_screen_api_submit_form( $( this ) );
			}
		);

		$( 'form.ic-ajax-container.ic-ajax-immediate-container' ).on(
			'change',
			'input',
			function ( e ) {
				var changedInput       = $( this );
				var immediateContainer = changedInput.closest( 'form.ic-ajax-immediate-container' );
				var prevTimer          = null;
				var timer              = null;
				var requiredContainer  = null;

				if ( immediateContainer.data( 'icUpdating' ) ) {
					return false;
				}

				if ( 0 === immediateContainer.find( '.ic-form-screen-update-submit' ).length ) {
					e.preventDefault();
					prevTimer = immediateContainer.data( 'icImmediateSubmitTimer' );
					if ( prevTimer ) {
						clearTimeout( prevTimer );
					}
					timer = setTimeout(
						function () {
							immediateContainer.data( 'icImmediateSubmitTimer', false );
							immediateContainer.trigger( 'submit' );
						},
						0
					);
					immediateContainer.data( 'icImmediateSubmitTimer', timer );
				} else if ( immediateContainer.find( '.ic-form-screen-update-submit' ).length === immediateContainer.find( '.ic-button-required-container .ic-form-screen-update-submit' ).length ) {
					requiredContainer = changedInput.closest( '.ic-button-required-container' );
					if ( 0 === requiredContainer.length ) {
						e.preventDefault();
						immediateContainer.find( '.ic-button-required-container input' ).each(
							function () {
								$( this ).attr( 'required', false );
								if ( changedInput.hasClass( 'ic-search-field' ) ) {
									$( this ).val( '' );
								}
							}
						);

						prevTimer = immediateContainer.data( 'icImmediateSubmitTimer' );
						if ( prevTimer ) {
							clearTimeout( prevTimer );
						}
						timer = setTimeout(
							function () {
								immediateContainer.trigger( 'submit' );
							},
							0
						);
						immediateContainer.data( 'icImmediateSubmitTimer', timer );
					}
				}

				return false;
			}
		);

		$( '.ic-ajax-container' ).on(
			'click',
			'.ic-button-required-container .ic-form-screen-update-submit',
			function () {
				var requiredContainer = $( this ).closest( '.ic-button-required-container' );

				requiredContainer.find( 'input' ).each(
					function () {
						$( this ).attr( 'required', true );
					}
				);
			}
		);

		$( '.ic-ajax-container' ).on(
			'click',
			'.ic-form-screen-update-submit',
			function ( e ) {
				e.preventDefault();
				$( this ).closest( 'form.ic-ajax-container' ).trigger( 'submit' );
			}
		);

		implecode.ic.addAction(
			'ic_modal_closed',
			function ( object ) {
				if ( object.closest( '.ic-ajax-container' ).length > 0 && 0 === jQuery( '.ic-modal-close:visible' ).length ) {
					location.replace( location.href );
					return false;
				}
			}
		);

		function ic_screen_api_submit_form( form ) {
			var screenName = '';
			var toDisable  = form;
			var input      = null;

			if ( form.data( 'icUpdating' ) ) {
				return false;
			}

			if ( form.find( 'input[type="file"]' ).length > 0 ) {
				input = $( '<input>' )
					.attr( 'type', 'hidden' )
					.attr( 'name', 'form_data' )
					.val( form.serialize() );
				form.append( input );
				form.parents( '.ic-ajax-container' ).off( 'submit' );
				form.off( 'submit' ).trigger( 'submit' );
				return true;
			}

			screenName = form.data( 'ic_screen_name' );
			if ( ! screenName ) {
				return false;
			}

			if ( form.find( '.ic-modal-container-inside' ).length > 0 ) {
				toDisable = form.find( '.ic-modal-container-inside > *' );
			}

			implecode.disable_container( toDisable );
			wp.apiFetch(
				{
					path: 'implecode/v1/screen-update/',
					data: {
						form_data: form.serialize(),
						screen_name: screenName,
					},
					method: 'POST',
					nonce: ic_screen_api.nonce,
				}
			).then(
				function ( response ) {
					form.data( 'icUpdating', true );
					form.html( response[ screenName ] );
					implecode.ic.doAction( 'ic_screen_updated', form );
					implecode.enable_container( toDisable );

					// Release update suppression after the current event loop.
					setTimeout(
						function () {
							form.data( 'icUpdating', false );
						},
						0
					);
				}
			);

			return true;
		}
	}
);
