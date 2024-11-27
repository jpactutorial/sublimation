@extends('layout')

@section('title')
<?= get_label('activity_log', 'Activity log') ?>
@endsection

@php
$visibleColumns = getUserPreferences('activity_log');
@endphp

@section('content')


<div class="container-fluid">
    <div class="d-flex justify-content-between mb-2 mt-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{url('/home')}}"><?= get_label('home', 'Home') ?></a>
                    </li>
                    <li class="breadcrumb-item active">
                        <?= get_label('activity_log', 'Activity log') ?>
                    </li>

                </ol>
            </nav>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="input-group input-group-merge">
                        <input type="text" id="activity_log_between_date" class="form-control" placeholder="<?= get_label('date_between', 'Date between') ?>" autocomplete="off">
                    </div>
                </div>

                @if(isAdminOrHasAllDataAccess())
                <div class="col-md-4 mb-3">
                    <select class="form-select" id="user_filter" aria-label="Default select example">
                        <option value=""><?= get_label('select_user', 'Select user') ?></option>
                        @foreach ($users as $user)
                        <option value="{{$user->id}}">{{$user->first_name.' '.$user->last_name}}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <select class="form-select" id="client_filter" aria-label="Default select example">
                        <option value=""><?= get_label('select_client', 'Select client') ?></option>
                        @foreach ($clients as $client)
                        <option value="{{$client->id}}">{{$client->first_name.' '.$client->last_name}}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="col-md-4 mb-3">
                    <select class="form-select" id="activity_filter" aria-label="Default select example">
                        <option value=""><?= get_label('select_activity', 'Select activity') ?></option>
                        <option value="created"><?= get_label('created', 'Created') ?></option>
                        <option value="updated"><?= get_label('updated', 'Updated') ?></option>
                        <option value="duplicated"><?= get_label('duplicated', 'Duplicated') ?></option>
                        <option value="uploaded"><?= get_label('uploaded', 'Uploaded') ?></option>
                        <option value="deleted"><?= get_label('deleted', 'Deleted') ?></option>
                        <option value="updated_status"><?= get_label('updated_status', 'Updated status') ?></option>
                        <option value="signed"><?= get_label('signed', 'Signed') ?></option>
                        <option value="unsigned"><?= get_label('unsigned', 'Unsigned') ?></option>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <select class="form-select" id="type_filter" aria-label="Default select example">
                        <option value=""><?= get_label('select_type', 'Select type') ?></option>
                        @foreach ($types as $type)
                        <option value="{{$type}}">{{ Str::title(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                </div>

            </div>

            <input type="hidden" id="activity_log_between_date_from">
            <input type="hidden" id="activity_log_between_date_to">
            <div class="table-responsive text-nowrap">
                <input type="hidden" id="data_type" value="activity-log">
                <input type="hidden" id="data_table" value="activity_log_table">
                <input type="hidden" id="save_column_visibility">
                <table id="activity_log_table" data-toggle="table" data-loading-template="loadingTemplate" data-url="/activity-log/list" data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total" data-trim-on-search="false" data-data-field="rows" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-side-pagination="server" data-show-columns="true" data-pagination="true" data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true" data-query-params="queryParams">
                    <thead>
                        <tr>
                            <th data-checkbox="true"></th>
                            <th data-field="id" data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('id', 'ID') ?></th>
                            <th data-field="actor_id" data-visible="{{ (in_array('actor_id', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('actor_id', 'Actor ID') ?></th>
                            <th data-field="actor_name" data-visible="{{ (in_array('actor_name', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('actor_name', 'Actor name') ?></th>
                            <th data-field="actor_type" data-visible="{{ (in_array('actor_type', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('actor_type', 'Actor type') ?></th>
                            <th data-field="type_id" data-visible="{{ (in_array('type_id', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('type_id', 'Type ID') ?></th>
                            <th data-field="parent_type_id" data-visible="{{ (in_array('parent_type_id', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('parent_type_id', 'Parent type ID') ?></th>
                            <th data-field="activity" data-visible="{{ (in_array('activity', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('activity', 'Activity') ?></th>
                            <th data-field="type" data-visible="{{ (in_array('type', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('type', 'Type') ?></th>
                            <th data-field="parent_type" data-visible="{{ (in_array('parent_type', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('parent_type', 'Parent type') ?></th>
                            <th data-field="type_title" data-visible="{{ (in_array('type_title', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('type_title', 'Type title') ?></th>
                            <th data-field="parent_type_title" data-visible="{{ (in_array('parent_type_title', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('parent_type_title', 'Parent type title') ?></th>
                            <th data-field="message" data-visible="{{ (in_array('message', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('message', 'Message') ?></th>
                            <th data-field="created_at" data-visible="{{ (in_array('created_at', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('created_at', 'Created at') ?></th>
                            <th data-field="updated_at" data-visible="{{ (in_array('updated_at', $visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('updated_at', 'Updated at') ?></th>
                            <th data-field="actions" data-visible="{{ (in_array('actions', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('actions', 'Actions') ?></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    var label_delete = '<?= get_label('delete', 'Delete') ?>';
</script>
<script src="{{asset('assets/js/pages/activity-log.js')}}">
                                </script>
                                @endsection