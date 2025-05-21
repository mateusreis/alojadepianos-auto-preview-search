(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Focus the search input when page loads
        $('#wc-auto-preview-search-input').focus();

        // Focus the search input when clicking anywhere in the search container
        $('.wc-auto-preview-search').on('click', function() {
            $('#wc-auto-preview-search-input').focus();
        });

        // Initialize autocomplete
        $('#wc-auto-preview-search-input').autocomplete({
            source: function(request, response) {
                $.ajax({
                    url: wc_auto_preview_search.ajax_url,
                    dataType: 'json',
                    data: {
                        action: 'wc_auto_preview_search',
                        term: request.term,
                        nonce: wc_auto_preview_search.nonce
                    },
                    success: function(data) {
                        response(data);
                    }
                });
            },
            minLength: 2,
            position: { my: 'left top+5', at: 'left bottom' },
            select: function(event, ui) {
                // Redirect to product page on select
                window.location.href = ui.item.url;
                return false;
            }
        }).data('ui-autocomplete')._renderItem = function(ul, item) {
            // Custom rendering of items with product preview
            var html = '<div class="wc-product-preview">';
            html += '<div class="wc-product-preview-image">';
            html += '<img src="' + item.image + '" alt="' + item.title + '">';
            html += '</div>';
            html += '<div class="wc-product-preview-content">';
            html += '<div class="wc-product-preview-title">' + item.title + '</div>';
            html += '<div class="wc-product-preview-price">' + item.price + '</div>';
            html += '</div>';
            html += '</div>';
            
            return $('<li>')
                .data('ui-autocomplete-item', item)
                .append(html)
                .appendTo(ul);
        };
    });
})(jQuery);