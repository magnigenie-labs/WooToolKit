<?php

/**
 * @package  WooToolkit
 * @author   Dipak Kumar <dipak.pusti@magnigeeks.com>
 * @version  1.0.0
 * @access   public
 */

class WooToolkit {

    /**
     * Adds the generic hooks that are required throughout
     * the theme. Primary class used by wootoolkit
     * @since 1.0.0
     */
    public function __construct() {

        add_action( 'init', 
            array( $this, 'wootoolkit_load_kits' ), 1, 0 );

        add_action( 'admin_notices', 
            array( $this, 'check_required_plugins' ) );

        add_action( 'admin_menu', 
            array( $this, 'wootoolkit_settings_page' ), 1, 0 );

        add_action( 'admin_enqueue_scripts', 
            array( $this, 'wootoolkit_css_enqueue' ), 1, 0 );

        add_filter( 'plugin_action_links_' . WOOTOOLKIT_BASE,
            array( $this, 'add_plugin_action_links' ) );

        register_activation_hook( WOOTOOLKIT_FILE, 
            array( $this, 'install' ) );
    }

    /**
     * Will be excecuted on activation of theme
     * Registers all local kits as active kits
     * in databse
     *
     * @since 1.0.0
     */
    public function install() {

        // Getting the kits
        $default_kits = $this->get_kits();

        // Putting the default kits to activated kits 
        $activated_kits = array();
        if( count( $default_kits ) > 0 ) {
            foreach ( $default_kits as $key => $kitdetails ) {
                array_push( $activated_kits, $key );
            }
        }

        // Resetting wordpress options for activated kits
        update_option( '_woo_active_kits', array() );

        // Setting wordpress options for activated kits
        update_option( '_woo_active_kits', $activated_kits );
    }

