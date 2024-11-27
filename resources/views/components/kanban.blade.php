@props(['task'])
@php
$user = getAuthenticatedUser();
@endphp
<div class="card m-2 shadow" data-task-id="{{$task->id}}">
    <div class="card-body">
        <div class="d-flex justify-content-between">
            <h6 class="card-title"><a href="{{url('/tasks/information/' . $task->id)}}" target="_blank"><strong>{{$task->title}}</strong></a></h6>
            <div>
                @if ($user->can('edit_tasks') || $user->can('delete_tasks') || $user->can('create_tasks'))
                <div class="input-group">
                    <a href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class='bx bx-cog'></i>
                    </a>
                    <ul class="dropdown-menu">
                        @if ($user->can('edit_tasks'))
                        <a href="javascript:void(0);" class="edit-task" data-id="{{$task->id}}">
                            <li class="dropdown-item">
                                <i class='menu-icon tf-icons bx bx-edit text-primary'></i> <?= get_label('update', 'Update') ?>
                            </li>
                        </a>
                        @endif
                        @if ($user->can('delete_tasks'))
                        <a href="javascript:void(0);" class="delete" data-reload="true" data-type="tasks" data-id="{{ $task->id }}">
                            <li class="dropdown-item">
                                <i class='menu-icon tf-icons bx bx-trash text-danger'></i> <?= get_label('delete', 'Delete') ?>
                            </li>
                        </a>
                        @endif
                        @if ($user->can('create_tasks'))
                        <a href="javascript:void(0);" class="duplicate" data-reload="true" data-type="tasks" data-id="{{$task->id}}" data-title="{{$task->title}}">
                            <li class="dropdown-item">
                                <i class='menu-icon tf-icons bx bx-copy text-warning'></i><?= get_label('duplicate', 'Duplicate') ?>
                            </li>
                        </a>
                        @endif
                    </ul>
                </div>
                @endif
            </div>
        </div>

        <div class="card-subtitle text-muted mb-3">{{$task->project->title}}</div>
        <div class="row mt-2">
            <div class="col-md-12">
                <p class="card-text mb-1">
                    <?= get_label('users', 'Users') ?>:
                <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                    <?php
                    $users = $task->users;
                    $count = count($users);
                    $displayed = 0;
                    if ($count > 0) {
                        foreach ($users as $user) {
                            if ($displayed < 9) { ?>
                                <li class="avatar avatar-sm pull-up" title="<?= $user->first_name ?> <?= $user->last_name ?>">
                                    <a href="/users/profile/<?= $user->id ?>" target="_blank">
                                        <img src="<?= $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg') ?>" class="rounded-circle" alt="<?= $user->first_name ?> <?= $user->last_name ?>">
                                    </a>
                                </li>
                    <?php
                                $displayed++;
                            } else {
                                $remaining = $count - $displayed;
                                echo '<span class="badge badge-center rounded-pill bg-primary mx-1">+' . $remaining . '</span>';
                                break;
                            }
                        }
                        // Add edit option at the end
                        echo '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-task update-users-clients" data-id="' . $task->id . '"><span class="bx bx-edit"></span></a>';
                    } else {
                        echo '<span class="badge bg-primary">' . get_label('not_assigned', 'Not assigned') . '</span>';
                        // Add edit option at the end
                        echo '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-task update-users-clients" data-id="' . $task->id . '"><span class="bx bx-edit"></span></a>';
                    }
                    ?>
                </ul>

                </p>
            </div>

            <div class="col-md-12">
                <p class="card-text mb-1">
                    Clients:
                <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                    <?php
                    $clients = $task->project->clients;
                    $count = $clients->count();
                    $displayed = 0;
                    if ($count > 0) {
                        foreach ($clients as $client) {
                            if ($displayed < 10) { ?>
                                <li class="avatar avatar-sm pull-up" title="<?= $client->first_name ?> <?= $client->last_name ?>">
                                    <a href="/clients/profile/<?= $client->id ?>" target="_blank">
                                        <img src="<?= $client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg') ?>" class="rounded-circle" alt="<?= $client->first_name ?> <?= $client->last_name ?>">
                                    </a>
                                </li>
                    <?php
                                $displayed++;
                            } else {
                                $remaining = $count - $displayed;
                                echo '<span class="badge badge-center rounded-pill bg-primary mx-1">+' . $remaining . '</span>';
                                break;
                            }
                        }
                    } else {
                        // Display "Not assigned" badge
                        echo '<span class="badge bg-primary">' . get_label('not_assigned', 'Not assigned') . '</span>';
                    }
                    ?>
                </ul>

                </p>
            </div>
        </div>
        <div class="d-flex flex-column">
            <div>
                <label for="statusSelect"><?= get_label('status', 'Status') ?></label>
                <select class="form-select form-select-sm select-bg-label-{{$task->status->color}} mb-3" id="statusSelect" data-id="{{ $task->id }}" data-original-status-id="{{ $task->status->id }}" data-original-color-class="select-bg-label-{{$task->status->color}}" data-type="task" data-reload="true">
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
            <div>
                <label for="prioritySelect"><?= get_label('priority', 'Priority') ?></label>
                <select class="form-select form-select-sm select-bg-label-{{$task->priority?$task->priority->color:'secondary'}}" id="prioritySelect" data-id="{{ $task->id }}" data-original-priority-id="{{ $task->priority ? $task->priority->id : '' }}" data-original-color-class="select-bg-label-{{$task->priority?$task->priority->color:'secondary'}}" data-type="task">
                    @foreach($priorities as $priority)
                    <option value="{{ $priority->id }}" class="badge bg-label-{{ $priority->color }}" {{ $task->priority && $task->priority->id == $priority->id ? 'selected' : '' }}>
                        {{ $priority->title }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="mt-2">
                <small class="text-muted"><?= get_label('created_at', 'Created At') ?>: {{ format_date($task->created_at) }}</small>
            </div>
        </div>


    </div>
</div>