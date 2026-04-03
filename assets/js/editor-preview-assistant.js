( function ( wp, config ) {
	if ( ! wp || ! config || ! config.enabled ) {
		return;
	}

	const plugins = wp.plugins || {};
	const editPost = wp.editPost || {};
	const element = wp.element || {};
	const components = wp.components || {};

	if ( ! plugins.registerPlugin || ! editPost.PluginPostStatusInfo || ! element.createElement ) {
		return;
	}

	const el = element.createElement;
	const ExternalLink = components.ExternalLink || null;
	const useExternalLink = '_blank' === config.linkTarget;

	function PreviewInfo() {
		return el(
			editPost.PluginPostStatusInfo,
			{
				className: 'wtr-preview-status-info'
			},
			el(
				'div',
				{
					style: {
						display: 'grid',
						gap: '10px'
					}
				},
				el(
					'strong',
					{
						style: {
							fontSize: '13px'
						}
					},
					config.heading
				),
				el(
					'p',
					{
						style: {
							margin: 0
						}
					},
					config.message
				),
				config.secondary
					? el(
							'p',
							{
								style: {
									margin: 0,
									color: '#50575e'
								}
							},
							config.secondary
					  )
					: null,
				config.linkUrl && config.linkLabel
					? el(
							'p',
							{
								style: {
									margin: 0
								}
							},
							useExternalLink && ExternalLink
								? el(
										ExternalLink,
										{
											href: config.linkUrl
										},
										config.linkLabel
								  )
								: el(
										'a',
										{
											href: config.linkUrl,
											target: useExternalLink ? '_blank' : undefined,
											rel: useExternalLink ? 'noreferrer' : undefined
										},
										config.linkLabel
								  )
					  )
					: null
			)
		);
	}

	plugins.registerPlugin( 'wtr-preview-assistant', {
		render: PreviewInfo
	} );
} )( window.wp, window.wtrPreviewAssistant );
