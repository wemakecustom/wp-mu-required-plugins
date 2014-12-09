<?php

namespace WMC\Wordpress\RequiredPlugins;

/**
 * Main plugin file
 */
class PluginManager
{
    /**
     * Holds a copy of itself, so it can be referenced by the class name.
     *
     * @var PluginManager
     */
    private static $instance = null;

    /**
     * Holds arrays of plugin details.
     *
     * @var array
     */
    public $plugins = array();

    /**
     * Parent menu slug for plugins page.
     *
     * @var string Parent menu slug. Defaults to 'themes.php'.
     */
    public $parent_menu_slug = 'themes.php';

    /**
     * Parent URL slug for URL references.
     *
     * This is useful if you want to place the custom plugins page as a
     * submenu item under a custom parent menu.
     *
     * @var string Parent URL slug. Defaults to 'themes.php'.
     */
    public $parent_url_slug = 'themes.php';

    /**
     * Name of the querystring argument for the admin page.
     *
     * @var string
     */
    public $menu = 'install-required-plugins';

    /**
     * Text domain for localization support.
     *
     * @var string
     */
    const TEXT_DOMAIN = 'tgmpa';

    /**
     * Default absolute path to folder containing pre-packaged plugin zip files.
     *
     * @var string Absolute path prefix to packaged zip file location. Default is empty string.
     */
    public $default_path = '';

    /**
     * Flag to show admin notices or not.
     *
     * @var boolean
     */
    public $has_notices = true;

    /**
     * Flag to set automatic activation of plugins. Off by default.
     *
     * @var boolean
     */
    public $is_automatic = false;

    /**
     * Optional message to display before the plugins table.
     *
     * @var string Message filtered by wp_kses_post(). Default is empty string.
     */
    public $message = '';

    /**
     * Holds configurable array of strings.
     *
     * Default values are added in the constructor.
     *
     * @var array
     */
    public $strings = array();

