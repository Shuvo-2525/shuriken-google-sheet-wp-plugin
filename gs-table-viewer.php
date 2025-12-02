<?php
/**
 * Plugin Name: GS Table Viewer
 * Description: Fetch Google Sheets data, select tabs, rename columns, and display tables via shortcode.
 * Version: 1.3
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'GSTV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GSTV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// 1. Activation Hook
register_activation_hook( __FILE__, 'gstv_create_table' );

function gstv_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gstv_tables';
    $charset_collate = $wpdb->get_charset_collate();

    // 'settings' column will hold our JSON column config
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

// 2. Admin Menu
add_action( 'admin_menu', 'gstv_add_admin_menu' );
function gstv_add_admin_menu() {
    add_menu_page( 'GS Table Viewer', 'GS Tables', 'manage_options', 'gs-table-viewer', 'gstv_admin_page_html', 'dashicons-media-spreadsheet' );
}

// 3. Admin Scripts
add_action( 'admin_enqueue_scripts', 'gstv_enqueue_admin_scripts' );
function gstv_enqueue_admin_scripts( $hook ) {
    if ( 'toplevel_page_gs-table-viewer' !== $hook ) { return; }
    wp_enqueue_script( 'gstv-admin-js', GSTV_PLUGIN_URL . 'admin.js', array( 'jquery' ), '1.0', true );
    wp_enqueue_style( 'gstv-admin-css', GSTV_PLUGIN_URL . 'admin.css' );
    wp_localize_script( 'gstv-admin-js', 'gstv_vars', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'gstv_admin_nonce' ) ));
}

// 4. Frontend Scripts
add_action( 'wp_enqueue_scripts', 'gstv_enqueue_frontend_scripts' );
function gstv_enqueue_frontend_scripts() {
    wp_enqueue_style( 'gstv-frontend-css', GSTV_PLUGIN_URL . 'frontend.css' );
    wp_enqueue_script( 'gstv-frontend-js', GSTV_PLUGIN_URL . 'frontend.js', array( 'jquery' ), '1.0', true );
    wp_localize_script( 'gstv-frontend-js', 'gstv_front_vars', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ));
}

// 5. Helper Function to Get Table HTML (UPDATED FOR COLUMNS)
function gstv_get_table_html( $table_id ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gstv_tables';
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $table_id ) );

    if ( ! $row ) return "GS Table: Table ID $table_id not found.";

    // Parse Settings
    $settings = !empty($row->settings) ? json_decode($row->settings, true) : null;

    // Cache Check (30 seconds)
    $cache_key = 'gstv_table_data_' . $table_id;
    $cached_data = get_transient( $cache_key );
    if ( false !== $cached_data ) return $cached_data; 

    $api_key = get_option( 'gstv_api_key' );
    if ( empty( $api_key ) ) return 'GS Table: API Key missing.';

    $tab_encoded = rawurlencode( $row->tab_name );
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$row->sheet_id}/values/{$tab_encoded}?key={$api_key}";

    $response = wp_remote_get( $url );
    if ( is_wp_error( $response ) ) return 'GS Table Error: ' . $response->get_error_message();

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( isset( $data['error'] ) ) return 'GS Table API Error: ' . $data['error']['message'];
    if ( empty( $data['values'] ) ) return 'GS Table: No data found.';

    // --- FILTER & RENDER LOGIC ---
    
    // 1. Identify which columns to show and what to name them
    $original_header = array_shift( $data['values'] ); // First row from Google
    
    $active_indices = []; // Array of indexes to keep: [0, 2, 5]
    $display_headers = []; // Array of names: ["Product", "Price"]

    if ( $settings && is_array( $settings ) ) {
        // Use user settings
        foreach ( $settings as $col_setting ) {
            $idx = intval( $col_setting['index'] );
            // Ensure this index actually exists in the sheet
            if ( isset( $original_header[$idx] ) ) {
                $active_indices[] = $idx;
                $display_headers[] = $col_setting['header']; // Use renamed header
            }
        }
    } else {
        // Fallback: Show All if no settings found (legacy support)
        $active_indices = array_keys( $original_header );
        $display_headers = $original_header;
    }

    // 2. Build Table HTML
    $html = '<table class="gstv-table">';
    
    // Header
    $html .= '<thead><tr>';
    foreach ( $display_headers as $col_name ) {
        $html .= '<th>' . esc_html( $col_name ) . '</th>';
    }
    $html .= '</tr></thead>';

    // Body
    $html .= '<tbody>';
    foreach ( $data['values'] as $row_data ) {
        $html .= '<tr>';
        foreach ( $active_indices as $idx ) {
            // Get data for this specific column index
            $cell_val = isset( $row_data[$idx] ) ? $row_data[$idx] : '';
            $html .= '<td>' . esc_html( $cell_val ) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';

    set_transient( $cache_key, $html, 30 ); 
    return $html;
}

// 6. Shortcode
add_shortcode( 'gs_table', 'gstv_render_shortcode' );
function gstv_render_shortcode( $atts ) {
    $a = shortcode_atts( array( 'id' => 0 ), $atts );
    $table_id = intval( $a['id'] );
    if ( ! $table_id ) return '<!-- GS Table: No ID provided -->';
    $output = '<div class="gstv-table-container gstv-live-table" data-id="' . $table_id . '">';
    $output .= gstv_get_table_html( $table_id );
    $output .= '</div>';
    return $output;
}

// 7. Frontend AJAX
add_action( 'wp_ajax_gstv_refresh_table', 'gstv_ajax_refresh_table' );
add_action( 'wp_ajax_nopriv_gstv_refresh_table', 'gstv_ajax_refresh_table' );
function gstv_ajax_refresh_table() {
    $table_id = intval( $_POST['id'] );
    echo gstv_get_table_html( $table_id );
    wp_die();
}

function gstv_admin_page_html() { require_once GSTV_PLUGIN_DIR . 'admin-page.php'; }
require_once GSTV_PLUGIN_DIR . 'ajax-functions.php';