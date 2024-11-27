@extends('layout')

@section('title')
<?= get_label('dashboard', 'Dashboard') ?>
@endsection

@php
$user = getAuthenticatedUser();
@endphp

@section('content')
@authBoth
<div class="container-fluid">
    <div class="col-lg-12 col-md-12 order-1">
        <div class="row mt-4">
            <div class="col-lg-3 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                            <div class="avatar flex-shrink-0">
                                <i class="menu-icon tf-icons bx bx-briefcase-alt-2 bx-md text-success"></i>
                            </div>
                        </div>
                        <span class="fw-semibold d-block mb-1"><?= get_label('total_projects', 'Total projects') ?></span>
                        <h3 class="card-title mb-2">{{is_countable($projects) && count($projects) > 0?count($projects):0}}</h3>
                        @if ($user->can('manage_projects'))
                        <a href="/{{getUserPreferences('projects', 'default_view')}}"><small class="text-success fw-semibold"><i class="bx bx-right-arrow-alt"></i><?= get_label('view_more', 'View more') ?></small></a>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                            <div class="avatar flex-shrink-0">
                                <i class="menu-icon tf-icons bx bx-task bx-md text-primary"></i>
                            </div>
                        </div>
                        <span class="fw-semibold d-block mb-1"><?= get_label('total_tasks', 'Total tasks') ?></span>
                        <h3 class="card-title mb-2">{{$tasks}}</h3>
                        @if ($user->can('manage_tasks'))
                        <a href="/{{getUserPreferences('tasks', 'default_view')}}"><small class="text-primary fw-semibold"><i class="bx bx-right-arrow-alt"></i><?= get_label('view_more', 'View more') ?></small></a>
                        @endif
                    </div>
                </div>
            </div>
            @if (!isClient())
            <div class="col-lg-3 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                            <div class="avatar flex-shrink-0">
                                <i class="menu-icon tf-icons bx bxs-user-detail bx-md text-warning"></i>
                            </div>
                        </div>
                        <span class="fw-semibold d-block mb-1"><?= get_label('total_users', 'Total users') ?></span>
                        <h3 class="card-title mb-2">{{is_countable($users) && count($users) > 0?count($users):0}}</h3>
                        @if ($user->can('manage_users'))
                        <a href="/users"><small class="text-warning fw-semibold"><i class="bx bx-right-arrow-alt"></i><?= get_label('view_more', 'View more') ?></small></a>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                            <div class="avatar flex-shrink-0">
                                <i class="menu-icon tf-icons bx bxs-user-detail bx-md text-info"></i>
                            </div>
                        </div>
                        <span class="fw-semibold d-block mb-1"><?= get_label('total_clients', 'Total clients') ?></span>
                        <h3 class="card-title mb-2"> {{is_countable($clients) && count($clients) > 0?count($clients):0}}</h3>
                        @if ($user->can('manage_clients'))
                        <a href="/clients"><small class="text-info fw-semibold"><i class="bx bx-right-arrow-alt"></i><?= get_label('view_more', 'View more') ?></small></a>
                        @endif
                    </div>
                </div>
            </div>
            @else
            <div class="col-lg-3 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                            <div class="avatar flex-shrink-0">
                                <i class="menu-icon tf-icons bx bx-shape-polygon text-success bx-md text-warning"></i>
                            </div>
                        </div>
                        <span class="fw-semibold d-block mb-1"><?= get_label('total_meetings', 'Total meetings') ?></span>
                        <h3 class="card-title mb-2">{{is_countable($meetings) && count($meetings) > 0?count($meetings):0}}</h3>
                        @if ($user->can('manage_meetings'))
                        <a href="/meetings"><small class="text-warning fw-semibold"><i class="bx bx-right-arrow-alt"></i><?= get_label('view_more', 'View more') ?></small></a>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between">
                            <div class="avatar flex-shrink-0">
                                <i class="menu-icon tf-icons bx bx-list-check bx-md text-info"></i>
                            </div>
                        </div>
                        <span class="fw-semibold d-block mb-1"><?= get_label('total_todos', 'Total todos') ?></span>
                        <h3 class="card-title mb-2"> {{is_countable($total_todos) && count($total_todos) > 0?count($total_todos):0}}</h3>
                        <a href="/todos"><small class="text-info fw-semibold"><i class="bx bx-right-arrow-alt"></i><?= get_label('view_more', 'View more') ?></small></a>
                    </div>
                </div>
            </div>
            @endif

        </div>
        <div class="row">
            <div class="col-md-6 col-sm-12">
                <div class="card overflow-hidden mb-4 statisticsDiv">
                    <div class="card-header pt-3 pb-1">
                        <div class="card-title mb-0">
                            <h5 class="m-0 me-2"><?= get_label('project_statistics', 'Project statistics') ?></h5>
                        </div>
                        <div class="my-3">
                            <div id="projectStatisticsChart"></div>
                        </div>
                    </div>
                    <div class="card-body" id="project-statistics">
                        <?php
                        // Calculate status counts and total projects count
                        $statusCounts = [];
                        $total_projects_count = 0;
                        foreach ($statuses as $status) {
                            $projectCount = isAdminOrHasAllDataAccess() ? count($status->projects) : $auth_user->status_projects($status->id)->count();
                            $statusCounts[$status->id] = $projectCount;
                            $total_projects_count += $projectCount; // Accumulate the count of projects
                        }

                        // Sort statuses by count in descending order
                        arsort($statusCounts);
                        ?>
                        <ul class="p-0 m-0">
                            @foreach ($statusCounts as $statusId => $count)
                            <?php $status = $statuses->where('id', $statusId)->first(); ?>
                            <li class="d-flex mb-4 pb-1">
                                <div class="avatar flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-briefcase-alt-2 text-{{$status->color}}"></i></span>
                                </div>
                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                    <div class="me-2">
                                        <a href="/{{getUserPreferences('projects', 'default_view')}}?status={{ $status->id }}">
                                            <h6 class="mb-0">{{ $status->title }}</h6>
                                        </a>
                                    </div>
                                    <div class="user-progress">
                                        <small class="fw-semibold">{{ $count }}</small>
                                    </div>
                                </div>
                            </li>
                            @endforeach
                            <li class="d-flex mb-4 pb-1">
                                <div class="avatar flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-menu"></i></span>
                                </div>
                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                    <div class="me-2">
                                        <h5 class="mb-0"><?= get_label('total', 'Total') ?></h5>
                                    </div>
                                    <div class="user-progress">
                                        <div class="status-count">
                                            <h5 class="mb-0">{{$total_projects_count}}</h5>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-6 col-xl-6 order-0 mb-6">
                <div class="card overflow-hidden mb-4 statisticsDiv">
                    <div class="card-header pt-3 pb-1">
                        <div class="card-title mb-0">
                            <h5 class="m-0 me-2"><?= get_label('task_statistics', 'Task statistics') ?></h5>
                        </div>
                        <div class="my-3">
                            <div id="taskStatisticsChart"></div>
                        </div>
                    </div>
                    <div class="card-body" id="task-statistics">
                        <?php
                        // Calculate status counts and total tasks count
                        $statusCounts = [];
                        $total_tasks_count = 0;
                        foreach ($statuses as $status) {
                            $statusCount = isAdminOrHasAllDataAccess() ? count($status->tasks) : $auth_user->status_tasks($status->id)->count();
                            $statusCounts[$status->id] = $statusCount;
                            $total_tasks_count += $statusCount; // Accumulate the count of tasks
                        }

                        // Sort statuses by count in descending order
                        arsort($statusCounts);
                        ?>
                        <ul class="p-0 m-0">
                            @foreach ($statusCounts as $statusId => $count)
                            <?php $status = $statuses->where('id', $statusId)->first(); ?>
                            <li class="d-flex mb-4 pb-1">
                                <div class="avatar flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-task text-{{$status->color}}"></i></span>
                                </div>
                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                    <div class="me-2">
                                        <a href="/{{getUserPreferences('tasks', 'default_view')}}?status={{ $status->id }}">
                                            <h6 class="mb-0">{{ $status->title }}</h6>
                                        </a>
                                    </div>
                                    <div class="user-progress">
                                        <small class="fw-semibold">{{ $count }}</small>
                                    </div>
                                </div>
                            </li>
                            @endforeach
                            <li class="d-flex mb-4 pb-1">
                                <div class="avatar flex-shrink-0 me-3">
                                    <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-menu"></i></span>
                                </div>
                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                    <div class="me-2">
                                        <h5 class="mb-0"><?= get_label('total', 'Total') ?></h5>
                                    </div>
                                    <div class="user-progress">
                                        <div class="status-count">
                                            <h5 class="mb-0">{{$total_tasks_count}}</h5>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>


            
        </div>
    </div>


    


    @if ($auth_user->can('manage_projects') || $auth_user->can('manage_tasks'))
    <div class="nav-align-top mt-4">
        <ul class="nav nav-tabs" role="tablist">
            @if ($auth_user->can('manage_projects'))
            <li class="nav-item">
                <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-projects" aria-controls="navs-top-projects" aria-selected="true">
                    <i class="menu-icon tf-icons bx bx-briefcase-alt-2 text-success"></i><?= get_label('projects', 'Projects') ?>
                </button>
            </li>
            @endif
            @if ($auth_user->can('manage_tasks'))
            <li class="nav-item">
                <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-top-tasks" aria-controls="navs-top-tasks" aria-selected="false">
                    <i class="menu-icon tf-icons bx bx-task text-primary"></i><?= get_label('tasks', 'Tasks') ?>
                </button>
            </li>
            @endif
        </ul>
        <div class="tab-content">
            @if ($auth_user->can('manage_projects'))
            <div class="tab-pane fade active show" id="navs-top-projects" role="tabpanel">
                <div class="d-flex justify-content-between">
                    <h4 class="fw-bold">{{$auth_user->first_name}}'s <?= get_label('projects', 'Projects') ?></h4>
                </div>
                @if (is_countable($projects) && count($projects) > 0)
                <?php
                $type = isUser() ? 'user' : 'client';
                $id = isAdminOrHasAllDataAccess() ? '' : $type . '_' . $auth_user->id;
                ?>
                <x-projects-card :projects="$projects" :id="$id" :users="$users" :clients="$clients" />
                @else
                <?php
                $type = 'Projects'; ?>
                <x-empty-state-card :type="$type" />
                @endif
            </div>
            @endif

            @if ($auth_user->can('manage_tasks'))
            <div class="tab-pane fade {{!$auth_user->can('manage_projects')?'active show':''}}" id="navs-top-tasks" role="tabpanel">
                <div class="d-flex justify-content-between">
                    <h4 class="fw-bold">{{$auth_user->first_name}}'s <?= get_label('tasks', 'Tasks') ?></h4>
                </div>
                @if ($tasks > 0)
                <?php
                $type = isUser() ? 'user' : 'client';
                $id = isAdminOrHasAllDataAccess() ? '' : $type . '_' . $auth_user->id;
                ?>
                <x-tasks-card :tasks="$tasks" :id="$id" :users="$users" :clients="$clients" :projects="$projects" />
                @else
                <?php
                $type = 'Tasks'; ?>
                <x-empty-state-card :type="$type" />
                @endif

            </div>
            @endif

        </div>
    </div>
    @endif
    <!-- ------------------------------------------- -->
    <?php

    $titles = [];
    $project_counts = [];
    $task_counts = [];
    $bg_colors = [];
    $total_projects = 0;
    $total_tasks = 0;

    $total_todos = count($todos);
    $done_todos = 0;
    $pending_todos = 0;
    $todo_counts = [];
    $ran = array(
        '#63ed7a', '#ffa426', '#fc544b', '#6777ef', '#FF00FF', '#53ff1a', '#ff3300', '#0000ff', '#00ffff', '#99ff33', '#003366',
        '#cc3300', '#ffcc00', '#ff9900', '#3333cc', '#ffff00', '#FF5733', '#33FF57', '#5733FF', '#FFFF33', '#A6A6A6', '#FF99FF',
        '#6699FF', '#666666', '#FF6600', '#9900CC', '#FF99CC', '#FFCC99', '#99CCFF', '#33CCCC', '#CCFFCC', '#99CC99', '#669999',
        '#CCCCFF', '#6666FF', '#FF6666', '#99CCCC', '#993366', '#339966', '#99CC00', '#CC6666', '#660033', '#CC99CC', '#CC3300',
        '#FFCCCC', '#6600CC', '#FFCC33', '#9933FF', '#33FF33', '#FFFF66', '#9933CC', '#3300FF', '#9999CC', '#0066FF', '#339900',
        '#666633', '#330033', '#FF9999', '#66FF33', '#6600FF', '#FF0033', '#009999', '#CC0000', '#999999', '#CC0000', '#CCCC00',
        '#00FF33', '#0066CC', '#66FF66', '#FF33FF', '#CC33CC', '#660099', '#663366', '#996666', '#6699CC', '#663399', '#9966CC',
        '#66CC66', '#0099CC', '#339999', '#00CCCC', '#CCCC99', '#FF9966', '#99FF00', '#66FF99', '#336666', '#00FF66', '#3366CC',
        '#CC00CC', '#00FF99', '#FF0000', '#00CCFF', '#000000', '#FFFFFF'

    );

    foreach ($statuses as $status) {
        $project_count = isAdminOrHasAllDataAccess() ? count($status->projects) : $auth_user->status_projects($status->id)->count();
        array_push($project_counts, $project_count);

        $task_count = isAdminOrHasAllDataAccess() ? count($status->tasks) : $auth_user->status_tasks($status->id)->count();
        array_push($task_counts, $task_count);

        array_push($titles, "'" . $status->title . "'");

        $v = array_shift($ran);
        array_push($bg_colors, "'" . $v . "'");

        $total_projects += $project_count;
        $total_tasks += $task_count;
    }
    $titles = implode(",", $titles);
    $project_counts = implode(",", $project_counts);
    $task_counts = implode(",", $task_counts);
    $bg_colors = implode(",", $bg_colors);

    foreach ($todos as $todo) {
        $todo->is_completed ? $done_todos += 1 : $pending_todos += 1;
    }
    array_push($todo_counts, $done_todos);
    array_push($todo_counts, $pending_todos);
    $todo_counts = implode(",", $todo_counts);
    ?>
</div>
<script>
    var labels = [<?= $titles ?>];
    var project_data = [<?= $project_counts ?>];
    var task_data = [<?= $task_counts ?>];
    var bg_colors = [<?= $bg_colors ?>];
    var total_projects = [<?= $total_projects ?>];
    var total_tasks = [<?= $total_tasks ?>];
    var total_todos = [<?= $total_todos ?>];
    var todo_data = [<?= $todo_counts ?>];
    //labels
    var done = '<?= get_label('done', 'Done') ?>';
    var pending = '<?= get_label('pending', 'Pending') ?>';
    var total = '<?= get_label('total', 'Total') ?>';
</script>
<script src="{{asset('assets/js/apexcharts.js')}}"></script>
<script src="{{asset('assets/js/pages/dashboard.js')}}"></script>
@else
<div class="w-100 h-100 d-flex align-items-center justify-content-center"><span>You must <a href="/login">Log in</a> or <a href="/register">Register</a> to access {{$general_settings['company_title']}}!</span></div>
@endauth
@endsection