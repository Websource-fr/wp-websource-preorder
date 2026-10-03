/* Blocs Gutenberg (rendu côté serveur). */
( function ( wp ) {
	var el = wp.element.createElement;
	var SSR = wp.serverSideRender;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var blocks = {
		featured: 'Précommande à la une',
		ticker: 'Bandeau des précommandes',
		list: 'Liste des précommandes',
		subscribe: 'Alertes précommandes'
	};
	Object.keys( blocks ).forEach( function ( slug ) {
		wp.blocks.registerBlockType( 'websource-preorder/' + slug, {
			apiVersion: 2,
			title: blocks[ slug ],
			category: 'widgets',
			icon: 'clock',
			description: 'Websource Précommandes',
			edit: function () {
				return el( 'div', useBlockProps(), el( SSR, { block: 'websource-preorder/' + slug } ) );
			},
			save: function () { return null; }
		} );
	} );
} )( window.wp );
