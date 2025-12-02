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
            alert('Invalid Google Sheet URL.');
            return;
        }

        $spinner.addClass('is-active');
        $msg.html('');
        $('#gstv-tab-selection-area').hide();
        $('#gstv-column-settings').hide(); // Hide columns if resetting

        $.ajax({
            url: gstv_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gstv_fetch_sheets',
                nonce: gstv_vars.nonce,
                sheet_id: sheetID
            },
            success: function(response) {
                $spinner.removeClass('is-active');
                
                if (response.success) {
                    var tabs = response.data;
                    var options = '<option value="">-- Select a Tab --</option>'; // Default empty option
                    
                    $.each(tabs, function(index, tabName) {
                        options += '<option value="' + tabName + '">' + tabName + '</option>';
                    });
                    
                    $('#gstv-sheet-tab-select').html(options);
                    $('#gstv-tab-selection-area').show();
                    
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

    // 1.5 TAB CHANGED -> FETCH COLUMNS (NEW)
    $('#gstv-sheet-tab-select').on('change', function() {
        var tabName = $(this).val();
        var url = $('#gstv-sheet-url').val();
        var sheetID = getSheetID(url);

        // Clear previous columns
        $('#gstv-column-settings').hide();
        $('#gstv-columns-list').empty();

        if (!tabName) return;

        var $msg = $('#gstv-message');
        $msg.html('Fetching columns...');

        $.ajax({
            url: gstv_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'gstv_fetch_columns',
                nonce: gstv_vars.nonce,
                sheet_id: sheetID,
                tab_name: tabName
            },
            success: function(response) {
                $msg.html('');
                if (response.success) {
                    var cols = response.data;
                    var html = '';
                    
                    // Build the UI for each column
                    $.each(cols, function(i, colName) {
                        html += '<div style="display:flex; gap:10px; margin-bottom:5px; align-items:center; border-bottom:1px solid #f0f0f0; padding:5px 0;">';
                        
                        // Checkbox to show/hide
                        html += '<input type="checkbox" class="gstv-col-check" value="'+i+'" checked title="Show this column"> ';
                        
                        // Input to rename
                        html += '<input type="text" class="gstv-col-name regular-text" value="'+colName+'" placeholder="Column Header" style="width:100%;">';
                        
                        html += '</div>';
                    });
                    
                    $('#gstv-columns-list').html(html);
                    $('#gstv-column-settings').fadeIn();
                } else {
                    $msg.html('<div class="notice notice-error inline"><p>' + response.data + '</p></div>');
                }
            }
        });
    });

    // 2. SAVE TABLE CLICK (UPDATED)
    $('#gstv-save-table-btn').on('click', function(e) {
        e.preventDefault();
        
        var url = $('#gstv-sheet-url').val();
        var sheetID = getSheetID(url);
        var tabName = $('#gstv-sheet-tab-select').val();
        var tableName = $('#gstv-table-name').val();
        
        if( !tabName ) { alert('Please select a tab.'); return; }
        if( !tableName ) { alert('Please give the table a name.'); return; }

        // GATHER COLUMN SETTINGS
        var columnSettings = [];
        $('#gstv-columns-list .gstv-col-check').each(function() {
            if( $(this).is(':checked') ) {
                var index = $(this).val();
                var newName = $(this).siblings('.gstv-col-name').val();
                
                columnSettings.push({
                    index: index,
                    header: newName
                });
            }
        });

        if(columnSettings.length === 0) {
            alert('Please select at least one column to display.');
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
                name: tableName,
                settings: JSON.stringify(columnSettings) // Send as JSON string
            },
            success: function(response) {
                if (response.success) {
                    alert('Table Saved!');
                    location.reload();
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