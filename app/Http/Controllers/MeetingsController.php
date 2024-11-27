<?php

namespace App\Http\Controllers;

use DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Client;
use App\Models\Meeting;
use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Services\DeletionService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class MeetingsController extends Controller
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
        $meetings = isAdminOrHasAllDataAccess() ? $this->workspace->meetings : $this->user->meetings;
        $users = $this->workspace->users;
        $clients = $this->workspace->clients;
        return view('meetings.meetings', compact('meetings', 'users', 'clients'));
    }

    public function create()
    {
        $users = $this->workspace->users;
        $clients = $this->workspace->clients;
        $auth_user = $this->user;

        return view('meetings.create_meeting', compact('users', 'clients', 'auth_user'));
    }

    public function store(Request $request)
    {
        $formFields = $request->validate([
            'title' => ['required'],
            'start_date' => ['required', 'before_or_equal:end_date'],
            'end_date' => ['required', 'after_or_equal:start_date'],
            'start_time' => ['required'],
            'end_time' => ['required']

        ]);
        $start_date = $request->input('start_date');
        $start_time = $request->input('start_time');
        $end_date = $request->input('end_date');
        $end_time = $request->input('end_time');

        $formFields['start_date_time'] = format_date($start_date, false, app('php_date_format'), 'Y-m-d', false) . ' ' . $start_time;
        $formFields['end_date_time'] = format_date($end_date, false, app('php_date_format'), 'Y-m-d', false) . ' ' . $end_time;

        $formFields['workspace_id'] = $this->workspace->id;
        $formFields['user_id'] =  $this->user->id;
        $userIds = $request->input('user_ids') ?? [];
        $clientIds = $request->input('client_ids') ?? [];

        // Set creator as a participant automatically

        if (Auth::guard('client')->check() && !in_array($this->user->id, $clientIds)) {
            array_splice($clientIds, 0, 0, $this->user->id);
        } else if (Auth::guard('web')->check() && !in_array($this->user->id, $userIds)) {
            array_splice($userIds, 0, 0, $this->user->id);
        }

        $new_meeting = Meeting::create($formFields);
        $meeting_id = $new_meeting->id;
        $meeting = Meeting::find($meeting_id);
        $meeting->users()->attach($userIds);
        $meeting->clients()->attach($clientIds);

        // Prepare notification data
        $notification_data = [
            'type' => 'meeting',
            'type_id' => $meeting_id,
            'type_title' => $meeting->title,
            'action' => 'assigned',
            'title' => 'Added in a meeting',
            'message' => $this->user->first_name . ' ' . $this->user->last_name . ' added you in meeting: ' . $meeting->title . ', ID #' . $meeting_id . '.'

        ];

        // Combine user and client IDs for notification recipients
        $recipients = array_merge(
            array_map(function ($userId) {
                return 'u_' . $userId;
            }, $userIds),
            array_map(function ($clientId) {
                return 'c_' . $clientId;
            }, $clientIds)
        );

        // Process notifications
        processNotifications($notification_data, $recipients);

        Session::flash('message', 'Meeting created successfully.');
        return response()->json(['error' => false, 'id' => $meeting_id, 'message' => 'Meeting created successfully.']);
    }

    public function list()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "id";
        $order = (request('order')) ? request('order') : "DESC";
        $status = isset($_REQUEST['status']) && $_REQUEST['status'] !== '' ? $_REQUEST['status'] : "";
        $user_id = (request('user_id')) ? request('user_id') : "";
        $client_id = (request('client_id')) ? request('client_id') : "";
        $start_date_from = (request('start_date_from')) ? request('start_date_from') : "";
        $start_date_to = (request('start_date_to')) ? request('start_date_to') : "";
        $end_date_from = (request('end_date_from')) ? request('end_date_from') : "";
        $end_date_to = (request('end_date_to')) ? request('end_date_to') : "";
        $meetings = isAdminOrHasAllDataAccess() ? $this->workspace->meetings() : $this->user->meetings();
        if ($search) {
            $meetings = $meetings->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%');
            });
        }
        if ($user_id) {
            $user = User::find($user_id);
            $meetings = $user->meetings();
        }
        if ($client_id) {
            $client = Client::find($client_id);
            $meetings = $client->meetings();
        }
        if ($start_date_from && $start_date_to) {
            $start_date_from = $start_date_from . ' 00:00:00';
            $start_date_to = $start_date_to . ' 23:59:59';
            $meetings = $meetings->whereBetween('start_date_time', [$start_date_from, $start_date_to]);
        }
        if ($end_date_from && $end_date_to) {
            $end_date_from = $end_date_from . ' 00:00:00';
            $end_date_to = $end_date_to . ' 23:59:59';
            $meetings  = $meetings->whereBetween('end_date_time', [$end_date_from, $end_date_to]);
        }
        if ($status) {
            if ($status === 'ongoing') {
                $meetings = $meetings->where('start_date_time', '<=', Carbon::now(config('app.timezone')))
                    ->where('end_date_time', '>=', Carbon::now(config('app.timezone')));
            } elseif ($status === 'yet_to_start') {
                $meetings = $meetings->where('start_date_time', '>', Carbon::now(config('app.timezone')));
            } elseif ($status === 'ended') {
                $meetings = $meetings->where('end_date_time', '<', Carbon::now(config('app.timezone')));
            }
        }
        $totalmeetings = $meetings->count();

        $canCreate = checkPermission('create_meetings');
        $canEdit = checkPermission('edit_meetings');
        $canDelete = checkPermission('delete_meetings');

        $currentDateTime = Carbon::now(config('app.timezone'));
        $meetings = $meetings->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($meeting) use ($canEdit, $canDelete, $canCreate, $currentDateTime) {

                $status = (($currentDateTime < \Carbon\Carbon::parse($meeting->start_date_time, config('app.timezone'))) ? 'Will start in ' . $currentDateTime->diff(\Carbon\Carbon::parse($meeting->start_date_time, config('app.timezone')))->format('%a days %H hours %I minutes %S seconds') : (($currentDateTime > \Carbon\Carbon::parse($meeting->end_date_time, config('app.timezone')) ? 'Ended before ' . \Carbon\Carbon::parse($meeting->end_date_time, config('app.timezone'))->diff($currentDateTime)->format('%a days %H hours %I minutes %S seconds') : 'Ongoing')));

                $actions = '';

                if ($canEdit) {
                    $actions .= '<a href="javascript:void(0);" class="edit-meeting" data-id="' . $meeting->id . '" title="' . get_label('update', 'Update') . '">' .
                        '<i class="bx bx-edit mx-1"></i>' .
                        '</a>';
                }

                if ($canDelete) {
                    $actions .= '<button title="' . get_label('delete', 'Delete') . '" type="button" class="btn delete" data-id="' . $meeting->id . '" data-type="meetings" data-table="meetings_table">' .
                        '<i class="bx bx-trash text-danger mx-1"></i>' .
                        '</button>';
                }

                if ($canCreate) {
                    $actions .= '<a href="javascript:void(0);" class="duplicate" data-id="' . $meeting->id . '" data-title="' . $meeting->title . '" data-type="meetings" data-table="meetings_table" title="' . get_label('duplicate', 'Duplicate') . '">' .
                        '<i class="bx bx-copy text-warning mx-2"></i>' .
                        '</a>';
                }

                if ($status == 'Ongoing') {
                    $actions .= '<a href="/meetings/join/' . $meeting->id . '" target="_blank" title="Join">' .
                        '<i class="bx bx-arrow-to-right text-success mx-3"></i>' .
                        '</a>';
                }

                $actions = $actions ?: '-';

                $userHtml = '';
                if (!empty($meeting->users) && count($meeting->users) > 0) {
                    $userHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                    foreach ($meeting->users as $user) {
                        $userHtml .= "<li class='avatar avatar-sm pull-up'><a href='/users/profile/{$user->id}' target='_blank' title='{$user->first_name} {$user->last_name}'><img src='" . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                    }
                    if ($canEdit) {
                        $userHtml .= '<li title=' . get_label('update', 'Update') . '><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-meeting update-users-clients" data-id="' . $meeting->id . '"><span class="bx bx-edit"></span></a></li>';
                    }
                    $userHtml .= '</ul>';
                } else {
                    $userHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
                    if ($canEdit) {
                        $userHtml .= '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-meeting update-users-clients" data-id="' . $meeting->id . '">' .
                            '<span class="bx bx-edit"></span>' .
                            '</a>';
                    }
                }

                $clientHtml = '';
                if (!empty($meeting->clients) && count($meeting->clients) > 0) {
                    $clientHtml .= '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">';
                    foreach ($meeting->clients as $client) {
                        $clientHtml .= "<li class='avatar avatar-sm pull-up'><a href='/clients/profile/{$client->id}' target='_blank' title='{$client->first_name} {$client->last_name}'><img src='" . ($client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle' /></a></li>";
                    }
                    if ($canEdit) {
                        $clientHtml .= '<li title=' . get_label('update', 'Update') . '><a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-meeting update-users-clients" data-id="' . $meeting->id . '"><span class="bx bx-edit"></span></a></li>';
                    }
                    $clientHtml .= '</ul>';
                } else {
                    $clientHtml = '<span class="badge bg-primary">' . get_label('not_assigned', 'Not Assigned') . '</span>';
                    if ($canEdit) {
                        $clientHtml .= '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-meeting update-users-clients" data-id="' . $meeting->id . '">' .
                            '<span class="bx bx-edit"></span>' .
                            '</a>';
                    }
                }

                return [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'start_date_time' => format_date($meeting->start_date_time, true, null, null, false),
                    'end_date_time' => format_date($meeting->end_date_time, true, null, null, false),
                    'users' => $userHtml,
                    'clients' => $clientHtml,
                    'status' => $status,
                    'created_at' => format_date($meeting->created_at,  true),
                    'updated_at' => format_date($meeting->updated_at, true),
                    'actions' => $actions
                ];
            });
        return response()->json([
            "rows" => $meetings->items(),
            "total" => $totalmeetings,
        ]);
    }

    public function edit($id)
    {
        $meeting = Meeting::findOrFail($id);
        $users = $this->workspace->users;
        $clients = $this->workspace->clients;
        return view('meetings.update_meeting', compact('meeting', 'users', 'clients'));
    }

    public function get($id)
    {
        $meeting = Meeting::with('users', 'clients')->findOrFail($id);

        $meeting->start_date = \Carbon\Carbon::parse($meeting->start_date_time)->format('Y-m-d');
        $meeting->start_time = \Carbon\Carbon::parse($meeting->start_date_time)->format('H:i:s');
        $meeting->end_date = \Carbon\Carbon::parse($meeting->end_date_time)->format('Y-m-d');
        $meeting->end_time = \Carbon\Carbon::parse($meeting->end_date_time)->format('H:i:s');

        return response()->json(['error' => false, 'meeting' => $meeting]);
    }

    public function update(Request $request)
    {
        $formFields = $request->validate([
            'id' => 'required|exists:meetings,id',
            'title' => ['required'],
            'start_date' => ['required', 'before_or_equal:end_date'],
            'end_date' => ['required'],
            'start_time' => ['required'],
            'end_time' => ['required']
        ]);
        $id = $request->input('id');
        $start_date = $request->input('start_date');
        $start_time = $request->input('start_time');
        $end_date = $request->input('end_date');
        $end_time = $request->input('end_time');

        $formFields['start_date_time'] = format_date($start_date, false, app('php_date_format'), 'Y-m-d', false) . ' ' . $start_time;
        $formFields['end_date_time'] = format_date($end_date, false, app('php_date_format'), 'Y-m-d', false) . ' ' . $end_time;


        $userIds = $request->input('user_ids') ?? [];
        // dd($userIds);
        $clientIds = $request->input('client_ids') ?? [];
        $meeting = Meeting::findOrFail($id);
        // Set creator as a participant automatically

        if (User::where('id', $meeting->user_id)->exists() && !in_array($meeting->user_id, $userIds)) {
            array_splice($userIds, 0, 0, $meeting->user_id);
        } elseif (Client::where('id', $meeting->user_id)->exists() && !in_array($meeting->user_id, $clientIds)) {
            array_splice($clientIds, 0, 0, $meeting->user_id);
        }

        // Get current list of users and clients associated with the workspace
        $existingUserIds = $meeting->users->pluck('id')->toArray();
        $existingClientIds = $meeting->clients->pluck('id')->toArray();

        $meeting->update($formFields);
        $meeting->users()->sync($userIds);
        $meeting->clients()->sync($clientIds);

        // Exclude old users and clients from receiving notification
        $userIds = array_diff($userIds, $existingUserIds);
        $clientIds = array_diff($clientIds, $existingClientIds);

        // Prepare notification data
        $notification_data = [
            'type' => 'meeting',
            'type_id' => $id,
            'type_title' => $meeting->title,
            'action' => 'assigned',
            'title' => 'Added in a meeting',
            'message' => $this->user->first_name . ' ' . $this->user->last_name . ' added you in meeting: ' . $meeting->title . ', ID #' . $id . '.'
        ];

        // Combine user and client IDs for notification recipients
        $recipients = array_merge(
            array_map(function ($userId) {
                return 'u_' . $userId;
            }, $userIds),
            array_map(function ($clientId) {
                return 'c_' . $clientId;
            }, $clientIds)
        );
        // Process notifications
        processNotifications($notification_data, $recipients);

        Session::flash('message', 'Meeting updated successfully.');
        return response()->json(['error' => false, 'id' => $id, 'message' => 'Meeting updated successfully.']);
    }


    public function destroy($id)
    {

        $response = DeletionService::delete(Meeting::class, $id, 'Meeting');
        return $response;
    }

    public function destroy_multiple(Request $request)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'ids' => 'required|array', // Ensure 'ids' is present and an array
            'ids.*' => 'integer|exists:meetings,id' // Ensure each ID in 'ids' is an integer and exists in the table
        ]);

        $ids = $validatedData['ids'];
        $deletedMeetings = [];
        $deletedMeetingTitles = [];
        // Perform deletion using validated IDs
        foreach ($ids as $id) {
            $meeting = Meeting::find($id);
            if ($meeting) {
                $deletedMeetings[] = $id;
                $deletedMeetingTitles[] = $meeting->title;
                DeletionService::delete(Meeting::class, $id, 'Meeting');
            }
        }

        return response()->json(['error' => false, 'message' => 'Meetings(s) deleted successfully.', 'id' => $deletedMeetings, 'titles' => $deletedMeetingTitles]);
    }

    public function join($id)
    {

        $meeting = Meeting::findOrFail($id);
        $currentDateTime = Carbon::now(config('app.timezone'));
        if ($currentDateTime < $meeting->start_date_time) {
            return redirect('/meetings')->with('error', 'Meeting is yet to start');
        } elseif ($currentDateTime > $meeting->end_date_time) {
            return redirect('/meetings')->with('error', 'Meeting has been ended');
        } else {
            if ($meeting->users->contains($this->user->id) || isAdminOrHasAllDataAccess()) {
                $is_meeting_admin =  $this->user->id == $meeting['user_id'];
                $meeting_id = $meeting['id'];
                $room_name = $meeting['title'];
                $user_email =  $this->user->email;
                $user_display_name =  $this->user->first_name . ' ' .  $this->user->last_name;
                return view('meetings.join_meeting', compact('is_meeting_admin', 'meeting_id', 'room_name', 'user_email', 'user_display_name'));
            } else {
                return redirect('/meetings')->with('error', 'You are not authorized to join this meeting');
            }
        }
    }

    public function duplicate($id)
    {
        // Define the related tables for this meeting
        $relatedTables = ['users', 'clients']; // Include related tables as needed

        // Use the general duplicateRecord function
        $title = (request()->has('title') && !empty(trim(request()->title))) ? request()->title : '';
        $duplicateMeeting = duplicateRecord(Meeting::class, $id, $relatedTables, $title);
        if (!$duplicateMeeting) {
            return response()->json(['error' => true, 'message' => 'Meeting duplication failed.']);
        }
        if (request()->has('reload') && request()->input('reload') === 'true') {
            Session::flash('message', 'Meeting duplicated successfully.');
        }
        return response()->json(['error' => false, 'message' => 'Meeting duplicated successfully.', 'id' => $id]);
    }
}
