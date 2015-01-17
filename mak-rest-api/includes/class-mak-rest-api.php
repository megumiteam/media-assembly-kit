<?php

class MAK_REST_API {
	const THEME_OPTION_GROUP  = 'mak_theme_options';
	const MAKAD_PREFIX        = 'mak_ad_';

	private static $instance;

	private function __construct() {}

	public static function get_instance() {
		if( !isset( self::$instance ) ) {
			$c = __CLASS__;
			self::$instance = new $c();
		}
		return self::$instance;
	}

	// Nginx Cache Controle
	public function nginx_cache_controle() {
		// nginx reverse proxy cache
		if ( class_exists('NginxChampuru_FlushCache') ){
			$ncf = NginxChampuru_FlushCache::get_instance();
			$ncf->template_redirect();
		}
		if ( class_exists('NginxChampuru_Caching') ){
			$ncc = NginxChampuru_Caching::get_instance();
			$ncc->template_redirect();
		}
	}

	private function is_singular() {
		global $wp_query;
		if ( !isset($_SERVER['REQUEST_URI']) )
			return false;

		$preg_pattern = '#^/wp-json/('.
			implode('|', array(
				'posts',
				'mak_themeoption',
			))
			.')/(?<id>[\d]+)#';
		if ( preg_match($preg_pattern, $_SERVER['REQUEST_URI'], $matches) ) {
			$id = $matches['id'];
		} else {
			$id = false;
		}
		unset($matches);
		if ( $id ) {
			$wp_query->is_singular = true;
		}

		return $id;
	}

	public function nginxchampuru_get_post_type( $post_type ) {
		if ( $this->is_singular() ) {
			$post_type = 'is_singular';
		}
		return $post_type;
	}

	public function nginxchampuru_get_post_id( $post_id ) {
		if ( $id = $this->is_singular() ) {
			$post_id = $id;
		}
		return $post_id;
	}

	// patch for json rest api term
	public function json_prepare_term( $data, $term ) {
		if ( $data['ID'] !== intval($term->term_id) )
			$data['ID'] = intval($term->term_id);
		return $data;
	}

	// regist path
	public function register_routes( $routes ) {

		// menus
		$routes['/mak_menu'] = array(
			array( array( $this, 'get_nav_menus'), WP_JSON_Server::READABLE ),
		);
		$routes['/mak_menu/(?P<theme_location>.+)'] = array(
			array( array( $this, 'get_wp_nav_menu'), WP_JSON_Server::READABLE ),
		);

		// sidebars
		$routes['/mak_sidebar'] = array(
			array( array( $this, 'get_sidebars_widgets'), WP_JSON_Server::READABLE ),
		);
		$routes['/mak_sidebar/(?P<index>.+)'] = array(
			array( array( $this, 'dynamic_sidebar'), WP_JSON_Server::READABLE ),
		);

		// theme option
		$routes['/mak_themeoption/?(?P<post_id>[\d]+)?'] = array(
			array( array( $this, 'get_theme_options'), WP_JSON_Server::READABLE ),
		);
		$routes['/mak_themeoption/(?P<option_name>[^/]+)/?(?P<post_id>[\d]+)?'] = array(
			array( array( $this, 'get_theme_option'), WP_JSON_Server::READABLE ),
		);

		// ad
		$routes['/mak_adoption'] = array(
			array( array( $this, 'get_mak_ad_options'), WP_JSON_Server::READABLE ),
		);
		$routes['/mak_adoption/(?P<option_name>.+)'] = array(
			array( array( $this, 'get_mak_adoption_option'), WP_JSON_Server::READABLE ),
		);

		// pickup
		$routes['/mak_pickup'] = array(
			array( array( $this, 'get_mak_pickup'), WP_JSON_Server::READABLE ),
		);

		// Related Post
		$routes['/mak_related/(?P<device>pc|mobile)/(?P<id>\d+)'] = array(
			array( array( $this, 'get_related'), WP_JSON_Server::READABLE ),
		);

		// adjacent_posts_rel_link
		$routes['/posts/(?P<id>\d+)/(?P<adjacent>prev|next)'] = array(
			array( array( $this, 'adjacent_posts_rel_link'), WP_JSON_Server::READABLE ),
		);

		// slide post list
		$routes['/mak_slide'] = array(
			array( array( $this, 'get_slide_post_list'), WP_JSON_Server::READABLE ),
		);

		// category tab
		$routes['/mak_cat_tab/(?P<device>pc|mobile)'] = array(
			array( array( $this, 'get_category_post_list'), WP_JSON_Server::READABLE ),
		);

		return $routes;
	}

