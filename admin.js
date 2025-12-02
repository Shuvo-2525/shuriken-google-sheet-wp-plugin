jQuery(document).ready(function($) {

    // Helper: Extract Sheet ID from URL
    function getSheetID(url) {
        var matches = url.match(/\/d\/([a-zA-Z0-9-_]+)/);
        return matches ? matches[1] : null;
    }

    // 1. FETCH DATA CLICK
    $('#gstv-fetch-sheets-btn').on('click', function(e) {
        e.preventDefault();
        
        var url = $('#gstv-sheet-url').val();
        var sheetID = getSheetID(url);
        var $spinner = $(this).parent().next('.gstv-spinner');
        var $msg = $('#gstv-message');

        if (!sheetID) {
            alert('Invalid Google Sheet URL. Please look for /d/YOUR_ID/ in the link.');
            return;
        }

        $spinner.addClass('is-active');
        $msg.html('');
        $('#gstv-tab-selection-area').hide();

        $.ajax({
            url: gstv_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gstv_fetch_sheets', // Calls function in ajax-functions.php
                nonce: gstv_vars.nonce,
                sheet_id: sheetID
            },
            success: function(response) {
                $spinner.removeClass('is-active');
                
                if (response.success) {
                    var tabs = response.data;
                    var options = '';
                    
                    // Populate Dropdown
                    $.each(tabs, function(index, tabName) {
                        options += '<option value="' + tabName + '">' + tabName + '</option>';
                    });
                    
                    $('#gstv-sheet-tab-select').html(options);
                    $('#gstv-tab-selection-area').show(); // Reveal the hidden area
                    
                    // Auto-fill table name if empty
                    if( $('#gstv-table-name').val() === '' ) {
                        $('#gstv-table-name').val('My Sheet Table');
                    }
                    
                } else {
                    $msg.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $spinner.removeClass('is-active');
                alert('Server error.');
            }
        });
    });

    // 2. SAVE TABLE CLICK
    $('#gstv-save-table-btn').on('click', function(e) {
        e.preventDefault();
        
        var url = $('#gstv-sheet-url').val();
        var sheetID = getSheetID(url);
        var tabName = $('#gstv-sheet-tab-select').val();
        var tableName = $('#gstv-table-name').val();
        
        if( !tableName ) {
            alert('Please give the table a name.');
            return;
        }

        $.ajax({
            url: gstv_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gstv_save_table',
                nonce: gstv_vars.nonce,
                sheet_id: sheetID,
                tab_name: tabName,
                name: tableName
            },
            success: function(response) {
                if (response.success) {
                    alert('Table Saved!');
                    location.reload(); // Reload to show in the list
                } else {
                    alert('Error saving: ' + response.data);
                }
            }
        });
    });

    // 3. DELETE BUTTON CLICK
    $('.gstv-delete-btn').on('click', function(e) {
        e.preventDefault();
        if(!confirm('Are you sure you want to delete this table?')) return;

        var id = $(this).data('id');
        var $row = $('#gstv-row-' + id);

        $.ajax({
            url: gstv_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gstv_delete_table',
                nonce: gstv_vars.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut();
                } else {
                    alert('Error deleting.');
                }
            }
        });
    });

});