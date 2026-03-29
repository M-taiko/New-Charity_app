@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-tasks"></i> إدارة المهام
                    </h1>
                </div>
                @if(auth()->user()->hasRole('مدير') || auth()->user()->hasRole('محاسب'))
                <a href="{{ route('tasks.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> مهمة جديدة
                </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3" data-aos="fade-up">
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-label">قيد الانتظار</div>
                <div class="stat-number" style="color: var(--warning);">{{ $grouped['pending']->count() }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card primary">
                <div class="stat-icon"><i class="fas fa-spinner"></i></div>
                <div class="stat-label">جاري التنفيذ</div>
                <div class="stat-number" style="color: var(--primary);">{{ $grouped['in_progress']->count() }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-label">مكتملة</div>
                <div class="stat-number" style="color: var(--success);">{{ $grouped['completed']->count() }}</div>
            </div>
        </div>
        <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card danger">
                <div class="stat-icon"><i class="fas fa-ban"></i></div>
                <div class="stat-label">ملغاة</div>
                <div class="stat-number" style="color: var(--danger);">{{ $grouped['cancelled']->count() }}</div>
            </div>
        </div>
    </div>

    <!-- Kanban Board -->
    <div class="row g-3" data-aos="fade-up" data-aos-delay="400">

        @php
        $columns = [
            'pending'     => ['label' => 'قيد الانتظار',   'color' => '#f59e0b', 'icon' => 'fa-clock'],
            'in_progress' => ['label' => 'جاري التنفيذ',   'color' => '#6366f1', 'icon' => 'fa-spinner fa-spin'],
            'completed'   => ['label' => 'مكتملة',         'color' => '#10b981', 'icon' => 'fa-check-circle'],
            'cancelled'   => ['label' => 'ملغاة',           'color' => '#6b7280', 'icon' => 'fa-ban'],
        ];
        @endphp

        @foreach($columns as $key => $col)
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100" style="border-top: 3px solid {{ $col['color'] }};">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span style="font-weight: 700; color: {{ $col['color'] }};">
                        <i class="fas {{ $col['icon'] }}"></i> {{ $col['label'] }}
                    </span>
                    <span class="badge rounded-pill" style="background: {{ $col['color'] }};">
                        {{ $grouped[$key]->count() }}
                    </span>
                </div>
                <div class="card-body p-2" style="max-height: 70vh; overflow-y: auto;">
                    @forelse($grouped[$key] as $task)
                    <a href="{{ route('tasks.show', $task) }}" class="text-decoration-none">
                        <div class="card mb-2 task-card" style="border: none; box-shadow: 0 1px 4px rgba(0,0,0,0.08); transition: transform .15s;">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="fw-bold text-dark" style="font-size: .9rem;">{{ $task->title }}</span>
                                    <span class="badge bg-{{ $task->priorityColor() }} ms-1" style="font-size: .7rem;">
                                        {{ $task->priorityLabel() }}
                                    </span>
                                </div>
                                @if($task->description)
                                <p class="text-muted mb-2" style="font-size: .8rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    {{ $task->description }}
                                </p>
                                @endif
                                <div class="d-flex justify-content-between align-items-center mt-2" style="font-size:.75rem;">
                                    <span class="text-muted">
                                        <i class="fas fa-user"></i> {{ $task->assignee->name }}
                                    </span>
                                    @if($task->due_date)
                                    <span class="{{ $task->due_date->isPast() && !$task->isCompleted() ? 'text-danger fw-bold' : 'text-muted' }}">
                                        <i class="fas fa-calendar-alt"></i>
                                        {{ $task->due_date->format('d/m/Y') }}
                                    </span>
                                    @endif
                                </div>
                                @if($task->comments->count())
                                <div class="mt-1" style="font-size:.75rem; color: #888;">
                                    <i class="fas fa-comments"></i> {{ $task->comments->count() }} تعليق
                                </div>
                                @endif
                            </div>
                        </div>
                    </a>
                    @empty
                    <div class="text-center text-muted py-4" style="font-size:.85rem;">
                        <i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.3;"></i>
                        لا توجد مهام
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

<style>
.task-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12) !important;
}
</style>
@endsection