    /**
     * Adds a reference of this object to $instance, populates default strings,
     * does the required_plugins_init action hook, and hooks in the interactions to init.
     *
     * @see PluginManager::init()
     */
    private function __construct()
    {
        $this->strings = array(
            'page_title'                      => __( 'Install Required Plugins', self::TEXT_DOMAIN ),
            'menu_title'                      => __( 'Install Plugins', self::TEXT_DOMAIN ),
            'installing'                      => __( 'Installing Plugin: %s', self::TEXT_DOMAIN ),
            'oops'                            => __( 'Something went wrong.', self::TEXT_DOMAIN ),
            'notice_can_install_required'     => _n_noop( 'This theme requires the following plugin: %1$s.', 'This theme requires the following plugins: %1$s.' ),
            'notice_can_install_recommended'  => _n_noop( 'This theme recommends the following plugin: %1$s.', 'This theme recommends the following plugins: %1$s.' ),
            'notice_cannot_install'           => _n_noop( 'Sorry, but you do not have the correct permissions to install the %s plugin. Contact the administrator of this site for help on getting the plugin installed.', 'Sorry, but you do not have the correct permissions to install the %s plugins. Contact the administrator of this site for help on getting the plugins installed.' ),
            'notice_can_activate_required'    => _n_noop( 'The following required plugin is currently inactive: %1$s.', 'The following required plugins are currently inactive: %1$s.' ),
            'notice_can_activate_recommended' => _n_noop( 'The following recommended plugin is currently inactive: %1$s.', 'The following recommended plugins are currently inactive: %1$s.' ),
            'notice_cannot_activate'          => _n_noop( 'Sorry, but you do not have the correct permissions to activate the %s plugin. Contact the administrator of this site for help on getting the plugin activated.', 'Sorry, but you do not have the correct permissions to activate the %s plugins. Contact the administrator of this site for help on getting the plugins activated.' ),
            'notice_ask_to_update'            => _n_noop( 'The following plugin needs to be updated to its latest version to ensure maximum compatibility with this theme: %1$s.', 'The following plugins need to be updated to their latest version to ensure maximum compatibility with this theme: %1$s.' ),
            'notice_cannot_update'            => _n_noop( 'Sorry, but you do not have the correct permissions to update the %s plugin. Contact the administrator of this site for help on getting the plugin updated.', 'Sorry, but you do not have the correct permissions to update the %s plugins. Contact the administrator of this site for help on getting the plugins updated.' ),
            'install_link'                    => _n_noop( 'Begin installing plugin', 'Begin installing plugins' ),
            'activate_link'                   => _n_noop( 'Activate installed plugin', 'Activate installed plugins' ),
            'return'                          => __( 'Return to Required Plugins Installer', self::TEXT_DOMAIN ),
            'plugin_activated'                => __( 'Plugin activated successfully.', self::TEXT_DOMAIN ),
            'complete'                        => __( 'All plugins installed and activated successfully. %1$s', self::TEXT_DOMAIN ),
        );

        /** Annouce that the class is ready, and pass the object (for advanced use) */
        do_action( 'required_plugins_init', $this );
    }

    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Initialise the interactions between this class and WordPress.
     *
     * Hooks in three new methods for the class: admin_menu, notices and styles.
     *
     * @see PluginManager::admin_menu()
     * @see PluginManager::notices()
     * @see PluginManager::styles()
     */
    public function init()
    {
        do_action( 'required_plugins_register' );
        /** After this point, the plugins should be registered and the configuration set */

        /** Proceed only if we have plugins to handle */
        if ($this->plugins) {
            $sorted = array(); // Prepare variable for sorting

            foreach ( $this->plugins as $plugin )
                $sorted[] = $plugin['name'];

            array_multisort( $sorted, SORT_ASC, $this->plugins ); // Sort plugins alphabetically by name

            add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
            add_action( 'admin_head', array( &$this, 'dismiss' ) );
            add_filter( 'install_plugin_complete_actions', array( &$this, 'actions' ) );

            /** Load admin bar in the header to remove flash when installing plugins */
            if ( $this->is_tgmpa_page() ) {
                remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 );
                remove_action( 'admin_footer', 'wp_admin_bar_render', 1000 );
                add_action( 'wp_head', 'wp_admin_bar_render', 1000 );
                add_action( 'admin_head', 'wp_admin_bar_render', 1000 );
            }

            if ($this->has_notices) {
                add_action( 'admin_notices', array( &$this, 'notices' ) );
                add_action( 'admin_init', array( &$this, 'admin_init' ), 1 );
                add_action( 'admin_enqueue_scripts', array( 'WMC\Wordpress\RequiredPlugins\ListTable', 'thickbox' ) );
                add_action( 'switch_theme', array( &$this, 'update_dismiss' ) );
            }

            /** Setup the force activation hook */
            foreach ($this->plugins as $plugin) {
                if ( isset( $plugin['force_activation'] ) && true === $plugin['force_activation'] ) {
                    add_action( 'admin_init', array( &$this, 'force_activation' ) );
                    break;
                }
            }

            /** Setup the force deactivation hook */
            foreach ($this->plugins as $plugin) {
                if ( isset( $plugin['force_deactivation'] ) && true === $plugin['force_deactivation'] ) {
                    add_action( 'switch_theme', array( &$this, 'force_deactivation' ) );
                    break;
                }
            }
        }

    }

    /**
     * Handles calls to show plugin information via links in the notices.
     *
     * We get the links in the admin notices to point to the TGMPA page, rather
     * than the typical plugin-install.php file, so we can prepare everything
     * beforehand.
     *
     * WP doesn't make it easy to show the plugin information in the thickbox -
     * here we have to require a file that includes a function that does the
     * main work of displaying it, enqueue some styles, set up some globals and
     * finally call that function before exiting.
     *
     * Down right easy once you know how...
     *
     * @global string $tab Used as iframe div class names, helps with styling
     * @global string $body_id Used as the iframe body ID, helps with styling
     * @return null Returns early if not the TGMPA page.
     */
    public function admin_init()
    {
        if ( ! $this->is_tgmpa_page() )
            return;

        if ( isset( $_REQUEST['tab'] ) && 'plugin-information' == $_REQUEST['tab'] ) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php'; // Need for install_plugin_information()

            wp_enqueue_style( 'plugin-install' );

            global $tab, $body_id;
            $body_id = $tab = 'plugin-information';

            install_plugin_information();

            exit;
        }

    }

    /**
     * Adds submenu page under 'Appearance' tab.
     *
     * This method adds the submenu page letting users know that a required
     * plugin needs to be installed.
     *
     * This page disappears once the plugin has been installed and activated.
     *
     * @see PluginManager::init()
     * @see PluginManager::install_plugins_page()
     */
    public function admin_menu()
    {
        // Make sure privileges are correct to see the page
        if ( ! current_user_can( 'install_plugins' ) )
            return;

        $this->populate_file_path();

        foreach ($this->plugins as $plugin) {
            if ( ! is_plugin_active( $plugin['file_path'] ) ) {
                add_submenu_page(
                    $this->parent_menu_slug,                // Parent menu slug
                    $this->strings['page_title'],           // Page title
                    $this->strings['menu_title'],           // Menu title
                    'edit_theme_options',                   // Capability
                    $this->menu,                            // Menu slug
                    array( &$this, 'install_plugins_page' ) // Callback
                );
            break;
            }
        }

    }

    /**
     * Echoes plugin installation form.
     *
     * This method is the callback for the admin_menu method function.
     * This displays the admin page and form area where the user can select to install and activate the plugin.
     *
     * @return null Aborts early if we're processing a plugin installation action
     */
    public function install_plugins_page()
    {
        /** Store new instance of plugin table in object */
        $plugin_table = new ListTable;

        /** Return early if processing a plugin installation action */
        if ( isset( $_POST[sanitize_key( 'action' )] ) && 'tgmpa-bulk-install' == $_POST[sanitize_key( 'action' )] && $plugin_table->process_bulk_actions() || $this->do_plugin_install() )
            return;

        ?>
        <div class="tgmpa wrap">

            <?php screen_icon( apply_filters( 'tgmpa_default_screen_icon', 'themes' ) ); ?>
            <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
            <?php $plugin_table->prepare_items(); ?>

            <?php if ( isset( $this->message ) ) _e( wp_kses_post( $this->message ), self::TEXT_DOMAIN ); ?>

            <form id="tgmpa-plugins" action="" method="post">
                <input type="hidden" name="tgmpa-page" value="<?php echo $this->menu; ?>" />
                <?php $plugin_table->display(); ?>
            </form>

        </div>
        <?php

    }

    /**
     * Installs a plugin or activates a plugin depending on the hover
     * link clicked by the user.
     *
     * Checks the $_GET variable to see which actions have been
     * passed and responds with the appropriate method.
     *
     * Uses WP_Filesystem to process and handle the plugin installation
     * method.
     *
     * @uses WP_Filesystem
     * @uses WP_Error
     * @uses WP_Upgrader
     * @uses Plugin_Upgrader
     * @uses Plugin_Installer_Skin
     *
     * @return boolean True on success, false on failure
     */
    protected function do_plugin_install()
    {
        /** All plugin information will be stored in an array for processing */
        $plugin = array();

        /** Checks for actions from hover links to process the installation */
        if ( isset( $_GET[sanitize_key( 'plugin' )] ) && ( isset( $_GET[sanitize_key( 'tgmpa-install' )] ) && 'install-plugin' == $_GET[sanitize_key( 'tgmpa-install' )] ) ) {
            check_admin_referer( 'tgmpa-install' );

            $plugin['name']   = $_GET[sanitize_key( 'plugin_name' )]; // Plugin name
            $plugin['slug']   = $_GET[sanitize_key( 'plugin' )]; // Plugin slug
            $plugin['source'] = $_GET[sanitize_key( 'plugin_source' )]; // Plugin source

            /** Pass all necessary information via URL if WP_Filesystem is needed */
            $url = wp_nonce_url(
                add_query_arg(
                    array(
                        'page'          => $this->menu,
                        'plugin'        => $plugin['slug'],
                        'plugin_name'   => $plugin['name'],
                        'plugin_source' => $plugin['source'],
                        'tgmpa-install' => 'install-plugin',
                    ),
                    admin_url( $this->parent_url_slug )
                ),
                'tgmpa-install'
            );
            $method = ''; // Leave blank so WP_Filesystem can populate it as necessary
            $fields = array( sanitize_key( 'tgmpa-install' ) ); // Extra fields to pass to WP_Filesystem

            if ( false === ( $creds = request_filesystem_credentials( $url, $method, false, false, $fields ) ) )
                return true;

            if ( ! WP_Filesystem( $creds ) ) {
                request_filesystem_credentials( $url, $method, true, false, $fields ); // Setup WP_Filesystem

                return true;
            }

            require_once ABSPATH . 'wp-admin/includes/plugin-install.php'; // Need for plugins_api
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; // Need for upgrade classes

            /** Set plugin source to WordPress API link if available */
            if ( isset( $plugin['source'] ) && 'repo' == $plugin['source'] ) {
                $api = plugins_api( 'plugin_information', array( 'slug' => $plugin['slug'], 'fields' => array( 'sections' => false ) ) );

                if ( is_wp_error( $api ) )
                    wp_die( $this->strings['oops'] . var_dump( $api ) );

                if ( isset( $api->download_link ) )
                    $plugin['source'] = $api->download_link;
            }

            /** Set type, based on whether the source starts with http:// or https:// */
            $type = preg_match( '|^http(s)?://|', $plugin['source'] ) ? 'web' : 'upload';

            /** Prep variables for Plugin_Installer_Skin class */
            $title = sprintf( $this->strings['installing'], $plugin['name'] );
            $url   = add_query_arg( array( 'action' => 'install-plugin', 'plugin' => $plugin['slug'] ), 'update.php' );
            if ( isset( $_GET['from'] ) )
                $url .= add_query_arg( 'from', urlencode( stripslashes( $_GET['from'] ) ), $url );

            $nonce = 'install-plugin_' . $plugin['slug'];

            /** Prefix a default path to pre-packaged plugins */
            $source = ( 'upload' == $type ) ? $this->default_path . $plugin['source'] : $plugin['source'];

            /** Create a new instance of Plugin_Upgrader */
            $upgrader = new \Plugin_Upgrader( $skin = new \Plugin_Installer_Skin( compact( 'type', 'title', 'url', 'nonce', 'plugin', 'api' ) ) );

            /** Perform the action and install the plugin from the $source urldecode() */
            $upgrader->install( $source );

            /** Flush plugins cache so we can make sure that the installed plugins list is always up to date */
            wp_cache_flush();

            /** Only activate plugins if the config option is set to true */
            if ($this->is_automatic) {
                $plugin_activate = $upgrader->plugin_info(); // Grab the plugin info from the Plugin_Upgrader method
                $activate = activate_plugin( $plugin_activate ); // Activate the plugin
                $this->populate_file_path(); // Re-populate the file path now that the plugin has been installed and activated

                if ( is_wp_error( $activate ) ) {
                    echo '<div id="message" class="error"><p>' . $activate->get_error_message() . '</p></div>';
                    echo '<p><a href="' . add_query_arg( 'page', $this->menu, admin_url( $this->parent_url_slug ) ) . '" title="' . esc_attr( $this->strings['return'] ) . '" target="_parent">' . __( 'Return to Required Plugins Installer', self::TEXT_DOMAIN ) . '</a></p>';

                    return true; // End it here if there is an error with automatic activation
                } else {
                    echo '<p>' . $this->strings['plugin_activated'] . '</p>';
                }
            }

            /** Display message based on if all plugins are now active or not */
            $complete = array();
            foreach ($this->plugins as $plugin) {
                if ( ! is_plugin_active( $plugin['file_path'] ) ) {
                    echo '<p><a href="' . add_query_arg( 'page', $this->menu, admin_url( $this->parent_url_slug ) ) . '" title="' . esc_attr( $this->strings['return'] ) . '" target="_parent">' . __( $this->strings['return'], self::TEXT_DOMAIN ) . '</a></p>';
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

            /** All plugins are active, so we display the complete string and hide the plugin menu */
            if ( empty( $complete ) ) {
                echo '<p>' .  sprintf( $this->strings['complete'], '<a href="' . admin_url() . '" title="' . __( 'Return to the Dashboard', self::TEXT_DOMAIN ) . '">' . __( 'Return to the Dashboard', self::TEXT_DOMAIN ) . '</a>' ) . '</p>';
                echo '<style type="text/css">#adminmenu .wp-submenu li.current { display: none !important; }</style>';
            }

            return true;
        }
        /** Checks for actions from hover links to process the activation */
        elseif ( isset( $_GET[sanitize_key( 'plugin' )] ) && ( isset( $_GET[sanitize_key( 'tgmpa-activate' )] ) && 'activate-plugin' == $_GET[sanitize_key( 'tgmpa-activate' )] ) ) {
            check_admin_referer( 'tgmpa-activate', 'tgmpa-activate-nonce' );

            /** Populate $plugin array with necessary information */
            $plugin['name']   = $_GET[sanitize_key( 'plugin_name' )];
            $plugin['slug']   = $_GET[sanitize_key( 'plugin' )];
            $plugin['source'] = $_GET[sanitize_key( 'plugin_source' )];

            $plugin_data = get_plugins( '/' . $plugin['slug'] ); // Retrieve all plugins
            $plugin_file = array_keys( $plugin_data ); // Retrieve all plugin files from installed plugins
            $plugin_to_activate = $plugin['slug'] . '/' . $plugin_file[0]; // Match plugin slug with appropriate plugin file
            $activate = activate_plugin( $plugin_to_activate ); // Activate the plugin

            if ( is_wp_error( $activate ) ) {
                echo '<div id="message" class="error"><p>' . $activate->get_error_message() . '</p></div>';
                echo '<p><a href="' . add_query_arg( 'page', $this->menu, admin_url( $this->parent_url_slug ) ) . '" title="' . esc_attr( $this->strings['return'] ) . '" target="_parent">' . __( $this->strings['return'], self::TEXT_DOMAIN ) . '</a></p>';

                return true; // End it here if there is an error with activation
            } else {
                /** Make sure message doesn't display again if bulk activation is performed immediately after a single activation */
                if ( ! isset( $_POST[sanitize_key( 'action' )] ) ) {
                    $msg = sprintf( __( 'The following plugin was activated successfully: %s.', self::TEXT_DOMAIN ), '<strong>' . $plugin['name'] . '</strong>' );
                    echo '<div id="message" class="updated"><p>' . $msg . '</p></div>';
                }
            }
        }

        return false;

    }

    /**
     * Echoes required plugin notice.
     *
     * Outputs a message telling users that a specific plugin is required for
     * their theme. If appropriate, it includes a link to the form page where
     * users can install and activate the plugin.
     *
     * @global object $current_screen
     * @return null Returns early if we're on the Install page
     */
    public function notices()
    {
        global $current_screen;

        /** Remove nag on the install page */
        if ( $this->is_tgmpa_page() )
            return;

        $installed_plugins = get_plugins(); // Retrieve a list of all the plugins
        $this->populate_file_path();

        $message              = array(); // Store the messages in an array to be outputted after plugins have looped through
        $install_link         = false; // Set to false, change to true in loop if conditions exist, used for action link 'install'
        $install_link_count   = 0; // Used to determine plurality of install action link text
        $activate_link        = false; // Set to false, change to true in loop if conditions exist, used for action link 'activate'
        $activate_link_count  = 0; // Used to determine plurality of activate action link text

        foreach ($this->plugins as $plugin) {
            /** If the plugin is installed and active, check for minimum version argument before moving forward */
            if ( is_plugin_active( $plugin['file_path'] ) ) {
                /** A minimum version has been specified */
                if ( isset( $plugin['version'] ) ) {
                    if ( isset( $installed_plugins[$plugin['file_path']]['Version'] ) ) {
                        /** If the current version is less than the minimum required version, we display a message */
                        if ( version_compare( $installed_plugins[$plugin['file_path']]['Version'], $plugin['version'], '<' ) ) {
                            if ( current_user_can( 'install_plugins' ) )
                                $message['notice_ask_to_update'][] = $plugin['name'];
                            else
                                $message['notice_cannot_update'][] = $plugin['name'];
                        }
                    }
                    /** Can't find the plugin, so iterate to the next condition */
                    else {
                        continue;
                    }
                }
                /** No minimum version specified, so iterate over the plugin */
                else {
                    continue;
                }
            }

            /** Not installed */
            if ( ! isset( $installed_plugins[$plugin['file_path']] ) ) {
                $install_link = true; // We need to display the 'install' action link
                $install_link_count++; // Increment the install link count
                if ( current_user_can( 'install_plugins' ) ) {
                    if ( $plugin['required'] )
                        $message['notice_can_install_required'][] = $plugin['name'];
                    /** This plugin is only recommended */
                    else
                        $message['notice_can_install_recommended'][] = $plugin['name'];
                }
                /** Need higher privileges to install the plugin */
                else {
                    $message['notice_cannot_install'][] = $plugin['name'];
                }
            }
            /** Installed but not active */
            elseif ( is_plugin_inactive( $plugin['file_path'] ) ) {
                $activate_link = true; // We need to display the 'activate' action link
                $activate_link_count++; // Increment the activate link count
                if ( current_user_can( 'activate_plugins' ) ) {
                    if ( ( isset( $plugin['required'] ) ) && ( $plugin['required'] ) )
                        $message['notice_can_activate_required'][] = $plugin['name'];
                    /** This plugin is only recommended */
                    else {
                        $message['notice_can_activate_recommended'][] = $plugin['name'];
                    }
                }
                /** Need higher privileges to activate the plugin */
                else {
                    $message['notice_cannot_activate'][] = $plugin['name'];
                }
            }
        }

        /** Only process the nag messages if the user has not dismissed them already */
        if ( ! get_user_meta( get_current_user_id(), 'tgmpa_dismissed_notice', true ) ) {
            /** If we have notices to display, we move forward */
            if ( ! empty( $message ) ) {
                krsort( $message ); // Sort messages
                $rendered = ''; // Display all nag messages as strings

                /** Grab all plugin names */
                foreach ($message as $type => $plugin_groups) {
                    $linked_plugin_groups = array();

                    /** Count number of plugins in each message group to calculate singular/plural message */
                    $count = count( $plugin_groups );

                    /** Loop through the plugin names to make the ones pulled from the .org repo linked */
                    foreach ($plugin_groups as $plugin_group_single_name) {
                        $external_url = $this->_get_plugin_data_from_name( $plugin_group_single_name, 'external_url' );
                        $source = $this->_get_plugin_data_from_name( $plugin_group_single_name, 'source' );

                        if ( $external_url && preg_match( '|^http(s)?://|', $external_url ) ) {
                            $linked_plugin_groups[] = '<a href="' . esc_url( $external_url ) . '" title="' . $plugin_group_single_name . '" target="_blank">' . $plugin_group_single_name . '</a>';
                        } elseif ( ! $source || preg_match( '|^http://wordpress.org/extend/plugins/|', $source ) ) {
                            $url = add_query_arg(
                                array(
                                    'tab'       => 'plugin-information',
                                    'plugin'    => $this->_get_plugin_data_from_name( $plugin_group_single_name ),
                                    'TB_iframe' => 'true',
                                    'width'     => '640',
                                    'height'    => '500',
                                ),
                                admin_url( 'plugin-install.php' )
                            );

                            $linked_plugin_groups[] = '<a href="' . esc_url( $url ) . '" class="thickbox" title="' . $plugin_group_single_name . '">' . $plugin_group_single_name . '</a>';
                        } else {
                            $linked_plugin_groups[] = $plugin_group_single_name; // No hyperlink
                        }

                        if ( isset( $linked_plugin_groups ) && (array) $linked_plugin_groups )
                            $plugin_groups = $linked_plugin_groups;
                    }

                    $last_plugin = array_pop( $plugin_groups ); // Pop off last name to prep for readability
                    $imploded    = empty( $plugin_groups ) ? '<em>' . $last_plugin . '</em>' : '<em>' . ( implode( ', ', $plugin_groups ) . '</em> and <em>' . $last_plugin . '</em>' );

                    $rendered .= '<p>' . sprintf( translate_nooped_plural( $this->strings[$type], $count, self::TEXT_DOMAIN ), $imploded, $count ) . '</p>'; // All messages now stored
                }

                /** Setup variables to determine if action links are needed */
                $show_install_link  = $install_link ? '<a href="' . add_query_arg( 'page', $this->menu, admin_url( $this->parent_url_slug ) ) . '">' . translate_nooped_plural( $this->strings['install_link'], $install_link_count, self::TEXT_DOMAIN ) . '</a>' : '';
                $show_activate_link = $activate_link ? '<a href="' . admin_url( 'plugins.php' ) . '">' . translate_nooped_plural( $this->strings['activate_link'], $activate_link_count, self::TEXT_DOMAIN ) . '</a>'  : '';

                /** Define all of the action links */
                $action_links = apply_filters(
                    'tgmpa_notice_action_links',
                    array(
                        'install'  => ( current_user_can( 'install_plugins' ) ) ? $show_install_link : '',
                        'activate' => ( current_user_can( 'activate_plugins' ) ) ? $show_activate_link : '',
                        'dismiss'  => '<a class="dismiss-notice" href="' . add_query_arg( 'tgmpa-dismiss', 'dismiss_admin_notices' ) . '" target="_parent">' . __( 'Dismiss this notice', self::TEXT_DOMAIN ) . '</a>',
                    )
                );

                $action_links = array_filter( $action_links ); // Remove any empty array items
                if ( $action_links )
                    $rendered .= '<p>' . implode( ' | ', $action_links ) . '</p>';

                /** Register the nag messages and prepare them to be processed */
                if ( isset( $this->strings['nag_type'] ) )
                    add_settings_error( 'tgmpa', 'tgmpa', $rendered, sanitize_html_class( strtolower( $this->strings['nag_type'] ), 'updated' ) );
                else
                    add_settings_error( 'tgmpa', 'tgmpa', $rendered, 'updated' );
            }
        }

        /** Admin options pages already output settings_errors, so this is to avoid duplication */
        if ( 'options-general' !== $current_screen->parent_base )
            settings_errors( 'tgmpa' );

    }

    /**
     * Add dismissable admin notices.
     *
     * Appends a link to the admin nag messages. If clicked, the admin notice disappears and no longer is visible to users.
     */
    public function dismiss()
    {
        if ( isset( $_GET[sanitize_key( 'tgmpa-dismiss' )] ) )
            update_user_meta( get_current_user_id(), 'tgmpa_dismissed_notice', 1 );

    }

    /**
     * Add individual plugin to our collection of plugins.
     *
     * If the required keys are not set, the plugin is not added.
     *
     * @param array $plugin Array of plugin arguments.
     */
    public function register( $plugin )
    {
        if ( ! isset( $plugin['slug'] ) || ! isset( $plugin['name'] ) )
            return;

        $this->plugins[$plugin['slug']] = $plugin;

    }

    /**
     * Amend default configuration settings.
     *
     * @param array $config
     */
    public function config( $config )
    {
        $keys = array( 'default_path', 'parent_menu_slug', 'parent_url_slug', 'domain', 'has_notices', 'menu', 'is_automatic', 'message', 'strings' );

        foreach ($keys as $key) {
            if ( isset( $config[$key] ) ) {
                if ( is_array( $config[$key] ) ) {
                    foreach ( $config[$key] as $subkey => $value )
                        $this->{$key}[$subkey] = $value;
                } else {
                    $this->$key = $config[$key];
                }
            }
        }

    }

    /**
     * Amend action link after plugin installation.
     *
     * @param  array $install_actions Existing array of actions
     * @return array Amended array of actions
     */
    public function actions( $install_actions )
    {
        /** Remove action links on the TGMPA install page */
        if ( $this->is_tgmpa_page() )
            return false;

        return $install_actions;

    }

    /**
     * Set file_path key for each installed plugin.
     */
    public function populate_file_path()
    {
        /** Add file_path key for all plugins */
        foreach ( $this->plugins as $plugin => $values )
            $this->plugins[$plugin]['file_path'] = $this->_get_plugin_basename_from_slug( $values['slug'] );

    }

    /**
     * Helper function to extract the file path of the plugin file from the
     * plugin slug, if the plugin is installed.
     *
     * @param  string $slug Plugin slug (typically folder name) as provided by the developer
     * @return string Either file path for plugin if installed, or just the plugin slug
     */
    protected function _get_plugin_basename_from_slug( $slug )
    {
        $keys = array_keys( get_plugins() );

        foreach ($keys as $key) {
            if ( preg_match( '|^' . $slug .'|', $key ) )
                return $key;
        }

        return $slug;

    }

    /**
     * Retrieve plugin data, given the plugin name.
     *
     * Loops through the registered plugins looking for $name. If it finds it,
     * it returns the $data from that plugin. Otherwise, returns false.
     *
     * @param  string         $name Name of the plugin, as it was registered
     * @param  string         $data Optional. Array key of plugin data to return. Default is slug
     * @return string|boolean Plugin slug if found, false otherwise.
     */
    protected function _get_plugin_data_from_name( $name, $data = 'slug' )
    {
        foreach ($this->plugins as $plugin => $values) {
            if ( $name == $values['name'] && isset( $values[$data] ) )
                return $values[$data];
        }

        return false;

    }

    /**
     * Determine if we're on the TGMPA Install page.
     *
     * We use $current_screen when it is available, and a slightly less ideal
     * conditional when it isn't (like when displaying the plugin information
     * thickbox).
     *
     * @global object $current_screen
     * @return boolean True when on the TGMPA page, false otherwise.
     */
    protected function is_tgmpa_page()
    {
        global $current_screen;

        if ( ! is_null( $current_screen ) && $this->parent_menu_slug == $current_screen->parent_file && isset( $_GET['page'] ) && $this->menu === $_GET['page'] )
            return true;

        if ( isset( $_GET['page'] ) && $this->menu === $_GET['page'] )
            return true;

        return false;

    }

    /**
     * Delete dismissable nag option when theme is switched.
     *
     * This ensures that the user is again reminded via nag of required
     * and/or recommended plugins if they re-activate the theme.
     */
    public function update_dismiss()
    {
        delete_user_meta( get_current_user_id(), 'tgmpa_dismissed_notice' );

    }

    /**
     * Forces plugin activation if the parameter 'force_activation' is
     * set to true.
     *
     * This allows theme authors to specify certain plugins that must be
     * active at all times while using the current theme.
     *
     * Please take special care when using this parameter as it has the
     * potential to be harmful if not used correctly. Setting this parameter
     * to true will not allow the specified plugin to be deactivated unless
     * the user switches themes.
     */
    public function force_activation()
    {
        /** Set file_path parameter for any installed plugins */
        $this->populate_file_path();

        $installed_plugins = get_plugins();

        foreach ($this->plugins as $plugin) {
            /** Oops, plugin isn't there so iterate to next condition */
            if ( isset( $plugin['force_activation'] ) && $plugin['force_activation'] && ! isset( $installed_plugins[$plugin['file_path']] ) )
                continue;
            /** There we go, activate the plugin */
            elseif ( isset( $plugin['force_activation'] ) && $plugin['force_activation'] && is_plugin_inactive( $plugin['file_path'] ) )
                activate_plugin( $plugin['file_path'] );
        }

    }

    /**
     * Forces plugin deactivation if the parameter 'force_deactivation'
     * is set to true.
     *
     * This allows theme authors to specify certain plugins that must be
     * deactived upon switching from the current theme to another.
     *
     * Please take special care when using this parameter as it has the
     * potential to be harmful if not used correctly.
     */
    public function force_deactivation()
    {
        /** Set file_path parameter for any installed plugins */
        $this->populate_file_path();

        foreach ($this->plugins as $plugin) {
            /** Only proceed forward if the paramter is set to true and plugin is active */
            if ( isset( $plugin['force_deactivation'] ) && $plugin['force_deactivation'] && is_plugin_active( $plugin['file_path'] ) )
                deactivate_plugins( $plugin['file_path'] );
        }

    }
}
