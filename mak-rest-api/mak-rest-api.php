<?php
/*
Plugin Name: Media Assembly Kit JSON REST API
Version: 0.2.1
Description: WP JSON REST API extension
Author: wokamoto
Author URI: https://www.digitalcube.jp/
Plugin URI: https://www.digitalcube.jp/
Text Domain: mak_rest_api
Domain Path: /languages
*/

require_once(dirname(__FILE__).'/includes/class-mak-rest-api.php');

$mak_rest_api = MAK_REST_API::get_instance();

// regist routes
add_action( 'wp_json_server_before_serve', function() {
	$mak_rest_api = MAK_REST_API::get_instance();
	add_filter( 'json_endpoints', array( $mak_rest_api, 'register_routes' ) );
});

// Nginx Caching
add_action( 'wp_json_server_before_serve', array( $mak_rest_api, 'nginx_cache_controle' ) );
add_filter( 'nginxchampuru_get_post_type', array( $mak_rest_api, 'nginxchampuru_get_post_type' ) );
add_filter( 'nginxchampuru_get_post_id',   array( $mak_rest_api, 'nginxchampuru_get_post_id' ) );

// json rest api bug fix
add_filter( 'json_prepare_term', array( $mak_rest_api, 'json_prepare_term'), 10, 2 );
add_action( 'plugins_loaded', function(){
    remove_action( 'deprecated_function_run', 'json_handle_deprecated_function', 10 );
    remove_action( 'deprecated_argument_run', 'json_handle_deprecated_argument', 10 );
});
