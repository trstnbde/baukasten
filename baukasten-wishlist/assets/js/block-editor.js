/**
 * The Wishlist block in the editor.
 *
 * Written against the wp.* globals, without JSX, so the plugin needs no build
 * step. The preview is the server's own rendering.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var blockEditor = wp.blockEditor;
	var components = wp.components;
	var ServerSideRender = wp.serverSideRender;
	var data = window.baukastenWishlistEditor || { lists: [], sorts: [], statuses: [], i18n: {} };
	var t = data.i18n || {};

	function listOptions() {
		return [ { value: 0, label: '— ' + ( t.choose || '' ) + ' —' } ].concat( data.lists || [] );
	}

	function placeholder() {
		if ( ! data.lists || 0 === data.lists.length ) {
			return el(
				components.Placeholder,
				{ icon: 'heart', label: t.title },
				el( 'p', null, t.none ),
				el( components.Button, { variant: 'primary', href: data.newUrl, target: '_blank' }, t.create )
			);
		}

		return null;
	}

	wp.blocks.registerBlockType( 'baukasten/wishlist', {
		apiVersion: 3,
		title: t.title,
		icon: 'heart',
		category: 'widgets',

		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = blockEditor.useBlockProps();

			var inspector = el(
				blockEditor.InspectorControls,
				null,
				el(
					components.PanelBody,
					{ title: t.settings, initialOpen: true },
					el( components.SelectControl, {
						label: t.list,
						value: attributes.listId,
						options: listOptions(),
						onChange: function ( value ) {
							setAttributes( { listId: parseInt( value, 10 ) || 0 } );
						},
						__nextHasNoMarginBottom: true
					} ),
					el( components.SelectControl, {
						label: t.sort,
						value: attributes.sort,
						options: data.sorts,
						onChange: function ( value ) {
							setAttributes( { sort: value } );
						},
						__nextHasNoMarginBottom: true
					} ),
					el( components.SelectControl, {
						label: t.status,
						value: attributes.status,
						options: data.statuses,
						onChange: function ( value ) {
							setAttributes( { status: value } );
						},
						__nextHasNoMarginBottom: true
					} ),
					el( components.ToggleControl, {
						label: t.controls,
						checked: !! attributes.showControls,
						onChange: function ( value ) {
							setAttributes( { showControls: !! value } );
						},
						__nextHasNoMarginBottom: true
					} ),
					el( 'p', null, el( 'a', { href: data.editUrl, target: '_blank', rel: 'noopener' }, t.editLists ) )
				)
			);

			var empty = placeholder();
			var body;

			if ( empty ) {
				body = empty;
			} else if ( ! attributes.listId ) {
				body = el(
					components.Placeholder,
					{ icon: 'heart', label: t.title },
					el( components.SelectControl, {
						label: t.list,
						value: attributes.listId,
						options: listOptions(),
						onChange: function ( value ) {
							setAttributes( { listId: parseInt( value, 10 ) || 0 } );
						},
						__nextHasNoMarginBottom: true
					} )
				);
			} else {
				body = el( ServerSideRender, { block: 'baukasten/wishlist', attributes: attributes } );
			}

			return el( Fragment, null, inspector, el( 'div', blockProps, body ) );
		},

		save: function () {
			return null;
		}
	} );
}( window.wp ) );
