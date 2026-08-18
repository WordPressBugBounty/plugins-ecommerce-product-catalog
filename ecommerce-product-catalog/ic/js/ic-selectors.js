/**
 * Selector behavior helpers.
 *
 * @package impleCode\IC
 *
 * (c) 2025 Norbert Dreszer - https://implecode.com
 */

jQuery( document ).ready(
	function ($) {
		/* globals implecode */
		function selectorJsonName(selector) {
			let name = selector.attr( 'data-ic-selector-json-name' );
			if ( ! name) {
				name = '';
			}

			return name;
		}
		function selectorScope(selector) {
			let form = selector.closest( 'form' );
			if (form.length > 0) {
				return form;
			}

			return $( document.body );
		}
		function selectorGroup(selector) {
			let name = selectorJsonName( selector );
			if ( ! name) {
				return selector;
			}

			return selectorScope( selector ).find( '[data-ic-selector-json-name]' ).filter(
				function () {
					return $( this ).attr( 'data-ic-selector-json-name' ) === name;
				}
			);
		}
		function selectorDefaultState(selector) {
			if (selector.hasClass( 'ic-selector-qty' )) {
				return {};
			}
			if (selector.attr( 'data-ic-select-multiple' ) === '1') {
				return [];
			}

			return '';
		}
		function selectorUniqueValues(values) {
			let unique = [];
			$.each(
				values,
				function (index, value) {
					value = String( value );
					if (unique.indexOf( value ) === -1) {
						unique.push( value );
					}
				}
			);

			return unique;
		}
		function selectorNormalizeState(selector, state) {
			if (selector.hasClass( 'ic-selector-qty' )) {
				if (typeof state !== 'object' || state === null || Array.isArray( state )) {
					return {};
				}

				return state;
			}
			if (selector.attr( 'data-ic-select-multiple' ) === '1') {
				if ( ! Array.isArray( state )) {
					return [];
				}

				return selectorUniqueValues( state );
			}
			if (state === null || state === undefined || state === '') {
				return '';
			}
			if (Array.isArray( state )) {
				if (state.length === 0) {
					return '';
				}

				return String( state[0] );
			}

			return String( state );
		}
		function selectorJsonInputs(selector) {
			return selectorGroup( selector ).find( 'input.ic-selector-json' );
		}
		function selectorCanonicalJsonInput(selector) {
			let inputs = selectorJsonInputs( selector );
			let canonical;
			let inputName = selectorJsonName( selector );
			if (inputs.length === 0) {
				return $();
			}
			canonical = inputs.filter( '[data-ic-selector-json-canonical="1"]' ).first();
			if (canonical.length === 0) {
				canonical = inputs.first();
			}
			inputs.removeAttr( 'data-ic-selector-json-canonical' );
			canonical.attr( 'data-ic-selector-json-canonical', '1' );
			canonical.prop( 'disabled', false );
			if (inputName) {
				canonical.attr( 'name', inputName );
			}
			inputs.not( canonical ).prop( 'disabled', true ).removeAttr( 'name' );

			return canonical;
		}
		function selectorReadInputState(input, selector) {
			let defaultState = selectorDefaultState( selector );
			if (input.length === 0 || ! input.val()) {
				return defaultState;
			}
			try {
				return selectorNormalizeState( selector, JSON.parse( input.val() ) );
			} catch (err) {
				return defaultState;
			}
		}
		function selectorMergeState(selector, state, incoming) {
			state    = selectorNormalizeState( selector, state );
			incoming = selectorNormalizeState( selector, incoming );
			if (selector.hasClass( 'ic-selector-qty' )) {
				$.each(
					incoming,
					function (main, values) {
						if (typeof values !== 'object' || values === null || Array.isArray( values )) {
							return;
						}
						if ( ! state[main] || typeof state[main] !== 'object' || Array.isArray( state[main] )) {
							state[main] = {};
						}
						$.each(
							values,
							function (sub, qty) {
								if (qty === undefined || qty === null || qty === '' || parseFloat( qty ) <= 0) {
									return;
								}
								state[main][sub] = qty;
							}
						);
						if (Object.keys( state[main] ).length === 0) {
							delete state[main];
						}
					}
				);

				return state;
			}
			if (selector.attr( 'data-ic-select-multiple' ) === '1') {
				return selectorUniqueValues( state.concat( incoming ) );
			}
			if (incoming !== '') {
				return incoming;
			}

			return state;
		}
		function selectorPrepareSharedState(selector) {
			let state  = selectorDefaultState( selector );
			let inputs = selectorJsonInputs( selector );
			if (inputs.length === 0) {
				return;
			}
			selectorCanonicalJsonInput( selector );
			inputs.each(
				function () {
					state = selectorMergeState( selector, state, selectorReadInputState( $( this ), selector ) );
				}
			);
			selectorWriteState( selector, state );
		}
		function selectorMode(selector) {
			let mode = selector.attr( 'data-ic-pagination-mode' );
			if ( ! mode) {
				mode = 'hidden';
			}

			return mode;
		}
		function selectorJsonInput(selector) {
			return selectorCanonicalJsonInput( selector );
		}
		function selectorReadState(selector) {
			let input = selectorJsonInput( selector );
			return selectorReadInputState( input, selector );
		}
		function selectorWriteState(selector, state) {
			let input = selectorJsonInput( selector );
			if (input.length === 0) {
				return;
			}
			input.val( JSON.stringify( selectorNormalizeState( selector, state ) ) );
		}
		function parseQtyName(inputName) {
			let match = /^([^\[]+)\[([^\]]+)\]\[([^\]]+)\]$/.exec( inputName );
			if ( ! match) {
				return null;
			}

			return {
				main: match[2],
				sub: match[3]
			};
		}
		function selectorApplyState(selector) {
			let state = selectorReadState( selector );
			if (selector.hasClass( 'ic-selector-qty' )) {
				selector.find( 'input[type="number"]' ).each(
					function () {
						let input      = $( this );
						let parsedName = parseQtyName( input.attr( 'name' ) );
						let qty        = 0;
						if (parsedName && state[parsedName.main] && state[parsedName.main][parsedName.sub] !== undefined) {
							qty = state[parsedName.main][parsedName.sub];
						}
						input.val( qty );
					}
				);
				return;
			}
			let selectedValues = [];
			if (Array.isArray( state )) {
				selectedValues = state.map( String );
			} else if (state !== '' && state !== null && state !== undefined) {
				selectedValues = [String( state )];
			}
			selector.find( '> .ic-div' ).removeClass( 'ic-selected' );
			selector.find( 'input.ic-radio, input.ic-checkbox' ).prop( 'checked', false );
			selector.find( 'input.ic-radio, input.ic-checkbox' ).each(
				function () {
					let input = $( this );
					if (selectedValues.indexOf( String( input.val() ) ) !== -1) {
						input.prop( 'checked', true );
						input.closest( '.ic-div' ).addClass( 'ic-selected' );
					}
				}
			);
		}
		function selectorSyncState(selector) {
			if (selector.length === 0 || selectorJsonInput( selector ).length === 0) {
				return;
			}
			let mode      = selectorMode( selector );
			let selectors = selectorGroup( selector );
			if (selector.hasClass( 'ic-selector-qty' )) {
				let state = selectorDefaultState( selector );
				if (mode === 'ajax') {
					state = selectorReadState( selector );
				}
				selectors.each(
					function () {
						$( this ).find( 'input[type="number"]' ).each(
							function () {
								let input      = $( this );
								let parsedName = parseQtyName( input.attr( 'name' ) );
								if ( ! parsedName) {
									return;
								}
								if ( ! state[parsedName.main] || typeof state[parsedName.main] !== 'object') {
									state[parsedName.main] = {};
								}
								let qty = input.val();
								if ( ! qty || parseFloat( qty ) <= 0) {
									if (state[parsedName.main]) {
										delete state[parsedName.main][parsedName.sub];
										if (Object.keys( state[parsedName.main] ).length === 0) {
											delete state[parsedName.main];
										}
									}
									return;
								}
								state[parsedName.main][parsedName.sub] = qty;
							}
						);
					}
				);
				selectorWriteState( selector, state );
				return;
			}
			let radioInputs    = selectors.find( 'input.ic-radio' );
			let checkboxInputs = selectors.find( 'input.ic-checkbox' );
			if (radioInputs.length > 0 && checkboxInputs.length === 0) {
				let checked = selectors.find( 'input.ic-radio:checked' ).first();
				if (checked.length > 0) {
					selectorWriteState( selector, checked.val() );
				} else {
					selectorWriteState( selector, '' );
				}
				return;
			}
			if (mode === 'ajax') {
				let state = selectorReadState( selector );
				selectors.each(
					function () {
						let currentSelector   = $( this );
						let currentPageValues = [];
						currentSelector.find( 'input.ic-checkbox' ).each(
							function () {
								currentPageValues.push( String( $( this ).val() ) );
							}
						);
						state = state.filter(
							function (value) {
								return currentPageValues.indexOf( String( value ) ) === -1;
							}
						);
						currentSelector.find( 'input.ic-checkbox:checked' ).each(
							function () {
								state.push( String( $( this ).val() ) );
							}
						);
					}
				);
				selectorWriteState( selector, selectorUniqueValues( state ) );
				return;
			}
			let values = [];
			selectors.find( 'input.ic-checkbox:checked' ).each(
				function () {
					values.push( String( $( this ).val() ) );
				}
			);
			selectorWriteState( selector, selectorUniqueValues( values ) );
		}
		function selectorSetPaginationButtons(selector, targetPage, totalPages) {
			selector.attr( 'data-ic-page-current', targetPage );
			selector.find( '.ic-selector-page-number' ).removeClass( 'ic-current' );
			selector.find( '.ic-selector-page-number[data-ic-page="' + targetPage + '"]' ).addClass( 'ic-current' );
			selector.find( '.ic-selector-page-prev' ).prop( 'disabled', targetPage === 1 );
			selector.find( '.ic-selector-page-next' ).prop( 'disabled', targetPage === totalPages );
		}
		function selectorAjaxUrl() {
			if (typeof ic_selector_vars !== 'undefined' && ic_selector_vars.ajax_url) {
				return ic_selector_vars.ajax_url;
			}
			if (typeof ajaxurl !== 'undefined') {
				return ajaxurl;
			}

			return '';
		}
		$( 'form' ).on(
			{
				mouseenter: function () {
					$( this ).find( '.ic-selector-name' ).show();
				},
				mouseleave: function () {
					$( this ).find( '.ic-selector-name' ).not( '.ic-selected .ic-selector-name' ).hide();
				}
			},
			'.ic-selector-grid > .ic-div'
		);
		$( 'form' ).on(
			'click',
			'.ic-selectable > .ic-div',
			function (e) {
				let button = $( this ).find( '.ic-radio' );
				if (button.closest( '.ic-selectable.ic-disabled-container' ).length > 0) {
					return;
				}
				if (button.length === 0) {
					button = $( this ).find( '.ic-checkbox' );
				}
				if (button.length === 0) {
					return;
				}
				if (button.is( ':checked' )) {
					button.prop( 'checked', false ).trigger( 'change' );
					$( this ).removeClass( 'ic-selected' );
					button.closest( '.ic-selected-first' ).removeClass( 'ic-selected-first' );
					button.closest( '.ic-selected-later' ).removeClass( 'ic-selected-later' );
					implecode.ic.doAction( 'ic_selector_unselected', button );
				} else {
					let container    = button.closest( '.ic-selectable' );
					let prev_checked = container.find( 'input:checked' );
					if (button.is( ':radio' )) {
						container.find( '> .ic-div' ).removeClass( 'ic-selected' );
						container.find( '.ic-radio' ).prop( 'checked', false );
					}
					button.prop( 'checked', true ).trigger( 'change' );
					$( this ).addClass( 'ic-selected' );
					if (button.is( ':radio' ) && button.closest( '.ic-name-visible' ).length === 0) {
						container.find( '.ic-selector-name' ).not( '.ic-selected .ic-selector-name' ).hide();
					}
					if ($( '.ic-selected-first' ).length === 0) {
						button.closest( '.ic-selectable' ).addClass( 'ic-selected-first', 3000 );
					} else {
						button.closest( '.ic-selectable' ).addClass( 'ic-selected-later' );
					}
					implecode.ic.doAction( 'ic_selector_selected', [button, prev_checked] );
				}
			}
		);
		$( 'form' ).on(
			'change',
			'[data-ic-selector-json-name] input.ic-radio, [data-ic-selector-json-name] input.ic-checkbox, [data-ic-selector-json-name] input[type="number"]',
			function () {
				let selector = $( this ).closest( '[data-ic-selector-json-name]' );
				selectorSyncState( selector );
			}
		);
		$( 'form' ).on(
			'click',
			'.ic-selector-pagination .ic-button',
			function (e) {
				e.preventDefault();
				let button   = $( this );
				let selector = button.closest( '.ic-selector-paginated' );
				if (selector.length === 0) {
					return;
				}
				let currentPage = parseInt( selector.attr( 'data-ic-page-current' ), 10 );
				if ( ! currentPage) {
					currentPage = 1;
				}
				let totalPages = parseInt( selector.attr( 'data-ic-pages' ), 10 );
				if ( ! totalPages || totalPages < 2) {
					return;
				}
				let targetPage = button.attr( 'data-ic-page' );
				if (targetPage === 'prev') {
					targetPage = currentPage - 1;
				} else if (targetPage === 'next') {
					targetPage = currentPage + 1;
				} else {
					targetPage = parseInt( targetPage, 10 );
				}
				if ( ! targetPage || targetPage < 1 || targetPage > totalPages) {
					return;
				}
				if (selectorMode( selector ) !== 'ajax') {
					selectorSetPaginationButtons( selector, targetPage, totalPages );
					selector.find( '> .ic-selector-item[data-ic-page]' ).addClass( 'ic-selector-page-hidden' );
					selector.find( '> .ic-selector-item[data-ic-page="' + targetPage + '"]' ).removeClass( 'ic-selector-page-hidden' );
					return;
				}
				if (selector.hasClass( 'ic-selector-pagination-loading' )) {
					return;
				}
				let ajaxUrl = selectorAjaxUrl();
				if ( ! ajaxUrl) {
					return;
				}
				let action  = selector.attr( 'data-ic-ajax-action' );
				let nonce   = selector.attr( 'data-ic-ajax-nonce' );
				let context = selector.attr( 'data-ic-ajax-context' );
				if ( ! action || ! nonce || ! context) {
					return;
				}
				selector.addClass( 'ic-selector-pagination-loading' );
				$.post(
					ajaxUrl,
					{
						action: action,
						page: targetPage,
						nonce: nonce,
						context: context
					},
					function (response) {
						if ( ! response || ! response.success || ! response.data || response.data.html === undefined) {
							selector.removeClass( 'ic-selector-pagination-loading' );
							return;
						}
						selector.find( '> .ic-selector-item' ).remove();
						selector.find( '> .ic-selector-pagination' ).before( response.data.html );
						selectorSetPaginationButtons( selector, targetPage, totalPages );
						selectorApplyState( selector );
						selector.removeClass( 'ic-selector-pagination-loading' );
					},
					'json'
				).fail(
					function () {
						selector.removeClass( 'ic-selector-pagination-loading' );
					}
				);
			}
		);
		$( '[data-ic-selector-json-name]' ).each(
			function () {
				selectorPrepareSharedState( $( this ) );
			}
		);
		$( '[data-ic-selector-json-name]' ).each(
			function () {
				let selector = $( this );
				if (selectorMode( selector ) === 'ajax') {
					selectorApplyState( selector );
				} else {
					selectorSyncState( selector );
				}
			}
		);
	}
);
