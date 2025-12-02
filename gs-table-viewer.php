<?php
/**
 * Plugin Name: GS Table Viewer
 * Description: Fetch Google Sheets data, select tabs, and generate shortcodes to display tables.
 * Version: 1.1
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'GSTV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GSTV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// 1. Activation Hook: Create Database Table
register_activation_hook( __FILE__, 'gstv_create_table' );

function gstv_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gstv_tables';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        sheet_id varchar(100) NOT NULL,
        tab_name varchar(100) NOT NULL,
        settings text DEFAULT '',
        created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( 'gstv_api_key', '' );
}

// 2. Add Admin Menu
add_action( 'admin_menu', 'gstv_add_admin_menu' );

function gstv_add_admin_menu() {
    add_menu_page( 'GS Table Viewer', 'GS Tables', 'manage_options', 'gs-table-viewer', 'gstv_admin_page_html', 'dashicons-media-spreadsheet' );
}

// 3. Enqueue Scripts
add_action( 'admin_enqueue_scripts', 'gstv_enqueue_admin_scripts' );
function gstv_enqueue_admin_scripts( $hook ) {
    if ( 'toplevel_page_gs-table-viewer' !== $hook ) { return; }
    wp_enqueue_script( 'gstv-admin-js', GSTV_PLUGIN_URL . 'admin.js', array( 'jquery' ), '1.0', true );
    wp_enqueue_style( 'gstv-admin-css', GSTV_PLUGIN_URL . 'admin.css' );
    wp_localize_script( 'gstv-admin-js', 'gstv_vars', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'gstv_admin_nonce' ) ));
}

// Enqueue Frontend Styles
add_action( 'wp_enqueue_scripts', 'gstv_enqueue_frontend_scripts' );
function gstv_enqueue_frontend_scripts() {
    wp_enqueue_style( 'gstv-frontend-css', GSTV_PLUGIN_URL . 'frontend.css' );
}

// 4. Admin Page HTML
function gstv_admin_page_html() {
    require_once GSTV_PLUGIN_DIR . 'admin-page.php';
}

// 5. Shortcode Registration (THE NEW PART)
add_shortcode( 'gs_table', 'gstv_render_shortcode' );

function gstv_render_shortcode( $atts ) {
    $a = shortcode_atts( array( 'id' => 0 ), $atts );
    $table_id = intval( $a['id'] );
    
    if ( ! $table_id ) return '<!-- GS Table: No ID provided -->';

    // 1. Get Table Details from DB
    global $wpdb;
    $table_name = $wpdb->prefix . 'gstv_tables';
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $table_id ) );

    if ( ! $row ) return "GS Table: Table ID $table_id not found.";

    // 2. Check Cache (Transient) - Cache for 1 hour (3600 seconds)
    $cache_key = 'gstv_table_data_' . $table_id;
    $cached_data = get_transient( $cache_key );

    if ( false !== $cached_data ) {
        return $cached_data; // Return cached HTML
    }

    // 3. Fetch Data from Google if not cached
    $api_key = get_option( 'gstv_api_key' );
    if ( empty( $api_key ) ) return 'GS Table: API Key missing.';

    // URL Encode the tab name in case it has spaces
    $tab_encoded = rawurlencode( $row->tab_name );
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$row->sheet_id}/values/{$tab_encoded}?key={$api_key}";

    $response = wp_remote_get( $url );

    if ( is_wp_error( $response ) ) {
        return 'GS Table Error: ' . $response->get_error_message();
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( isset( $data['error'] ) ) {
        return 'GS Table API Error: ' . $data['error']['message'];
    }

    if ( empty( $data['values'] ) ) {
        return 'GS Table: No data found in this tab.';
    }

    // 4. Build HTML
    $html = '<div class="gstv-table-container">';
    $html .= '<table class="gstv-table">';
    
    // Header (First Row)
    $header = array_shift( $data['values'] ); // Removes first row from array
    $html .= '<thead><tr>';
    foreach ( $header as $col ) {
        $html .= '<th>' . esc_html( $col ) . '</th>';
    }
    $html .= '</tr></thead>';

    // Body (Remaining Rows)
    $html .= '<tbody>';
    foreach ( $data['values'] as $row_data ) {
        $html .= '<tr>';
        foreach ( $row_data as $cell ) {
            $html .= '<td>' . esc_html( $cell ) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';

    // 5. Save Cache and Return
    set_transient( $cache_key, $html, 3600 ); // Cache for 1 hour

    return $html;
}

// 6. Include AJAX Logic
require_once GSTV_PLUGIN_DIR . 'ajax-functions.php';