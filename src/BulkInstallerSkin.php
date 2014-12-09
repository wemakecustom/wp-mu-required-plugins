<?php

namespace WMC\Wordpress\RequiredPlugins;

if ( ! class_exists( 'WP_Upgrader' ) )
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

/**
 * Installer skin to set strings for the bulk plugin installations..
 *
 * Extends Bulk_Upgrader_Skin and customizes to suit the installation of multiple
 * plugins.
 *
 * @package TGM-Plugin-Activation
 * @author Thomas Griffin <thomas@thomasgriffinmedia.com>
 * @author Gary Jones <gamajo@gamajo.com>
 */
class BulkInstallerSkin extends \Bulk_Upgrader_Skin
{
    /**
     * Holds plugin info for each individual plugin installation.
     *
     * @var array
     */
    public $plugin_info = array();

    /**
     * Holds names of plugins that are undergoing bulk installations.
     *
     * @var array
     */
    public $plugin_names = array();

    /**
     * Integer to use for iteration through each plugin installation.
     *
     * @var integer
     */
    public $i = 0;

    /**
     * Constructor. Parses default args with new ones and extracts them for use.
     *
     * @param array $args Arguments to pass for use within the class
     */
    public function __construct( $args = array() )
    {
        /** Parse default and new args */
        $defaults = array( 'url' => '', 'nonce' => '', 'names' => array() );
        $args = wp_parse_args( $args, $defaults );

        /** Set plugin names to $this->plugin_names property */
        $this->plugin_names = $args['names'];

        /** Extract the new args */
        parent::__construct( $args );

    }

    /**
     * Sets install skin strings for each individual plugin.
     *
     * Checks to see if the automatic activation flag is set and uses the
     * the proper strings accordingly.
     */
    public function add_strings()
    {
        /** Automatic activation strings */
        if ( PluginManager::getInstance()->is_automatic ) {
            $this->upgrader->strings['skin_upgrade_start']        = __( 'The installation and activation process is starting. This process may take a while on some hosts, so please be patient.', PluginManager::TEXT_DOMAIN );
            $this->upgrader->strings['skin_update_successful']    = __( '%1$s installed and activated successfully.', PluginManager::TEXT_DOMAIN ) . ' <a onclick="%2$s" href="#" class="hide-if-no-js"><span>' . __( 'Show Details', PluginManager::TEXT_DOMAIN ) . '</span><span class="hidden">' . __( 'Hide Details', PluginManager::TEXT_DOMAIN ) . '</span>.</a>';
            $this->upgrader->strings['skin_upgrade_end']          = __( 'All installations and activations have been completed.', PluginManager::TEXT_DOMAIN );
            $this->upgrader->strings['skin_before_update_header'] = __( 'Installing and Activating Plugin %1$s (%2$d/%3$d)', PluginManager::TEXT_DOMAIN );
        }
        /** Default installation strings */
        else {
            $this->upgrader->strings['skin_upgrade_start']        = __( 'The installation process is starting. This process may take a while on some hosts, so please be patient.', PluginManager::TEXT_DOMAIN );
            $this->upgrader->strings['skin_update_failed_error']  = __( 'An error occurred while installing %1$s: <strong>%2$s</strong>.', PluginManager::TEXT_DOMAIN );
            $this->upgrader->strings['skin_update_failed']        = __( 'The installation of %1$s failed.', PluginManager::TEXT_DOMAIN );
            $this->upgrader->strings['skin_update_successful']    = __( '%1$s installed successfully.', PluginManager::TEXT_DOMAIN ) . ' <a onclick="%2$s" href="#" class="hide-if-no-js"><span>' . __( 'Show Details', PluginManager::TEXT_DOMAIN ) . '</span><span class="hidden">' . __( 'Hide Details', PluginManager::TEXT_DOMAIN ) . '</span>.</a>';
            $this->upgrader->strings['skin_upgrade_end']          = __( 'All installations have been completed.', PluginManager::TEXT_DOMAIN );
            $this->upgrader->strings['skin_before_update_header'] = __( 'Installing Plugin %1$s (%2$d/%3$d)', PluginManager::TEXT_DOMAIN );
        }

    }

