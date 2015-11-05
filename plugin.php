<?php
/**
 * Plugin Name: Settings API Wrap
 * Plugin URI: http://crowdfavorite.com
 * Description: Wraps the settings API into a single filter
 * Version: 0.0
 * Author: Crowd Favorite
 * Author URI: http://crowdfavorite.com
 */

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) { die(); }

class CF_Settings {
	/**
	*  Standard Singleton implementation
	*/
	private static $instance;

	public static function get_object() {
		if ( ! static::$instance ) {
			static::$instance = new CF_Settings();
		}
		return static::$instance;
	}

	// Enforce use of singleton
	protected function __construct() { }
	private function __clone() { }
	private function __wakeup() { }



	/**
	*  General class definition
	*/
	private $plugins_settings;
	private $registrant;

	public function add_plugin_settings() {
		$settings = apply_filters( 'cf_settings__plugin_settings', array() );
		if ( $settings ) {
			$this->register_settings( $settings );
		}
		else {
			$this->plugins_settings = array();
		}
	}

	private function register_settings( $raw_settings ) {
		if ( ! isset( $this->plugins_settings )
			|| gettype( $this->plugins_settings ) !== 'stdObject'
		) {
			$this->plugins_settings = (object) array();
		}

		$raw_settings = $this->sanitize_input( $raw_settings );

		foreach ( $raw_settings as $plugin_name => $plugin_data ) {
			if ( ! isset( $registered_settings[ $plugin_name ] ) ) {
				if ( $this->set_registrant_plugin_data( $plugin_data, $plugin_name ) ) {
					$this->setup_registrant_entry();

					$this->register_settings_page_callback();
					// !! menu callback depends on page callback !!
					$this->register_settings_menu_callback();

					$this->register_settings_sections_callback();
					$this->register_settings_fields_callback();
				}
				else {
					$this->register_error( 'registrant_failure', $plugin_name, $plugin_data );
				}
			}
			else {
				$this->register_error( 'duplicate_plugin_name', $plugin_name, $plugin_data );
			}
		}
	}

	/**
	*  Specifically to ensure values that are or will become array IDs are of a reasonable
	*  format so that any process which might use them as object members will not stop
	*  execution
	*/
	private function sanitize_input( $settings_in ) {
		foreach ( $settings_in as $key => $val ) {
			if ( is_array( $val ) ) {
				$val = $this->sanitize_input( $val );
			}
			unset( $settings_in[ $key ] );
			$settings_in[ str_replace( '-', '_', sanitize_title( $key ) ) ] = $val;

			if ( $key == 'id' && is_string( $val ) ) {
				$settings_in[ $key ] = str_replace( '-', '_', sanitize_title( $val ) );
			}
			else {
				// Most will be auto-generated. Those that wont will throw an error.
				unset( $settings_in[ $key ] );
			}
		}
		return $settings_in;
	}

	private function set_registrant_plugin_data( $plugin_data, $plugin_name ) {
		$registrant = array(
			'name' => $plugin_name,
			'id' => $this->extract_settings_id( $plugin_data, $plugin_name ),
			'group' => $this->extract_settings_group( $plugin_data, $plugin_name ),
			'option' => $this->extract_settings_option_name( $plugin_data, $plugin_name ),
			'menu' => $this->build_menu_data_array( $plugin_data, $plugin_name ),
			'page' => $this->build_page_data_array( $plugin_data, $plugin_name ),
			'sections' => $this->build_sections_array( $plugin_data, $plugin_name ),
			'fields_by_section' => $this->build_fields_array( $plugin_data, $plugin_name ),
		);

		foreach ( $registrant as $key => $val ) {
			if ( ! $val ) {
				return false;
			}
		}

		$this->registrant = (object) $registrant;
		return true;
	}

	private function extract_settings_id( $plugin_data, $plugin_name ) {
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

	private function extract_settings_group ( $plugin_data, $plugin_name ) {
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

	private function extract_settings_option_name ( $plugin_data, $plugin_name ) {
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

	private function build_menu_data_array ( $plugin_data, $plugin_name ) {
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
				'label' => $this->build_registrant_name( $plugin_name ),
				'parent' => 'options',
				'cap' => 'manage_options',
				'slug' => santize_title( $plugin_name ),
			) ),
			$provided
		);

		return $menu;
	}

