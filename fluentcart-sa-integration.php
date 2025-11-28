<?php
/**
 * Plugin Name: FluentCart SA Integration
 * Plugin URI:  https://github.com/your-org/fluentcart-sa-integration
 * Description: PayFast, PayFlex, PayStack, The Courier Guy, Meta WhatsApp – built for FluentCart.
 * Version:     0.1.0
 * Author:      Your Name
 * License:     GPL v2 or later
 * Text Domain: fluentcart-sa
 * Domain Path: /languages
 * Requires PHP: 7.4
 * WC tested up to: 8.5
 */

defined( 'ABSPATH' ) || exit;

define( 'FCSI_FILE', __FILE__ );
define( 'FCSI_DIR', plugin_dir_path( __FILE__ ) );
define( 'FCSI_URL', plugin_dir_url( __FILE__ ) );
define( 'FCSI_VER', '0.1.0' );

/* ---------- autoloader ---------- */
spl_autoload_register( function ( $class ) {
    $prefix = 'FCSI\\';
    $base_dir = FCSI_DIR . 'includes/';
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) { return; }
    $relative = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
    if ( file_exists( $file ) ) { require $file; }
} );

/* ---------- bootstrap ---------- */
add_action( 'plugins_loaded', 'fcsi_bootstrap', 20 );
function fcsi_bootstrap() {
    if ( ! class_exists( 'FluentCart\\Core' ) ) {
        add_action( 'admin_notices', 'fcsi_missing_fluentcart' );
        return;
    }
    new FCSI\Core\Plugin();
}

function fcsi_missing_fluentcart() {
    echo '<div class="notice notice-error"><p>'
         . esc_html__( 'FluentCart SA Integration requires the FluentCart plugin.', 'fluentcart-sa' )
         . '</p></div>';
}
