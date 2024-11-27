'use strict';
function queryParams(p) {
    return {
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
    toggleOff: 'bx-toggle-left',
    toggleOn: 'bx-toggle-right'
}

function loadingTemplate(message) {
    return '<i class="bx bx-loader-alt bx-spin bx-flip-vertical" ></i>'
}

function userFormatter(value, row, index) {
    return '<div class="d-flex">' + row.photo + '<div class="mx-2 mt-2"><h6 class="mb-1">' + row.first_name + ' ' + row.last_name +
    (row.status === 1 ? ' <span class="badge bg-success">Active</span>' : ' <span class="badge bg-danger">Deactive</span>') +
    '</h6><p class="text-muted">' + row.email + '</p></div>' +
    '</div>';

}