	private function build_page_data_array ( $plugin_data ) {
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
				'heading' => $this->build_registrant_name( $plugin_name ),
				'template' => $this->get_template( 'page/settings_simple' ),
			) ),
			$provided
		);

		return $page;
	}

	private function build_sections_array ( $plugin_data, $plugin_name ) {
		$sections = array();
		foreach ( $plugin_data['settings'] as $section ) {
			if ( ! is_array( $section ) || ! isset( $section['id'] ) && ! isset( $section['name'] ) ) {
				$this->register_error( 'Invalid Object in Settings array', $plugin_name, $section );
			}
			else {
				if ( isset( $section['template'] ) && $section['template'] ) {
					$template = $section['template'];
				}
				else {
					$template = $this->get_template( 'section/default' );
				}
				$sections[ $section['id'] ] = array(
					'name' => $section['name'],
					'template' => $template
				);
			}
		}
		return $sections;
	}

	private function build_fields_array ( $plugin_data, $plugin_name ) {
		$fields = array();
		foreach ( $plugin_data['settings'] as $section => $section_data ) {
			if ( ! isset( $section_data['id'] ) ) {
				continue;
			}
			$section_id = $section_data['id'];
			if ( ! isset( $fields[ $section_id ] ) ) {
				$fields[ $section_id ] = array();
			}
			else {
				$this->register_error( 'Duplicate section ID', $plugin_name, $section_id );
				continue;
			}

			foreach ( $section_data['fields'] as $field_id => $field ) {
				$field_id = $field_id;
				if ( $this->validate_field_settings( $field ) && $field_id ) {
					$field['renderer'] = $this->field_type_template_callback( $field );
					$fields[ $section_id ][ $field_id ] = $field;
				}
				else {
					$this->register_error(
						'Invalid Field Entry',
						$plugin_name,
						array(
							'section' => $section_data['id'],
							'field' => $field
						)
					);
				}
			}
		}

		return $fields;
	}


	private function setup_registrant_entry() {
		// Verified before this function call that name is not a duplicate
		$this->registered_settings[ $this->registrant->name ] = array(
			'callbacks' => array(),
		);
	}

	private function register_settings_page_callback() {
		$args = $this->registrant->page;

		$this->registered_settings[ $this->registrant->name ]['page_callback'] =
			function() use( $args ) {
				$cf_settings = CF_Settings::get_object();
				wp_enqueue_style(
					'cf_settings_css',
					$cf_settings->get_settings_stylesheet()
				);
				$args = $cf_settings->fill_all_placeholders( $args );
				include( $args['template'] );
			};
	}

	private function register_settings_menu() {
		$label = __( $this->registrant->menu['label'], 'cf_settings' );
		$parent = $this->registrant->menu['parent'];
		$cap = $this->registrant->menu['required_cap'];
		$slug = sanitize_title( $this->registrant->menu['slug'] );
		$renderer = $this->registered_settings[ $this->registrant->name ]['page_callback'];

		if ( ! $parent ) {
			$parent = 'menu';
		}

		if ( function_exists( 'add_' . $parent . '_page' ) ) {
			$this->registered_settings[ $this->registrant->name ]['callbacks']['menu'] =
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
		else {
			if ( strpos( $parent, 'post_type__' ) === 0 ) {
				$parent = 'edit.php?post_type=' . substr( $parent, strlen( 'post_type__' ) );
			}
			else {
				$this->register_warning(
					'Possible unknown menu item parent',
					$this->registrant->name,
					array(
						'given_parent' => $parent,
					)
				);
			}

			$this->registered_settings[ $this->registrant->name ]['callbacks']['menu'] =
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

	private function register_settings_sections_callback() {
		$plugin_name = $this->registrant->name;
		$settings_group = $this->registrant->group;
		$settings_option = $this->registrant->option;
		$sections = $this->registrant->sections;
		$settings_id = $this->registrant->id;
		$slug = $this->registrant->menu['slug'];

		$this->registered_settings[ $this->registrant->name ]['callbacks']['sections'] =
			function() use ( $plugin_name, $settings_group, $settings_option, $sections, $settings_id, $slug ) {

				register_setting( $settings_group, $settings_option );
				foreach ( $sections as $section_id => $section_data ) {
					$renderer = function() use ( $section_data ) {
						$cf_settings = CF_Settings::get_object();
						$args = $cf_settings->fill_all_placeholders( $section_data );
						include( $args['template'] );
					};
					add_settings_section(
						$section_id,
						$section_data['name'],
						$renderer,
						$slug
					);
				}
			};
	}

	private function register_settings_fields_callback() {
		$fields_by_section = $this->registrant->fields_by_section;
		$settings_id = $this->registrant->id;

		$this->registered_settings[ $this->registrant->name ]['callbacks']['fields'] =
			function() use ( $fields_by_section, $sections, $settings_id ) {
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

	private function get_field_template_renderer( $field_data ) {
		$template = $this->get_template( 'field/' . $field_data['type'] );

		return function( $args ) use ( $template ) {
			$args = self::fill_all_placeholders( $args );
			$allowed_tags = self::$allowed_tags;
			include( $template );
		};
	}

	private function build_registrant_name() {
		return ucwords( preg_replace( '/-_/', ' ', $this->registrant->name ) ) . ' Settings';
	}

	private function register_error( $type, $plugin_name, $data ) {

	}

	private function register_warning( $type, $plugin_name, $data ) {

	}

	public function asdf() {

	}

}
