<?php


/**
 * ACF_json_location_manager
 *
 * This class utility helps to manage ACF Field group json files from several paths.
 * It add, for each field group, a metabox used to select a location (in plugins, themes or user defined location) then
 * it manages jsons when required ie during load, save, trash, un trash or delete actions.
 *
 *
 * @version : 2.2
 * @author : Christian Denat
 * @mail : contact@noleam.fr
 *
 *
 * twitter : @chdenat
 * github @chdenat
 *
 * ***********
 *
 *  Feel free to use it freely for personal or commercial use.
 *  Credits will be appreciated.
 *
 */

namespace NOLEAM\ACF\UTILS;

use function get_plugins;

class ACF_json_location_manager {

	/**
	 * @var ACF_json_location_manager
	 */
	private static ACF_json_location_manager $_instance;
	/**
	 * @var string
	 */
	private string $json_dir;
	/**
	 * @var string
	 */
	private string $type = 'json-location';
	/**
	 * @var array of string
	 */
	private array $location_list;
	/**
	 * @var string
	 */
	private string $load_json;
	/**
	 * @var mixed
	 */
	private bool $add_column;

	/**
	 * ACF_json_location_manager constructor.
	 *
	 * @param null $args array of parameters
	 *
	 * 'load-json' : This directory is used used to centralize all json files
	 *               before triggering 'acf/settings/load_json' hook. It stands in theme root,
	 *               in order to change theme without problem.
	 *               default = 'ajlm'
	 * 'json-dir' :  This directory stands in each Theme,Plugin or user defined root dir and
	 *               it is used to save json when triggering  'acf/settings/save_json' hook.
	 *               default = acf-json
	 * 'add-column'  Boolean value. if true, a new column in Field Group list shows the JSON Location
	 *               default = true.
	 */

	public function __construct( $args = null ) {

		$this->settings( $args );

		// ACF hooks
		add_filter( 'acf/settings/save_json', [ $this, 'set_save_point' ] );
		add_filter( 'acf/settings/load_json', [ $this, 'set_load_point' ] );
		add_action( 'acf/trash_field_group', [ $this, 'delete_file' ] );
		add_action( 'acf/delete_field_group', [ $this, 'delete_file' ] );
		add_action( 'acf/untrash_field_group', [ $this, 'untrash_file' ], 99 );

		// Add a meta box for Json locations
		add_action( 'acf/field_group/admin_head', [ $this, 'add_json_location_meta_box' ] );

		// Add information in admin field group table
		add_action( 'manage_acf-field-group_posts_custom_column', [ $this, 'add_location_in_column' ], 11, 2 );

		// Remove tmp file and dir.
		add_action( 'acf/render_field_group_settings', [ $this, 'clean_dir' ] );

		// That's all
	}

	/**
	 * AJLM Settings
	 *
	 * @param null $args
	 *
	 * @since 2.0
	 */

	private function settings( $args = null ): void {

		$options = wp_parse_args( $args, [
			'json-dir'   => 'acf-json',  // name of the json location in each plugin/theme
			'load-json'  => 'ajlm',      // Dir used for loading jsons
			'add-column' => true,        // add a column
		] );

		if ( $options !== null ) {
			foreach ( $options as $key => $option ) {
				switch ( $key ) {
					case 'load-json' :
						/**
						 * This directory is used used to centralize all json files
						 * before triggering 'acf/settings/load_json' hook
						 *
						 * Stands in theme root, in order to change theme without problem
						 */
						$this->load_json = get_theme_root() . '/' . $option;
						break;
					case 'json-dir':
						/**
						 * This directory stands in each Theme or Plugin root dir and
						 * it is used to save json when triggering  'acf/settings/save_json' hook
						 */
						$this->json_dir = $option;
						break;

					case 'add-column':
						/**
						 * Boolean value : if true, a new column in Field Group list shows the JSON Location
						 */
						$this->add_column = $option;
					default :
				}
			}
		}

		$this->set_locations();
		$this->copy_files();
	}

	/**
	 * Get all possible json locations in activated plugins or themes (parent+child)
	 *
	 * We scan all possible directories (active plugins, child and parent themes) to check if json_dir exists.
	 *
	 * @since 2.0
	 *
	 */

