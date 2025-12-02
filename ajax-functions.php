<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// 1. FETCH SHEETS FROM GOOGLE
add_action( 'wp_ajax_gstv_fetch_sheets', 'gstv_ajax_fetch_sheets' );

function gstv_ajax_fetch_sheets() {
    check_ajax_referer( 'gstv_admin_nonce', 'nonce' );

    $api_key = get_option( 'gstv_api_key' );
    $sheet_id = sanitize_text_field( $_POST['sheet_id'] );

    if ( empty( $api_key ) ) {
        wp_send_json_error( 'API Key is missing in settings.' );
    }

    // Google API Endpoint to get Spreadsheet Metadata (Tab names)
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$sheet_id}?key={$api_key}&includeGridData=false";

    $response = wp_remote_get( $url );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Connection Error: ' . $response->get_error_message() );
    }

    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    // Check for Google API Errors
    if ( isset( $data['error'] ) ) {
        $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown Google Error';
        // Common hint for users
        if ( strpos( $error_msg, 'The caller does not have permission' ) !== false ) {
            $error_msg .= ' (Make sure your Sheet is set to "Anyone with the link can view")';
        }
        wp_send_json_error( 'Google API Error: ' . $error_msg );
    }

    // Extract Tab Names
    $tabs = array();
    if ( isset( $data['sheets'] ) ) {
        foreach ( $data['sheets'] as $sheet ) {
            $tabs[] = $sheet['properties']['title'];
        }
        wp_send_json_success( $tabs );
    } else {
        wp_send_json_error( 'No sheets found inside this file.' );
    }
}

// 2. SAVE TABLE TO DATABASE
add_action( 'wp_ajax_gstv_save_table', 'gstv_ajax_save_table' );

function gstv_ajax_save_table() {
    check_ajax_referer( 'gstv_admin_nonce', 'nonce' );
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'gstv_tables';

    $sheet_id = sanitize_text_field( $_POST['sheet_id'] );
    $tab_name = sanitize_text_field( $_POST['tab_name'] );
    $name     = sanitize_text_field( $_POST['name'] );

    $wpdb->insert( 
        $table_name, 
        array( 
            'name' => $name, 
            'sheet_id' => $sheet_id, 
            'tab_name' => $tab_name,
            'created_at' => current_time( 'mysql' )
        ) 
    );

    wp_send_json_success( $wpdb->insert_id );
}

// 3. DELETE TABLE
add_action( 'wp_ajax_gstv_delete_table', 'gstv_ajax_delete_table' );

function gstv_ajax_delete_table() {
    check_ajax_referer( 'gstv_admin_nonce', 'nonce' );

    global $wpdb;
    $table_name = $wpdb->prefix . 'gstv_tables';
    $id = intval( $_POST['id'] );

    $wpdb->delete( $table_name, array( 'id' => $id ) );

    wp_send_json_success();
}