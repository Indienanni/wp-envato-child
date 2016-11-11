<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'MultiPackage_university' ) ) :
    class MultiPackage_university extends MultiPackage {
	        
        /**
         * Costruttore
         * 
         * @param type $name
         */
        function __construct($name) {
            parent::__construct($name);
            $this->nestedPackages=array( "theme" => "university", 
                                         "plugins" => array( "u-course" => "u-course", 
                                                             "u-member" => "u-member", 
                                                             "u-shortcodes" => "u-shortcodes", 
                                                             "u-event" => "u-event",
                                                             "u-projects" => "u-projects",
                                                             "revslider" => "revslider",
                                                             "js_composer" => "visual-composer"));
        }        
}
endif;