	private function set_locations(): void {

		$this->location_list = [];

		/**
		 * Add code to manage plugins if needed.
		 */
		if ( ! function_exists( 'get_plugins' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		/**
		 * Get current theme. We propose to use Parent or Child theme if it exists.
		 *
		 * We check the theme and add it to the selection list if it contains the right sub-directory used
		 * to save json files then set information.
		 */

		$theme  = wp_get_theme();
		$parent = $theme->get( 'Template' );
		$name   = $theme->get( 'Name' );

		// Child theme or parent ?
		$child = ( ! empty( $parent ) );

		//Child theme
		if ( $child && is_dir( $path = get_stylesheet_directory() . '/' . $this->json_dir ) ) {
			$label                          = 'Theme: ' . $name . ' (child)';
			$this->location_list [ $label ] = [
				'type'  => 'child',
				'value' => $path,
				'name'  => $label
			];
		}

		//Parent or single theme
		if ( is_dir( $path = get_template_directory() . '/' . $this->json_dir ) ) {
			$label                          = 'Theme: ' . $name . ( ( $child ) ? ' (parent)' : '' );
			$this->location_list [ $label ] = [
				'type'  => ( $child ) ? 'parent' : 'theme',
				'value' => $path,
				'name'  => $label
			];
		}

		/**
		 * Get all plugins
		 *
		 * For each active one, we check if there is the right sub-directory used
		 * to save json files the set information.
		 */

		$plugins = get_plugins();
		foreach ( $plugins as $uri => $plugin_data ) {
			if ( is_plugin_active( $uri ) ) {
				$json_location = '/' . explode( '/', $uri )[0] . '/' . $this->json_dir;
				if ( is_dir( $path = WP_PLUGIN_DIR . $json_location ) ) {
					$label                          = 'Plugin: ' . $plugin_data['Title'];
					$this->location_list [ $label ] = [
						'type'  => 'plugin',
						'value' => $path,
						'name'  => $label
					];
				}
			}
		}


		// We can also remove existing location or add any others.
		$this->location_list = apply_filters( 'ajlm/manage-json-location', $this->location_list );
	}

	/**
	 * Copy all json files found into $this->load_json dir
	 *
	 * As ACF can not natively used multiple load_json points, we creat a unique one and copy all
	 * required files into it.
	 *
	 * @since 2.0
	 */
	private function copy_files(): void {
		$can_copy_files = true;
		/**
		 * Some checks for load_json dir.
		 */
		if ( ! file_exists( $this->load_json ) ) {
			// Does not exist : ok create it
			$can_copy_files = mkdir( $this->load_json );
		} else if ( ! is_dir( $this->load_json ) ) {
			// Not a dir... stop !
			$can_copy_files = false;
		}
		/**
		 * We're ready now to retrieve all files and copy them into this directory.
		 */
		if ( $can_copy_files ) {
			//Maybe some files are existing.. Cleaning them
			$files = glob( $this->load_json . '/*.json' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					try {
						unlink( $file );
					} catch ( \Exception $e ) {
						// failed silently
					}
				}
			}

			$exclude = [ '.', '..' ]; // obvious
			foreach ( $this->location_list as $location ) {
				$files = array_diff( scandir( $location['value'] ), $exclude );
				foreach ( $files as $file ) {
					copy( $location['value'] . '/' . $file, $this->load_json . '/' . $file );
				}
			}
		}
	}

	/**
	 * Instantiation of ACF JSON Location Manager
	 *
	 * @param null $args
	 *
	 * @return ACF_json_location_manager|null
	 *
	 * @since 2.0
	 */

	public static function init( $args = null ): ?ACF_json_location_manager {
		if ( ! is_admin() ) {
			return null;
		}

		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new ACF_json_location_manager( $args );
		}

		return self::$_instance;
	}

	/**
	 * acf/settings/save_json hook
	 *
	 * According to  the user selection, we use the right path for acf-json
	 * To avoid doublons if the path has changed, we remove the .json file (if it is  existing) in other locations.
	 *
	 * @param $path
	 *
	 * @return string | false
	 *
	 * @since 2.0
	 */
	public function set_save_point( $path ) {

		// We use $_POST to retrieve the filed group information
		if ( empty( $_POST ) ) {
			return $path;
		}

		// We scan $_POST
		$location = $_POST['acf_field_group'][ $this->type ];

		if ( isset( $location, $this->location_list[ $location ] ) ) {
			$path = $this->location_list[ $location ]['value'];
		}

		if ( null !== $path ) {
			// In case the location has  changed, we remove the former json file.
			// We scan all possible locations (except current one) and remove file.

			foreach ( $this->location_list as $key => $location ) {
				$file = $location['value'] . '/' . $_POST['post_name'] . '.json';
				if ( $location['value'] !== $path && file_exists( $file ) ) {
					@unlink( $file );
				}
			}
		}

		return $path;

	}

	/**
	 * acf/trash_field_group & acf/delete_field_group hooks
	 *
	 * We remove the json file when we remove the field_group
	 *
	 * @param $field_group
	 *
	 * @since 2.0
	 */
	public function delete_file( $field_group ): void {

		$target = $field_group['json-location'];
		foreach ( $this->location_list as $key => $location ) {
			if ( $key === $target ) {
				// Key is suffixed by __trashed, change it and get file
				$file = $this->get_file( $field_group, str_replace( '__trashed', '', $field_group['key'] ) );
				if ( file_exists( $file ) ) {
					unlink( $file );
				}
			}
		}
	}

