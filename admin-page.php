<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( isset( $_POST['gstv_save_apikey'] ) && check_admin_referer( 'gstv_apikey_action', 'gstv_apikey_nonce' ) ) {
    update_option( 'gstv_api_key', sanitize_text_field( $_POST['gstv_api_key'] ) );
    echo '<div class="notice notice-success is-dismissible"><p>API Key Saved!</p></div>';
}
$api_key = get_option( 'gstv_api_key' );
?>

<div class="wrap gstv-wrapper">
    <h1>Google Sheets Table Viewer</h1>

    <!-- SECTION 1: API SETTINGS -->
    <div class="gstv-card">
        <h2>1. Configuration</h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'gstv_apikey_action', 'gstv_apikey_nonce' ); ?>
            <p>
                <label for="gstv_api_key"><strong>Google Sheets API Key:</strong></label><br>
                <input type="text" id="gstv_api_key" name="gstv_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" placeholder="Paste API Key here">
                <input type="submit" name="gstv_save_apikey" class="button button-secondary" value="Save API Key">
            </p>
        </form>
    </div>

    <!-- SECTION 2: ADD NEW TABLE -->
    <div class="gstv-card">
        <h2>2. Add New Table</h2>
        
        <?php if ( empty( $api_key ) ) : ?>
            <div class="notice notice-error inline"><p>Please save your API Key above to start.</p></div>
        <?php else : ?>
            
            <div class="gstv-step">
                <label><strong>A. Paste Google Sheet Link</strong></label><br>
                <div style="display:flex; gap:10px; max-width:600px;">
                    <input type="text" id="gstv-sheet-url" class="large-text" placeholder="https://docs.google.com/spreadsheets/d/...">
                    <button type="button" id="gstv-fetch-sheets-btn" class="button button-primary">Fetch Data</button>
                </div>
                <span class="spinner gstv-spinner"></span>
            </div>

            <!-- This area is hidden until data is fetched -->
            <div id="gstv-tab-selection-area" style="display:none; margin-top:20px; padding:15px; background:#f9f9f9; border:1px solid #ddd;">
                <label><strong>B. Select a Tab (Sheet)</strong></label><br>
                <select id="gstv-sheet-tab-select" style="min-width:200px; margin-bottom:10px;">
                    <!-- Javascript will fill this -->
                </select>
                
                <!-- NEW: Column Selection Area -->
                <div id="gstv-column-settings" style="display:none; margin:15px 0; padding:15px; background:#fff; border:1px solid #eee;">
                    <label><strong>C. Select & Rename Columns</strong></label>
                    <p class="description">Uncheck to hide. Edit text to rename.</p>
                    <div id="gstv-columns-list" style="max-height:300px; overflow-y:auto; padding:10px 0;">
                        <!-- Columns will appear here -->
                    </div>
                </div>

                <br>
                <label><strong>D. Name this Table</strong></label><br>
                <input type="text" id="gstv-table-name" class="regular-text" placeholder="e.g. Product List">
                
                <br><br>
                <button type="button" id="gstv-save-table-btn" class="button button-primary">Save & Generate Shortcode</button>
            </div>
            
            <div id="gstv-message" style="margin-top:10px;"></div>

        <?php endif; ?>
    </div>

    <!-- SECTION 3: SAVED TABLES -->
    <div class="gstv-card">
        <h2>Your Saved Tables</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th>Table Name</th>
                    <th>Shortcode (Copy this)</th>
                    <th>Source Tab</th>
                    <th>Date Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="gstv-tables-list">
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'gstv_tables';
                $results = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );

                if ( $results ) {
                    foreach ( $results as $row ) {
                        echo '<tr id="gstv-row-' . $row->id . '">';
                        echo '<td>' . $row->id . '</td>';
                        echo '<td><strong>' . esc_html( $row->name ) . '</strong></td>';
                        echo '<td><input type="text" readonly value="[gs_table id=\'' . $row->id . '\']" onclick="this.select()" style="width:100%; text-align:center;"></td>';
                        echo '<td>' . esc_html( $row->tab_name ) . '</td>';
                        echo '<td>' . $row->created_at . '</td>';
                        echo '<td><button class="button gstv-delete-btn" data-id="' . $row->id . '" style="color: #a00;">Delete</button></td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="6">No tables found. Create one above!</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>