    /**
    *
    * Check if woocommerce is installed and activated and if not
    * activated then deactivate woocommerce mailchimp discount.
    *
    */
    public function check_required_plugins() {

        //Check if woocommerce is installed and activated
        if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) { ?>

            <div id="message" class="error">
                <p>WooToolkit requires <a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a> to be activated in order to work. Please install and activate <a href="<?php echo admin_url('/plugin-install.php?tab=search&amp;type=term&amp;s=WooCommerce'); ?>" target="">WooCommerce</a> first.</p>
            </div>

            <?php
            deactivate_plugins( '/wootoolkit/wootoolkit.php' );
        }

    }

    /**
     * Set setting menu link for plugin WooToolkit
     *
     * @since 1.0.0
     */
    public function add_plugin_action_links( $links ) {
        $wootoolkit_settings_page = array(
            '<a href="' . admin_url( 'admin.php?page=woo-toolkit-settings' ) . '">'.__( 'Settings', 'wootoolkit' ).'</a>',
        );
        return array_merge( $links, $wootoolkit_settings_page );
    }

    /**
     * Gets all active and valid kits to include
     *
     * @since 1.0.0
     */
    public function wp_get_active_and_valid_kits() {
    
        $kits = array();
        $active_kits = get_option( '_woo_active_kits', array() );

        if ( empty( $active_kits ) || wp_installing() )
            return $kits;

        foreach ( $active_kits as $kit ) {
            if ( ! validate_file( $kit ) // $kit must validate as file
                && '.php' == substr( $kit, -4 ) // $kit must end with '.php'
                && file_exists( WOOTOOLKIT_PATH . '/kits/' . $kit ) // $kit must exist
            )
            $kits[] = WOOTOOLKIT_PATH . '/kits/' . $kit;
        }
        return $kits;
    }

    /**
     * Gets the activated kits from wordpress
     * includes theme to wootoolkit one by one
     *
     * @since 1.0.0
     */
    public function wootoolkit_load_kits() {

        // Getting all the kits
        $active_kits = $this->wp_get_active_and_valid_kits();

        // Include active kits
        foreach ( $active_kits as $kit ) {
            include_once( $kit );
        }
        unset( $kit );
    }

    /**
     * Css enqueue for wootoolkit
     *
     * @since 1.0.0
     */
    public function wootoolkit_css_enqueue() {
        wp_enqueue_style( 'wootoolkit-admin', plugins_url( 'assets/css/admin.css', dirname( __FILE__ ) ) );
    }

    /**
     * Menu and submenu pages for wootoolkit
     *
     * @since 1.0.0
     */
    public function wootoolkit_settings_page() {
        
        add_menu_page( 
            __( 'Woo Toolkit', 'wootoolkit' ),
            __( 'Woo Toolkit', 'wootoolkit' ),
            'manage_options',
            'woo-toolkit', 
            '', 
            plugins_url( 'assets/images/woo_toolkit_icon.png', dirname( __FILE__ ) ), 
            '56' );

        add_submenu_page( 
            'woo-toolkit', 
            __( 'Toolkits', 'wootoolkit' ), 
            __( 'Installed Kits', 'wootoolkit' ),
            'manage_options', 
            'woo-toolkit',
            array( $this, 'wootoolkit_toolkits_html' ) );
    }

    /**
     * Toolkits page to view all installed kits
     *
     * @since 1.0.0
     */
    public function wootoolkit_toolkits_html() {
    
        // check user capabilities
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        require WOOTOOLKIT_PATH . 'templates/toolkits.php';
    }

    /**
     * This functions return all the kits when called
     * those are present inside the specified folder
     *
     * @param string | folder path that holds the kits
     * @return array | Array holding all the kit details
     * @since 1.0.0
     */
    public function get_kits( $kit_folder = '') {

        $wp_plugins = array();
        
        $plugin_root = WOOTOOLKIT_PATH . 'kits';
        if ( !empty( $kit_folder ) )
            $plugin_root .= $kit_folder;

        // Files in wp-content/plugins directory
        $plugins_dir = @opendir( $plugin_root );
        $plugin_files = array();
        if ( $plugins_dir ) {
            while ( ( $file = readdir( $plugins_dir ) ) !== false ) {
                if ( substr( $file, 0, 1 ) == '.' )
                    continue;
                if ( is_dir( $plugin_root.'/'.$file ) ) {
                    $plugins_subdir = @opendir( $plugin_root.'/'.$file );
                    if ( $plugins_subdir ) {
                        while ( ( $subfile = readdir( $plugins_subdir ) ) !== false ) {
                            if ( substr( $subfile, 0, 1 ) == '.' )
                                continue;
                            if ( substr( $subfile, -4 ) == '.php' )
                                $plugin_files[] = "$file/$subfile";
                        }
                        closedir( $plugins_subdir );
                    }
                } else {
                    if ( substr($file, -4) == '.php' )
                        $plugin_files[] = $file;
                }
            }
            closedir( $plugins_dir );
        }

        if ( empty( $plugin_files ) )
            return $wp_plugins;

        foreach ( $plugin_files as $plugin_file ) {
            
            if ( !is_readable( "$plugin_root/$plugin_file" ) )
                continue;

            $plugin_data = $this->get_kit_data( "$plugin_root/$plugin_file", false, false );

            if ( empty ( $plugin_data['Name'] ) )
                continue;

            $wp_plugins[plugin_basename( $plugin_file )] = $plugin_data;
        }

        uasort( $wp_plugins, '_sort_uname_callback' );
        return $wp_plugins;
    }

    /**
     * Will get all the kits from the specified path
     * and validates the file that holds kit information
     *
     * @param string | path of the kit file
     * @return array | Array holding the kit information
     * @since 1.0.0
     */
    public function get_kit_data( $kit_file, $markup = true, $translate = true ) {

        $default_headers = array(
            'Name'          => 'Kit Name',
            'PluginURI'     => 'Kit URI',
            'PluginSlug'    => 'Kit Slug',
            'Version'       => 'Version',
            'Description'   => 'Description',
            'Author'        => 'Author',
            'AuthorURI'     => 'Author URI',
            'TextDomain'    => 'Text Domain',
            'DomainPath'    => 'Domain Path',
        );

        $kit_data = get_file_data( $kit_file, $default_headers, 'plugin' );

        // If no text domain is defined fall back to the kit slug.
        if ( ! $kit_data['TextDomain'] ) {
            $kit_slug = dirname( plugin_basename( $kit_file ) );
            if ( '.' !== $kit_slug && false === strpos( $kit_slug, '/' ) ) {
                $kit_data['TextDomain'] = $kit_slug;
            }
        }

        if ( $markup || $translate ) {
            $kit_data = _get_plugin_data_markup_translate( $kit_file, $kit_data, $markup, $translate );
        } else {
            $kit_data['Title']      = $kit_data['Name'];
            $kit_data['AuthorName'] = $kit_data['Author'];
        }

        return $kit_data;
    }

    /**
     * Functions determines the current status of kit
     *
     * @param string | Plugin index file as string
     * @return string | Returns the status as 'active' or 'inactive'
     * @since 1.0.0
     */
    public function get_kit_status( $kit ) {

        // Getting currently active kits
        $active_kits = get_option( '_woo_active_kits', array() );

        // Checking status of current kit and return
        if( in_array( $kit, $active_kits) ) {
            return 'active';
        } else {
            return 'inactive';
        }
    }

    /**
     * Functions determines the total count of kits
     * Count as Active, Inactive or for all kits
     *
     * @param string | 'all', 'active', 'inactive'
     * @return int | if status is specified
     * @return array | array of count for all 3 if nothing specified
     * @since 1.0.0
     */
    public function get_kit_count( $kit_status = '' ) {

        $count_all = 0;
        $count_active = 0;
        $count_inactive = 0;

        // Getting all the kits
        $default_kits = $this->get_kits();

        $count_all = count( $default_kits );

        // Getting existing activated kits
        $active_kits = get_option( '_woo_active_kits', array() );

        foreach ( $default_kits as $key => $kit ) {
            
            // Checking all the kits for active or inactive
            if( in_array( $key, $active_kits)) {
                $count_active++;
            } else {
                $count_inactive++;
            }
        }

        if( $kit_status && '' != $kit_status ) {

            // Returns a single count as per requested
            switch ($kit_status) {
                case 'all':
                    return $count_all;
                    break;
                
                case 'active':
                    return $count_active;
                    break;

                case 'inactive':
                    return $count_inactive;
                    break;

                default:
                    break;
            }

        } else {

            // Returns an array with all the values
            return array(
                'all' => $count_all,
                'active' => $count_active,
                'inactive' => $count_inactive,
            );
        }
    }

    /**
     * Activates single kit when requested 
     *
     * @param string | kit index text file text
     * @since 1.0.0
     */
    public function activate_kit( $kit ) {

        // Getting existing activated kits
        $active_kits = get_option( '_woo_active_kits', array() );

        // Checking current kit if already activated
        if( !in_array( $kit, $active_kits ) ) {

            // Adding current item to active kit list
            array_push( $active_kits, $kit );
        }

        // Updating the options with new value
        update_option( '_woo_active_kits', $active_kits );
    }

    /**
     * Deactivates single kit when requested 
     *
     * @param string | kit index text file text
     * @since 1.0.0
     */
    public function deactivate_kit( $kit ) {

        // Getting existing activated kits
        $active_kits = get_option( '_woo_active_kits', array() );

        // Checking current kit if activated
        if( in_array( $kit, $active_kits) ) {

            $key = array_search( $kit, $active_kits);

            // Removing current item from active kit list
            if ( false !== $key ) {
                unset( $active_kits[ $key ] );
            }
        }

        // Updating the options with new array of active plugins
        update_option( '_woo_active_kits', $active_kits );
    }

    /**
     * Activates multiple kit when requested 
     *
     * @param array | array kit index text file text
     * @since 1.0.0
     */
    public function activate_selected_kits( $kits ) {

        // Getting existing activated kits
        $active_kits = get_option( '_woo_active_kits', array() );

        // Adding selected kits to active list
        foreach ( $kits as $key => $value ) {
            
            // Checking if already not in array
            if( !in_array( $value, $active_kits ) ) {
                array_push( $active_kits, $value );
            }
        }

        // Updating the options with new array of active plugins
        update_option( '_woo_active_kits', $active_kits );
    }

    /**
     * Deactivates multiple kit when requested 
     *
     * @param array | array kit index text file text
     * @since 1.0.0
     */
    public function deactivate_selected_kits( $kits ) {

        // Getting existing activated kits
        $active_kits = get_option( '_woo_active_kits', array() );

        // Adding selected kits to active list
        foreach ( $kits as $key => $value ) {

            // Checking current kit in activated items
            if( in_array( $value, $active_kits ) ) {

                $item_key = array_search( $value, $active_kits );

                // Removing current item from active kit list
                if ( false !== $item_key ) {
                    unset( $active_kits[ $item_key ] );
                }
            }
        }

        // Updating the options with new array of active plugins
        update_option( '_woo_active_kits', $active_kits );
    }

    /**
     * Indivisual kit action links 
     *
     * @param array
     * @since 1.0.0
     */
    public function kit_row_actions( $actions, $always_visible = false ) {
        
        $action_count = count( $actions );
        
        $i = 0;

        if ( !$action_count )
            return '';

        $out = '<div class="' . ( $always_visible ? 'row-actions visible' : 'row-actions' ) . '">';
        foreach ( $actions as $action => $link ) {
            ++$i;
            ( $i == $action_count ) ? $sep = '' : $sep = ' | ';
            $out .= "<span class='$action'>$link$sep</span>";
        }
        $out .= '</div>';

        $out .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details' ) . '</span></button>';

        return $out;
    }
}
new WooToolkit();