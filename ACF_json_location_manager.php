<?php


/**
 *  ACF_json_location_manager
 *
 *  Version : 0.9
 *  Author : Christian Denat mail : christian.denat @orange.fr  twitter : @chdenat  github @chdenat
 *
 *  Feel free to use it freely, but with credits
 *
 *  The story :
 *
 *  When developers are using control version tools they need to use the ACF local json functionality
 * (see https://www.advancedcustomfields.com/resources/local-json/)
 *
 *  But the standard management allows only one directory to manage all the field groups to synchronize and
 *  that can be really annoying when you are working on plugins and theme on the same time or if you are using
 *  others ACF tool sthat already use this functionality.
 *
 *  The solution :
 *
 *  Now, with ACF_Json_location_manager, you just have to select, in a specific locations rule where you want to put
 *  your Json, and the hooks will do the trick !
 *
 *  by defaults, jsons dirs are named "acf-json" but this can be overload using args :
 *
 *       new ACF_json_location_manager(['dir'=>'any-name'])
 *
 *  For each plugin or theme (parent or child) you want to add json management, you just have to create the right
 *  sub directory in plugin or theme directory (same name for all).
 *
 *  ex :
 *     <plugin dir>
 *         !__ acf-json
 *         !_ others_dirs
 *
 *     <theme dir>
 *         !__ acf-json
 *         !_ others_dirs
 *
 */

namespace NOLEAM\ACF\UTILS;

use function get_plugins;

