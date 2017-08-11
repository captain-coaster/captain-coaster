$('document').ready(function () {
    $('.rating-coaster').rateit(
        {max: 5, step: 0.5, resetable: false, mode: 'font'}
    ).bind('rated', function () {
        var url = Routing.generate(
            'rating_edit',
            {'id': this.dataset.coaster, '_locale': locale}
        );

        $.post(url, {value: $(this).rateit('value')}, function (response) {
        }, 'JSON');
    });
});

function deleteRating(id, obj) {
    var url = Routing.generate(
        'rating_delete',
        {'id': id, '_locale': locale}
    );
    $.ajax({
        url: url,
        type: "DELETE",
    }).done(function () {
        $(obj).remove();
    });
}