	/**
	 *
	 * Get the json file
	 *
	 * @param $field_group
	 *
	 * @param null $new_key : new value of key (used when deleting field group)
	 *
	 * @return false|string
	 *
	 * @since 2.0
	 *
	 */
	private function get_file( $field_group, $new_key = null ) {
		$key = ( $new_key ) ?? $field_group['key'] ?? null;
		if ( $key ) {
			$path = $this->get_path( $field_group );
			if ( $path ) {
				return untrailingslashit( $path ) . '/' . $key . '.json';
			}
		}

		return false;
	}

	/**
	 *
	 * Get the json file path, according to location value
	 *
	 * @param $field_group
	 *
	 * @return false|mixed|string
	 *
	 */

	private function get_path( $field_group ) {
		$path     = '';
		$location = $field_group[ $this->type ];
		if ( isset( $location, $this->location_list[ $location ] ) ) {
			$path = $this->location_list[ $location ]['value'];
		}
		if ( ! is_writable( $path ) ) {
			return false;
		}

		return $path;
	}

	/**
	 * acf/untrash_field_group hook
	 *
	 * We re create the json file at the right place when the field group is untrashed
	 *
	 * Part of code from ACF
	 *
	 * @param $field_group
	 *
	 * @return bool|null
	 * @since 2.0
	 */

	public function untrash_file( $field_group ): bool {
		// get path and exit if there is none
		$path = $this->get_path( $field_group );

		if ( ! $path ) {
			return false;
		}

		$file = $this->get_file( $field_group );

		/**
		 * acf/update_field_group has been triggered just before, and it have not all the right dir information.
		 * This implies that it can create unexpected json file in default location (ie theme/acf-json).
		 *
		 * So we need to purge extra files  in case they already exists else we save our.
		 *
		 */

		foreach ( $this->location_list as $location ) {
			$_file = $location['value'] . '/' . $field_group['key'] . '.json';
			if ( $location['value'] !== $path && file_exists( $_file ) ) {
				@unlink( $_file );
			} else {
				// Append modified time.
				if ( isset( $field_group['ID'] ) ) {
					$field_group['modified'] = get_post_modified_time( 'U', true, $field_group['ID'] );
				} else {
					$field_group['modified'] = time();
				}

				// Prepare for export.
				$field_group = acf_prepare_field_group_for_export( $field_group );
				// Save content
				file_put_contents( $file, acf_json_encode( $field_group ) );
			}
		}

		return true;
	}

	/**
	 * acf/render_field_group_settings hook
	 *
	 * Clean the temp directory which contains the tmp json to sync
	 *  Hook called called the group field page is ended
	 *
	 * @since 2.0
	 *
	 */

	public function clean_dir(): void {

		// it's time to clean the tmp_ directory.
		if ( file_exists( $this->load_json ) ) {
			foreach (
				$scanned_directory = array_diff( scandir( $this->load_json ), [
					'..',
					'.'
				] ) as $file
			) {
				@unlink( $this->load_json . '/' . $file );
			}
			@rmdir( $this->load_json );
		}
	}

	/**
	 *
	 * acf/settings/load_json hook
	 *
	 * @param $paths
	 *
	 * @return array of paths with the right uri
	 *
	 * @since 2.0
	 *
	 */
	public function set_load_point( $paths ): array {

		$paths[0] = $this->load_json;

		return $paths;
	}

	/**
	 * acf/field_group/admin_head hook
	 *
	 * Adds the metabox  used to select a json location
	 *
	 * @since 2.0
	 *
	 */
	public function add_json_location_meta_box(): void {

		add_meta_box( 'acf-field-group-json-location', __( 'Json sync Settings', 'noleam' ), function () {

			global $field_group;

			$locations = [];
			foreach ( $this->location_list as $key => $location ) {
				$locations[ $key ] = $key;
			}

			// Form settings
			acf_render_field_wrap( array(
				'label'        => __( 'Json location', 'noleam' ),
				'name'         => $this->type,
				'prefix'       => 'acf_field_group',
				'type'         => 'select',
				'ui'           => 1,
				'instructions' => __( 'Select a location', 'noleam' ),
				'value'        => $field_group[ $this->type ] ?? $locations[ array_key_first( $locations ) ],
				'choices'      => $locations,
				'required'     => false,
			) ); ?>
			<?php
		}, 'acf-field-group', 'normal' );

	}

	/**
	 *
	 * manage_acf-field-group_posts_custom_column hook
	 *
	 * Add the Json location information in the 'JSON Local" column after the existing text.
	 *
	 * @param $column
	 * @param $post_id
	 */
	public function add_location_in_column( $column, $post_id ) {
		if ( $this->add_column ) {
			if ( $column === 'acf-json' ) {
				$post = get_post( $post_id );
				if ( $post ) {
					$field_group = (array) maybe_unserialize( $post->post_content );
					$file        = $this->get_file( $field_group );
					//We check if file exist (could be an imported value)...
					if ( isset( $field_group['json-location'] ) && file_exists( $this->get_path( $field_group ) ) ) {
						?>
                        <div>&blacktriangleright;&nbsp;<?= $field_group['json-location'] ?></div>
						<?php
					}
				}
			}
		}
	}
}