	// menus
	public function get_nav_menus( $_headers ) {
		global $wpdb;
		$sql = $wpdb->prepare(
			"SELECT t.term_id as ID, t.name, t.slug
			 FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			 where tt.taxonomy = %s",
			 'nav_menu');
		$menus = $wpdb->get_results($sql);
		return $menus;
	}

	public function get_wp_nav_menu( $theme_location, $_headers ) {
		$menu_items = wp_nav_menu(array(
			'theme_location'  => $theme_location,
			'echo'            => false,
			'fallback_cb'     => '',
			'container'       => '',
		));
		return array('name' => $theme_location, 'content' => $menu_items);
	}

	// sidebars
	public function get_sidebars_widgets( $_headers = array() ) {
		return wp_get_sidebars_widgets();
	}

	public function dynamic_sidebar( $index, $_headers ) {
		$sidebars = $this->get_sidebars_widgets();
		if ( !isset($sidebars[$index]) )
			return new WP_Error( 'mak_rest_api_sidebar_invalid_index', __( 'Invalid index.' ), array( 'status' => 400 ) );

		ob_start();
		dynamic_sidebar( $index );
		$content = ob_get_contents();
		ob_end_clean();
		return array( 'name' => $index, 'content' => $content );
	}

	// theme option
	private function get_theme_options_name() {
		static $mak_theme_options;
		global $new_whitelist_options;

		if ( isset($mak_theme_options) )
			return $mak_theme_options;

		$new_whitelist_options_old = $new_whitelist_options;
		if (function_exists('mak_register_setting'))
			mak_register_setting();
		$mak_theme_options =
			isset($new_whitelist_options[self::THEME_OPTION_GROUP])
			? $new_whitelist_options[self::THEME_OPTION_GROUP]
			: array();
		$mak_theme_options =
			isset($new_whitelist_options[self::THEME_OPTION_GROUP])
			? $new_whitelist_options[self::THEME_OPTION_GROUP]
			: array();
		return $mak_theme_options;
	}

	public function get_theme_options( $post_id = '', $_headers = array() ) {
		$mak_theme_options_key = $this->get_theme_options_name();
		$mak_theme_options = array();
		foreach( $mak_theme_options_key as $option_name ) {
			$value = $this->get_theme_option( $option_name );
			if ( ! is_wp_error($value) )
				$mak_theme_options[] = $value;
		}
		return $mak_theme_options;
	}

	public function get_theme_option( $option_name, $post_id = '', $_headers = array() ) {
		global $posts, $post;
		static $mak_theme_options_key;

		if ( !isset($mak_theme_options_key) ) {
			$mak_theme_options_key = $this->get_theme_options_name();
			if (function_exists('mak_register_setting'))
				mak_register_setting();
			if (function_exists('mak_theme_options_fields'))
				mak_theme_options_fields();
		}

		if ( !in_array($option_name ,$mak_theme_options_key) )
			return new WP_Error( 'mak_rest_api_themeoption_invalid_option_name', sprintf(__( 'Invalid option name.' ).' : %s', $option_name), array( 'status' => 400 ) );

		$value = get_option($option_name);

		return array('name' => $option_name, 'value' => $value);
	}

	// ad
	private function get_mak_ad_options_name() {
		static $option_names;
		global $wpdb;

		if ( isset($option_names) )
			return $option_names;

		$sql = $wpdb->prepare(
			"SELECT option_name
			 FROM {$wpdb->options}
			 where option_name like %s",
			 self::MAKAD_PREFIX.'%');
		$option_names = $wpdb->get_col($sql);
		return $option_names ? $option_names : array();
	}

