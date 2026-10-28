/**
 * Pavel Silinskii Maintenance Mode — settings page: color pickers and logo picker.
 */
( function ( $ ) {
	'use strict';

	$( function () {
		$( '.psmm-color' ).wpColorPicker();

		const $field = $( '.psmm-logo-field' );
		const $input = $field.find( 'input[type="url"]' );
		const $preview = $field.find( '.psmm-logo-preview' );
		let frame = null;

		const setLogo = function ( url ) {
			$input.val( url );
			$preview.empty();

			if ( url ) {
				$( '<img>', { src: url, alt: '' } ).appendTo( $preview );
			}
		};

		$field.on( 'click', '.psmm-logo-select', function ( event ) {
			event.preventDefault();

			if ( ! frame ) {
				frame = wp.media( {
					title: psmmAdmin.mediaTitle,
					button: { text: psmmAdmin.mediaButton },
					library: { type: 'image' },
					multiple: false,
				} );

				frame.on( 'select', function () {
					setLogo( frame.state().get( 'selection' ).first().toJSON().url );
				} );
			}

			frame.open();
		} );

		$field.on( 'click', '.psmm-logo-remove', function ( event ) {
			event.preventDefault();
			setLogo( '' );
		} );

		$input.on( 'change', function () {
			setLogo( $input.val().trim() );
		} );
	} );
}( jQuery ) );
