@extends('layout')

@section('title')
<?= get_label('task_details', 'Task details') ?>
@endsection

@section('content')
<div class="container-fluid">
    <div class="align-items-center d-flex justify-content-between m-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{url('/home')}}"><?= get_label('home', 'Home') ?></a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{url('/'.getUserPreferences('tasks', 'default_view'))}}"><?= get_label('tasks', 'Tasks') ?></a>
                    </li>
                    <li class="breadcrumb-item active">
                        {{$task->title}}
                    </li>
                </ol>
            </nav>
        </div>
        <div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <h2 class="fw-bold">{{ $task->title }}</h2>
                            <div class="row">
                                <div class="col-md-6 mt-3 mb-3">
                                    <label class="form-label" for="start_date"><?= get_label('users', 'Users') ?></label>
                                    <?php
                                    $users = $task->users;
                                    $clients = $task->project->clients;
                                    if (count($users) > 0) { ?>
                                        <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center flex-wrap">
                                            @foreach($users as $user)
                                            <li class="avatar avatar-sm pull-up" title="{{$user->first_name}} {{$user->last_name}}"><a href="/users/profile/{{$user->id}}" target="_blank">
                                                    <img src="{{$user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')}}" class="rounded-circle" alt="{{$user->first_name}} {{$user->last_name}}">
                                                </a></li>
                                            @endforeach
                                            <a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-task update-users-clients" data-id="{{$task->id}}"><span class="bx bx-edit"></span></a>
                                        </ul>
                                    <?php } else { ?>
                                        <p><span class="badge bg-primary"><?= get_label('not_assigned', 'Not assigned') ?></span><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-task update-users-clients" data-id="{{$task->id}}"><span class="bx bx-edit"></span></a></p>
                                    <?php } ?>
                                </div>
                                <div class="col-md-6  mt-3 mb-3">
                                    <label class="form-label" for="end_date"><?= get_label('clients', 'Clients') ?></label>
                                    <?php
                                    if (count($clients) > 0) { ?>
                                        <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center flex-wrap">
                                            @foreach($clients as $client)
                                            <li class="avatar avatar-sm pull-up" title="{{$client->first_name}} {{$client->last_name}}"><a href="/clients/profile/{{$client->id}}" target="_blank">
                                                    <img src="{{$client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')}}" class="rounded-circle" alt="{{$client->first_name}} {{$client->last_name}}">
                                                </a></li>
                                            @endforeach
                                        </ul>
                                    <?php } else { ?>
                                        <p><span class="badge bg-primary"><?= get_label('not_assigned', 'Not assigned') ?></span></p>
                                    <?php } ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= get_label('status', 'Status') ?></label>
                                    <div class="input-group">
                                        <select class="form-select form-select-sm select-bg-label-{{$task->status->color}}" id="statusSelect" data-id="{{ $task->id }}" data-original-status-id="{{ $task->status->id }}" data-original-color-class="select-bg-label-{{$task->status->color}}" data-type="task">
                                            @foreach($statuses as $status)
                                            @php
                                            $disabled = canSetStatus($status) ? '' : 'disabled';
                                            @endphp
                                            <option value="{{ $status->id }}" class="badge bg-label-{{ $status->color }}" {{ $task->status->id == $status->id ? 'selected' : '' }} {{ $disabled }}>
                                                {{ $status->title }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="prioritySelect" class="form-label"><?= get_label('priority', 'Priority') ?></label>
                                    <div class="input-group">
                                        <select class="form-select form-select-sm select-bg-label-{{$task->priority?$task->priority->color:'secondary'}}" id="prioritySelect" data-id="{{ $task->id }}" data-original-priority-id="{{ $task->priority ? $task->priority->id : '' }}" data-original-color-class="select-bg-label-{{$task->priority?$task->priority->color:'secondary'}}" data-type="task">
                                            @foreach($priorities as $priority)
                                            <option value="{{ $priority->id }}" class="badge bg-label-{{ $priority->color }}" {{ $task->priority && $task->priority->id == $priority->id ? 'selected' : '' }}>
                                                {{ $priority->title }}
                                            </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-0" />
                <div class="card-body">
                    <div class="row">

                        <div class="mb-3 col-md-12">
                            <label class="form-label" for="project"><?= get_label('project', 'Project') ?></label>
                            <div class="input-group input-group-merge">
                                @php
                                $project = $task->project;
                                @endphp
                                <input class="form-control px-2" type="text" id="project" value="{{$project->title}}" readonly="">
                            </div>
                        </div>
                    </div>

                    <div class="row">

                        <div class="mb-3">
                            <label class="form-label" for="description"><?= get_label('description', 'Description') ?></label>
                            <div class="input-group input-group-merge">
                                <textarea class="form-control" id="description" name="description" rows="5" readonly>{{ $task->description }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row">

                        <div class="mb-3 col-md-6">
                            <label class="form-label" for="start_date"><?= get_label('starts_at', 'Starts at') ?></label>
                            <div class="input-group input-group-merge">
                                <input type="text" name="start_date" class="form-control" placeholder="" value="{{ format_date($task->start_date)}}" readonly />
                            </div>
                        </div>

                        <div class="mb-3 col-md-6">
                            <label class="form-label" for="due-date"><?= get_label('ends_at', 'Ends at') ?></label>
                            <div class="input-group input-group-merge">
                                <input class="form-control" type="text" name="due_date" placeholder="" value="{{ format_date($task->due_date)}}" readonly="">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <input type="hidden" id="media_type_id" value="{{$task->id}}">
        </div>

        <div class="nav-align-top mt-2">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-media" aria-controls="navs-top-media">
                        <i class="menu-icon tf-icons bx bx-image-alt text-success"></i><?= get_label('media', 'Media') ?>
                    </button>
                </li>
                @if ($auth_user->can('manage_activity_log'))
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-activity-log" aria-controls="navs-top-activity-log">
                        <i class="menu-icon tf-icons bx bx-line-chart text-info"></i><?= get_label('activity_log', 'Activity log') ?>
                    </button>
                </li>
                @endif
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade active show" id="navs-top-media" role="tabpanel">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div></div>
                            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#add_media_modal">
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('add_media', 'Add Media') ?>">
                                    <i class="bx bx-plus"></i>
                                </button>
                            </a>
                        </div>
                        @php
                        $visibleColumns = getUserPreferences('task_media');
                        @endphp
                        <div class="table-responsive text-nowrap">
                            <input type="hidden" id="data_type" value="task-media">
                            <input type="hidden" id="data_table" value="task_media_table">
                            <input type="hidden" id="save_column_visibility">
                            <table id="task_media_table" data-toggle="table" data-loading-template="loadingTemplate" data-url="/tasks/get-media/{{$task->id}}" data-icons-prefix="bx" data-icons="icons" data-show-refresh="true" data-total-field="total" data-trim-on-search="false" data-data-field="rows" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-side-pagination="server" data-show-columns="true" data-pagination="true" data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true" data-query-params="queryParamsTaskMedia">
                                <thead>
                                    <tr>
                                        <th data-checkbox="true"></th>
                                        <th data-field="id" data-visible="{{ (in_array('id', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('id', 'ID') ?></th>
                                        <th data-field="file" data-visible="{{ (in_array('file', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('file', 'File') ?></th>
                                        <th data-field="file_name" data-sortable="true" data-visible="{{ (in_array('file_name', $visibleColumns)) ? 'true' : 'false' }}">{{ get_label('file_name', 'File name') }}</th>
                                        <th data-field="file_size" data-visible="{{ (in_array('file_size', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="true"><?= get_label('file_size', 'File size') ?></th>
                                        <th data-field="created_at" data-sortable="true" data-visible="{{ (in_array('created_at', $visibleColumns)) ? 'true' : 'false' }}">{{ get_label('created_at', 'Created at') }}</th>
                                        <th data-field="updated_at" data-sortable="true" data-visible="{{ (in_array('updated_at', $visibleColumns)) ? 'true' : 'false' }}">{{ get_label('updated_at', 'Updated at') }}</th>
                                        <th data-field="actions" data-visible="{{ (in_array('actions', $visibleColumns) || empty($visibleColumns)) ? 'true' : 'false' }}" data-sortable="false">{{ get_label('actions', 'Actions') }}</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>

                @if ($auth_user->can('manage_activity_log'))
                <div class="tab-pane fade" id="navs-top-activity-log" role="tabpanel">
                    <div class="col-12">
                        <div class="row mt-4">
                            <div class="mb-3 col-md-4">
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
                                </select>
                            </div>

                        </div>
                        @php
                        $visibleColumns = getUserPreferences('activity_log');
                        @endphp
                        <div class="table-responsive text-nowrap">
                            <input type="hidden" id="activity_log_between_date_from">
                            <input type="hidden" id="activity_log_between_date_to">

                            <input type="hidden" id="data_type" value="activity-log">
                            <input type="hidden" id="data_table" value="activity_log_table">
                            <input type="hidden" id="type_id" value="{{$task->id}}">
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
                @endif


            </div>
        </div>
        <div class="modal fade" id="add_media_modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form class="modal-content form-horizontal" id="media-upload" action="{{url('/tasks/upload-media')}}" method="POST" enctype="multipart/form-data">
                                            @csrf
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLabel1"><?= get_label('add_media', 'Add Media') ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-primary alert-dismissible" role="alert"><?= $media_storage_settings['media_storage_type'] == 's3' ? get_label('storage_type_set_as_aws_s3', 'Storage type is set as AWS S3 storage') : get_label('storage_type_set_as_local', 'Storage type is set as local storage') ?>, <a href="/settings/media-storage" target="_blank"><?= get_label('click_here_to_change', 'Click here to change.') ?></a></div>
                                                <div class="dropzone dz-clickable" id="media-upload-dropzone">

                                                </div>
                                                <div class="form-group mt-4 text-center">
                                                    <button class="btn btn-primary" id="upload_media_btn"><?= get_label('upload', 'Upload') ?></button>
                                                </div>
                                                <div class="d-flex justify-content-center">
                                                    <div class="form-group" id="error_box">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                                    <?= get_label('close', 'Close') ?>
                                                </button>
                                            </div>
                                            </form>
                        </div>
                    </div>
                </div>
                <script>
                    var label_delete = '<?= get_label('delete', 'Delete') ?>';
                </script>
                <script src="{{asset('assets/js/pages/task-information.js')}}"></script>
                @endsection