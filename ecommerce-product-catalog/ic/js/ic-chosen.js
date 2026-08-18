/**
 * Chosen field integration.
 *
 * @package impleCode\IC
 *
 * (c) 2021 Norbert Dreszer - https://implecode.com
 */

jQuery( document ).ready(
	function ($) {
		/* globals implecode */
		implecode.ic.addAction(
			'settings_row_screen_updated',
			function (container) {
				container.find( '.ic-chosen' ).each(
					function () {
						ic_apply_chosen( $( this ) );
					}
				);
			}
		);
		implecode.ic.addAction(
			'ic_screen_updated',
			function (container) {
				container.find( '.ic-chosen' ).each(
					function () {
						ic_apply_chosen( $( this ) );
					}
				);
			}
		);
		$( '.ic-chosen' ).each(
			function () {
				ic_apply_chosen( $( this ) );
			}
		);

		function ic_apply_chosen(select) {
			let chosen_width = '224px';
			const row        = select.closest( '.row' );
			if (row.length) {
				if (row.hasClass( 'address' )) {
					chosen_width = '100%';
				} else if (row.hasClass( 'size-medium' )) {
					chosen_width = '400px';
				}
			}
			select.chosen( {width: chosen_width} );
			select.removeClass( '.ic-chosen' );
		}
	}
);
