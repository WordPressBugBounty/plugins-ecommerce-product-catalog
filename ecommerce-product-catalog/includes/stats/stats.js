( function () {
	'use strict';
	document.documentElement.classList.add( 'ic-stats-js' );
	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.ic-stats-chart-toggle' );
		if ( ! button ) {
			return;
		}
		var expanded = 'true' !== button.getAttribute( 'aria-expanded' );
		button.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		button.textContent = expanded ? button.dataset.less : button.dataset.more;
		var table = document.getElementById( button.getAttribute( 'aria-controls' ) );
		if ( table ) {
			table.classList.toggle( 'is-expanded', expanded );
		}
	} );
}() );
