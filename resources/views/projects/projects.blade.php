@extends('layout')

@section('title')
<?= get_label('projects', 'Projects') ?> - <?= get_label('list_view', 'List view') ?>
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
                    <li class="breadcrumb-item">
                        <a href="{{url('/'.getUserPreferences('projects', 'default_view'))}}"><?= get_label('projects', 'Projects') ?></a>
                    </li>
                    @if ($is_favorites==1)
                    <li class="breadcrumb-item active"><?= get_label('favorite', 'Favorite') ?></li>
                    @else
                    <li class="breadcrumb-item active"><?= get_label('list', 'List') ?></li>
                    @endif
                </ol>
            </nav>
        </div>
        <div>
            @php
            $projectDefaultView = getUserPreferences('projects', 'default_view');
            @endphp
            @if ($projectDefaultView === 'projects/list')
            <span class="badge bg-primary"><?= get_label('default_view', 'Default View') ?></span>
            @else
            <a href="javascript:void(0);"><span class="badge bg-secondary" id="set-default-view" data-type="projects" data-view="list"><?= get_label('set_as_default_view', 'Set as Default View') ?></span></a>
            @endif
        </div>
        <div>
            @php
            $url = $is_favorites == 1 ? url('projects/favorite') : url('projects');
            $additionalParams = request()->has('status') ? '/projects?status=' . request()->status : '';
            $finalUrl = url($additionalParams ?: $url);
            @endphp
            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#create_project_modal"><button type="button" class="btn btn-sm btn-primary action_create_projects" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('create_project', 'Create project') ?>"><i class='bx bx-plus'></i></button></a>
            <a href="{{$finalUrl}}"><button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="<?= get_label('grid_view', 'Grid view') ?>"><i class='bx bxs-grid-alt'></i></button></a>
        </div>
    </div>
    <x-projects-card :projects="$projects" :users="$users" :clients="$clients" :favorites="$is_favorites" />
</div>
@endsection