<?php

namespace WMC\Wordpress\RequiredPlugins;

if ( ! class_exists( 'WP_Upgrader' ) )
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Installer class to handle bulk plugin installations.
 *
 * Extends WP_Upgrader and customizes to suit the installation of multiple
 * plugins.
 *
 * @package TGM-Plugin-Activation
 * @author Thomas Griffin <thomas@thomasgriffinmedia.com>
 * @author Gary Jones <gamajo@gamajo.com>
 */
class BulkInstaller extends \WP_Upgrader
{
    /**
     * Holds result of bulk plugin installation.
     *
     * @var string
     */
    public $result;

    /**
     * Flag to check if bulk installation is occurring or not.
     *
     * @var boolean
     */
    public $bulk = false;

    /**
     * Processes the bulk installation of plugins.
     *
     * @param  array          $packages The plugin sources needed for installation
     * @return string|boolean Install confirmation messages on success, false on failure
     */
    public function bulk_install( $packages )
    {
        /** Pass installer skin object and set bulk property to true */
        $this->init();
        $this->bulk = true;

        /** Set install strings and automatic activation strings (if config option is set to true) */
        $this->install_strings();
        if ( PluginManager::getInstance()->is_automatic )
            $this->activate_strings();

        /** Run the header string to notify user that the process has begun */
        $this->skin->header();

        /** Connect to the Filesystem */
        $res = $this->fs_connect( array( WP_CONTENT_DIR, WP_PLUGIN_DIR ) );
        if (! $res) {
            $this->skin->footer();

            return false;
        }

        /** Set the bulk header and prepare results array */
        $this->skin->bulk_header();
        $results = array();

        /** Get the total number of packages being processed and iterate as each package is successfully installed */
        $this->update_count   = count( $packages );
        $this->update_current = 0;

        /** Loop through each plugin and process the installation */
        foreach ($packages as $plugin) {
            $this->update_current++; // Increment counter

            /** Do the plugin install */
            $result = $this->run(
                array(
                    'package'           => $plugin, // The plugin source
                    'destination'       => WP_PLUGIN_DIR, // The destination dir
                    'clear_destination' => false, // Do we want to clear the destination or not?
                    'clear_working'     => true, // Remove original install file
                    'is_multi'          => true, // Are we processing multiple installs?
                    'hook_extra'        => array( 'plugin' => $plugin, ), // Pass plugin source as extra data
                )
            );

            /** Store installation results in result property */
            $results[$plugin] = $this->result;

            /** Prevent credentials auth screen from displaying multiple times */
            if ( false === $result )
                break;
        }

        /** Pass footer skin strings */
        $this->skin->bulk_footer();
        $this->skin->footer();

        /** Return our results */

        return $results;

    }

