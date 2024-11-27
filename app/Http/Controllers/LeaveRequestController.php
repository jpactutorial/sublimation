<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Workspace;
use App\Models\LeaveEditor;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;

class LeaveRequestController extends Controller
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
    public function index()
    {
        $leave_requests = is_admin_or_leave_editor() ? $this->workspace->leave_requests() : $this->user->leave_requests();
        $users = $this->workspace->users(true)->get();
        return view('leave_requests.list', ['leave_requests' => $leave_requests->count(), 'users' => $users, 'auth_user' => $this->user]);
    }

    public function store(Request $request)
    {
        $formFields = $request->validate([
            'reason' => ['required'],
            'from_date' => ['required', 'before_or_equal:to_date'],
            'to_date' => ['required'],
            'from_time' => ['required_if:partialLeave,on'],
            'to_time' => ['required_if:partialLeave,on'],
            'status' => ['nullable'],
        ], [
            'from_time.required_if' => 'The from time field is required when partial leave is checked.',
            'to_time.required_if' => 'The to time field is required when partial leave is checked.',
        ]);

        if (!$this->user->hasRole('admin') && $request->input('status') && $request->filled('status') && $request->input('status') == 'approved') {
            return response()->json(['error' => true, 'message' => 'You cannot approve your own leave request.']);
        }

        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $formFields['from_date'] = format_date($from_date, false, app('php_date_format'), 'Y-m-d');
        $formFields['to_date'] = format_date($to_date, false, app('php_date_format'), 'Y-m-d');

        if (is_admin_or_leave_editor() && $request->input('status') && $request->filled('status') && $request->input('status') != 'pending') {
            $formFields['action_by'] = $this->user->id;
        }

        $formFields['workspace_id'] = $this->workspace->id;
        $formFields['user_id'] = is_admin_or_leave_editor() && $request->filled('user_id') ? $request->input('user_id') : $this->user->id;

        if ($lr = LeaveRequest::create($formFields)) {
            $visibleToUsers = $request->input('visible_to_ids', []);
            $lr->visibleToUsers()->sync($visibleToUsers);
            $lr = LeaveRequest::find($lr->id);
            $fromDate = Carbon::parse($lr->from_date);
            $toDate = Carbon::parse($lr->to_date);

            $fromDateDayOfWeek = $fromDate->format('D');
            $toDateDayOfWeek = $toDate->format('D');
            if ($lr->from_time && $lr->to_time) {
                $duration = 0;
                // Loop through each day
                while ($fromDate->lessThanOrEqualTo($toDate)) {
                    // Create Carbon instances for the start and end times of the leave request for the current day
                    $fromDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $lr->from_time);
                    $toDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $lr->to_time);

                    // Calculate the duration for the current day and add it to the total duration
                    $duration += $fromDateTime->diffInMinutes($toDateTime) / 60; // Duration in hours

                    // Move to the next day
                    $fromDate->addDay();
                }
            } else {
                // Calculate the inclusive duration in days
                $duration = $fromDate->diffInDays($toDate) + 1;
            }
            
            $leaveType = $lr->from_time && $lr->to_time ? get_label('partial', 'Partial') : get_label('full', 'Full');
            $from = $fromDateDayOfWeek . ', ' . ($lr->from_time ? format_date($lr->from_date . ' ' . $lr->from_time, true, null, null, false) : format_date($lr->from_date));
            $to = $toDateDayOfWeek . ', ' . ($lr->to_time ? format_date($lr->to_date . ' ' . $lr->to_time, true, null, null, false) : format_date($lr->to_date));
            $duration = $lr->from_time && $lr->to_time ? $duration . ' hour' . ($duration > 1 ? 's' : '') : $duration . ' day' . ($duration > 1 ? 's' : '');
            // Fetch user details based on the user_id in the leave request
            $user = User::find($lr->user_id);

            // Prepare notification data
            $notificationData = [
                'type' => 'leave_request_creation',
                'type_id' => $lr->id,
                'team_member_first_name' => $user->first_name,
                'team_member_last_name' => $user->last_name,
                'leave_type' => $leaveType,
                'from' => $from,
                'to' => $to,
                'duration' => $duration,
                'reason' => $lr->reason,
                'status' => ucfirst($lr->status),
                'action' => 'created',
                'title' => 'Leave Request Created',
                'message' => $this->user->first_name . ' ' . $this->user->last_name . ' has created a leave request, ID #' . $lr->id . '.'
            ];

            // Determine recipients
            $adminModelIds = DB::table('model_has_roles')
                ->select('model_id')
                ->where('role_id', 1)
                ->pluck('model_id')
                ->toArray();

            $leaveEditorIds = DB::table('leave_editors')
                ->pluck('user_id')
                ->toArray();

            // Combine admin model_ids and leave_editor_ids
            $adminIds = array_map(function ($modelId) {
                return 'u_' . $modelId;
            }, $adminModelIds);

            $leaveEditorIdsWithPrefix = array_map(function ($leaveEditorId) {
                return 'u_' . $leaveEditorId;
            }, $leaveEditorIds);

            // Combine admin and leave editor ids
            $recipients = array_merge($adminIds, $leaveEditorIdsWithPrefix);

            processNotifications($notificationData, $recipients);
            Session::flash('message', 'Leave request created successfully.');
            return response()->json(['error' => false, 'message' => 'Leave request created successfully.', 'id' => $lr->id, 'type' => 'leave_request']);
        } else {
            return response()->json(['error' => true, 'message' => 'Leave request couldn\'t be created.']);
        }
    }


    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $status = isset($_REQUEST['status']) && $_REQUEST['status'] !== '' ? $_REQUEST['status'] : "";
        $user_id = (request('user_id')) ? request('user_id') : "";
        $action_by_id = (request('action_by_id')) ? request('action_by_id') : "";
        $start_date_from = (request('start_date_from')) ? request('start_date_from') : "";
        $start_date_to = (request('start_date_to')) ? request('start_date_to') : "";
        $end_date_from = (request('end_date_from')) ? request('end_date_from') : "";
        $end_date_to = (request('end_date_to')) ? request('end_date_to') : "";
        $type = (request('type')) ? request('type') : "";
        $where = ['workspace_id' => $this->workspace->id];

        if (!is_admin_or_leave_editor()) {
            // If the user is not an admin or leave editor, filter by user_id
            $where['user_id'] = $this->user->id;
        }

        if ($status != '') {
            $where['leave_requests.status'] = $status;
        }

        $leave_requests = LeaveRequest::select(
            'leave_requests.*',
            'users.photo AS user_photo',
            DB::raw('CONCAT(users.first_name, " ", users.last_name) AS user_name'),
            DB::raw('CONCAT(action_users.first_name, " ", action_users.last_name) AS action_by_name')
        )
            ->leftJoin('users', 'leave_requests.user_id', '=', 'users.id')
            ->leftJoin('users AS action_users', 'leave_requests.action_by', '=', 'action_users.id');

        if ($user_id) {
            $where['user_id'] = $user_id;
        }
        if ($action_by_id) {
            $where['action_by'] = $action_by_id;
        }
        if ($start_date_from && $start_date_to) {
            $leave_requests = $leave_requests->whereBetween('from_date', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $leave_requests  = $leave_requests->whereBetween('to_date', [$end_date_from, $end_date_to]);
        }
        if ($type) {
            if ($type == 'full') {
                $leave_requests = $leave_requests->whereNull('from_time')->whereNull('to_time');
            } elseif ($type == 'partial') {
                $leave_requests = $leave_requests->whereNotNull('from_time')->whereNotNull('to_time');
            }
        }
        if ($search) {
            $leave_requests = $leave_requests->where(function ($query) use ($search) {
                $query->where('reason', 'like', '%' . $search . '%')
                    ->orWhere('leave_requests.id', 'like', '%' . $search . '%');
            });
        }

        $leave_requests->where($where);
        $total = $leave_requests->count();

        $leave_requests = $leave_requests->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($leave_request) {
                // Calculate the duration in hours if both from_time and to_time are provided
                $fromDate = Carbon::parse($leave_request->from_date);
                $toDate = Carbon::parse($leave_request->to_date);

                $fromDateDayOfWeek = $fromDate->format('D');
                $toDateDayOfWeek = $toDate->format('D');

                if ($leave_request->from_time && $leave_request->to_time) {
                    $duration = 0;
                    // Loop through each day
                    while ($fromDate->lessThanOrEqualTo($toDate)) {
                        // Create Carbon instances for the start and end times of the leave request for the current day
                        $fromDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $leave_request->from_time);
                        $toDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $leave_request->to_time);

                        // Calculate the duration for the current day and add it to the total duration
                        $duration += $fromDateTime->diffInMinutes($toDateTime) / 60; // Duration in hours

                        // Move to the next day
                        $fromDate->addDay();
                    }
                } else {
                    // Calculate the inclusive duration in days
                    $duration = $fromDate->diffInDays($toDate) + 1;
                }
                
                // Format "from_date" and "to_date" with labels
                $formattedDates = $duration > 1 ? format_date($leave_request->from_date) . ' ' . get_label('to', 'To') . ' ' . format_date($leave_request->to_date) : format_date($leave_request->from_date);
                $statusBadges = [
                    'pending' => '<span class="badge bg-warning">' . get_label('pending', 'Pending') . '</span>',
                    'approved' => '<span class="badge bg-success">' . get_label('approved', 'Approved') . '</span>',
                    'rejected' => '<span class="badge bg-danger">' . get_label('rejected', 'Rejected') . '</span>',
                ];
                $statusBadge = $statusBadges[$leave_request->status] ?? '';

                $visibleTo = $leave_request->visibleToUsers->isEmpty()
                    ? '-'
                    : $leave_request->visibleToUsers->map(function ($user) {
                        $profileLink = route('users.profile', ['id' => $user->id]);
                        return '<a href="' . $profileLink . '" target="_blank">' . $user->first_name . ' ' . $user->last_name . '</a>';
                    })->implode(', ');


                return [
                    'id' => $leave_request->id,
                    'user_name' => $leave_request->user_name . "<ul class='list-unstyled users-list m-0 avatar-group d-flex align-items-center'><a href='/users/profile/" . $leave_request->user_id . "' target='_blank'><li class='avatar avatar-sm pull-up'  title='" . $leave_request->user_name . "'>
            <img src='" . ($leave_request->user_photo ? asset('storage/' . $leave_request->user_photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle'>",
                    'action_by' => $leave_request->action_by_name,
                    'from_date' => $fromDateDayOfWeek . ', ' . ($leave_request->from_time ? format_date($leave_request->from_date . ' ' . $leave_request->from_time, true, null, null, false) : format_date($leave_request->from_date)),
                    'to_date' => $toDateDayOfWeek . ', ' . ($leave_request->to_time ? format_date($leave_request->to_date . ' ' . $leave_request->to_time, true, null, null, false) : format_date($leave_request->to_date)),
                    'type' => $leave_request->from_time && $leave_request->to_time ? '<span class="badge bg-info">' . get_label('partial', 'Partial') . '</span>' : '<span class="badge bg-primary">' . get_label('full', 'Full') . '</span>',
                    'duration' => $leave_request->from_time && $leave_request->to_time ? $duration . ' hour' . ($duration > 1 ? 's' : '') : $duration . ' day' . ($duration > 1 ? 's' : ''),
                    'reason' => $leave_request->reason,
                    'status' => $statusBadge,
                    'visible_to' => $visibleTo,
                    'created_at' => format_date($leave_request->created_at, true),
                    'updated_at' => format_date($leave_request->updated_at, true),
                ];
            });
        return response()->json([
            "rows" => $leave_requests->items(),
            "total" => $total,
        ]);
    }

    public function get($id)
    {
        $lr = LeaveRequest::with('user')->findOrFail($id);
        $visibleTo = $lr->visibleToUsers;
        return response()->json(['lr' => $lr, 'visibleTo' => $visibleTo]);
    }

    public function update(Request $request)
    {
            $isAdminOrLe = is_admin_or_leave_editor();
            $validatedData = $request->validate([
                'id' => 'required|exists:leave_requests,id', // Ensure the leave request exists
                'reason' => ['required'],
                'from_date' => ['required', 'before_or_equal:to_date'],
                'to_date' => ['required'],
                'from_time' => ['required_if:partialLeave,on'],
                'to_time' => ['required_if:partialLeave,on'],
                'status' => $isAdminOrLe ? 'required|in:pending,approved,rejected' : 'nullable|in:pending,approved,rejected',
            ], [
                'from_time.required_if' => 'The from time field is required when partial leave is checked.',
                'to_time.required_if' => 'The to time field is required when partial leave is checked.',
            ]);

            // Find the leave request by its ID
            $leaveRequest = LeaveRequest::findOrFail($validatedData['id']);
            $currentStatus = $leaveRequest->status;
            $newStatus = $validatedData['status']??$currentStatus;

            if (!is_null($leaveRequest->action_by) && !$this->user->hasRole('admin')) {
                return response()->json([
                    'error' => true,
                    'message' => 'Once actioned only admin can update leave request.',
                ]);
            }

            if ($leaveRequest->user_id == $this->user->id && !$this->user->hasRole('admin') && $request->input('status') && $request->filled('status') && $request->input('status') == 'approved') {
                return response()->json([
                    'error' => true,
                    'message' => 'You can not approve own leave request.',
                ]);
            }

            $from_date = $request->input('from_date');
            $to_date = $request->input('to_date');
            $validatedData['from_date'] = format_date($from_date, false, app('php_date_format'), 'Y-m-d');
            $validatedData['to_date'] = format_date($to_date, false, app('php_date_format'), 'Y-m-d');
            if ($newStatus != $currentStatus) {
                $validatedData['action_by'] = $this->user->id;
            }

            // Update the status of the leave request
            if ($leaveRequest->update($validatedData)) {
                $leaveRequest = $leaveRequest->fresh();
                $visibleToUsers = $request->input('visible_to_ids', []);
                $leaveRequest->visibleToUsers()->sync($visibleToUsers);
                if ($newStatus != $currentStatus) {
                    $fromDate = Carbon::parse($leaveRequest->from_date);
                    $toDate = Carbon::parse($leaveRequest->to_date);

                    $fromDateDayOfWeek = $fromDate->format('D');
                    $toDateDayOfWeek = $toDate->format('D');
                    if ($leaveRequest->from_time && $leaveRequest->to_time) {
                        $duration = 0;
                        // Loop through each day
                        while ($fromDate->lessThanOrEqualTo($toDate)) {
                            // Create Carbon instances for the start and end times of the leave request for the current day
                            $fromDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $leaveRequest->from_time);
                            $toDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $leaveRequest->to_time);

                            // Calculate the duration for the current day and add it to the total duration
                            $duration += $fromDateTime->diffInMinutes($toDateTime) / 60; // Duration in hours

                            // Move to the next day
                            $fromDate->addDay();
                        }
                    } else {
                        // Calculate the inclusive duration in days
                        $duration = $fromDate->diffInDays($toDate) + 1;
                    }
                    
                    $leaveType = $leaveRequest->from_time && $leaveRequest->to_time ? get_label('partial', 'Partial') : get_label('full', 'Full');
                    $from = $fromDateDayOfWeek . ', ' . ($leaveRequest->from_time ? format_date($leaveRequest->from_date . ' ' . $leaveRequest->from_time, true, null, null, false) : format_date($leaveRequest->from_date));
                    $to = $toDateDayOfWeek . ', ' . ($leaveRequest->to_time ? format_date($leaveRequest->to_date . ' ' . $leaveRequest->to_time, true, null, null, false) : format_date($leaveRequest->to_date));
                    $duration = $leaveRequest->from_time && $leaveRequest->to_time ? $duration . ' hour' . ($duration > 1 ? 's' : '') : $duration . ' day' . ($duration > 1 ? 's' : '');
                    // Fetch user details based on the user_id in the leave request
                    $user = User::find($leaveRequest->user_id);

                    // Prepare notification data
                    $notificationData = [
                        'type' => 'leave_request_status_updation',
                        'type_id' => $leaveRequest->id,
                        'team_member_first_name' => $user->first_name,
                        'team_member_last_name' => $user->last_name,
                        'leave_type' => $leaveType,
                        'from' => $from,
                        'to' => $to,
                        'duration' => $duration,
                        'reason' => $leaveRequest->reason,
                        'old_status' => ucfirst($currentStatus),
                        'new_status' => ucfirst($newStatus),
                        'action' => 'status_updated',
                        'title' => 'Leave Request Status Updated',
                        'message' => $this->user->first_name . ' ' . $this->user->last_name . ' has updated the leave request, ID #' . $leaveRequest->id . ' status from ' . ucfirst($currentStatus) . ' to ' . ucfirst($newStatus)
                    ];

                    // Determine recipients
                    $adminModelIds = DB::table('model_has_roles')
                        ->select('model_id')
                        ->where('role_id', 1)
                        ->pluck('model_id')
                        ->toArray();

                    $leaveEditorIds = DB::table('leave_editors')
                        ->pluck('user_id')
                        ->toArray();

                    // Combine admin model_ids and leave_editor_ids
                    $adminIds = array_map(function ($modelId) {
                        return 'u_' . $modelId;
                    }, $adminModelIds);

                    $leaveEditorIdsWithPrefix = array_map(function ($leaveEditorId) {
                        return 'u_' . $leaveEditorId;
                    }, $leaveEditorIds);

                    $userWithPrefix = 'u_' . $leaveRequest->user_id;

                    // Combine admin and leave editor ids
                    $recipients = array_merge($adminIds, $leaveEditorIdsWithPrefix, [$userWithPrefix]);
                    processNotifications($notificationData, $recipients);
                }

                return response()->json([
                    'error' => false,
                    'message' => 'Leave request updated successfully.',
                    'id' => $leaveRequest->id,
                    'type' => 'leave_request'
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Leave request couldn\'t updated.'
                ]);
            }
    }

    public function update_editors(Request $request)
    {

        $userIds = $request->input('user_ids') ?? [];
        $currentLeaveEditorUserIds = LeaveEditor::pluck('user_id')->toArray();
        $usersToDetach = array_diff($currentLeaveEditorUserIds, $userIds);
        LeaveEditor::whereIn('user_id', $usersToDetach)->delete();
        foreach ($userIds as $assignedUserId) {
            // Check if a leave editor with the same user_id already exists
            $existingLeaveEditor = LeaveEditor::where('user_id', $assignedUserId)->first();

            if (!$existingLeaveEditor) {
                // Create a new LeaveEditor only if it doesn't exist
                $leaveEditor = new LeaveEditor();
                $leaveEditor->user_id = $assignedUserId;
                $leaveEditor->save();
            }
        }

        return response()->json(['error' => false, 'message' => 'Leave editors updated successfully.']);
    }

    public function destroy($id)
    {
        DeletionService::delete(LeaveRequest::class, $id, 'Leave request');
        return response()->json(['error' => false, 'message' => 'Leave request deleted successfully.', 'id' => $id, 'type' => 'leave_request']);
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:leave_requests,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedIds = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $deletedIds[] = $id;
            DeletionService::delete(LeaveRequest::class, $id, 'Leave request');
        }

        return response()->json(['error' => false, 'message' => 'Leave request(s) deleted successfully.', 'id' => $deletedIds, 'type' => 'leave_request']);
    }
}
