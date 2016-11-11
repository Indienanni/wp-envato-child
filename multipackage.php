<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'MultiPackage' ) ) :
    abstract class MultiPackage {		
            
            protected $themeName;

            /**
             * Lista dei plugin embedded
             * 
             * @var type array
             */
            protected $nestedPackages;

            /**
             * È elenco dei plugin con il loro pathname 
             * 
             * @var type array
             */
            protected $nestedPluginsPackage;

            /**
             * È il tema della distribuzione con il suo pathname 
             * 
             * @var type array
             */
            protected $nestedThemePackage;

            /**
             * È l'elenco dei plugin con il package
             * 
             * @var type array()
             */
            protected $pluginsToUpdate;

            /**
             * Costruttore
             * 
             * @param type $name
             */
            function __construct($name) {
                $this->themeName = $name;
            }        
            
            /**
             * Unpack a compressed package file.
             *
             * @since 2.8.0
             * @access public
             *
             * @global WP_Filesystem_Base $wp_filesystem Subclass
             *
             * @param string $package        Full path to the package file.
             * @param bool   $delete_package Optional. Whether to delete the package file after attempting
             *                               to unpack it. Default true.
             * @return string|WP_Error The path to the unpacked contents, or a {@see WP_Error} on failure.
             */
            public function unpack_package( $package, $delete_package = true ) {
                    global $wp_filesystem;

                    $upgrade_folder = $wp_filesystem->wp_content_dir() . 'upgrade/';

                    //Clean up contents of upgrade directory beforehand.
                    $upgrade_files = $wp_filesystem->dirlist($upgrade_folder);
                    if ( !empty($upgrade_files) ) {
                            foreach ( $upgrade_files as $file )
                                    $wp_filesystem->delete($upgrade_folder . $file['name'], true);
                    }

                    // We need a working directory - Strip off any .tmp or .zip suffixes
                    $working_dir = $upgrade_folder . basename( basename( $package, '.tmp' ), '.zip' );

                    // Clean up working directory
                    if ( $wp_filesystem->is_dir($working_dir) )
                            $wp_filesystem->delete($working_dir, true);

                    // Unzip package to working directory
                    $result = unzip_file( $package, $working_dir );

                    // Once extracted, delete the package if required.
                    if ( $delete_package )
                            unlink($package);

                    if ( is_wp_error($result) ) {
                            $wp_filesystem->delete($working_dir, true);
                            if ( 'incompatible_archive' == $result->get_error_code() ) {
                                    return new WP_Error( 'incompatible_archive', $this->strings['incompatible_archive'], $result->get_error_data() );
                            }
                            return $result;
                    }

                    return $working_dir;
            } 
            
            /**
             * Prepara le info sui pacchetti disponibili aggiungendo
             * quelli salvati nella direcotry temporanea
             * 
             * @param type $value
             * @return type
             */
            public function set_nestedplugins_package_path($value)
            {
                foreach($this->pluginsToUpdate as $pluginPath){
                    if( array_key_exists($pluginPath,$value->response) ){
                        $value->response[$pluginPath]->package = $this->nestedPluginsPackage[dirname($pluginPath)];
                    }
                    else
                    {
                        $value->response[$pluginPath] = (object) array(
                            'package' => $this->nestedPluginsPackage[dirname($pluginPath)],
                            'plugin' => $pluginPath,
                            'slug' => dirname($pluginPath)
                            ); 
                    }
                }

                return $value;
            }
            
            /**
             * Raccoglie tutti i package e li sposta in una directory temporanea
             * 
             * @global WP_Filesystem_Base $wp_filesystem
             * @param type $source
             * @return type
             */
            function gatherNestedPackage($source)                        
            {
                global $wp_filesystem;

                unset($this->nestedPluginsPackage);
                unset($this->nestedThemePackage);
                unset($this->pluginsToUpdate);

                $this->nestedPluginsPackage = array();

                $pluginList = array_keys($this->nestedPackages["plugins"]);

                $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source ), RecursiveIteratorIterator::SELF_FIRST );
                foreach ( $iterator as $path ) {
                    if ( ( '.' === $path->getFilename() ) || 
                         ( '..' === $path->getFilename() ) ||
                         ( $path->isFile() ) ) {
                        continue;
                    }
                    foreach ( $pluginList as $nestedPlugin ) {
                        $result = glob( $path->getPathName() . '/'. $this->nestedPackages["plugins"][$nestedPlugin] .'*.zip' );
                        if(count($result)){
                            $temp = wp_tempnam($result[0]);
                            $wp_filesystem->move($result[0], $temp, true);
                            $this->nestedPluginsPackage[$nestedPlugin] = $temp;
                        }                            
                    }
                    if( !isset($this->nestedThemePackage)){
                        $result = glob( $path->getPathName() . '/'. $this->nestedPackages["theme"] .'*.zip' );
                        if(count($result)){
                            $temp = wp_tempnam($result[0]);
                            $wp_filesystem->move($result[0], $temp, true);
                            $this->nestedThemePackage = $temp;
                        }                            
                    }
                    $pluginList = array_diff($pluginList,array_keys($this->nestedPluginsPackage));
                    if( !count($pluginList) && isset($this->nestedThemePackage) ){
                        break;
                    }
                }
                $iterator = null;
                unset( $iterator );                    
            }
            
            /**
             * Effettua l'aggiornamento di tutti i plugin
             * 
             * @global WP_Filesystem_Base $wp_filesystem
             * @param type $source
             * @return type
             */
            function child_upgrade_nested_plugins($source)                        
            {
                global $wp_filesystem;

                $this->gatherNestedPackage($source);

                if( count($this->nestedPluginsPackage)){
                    $this->pluginsToUpdate = array();
                    $all_plugins = get_plugins();
                    foreach( $all_plugins as $key => $value){
                        if(array_key_exists(dirname($key), $this->nestedPluginsPackage)){
                            //Prima faccio l'unzip del pacchetto
                            $packagePath = $this->nestedPluginsPackage[dirname($key)];
                            $working_dir = dirname($packagePath) . '/' . dirname($key);

                            // Clean up working directory
                            if ( $wp_filesystem->is_dir($working_dir) ){
                                $wp_filesystem->delete($working_dir, true);
                            }
                            
                            // Unzip package to working directory
                            $result = unzip_file( $packagePath, $working_dir );
                            if ( !is_wp_error($result) ) {
                                $newPluginData = get_plugin_data( $working_dir . '/' . $key );
                                if (version_compare($newPluginData['Version'], $value['Version']) > 0) {
                                    array_push($this->pluginsToUpdate,$key);                                   
                                }
                            }
                            $wp_filesystem->delete($working_dir, true);
                        }
                    }
                    if( count($this->pluginsToUpdate)){
                        add_filter( 'site_transient_update_plugins', array($this,'set_site_transient_update_plugins'));
                        add_filter( 'upgrader_pre_download', array( $this, 'preUpgradeFilter' ), 99, 4 );
                        $upgrader = new Plugin_Upgrader( new Bulk_Plugin_Upgrader_Skin( compact( 'nonce', 'url' ) ) );
                        $upgrader->bulk_upgrade( $this->pluginsToUpdate );
                        remove_filter('upgrader_pre_download', array($this, 'preUpgradeFilter') );
                        remove_filter('site_transient_update_plugins', array($this, 'set_site_transient_update_plugins') );
                    }
                }

                $source = $this->unpack_package($this->nestedThemePackage,false);

                //Cancellazione dei file che ho messo nella temp
                if( count($this->nestedPluginsPackage)){
                   foreach( $this->nestedPluginsPackage as $tempfile){
                       unlink( $tempfile );
                   }   
                }
                if( isset($this->nestedThemePackage)){
                       unlink( $this->nestedThemePackage );
                }

                return $source . '/' . $this->themeName;
            }            
        }
            
endif;