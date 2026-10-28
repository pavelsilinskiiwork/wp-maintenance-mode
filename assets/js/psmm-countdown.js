/**
 * Pavel Silinskii Maintenance Mode — countdown to the expected end.
 * Reloads the page once the timer reaches zero, so visitors land on the live site
 * when auto-disable is on.
 */
( function () {
	'use strict';

	const root = document.querySelector( '.psmm-countdown' );

	if ( ! root ) {
		return;
	}

	const end = parseInt( root.dataset.end, 10 ) * 1000;

	if ( ! end ) {
		return;
	}

	const fields = {};
	root.querySelectorAll( '[data-unit]' ).forEach( function ( el ) {
		fields[ el.dataset.unit ] = el;
	} );

	const pad = function ( n ) {
		return String( n ).padStart( 2, '0' );
	};

	let timer = null;

	const tick = function () {
		const left = Math.max( 0, Math.floor( ( end - Date.now() ) / 1000 ) );

		fields.days.textContent = Math.floor( left / 86400 );
		fields.hours.textContent = pad( Math.floor( ( left % 86400 ) / 3600 ) );
		fields.minutes.textContent = pad( Math.floor( ( left % 3600 ) / 60 ) );
		fields.seconds.textContent = pad( left % 60 );

		if ( 0 === left ) {
			clearInterval( timer );
			setTimeout( function () {
				window.location.reload();
			}, 3000 );
		}
	};

	tick();
	timer = setInterval( tick, 1000 );
}() );
