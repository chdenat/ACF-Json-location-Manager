<?php


/**
 * ACF_json_location_manager
 *
 * version : 1.0
 * author : Christian Denat
 * mail : christian.denat @orange.fr
 * twitter : @chdenat
 * github @chdenat
 *
 *  Feel free to use it freely for personal or commercial use.
 *  Credits will be appreciated.
 *
 */

namespace NOLEAM\ACF\UTILS;

use function get_plugins;

Class ACF_json_location_manager {

	/**
	 * @var ACF_json_location_manager
	 */
	private static $_instance;

	/**
	 * @var string
	 */
	private $json_dir;
	/**
	 * @var string
	 */
	private $type = 'json-location';
	/**
	 * @var array of string
	 */
	private $location_list;
	/**
	 * @var
	 */
	private $post;
	/**
	 * @var string
	 */
	private $local_json;
	/**
	 * @var mixed
	 */
	private $tmp_dir;
	/**
	 * @var boolean
	 */
	private $auto_sync;
	/**
	 * @var const array of string
	 */
	private const available_options = [ 'dir', 'tmp', 'sync' ];

	/**
	 * ACF_json_location_manager constructor.
	 *
	 * @param null $args
	 */
	function __construct( $args = null ) {

		// Settings

		$args = wp_parse_args( $args, [
			'dir'       => 'acf-json',  // name of the json location
			'tmp'       => 'tmp',       // prefix for load json ) <tmp>_<dir>
			'sync' => false             // set to manual
		] );

		$this->settings( $args );

		$this->set_json_locations();
		$this->set_local_json();

		// ACF hooks
		add_filter( 'acf/settings/save_json', [ $this, 'acf_json_save_point' ] );
		add_filter( 'acf/settings/load_json', [ $this, 'acf_json_load_point' ] );

		// Add a meta box for Json locations
		add_action( 'acf/field_group/admin_head', [ $this, 'json_sync_group_settings' ] );

		// Remove tmp file and dir.
		add_action( 'acf/render_field_group_settings', [ $this, 'acf_clean_dir' ] );

		// That's all
	}

	/**
	 * Get all possible json locations in plugin or theme (parant+child)
	 *
	 * @since 1.0
	 */
	private
	function set_json_locations(): void {

		$this->location_list = [];

		// Check inside plugins
		if ( ! function_exists( 'get_plugins' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		$plugins = get_plugins();

		foreach ( $plugins as $uri => $plugin_data ) {
			$json_location = '/' . explode( '/', $uri )[0] . '/' . $this->json_dir;
			if ( is_dir( $path = WP_PLUGIN_DIR . $json_location ) ) {
				$label                          = 'Plugin ' . $plugin_data['Title'];
				$this->location_list [ $label ] = [
					'type'  => 'plugin',
					'value' => $path,
				];
			}
		}

		// Check the theme (and it's parent if it is a child) and add it to the selection list if it contains an 'acf-json' dir
		$theme  = wp_get_theme();
		$parent = $theme->get( 'Template' );
		$name   = $theme->get( 'Name' );

		// Child theme or parent ?
		$child = ( ! empty( $parent ) );

		//Child theme
		if ( $child && is_dir( $path = get_stylesheet_directory() . '/' . $this->json_dir ) ) {
			$label                          = 'Theme ' . $name . ' (child)';
			$this->location_list [ $label ] = [
				'type'  => 'child',
				'value' => $path,
			];
		}

		//Parent or single theme
		if ( is_dir( $path = get_template_directory() . '/' . $this->json_dir ) ) {
			$label                          = 'Theme ' . $name . ( ( $child ) ? ' (parent)' : '' );
			$this->location_list [ $label ] = [
				'type'  => ( $child ) ? 'parent' : 'theme',
				'value' => $path,
			];
		}
	}

	/**
	 * Copy all the json file found to a sub directory of upload directory
	 *
	 * @since 1.0
	 */
	private function set_local_json(): void {
		$this->local_json = wp_get_upload_dir()['basedir'] . '/' . $this->tmp_dir . '-' . $this->json_dir;

		if ( ! file_exists( $this->local_json ) ) {
			if ( ! mkdir( $concurrentDirectory = $this->local_json ) && ! is_dir( $concurrentDirectory ) ) {
				throw new \RuntimeException( sprintf( 'Directory "%s" was not created', $concurrentDirectory ) );
			}
		}
		if ( is_dir( $this->local_json ) ) {
			foreach ( $this->location_list as $location ) {
				foreach (
					$scanned_directory = array_diff( scandir( $location['value'] ), [
						'..',
						'.'
					] ) as $file
				) {
					copy( $location['value'] . '/' . $file, $this->local_json . '/' . $file );
				}
			}
		}
	}

	/**
	 * Unic instantiation
	 *
	 * @return ACF_json_location_manager
	 * @since 1.0
	 */
	public static function getInstance(): ACF_json_location_manager {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new ACF_json_location_manager();
		}

		return self::$_instance;
	}

	/**
	 * Settings
	 *
	 * @param null $options
	 */
	public function settings( $options = null ) : void {
		if ( $options !== null ) {
			foreach ( $options as $option ) {
				switch ( $option ) {
					case 'mode':
						$this->auto_sync = $options['sync'];
						break;
					case 'tmp' :
						$this->json_dir = $options['dir'];
						break;
					case 'dir':
						$this->tmp_dir = $options['tmp'];
						break;
					default :
				}
			}
		}
	}

	/**
	 * @return const
	 */
	private static function getAvailableOptions(): array {
		return self::available_options;
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
	 * @since 0.1
	 */
	public function acf_json_save_point( $path ) {

		// We scan $_POST
		$location = $_POST['acf_field_group'][ $this->type ];

		if ( isset( $location ) && isset( $this->location_list[ $location ] ) ) {
			$path = $this->location_list[ $location ]['value'];

			// In case the location has  changed, we remove the former json file.
			// We scan all possible locations an remove file if not the current
		}
		if ( null !== $path ) {
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
	 * acf/render_field_group_settings hook
	 *
	 * Clean the temp directory which contains the tmp json to sync
	 *  Hook called called the group field page is ended
	 *
	 * @since 1.0
	 */
	public function acf_clean_dir(): void {

		// it's time to clean the tmp_ directory.
		if ( file_exists( $this->local_json ) ) {
			foreach (
				$scanned_directory = array_diff( scandir( $this->local_json ), [
					'..',
					'.'
				] ) as $file
			) {
				@unlink( $this->local_json . '/' . $file );
			}
			@rmdir( $this->local_json );
		}
	}

	/**
	 *
	 * acf/settings/load_json hook
	 *
	 * @param $paths
	 *
	 * @return array of paths with the right uri
	 * @since 0.1
	 */
	public function acf_json_load_point( $paths ) {

		$paths[0] = $this->local_json;

		return $paths;
	}

	/**
	 * acf/field_group/admin_head hook
	 *
	 * Adds the metabox  used to select json location
	 *
	 * @since 1.0
	 */
	public
	function json_sync_group_settings(): void {

		add_meta_box( 'acf-field-group-json-location', __( 'Json sync Settings', 'noleam' ), function () {

			global $field_group;

			$locations = [ '0' => __( 'none' ) ];
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
				'value'        => $field_group[ $this->type ],
				'choices'      => $locations,
				'required'     => false,
			) ); ?>
			<?php
		}, 'acf-field-group', 'normal' );

	}
}

ACF_json_location_manager::getInstance();
