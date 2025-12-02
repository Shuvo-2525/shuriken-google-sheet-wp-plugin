jQuery(document).ready(function($) {
    
    // Check if there are any live tables on the page
    var $tables = $('.gstv-live-table');
    
    if ($tables.length > 0) {
        
        // Run every 10 seconds (10000 milliseconds)
        setInterval(function() {
            
            $tables.each(function() {
                var $container = $(this);
                var tableID = $container.data('id');
                
                // Fetch new data
                $.ajax({
                    url: gstv_front_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'gstv_refresh_table',
                        id: tableID
                    },
                    success: function(response) {
                        // Only update if response is valid HTML (not an error message starting with GS Table Error)
                        if (response && response.indexOf('GS Table') === -1) {
                             $container.html(response);
                        } else if (response.indexOf('GS Table Error') === -1) {
                            // If it's a valid table (even if error-free), update it
                             $container.html(response);
                        }
                    }
                });
            });
            
        }, 10000); // <-- Change 10000 to 5000 for 5 seconds speed
    }
});