<?php
/**
 * Plugin Name: Envato Child
 * Plugin URI: http://indienanni.github.io/wp-envato-child/
 * Description: WordPress Theme & Plugin integration for the Envato Market.
 * Version: 1.0.0
 * Author: Claudio Nanni
 * Author URI: http://indienanni.com/
 * Requires at least: 4.2
 * Tested up to: 4.4
 * Text Domain: envato-child
 * Domain Path: /languages/
 *
 * @package Envato_Market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Envato_Child' ) ) :

	/**
	 * It's the main class that does all the things.
	 *
	 * @class Envato_Market
	 * @version	1.0.0
	 * @since 1.0.0
	 */
	final class Envato_Child {

		/**
		 * The single class instance.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @var object
		 */
		private static $_instance = null;

		/**
		 * Plugin data.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @var object
		 */
		private $data;

		/**
		 * The slug.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @var string
		 */
		private $slug;

		/**
		 * The version number.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @var string
		 */
		private $version;

		/**
		 * The web URL to the plugin directory.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @var string
		 */
		private $plugin_url;

		/**
		 * The server path to the plugin directory.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @var string
		 */
		private $plugin_path;

		/**
		 * The web URL to the plugin admin page.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @var string
		 */
		private $page_url;

		/**
		 * The setting option name.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @var string
		 */
		private $option_name;
                
                
                /**
                 * È l'oggetto che si occupa di installare il multi-pacchetto
                 * 
                 * @var type MultiPackage
                 */
                private $multipackageObj;
                
                               
		/**
		 * Main Envato_Market Instance
		 *
		 * Ensures only one instance of this class exists in memory at any one time.
		 *
		 * @see Envato_Market()
		 * @uses Envato_Market::init_globals() Setup class globals.
		 * @uses Envato_Market::init_includes() Include required files.
		 * @uses Envato_Market::init_actions() Setup hooks and actions.
		 *
		 * @since 1.0.0
		 * @static
		 * @return object The one true Envato_Market.
		 * @codeCoverageIgnore
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
				self::$_instance->init_globals();
				self::$_instance->init_includes();
				self::$_instance->init_actions();
			}
			return self::$_instance;
		}

		/**
		 * A dummy constructor to prevent this class from being loaded more than once.
		 *
		 * @see Envato_Market::instance()
		 *
		 * @since 1.0.0
		 * @access private
		 * @codeCoverageIgnore
		 */
		private function __construct() {
			/* We do nothing here! */
		}

		/**
		 * You cannot clone this class.
		 *
		 * @since 1.0.0
		 * @codeCoverageIgnore
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'envato-child' ), '1.0.0' );
		}

		/**
		 * You cannot unserialize instances of this class.
		 *
		 * @since 1.0.0
		 * @codeCoverageIgnore
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'envato-child' ), '1.0.0' );
		}

		/**
		 * Setup the class globals.
		 *
		 * @since 1.0.0
		 * @access private
		 * @codeCoverageIgnore
		 */
		private function init_globals() {
			$this->data        = new stdClass();
			$this->version     = '1.0.0';
			$this->slug        = 'envato-child';
			$this->option_name = self::sanitize_key( $this->slug );
			$this->plugin_url  = plugin_dir_url( __FILE__ );
			$this->plugin_path = plugin_dir_path( __FILE__ );
			$this->page_url    = admin_url( 'admin.php?page=' . $this->slug );
			$this->data->admin = true;

			// Set the current version for the Github updater to use.
			if ( version_compare( get_option( 'envato_child_version' ), $this->version, '<' ) ) {
				update_option( 'envato_child_version', $this->version );
			}
		}

		/**
		 * Include required files.
		 *
		 * @since 1.0.0
		 * @access private
		 * @codeCoverageIgnore
		 */
		private function init_includes() {
                        require $this->plugin_path . '/inc/github.php';
			require $this->plugin_path . '/multipackage.php';
                        foreach (glob($this->plugin_path . '/packages/'. '*.php') as $file){
                            include_once( $file );
                        }
		}

		/**
		 * Setup the hooks, actions and filters.
		 *
		 * @uses add_action() To add actions.
		 * @uses add_filter() To add filters.
		 *
		 * @since 1.0.0
		 * @access private
		 * @codeCoverageIgnore
		 */
		private function init_actions() {
			// Activate plugin.
			register_activation_hook( __FILE__, array( $this, 'activate' ) );

			// Deactivate plugin.
			register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

			// Load the textdomain.
			add_action( 'init', array( $this, 'load_textdomain' ) );
                        
                        // Questo fa l'override del download (lo fa di tutto il pacchetto)
                        add_action( 'upgrader_package_options', array( $this, 'child_deferred_download' ), 98, 1  );
                        add_filter('upgrader_pre_install', array($this, 'child_upgrader_pre_install'), 9, 2 );
		}

		/**
		 * Activate plugin.
		 *
		 * @since 1.0.0
		 * @codeCoverageIgnore
		 */
		public function activate() {
			self::set_plugin_state( true );
		}

		/**
		 * Deactivate plugin.
		 *
		 * @since 1.0.0
		 * @codeCoverageIgnore
		 */
		public function deactivate() {
			self::set_plugin_state( false );
		}

		/**
		 * Loads the plugin's translated strings.
		 *
		 * @since 1.0.0
		 * @codeCoverageIgnore
		 */
		public function load_textdomain() {
			load_plugin_textdomain( 'envato-child', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Sanitize data key.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @param string $key An alpha numeric string to sanitize.
		 * @return string
		 */
		private function sanitize_key( $key ) {
			return preg_replace( '/[^A-Za-z0-9\_]/i', '', str_replace( array( '-', ':' ), '_', $key ) );
		}

		/**
		 * Recursively converts data arrays to objects.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @param array $array An array of data.
		 * @return object
		 */
		private function convert_data( $array ) {
			foreach ( (array) $array as $key => $value ) {
				if ( is_array( $value ) ) {
					$array[ $key ] = self::convert_data( $value );
				}
			}
			return (object) $array;
		}

		/**
		 * Set the `is_plugin_active` option.
		 *
		 * This setting helps determine context. Since the plugin can be included in your theme root you
		 * might want to hide the admin UI when the plugin is not activated and implement your own.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @param bool $value Whether or not the plugin is active.
		 */
		private function set_plugin_state( $value ) {
			self::set_option( 'is_plugin_active', $value );
		}

		/**
		 * Set option value.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @param string $name Option name.
		 * @param mixed  $option Option data.
		 */
		private function set_option( $name, $option ) {
			$options = self::get_options();
			$name = self::sanitize_key( $name );
			$options[ $name ] = esc_html( $option );
			update_option( $this->option_name, $options );
		}

		/**
		 * Return the option settings array.
		 *
		 * @since 1.0.0
		 */
		public function get_options() {
			return get_option( $this->option_name, array() );
		}

		/**
		 * Return a value from the option settings array.
		 *
		 * @since 1.0.0
		 *
		 * @param string $name Option name.
		 * @param mixed  $default The default value if nothing is set.
		 * @return mixed
		 */
		public function get_option( $name, $default = '' ) {
			$options = self::get_options();
			$name = self::sanitize_key( $name );
			return isset( $options[ $name ] ) ? $options[ $name ] : $default;
		}

		/**
		 * Set data.
		 *
		 * @since 1.0.0
		 *
		 * @param string $key Unique object key.
		 * @param mixed  $data Any kind of data.
		 */
		public function set_data( $key, $data ) {
			if ( ! empty( $key ) ) {
				if ( is_array( $data ) ) {
					$data = self::convert_data( $data );
				}
				$key = self::sanitize_key( $key );
				$this->data->$key = $data;
			}
		}

		/**
		 * Get data.
		 *
		 * @since 1.0.0
		 *
		 * @param string $key Unique object key.
		 * @return string|object
		 */
		public function get_data( $key ) {
			return isset( $this->data->$key ) ? $this->data->$key : '';
		}

		/**
		 * Return the plugin slug.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function get_slug() {
			return $this->slug;
		}

		/**
		 * Return the plugin version number.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function get_version() {
			return $this->version;
		}

		/**
		 * Return the plugin URL.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function get_plugin_url() {
			return $this->plugin_url;
		}

		/**
		 * Return the plugin path.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function get_plugin_path() {
			return $this->plugin_path;
		}

		/**
		 * Return the plugin page URL.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function get_page_url() {
			return $this->page_url;
		}

		/**
		 * Return the option settings name.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function get_option_name() {
			return $this->option_name;
		}

		/**
		 * Admin UI class.
		 *
		 * @since 1.0.0
		 *
		 * @return Envato_Market_Admin
		 */
		public function admin() {
			return Envato_Market_Admin::instance();
		}

		/**
		 * Envato API class.
		 *
		 * @since 1.0.0
		 *
		 * @return Envato_Market_API
		 */
		public function api() {
			return Envato_Market_API::instance();
		}

		/**
		 * Items class.
		 *
		 * @since 1.0.0
		 *
		 * @return Envato_Market_Items
		 */
		public function items() {
			return Envato_Market_Items::instance();
		}
                
             		/**
		 * Defers building the API download url until the last responsible moment to limit file requests.
		 *
		 * Filter the package options before running an update.
		 *
		 * @since 1.0.0
		 *
		 * @param array $options {
		 *     Options used by the upgrader.
		 *
		 *     @type string $package                     Package for update.
		 *     @type string $destination                 Update location.
		 *     @type bool   $clear_destination           Clear the destination resource.
		 *     @type bool   $clear_working               Clear the working resource.
		 *     @type bool   $abort_if_destination_exists Abort if the Destination directory exists.
		 *     @type bool   $is_multi                    Whether the upgrader is running multiple times.
		 *     @type array  $hook_extra                  Extra hook arguments.
		 * }
		 */
		function child_deferred_download( $options ) {
                        $package = $options['package'];
                        if( false !== strrpos( $package, 'deferred_download' ) &&
                            false !== strrpos( $package, 'item_id' ) &&
                            array_key_exists("theme", $options["hook_extra"])) {
                                $themeName = $options["hook_extra"]["theme"];
                                if(class_exists('MultiPackage_' . $themeName)){
                                    parse_str( parse_url( $package, PHP_URL_QUERY ), $vars );
                                    if ( $vars['item_id'] ) {
                                        $args = $this->admin()->set_bearer_args( $vars['item_id'] );
                                        $options['package'] = $this->download( $vars['item_id'], $args );
                                        $r = new ReflectionClass('MultiPackage_' . $themeName);
                                        $this->multipackageObj = $r->newInstanceArgs(array($themeName));
                                    }
                            }
                        }                        
                        return $options;
		}
                
                /**
                 * Get the item download.
                 *
                 * @since 1.0.0
                 *
                 * @param  int   $id The item ID.
                 * @param  array $args The arguments passed to `wp_remote_get`.
                 * @return bool|array The HTTP response.
                 */
                public function download( $id, $args = array() ) {
                        if ( empty( $id ) ) {
                                return false;
                        }

                        $url = 'https://api.envato.com/v2/market/buyer/download?item_id=' . $id . '&shorten_url=true';
                        $response = $this->api()->request( $url, $args );

                        // @todo Find out which errors could be returned & handle them in the UI.
                        if ( is_wp_error( $response ) || empty( $response ) || ! empty( $response['error'] ) ) {
                                return false;
                        }

                        if ( ! empty( $response['wordpress_theme'] ) ) {
                            if( ! empty( $response['download_url'] ) ){
                                return $response['download_url'];                                
                            } else {
                                return $response['wordpress_theme'];
                            }
                        }

                        if ( ! empty( $response['wordpress_plugin'] ) ) {
                                return $response['wordpress_plugin'];
                        }

                        return false;
                }

                /**
                 * Aggancia la selezione della sorgente del pacchetto
                 * 
                 * @param type $result
                 * @param type $hook_extra
                 * @return boolean
                 */
                function child_upgrader_pre_install($result, $hook_extra )
                {
                    if( isset($this->multipackageObj)){
                        add_filter('upgrader_source_selection', array($this, 'upgrader_source_selection') );                     
                    }
                    return true;
                }

                /**
                 * Prepara le info sui pacchetti disponibili aggiungendo
                 * quelli salvati nella direcotry temporanea
                 * 
                 * @param type $value
                 * @return type
                 */
                function set_site_transient_update_plugins($value)
                {
                    if( !isset($value->response) || !isset($this->multipackageObj)){
                        return $value;
                    }
                    
                    $value = $this->multipackageObj->set_nestedplugins_package_path($value);
                                        
                    remove_filter('site_transient_update_plugins', array($this, 'set_site_transient_update_plugins') );
                                        
                    return $value;
                }
                
                /**
                 * Disabilita il sistema di controllo della licenza nel download dei nested plugin
                 * 
                 * @param type $reply
                 * @param type $package
                 * @param type $updater
                 */
                public function preUpgradeFilter( $reply, $package, $updater ) {
                    return false;
                }
                
                
                /**
                 * Effettua l'aggiornamento di tutti i plugin
                 * 
                 * @global WP_Filesystem_Base $wp_filesystem
                 * @param type $source
                 * @param type $remote_source
                 * @param type $sender
                 * @param type $args
                 * @return type
                 */
                function upgrader_source_selection($source, $remote_source, $sender, $args = array())
                {  
                    if(!isset($this->multipackageObj)){
                        return $source;
                    }
                    
                    $source = $this->multipackageObj->child_upgrade_nested_plugins($source);
          
                    remove_filter('upgrader_source_selection', array($this, 'upgrader_source_selection') );
                    
                    // Qui finisce tutto il giro
                    $this->multipackageObj=null;
                    unset($this->multipackageObj);

                    return $source;
                }

	}

endif;

if ( ! function_exists( 'envato_child' ) ) :
	/**
	 * The main function responsible for returning the one true
	 * Envato_Market Instance to functions everywhere.
	 *
	 * Use this function like you would a global variable, except
	 * without needing to declare the global.
	 *
	 * Example: <?php $envato_market = envato_market(); ?>
	 *
	 * @since 1.0.0
	 * @return Envato_Market The one true Envato_Market Instance
	 */
	function envato_child() {
		return Envato_Child::instance();
	}
endif;

/**
 * Loads the main instance of Envato_Market to prevent
 * the need to use globals.
 *
 * @since 1.0.0
 * @return object Envato_Market
 */
add_action( 'after_setup_theme', 'envato_child', 12 );
