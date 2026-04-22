/**
 * Admin settings page JS — color picker + live preview refresh.
 *
 * @package ScoreBox
 */

( function( $ ) {
	'use strict';

	var refreshTimer;

	function scheduleRefresh() {
		clearTimeout( refreshTimer );
		refreshTimer = setTimeout( refreshPreview, 150 );
	}

	function refreshPreview() {
		var $container = $( '#scorebox-settings-preview' );
		if ( ! $container.length || typeof scoreboxPreview === 'undefined' ) {
			return;
		}

		$container.css( 'opacity', 0.6 );

		$.post( scoreboxPreview.ajaxUrl, {
			action:       'scorebox_render_preview',
			nonce:        scoreboxPreview.nonce,
			style:        $( '#scorebox_default_style' ).val() || 'default',
			accent_color: $( '#scorebox_accent_color' ).val(),
			bg_color:     $( '#scorebox_bg_color' ).val(),
			border_color: $( '#scorebox_border_color' ).val()
		} ).done( function( resp ) {
			if ( resp && resp.success && resp.data && resp.data.html ) {
				$container.html( resp.data.html );
			}
		} ).always( function() {
			$container.css( 'opacity', 1 );
		} );
	}

	$( function() {
		$( '.scorebox-color-picker' ).wpColorPicker( {
			change: scheduleRefresh,
			clear:  scheduleRefresh
		} );

		$( '#scorebox_default_style' ).on( 'change', refreshPreview );
	} );
} )( jQuery );
