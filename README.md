# cf-settings-api-wrapper
Wraps the Settings API into a single hook for simple Settings page construction with extendible template architecture.

Quick usage example with [ ] tags for optional parameters and a very brief description for some special cases
```
add_filter( 'cf_settings__plugin_settings__eg_name', function( $a ) {
	return array(
		// Will be auto-generated based on "eg_name"
		[ 'id' => 'my-plugin-settings-id', ]
		// Will be auto-generated based on "eg_name"
		[ 'group' => 'my-plugin-group', ]
		// Will be auto-generated based on "eg_name"
		[ 'option_name' => 'my-plugin-db-name', ]

		// Will default inside the Settings menu with label based on "eg_name"
		[ 'menu' => array(
			[ 'label' => 'My Plugin Settings', ]
			[ 'parent' => '', ]
		), ]

		// Optional template settings
		[ 'page' => array(
			[ 'heading' => 'My Plugin Settings', ]
		), ]

		/**
		*  The 'settings' key is optional if none of the above are provided.
		*  Specifically, an array of arrays with no keys will be presumed to be
		*  the settings array. Invalid section structure will error gracefully
		*  if the assumption is in error.
		*/
		'settings' => array(
			array(
				'id' => 'my-first-section',
				[ 'label' => 'My First Section', ]
				'fields' => array(
					// Names of fields are technically optional, but are preferred
					'my-setting-1a' => array(
						'label' => 'Setting 1a',
						[ 'description' => '1a -- text', ]
						'type' => 'text',
						'default' => 'Setting 1a Default Value',
					),
					'my-setting-1b' => array(
						'label' => 'Setting 1b',
						'description' => '1b -- rel_link',
						'type' => 'rel_link',
					),
					'my-setting-1c' => array(
						'label' => 'Setting 1c',
						'description' => '1c -- select',
						'type' => 'select',
						// If the field type is one with multiple options, provide them.
						'options' => array(
							'1c-a' => '1c A',
							'1c-b' => '1c B',
							'1c-c' => '1c C',
							'1c-d' => '1c D',
						),
					),
					'my-setting-1d' => array(
						'label' => 'Setting 1d',
						'description' => '1d -- true_false',
						'type' => 'true_false',
						'default' => 0,
					),
				),
			),
			array(
				'id' => 'my-second-section',
				'label' => 'My Second Section',
				'fields' => array(
					// We can also handle grouped fields within a section
					'my-setting-group' => array(
						'label' => 'Settings Group',
						// Provide the "group" type and "multi" array of sub-fields
						'type' => 'group',
						'multi' => array(
							// These are constructed exactly as the standard fields
							'my-setting-2a-a' => array(
								'label' => 'Setting 2a-a',
								'description' => 'Text Field',
								'type' => 'text',
								'default' => "Some Text Value",
							),
							'my-setting-2a-b' => array(
								'label' => 'Setting 2a-b',
								'description' => 'Relative Link',
								'type' => 'rel_link',
								// Support exists for placeholders and is filterable.
								'default' => '::site_url::/home/',
							),
						),
					),
				),
			),
		),
	);
});
```
