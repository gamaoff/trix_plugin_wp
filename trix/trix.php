<?php
/**
 * Plugin Name: Trix
 * Plugin URI: http://trix.rocks
 * Description: Provides integration with Trix platform.
 * Version: 1.0
 * Author: trix.rocks
 * Author URI: http://trix.rocks
 * Text Domain: trix
 * Domain Path: Optional. Plugin's relative directory path to .mo files. Example: /locale/
 * License: GPL2
 */

if(!defined('ABSPATH'))	exit();

if(!function_exists('get_user_by')) require_once(ABSPATH . "wp-includes/pluggable.php");

if(!class_exists('Trix')){

	class Trix {

		private $options;
		private $status;
		private $error_message;

		public function __construct(){
			$this->options = get_option('trix_plugin_options');
			$this->status = get_option('trix_plugin_response["code"]');
			$this->error_message = get_option('trix_plugin_response["message"]');
		}

		public function trix_admin_init() {
			register_setting('trix_plugin_options', 'trix_plugin_options', array($this, 'validate_trix_settings'));
			add_settings_section('trix_main_section', 'Trix Integration', array($this, 'options_main_section_cb'),__FILE__);
			add_settings_field('options_token', 'Token', array($this, 'options_token_setting'),__FILE__,'trix_main_section');
			add_settings_field('options_url', 'Url', array($this, 'options_url_setting'),__FILE__,'trix_main_section');
			add_settings_field('options_status', 'Status', array($this, 'options_status_setting'),__FILE__,'trix_main_section');
		}

		public function add_menu(){
			add_options_page('Trix','Trix','manage_options',__FILE__,array($this,"add_menu_page"));
		}

		public function add_menu_page(){
			wp_enqueue_style('trix');
			?>
			<div class="wrap" id="bbuinfo_config">
				<?php screen_icon(); ?> <h2>Trix Options</h2>
				<?php settings_errors(); ?>
				<form action="options.php" name="trix_config_form" method="post">
					<?php 
					settings_fields('trix_plugin_options');
					do_settings_sections(__FILE__); 
					submit_button('Send Request', 'primary', 'submitData');
					?>
			  	</form>
			</div>
			<?php

		}
		public function options_token_setting(){
			echo "<input name='trix_plugin_options[options_token]' type='text' />";
		}
		public function options_url_setting(){
			echo "<input name='trix_plugin_options[options_url]' type='text' />";
		}
		public function options_status_setting(){
			if($this->error_message==''){
				switch ($this->status) {
					case 200:
						echo "All set!";
						break;
					case 401:
						echo "Access unauthorized!";
						break;
					default:
						"Not integrated!";
						break;
				}
			}else echo $this->error_message;
		}

		public function options_main_section_cb(){}

		public function validate_trix_settings($plugin_options){
			$token_sanitized = sanitize_text_field($plugin_options['options_token']);
			if(!preg_match('/^[a-f0-9]{32}$/', $token_sanitized)) {
				$plugin_options['options_token'] = '';
			}
			return $plugin_options;
		}
		
		public function send_trix_data(){
			$pass = sha1(microtime());
		  	$user_id = wp_create_user( 'trix_user', $pass);
		  	$user = new WP_User($user_id);
			$user->remove_role( 'subscriber' );
			$user->add_role( 'editor' );

			$url = esc_url($_POST['trix_plugin_options']['options_url']);

 			$user = get_user_by('login','trix_user');
			$args_data = array("user" => 'trix_user',
			    					"password" => $pass,
									"domain" => get_site_url().'/wp-json',
									"token" => $_POST['trix_plugin_options']['options_token']);

  			$data = json_encode($args_data);

  			$args = array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => $data );
  			$response = null;
			$response = wp_remote_post(esc_url_raw($url), $args);

			if ( is_wp_error( $response ) ) {			   
				update_option('trix_plugin_response["message"]', $response->get_error_message());
			} else {
				update_option('trix_plugin_response["code"]', $response['response']['code']);
				update_option('trix_plugin_response["message"]', '');
			}				
		}
	}
}

$trix = new Trix;

if(isset($_POST['submitData'])){
	$trix->send_trix_data();
}

//----------------------------------------------------------------

function trix_deactivate(){
	$user = get_user_by( 'login', 'trix_user' );
	if($user) wp_delete_user($user->data->ID);

}
register_deactivation_hook( __FILE__, 'trix_deactivate' );

function trix_activate() {

  	add_option( 'Activated_Plugin', 'trix' );

}
register_activation_hook( __FILE__, 'trix_activate' );

function trix_init() {
	if(!function_exists( 'json_api_init' ) ) include_once(dirname(__FILE__).'/json-rest-api/plugin.php');
	if(!function_exists( 'json_basic_auth_handler' ) ) include_once(dirname(__FILE__).'/json-rest-api/basic-auth.php');
}
add_action( 'plugins_loaded', 'trix_init' );

add_action('admin_init', array($trix,'trix_admin_init'));
add_action('admin_menu', array($trix,'add_menu'));