	public function get_mak_ad_options( $_headers = array() ) {
		$mak_ad_options_key = $this->get_mak_ad_options_name();
		$mak_ad_options = array();
		foreach( $mak_ad_options_key as $option_name ) {
			$value = $this->get_mak_ad_option( $option_name );
			if ( ! is_wp_error($value) )
				$mak_ad_options[] = $value;
		}
		return $mak_ad_options;
	}

	public function get_mak_ad_option( $option_name, $_headers = array() ) {
		static $mak_ad_options;
		if ( !isset($mak_ad_options) )
			$mak_ad_options = $this->get_mak_ad_options_name();
		if ( !in_array($option_name, $mak_ad_options) ) {
			return new WP_Error( 'mak_rest_api_ad_invalid_option_name', __( 'Invalid option name.' ), array( 'status' => 400 ) );
		}
		return array('name' => $option_name, 'value' => get_option($option_name));
	}

	// pickup
	public function get_mak_pickup( $_headers ) {
		if ( !function_exists('mak_get_pickup'))
			return new WP_Error( 'mak_rest_api_pickup', __( 'Function mak_get_pickup() is not exists.' ), array( 'status' => 400 ) );
		$content = mak_get_pickup();
		return array('content' => $content ? $content : '');
	}

	// Related Post
	public function get_related( $device, $id, $_headers ) {
		global $posts, $post;
		$id = intval($id);
		if ( !function_exists('mak_get_related_post_list'))
			return new WP_Error( 'mak_rest_api_related', __( 'Function mak_get_related_post_list() is not exists.' ), array( 'status' => 400 ) );
		$post = get_post($id);
		$posts = array($post);
		$content = mak_get_related_post_list( array( 'device' => $device ) );
		return array('post_id' => $id, 'content' => $content ? $content : '');
	}

	// adjacent_posts_rel_link
	public function adjacent_posts_rel_link( $id, $adjacent, $_headers ) {
		global $posts, $post;
		$id = intval($id);
		$post = get_post($id);
		$posts = array($post);

		$current_post = $post;
		if ( $current_post )
			$current_post->permalink = get_permalink($current_post->ID);

		$in_same_term = false;
		$excluded_terms = '';
		$previous = ('prev' === $adjacent);

		if ( $previous && is_attachment() && $post )
			$post = get_post( $post->post_parent );
		else
			$post = get_adjacent_post( $in_same_term, $excluded_terms, $previous );

		if ($post)
			$post->permalink = get_permalink($post->ID);

		return array('current' => $current_post, $adjacent => $post);
	}

	// slide post list
	public function get_slide_post_list( $_headers ) {
		$content = '';
		if ( !function_exists('mak_get_slide_post_list'))
			return new WP_Error( 'mak_rest_api_slide', __( 'Function mak_get_slide_post_list() is not exists.' ), array( 'status' => 400 ) );
		$content = mak_get_slide_post_list();
		return array('content' => $content ? $content : '');
	}

	// category tab
	public function get_category_post_list( $device, $_headers) {
		$content = '';
		switch ($device) {
/*
		case 'pc':
			if ( !function_exists('mak_get_category_induction_post_list'))
				return new WP_Error( 'mak_rest_api_cat_tab', __( 'Function mak_get_category_induction_post_list() is not exists.' ), array( 'status' => 400 ) );
			$content = mak_get_category_induction_post_list();
			break;
*/
		case 'mobile':
			if ( !function_exists('mak_get_category_posts_tab'))
				return new WP_Error( 'mak_rest_api_cat_tab', __( 'Function mak_get_category_posts_tab() is not exists.' ), array( 'status' => 400 ) );
			$content = mak_get_category_posts_tab();
			break;
		default:
			return new WP_Error( 'mak_rest_api_cat_tab', __( 'Invalid device.' ), array( 'status' => 400 ) );
		}
		return array('content' => $content ? $content : '');
	}

}