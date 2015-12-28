<?php
/**
 * Plugin Name: Settings API Wrap
 * Plugin URI: http://crowdfavorite.com
 * Description: Wraps the settings API into a single filter
 * Version: 0.1
 * Author: Crowd Favorite
 * Author URI: http://crowdfavorite.com
 */

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }
if ( ! class_exists( 'CF_Settings' ) ) {

class CF_Settings {
	private static $registered_settings;
	private static $registrant;
	private static $templates;
	private static $assets_url;
	private static $placeholders;
	private static $allowed_tags = array(
		'code' => array(),
		'em' => array(),
		'strong' => array(),
		'br' => array(),
	);

	public static function add_plugin_settings() {
		global $wp_filter;

		$settings = array();
		foreach ( array_keys( $wp_filter ) as $hook ) {
			if ( strpos( $hook, 'cf_settings__plugin_settings__' ) === 0 ) {
				$plugin_name = substr( $hook, strlen( 'cf_settings__plugin_settings__' ) );
				if ( $plugin_name ) {
					if ( ! isset( $settings[ $plugin_name ] ) ) {
						$settings[ $plugin_name ] = apply_filters( $hook, array() );
					}
					else {
						CF_Settings::register_error(
							'Duplicate Plugin Name',
							$plugin_name,
							array(
								'registered_functions' => $wp_filter[ $hook ]
							)
						);
					}
				}
				else {
					CF_Settings::register_error(
						'Invalid Hook Declaration',
						'N/A',
						array(
							'filter' => $hook,
							'registered_functions' => $wp_filter[ $hook ]
						)
					);
				}
			}
		}
		CF_Settings::register_settings( $settings );

		if ( is_array( CF_Settings::$registered_settings ) && CF_Settings::$registered_settings ) {
			$plugin_names = array_keys( CF_Settings::$registered_settings );
			foreach ( $plugin_names as $plugin_name ) {
				$callbacks = CF_Settings::$registered_settings[ $plugin_name ]['callbacks'];
				add_action( 'admin_init', $callbacks['admin_init'] );
				add_action( 'admin_menu', $callbacks['admin_menu'] );
			}
		}
	}

	private static function register_settings( $raw_settings ) {
		if ( ! isset( CF_Settings::$registered_settings )
			|| ! is_array( CF_Settings::$registered_settings )
		) {
			CF_Settings::$registered_settings = array();
		}

		$settings = CF_Settings::fill_optional_keys(
			CF_Settings::sanitize_settings_array( $raw_settings )
		);

		foreach ( $settings as $plugin_name => $plugin_data ) {
			if ( ! isset( $registered_settings[ $plugin_name ] ) ) {
				if ( CF_Settings::set_registrant_plugin_data( $plugin_data, $plugin_name ) ) {
					CF_Settings::setup_registrant_entry();

					CF_Settings::register_settings_page_callback();
					// !! menu callback depends on page callback !!
					CF_Settings::register_admin_menu_callback();

					CF_Settings::register_settings_sections_callback();
					CF_Settings::register_settings_fields_callback();
					CF_Settings::register_admin_init_callback();
				}
			}
			else {
				CF_Settings::register_error( 'Unexpected Duplicate Plugin Name', $plugin_name, $plugin_data );
			}
		}
	}

	static function fill_optional_keys( $raw_settings ) {
		$plugin_names = array_keys( $raw_settings );
		foreach ( $plugin_names as $plugin_name ) {
			$raw_settings[ $plugin_name ] = CF_Settings::fill_setting_globals(
				$plugin_name,
				$raw_settings[ $plugin_name ]
			);
		}
		return $raw_settings;
	}

	static function fill_setting_globals( $plugin_name, $plugin_data ) {
		$base = sanitize_title( $plugin_name );
		if ( ! isset( $plugin_data['id'] )
			&& ! isset( $plugin_data['group'] )
			&& ! isset( $plugin_data['option_name'] )
			&& ! isset( $plugin_data['settings'] )
		) {
			if ( is_array( $plugin_data ) ) {
				// Try assuming the array is just a collection of settings
				$plugin_data = array(
					'settings' => $plugin_data,
				);
			}
			else {
				CF_Settings::register_error(
					'Invalid Plugin Settings',
					$plugin_name,
					$plugin_data
				);
				return array();
			}
		}
		if ( ! isset( $plugin_data['id'] ) ) {
			$plugin_data['id'] = $base . '-settings-id';
		}
		if ( ! isset( $plugin_data['group'] ) ) {
			$plugin_data['group'] = $base . '-settings-group';
		}
		if ( ! isset( $plugin_data['option_name'] ) ) {
			$plugin_data['option_name'] = $base . '-database-name';
		}
		return $plugin_data;
	}

	/**
	*  Specifically to ensure values that are or will become array IDs are of a reasonable
	*  format.
	*/
	private static function sanitize_settings_array( $settings_in ) {
		foreach ( $settings_in as $key => $val ) {
			if ( is_array( $val ) ) {
				$val = CF_Settings::sanitize_settings_array( $val );
			}
			$sanitized_key = sanitize_title( $key );
			if ( $sanitized_key != $key ) {
				$settings_in[ $sanitized_key ] = $val;
				unset( $settings_in[ $key ] );
			}
			if ( $key == 'id' ) {
				if ( is_string( $val ) ) {
					$settings_in[ $key ] = sanitize_title( $val );
				}
				else {
					unset( $settings_in[ $key ] );
				}
			}
		}
		return $settings_in;
	}

	private static function set_registrant_plugin_data( $plugin_data, $plugin_name ) {
		$registrant = array(
			'name' => $plugin_name,
			'id' => CF_Settings::extract_settings_id( $plugin_data, $plugin_name ),
			'group' => CF_Settings::extract_settings_group( $plugin_data, $plugin_name ),
			'option' => CF_Settings::extract_settings_option_name( $plugin_data, $plugin_name ),
			'menu' => array(),
			'page' => array(),
			'sections' => array(),
			'fields_by_section' => array(),
			'current_settings' => array()
		);

		CF_Settings::$registrant = $registrant;

		if ( $registrant['name'] && $registrant['id'] && $registrant['group'] && $registrant['option'] ) {
			CF_Settings::$registrant['menu'] = CF_Settings::build_menu_data_array( $plugin_data, $plugin_name );
			CF_Settings::$registrant['page'] = CF_Settings::build_page_data_array( $plugin_data, $plugin_name );
			CF_Settings::$registrant['sections'] = CF_Settings::build_sections_array( $plugin_data, $plugin_name );
			CF_Settings::$registrant['current_settings'] = get_option( $registrant['option'], array() );
			CF_Settings::$registrant['fields_by_section'] = CF_Settings::build_fields_array( $plugin_data, $plugin_name );
		}

		foreach ( CF_Settings::$registrant as $key => $val ) {
			if ( ! $val && $key != 'current_settings' ) {
				CF_Settings::register_error(
					'Registrant Setup Failure',
					$plugin_name,
					array(
						'failed step' => $key,
						'val' => $val,
						'registrant' => CF_Settings::$registrant,
						'settings data' => $plugin_data
					)
				);
				CF_Settings::$registrant = array();
				return false;
			}
		}
		return true;
	}

	private static function extract_settings_id( $plugin_data, $plugin_name ) {
		if ( isset( $plugin_data['id'] ) && $plugin_data['id'] ) {
			$id = $plugin_data['id'];
		}
		else {
			$id = apply_filters(
				'cf_settings__default_id__' . $plugin_name,
				$plugin_name . ' settings'
			);
		}
		return sanitize_title( $id );
	}

	private static function extract_settings_group ( $plugin_data, $plugin_name ) {
		if ( isset( $plugin_data['group'] ) && $plugin_data['group'] ) {
			$group = $plugin_data['group'];
		}
		else {
			$group = apply_filters(
				'cf_settings__default_group__' . $plugin_name,
				$plugin_name . ' group'
			);
		}
		return sanitize_title( $group );
	}

	private static function extract_settings_option_name ( $plugin_data, $plugin_name ) {
		if ( isset( $plugin_data['option_name'] ) && $plugin_data['option_name'] ) {
			$option = $plugin_data['option_name'];
		}
		else {
			$option = apply_filters(
				'cf_settings__default_option__' . $plugin_name,
				$plugin_name . ' options'
			);
		}
		return sanitize_title( $option );
	}

	private static function build_menu_data_array ( $plugin_data, $plugin_name ) {
		if ( isset( $plugin_data['menu'] )
			&& is_array( $plugin_data['menu'] )
			&& $plugin_data['menu']
		) {
			$provided = $plugin_data['menu'];
		}
		else {
			$provided = array();
		}

		$menu = array_merge( apply_filters( 'cf_settings__default_menu_options', array(
				'label' => CF_Settings::build_registrant_name( $plugin_name ),
				'parent' => 'options',
				'cap' => 'manage_options',
				'slug' => sanitize_title( $plugin_name ),
			) ),
			$provided
		);

		return $menu;
	}

	private static function build_page_data_array ( $plugin_data, $plugin_name ) {
		if ( isset( $plugin_data['page'] )
			&& is_array( $plugin_data['page'] )
			&& $plugin_data['page']
		) {
			$provided = $plugin_data['page'];
		}
		else {
			$provided = array();
		}

		$page = array_merge( apply_filters( 'cf_settings__default_page_options', array(
				'heading' => CF_Settings::build_registrant_name( $plugin_name ),
				'template' => CF_Settings::get_template( 'page/settings-simple' ),
				'settings_group' => $plugin_data['group'],
				'settings_id' => $plugin_data['id'],
			) ),
			$provided
		);

		return $page;
	}

	private static function build_sections_array ( $plugin_data, $plugin_name ) {
		$sections = array();
		foreach ( $plugin_data['settings'] as $section ) {
			if (
				! is_array( $section )
				|| ! isset( $section['id'] )
				|| ! isset( $section['fields'] )
			) {
				CF_Settings::register_error( 'Invalid Object in Settings array', $plugin_name, array(
					'section_submitted' => $section,
					'required_keys' => array(
						'id' => 'The Unique ID of the section',
						'fields' => 'The individual settings in this section.',
					)
				) );
			}
			else {
				if ( isset( $section['template'] ) && $section['template'] ) {
					$template = $section['template'];
				}
				else {
					$template = CF_Settings::get_template( 'section/default' );
				}
				$sections[ $section['id'] ] = array(
					'label' => isset( $section['label'] )
						? $section['label']
						: '',
					'template' => $template
				);
			}
		}
		return $sections;
	}

	private static function build_fields_array ( $plugin_data, $plugin_name ) {
		$fields = array();
		foreach ( $plugin_data['settings'] as $section => $section_data ) {
			if ( ! isset( $section_data['id'] ) || ! isset( $section_data['fields'] ) ) {
				continue;
			}
			$section_id = $section_data['id'];
			if ( ! isset( $fields[ $section_id ] ) ) {
				$fields[ $section_id ] = array();
			}
			else {
				CF_Settings::register_error( 'Duplicate section ID', $plugin_name, $section_id );
				continue;
			}
			foreach ( $section_data['fields'] as $field_id => $field_data ) {
				if ( $field_data['type'] == 'group' || isset( $field_data['multi'] ) ) {
					$field_array = CF_Settings::build_multi_field_array( $section_id, $field_id, $field_data['multi'] );
					if ( $field_array ) {
						$field_data['multi'] = $field_array;
						$field_data = CF_Settings::finalize_field_group( $field_data );
						$fields[ $section_id ][ $field_id ] = $field_data;
					}
				}
				else {
					$valid = CF_Settings::validate_field_settings( $field_data, $section_id );
					if ( $valid ) {
						$fields[ $section_id ][ $field_id ] = CF_Settings::finalize_field( $section_id, $field_id, $field_data );
					}
				}
			}
		}

		return $fields;
	}

	private static function build_multi_field_array( $section_id, $field_id, $fields ) {
		$multi_field_array = array();
		if ( ! is_array( $fields ) || count( $fields ) < 1 ) {
			CF_Settings::register_error(
				'Invalid Field Group',
				CF_Settings::$registrant['name'],
				array(
					'id' => $field_id,
					'field_group' => $fields
				)
			);
		}
		else {
			foreach ( $fields as $sub_field_id => $field_data ) {
				$valid = CF_Settings::validate_field_settings( $field_data, $section_id );
				if ( $valid ) {
					$multi_field_array[ $sub_field_id ] = CF_Settings::finalize_field( $section_id, $field_id, $field_data, $sub_field_id );
				}
			}
		}

		return $multi_field_array;
	}

	private static function validate_field_settings( $field_data, $section_id ) {
		$valid = true;
		if ( ! $valid ) {
			CF_Settings::register_error(
				'Invalid Field Entry',
				CF_Settings::$registrant['name'],
				array(
					'section' => $section_id,
					'field' => $field_data
				)
			);
			return false;
		}
		else {
			return true;
		}
	}

	private static function finalize_field( $section_id, $field_id, $field_data, $sub_field_id = null ) {
		$field_data['renderer'] = CF_Settings::get_field_template_renderer( $field_data );
		$field_data['value'] = CF_Settings::parse_existing_field_value( $section_id, $field_id, $field_data, $sub_field_id );
		$field_data['name'] = CF_Settings::build_field_name( $section_id, $field_id, $field_data, $sub_field_id );
		$field_data['in_group'] = ! is_null( $sub_field_id );
		return $field_data;
	}

	private static function finalize_field_group( $field_data ) {
		$field_data['renderer'] = CF_Settings::get_field_template_renderer( $field_data );
		return $field_data;
	}

	private static function parse_existing_field_value( $section_id, $field_id, $field_data, $sub_field_id = null ) {
		$current_settings = &CF_Settings::$registrant['current_settings'];
		$have_current = isset( $current_settings[ $section_id ] )
			&& isset( $current_settings[ $section_id ][ $field_id ] );

		if ( ! is_null( $sub_field_id ) ) {
			$have_current = $have_current
				&& isset( $current_settings[ $section_id ][ $field_id ][ $sub_field_id ] );
		}

		if ( ! $have_current ) {
			$current_settings = CF_Settings::build_section_subarrays( $current_settings, $section_id, $field_id, $sub_field_id );

			if ( is_null( $sub_field_id ) ) {
				$current_settings[ $section_id ][ $field_id ] = isset( $field_data['default'] )
					? $field_data['default']
					: '';
			}
			else {
				$current_settings[ $section_id ][ $field_id ][ $sub_field_id ] = isset( $field_data['default'] )
					? $field_data['default']
					: '';
			}
		}

		if ( is_null( $sub_field_id ) ) {
			$current = $current_settings[ $section_id ][ $field_id ];
		}
		else {
			$current = $current_settings[ $section_id ][ $field_id ][ $sub_field_id ];
		}
		return $current;
	}

	private static function build_section_subarrays( $current_settings, $section_id, $field_id, $sub_field_id = null ) {
		if ( ! isset( $current_settings[ $section_id ] ) ) {
			$current_settings[ $section_id ] = array();
		}
		if ( ! isset( $current_settings[ $section_id ][ $field_id ] ) ) {
			if ( ! is_null( $sub_field_id ) ) {
				$current_settings[ $section_id ][ $field_id ] = array();
			}
		}
		return $current_settings;
	}

	private static function build_field_name( $section_id, $field_id, $field_data, $sub_field_id = null ) {
		if ( ! is_null( $sub_field_id ) ) {
			$sub_index = '[' . $sub_field_id . ']';
		}
		else {
			$sub_index = '';
		}

		$name = CF_Settings::$registrant['option']
			. '[' . $section_id . ']'
			. '[' . $field_id . ']'
			. $sub_index;

		return $name;
	}

	private static function setup_registrant_entry() {
		// Verified before this function call that name is not a duplicate
		CF_Settings::$registered_settings[ CF_Settings::$registrant['name'] ] = array(
			'callbacks' => array(),
		);
	}

	private static function register_settings_page_callback() {
		$args = CF_Settings::$registrant['page'];

		CF_Settings::$registered_settings[ CF_Settings::$registrant['name'] ]['page_callback'] =
			function() use ( $args ) {
				wp_enqueue_style(
					'cf_settings_css',
					CF_Settings::get_settings_stylesheet()
				);
				$args = CF_Settings::fill_all_placeholders( apply_filters(
					'cf_settings__page_template_args', $args, $args['template']
				) );
				wp_enqueue_script(
					'cf_settings_actions',
					CF_Settings::get_actions_script(),
					array( 'jquery' )
				);
				include( $args['template'] );
			};
	}

	private static function register_admin_menu_callback() {
		$label = __( CF_Settings::$registrant['menu']['label'], 'cf_settings' );
		$parent = CF_Settings::$registrant['menu']['parent'];
		$cap = CF_Settings::$registrant['menu']['cap'];
		$slug = sanitize_title( CF_Settings::$registrant['menu']['slug'] );
		$renderer = CF_Settings::$registered_settings[ CF_Settings::$registrant['name'] ]['page_callback'];

		if ( ! $parent ) {
			$parent = 'menu';
		}
		// Misnomered menu item
		if ( $parent == 'settings' ) {
			$parent = 'options';
		}
		// add_submenu_page wrappers that exist as of WordPress 4.3.1
		$submenu_helpers = array(
			'dashboard',
			'posts',
			'media',
			'links',
			'pages',
			'comments',
			'theme',
			'plugins',
			'users',
			'management',
			'options',
		);

		// If we have a submenu page wrapper or just want to add a top level menu, call the appropriate wrapper
		if ( in_array( $parent, $submenu_helpers ) || $parent == 'menu' ) {
			CF_Settings::$registered_settings[ CF_Settings::$registrant['name'] ]['callbacks']['admin_menu'] =
				function() use ( $parent, $label, $cap, $slug, $renderer ) {
					call_user_func( 'add_' . $parent . '_page',
						$label,
						$label,
						$cap,
						$slug,
						$renderer
					);
				};
		}
		/**
		*  Otherwise try to put it under a post type menu. Any custom top level menu is safe, but we
		*  can't know what they'll be so we'll just issue a warning that says we don't know if the
		*  parent provided is valid.
		*/
		else {
			if ( strpos( $parent, 'post_type__' ) === 0 ) {
				$parent = 'edit.php?post_type=' . substr( $parent, strlen( 'post_type__' ) );
			}
			else {
				CF_Settings::register_warning(
					'Possible unknown menu item parent',
					CF_Settings::$registrant['name'],
					array(
						'function_exists' => function_exists( 'add_' . $parent . '_page' )
							? 'yes'
							: 'no',
						'function_checked' => 'add_' . $parent . '_page',
						'given_parent' => $parent,
					)
				);
			}

			CF_Settings::$registered_settings[ CF_Settings::$registrant['name'] ]['callbacks']['admin_menu'] =
				function() use ( $parent, $label, $cap, $slug, $renderer ) {
					add_submenu_page( $parent,
						$label,
						$label,
						$cap,
						$slug,
						$renderer
					);
				};
		}
	}

	private static function register_settings_sections_callback() {
		$plugin_name = CF_Settings::$registrant['name'];
		$settings_group = CF_Settings::$registrant['group'];
		$settings_option = CF_Settings::$registrant['option'];
		$sections = CF_Settings::$registrant['sections'];
		$settings_id = CF_Settings::$registrant['id'];

		CF_Settings::$registered_settings[ CF_Settings::$registrant['name'] ]['callbacks']['sections'] =
			function() use ( $plugin_name, $settings_group, $settings_option, $sections, $settings_id ) {

				register_setting( $settings_group, $settings_option );
				foreach ( $sections as $section_id => $section_data ) {
					$renderer =
						function() use ( $section_id, $section_data ) {
							$args = CF_Settings::fill_all_placeholders( apply_filters(
								'cf_settings__section_template_args',
								array_merge( array( 'id' => $section_id ), $section_data ),
								$section_data['template']
							) );
							include( $args['template'] );
						};
					add_settings_section(
						$section_id,
						$section_data['label'],
						$renderer,
						$settings_id
					);
				}
			};
	}

	private static function register_settings_fields_callback() {
		$fields_by_section = CF_Settings::$registrant['fields_by_section'];
		$settings_id = CF_Settings::$registrant['id'];

		CF_Settings::$registered_settings[ CF_Settings::$registrant['name'] ]['callbacks']['fields'] =
			function() use ( $fields_by_section, $settings_id ) {
				foreach ( $fields_by_section as $section_id => $fields ) {
					foreach ( $fields as $field_id => $field_data ) {
						add_settings_field(
							$field_id,
							$field_data['label'],
							$field_data['renderer'],
							$settings_id,
							$section_id,
							$field_data
						);
					}
				}
			};
	}

	private static function register_admin_init_callback() {
		$callbacks = CF_Settings::$registered_settings[ CF_Settings::$registrant['name'] ]['callbacks'];
		CF_Settings::$registered_settings[ CF_Settings::$registrant['name'] ]['callbacks']['admin_init'] =
			function() use ( $callbacks ) {
				$callbacks['sections']();
				$callbacks['fields']();
			};
	}

	private static function get_field_template_renderer( $field_data ) {
		$template = CF_Settings::get_template( 'field/' . $field_data['type'] );
		$allowed_tags = CF_Settings::$allowed_tags;

		return function( $args ) use ( $template, $allowed_tags ) {
			$args = CF_Settings::fill_all_placeholders( $args );
			include( $template );
		};
	}

	private static function init_templates() {
		$templates = array();
		$template_dir = CF_Settings::get_template_dir();
		foreach ( glob( $template_dir . '*', GLOB_ONLYDIR ) as $dir ) {
			$template_group = basename( $dir );
			if ( ! isset( $templates[ $template_group ] ) ) {
				$templates[ $template_group ] = array();
			}
			foreach ( glob( trailingslashit( $dir ) . '*.php' ) as $template ) {
				$template_name = substr( basename( $template ), 0, -4 );
				$templates[ $template_group ][ $template_name ] = apply_filters(
						'cf_settings__template__' . $template_group . '__' . $template_name,
						$template
					);
			}
 		}
 		CF_Settings::$templates = apply_filters( 'cf_settings__templates__all', $templates );
	}

	private static function get_template_dir() {
		return trailingslashit( dirname( __FILE__ ) ) . 'assets/templates/';
	}

	private static function get_template( $reference ) {
		list( $group, $part ) = explode( '/', $reference );
		$templates = CF_Settings::get_all_templates();
		if ( isset( $templates[ $group ] ) && isset( $templates[ $group ][ $part ] ) ) {
			return $templates[ $group ][ $part ];
		}
		else {
			if ( isset( $templates[ $group ] ) ) {
				return $templates[ $group ]['default'];
			}
			else {
				return $template['default'];
			}
		}
		return $templates[ $group ][ $part ];
	}

	private static function get_all_templates() {
		if ( ! isset( CF_Settings::$templates ) ) {
			CF_Settings::init_templates();
		}
		return CF_Settings::$templates;
	}

	private static function build_registrant_name() {
		return ucwords( preg_replace( '/[-_]/', ' ', CF_Settings::$registrant['name'] ) ) . ' Settings';
	}

	public static function fill_placeholders( $value ) {
		if ( ! isset( CF_Settings::$placeholders ) || ! is_array( CF_Settings::$placeholders ) ) {
			CF_Settings::$placeholders = apply_filters( 'cf_settings__value_placeholders', array(
					'home_url' => home_url(),
					'site_url' => site_url(),
				)
			);
		}
		if ( is_string( $value ) ) {
			foreach ( CF_Settings::$placeholders as $placeholder => $filler ) {
				if ( strpos( $value, '::' . $placeholder . '::' ) !== false ) {
					$value = str_replace( '::' . $placeholder . '::', $filler, $value );
				}
			}
		}
		return $value;
	}

	public static function fill_all_placeholders( $array ) {
		foreach ( $array as $k => $v ) {
			if ( is_array( $v ) ) {
				$array[ $k ] = CF_Settings::fill_all_placeholders( $v );
			}
			else {
				$array[ $k ] = CF_Settings::fill_placeholders( $v );
			}
		}
		return $array;
	}

	private static function register_error( $msg, $plugin_name, $data ) {

	}

	private static function register_warning( $msg, $plugin_name, $data ) {

	}

	public static function get_settings_stylesheet() {
		$assets = CF_Settings::get_assets_url();
		return apply_filters( 'cf_settings__default_settings_css', $assets . 'css/style.css' );
	}

	private static function get_assets_url() {
		if ( ! isset( CF_Settings::$assets_url ) ) {
			CF_Settings::$assets_url = plugin_dir_url( __FILE__ ) . 'assets/';
		}
		return CF_Settings::$assets_url;
	}

	public static function get_actions_script() {
		$assets = CF_Settings::get_assets_url();
		return apply_filters( 'cf_settings__default_actions_script', $assets . 'js/actions.js' );
	}

}
add_action( 'plugins_loaded', 'CF_Settings::add_plugin_settings' );

}
