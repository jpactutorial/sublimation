<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Task;
use App\Models\User;
use App\Models\Client;
use App\Models\Project;
use App\Models\Workspace;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class HomeController extends Controller
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
    public function index(Request $request)
    {
        $projects = isAdminOrHasAllDataAccess() ? $this->workspace->projects ?? [] : $this->user->projects ?? [];
        $tasks = isAdminOrHasAllDataAccess() ? $this->workspace->tasks ?? [] : $this->user->tasks() ?? [];
        $tasks = $tasks ? $tasks->count() : 0;
        $users = $this->workspace->users ?? [];
        $clients = $this->workspace->clients ?? [];
        $todos = $this->user->todos()
            ->orderBy('is_completed', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(5);
        $total_todos = $this->user->todos;
        $meetings = isAdminOrHasAllDataAccess() ? $this->workspace->meetings ?? [] : $this->user->meetings ?? [];
        return view('dashboard', ['users' => $users, 'clients' => $clients, 'projects' => $projects, 'tasks' => $tasks, 'todos' => $todos, 'total_todos' => $total_todos, 'meetings' => $meetings, 'auth_user' => $this->user]);
    }

    public function upcoming_birthdays()
    {
        $search = request('search');
        $sort = request('sort', 'dob');
        $order = request('order', 'ASC');
        $upcoming_days = (int)request('upcoming_days', 30); // Cast to integer, default to 30 if not provided
        $user_id = request('user_id');

        $users = $this->workspace->users();

        // Calculate the current date
        $currentDate = today();
        $currentYear = $currentDate->format('Y');

        // Calculate the range for upcoming birthdays (e.g., 365 days from today)
        $upcomingDate = $currentDate->copy()->addDays($upcoming_days);

        $currentDateString = $currentDate->format('Y-m-d');
        $upcomingDateString = $upcomingDate->format('Y-m-d');

        $users = $users->whereRaw("DATE_ADD(DATE_FORMAT(dob, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(dob) + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(dob, '%m-%d'), 1, 0) YEAR) BETWEEN ? AND ? AND DATEDIFF(DATE_ADD(DATE_FORMAT(dob, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(dob) + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(dob, '%m-%d'), 1, 0) YEAR), CURRENT_DATE()) <= ?", [$currentDateString, $upcomingDateString, $upcoming_days])
            ->orderByRaw("DATEDIFF(DATE_ADD(DATE_FORMAT(dob, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(dob) + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(dob, '%m-%d'), 1, 0) YEAR), CURRENT_DATE()) " . $order);
        // Search by full name (first name + last name)
        if ($search) {
            $users->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('dob', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
        }

        if ($user_id) {
            $users->where('users.id', $user_id);
        }

        $total = $users->count();

        // $users = $users->orderBy($sort, $order)
        $users = $users->paginate(request("limit"))
            ->through(function ($user) use ($currentDate, $currentYear) {
                // Convert the 'dob' field to a DateTime object
                $birthdayDate = \Carbon\Carbon::createFromFormat('Y-m-d', $user->dob);
                $birthdayDateYear = $birthdayDate->year;
                $yearDifference = $currentYear - $birthdayDateYear;
                $ordinalSuffix = getOrdinalSuffix($yearDifference);
                // Set the year to the current year
                $birthdayDate->year = $currentDate->year;

                if ($birthdayDate->lt($currentDate)) {
                    // If the birthday has already passed this year, calculate for next year
                    $birthdayDate->year = $currentDate->year + 1;
                }

                // Calculate days left until the user's birthday
                $daysLeft = $currentDate->diffInDays($birthdayDate);

                $emoji = '';
                $label = '';

                if ($daysLeft === 0) {
                    $emoji = ' 🥳';
                    $label = '<span class="badge bg-primary mt-2">' . $yearDifference . '<sup>' . $ordinalSuffix . '</sup> '.get_label('birthday', 'Birthday').' '.get_label('today', 'Today').'</span>';
                } elseif ($daysLeft === 1) {
                    $label = '<span class="badge bg-primary mt-2">' . $yearDifference . '<sup>' . $ordinalSuffix . '</sup> '.get_label('birthday', 'Birthday').' '.get_label('tomorrow', 'Tomorrow').'</span>';
                } elseif ($daysLeft === 2) {
                    $label = '<span class="badge bg-primary mt-2">' . $yearDifference . '<sup>' . $ordinalSuffix . '</sup> '.get_label('birthday', 'Birthday').' '.get_label('day_after_tomorrow', 'Day After Tomorrow').'</span>';
                }
                $dayOfWeek = $birthdayDate->format('D');           
                return [
                    'id' => $user->id,
                    'member' => $user->first_name . ' ' . $user->last_name . $emoji . "<ul class='list-unstyled users-list m-0 avatar-group d-flex align-items-center'><a href='/users/profile/" . $user->id . "' target='_blank'><li class='avatar avatar-sm pull-up'  title='" . $user->first_name . " " . $user->last_name . "'><img src='" . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle'>",
                    'age' => $currentDate->diffInYears($birthdayDate),
                    'days_left' => $daysLeft,
                    'dob' => $dayOfWeek.', '.format_date($birthdayDate).'<br>'.$label,
                ];
            });

        return response()->json([
            "rows" => $users->items(),
            "total" => $total,
        ]);
    }



    public function upcoming_work_anniversaries()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "doj";
        $order = (request('order')) ? request('order') : "ASC";
        $upcoming_days = (request('upcoming_days')) ? request('upcoming_days') : 30;
        $user_id = (request('user_id')) ? request('user_id') : "";
        $users = $this->workspace->users();

        $currentDate = today();
        $currentYear = $currentDate->format('Y');

        // Calculate the range for upcoming birthdays (e.g., 365 days from today)
        $upcomingDate = $currentDate->copy()->addDays($upcoming_days);

        $currentDateString = $currentDate->format('Y-m-d');
        $upcomingDateString = $upcomingDate->format('Y-m-d');

        $users = $users->whereRaw("DATE_ADD(DATE_FORMAT(doj, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(doj) + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(doj, '%m-%d'), 1, 0) YEAR) BETWEEN ? AND ? AND DATEDIFF(DATE_ADD(DATE_FORMAT(doj, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(doj) + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(doj, '%m-%d'), 1, 0) YEAR), CURRENT_DATE()) <= ?", [$currentDateString, $upcomingDateString, $upcoming_days])
            ->orderByRaw("DATEDIFF(DATE_ADD(DATE_FORMAT(doj, '%Y-%m-%d'), INTERVAL YEAR(CURRENT_DATE()) - YEAR(doj) + IF(DATE_FORMAT(CURRENT_DATE(), '%m-%d') > DATE_FORMAT(doj, '%m-%d'), 1, 0) YEAR), CURRENT_DATE()) " . $order);

        // Search by full name (first name + last name)
        if (!empty($search)) {
            $users->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhere('doj', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
        }
        if (!empty($user_id)) {
            $users->where('users.id', $user_id);
        }
        $total = $users->count();

        // $users = $users->orderBy($sort, $order)
        $users = $users->paginate(request("limit"))
            ->through(function ($user) use ($currentDate, $currentYear) {
                // Convert the 'dob' field to a DateTime object
                $doj = \Carbon\Carbon::createFromFormat('Y-m-d', $user->doj);
                $dojYear = $doj->year;
                $yearDifference = $currentYear - $dojYear;
                $ordinalSuffix = getOrdinalSuffix($yearDifference);

                // Set the year to the current year
                $doj->year = $currentDate->year;

                if ($doj->lt($currentDate)) {
                    // If the birthday has already passed this year, calculate for next year
                    $doj->year = $currentDate->year + 1;
                }

                // Calculate days left until the user's birthday
                $daysLeft = $currentDate->diffInDays($doj);
                $label = '';
                $emoji = '';
                if ($daysLeft === 0) {
                    $emoji = ' 🥳';
                    $label = '<span class="badge bg-primary mt-2">' . $yearDifference . '<sup>' . $ordinalSuffix . '</sup> '.get_label('work_anniversary', 'Work Anniversary').' '.get_label('today', 'Today').'</span>';
                } elseif ($daysLeft === 1) {
                    $label = '<span class="badge bg-primary mt-2">' . $yearDifference . '<sup>' . $ordinalSuffix . '</sup> '.get_label('work_anniversary', 'Work Anniversary').' '.get_label('tomorrow', 'Tomorrow').'</span>';
                } elseif ($daysLeft === 2) {
                    $label = '<span class="badge bg-primary mt-2">' . $yearDifference . '<sup>' . $ordinalSuffix . '</sup> '.get_label('work_anniversary', 'Work Anniversary').' '.get_label('day_after_tomorrow', 'Day After Tomorrow').'</span>';
                }

                $dayOfWeek = $doj->format('D');
                return [
                    'id' => $user->id,
                    'member' => $user->first_name . ' ' . $user->last_name . $emoji . "<ul class='list-unstyled users-list m-0 avatar-group d-flex align-items-center'><a href='/users/profile/" . $user->id . "' target='_blank'><li class='avatar avatar-sm pull-up'  title='" . $user->first_name . " " . $user->last_name . "'>
                    <img src='" . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle'>",
                    'wa_date' => $dayOfWeek.', '.format_date($doj).'<br>'.$label,
                    'days_left' => $daysLeft,
                ];
            });

        return response()->json([
            "rows" => $users->items(),
            "total" => $total,
        ]);
    }



    public function members_on_leave()
    {
        $search = request('search');
        $sort = (request('sort')) ? request('sort') : "from_date";
        $order = (request('order')) ? request('order') : "ASC";
        $upcoming_days = (request('upcoming_days')) ? request('upcoming_days') : 30;
        $user_id = (request('user_id')) ? request('user_id') : "";

        // Calculate the current date
        $currentDate = today();

        // Calculate the range for upcoming work anniversaries (e.g., 30 days from today)
        $upcomingDate = $currentDate->copy()->addDays($upcoming_days);
        // Query members on leave based on 'start_date' in the 'leave_requests' table
        $leaveUsers = DB::table('leave_requests')
            ->selectRaw('*, leave_requests.user_id as UserId')
            ->leftJoin('users', 'leave_requests.user_id', '=', 'users.id')
            ->leftJoin('leave_request_visibility', 'leave_requests.id', '=', 'leave_request_visibility.leave_request_id')
            ->where(function ($leaveUsers) use ($currentDate, $upcomingDate) {
                $leaveUsers->where('from_date', '<=', $upcomingDate)
                    ->where('to_date', '>=', $currentDate);
            })
            ->where('leave_requests.status', '=', 'approved')
            ->where('workspace_id', '=', $this->workspace->id);

        if (!is_admin_or_leave_editor()) {
            $leaveUsers->where(function ($query) {
                $query->where('leave_requests.user_id', '=', $this->user->id)
                    ->orWhere('leave_request_visibility.user_id', '=', $this->user->id);
            });
        }

        // Search by full name (first name + last name)
        if (!empty($search)) {
            $leaveUsers->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%$search%")
                    ->orWhere('last_name', 'LIKE', "%$search%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$search%"]);
            });
        }
        if (!empty($user_id)) {
            $leaveUsers->where('leave_requests.user_id', $user_id);
        }
        $total = $leaveUsers->count();
        $timezone = config('app.timezone');
        $leaveUsers = $leaveUsers->orderBy($sort, $order)
            ->paginate(request("limit"))
            ->through(function ($user) use ($currentDate, $timezone) {

                $fromDate = \Carbon\Carbon::createFromFormat('Y-m-d', $user->from_date);

                // Set the year to the current year
                $fromDate->year = $currentDate->year;

                // Calculate days left until the user's return from leave
                $daysLeft = $currentDate->diffInDays($fromDate);
                if ($fromDate->lt($currentDate)) {
                    $daysLeft = 0;
                }
                $currentDateTime = \Carbon\Carbon::now()->tz($timezone);
                $currentTime = $currentDateTime->format('H:i:s');

                $label = '';
                if ($daysLeft === 0 && $user->from_time && $user->to_time && $user->from_time <= $currentTime && $user->to_time >= $currentTime) {
                    $label = ' <span class="badge bg-info">' . get_label('on_partial_leave', 'On Partial Leave') . '</span>';
                } elseif (($daysLeft === 0 && (!$user->from_time && !$user->to_time)) ||
                    ($daysLeft === 0 && $user->from_time <= $currentTime && $user->to_time >= $currentTime)
                ) {
                    $label = ' <span class="badge bg-success">' . get_label('on_leave', 'On leave') . '</span>';
                } elseif ($daysLeft === 1) {
                    $langLabel = $user->from_time && $user->to_time ?  get_label('on_partial_leave_tomorrow', 'On partial leave from tomorrow') : get_label('on_leave_tomorrow', 'On leave from tomorrow');
                    $label = ' <span class="badge bg-primary">' . $langLabel . '</span>';
                } elseif ($daysLeft === 2) {
                    $langLabel = $user->from_time && $user->to_time ?  get_label('on_partial_leave_day_after_tomorow', 'On partial leave from day after tomorrow') : get_label('on_leave_day_after_tomorow', 'On leave from day after tomorrow');
                    $label = ' <span class="badge bg-warning">' . $langLabel . '</span>';
                }

                $fromDate = Carbon::parse($user->from_date);
                $toDate = Carbon::parse($user->to_date);
                if ($user->from_time && $user->to_time) {
                    $duration = 0;
                    // Loop through each day
                    while ($fromDate->lessThanOrEqualTo($toDate)) {
                        // Create Carbon instances for the start and end times of the leave request for the current day
                        $fromDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $user->from_time);
                        $toDateTime = Carbon::parse($fromDate->toDateString() . ' ' . $user->to_time);

                        // Calculate the duration for the current day and add it to the total duration
                        $duration += $fromDateTime->diffInMinutes($toDateTime) / 60; // Duration in hours

                        // Move to the next day
                        $fromDate->addDay();
                    }
                } else {
                    // Calculate the inclusive duration in days
                    $duration = $fromDate->diffInDays($toDate) + 1;
                }
                $fromDateDayOfWeek = $fromDate->format('D');
                $toDateDayOfWeek = $toDate->format('D');
                return [
                    'id' => $user->UserId,
                    'member' => $user->first_name . ' ' . $user->last_name . ' ' . $label . "<ul class='list-unstyled users-list m-0 avatar-group d-flex align-items-center'><a href='/users/profile/" . $user->UserId . "' target='_blank'><li class='avatar avatar-sm pull-up'  title='" . $user->first_name . " " . $user->last_name . "'>
            <img src='" . ($user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')) . "' alt='Avatar' class='rounded-circle'>",
                    'from_date' =>  $fromDateDayOfWeek.', '.($user->from_time ? format_date($user->from_date . ' ' . $user->from_time, true, null, null, false) : format_date($user->from_date)),
                    'to_date' =>  $toDateDayOfWeek.', '.($user->to_time ? format_date($user->to_date . ' ' . $user->to_time, true, null, null, false) : format_date($user->to_date)),
                    'type' => $user->from_time && $user->to_time ? '<span class="badge bg-info">' . get_label('partial', 'Partial') . '</span>' : '<span class="badge bg-primary">' . get_label('full', 'Full') . '</span>',
                    'duration' => $user->from_time && $user->to_time ? $duration . ' hour' . ($duration > 1 ? 's' : '') : $duration . ' day' . ($duration > 1 ? 's' : ''),
                    'days_left' => $daysLeft,
                ];
            });

        return response()->json([
            "rows" => $leaveUsers->items(),
            "total" => $total,
        ]);
    }

    public function upcoming_birthdays_calendar()
    {
        $users = $this->workspace->users()->get();
        $currentDate = today();

        $events = [];

        foreach ($users as $user) {
            if (!empty($user->dob)) {
                // Format the start date in the required format for FullCalendar
                $birthdayDate = \Carbon\Carbon::createFromFormat('Y-m-d', $user->dob);

                // Set the year to the current year
                $birthdayDate->year = $currentDate->year;

                if ($birthdayDate->lt($currentDate)) {
                    // If the birthday has already passed this year, calculate for next year
                    $birthdayDate->year = $currentDate->year + 1;
                }
                $startDate = $birthdayDate->format('Y-m-d');

                // Prepare the event data
                $event = [
                    'userId' => $user->id,
                    'title' => $user->first_name . ' ' . $user->last_name . '\'s Birthday',
                    'start' => $startDate,
                    'backgroundColor' => '#007bff',
                    'borderColor' => '#007bff',
                    'textColor' => '#ffffff',
                ];

                // Add the event to the events array
                $events[] = $event;
            }
        }
        return response()->json($events);
    }

    public function upcoming_work_anniversaries_calendar()
    {
        $users = $this->workspace->users()->get();

        // Calculate the current date
        $currentDate = today();

        $events = [];

        foreach ($users as $user) {
            if (!empty($user->doj)) {
                // Format the start date in the required format for FullCalendar
                $WADate = \Carbon\Carbon::createFromFormat('Y-m-d', $user->doj);

                // Set the year to the current year
                $WADate->year = $currentDate->year;

                if ($WADate->lt($currentDate)) {
                    // If the birthday has already passed this year, calculate for next year
                    $WADate->year = $currentDate->year + 1;
                }
                $startDate = $WADate->format('Y-m-d');

                // Prepare the event data
                $event = [
                    'userId' => $user->id,
                    'title' => $user->first_name . ' ' . $user->last_name . '\'s Work Anniversary',
                    'start' => $startDate,
                    'backgroundColor' => '#007bff',
                    'borderColor' => '#007bff',
                    'textColor' => '#ffffff',
                ];

                // Add the event to the events array
                $events[] = $event;
            }
        }

        return response()->json($events);
    }

    public function members_on_leave_calendar()
    {
        $currentDate = today();
        $leaveRequests = DB::table('leave_requests')
            ->selectRaw('*, leave_requests.user_id as UserId')
            ->leftJoin('users', 'leave_requests.user_id', '=', 'users.id')
            ->leftJoin('leave_request_visibility', 'leave_requests.id', '=', 'leave_request_visibility.leave_request_id')
            ->where('to_date', '>=', $currentDate)
            ->where('leave_requests.status', '=', 'approved')
            ->where('workspace_id', '=', $this->workspace->id);


        // Add condition to restrict results based on user roles
        if (!is_admin_or_leave_editor()) {
            $leaveRequests->where(function ($query) {
                $query->where('leave_requests.user_id', '=', $this->user->id)
                    ->orWhere('leave_request_visibility.user_id', '=', $this->user->id);
            });
        }

        $time_format = get_php_date_time_format(true);
        $time_format = str_replace(':s', '', $time_format);
        // Get leave requests and format for calendar
        $events = $leaveRequests->get()->map(function ($leave) use ($time_format) {
            $title = $leave->first_name . ' ' . $leave->last_name;
            if ($leave->from_time && $leave->to_time) {
                // If both start and end times are present, format them according to the desired format
                $formattedStartTime = \Carbon\Carbon::createFromFormat('H:i:s', $leave->from_time)->format($time_format);
                $formattedEndTime = \Carbon\Carbon::createFromFormat('H:i:s', $leave->to_time)->format($time_format);
                $title .= ' - ' . $formattedStartTime . ' to ' . $formattedEndTime;
                $backgroundColor = '#02C5EE';
            } else {
                $backgroundColor = '#007bff';
            }
            return [
                'userId' => $leave->UserId,
                'title' => $title,
                'start' => $leave->from_date,
                'end' => $leave->to_date,
                'startTime' => $leave->from_time,
                'endTime' => $leave->to_time,
                'backgroundColor' => $backgroundColor,
                'borderColor' => $backgroundColor,
                'textColor' => '#ffffff'
            ];
        });

        return response()->json($events);
    }
}