    /**
     * Outputs the header strings and necessary JS before each plugin installation.
     */
    public function before()
    {
        /** We are currently in the plugin installation loop, so set to true */
        $this->in_loop = true;

        printf( '<h4>' . $this->upgrader->strings['skin_before_update_header'] . ' <img alt="" src="' . admin_url( 'images/wpspin_light.gif' ) . '" class="hidden waiting-' . $this->upgrader->update_current . '" style="vertical-align:middle;" /></h4>', $this->plugin_names[$this->i], $this->upgrader->update_current, $this->upgrader->update_count );
        echo '<script type="text/javascript">jQuery(\'.waiting-' . esc_js( $this->upgrader->update_current ) . '\').show();</script>';
        echo '<div class="update-messages hide-if-js" id="progress-' . esc_attr( $this->upgrader->update_current ) . '"><p>';

        /** Flush header output buffer */
        $this->before_flush_output();

    }

    /**
     * Outputs the footer strings and necessary JS after each plugin installation.
     *
     * Checks for any errors and outputs them if they exist, else output
     * success strings.
     */
    public function after()
    {
        /** Close install strings */
        echo '</p></div>';

        /** Output error strings if an error has occurred */
        if ($this->error || ! $this->result) {
            if ( $this->error )
                echo '<div class="error"><p>' . sprintf( $this->upgrader->strings['skin_update_failed_error'], $this->plugin_names[$this->i], $this->error ) . '</p></div>';
            else
                echo '<div class="error"><p>' . sprintf( $this->upgrader->strings['skin_update_failed'], $this->plugin_names[$this->i] ) . '</p></div>';

            echo '<script type="text/javascript">jQuery(\'#progress-' . esc_js( $this->upgrader->update_current ) . '\').show();</script>';
        }

        /** If the result is set and there are no errors, success! */
        if ( ! empty( $this->result ) && ! is_wp_error( $this->result ) ) {
            echo '<div class="updated"><p>' . sprintf( $this->upgrader->strings['skin_update_successful'], $this->plugin_names[$this->i], 'jQuery(\'#progress-' . esc_js( $this->upgrader->update_current ) . '\').toggle();jQuery(\'span\', this).toggle(); return false;' ) . '</p></div>';
            echo '<script type="text/javascript">jQuery(\'.waiting-' . esc_js( $this->upgrader->update_current ) . '\').hide();</script>';
        }

        /** Set in_loop and error to false and flush footer output buffer */
        $this->reset();
        $this->after_flush_output();

    }

    /**
     * Outputs links after bulk plugin installation is complete.
     */
    public function bulk_footer()
    {
        /** Serve up the string to say installations (and possibly activations) are complete */
        parent::bulk_footer();

        /** Flush plugins cache so we can make sure that the installed plugins list is always up to date */
        wp_cache_flush();

        /** Display message based on if all plugins are now active or not */
        $complete = array();
        foreach ( PluginManager::getInstance()->plugins as $plugin ) {
            if ( ! is_plugin_active( $plugin['file_path'] ) ) {
                echo '<p><a href="' . add_query_arg( 'page', PluginManager::getInstance()->menu, admin_url( PluginManager::getInstance()->parent_url_slug ) ) . '" title="' . esc_attr( PluginManager::getInstance()->strings['return'] ) . '" target="_parent">' . __( PluginManager::getInstance()->strings['return'], PluginManager::TEXT_DOMAIN ) . '</a></p>';
                $complete[] = $plugin;
                break;
            }
            /** Nothing to store */
            else {
                $complete[] = '';
            }
        }

        /** Filter out any empty entries */
        $complete = array_filter( $complete );

        /** All plugins are active, so we display the complete string and hide the menu to protect users */
        if ( empty( $complete ) ) {
            echo '<p>' .  sprintf( PluginManager::getInstance()->strings['complete'], '<a href="' . admin_url() . '" title="' . __( 'Return to the Dashboard', PluginManager::TEXT_DOMAIN ) . '">' . __( 'Return to the Dashboard', PluginManager::TEXT_DOMAIN ) . '</a>' ) . '</p>';
            echo '<style type="text/css">#adminmenu .wp-submenu li.current { display: none !important; }</style>';
        }

    }

    /**
     * Flush header output buffer.
     */
    public function before_flush_output()
    {
        wp_ob_end_flush_all();
        flush();

    }

    /**
     * Flush footer output buffer and iterate $this->i to make sure the
     * installation strings reference the correct plugin.
     */
    public function after_flush_output()
    {
        wp_ob_end_flush_all();
        flush();
        $this->i++;

    }

}