    /**
     * Performs the actual installation of each plugin.
     *
     * This method also activates the plugin in the automatic flag has been
     * set to true for the TGMPA class.
     *
     * @param  array      $options The installation cofig options
     * @return null/array Return early if error, array of installation data on success
     */
    public function run( $options )
    {
        /** Default config options */
        $defaults = array(
            'package'           => '',
            'destination'       => '',
            'clear_destination' => false,
            'clear_working'     => true,
            'is_multi'          => false,
            'hook_extra'        => array(),
        );

        /** Parse default options with config options from $this->bulk_upgrade and extract them */
        $options = wp_parse_args( $options, $defaults );
        extract( $options );

        /** Connect to the Filesystem */
        $res = $this->fs_connect( array( WP_CONTENT_DIR, $destination ) );
        if ( ! $res )
            return false;

        /** Return early if there is an error connecting to the Filesystem */
        if ( is_wp_error( $res ) ) {
            $this->skin->error( $res );

            return $res;
        }

        /** Call $this->header separately if running multiple times */
        if ( ! $is_multi )
            $this->skin->header();

        /** Set strings before the package is installed */
        $this->skin->before();

        /** Download the package (this just returns the filename of the file if the package is a local file) */
        $download = $this->download_package( $package );
        if ( is_wp_error( $download ) ) {
            $this->skin->error( $download );
            $this->skin->after();

            return $download;
        }

        /** Don't accidentally delete a local file */
        $delete_package = ( $download != $package );

        /** Unzip file into a temporary working directory */
        $working_dir = $this->unpack_package( $download, $delete_package );
        if ( is_wp_error( $working_dir ) ) {
            $this->skin->error( $working_dir );
            $this->skin->after();

            return $working_dir;
        }

        /** Install the package into the working directory with all passed config options */
        $result = $this->install_package(
            array(
                'source'            => $working_dir,
                'destination'       => $destination,
                'clear_destination' => $clear_destination,
                'clear_working'     => $clear_working,
                'hook_extra'        => $hook_extra,
            )
        );

        /** Pass the result of the installation */
        $this->skin->set_result( $result );

        /** Set correct strings based on results */
        if ( is_wp_error( $result ) ) {
            $this->skin->error( $result );
            $this->skin->feedback( 'process_failed' );
        }
        /** The plugin install is successful */
        else {
            $this->skin->feedback( 'process_success' );
        }

        /** Only process the activation of installed plugins if the automatic flag is set to true */
        if ( PluginManager::getInstance()->is_automatic ) {
            /** Flush plugins cache so we can make sure that the installed plugins list is always up to date */
            wp_cache_flush();

            /** Get the installed plugin file and activate it */
            $plugin_info = $this->plugin_info( $package );
            $activate    = activate_plugin( $plugin_info );

            /** Re-populate the file path now that the plugin has been installed and activated */
            PluginManager::getInstance()->populate_file_path();

            /** Set correct strings based on results */
            if ( is_wp_error( $activate ) ) {
                $this->skin->error( $activate );
                $this->skin->feedback( 'activation_failed' );
            }
            /** The plugin activation is successful */
            else {
                $this->skin->feedback( 'activation_success' );
            }
        }

        /** Flush plugins cache so we can make sure that the installed plugins list is always up to date */
        wp_cache_flush();

        /** Set install footer strings */
        $this->skin->after();
        if ( ! $is_multi )
            $this->skin->footer();

        return $result;

    }

    /**
     * Sets the correct install strings for the installer skin to use.
     */
    public function install_strings()
    {
        $this->strings['no_package']          = __( 'Install package not available.', PluginManager::TEXT_DOMAIN );
        $this->strings['downloading_package'] = __( 'Downloading install package from <span class="code">%s</span>&#8230;', PluginManager::TEXT_DOMAIN );
        $this->strings['unpack_package']      = __( 'Unpacking the package&#8230;', PluginManager::TEXT_DOMAIN );
        $this->strings['installing_package']  = __( 'Installing the plugin&#8230;', PluginManager::TEXT_DOMAIN );
        $this->strings['process_failed']      = __( 'Plugin install failed.', PluginManager::TEXT_DOMAIN );
        $this->strings['process_success']     = __( 'Plugin installed successfully.', PluginManager::TEXT_DOMAIN );

    }

    /**
     * Sets the correct activation strings for the installer skin to use.
     */
    public function activate_strings()
    {
        $this->strings['activation_failed']  = __( 'Plugin activation failed.', PluginManager::TEXT_DOMAIN );
        $this->strings['activation_success'] = __( 'Plugin activated successfully.', PluginManager::TEXT_DOMAIN );

    }

    /**
     * Grabs the plugin file from an installed plugin.
     *
     * @return string|boolean Return plugin file on success, false on failure
     */
    public function plugin_info()
    {
        /** Return false if installation result isn't an array or the destination name isn't set */
        if ( ! is_array( $this->result ) )
            return false;
        if ( empty( $this->result['destination_name'] ) )
            return false;

        /** Get the installed plugin file or return false if it isn't set */
        $plugin = get_plugins( '/' . $this->result['destination_name'] );
        if ( empty( $plugin ) )
            return false;

        /** Assume the requested plugin is the first in the list */
        $pluginfiles = array_keys( $plugin );

        return $this->result['destination_name'] . '/' . $pluginfiles[0];

    }

}
