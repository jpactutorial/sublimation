
window.icons = {
    refresh: 'bx-refresh'
}

function loadingTemplate(message) {
    return '<i class="bx bx-loader-alt bx-spin bx-flip-vertical" ></i>'
}

function actionFormatterUsers(value, row, index) {
    return [
        '<a href="/users/edit/' + row.id + '" title=' + label_update + '>' +
        '<i class="bx bx-edit mx-1">' +
        '</i>' +
        '</a>' +
        '<button title=' + label_delete + ' type="button" class="btn delete" data-id=' + row.id + ' data-type="users">' +
        '<i class="bx bx-trash text-danger mx-1"></i>' +
        '</button>'
    ]
}

function actionFormatterClients(value, row, index) {
    return [
        '<a href="/clients/edit/' + row.id + '" title=' + label_update + '>' +
        '<i class="bx bx-edit mx-1">' +
        '</i>' +
        '</a>' +
        '<button title=' + label_delete + ' type="button" class="btn delete" data-id=' + row.id + ' data-type="clients">' +
        '<i class="bx bx-trash text-danger mx-1"></i>' +
        '</button>'
    ]
}

function queryParamsTasks(p) {
    return {
        "status_ids": $('#task_status_filter').val(),
        "priority_ids": $('#task_priority_filter').val(),
        "user_ids": $('#tasks_user_filter').val(),
        "client_ids": $('#tasks_client_filter').val(),
        "project_ids": $('#tasks_project_filter').val(),
        "task_start_date_from": $('#task_start_date_from').val(),
        "task_start_date_to": $('#task_start_date_to').val(),
        "task_end_date_from": $('#task_end_date_from').val(),
        "task_end_date_to": $('#task_end_date_to').val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search
    };
}
$('#task_status_filter, #task_priority_filter, #tasks_user_filter, #tasks_client_filter, #tasks_project_filter').on('change', function (e, refreshTable) {
    e.preventDefault();
    if (typeof refreshTable === 'undefined' || refreshTable) {
        $('#task_table').bootstrapTable('refresh');
    }
});

function userFormatter(value, row, index) {
    return '<div class="d-flex">' + row.photo + '<div class="mx-2 mt-2"><h6 class="mb-1">' + row.first_name + ' ' + row.last_name +
        (row.status === 1 ? ' <span class="badge bg-success">Active</span>' : ' <span class="badge bg-danger">Deactive</span>') +
        '</h6><p class="text-muted">' + row.email + '</p></div>' +
        '</div>';

}

function clientFormatter(value, row, index) {
    return '<div class="d-flex">' + row.profile + '<div class="mx-2 mt-2"><h6 class="mb-1">' + row.first_name + ' ' + row.last_name +
        (row.status === 1 ? ' <span class="badge bg-success">Active</span>' : ' <span class="badge bg-danger">Deactive</span>') +
        '</h6><p class="text-muted">' + row.email + '</p></div>' +
        '</div>';

}

function assignedFormatter(value, row, index) {
    return '<div class="d-flex justify-content-start align-items-center"><div class="text-center mx-4"><span class="badge rounded-pill bg-primary" >' + row.projects + '</span><div>' + label_projects + '</div></div>' +
        '<div class="text-center"><span class="badge rounded-pill bg-primary" >' + row.tasks + '</span><div>' + label_tasks + '</div></div></div>'
}

function queryParamsUsersClients(p) {
    return {
        type: $('#type').val(),
        typeId: $('#typeId').val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search
    };
}

$(document).on('click', '.clear-filters', function (e) {
    e.preventDefault();
    $('#task_start_date_between').val('');
    $('#task_end_date_between').val('');
    $('#task_start_date_from').val('');
    $('#task_start_date_to').val('');
    $('#task_end_date_from').val('');
    $('#task_end_date_to').val('');
    $('#tasks_project_filter').val('').trigger('change', [0]);
    $('#tasks_user_filter').val('').trigger('change', [0]);
    $('#tasks_client_filter').val('').trigger('change', [0]);
    $('#task_status_filter').val('').trigger('change', [0]);
    $('#task_priority_filter').val('').trigger('change', [0]);
    $('#task_table').bootstrapTable('refresh');
})
