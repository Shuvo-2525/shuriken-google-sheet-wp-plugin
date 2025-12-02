<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// 1. FETCH SHEETS (TABS)
add_action( 'wp_ajax_gstv_fetch_sheets', 'gstv_ajax_fetch_sheets' );

function gstv_ajax_fetch_sheets() {
    check_ajax_referer( 'gstv_admin_nonce', 'nonce' );

    $api_key = get_option( 'gstv_api_key' );
    $sheet_id = sanitize_text_field( $_POST['sheet_id'] );

    if ( empty( $api_key ) ) wp_send_json_error( 'API Key is missing.' );

    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}?key={$api_key}&includeGridData=false";
    $response = wp_remote_get( $url );

    if ( is_wp_error( $response ) ) wp_send_json_error( 'Connection Error: ' . $response->get_error_message() );

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( isset( $data['error'] ) ) {
        wp_send_json_error( 'Google API Error: ' . ($data['error']['message'] ?? 'Unknown') );
    }

    $tabs = array();
    if ( isset( $data['sheets'] ) ) {
        foreach ( $data['sheets'] as $sheet ) {
            $tabs[] = $sheet['properties']['title'];
        }
        wp_send_json_success( $tabs );
    } else {
        wp_send_json_error( 'No sheets found.' );
    }
}

// 2. FETCH COLUMNS (NEW FUNCTIONALITY)
add_action( 'wp_ajax_gstv_fetch_columns', 'gstv_ajax_fetch_columns' );

function gstv_ajax_fetch_columns() {
    check_ajax_referer( 'gstv_admin_nonce', 'nonce' );

    $api_key = get_option( 'gstv_api_key' );
    $sheet_id = sanitize_text_field( $_POST['sheet_id'] );
    $tab_name = sanitize_text_field( $_POST['tab_name'] );

    if ( empty( $api_key ) ) wp_send_json_error( 'API Key missing.' );

    // Fetch only the first row (headers)
    $tab_encoded = rawurlencode( $tab_name );
    // Range syntax: TabName!1:1 means "Row 1 of TabName"
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}/values/{$tab_encoded}!1:1?key={$api_key}";

    $response = wp_remote_get( $url );
    if ( is_wp_error( $response ) ) wp_send_json_error( $response->get_error_message() );

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    
    // Return the first row of data
    if ( ! empty( $data['values'][0] ) ) {
        wp_send_json_success( $data['values'][0] );
    } else {
        wp_send_json_error( 'No headers found in the first row.' );
    }
}

// 3. SAVE TABLE (UPDATED)
add_action( 'wp_ajax_gstv_save_table', 'gstv_ajax_save_table' );

function gstv_ajax_save_table() {
    check_ajax_referer( 'gstv_admin_nonce', 'nonce' );
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'gstv_tables';

    $sheet_id = sanitize_text_field( $_POST['sheet_id'] );
    $tab_name = sanitize_text_field( $_POST['tab_name'] );
    $name     = sanitize_text_field( $_POST['name'] );
    
    // Capture the JSON string of column settings
    // We use stripslashes because WP adds slashes to $_POST data
    $settings = isset($_POST['settings']) ? stripslashes($_POST['settings']) : ''; 

    $wpdb->insert( 
        $table_name, 
        array( 
            'name' => $name, 
            'sheet_id' => $sheet_id, 
            'tab_name' => $tab_name,
            'settings' => $settings, // Save the column config here
            'created_at' => current_time( 'mysql' )
        ) 
    );

    wp_send_json_success( $wpdb->insert_id );
}

// 4. DELETE TABLE
add_action( 'wp_ajax_gstv_delete_table', 'gstv_ajax_delete_table' );

function gstv_ajax_delete_table() {
    check_ajax_referer( 'gstv_admin_nonce', 'nonce' );
    global $wpdb;
    $table_name = $wpdb->prefix . 'gstv_tables';
    $id = intval( $_POST['id'] );
    $wpdb->delete( $table_name, array( 'id' => $id ) );
    wp_send_json_success();
}