Class ACF_json_location_manager {

	private $json_dir;
	private $type = 'json_location';
	private $sync;

	function __construct( $args = null ) {

		// Settings

		$args = wp_parse_args( $args, [
			'dir'  => 'acf-json',
			'sync' => 'manual', //TODO add auto sync
		] );

		$this->json_dir = $args['dir'];
		$this->sync     = $args['sync'];

		// ACF hooks

		add_filter( 'acf/location/rule_types', [ $this, 'acf_location_rules_types' ] );
		add_filter( 'acf/location/rule_values/json_location', [ $this, 'acf_location_rules_values_json_location' ] );
		add_filter( 'acf/location/rule_match/json_location', [ $this, 'acf_location_rules_match_user' ], 10, 3 );
		add_filter( 'acf/settings/save_json', [ $this, 'acf_json_save_point' ] );
		add_filter( 'acf/settings/load_json', [ $this, 'acf_json_load_point' ] );
		add_filter( 'acf/location/rule_operators/json_location', [ $this, 'acf_location_rules_operators' ] );

		if ( 'manual' === $this->sync ) {
			add_filter( "acf/settings/load_json", [ $this, 'acf_json_load_all' ] );
		}

		// That's all

	}

	/**
	 *
	 * For our rule there's no choice so we define a more explicit text and delete the second.
	 *
	 * @param $choices
	 *
	 * @return array of one choice
	 */
	function acf_location_rules_operators( $choices ) {

		$choices       = [];
		$choices['--'] = 'saved in';

		return $choices;

	}

	/**
	 * @param $choices
	 *
	 * @return mixed
	 */

	function acf_location_rules_types( $choices ) {

		// If we have some possible acf-json location, we add the rules else , nothing changes

		if ( false !==
		     $this->get_json_locations() ) {
			$choices['JSON'][ $this->type ] = 'JSON file';
		}

		return $choices;
	}

	/**
	 * Check all activated plugins  and parent/child theme to see if they contain an 'acf-json' directory and then
	 * add to the rule type list with some additional infos.
	 *
	 * This implies that the 'acf-json' dirs had been created before in the plugins and/or themes.
	 *
	 * @return
	 *
	 * array : list of all json locations
	 *
	 *      for plugins :
	 *
	 *             $choices [<plugin-dir>/acf-json][text] : Text to be displayed + plugin info
	 *                                             [type] : plugin
	 *
	 *      for theme :
	 *            $choices ['child'|'parent'][text] : Text to be displayed + parent/child info
	 *                     ['child'|'parent'] : 'child'|'parent'*
	 *
	 * bool : false if the list is empty (ie no 'acf-json' in the project)
	 *
	 */

	private
	function get_json_locations() {

		$choices = [];
		$plugins = get_plugins();
		foreach ( $plugins as $uri => $plugin_data ) {
			$json_location = '/' . explode( '/', $uri )[0] . '/' . $this->json_dir;
			if ( is_dir( WP_PLUGIN_DIR . $json_location ) ) {

				$choices[ $json_location ] ['text'] = 'Plugin ' . $plugin_data['Title'];
				$choices[ $json_location ] ['type'] = 'plugin';
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
			$choices['child']['text'] = 'Theme ' . $name . ' (child)';
			$choices['child']['type'] = 'child';
		}

		//Parent or single theme
		if ( is_dir( $path = get_template_directory() . '/' . $this->json_dir ) ) {
			$choices['parent']['text'] = 'Theme ' . ( ( $child ) ? $parent : $name ) . ( ( $child ) ? ' (parent)' : '' );
			$choices['parent']['type'] = 'parent';
		}

		// Returns the array or false if empty
		return count( $choices ) ? $choices : false;
	}

	/**
	 *
	 * Build the selection list according to the available locations.
	 *
	 * @param $choices
	 *
	 * @return mixed
	 */

	function acf_location_rules_values_json_location( $choices ) {

		// we concatenate the type and the value (type#value) in order to decode them later

		$list = $this->get_json_locations();

		if ( false !== $list ) {
			foreach ( $list as $key => $item ) {
				$choices[ $this->add_hash( $item['type'], $key ) ] = $item['text'];
			}
		}

		return $choices;
	}

	/**
	 *
	 * Concatenate the 2 values with #
	 *
	 * @param $a
	 * @param $b
	 *
	 * @return string
	 */

	private function add_hash( $a, $b ) {
		return "$a#$b";
	}

	function acf_location_rules_match_json_location( $match, $rule, $options ) {
		//As it is a pseudo-rule we always return true, whatever the state of the world
		return true;
	}

	/**
	 *
	 * Hook when saving  field_group :
	 *
	 * According to  the user selection, we use the right path.
	 * To avoid doublons if the path has changed, we remove the group (if it is  existing) in other locations.
	 *
	 * @param $path
	 *
	 * @return the path or false
	 */

	function acf_json_save_point( $path ) {

		// update path

		$path = null;

		// We scan $_POST to see if the user has defined a "json location rule"

		if ( isset( $_POST['acf_field_group']['location'] ) ) {
			foreach ( $_POST['acf_field_group']['location'] as $group ) {
				foreach ( $group as $rule ) {
					if ( isset( $rule['value'] ) && $rule['param'] === $this->type ) {
						$path = $this->build_json_uri( $rule['value'] );
						break 2;
					}
				}
			}
		}

		// May be the location has changed (TODO see if it is possible to check it programatically)
		// So we check all other possible json-location and delete the old json if it exists.

		// In case  "json location rule" has been removed from the edit field group page, we remove the last
		// created json file

		$choices = $this->get_json_locations();
		if ( false !== $choices ) {
			foreach ( $choices as $key => $location ) {
				$uri  = $this->build_json_uri( $this->add_hash( $location['type'], $key ) );
				$file = $uri . '/' . $_POST['post_name'] . '.json';
				if ( $uri !== $path && file_exists( $file ) ) {
					@unlink( $file );
				}
			}
		}

		return ( $path !== null ) ? $path : false;

	}

	/**
	 *
	 * Build the right uri according to the 'standard path' and the context
	 *
	 * @param $path contains
	 *
	 * - parent#parent for a parent theme
	 * - child#child for a child theme
	 * - plugin#<plugin-uri> for a plugin
	 *
	 * @return string that contains the real json location path
	 */

	private function build_json_uri( $path ) {
		$info = explode( "#", $path );
		if ( count( $info ) === 2 ) {
			switch ( $info[0] ) {
				case 'child': // returns child path
					$path = get_stylesheet_directory() . '/' . $this->json_dir;
					break;
				case 'parent': // returns parent path
					$path = get_template_directory() . '/' . $this->json_dir;
					break;
				case 'plugin': // returns plugin path;
					$path = WP_PLUGIN_DIR . $info[1]; //TODO check if we can use WP_PLUGIN_DIR on each deployment
					break;
				default :
					$path = null;
			}
		}

		return $path;
	}

	/**
	 *
	 * Hook when loading the field group : we fire this hooks just in cae we are in the field group edition
	 *
	 * @param $paths
	 *
	 * @return $paths with the right uri
	 */
	function acf_json_load_point( $paths ) {

		// We fire this hook only if there is a post, we read it to check json-location and we push it as $path.

		// To check, we scan $_GET[post] to retrieve the post number then we unserialize the content

		if ( isset( $_GET['post'] ) ) {
			unset( $paths[0] );

			$content = maybe_unserialize( get_post( $_GET['post'] )->post_content );
			if ( isset( $content['location'][0] ) ) {
				foreach ( $content['location'][0] as $rule ) {
					if ( isset( $rule['value'] ) && $rule['param'] === $this->type ) {
						$path[] = $this->build_json_uri( $rule['value'] );
						break;
					}
				}
			}
		}

		return $paths;
	}

	/**
	 *
	 * Hook when loading all  field group : we fire this hooks just in case we are in the field_groups page
	 *
	 * We'll retrieve all the available json location to help synchronization.
	 *
	 * @param $paths
	 *
	 * @return array
	 */

	function acf_json_load_all( $paths ) {

		// We fire this hook only when we are on the acf_field_group page
		// We scan $_POST to retrieve the screen_id then we check its content
		//          $_POST['screen_id'] === 'edit-acf-field-group'

		if ( isset( $_POST['screen_id'] ) && 'edit-acf-field-group' === $_POST['screen_id'] ) {
			unset( $paths[0] );

			$locations = $this->get_json_locations();
			foreach ( $locations as $key => $location ) {
				$paths[] = $this->build_json_uri( $this->add_hash( $location['type'], $key ) );
			}
		}

		return $paths;
	}

}