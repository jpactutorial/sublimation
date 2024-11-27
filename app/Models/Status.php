<?php

namespace App\Models;

use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Status extends Model
{
    use HasFactory;


    protected $fillable = [
        'title',
        'color',
        'slug'
    ];

    public function projects($considerWorkspace = true)
    {
        $query = $this->hasMany(Project::class);

        if ($considerWorkspace) {
            $query->where('projects.workspace_id', session()->get('workspace_id'));
        }

        return $query;
    }

    public function tasks($considerWorkspace = true)
    {
        $query = $this->hasMany(Task::class);

        if ($considerWorkspace) {
            $query->where('tasks.workspace_id', session()->get('workspace_id'));
        }

        return $query;
    }

    public function user_tasks()
    {
        return $this->belongsToMany(Task::class, 'task_user')
            ->where('tasks.workspace_id', session()->get('workspace_id'));
    }
    
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_status');
    }
}
