/**
 * Classic-editor meta box interactions: pros/cons/criteria repeaters and criteria toggle.
 *
 * @package ScoreBox
 */
( function() {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) { fn(); return; }
		document.addEventListener( 'DOMContentLoaded', fn );
	}

	function buildRow( field ) {
		var row = document.createElement( 'div' );
		row.className = 'scorebox-repeater__row' + ( field === 'criteria' ? ' scorebox-criteria-row' : '' );

		if ( field === 'criteria' ) {
			var labelInput = document.createElement( 'input' );
			labelInput.type = 'text';
			labelInput.name = 'scorebox_review_criteria_labels[]';
			labelInput.placeholder = 'e.g., Features';
			labelInput.className = 'scorebox-criteria-label';
			row.appendChild( labelInput );

			var ratingInput = document.createElement( 'input' );
			ratingInput.type = 'number';
			ratingInput.name = 'scorebox_review_criteria_ratings[]';
			ratingInput.min = '0';
			ratingInput.max = '5';
			ratingInput.step = '0.5';
			ratingInput.value = '0';
			ratingInput.className = 'scorebox-criteria-rating';
			row.appendChild( ratingInput );
		} else {
			var input = document.createElement( 'input' );
			input.type = 'text';
			input.name = 'scorebox_review_' + field + '[]';
			input.className = 'regular-text';
			input.placeholder = field === 'pros' ? 'Add a pro...' : 'Add a con...';
			row.appendChild( input );
		}

		var remove = document.createElement( 'button' );
		remove.type = 'button';
		remove.className = 'button scorebox-repeater__remove';
		remove.setAttribute( 'aria-label', 'Remove' );
		remove.textContent = '\u00d7';
		row.appendChild( remove );

		return row;
	}

	ready( function() {
		var box = document.querySelector( '.scorebox-meta-box' );
		if ( ! box ) { return; }

		box.addEventListener( 'click', function( e ) {
			var target = e.target;

			if ( target.classList.contains( 'scorebox-repeater__add' ) ) {
				e.preventDefault();
				var field = target.getAttribute( 'data-target' );
				var container = box.querySelector( '.scorebox-repeater[data-field="' + field + '"]' );
				if ( container ) {
					var row = buildRow( field );
					container.appendChild( row );
					var firstInput = row.querySelector( 'input' );
					if ( firstInput ) { firstInput.focus(); }
				}
			}

			if ( target.classList.contains( 'scorebox-repeater__remove' ) ) {
				e.preventDefault();
				var row = target.closest( '.scorebox-repeater__row' );
				if ( row && row.parentElement ) {
					var siblings = row.parentElement.querySelectorAll( '.scorebox-repeater__row' );
					if ( siblings.length > 1 ) {
						row.parentElement.removeChild( row );
					} else {
						// Keep at least one blank row — just clear it.
						row.querySelectorAll( 'input' ).forEach( function( inp ) {
							inp.value = inp.type === 'number' ? '0' : '';
						} );
					}
				}
			}
		} );

		// Criteria toggle: show/hide the criteria repeater wrapper.
		var useCriteria = box.querySelector( '#scorebox_review_use_criteria' );
		if ( useCriteria ) {
			useCriteria.addEventListener( 'change', function() {
				var wrap = box.querySelector( '.scorebox-criteria-wrap' );
				if ( wrap ) {
					wrap.style.display = useCriteria.checked ? '' : 'none';
				}
			} );
		}
	} );
} )();
