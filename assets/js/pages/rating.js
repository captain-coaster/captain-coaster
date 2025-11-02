/**
 * Rating functionality for coaster ratings
 * Migrated from public/js/pages/rating.js
 * Uses jquery.rateit plugin from npm dependencies
 */

import 'jquery.rateit';

$(document).ready(function () {
    // Initialize rating widget
    $('.rating-coaster').rateit({
        max: 5, 
        step: 0.5, 
        resetable: false, 
        mode: 'font'
    }).bind('rated', function () {
        const coasterId = this.dataset.coaster;
        const ratingValue = $(this).rateit('value');
        
        // Generate URL using Symfony routing
        const url = Routing.generate('rating_edit', {
            'id': coasterId, 
            '_locale': locale
        });

        // Submit rating via AJAX
        $.post(url, {value: ratingValue}, function (response) {
            // Handle response
        }, 'JSON').done(function (data) {
            // Update delete link
            $('#review-delete').html('<a class="text-muted" onclick="deleteRating(' + data.id + ');">delete</a>');
        }.bind(this));
    });
});

/**
 * Delete a rating
 * @param {number} id - Rating ID to delete
 */
function deleteRating(id) {
    if (confirm('Delete ?')) {
        const url = Routing.generate('rating_delete', {
            'id': id, 
            '_locale': locale
        });
        
        $.ajax({
            url: url,
            type: "DELETE",
        }).done(function () {
            location.reload();
        });
    }
}

// Export for global access (needed for inline onclick handlers)
window.deleteRating = deleteRating;