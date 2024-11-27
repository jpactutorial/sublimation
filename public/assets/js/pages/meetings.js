
'use strict';
function queryParams(p) {
    return {
        "status": $('#status_filter').val(),
        "user_id": $('#meeting_user_filter').val(),
        "client_id": $('#meeting_client_filter').val(),
        "start_date_from": $('#meeting_start_date_from').val(),
        "start_date_to": $('#meeting_start_date_to').val(),
        "end_date_from": $('#meeting_end_date_from').val(),
        "end_date_to": $('#meeting_end_date_to').val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search
    };
}
window.icons = {
    refresh: 'bx-refresh',
    toggleOn: 'bx-toggle-right',
    toggleOff: 'bx-toggle-left'
}

function loadingTemplate(message) {
    return '<i class="bx bx-loader-alt bx-spin bx-flip-vertical" ></i>'
}

$('#status_filter,#meeting_user_filter,#meeting_client_filter').on('change', function (e) {
    e.preventDefault();
    $('#meetings_table').bootstrapTable('refresh');
});