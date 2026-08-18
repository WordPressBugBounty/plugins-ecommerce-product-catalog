/**
 * Shared customer-login behavior without guest or registration flows.
 *
 * @package impleCode\IC
 */

function ic_get_popup_login_menu_container( currentContext ) {
	var context = currentContext ? jQuery( currentContext ) : jQuery();
	var menuContainer = jQuery();

	if ( context.length > 0 ) {
		menuContainer = context.closest( '.ic-customer-menu-login-container' );
	}
	if ( menuContainer.length === 0 ) {
		menuContainer = jQuery( '.ic-customer-menu-login-container:has(.popup_login_form:visible)' ).first();
	}
	if ( menuContainer.length === 0 ) {
		menuContainer = jQuery( '.ic-customer-menu-login-container:has(.popup_login_form)' ).first();
	}

	return menuContainer;
}

function ic_get_popup_login_overlay( currentContext ) {
	var menuContainer = ic_get_popup_login_menu_container( currentContext );

	if ( menuContainer.length > 0 ) {
		return menuContainer.find( '.ic-overlay' ).first();
	}

	return jQuery( '.ic-overlay' ).first();
}

function ic_show_popup_login_overlay( currentContext ) {
	var overlay = ic_get_popup_login_overlay( currentContext );

	if ( overlay.length > 0 ) {
		overlay.show();
	}
}

function ic_hide_popup_login_overlay( currentContext ) {
	var overlay = ic_get_popup_login_overlay( currentContext );

	if ( overlay.length > 0 ) {
		overlay.hide();
	} else {
		jQuery( '.ic-overlay' ).hide();
	}
}

function ic_get_popup_login_form( currentContext ) {
	var context = currentContext ? jQuery( currentContext ) : jQuery();
	var loginForm = jQuery();

	if ( context.length > 0 ) {
		if ( context.hasClass( 'popup_login_form' ) ) {
			loginForm = context;
		}
		if ( loginForm.length === 0 ) {
			loginForm = context.closest( '.popup_login_form' );
		}
		if ( loginForm.length === 0 ) {
			loginForm = context.closest( '.ic-customer-menu-login-container' ).find( '.popup_login_form' ).first();
		}
		if ( loginForm.length === 0 ) {
			loginForm = context.closest( '.ic-login-form-container' ).find( '.popup_login_form' ).first();
		}
	}
	if ( loginForm.length === 0 ) {
		loginForm = jQuery( '.popup_login_form:visible' ).first();
	}
	if ( loginForm.length === 0 || loginForm.is( ':hidden' ) ) {
		loginForm = jQuery( '.popup_login_form' ).first();
	}

	return loginForm;
}

jQuery( document ).ready(
	function ( $ ) {
		var loginFormContainer = $( '.ic-login-form-container' );

		$( '.login_button.not-logged a, .popup_login_form .closer' ).on(
			'click',
			function ( event ) {
				event.preventDefault();
				digital_customer_toggle_popup_login( this );
			}
		);

		if ( $( '.inside_login.ui-tabs' ).length > 0 ) {
			$( '.inside_login.ui-tabs' ).tabs();
		}

		loginFormContainer.on(
			'click',
			'.sign-in',
			function () {
				var currentContainer = $( this ).closest( '.ic-login-form-container' );
				currentContainer.find( '.login_form' ).show();
				currentContainer.find( '.ic-cancel-login' ).remove();
				currentContainer.find( '.login-submit' ).append( ic_user_login.login_cancel_button );
				if ( $( this ).closest( '.popup_login' ).length ) {
					ic_show_popup_login_overlay( currentContainer );
				} else {
					currentContainer.find( '.ic-login-form-options' ).hide();
				}
			}
		);

		loginFormContainer.on(
			'click',
			'.ic-cancel-login',
			function () {
				var currentContainer = $( this ).closest( '.ic-login-form-container' );
				currentContainer.find( '.login_form' ).hide();
				currentContainer.find( '.ic-login-form-options' ).show();
				ic_hide_popup_login_overlay( currentContainer );
				removeLoginAlerts( currentContainer );
			}
		);

		var autoOpenLoginFormContainer = loginFormContainer.filter( '.ic-login-form-container-show-login-form' ).first();
		if ( autoOpenLoginFormContainer.length > 0 && $( '.login_form:visible' ).length === 0 ) {
			autoOpenLoginFormContainer.find( '.sign-in' ).first().trigger( 'click' );
		}

		loginFormContainer.on(
			'submit',
			'#loginform',
			function () {
				removeLoginAlerts( $( this ).closest( '.ic-login-form-container' ) );
			}
		);

		$( '.ic-customer-menu-login-container' ).on(
			'click',
			'.button, a',
			function () {
				var menuContainer = $( this ).closest( '.ic-customer-menu-login-container' );
				menuContainer.closest( '.sub-menu' ).addClass( 'ic-permanent-show' );
				menuContainer.on(
					'click',
					'.ic-cancel-login, .ic-overlay',
					function () {
						menuContainer.closest( '.sub-menu' ).removeClass( 'ic-permanent-show' );
						menuContainer.off( 'click', '.ic-cancel-login, .ic-overlay' );
					}
				);
			}
		);

		manageLoginHash( window.location.hash.substr( 1 ) );
		$( 'body' ).on(
			'click',
			'.ic-trigger-menu-login',
			function ( event ) {
				if ( manageLoginHash( $( this ).attr( 'href' ).substr( 1 ) ) ) {
					event.preventDefault();
				}
			}
		);

		function manageLoginHash( locationHash ) {
			var currentContainer = $( '.entry-content .ic-login-form-container' ).first();
			var menuLoginContainers = $( '.ic-customer-menu-login-container' );
			if ( currentContainer.length === 0 ) {
				currentContainer = loginFormContainer.first();
			}
			if ( locationHash === 'customer_login' ) {
				if ( currentContainer.closest( '.entry-content' ).length > 0 ) {
					menuLoginContainers.find( '.popup_login_form, .ic-overlay' ).hide();
					menuLoginContainers.closest( '.sub-menu' ).removeClass( 'ic-permanent-show' );
				}
				currentContainer.find( '.sign-in' ).first().trigger( 'click' );

				return true;
			}

			return false;
		}

		function removeLoginAlerts( currentContainer ) {
			var alertsContainer = currentContainer && currentContainer.length ? currentContainer : loginFormContainer;
			$( '.login-success-message' ).hide();
			$( '.login-failed-message' ).hide();
			$( '.login-locked-message' ).hide();
			alertsContainer.find( '.warning:visible' ).remove();
			alertsContainer.find( '.success:visible' ).remove();
		}
	}
);

function digital_customer_toggle_popup_login( currentContext ) {
	var loginForm = ic_get_popup_login_form( currentContext );

	loginForm.toggle(
		'slide',
		{ direction: 'up' },
		function () {
			var isOpen = ! jQuery( this ).is( ':hidden' );
			jQuery( this ).trigger( 'ic_popup_login_toggled', [ isOpen ] );
			if ( ! isOpen ) {
				ic_hide_popup_login_overlay( jQuery( this ) );
			} else {
				ic_show_popup_login_overlay( jQuery( this ) );
			}
		}
	);
}

function is_mobile_login_popup() {
	return false;
}
