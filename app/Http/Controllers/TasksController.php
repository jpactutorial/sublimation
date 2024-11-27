<?php

namespace App\Http\Controllers;

use PDO;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Models\Status;
use App\Models\Priority;
use App\Models\Project;
use App\Models\Workspace;
use App\Models\UserClientPreference;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Exception;

class TasksController extends Controller
{
    protected $workspace;
    protected $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            // fetch session and use it in entire class with constructor
            $this->workspace = Workspace::find(session()->get('workspace_id'));
            $this->user = getAuthenticatedUser();
            return $next($request);
        });
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id = '')
    {
        $project = (object)[];
        if ($id) {
            $project = Project::findOrFail($id);
            $tasks = isAdminOrHasAllDataAccess() ? $project->tasks : $this->user->project_tasks($id);
            $toSelectTaskUsers = $project->users;
        } else {
            $tasks = isAdminOrHasAllDataAccess() ? $this->workspace->tasks : $this->user->tasks();
            $toSelectTaskUsers = $this->workspace->users;
        }
        $tasks = $tasks->count();
        $users = $this->workspace->users;
        $clients = $this->workspace->clients;
        $projects = isAdminOrHasAllDataAccess() ? $this->workspace->projects : $this->user->projects;
        return view('tasks.tasks', ['project' => $project, 'tasks' => $tasks, 'users' => $users, 'clients' => $clients, 'projects' => $projects, 'toSelectTaskUsers' => $toSelectTaskUsers]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($id = '')
    {
        $project = (object)[];
        $projects = [];
        if ($id) {
            $project = Project::find($id);
            $users = $project->users;
        } else {
            $projects = isAdminOrHasAllDataAccess() ? $this->workspace->projects : $this->user->projects;
            $users = $this->workspace->users;
        }
        return view('tasks.create_task', ['project' => $project, 'projects' => $projects, 'users' => $users]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $formFields = $request->validate([
            'title' => ['required'],
            'status_id' => ['required'],
            'priority_id' => ['nullable'],
            'start_date' => ['required', 'before_or_equal:due_date'],
            'due_date' => ['required'],
            'description' => ['nullable'],
            'project' => ['required'],
            'note' => ['nullable']
        ]);
        $status = Status::findOrFail($request->input('status_id'));
        if (canSetStatus($status)) {
            $project_id = $request->input('project');
            $start_date = $request->input('start_date');
            $due_date = $request->input('due_date');
            $formFields['start_date'] = format_date($start_date, false, app('php_date_format'), 'Y-m-d');
            $formFields['due_date'] = format_date($due_date, false, app('php_date_format'), 'Y-m-d');

            $formFields['workspace_id'] = $this->workspace->id;
            $formFields['created_by'] = $this->user->id;

            $formFields['project_id'] = $project_id;
            $userIds = $request->input('user_id', []);

            $new_task = Task::create($formFields);
            $task_id = $new_task->id;
            $task = Task::find($task_id);
            $task->users()->attach($userIds);


            $notification_data = [
                'type' => 'task',
                'type_id' => $task_id,
                'type_title' => $task->title,
                'access_url' => 'tasks/information/' . $task->id,
                'action' => 'assigned',
                'title' => 'New task assigned',
                'message' => $this->user->first_name . ' ' . $this->user->last_name . ' assigned you new task : ' . $task->title . ', ID #' . $task_id . '.'
            ];
            // $clientIds = $project->clients()->pluck('clients.id')->toArray();
            // $recipients = array_merge(
            //     array_map(function ($userId) {
            //         return 'u_' . $userId;
            //     }, $userIds),
            //     array_map(function ($clientId) {
            //         return 'c_' . $clientId;
            //     }, $clientIds)
            // );
            $recipients = array_map(function ($userId) {
                return 'u_' . $userId;
            }, $userIds);
            processNotifications($notification_data, $recipients);
            Session::flash('message', 'Task created successfully.');
            return response()->json(['error' => false, 'id' => $new_task->id, 'parent_id' => $project_id, 'parent_type' => 'project', 'message' => 'Task created successfully.']);
        } else {
            return response()->json(['error' => true, 'message' => 'You are not authorized to set this status.']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $task = Task::findOrFail($id);
        return view('tasks.task_information', ['task' => $task, 'auth_user' => $this->user]);
    }

    public function get($id)
    {
        $task = Task::with('users')->findOrFail($id);
        $project = $task->project()->with('users')->firstOrFail();

        return response()->json(['error' => false, 'task' => $task, 'project' => $project]);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $task = Task::findOrFail($id);
        $project = $task->project;
        $users = $task->project->users;
        $task_users = $task->users;
        return view('tasks.update_task', ["project" => $project, "task" => $task, "users" => $users, "task_users" => $task_users]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $formFields = $request->validate([
            'id' => 'required|exists:tasks,id',
            'title' => ['required'],
            'status_id' => ['required'],
            'priority_id' => ['nullable'],
            'start_date' => ['required', 'before_or_equal:due_date'],
            'due_date' => ['required'],
            'description' => ['nullable'],
            'note' => ['nullable']
        ]);
        $status = Status::findOrFail($request->input('status_id'));
        $id = $request->input('id');
        $task = Task::findOrFail($id);
        $currentStatusId = $task->status_id;

        // Check if the status has changed
        if ($currentStatusId != $request->input('status_id')) {
            $status = Status::findOrFail($request->input('status_id'));
            if (!canSetStatus($status)) {
                return response()->json(['error' => true, 'message' => 'You are not authorized to set this status.']);
            }
        }
        $start_date = $request->input('start_date');
        $due_date = $request->input('due_date');
        $formFields['start_date'] = format_date($start_date, false, app('php_date_format'), 'Y-m-d');
        $formFields['due_date'] = format_date($due_date, false, app('php_date_format'), 'Y-m-d');

        $userIds = $request->input('user_id', []);

        $task = Task::findOrFail($id);
        $task->update($formFields);

        // Get the current users associated with the task
        $currentUsers = $task->users->pluck('id')->toArray();
        $currentClients = $task->project->clients->pluck('id')->toArray();

        // Sync the users for the task
        $task->users()->sync($userIds);

        // Get the new users associated with the task
        $newUsers = array_diff($userIds, $currentUsers);

        // Prepare notification data for new users
        $notification_data = [
            'type' => 'task',
            'type_id' => $id,
            'type_title' => $task->title,
            'access_url' => 'tasks/information/' . $task->id,
            'action' => 'assigned',
            'title' => 'Task updated',
            'message' => $this->user->first_name . ' ' . $this->user->last_name . ' assigned you new task : ' . $task->title . ', ID #' . $id . '.'
        ];

        // Notify only the new users
        $recipients = array_map(function ($userId) {
            return 'u_' . $userId;
        }, $newUsers);

        // Process notifications for new users
        processNotifications($notification_data, $recipients);

        if ($currentStatusId != $request->input('status_id')) {
            $currentStatus = Status::findOrFail($currentStatusId);
            $newStatus = Status::findOrFail($request->input('status_id'));

            $notification_data = [
                'type' => 'task_status_updation',
                'type_id' => $id,
                'type_title' => $task->title,
                'updater_first_name' => $this->user->first_name,
                'updater_last_name' => $this->user->last_name,
                'old_status' => $currentStatus->title,
                'new_status' => $newStatus->title,
                'access_url' => 'tasks/information/' . $id,
                'action' => 'status_updated',
                'title' => 'Task status updated',
                'message' => $this->user->first_name . ' ' . $this->user->last_name . ' has updated the status of task : ' . $task->title . ', ID #' . $id . ' from ' . $currentStatus->title . ' to ' . $newStatus->title
            ];

            $currentRecipients = array_merge(
                array_map(function ($userId) {
                    return 'u_' . $userId;
                }, $currentUsers),
                array_map(function ($clientId) {
                    return 'c_' . $clientId;
                }, $currentClients)
            );
            processNotifications($notification_data, $currentRecipients);
        }
        return response()->json(['error' => false, 'id' => $id, 'parent_id' => $task->project->id, 'parent_type' => 'project',  'message' => 'Task updated successfully.']);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $task = Task::find($id);
        DeletionService::delete(Task::class, $id, 'Task');
        return response()->json(['error' => false, 'message' => 'Task deleted successfully.', 'id' => $id, 'title' => $task->title, 'parent_id' => $task->project_id, 'parent_type' => 'project']);
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:tasks,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedTasks = [];
        $deletedTaskTitles = [];
        $parentIds = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $task = Task::find($id);
            if ($task) {
                $deletedTaskTitles[] = $task->title;
                DeletionService::delete(Task::class, $id, 'Task');
                $deletedTasks[] = $id;
                $parentIds[] = $task->project_id;
            }
        }

        return response()->json(['error' => false, 'message' => 'Task(s) deleted successfully.', 'id' => $deletedTasks, 'titles' => $deletedTaskTitles, 'parent_id' => $parentIds, 'parent_type' => 'project']);
    }


    public function list($id = '')
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $status_ids = request('status_ids', []);
        $priority_ids = request('priority_ids', []);
        $user_ids = request('user_ids', []);
        $client_ids = request('client_ids', []);
        $project_ids = request('project_ids', []);
        $start_date_from = (request('task_start_date_from')) ? trim(request('task_start_date_from')) : "";
        $start_date_to = (request('task_start_date_to')) ? trim(request('task_start_date_to')) : "";
        $end_date_from = (request('task_end_date_from')) ? trim(request('task_end_date_from')) : "";
        $end_date_to = (request('task_end_date_to')) ? trim(request('task_end_date_to')) : "";

        $where = [];


        if ($id) {
            $id = explode('_', $id);
            $belongs_to = $id[0];
            $belongs_to_id = $id[1];
            if ($belongs_to == 'project') {
                $project = Project::find($belongs_to_id);
                $tasks = $project->tasks();
            } else {
                $userOrClient = $belongs_to == 'user' ? User::find($belongs_to_id) : Client::find($belongs_to_id);
                $tasks = isAdminOrHasAllDataAccess($belongs_to, $belongs_to_id) ? $this->workspace->tasks() : $userOrClient->tasks();
            }
        } else {
            $tasks = isAdminOrHasAllDataAccess() ? $this->workspace->tasks() : $this->user->tasks();
        }
        if (!empty($user_ids)) {
            $taskIds = DB::table('task_user')
                ->whereIn('user_id', $user_ids)
                ->pluck('task_id')
                ->toArray();

            $tasks = $tasks->whereIn('id', $taskIds);
        }
        if (!empty($client_ids)) {
            $projectIds = DB::table('client_project')
                ->whereIn('client_id', $client_ids)
                ->pluck('project_id')
                ->toArray();

            $tasks = $tasks->whereIn('project_id', $projectIds);
        }

        if (!empty($project_ids)) {
            $tasks->whereIn('project_id', $project_ids);
        }
        if (!empty($status_ids)) {
            $tasks->whereIn('status_id', $status_ids);
        }
        if (!empty($priority_ids)) {
            $tasks->whereIn('priority_id', $priority_ids);
        }
        if ($start_date_from && $start_date_to) {
            $tasks->whereBetween('start_date', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $tasks->whereBetween('due_date', [$end_date_from, $end_date_to]);
        }
        if ($search) {
            $tasks = $tasks->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        // Apply where clause to $tasks
        $tasks = $tasks->where($where);

        // Count total tasks before pagination
        $totaltasks = $tasks->count();

        $canCreate = checkPermission('create_tasks');
        $canEdit = checkPermission('edit_tasks');
        $canDelete = checkPermission('delete_tasks');

        $statuses = Status::all();
        $priorities = Priority::all();
        // Paginate tasks and format them
        $tasks = $tasks->orderBy($sort, $order)->paginate(request('limit'))->through(function ($task) use ($statuses, $priorities, $canEdit, $canDelete, $canCreate) {
            $statusOptions = '';
            foreach ($statuses as $status) {
                $disabled = canSetStatus($status)  ? '' : 'disabled';
                $selected = $task->status_id == $status->id ? 'selected' : '';
                $statusOptions .= "<option value='{$status->id}' class='badge bg-label-{$status->color}' {$selected} {$disabled}>{$status->title}</option>";
            }

            $priorityOptions = '';
            foreach ($priorities as $priority) {
                $selectedPriority = $task->priority_id == $priority->id ? 'selected' : '';
                $priorityOptions .= "<option value='{$priority->id}' class='badge bg-label-{$priority->color}' {$selectedPriority}>{$priority->title}</option>";
            }

            $actions = '';

            if ($canEdit) {
                $actions .= '<a href="javascript:void(0);" class="edit-task" data-id="' . $task->id . '" title="' . get_label('update', 'Update') . '">' .
                    '<i class="bx bx-edit mx-1"></i>' .
                    '</a>';
            }

            if ($canDelete) {
                $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $task->id . '" data-type="tasks" data-table="task_table">' .
                    '<i class="bx bx-trash text-danger mx-1"></i>' .
                    '</button>';
            }

            if ($canCreate) {
                $actions .= '<a href="javascript:void(0);" class="duplicate" data-id="' . $task->id . '" data-title="' . $task->title . '" data-type="tasks" data-table="task_table" title="' . get_label('duplicate', 'Duplicate') . '">' .
                    '<i class="bx bx-copy text-warning mx-2"></i>' .
                    '</a>';
            }

            $actions .= '<a href="javascript:void(0);" class="quick-view" data-id="' . $task->id . '" title="' . get_label('quick_view', 'Quick View') . '">' .
                '<i class="bx bx-info-circle mx-3"></i>' .
                '</a>';

            $actions = $actions ?: '-';

            $userHtml = '';
            if (!empty($task->users) && count($task->users) > 0) {
                $userHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                foreach ($task->users as $user) {
                    $userHtml .= "<li class='avatar avatar-sm pull-up'><a href='/users/profile/{$user->id}' target='_blank' title='{$user->first_name} {$user->last_name}'><img src='" . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                }
                if ($canEdit) {
                    $userHtml .= '<li title=' . get_label('update', 'Update') . '><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-task update-users-clients" data-id="' . $task->id . '"><span class="bx bx-edit"></span></a></li>';
                }
                $userHtml .= '</ul>';
            } else {
                $userHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
                if ($canEdit) {
                    $userHtml .= '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-task update-users-clients" data-id="' . $task->id . '">' .
                        '<span class="bx bx-edit"></span>' .
                        '</a>';
                }
            }

            $clientHtml = '';
            if (!empty($task->project->clients) && count($task->project->clients) > 0) {
                $clientHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                foreach ($task->project->clients as $client) {
                    $clientHtml .= "<li class='avatar avatar-sm pull-up'><a href='/clients/profile/{$client->id}' target='_blank' title='{$client->first_name} {$client->last_name}'><img src='" . ($client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                }
                $clientHtml .= '</ul>';
            } else {
                $clientHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
            }

            return [
                'id' => $task->id,
                'title' => "<a href='/tasks/information/{$task->id}' target='_blank' title='{$task->description}'><strong>{$task->title}</strong></a>",
                'project_id' => "<a href='/projects/information/{$task->project->id}' target='_blank' title='{$task->project->description}'><strong>{$task->project->title}</strong></a> <a href='javascript:void(0);' class='mx-2'><i class='bx " . ($task->project->is_favorite ? 'bxs' : 'bx') . "-star favorite-icon text-warning' data-favorite='{$task->project->is_favorite}' data-id='{$task->project->id}' title='" . ($task->project->is_favorite ? get_label('remove_favorite', 'Click to remove from favorite') : get_label('add_favorite', 'Click to mark as favorite')) . "'></i></a>",
                'users' => $userHtml,
                'clients' => $clientHtml,
                'start_date' => format_date($task->start_date),
                'end_date' => format_date($task->due_date),
                'status_id' => "<select class='form-select form-select-sm select-bg-label-{$task->status->color}' id='statusSelect' data-id='{$task->id}' data-original-status-id='{$task->status->id}' data-original-color-class='select-bg-label-{$task->status->color}' data-type='task'>{$statusOptions}</select>",
                'priority_id' => "<select class='form-select form-select-sm select-bg-label-" . ($task->priority ? $task->priority->color : 'secondary') . "' id='prioritySelect' data-id='{$task->id}' data-original-priority-id='" . ($task->priority ? $task->priority->id : '') . "' data-original-color-class='select-bg-label-" . ($task->priority ? $task->priority->color : 'secondary') . "' data-type='task'>{$priorityOptions}</select>",
                'created_at' => format_date($task->created_at, true),
                'updated_at' => format_date($task->updated_at, true),
                'actions' => $actions
            ];
        });

        // Return JSON response with formatted tasks and total count
        return response()->json([
            "rows" => $tasks->items(),
            "total" => $totaltasks,
        ]);
    }


    public function dragula($id = '')
    {
        $project = (object)[];
        $projects = [];
        if ($id) {
            $project = Project::findOrFail($id);
            $tasks = isAdminOrHasAllDataAccess() ? $project->tasks : $this->user->project_tasks($id);
            $toSelectTaskUsers = $project->users;
        } else {
            $projects = isAdminOrHasAllDataAccess() ? $this->workspace->projects : $this->user->projects;
            $toSelectTaskUsers = $this->workspace->users;
            $tasks = isAdminOrHasAllDataAccess() ? $this->workspace->tasks : $this->user->tasks()->get();
        }
        if (request()->has('status')) {
            $tasks = $tasks->where('status_id', request()->status);
        }
        if (request()->has('project')) {
            $project = Project::findOrFail(request()->project);
            $tasks = $tasks->where('project_id', request()->project);
            $toSelectTaskUsers = $project->users;
        }
        $total_tasks = $tasks->count();
        return view('tasks.board_view', ['project' => $project, 'tasks' => $tasks, 'total_tasks' => $total_tasks, 'projects' => $projects, 'toSelectTaskUsers' => $toSelectTaskUsers]);
    }

    public function updateStatus($id, $newStatus)
    {
        $status = Status::findOrFail($newStatus);
        if (canSetStatus($status)) {
            $task = Task::findOrFail($id);
            $current_status = $task->status->title;
            $task->status_id = $newStatus;
            if ($task->save()) {
                $task->refresh();
                $new_status = $task->status->title;

                $notification_data = [
                    'type' => 'task_status_updation',
                    'type_id' => $id,
                    'type_title' => $task->title,
                    'updater_first_name' => $this->user->first_name,
                    'updater_last_name' => $this->user->last_name,
                    'old_status' => $current_status,
                    'new_status' => $new_status,
                    'access_url' => 'tasks/information/' . $id,
                    'action' => 'status_updated',
                    'title' => 'Task status updated',
                    'message' => $this->user->first_name . ' ' . $this->user->last_name . ' has updated the status of task : ' . $task->title . ', ID #' . $id . ' from ' . $current_status . ' to ' . $new_status
                ];
                $userIds = $task->users->pluck('id')->toArray();
                $clientIds = $task->project->clients->pluck('id')->toArray();
                $recipients = array_merge(
                    array_map(function ($userId) {
                        return 'u_' . $userId;
                    }, $userIds),
                    array_map(function ($clientId) {
                        return 'c_' . $clientId;
                    }, $clientIds)
                );
                processNotifications($notification_data, $recipients);

                return response()->json(['error' => false, 'message' => 'Task status updated successfully.', 'id' => $id, 'activity_message' => $this->user->first_name . ' ' . $this->user->last_name . ' updated task status from ' . $current_status . ' to ' . $new_status]);
            } else {
                return response()->json(['error' => true, 'message' => 'Task status couldn\'t updated.']);
            }
        } else {
            return response()->json(['error' => true, 'message' => 'You are not authorized to set this status.']);
        }
    }
    //For status change from dropdown
    public function update_status(Request $request)
    {
        $request->validate([
            'id' => ['required'],
            'statusId' => ['required']

        ]);
        $id = $request->id;
        $statusId = $request->statusId;
        $status = Status::findOrFail($statusId);
        if (canSetStatus($status)) {
            $task = Task::findOrFail($id);
            $currentStatus = $task->status->title;
            $task->status_id = $statusId;
            $task->note = $request->note;
            if ($task->save()) {
                $task = $task->fresh();
                $newStatus = $task->status->title;

                $notification_data = [
                    'type' => 'task_status_updation',
                    'type_id' => $id,
                    'type_title' => $task->title,
                    'updater_first_name' => $this->user->first_name,
                    'updater_last_name' => $this->user->last_name,
                    'old_status' => $currentStatus,
                    'new_status' => $newStatus,
                    'access_url' => 'tasks/information/' . $id,
                    'action' => 'status_updated',
                    'title' => 'Task status updated',
                    'message' => $this->user->first_name . ' ' . $this->user->last_name . ' has updated the status of task : ' . $task->title . ', ID #' . $id . ' from ' . $currentStatus . ' to ' . $newStatus
                ];
                $userIds = $task->users->pluck('id')->toArray();
                $clientIds = $task->project->clients->pluck('id')->toArray();
                $recipients = array_merge(
                    array_map(function ($userId) {
                        return 'u_' . $userId;
                    }, $userIds),
                    array_map(function ($clientId) {
                        return 'c_' . $clientId;
                    }, $clientIds)
                );
                processNotifications($notification_data, $recipients);


                return response()->json(['error' => false, 'message' => 'Status updated successfully.', 'id' => $id, 'type' => 'task', 'activity_message' => $this->user->first_name . ' ' . $this->user->last_name . ' updated task status from ' . $currentStatus . ' to ' . $newStatus]);
            } else {
                return response()->json(['error' => true, 'message' => 'Status couldn\'t updated.']);
            }
        } else {
            return response()->json(['error' => true, 'message' => 'You are not authorized to set this status.']);
        }
    }

    public function duplicate($id)
    {
        // Define the related tables for this meeting
        $relatedTables = ['users']; // Include related tables as needed

        // Use the general duplicateRecord function
        $title = (request()->has('title') && !empty(trim(request()->title))) ? request()->title : '';
        $duplicate = duplicateRecord(Task::class, $id, $relatedTables, $title);

        if (!$duplicate) {
            return response()->json(['error' => true, 'message' => 'Task duplication failed.']);
        }
        if (request()->has('reload') && request()->input('reload') === 'true') {
            Session::flash('message', 'Task duplicated successfully.');
        }
        return response()->json(['error' => false, 'message' => 'Task duplicated successfully.', 'id' => $id, 'parent_id' => $duplicate->project->id, 'parent_type' => 'project']);
    }

    public function upload_media(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'id' => 'integer|exists:tasks,id'
            ]);

            $mediaIds = [];

            if ($request->hasFile('media_files')) {
                $task = Task::find($validatedData['id']);
                $mediaFiles = $request->file('media_files');

                foreach ($mediaFiles as $mediaFile) {
                    $mediaItem = $task->addMedia($mediaFile)
                        ->sanitizingFileName(function ($fileName) use ($task) {
                            // Replace special characters and spaces with hyphens
                            $sanitizedFileName = strtolower(str_replace(['#', '/', '\\', ' '], '-', $fileName));

                            // Generate a unique identifier based on timestamp and random component
                            $uniqueId = time() . '_' . mt_rand(1000, 9999);

                            $extension = pathinfo($sanitizedFileName, PATHINFO_EXTENSION);
                            $baseName = pathinfo($sanitizedFileName, PATHINFO_FILENAME);

                            return "{$baseName}-{$uniqueId}.{$extension}";
                        })
                        ->toMediaCollection('task-media');

                    $mediaIds[] = $mediaItem->id;
                }
                return response()->json(['error' => false, 'message' => 'File(s) uploaded successfully.', 'id' => $mediaIds, 'type' => 'media', 'parent_type' => 'task', 'parent_id' => $task->id]);
            } else {
                return response()->json(['error' => true, 'message' => 'No file(s) chosen.']);
            }
        } catch (Exception $e) {
            // Handle the exception as needed
            return response()->json(['error' => true, 'message' => 'An error occurred during file upload: ' . $e->getMessage()]);
        }
    }


    public function get_media($id)
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $task = Task::findOrFail($id);
        $media = $task->getMedia('task-media');

        if ($search) {
            $media = $media->filter(function ($mediaItem) use ($search) {
                return (
                    // Check if ID contains the search query
                    stripos($mediaItem->id, $search) !== false ||
                    // Check if file name contains the search query
                    stripos($mediaItem->file_name, $search) !== false ||
                    // Check if date created contains the search query
                    stripos($mediaItem->created_at->format('Y-m-d'), $search) !== false
                );
            });
        }


        $formattedMedia = $media->map(function ($mediaItem) {
            // Check if the disk is public
            $isPublicDisk = $mediaItem->disk == 'public' ? 1 : 0;

            // Generate file URL based on disk visibility
            $fileUrl = $isPublicDisk
                ? asset('storage/task-media/' . $mediaItem->file_name)
                : $mediaItem->getFullUrl();


            $fileExtension = pathinfo($fileUrl, PATHINFO_EXTENSION);

            // Check if file extension corresponds to an image type
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];
            $isImage = in_array(strtolower($fileExtension), $imageExtensions);

            if ($isImage) {
                $html = '<a href="' . $fileUrl . '" data-lightbox="task-media">';
                $html .= '<img src="' . $fileUrl . '" alt="' . $mediaItem->file_name . '" width="50">';
                $html .= '</a>';
            } else {
                $html = '<a href="' . $fileUrl . '" title=' . get_label('download', 'Download') . '>' . $mediaItem->file_name . '</a>';
            }

            return [
                'id' => $mediaItem->id,
                'file' => $html,
                'file_name' => $mediaItem->file_name,
                'file_size' => formatSize($mediaItem->size),
                'created_at' => format_date($mediaItem->created_at, true),
                'updated_at' => format_date($mediaItem->updated_at, true),
                'actions' => [
                    '<a href="' . $fileUrl . '" title="' . get_label('download', 'Download') . '" download>' .
                        '<i class="bx bx-download bx-sm"></i>' .
                        '</a>' .
                        '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $mediaItem->id . '" data-type="task-media">' .
                        '<i class="bx bx-trash text-danger"></i>' .
                        '</button>'
                ],


            ];
        });

        if ($order == 'asc') {
            $formattedMedia = $formattedMedia->sortBy($sort);
        } else {
            $formattedMedia = $formattedMedia->sortByDesc($sort);
        }

        return response()->json([
            'rows' => $formattedMedia->values()->toArray(),
            'total' => $formattedMedia->count(),
        ]);
    }

    public function delete_media($mediaId)
    {
        $mediaItem = Media::find($mediaId);

        if (!$mediaItem) {
            // Handle case where media item is not found
            return response()->json(['error' => true, 'message' => 'File not found.']);
        }

        // Delete media item from the database and disk
        $mediaItem->delete();

        return response()->json(['error' => false, 'message' => 'File deleted successfully.', 'id' => $mediaId, 'title' => $mediaItem->file_name, 'parent_id' => $mediaItem->model_id,  'type' => 'media', 'parent_type' => 'task']);
    }

    public function delete_multiple_media(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:media,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        $deletedTitles = [];
        $parentIds = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $media = Media::find($id);
            if ($media) {
                $deletedIds[] = $id;
                $deletedTitles[] = $media->file_name;
                $parentIds[] = $media->model_id;
                $media->delete();
            }
        }

        return response()->json(['error' => false, 'message' => 'Files(s) deleted successfully.', 'id' => $deletedIds, 'titles' => $deletedTitles, 'parent_id' => $parentIds, 'type' => 'media', 'parent_type' => 'task']);
    }

    public function update_priority(Request $request)
    {
        $request->validate([
            'id' => ['required'],
            'priorityId' => ['nullable']

        ]);
        $id = $request->id;
        $priorityId = $request->priorityId;
        $task = Task::findOrFail($id);
        $currentPriority = $task->priority ? $task->priority->title : 'Default';
        $task->priority_id = $priorityId;
        $task->note = $request->note;
        if ($task->save()) {
            // Reload the task to get updated priority information
            $task = $task->fresh();
            $newPriority = $task->priority ? $task->priority->title : 'Default';
            $message = $this->user->first_name . ' ' . $this->user->last_name . ' updated task priority from ' . $currentPriority . ' to ' . $newPriority;
            return response()->json(['error' => false, 'message' => 'Priority updated successfully.', 'id' => $id, 'type' => 'task', 'activity_message' => $message]);
        } else {
            return response()->json(['error' => true, 'message' => 'Priority couldn\'t updated.']);
        }
    }

    public function saveViewPreference(Request $request)
    {
        $view = $request->input('view');
        $prefix = isClient() ? 'c_' : 'u_';
        UserClientPreference::updateOrCreate(
            ['user_id' => $prefix . $this->user->id, 'table_name' => 'tasks'],
            ['default_view' => $view]
        );
        return response()->json(['error' => false, 'message' => 'Default View Set Successfully.']);
    }
}
