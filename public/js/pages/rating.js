$('document').ready(function () {
    $('.rating-coaster').rateit(
        {max: 5, step: 0.5, resetable: false, mode: 'font'}
    ).bind('rated', function () {
        var url = Routing.generate(
            'rating_edit',
            {'id': this.dataset.coaster, '_locale': locale}
        );

        $.post(url, {value: $(this).rateit('value')}, function (response) {
        }, 'JSON').done(function (data) {
            $('#rating-date').show();
            $('#review-delete').html('<a class="text-muted" onclick="deleteRating(' + data.id + ');">delete</a>');
        });
    });
});

function deleteRating(id) {
    if (confirm('Delete ?')) {
        var url = Routing.generate(
            'rating_delete',
            {'id': id, '_locale': locale}
        );
        $.ajax({
            url: url,
            type: "DELETE",
        }).done(function () {
            location.reload();
        });
    }
}
