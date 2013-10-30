<?php

/**
 * Plugin Name: Required Plugins
 * Plugin URI: https://github.com/wemakecustom/wp-mu-required-plugins
 * Description: Wordpress plugins dependency manager
 * Author: WeMakeCustom
 * Author URI: http://www.wemakecustom.com/
 */

use WMC\Wordpress\RequiredPlugins\PluginManager;

/**
 * Helper function to register a collection of required plugins.
 *
 * @api
 *
 * @param array $plugins An array of plugin arrays
 * @param array $config Optional. An array of configuration values
 */
function required_plugins_register( $plugins, $config = array() )
{
    foreach ( $plugins as $plugin )
        PluginManager::getInstance()->register( $plugin );

    if ( $config )
        PluginManager::getInstance()->config( $config );

}

/** When the rest of WP has loaded, kick-start the rest of the class */
add_action( 'init', function() {
    PluginManager::getInstance()->init();
});
