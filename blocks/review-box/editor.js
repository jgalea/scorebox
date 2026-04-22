( function() {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useEntityProp = wp.coreData.useEntityProp;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var TextareaControl = wp.components.TextareaControl;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var Button = wp.components.Button;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useCallback = wp.element.useCallback;

	var STAR_PATH = 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z';

	var ToggleControl = wp.components.ToggleControl;

	var CONFIG = window.scoreboxEditorConfig || {
		ratingTypes: [ { value: 'star', label: 'Star (0-5)', max: 5, step: 0.5 } ],
		styles: [ { value: 'default', label: 'Default (Classic)' } ],
		features: { criteria: false }
	};

	var DEFAULTS = window.scoreboxDefaults || {
		rating: 0, rating_type: 'star', style: '', position: '', heading: '', summary: '', pros: [], cons: [],
		schema_type: 'Product', product_name: '', price: '', currency: 'USD',
		cta_text: '', cta_url: '', author_name: '',
		use_criteria: false, criteria: [], type_fields: {}
	};

	var starIdCounter = 0;

	// --- Star component ---
	function Star( props ) {
		var index = props.index;
		var rating = props.rating;
		var onRate = props.onRate;
		var filled = rating >= index + 1;
		var halfFilled = ! filled && rating >= index + 0.5;
		var idRef = useState( function() { return 'sb-star-grad-' + ( ++starIdCounter ); } );
		var gradientId = idRef[ 0 ];

		function handleClick( e ) {
			var rect = e.currentTarget.getBoundingClientRect();
			var x = e.clientX - rect.left;
			onRate( x < rect.width / 2 ? index + 0.5 : index + 1 );
		}

		var children = [];
		if ( halfFilled ) {
			children.push(
				el( 'defs', { key: 'defs' },
					el( 'linearGradient', { id: gradientId },
						el( 'stop', { offset: '50%', stopColor: 'var(--sb-star-color, #1e73be)' } ),
						el( 'stop', { offset: '50%', stopColor: '#d0d0d0' } )
					)
				)
			);
		}
		children.push(
			el( 'path', {
				key: 'path',
				d: STAR_PATH,
				fill: filled ? 'var(--sb-star-color, #1e73be)' : halfFilled ? 'url(#' + gradientId + ')' : '#d0d0d0'
			} )
		);

		return el( 'svg', {
			className: 'scorebox-star-editor',
			width: 28, height: 28, viewBox: '0 0 24 24',
			onClick: handleClick,
			style: { cursor: 'pointer', display: 'inline-block' },
			role: 'button', tabIndex: 0,
			'aria-label': ( index + 1 ) + ' star',
			onKeyDown: function( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); onRate( index + 1 ); }
			}
		}, children );
	}

	// --- StarRating component ---
	function StarRating( props ) {
		var stars = [];
		for ( var i = 0; i < 5; i++ ) {
			stars.push( el( Star, { key: i, index: i, rating: props.value, onRate: props.onChange } ) );
		}
		stars.push( el( 'span', { key: 'val', className: 'scorebox-star-rating__value' }, props.value + '/5' ) );
		return el( 'div', { className: 'scorebox-star-rating', role: 'group', 'aria-label': 'Rating' }, stars );
	}

	// --- ListEditor component ---
	function ListEditor( props ) {
		var items = props.items || [];
		var onChange = props.onChange;
		var placeholder = props.placeholder;

		function updateItem( index, value ) {
			var updated = items.slice();
			updated[ index ] = value;
			onChange( updated );
		}
		function removeItem( index ) {
			onChange( items.filter( function( _, i ) { return i !== index; } ) );
		}
		function addItem() {
			onChange( items.concat( [ '' ] ) );
		}

		var children = items.map( function( item, index ) {
			return el( 'div', { key: index, className: 'scorebox-list-editor__item' },
				el( TextControl, {
					value: item,
					onChange: function( val ) { updateItem( index, val ); },
					placeholder: placeholder
				} ),
				el( Button, {
					icon: 'no-alt',
					label: __( 'Remove', 'scorebox' ),
					onClick: function() { removeItem( index ); },
					isDestructive: true,
					size: 'small'
				} )
			);
		} );

		children.push(
			el( Button, {
				key: 'add',
				variant: 'secondary',
				onClick: addItem,
				size: 'small',
				className: 'scorebox-list-editor__add'
			}, __( '+ Add', 'scorebox' ) )
		);

		return el( 'div', { className: 'scorebox-list-editor' }, children );
	}

	// --- Criteria helpers ---
	function calcCriteriaAverage( criteria ) {
		if ( ! criteria || ! criteria.length ) return 0;
		var sum = 0;
		for ( var i = 0; i < criteria.length; i++ ) {
			sum += ( criteria[ i ].rating || 0 );
		}
		var avg = sum / criteria.length;
		return Math.round( avg * 2 ) / 2; // Snap to half-star.
	}

	// --- CriteriaEditor component ---
	function CriteriaEditor( props ) {
		var criteria = props.criteria || [];
		var onChange = props.onChange;

		function updateCriterion( index, changes ) {
			var updated = criteria.map( function( c, i ) {
				return i === index ? Object.assign( {}, c, changes ) : c;
			} );
			onChange( updated );
		}
		function removeCriterion( index ) {
			onChange( criteria.filter( function( _, i ) { return i !== index; } ) );
		}
		function addCriterion() {
			onChange( criteria.concat( [ { label: '', rating: 0 } ] ) );
		}

		var items = criteria.map( function( criterion, index ) {
			return el( 'div', { key: index, className: 'scorebox-criteria-item' },
				el( 'div', { className: 'scorebox-criteria-item__label' },
					el( TextControl, {
						value: criterion.label,
						onChange: function( val ) { updateCriterion( index, { label: val } ); },
						placeholder: __( 'e.g., Features', 'scorebox' )
					} )
				),
				el( 'div', { className: 'scorebox-criteria-item__stars' },
					el( StarRating, {
						value: criterion.rating,
						onChange: function( val ) { updateCriterion( index, { rating: val } ); }
					} )
				),
				el( 'div', { className: 'scorebox-criteria-item__remove' },
					el( Button, {
						icon: 'no-alt',
						label: __( 'Remove', 'scorebox' ),
						onClick: function() { removeCriterion( index ); },
						isDestructive: true,
						size: 'small'
					} )
				)
			);
		} );

		var avg = calcCriteriaAverage( criteria );

		var actions = el( 'div', { className: 'scorebox-criteria-actions' },
			el( Button, {
				variant: 'secondary',
				onClick: addCriterion,
				size: 'small'
			}, __( '+ Add Criterion', 'scorebox' ) ),
			criteria.length > 0 ? el( 'span', { className: 'scorebox-criteria-average' },
				__( 'Average: ', 'scorebox' ), el( 'strong', {}, avg + '/5' )
			) : null
		);

		return el( 'div', { className: 'scorebox-box-editor__criteria' },
			el( 'div', { className: 'scorebox-box-editor__criteria-heading' }, __( 'Rating Criteria', 'scorebox' ) ),
			items,
			actions
		);
	}

	// --- Rating input based on type ---
	function RatingInput( props ) {
		var ratingType = props.ratingType;
		var value = props.value;
		var onChange = props.onChange;

		var typeConfig = null;
		for ( var i = 0; i < CONFIG.ratingTypes.length; i++ ) {
			if ( CONFIG.ratingTypes[ i ].value === ratingType ) {
				typeConfig = CONFIG.ratingTypes[ i ];
				break;
			}
		}
		if ( ! typeConfig ) {
			typeConfig = CONFIG.ratingTypes[ 0 ];
		}

		if ( typeConfig.value === 'star' ) {
			return el( 'div', { className: 'scorebox-box-editor__rating' },
				el( StarRating, { value: value, onChange: onChange } ),
				el( RangeControl, {
					value: value,
					onChange: onChange,
					min: 0, max: typeConfig.max, step: typeConfig.step, withInputField: true
				} )
			);
		}

		return el( 'div', { className: 'scorebox-box-editor__rating' },
			el( RangeControl, {
				label: typeConfig.label,
				value: value,
				onChange: onChange,
				min: 0, max: typeConfig.max, step: typeConfig.step, withInputField: true
			} )
		);
	}

	// --- Main Edit component ---
	function Edit( props ) {
		var blockProps = useBlockProps();
		var context = props.context || {};
		var postType = context.postType || 'post';

		var entityProp = useEntityProp( 'postType', postType, 'meta' );
		var rawMeta = entityProp[ 0 ];
		var setMeta = entityProp[ 1 ];

		var rawReview = '';
		if ( rawMeta ) {
			rawReview = rawMeta._scorebox_review || '';
		}

		function parseReview( raw ) {
			if ( ! raw ) return Object.assign( {}, DEFAULTS );
			try {
				return Object.assign( {}, DEFAULTS, JSON.parse( raw ) );
			} catch ( e ) {
				return Object.assign( {}, DEFAULTS );
			}
		}

		var stateHook = useState( function() { return parseReview( rawReview ); } );
		var review = stateHook[ 0 ];
		var setReviewState = stateHook[ 1 ];

		useEffect( function() {
			setReviewState( parseReview( rawReview ) );
		}, [ rawReview ] );

		function updateReview( changes ) {
			var updated = Object.assign( {}, review, changes );
			// Auto-calculate overall rating from criteria when criteria are active.
			if ( updated.use_criteria && updated.criteria && updated.criteria.length > 0 ) {
				// Recalculate if criteria or use_criteria changed (not if rating was directly set).
				if ( changes.criteria !== undefined || changes.use_criteria !== undefined ) {
					updated.rating = calcCriteriaAverage( updated.criteria );
				}
			}
			setReviewState( updated );
			var newMeta = Object.assign( {}, rawMeta, { _scorebox_review: JSON.stringify( updated ) } );
			setMeta( newMeta );
		}

		var ratingTypeOptions = CONFIG.ratingTypes;
		var hasMultipleRatingTypes = ratingTypeOptions.length > 1;

		// Inspector controls (sidebar)
		var inspectorPanels = [];

		// Style panel.
		var styleChildren = [];
		var styleOptions = [ { label: __( 'Use global default', 'scorebox' ), value: '' } ].concat(
			CONFIG.styles.map( function( s ) {
				return { label: s.label, value: s.value };
			} )
		);

		if ( CONFIG.styles.length > 1 ) {
			styleChildren.push(
				el( SelectControl, {
					key: 'style-select',
					label: __( 'Review Box Style', 'scorebox' ),
					value: review.style || '',
					options: styleOptions,
					onChange: function( val ) { updateReview( { style: val } ); },
					help: __( 'Choose a visual style for this review box. Leave on global default unless you want a per-post override.', 'scorebox' )
				} )
			);
		} else {
			styleChildren.push(
				el( 'p', { key: 'style-label', style: { marginBottom: '8px' } },
					CONFIG.styles[ 0 ] ? CONFIG.styles[ 0 ].label : __( 'Default (Classic)', 'scorebox' )
				)
			);
		}

		inspectorPanels.push(
			el( PanelBody, { key: 'style', title: __( 'Style', 'scorebox' ), initialOpen: false },
				styleChildren
			)
		);

		// Rating type panel.
		var ratingTypeChildren = [];
		if ( hasMultipleRatingTypes ) {
			ratingTypeChildren.push(
				el( SelectControl, {
					key: 'type-select',
					label: __( 'Rating Type', 'scorebox' ),
					value: review.rating_type || 'star',
					options: ratingTypeOptions.map( function( t ) {
						return { label: t.label, value: t.value };
					} ),
					onChange: function( val ) {
						// Reset rating to 0 when switching types to avoid out-of-range values.
						updateReview( { rating_type: val, rating: 0 } );
					}
				} )
			);
		} else {
			ratingTypeChildren.push(
				el( 'p', { key: 'type-label', style: { marginBottom: '8px' } },
					ratingTypeOptions[ 0 ] ? ratingTypeOptions[ 0 ].label : __( 'Star (0-5)', 'scorebox' )
				)
			);
		}

		inspectorPanels.push(
			el( PanelBody, { key: 'rating-type', title: __( 'Rating Type', 'scorebox' ), initialOpen: false },
				ratingTypeChildren
			)
		);

		// Position panel.
		inspectorPanels.push(
			el( PanelBody, { key: 'position', title: __( 'Position', 'scorebox' ), initialOpen: false },
				el( SelectControl, {
					label: __( 'Review Box Position', 'scorebox' ),
					value: review.position || '',
					options: [
						{ label: __( 'Use global default', 'scorebox' ), value: '' },
						{ label: __( 'After content', 'scorebox' ), value: 'bottom' },
						{ label: __( 'Before content', 'scorebox' ), value: 'top' },
						{ label: __( 'Before and after content', 'scorebox' ), value: 'both' },
						{ label: __( 'Manual (block/shortcode only)', 'scorebox' ), value: 'manual' }
					],
					onChange: function( val ) { updateReview( { position: val } ); },
					help: __( 'Controls where the review box appears. "Manual" means only via block or [scorebox] shortcode.', 'scorebox' )
				} )
			)
		);

		// Schema settings panel.
		var schemaTypeOptions = CONFIG.schemaTypes && CONFIG.schemaTypes.length
			? CONFIG.schemaTypes
			: [
				{ label: 'Product', value: 'Product' },
				{ label: 'Software Application', value: 'SoftwareApplication' },
				{ label: 'Thing', value: 'Thing' }
			];

		// Build type-specific field controls for the currently selected schema type.
		function updateTypeField( typeKey, fieldKey, value ) {
			var tf = Object.assign( {}, review.type_fields || {} );
			var typeObj = Object.assign( {}, tf[ typeKey ] || {} );
			if ( value === '' || value === null || value === undefined ) {
				delete typeObj[ fieldKey ];
			} else {
				typeObj[ fieldKey ] = value;
			}
			if ( Object.keys( typeObj ).length === 0 ) {
				delete tf[ typeKey ];
			} else {
				tf[ typeKey ] = typeObj;
			}
			updateReview( { type_fields: tf } );
		}

		var typeFieldDefs = ( CONFIG.typeFields && CONFIG.typeFields[ review.schema_type ] ) || [];
		var typeFieldControls = typeFieldDefs.map( function ( f ) {
			var currentType = review.type_fields && review.type_fields[ review.schema_type ];
			var val = currentType && currentType[ f.key ] !== undefined ? currentType[ f.key ] : '';
			var onChange = function ( v ) { updateTypeField( review.schema_type, f.key, v ); };
			if ( f.type === 'textarea' ) {
				return el( TextareaControl, { key: 'tf-' + f.key, label: f.label, value: val, onChange: onChange } );
			}
			return el( TextControl, {
				key:      'tf-' + f.key,
				label:    f.label,
				type:     ( f.type === 'number' || f.type === 'date' ) ? f.type : 'text',
				value:    val,
				onChange: onChange
			} );
		} );

		inspectorPanels.push(
			el( PanelBody, { key: 'schema', title: __( 'Schema Settings', 'scorebox' ), initialOpen: false },
				el( SelectControl, {
					label: __( 'Schema Type', 'scorebox' ),
					value: review.schema_type,
					options: schemaTypeOptions,
					onChange: function( val ) { updateReview( { schema_type: val } ); }
				} ),
				typeFieldControls,
				el( TextControl, {
					label: __( 'Product / Item Name', 'scorebox' ),
					value: review.product_name,
					onChange: function( val ) { updateReview( { product_name: val } ); },
					placeholder: __( 'Defaults to post title', 'scorebox' )
				} ),
				el( TextControl, {
					label: __( 'Price', 'scorebox' ),
					value: review.price,
					onChange: function( val ) { updateReview( { price: val } ); },
					placeholder: '49.99'
				} ),
				el( SelectControl, {
					label: __( 'Currency', 'scorebox' ),
					value: review.currency,
					options: [
						{ label: 'USD', value: 'USD' },
						{ label: 'EUR', value: 'EUR' },
						{ label: 'GBP', value: 'GBP' }
					],
					onChange: function( val ) { updateReview( { currency: val } ); }
				} ),
				el( TextControl, {
					label: __( 'Author Name Override', 'scorebox' ),
					value: review.author_name,
					onChange: function( val ) { updateReview( { author_name: val } ); },
					placeholder: __( 'Uses default from settings', 'scorebox' )
				} )
			)
		);

		// CTA panel.
		inspectorPanels.push(
			el( PanelBody, { key: 'cta', title: __( 'CTA Button', 'scorebox' ), initialOpen: false },
				el( TextControl, {
					label: __( 'Button Text', 'scorebox' ),
					value: review.cta_text,
					onChange: function( val ) { updateReview( { cta_text: val } ); },
					placeholder: __( 'e.g., Visit Website', 'scorebox' )
				} ),
				el( TextControl, {
					label: __( 'Button URL', 'scorebox' ),
					value: review.cta_url,
					onChange: function( val ) { updateReview( { cta_url: val } ); },
					placeholder: 'https://',
					type: 'url'
				} )
			)
		);

		var inspector = el( InspectorControls, {}, inspectorPanels );

		// Main block content
		var contentChildren = [
			el( TextControl, {
				key: 'heading',
				className: 'scorebox-box-editor__heading',
				value: review.heading,
				onChange: function( val ) { updateReview( { heading: val } ); },
				placeholder: __( 'Review heading (e.g., Our Verdict)', 'scorebox' )
			} )
		];

		// Criteria toggle — gated behind config.
		if ( CONFIG.features.criteria ) {
			contentChildren.push(
				el( ToggleControl, {
					key: 'criteria-toggle',
					label: __( 'Multi-Criteria Rating', 'scorebox' ),
					checked: !! review.use_criteria,
					onChange: function( val ) { updateReview( { use_criteria: val } ); }
				} )
			);

			if ( review.use_criteria ) {
				contentChildren.push(
					el( CriteriaEditor, {
						key: 'criteria',
						criteria: review.criteria || [],
						onChange: function( val ) { updateReview( { criteria: val } ); }
					} )
				);
			}
		}

		// Rating input — adapts to rating type.
		var currentRatingType = review.rating_type || 'star';

		contentChildren.push(
			el( RatingInput, {
				key: 'rating',
				ratingType: currentRatingType,
				value: review.rating,
				onChange: function( val ) { updateReview( { rating: val } ); }
			} )
		);

		if ( CONFIG.features.criteria && review.use_criteria && review.criteria && review.criteria.length > 0 ) {
			contentChildren.push(
				el( 'span', {
					key: 'criteria-hint',
					style: { fontSize: '12px', color: '#888', display: 'block', marginTop: '-8px', marginBottom: '8px' }
				}, __( '(auto-calculated from criteria, or override manually)', 'scorebox' ) )
			);
		}

		contentChildren.push(
			el( TextareaControl, {
				key: 'summary',
				className: 'scorebox-box-editor__summary',
				value: review.summary,
				onChange: function( val ) { updateReview( { summary: val } ); },
				placeholder: __( 'Write a brief review summary...', 'scorebox' ),
				rows: 3
			} ),
			el( 'div', { key: 'pros-cons', className: 'scorebox-box-editor__pros-cons' },
				el( 'div', { className: 'scorebox-box-editor__pros' },
					el( 'h4', { className: 'scorebox-box-editor__list-heading scorebox-box-editor__list-heading--pros' }, __( 'Pros', 'scorebox' ) ),
					el( ListEditor, { items: review.pros, onChange: function( val ) { updateReview( { pros: val } ); }, placeholder: __( 'Add a pro...', 'scorebox' ) } )
				),
				el( 'div', { className: 'scorebox-box-editor__cons' },
					el( 'h4', { className: 'scorebox-box-editor__list-heading scorebox-box-editor__list-heading--cons' }, __( 'Cons', 'scorebox' ) ),
					el( ListEditor, { items: review.cons, onChange: function( val ) { updateReview( { cons: val } ); }, placeholder: __( 'Add a con...', 'scorebox' ) } )
				)
			)
		);

		if ( review.cta_text ) {
			contentChildren.push(
				el( 'div', { key: 'cta', className: 'scorebox-box-editor__cta' },
					el( 'span', { className: 'scorebox-box-editor__cta-button' }, review.cta_text )
				)
			);
		}

		var content = el( 'div', blockProps,
			el( 'div', { className: 'scorebox-box-editor' }, contentChildren )
		);

		return el( Fragment, {}, inspector, content );
	}

	// --- Register block ---
	wp.blocks.registerBlockType( 'scorebox/review-box', {
		edit: Edit,
		save: function() { return null; }
	} );
} )();
