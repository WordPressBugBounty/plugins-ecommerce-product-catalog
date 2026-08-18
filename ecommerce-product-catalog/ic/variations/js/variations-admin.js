/*!
 impleCode Shopping Cart v1.0.0 - 2014-08-27
 Adds appropriate scripts to admin settings
 (c) 2014 Norbert Dreszer - http://implecode.com
 */

function ic_variations_admin_fix_helper( e, ui ) {
	ui.children().each(
		function () {
			jQuery( this ).width( jQuery( this ).width() );
		}
	);

	return ui;
}

function ic_variations_admin_reindex_row( row, correct_var_id ) {
	var current_var_id = parseInt( row.find( 'input.product-variation-label' ).data( 'var_num' ), 10 );
	var lp_column;
	var correct_lp_column;

	if ( isNaN( current_var_id ) || current_var_id === correct_var_id ) {
		return;
	}

	lp_column = row.find( '.lp-column' ).first();
	correct_lp_column = lp_column.text().replace( current_var_id, correct_var_id );
	lp_column.text( correct_lp_column );

	row.find( '*' ).each(
		function () {
			var field = jQuery( this );
			var field_var_num = field.attr( 'data-var_num' );
			var field_name = field.attr( 'name' );
			var field_id = field.attr( 'id' );

			if ( field_var_num !== undefined ) {
				field.attr( 'data-var_num', correct_var_id );
			}
			if ( field_name !== undefined ) {
				field.attr( 'name', field_name.replace( current_var_id, correct_var_id ) );
			}
			if ( field_id !== undefined ) {
				field.attr( 'id', field_id.replace( current_var_id, correct_var_id ) );
			}
		}
	);
}

function ic_variations_admin_renumber_rows() {
	var tbody = jQuery( '#al_cart_variations table.variations > tbody' );

	tbody.children( 'tr' ).each(
		function ( index ) {
			ic_variations_admin_reindex_row( jQuery( this ), index + 1 );
		}
	);

	jQuery( document ).trigger( 'ic_variations_admin_rows_renumbered', [ tbody ] );
}

function ic_variations_admin_add_value_row( row ) {
	var values = row.find( 'td.variation_values input:last-of-type' ).clone();
	var var_id = values.data( 'var_num' );
	var new_var_lp = values.data( 'var_lp' ) + 1;
	var prices = row.find( 'td.variation_prices input:last-of-type' ).clone();
	var shipping = row.find( 'td.variation_shipping input:last-of-type' ).clone();

	values = values.attr( 'data-var_lp', new_var_lp );
	row.find( 'td.variation_values' ).append( values );

	if ( prices.length ) {
		row.find( 'td.variation_prices' ).append( prices );
	}
	if ( shipping.length ) {
		row.find( 'td.variation_shipping' ).append( shipping );
	}

	jQuery( document ).trigger( 'ic_variations_admin_value_added', [ row, var_id, new_var_lp ] );
}

jQuery( document ).ready(
	function () {
		jQuery( '#al_cart_variations table.variations > tbody' ).sortable(
			{
				items: 'tr:not(.ic-not-sortable)',
				stop: function () {
					jQuery( this ).find( '[type=radio]' ).each(
						function () {
							if ( jQuery( this ).is( ':checked' ) ) {
								jQuery( this ).attr( 'data-checked', '1' );
							}
						}
					);

					ic_variations_admin_renumber_rows();

					jQuery( this ).find( '[data-checked=1]' ).each(
						function () {
							jQuery( this ).attr( 'checked', true );
							jQuery( this ).removeAttr( 'data-checked' );
						}
					);
				},
				helper: ic_variations_admin_fix_helper,
				placeholder: 'sort-settings-placeholder',
				start: function ( e, ui ) {
					ui.placeholder.height( ui.item.height() );
				}
			}
		);

		jQuery( '#al_cart_variations' ).on(
			'click',
			'.add_variation:not(.disabled)',
			function () {
				ic_variations_admin_add_value_row( jQuery( this ).closest( 'tr' ) );
			}
		);
	}
);
