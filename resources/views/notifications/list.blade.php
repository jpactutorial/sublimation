@extends('layout')

@section('title')
<?= get_label('notifications', 'Notifications') ?>
@endsection

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
                        <?= get_label('notifications', 'Notifications') ?>
                    </li>

                </ol>
            </nav>
        </div>
    </div>

    @if ($notifications_count > 0)
    @php
    $visibleColumns = getUserPreferences('notifications');
    @endphp
    <div class="card">
        <div class="card-body">
            <div class="row">
                @if(isAdminOrHasAllDataAccess())
                <div class="col-md-4 mb-3">
                    <select class="form-select" id="user_filter" aria-label="Default select example">
                        <option value=""><?= get_label('select_user', 'Select user') ?></option>
                        @foreach ($users as $user)
                        <option value="{{$user->id}}" {{!isClient() && auth()->id()==$user->id?'selected':''}}>{{$user->first_name.' '.$user->last_name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <select class="form-select" id="client_filter" aria-label="Default select example">
                        <option value=""><?= get_label('select_client', 'Select client') ?></option>
                        @foreach ($clients as $client)
                        <option value="{{$client->id}}" {{isClient() && auth()->id()==$client->id?'selected':''}}>{{$client->first_name.' '.$client->last_name}}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-md-4 mb-3">
                    <select class="form-select" id="type_filter" aria-label="Default select example">
                        <option value=""><?= get_label('select_type', 'Select type') ?></option>
                        @foreach ($types as $type)
                        <option value="{{$type}}">{{ get_label($type, ucfirst(str_replace('_', ' ', $type))) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <select class="form-select" id="status_filter" aria-label="Default select example">
                        <option value=""><?= get_label('select_status', 'Select status') ?></option>
                        <option value="read"><?= get_label('read', 'Read') ?></option>
                        <option value="unread"><?= get_label('unread', 'Unread') ?></option>
                    </select>
                </div>
            </div>

            <div class="table-responsive text-nowrap">
                <input type="hidden" id="data_type" value="notifications">
                <input type="hidden" id="save_column_visibility">
                <table id="table" data-toggle="table" data-loading-template="loadingTemplate" data-url="/notifications/list" data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total" data-trim-on-search="false" data-data-field="rows" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-side-pagination="server" data-show-columns="true" data-pagination="true" data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true" data-query-params="queryParams">
                    <thead>
                        <tr>
                            <th data-checkbox="true"></th>
                            <th data-field="id" data-sortable="true" data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('id', 'ID') ?></th>
                            <th data-field="title" data-sortable="true" data-visible="{{ (in_array('title', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('title', 'Title') ?></th>
                            <th data-field="message" data-sortable="true" data-visible="{{ (in_array('message', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('message', 'Message') ?></th>
                            @if (isAdminOrHasAllDataAccess())
                            <th data-field="users" data-formatter="UserFormatter" data-visible="{{ (in_array('users', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('users', 'Users') ?></th>
                            <th data-field="clients" data-formatter="ClientFormatter" data-visible="{{ (in_array('clients', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('clients', 'Clients') ?></th>
                            @endif
                            <th data-field="type" data-sortable="true" data-visible="{{ (in_array('type', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('type', 'Type') ?></th>
                            <th data-field="status" data-sortable="true" data-visible="{{ (in_array('status', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('status', 'Status') ?></th>
                            <th data-field="created_at" data-sortable="true" data-visible="{{ (in_array('created_at', $visibleColumns)) ? 'true' : 'false' }}"><?= get_label('created_at', 'Created at') ?></th>
                            <th data-field="updated_at" data-sortable="true" data-visible="{{ (in_array('updated_at', $visibleColumns)) ? 'true' : 'false' }}"><?= get_label('updated_at', 'Updated at') ?></th>
                            <th data-field="actions" data-sortable="false" data-visible="{{ (in_array('actions', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}"><?= get_label('actions', 'Actions') ?></th>
                        </tr>

                    </thead>
                </table>

            </div>
        </div>
    </div>
    @else
    <?php
    $type = 'Notifications'; ?>
    <x-empty-state-card :type="$type" />

    @endif
</div>
<script src="{{asset('assets/js/pages/notifications.js')}}"></script>
@endsection