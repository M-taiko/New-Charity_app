@extends('layouts.modern')

@section('content')
<div class="container-fluid">

    <!-- Header -->
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h1 style="margin: 0; font-size: 1.8rem; font-weight: 700;">
                        <i class="fas fa-tasks"></i> {{ $task->title }}
                    </h1>
                    <div class="mt-1 d-flex gap-2 flex-wrap">
                        <span class="badge bg-{{ $task->statusColor() }}" style="font-size:.85rem;">
                            {{ $task->statusLabel() }}
                        </span>
                        <span class="badge bg-{{ $task->priorityColor() }}" style="font-size:.85rem;">
                            {{ $task->priorityLabel() }} الأولوية
                        </span>
                    </div>
                </div>
                <a href="{{ route('tasks.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row g-4">

        <!-- Task Details -->
        <div class="col-12 col-lg-4" data-aos="fade-up">
            <div class="card h-100">
                <div class="card-header">
                    <h6 style="margin:0;"><i class="fas fa-info-circle"></i> تفاصيل المهمة</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td class="text-muted fw-bold" style="width:40%;">المُنشئ</td>
                            <td>{{ $task->creator->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">المُعيَّن لـ</td>
                            <td>{{ $task->assignee->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">الحالة</td>
                            <td>
                                <span class="badge bg-{{ $task->statusColor() }}">{{ $task->statusLabel() }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">الأولوية</td>
                            <td>
                                <span class="badge bg-{{ $task->priorityColor() }}">{{ $task->priorityLabel() }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">تاريخ الإنشاء</td>
                            <td>{{ $task->created_at->format('d/m/Y') }}</td>
                        </tr>
                        @if($task->due_date)
                        <tr>
                            <td class="text-muted fw-bold">الاستحقاق</td>
                            <td class="{{ $task->due_date->isPast() && !$task->isCompleted() ? 'text-danger fw-bold' : '' }}">
                                {{ $task->due_date->format('d/m/Y') }}
                                @if($task->due_date->isPast() && !$task->isCompleted())
                                <small>(متأخر)</small>
                                @endif
                            </td>
                        </tr>
                        @endif
                        @if($task->completed_at)
                        <tr>
                            <td class="text-muted fw-bold">اكتمل في</td>
                            <td>{{ $task->completed_at->format('d/m/Y') }}</td>
                        </tr>
                        @endif
                    </table>

                    @if($task->description)
                    <hr>
                    <div class="text-muted fw-bold mb-1" style="font-size:.85rem;">الوصف</div>
                    <p style="white-space: pre-line; font-size:.9rem;">{{ $task->description }}</p>
                    @endif
                </div>

                <!-- Status Actions -->
                <div class="card-footer">
                    @php $user = auth()->user(); $isManager = $user->hasRole('مدير') || $user->hasRole('محاسب'); @endphp

                    @if($isManager)
                    {{-- Manager: full control --}}
                    @if($task->status !== 'completed' && $task->status !== 'cancelled')
                    <form action="{{ route('tasks.status', $task) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fas fa-check"></i> تحديد كمكتملة
                        </button>
                    </form>
                    <form action="{{ route('tasks.status', $task) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('إلغاء المهمة؟')">
                            <i class="fas fa-ban"></i> إلغاء
                        </button>
                    </form>
                    @elseif($task->status === 'completed' || $task->status === 'cancelled')
                    <form action="{{ route('tasks.status', $task) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="status" value="pending">
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="fas fa-undo"></i> إعادة فتح
                        </button>
                    </form>
                    @endif

                    <form action="{{ route('tasks.destroy', $task) }}" method="POST" class="d-inline float-end">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('حذف المهمة نهائياً؟')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>

                    @elseif($user->id === $task->assigned_to && $task->status === 'pending')
                    {{-- Assignee: can start task --}}
                    <form action="{{ route('tasks.status', $task) }}" method="POST">
                        @csrf
                        <input type="hidden" name="status" value="in_progress">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-play"></i> بدء العمل على المهمة
                        </button>
                    </form>
                    @endif

                    {{-- Delegate button: creator, assignee, or manager can re-assign --}}
                    @if(
                        ($user->id === $task->created_by || $user->id === $task->assigned_to || $user->hasRole('مدير') || $user->hasRole('محاسب'))
                        && !in_array($task->status, ['completed', 'cancelled'])
                    )
                    <hr>
                    <button type="button" class="btn btn-outline-warning btn-sm w-100" data-bs-toggle="collapse" data-bs-target="#delegateForm">
                        <i class="fas fa-share"></i> تفويض إلى شخص آخر
                    </button>
                    <div class="collapse mt-2" id="delegateForm">
                        <form action="{{ route('tasks.delegate', $task) }}" method="POST">
                            @csrf
                            <select name="assigned_to" class="form-select form-select-sm mb-2" required>
                                <option value="">اختر المستخدم...</option>
                                @foreach($users as $u)
                                    @if($u->id !== $task->assigned_to)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-warning btn-sm w-100">
                                <i class="fas fa-paper-plane"></i> تأكيد التفويض
                            </button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Comments / Discussion -->
        <div class="col-12 col-lg-8" data-aos="fade-up" data-aos-delay="100">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 style="margin:0;"><i class="fas fa-comments"></i> التعليقات والنقاش</h6>
                    <span class="badge bg-primary rounded-pill">{{ $task->comments->count() }}</span>
                </div>
                <div class="card-body" style="max-height: 55vh; overflow-y: auto;" id="commentsContainer">
                    @forelse($task->comments as $comment)
                    @php $isMe = $comment->user_id === auth()->id(); @endphp
                    <div class="d-flex gap-2 mb-3 {{ $isMe ? 'flex-row-reverse' : '' }}">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:38px;height:38px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-weight:700;font-size:.9rem;">
                            {{ mb_substr($comment->user->name, 0, 1) }}
                        </div>
                        <div style="max-width:75%;">
                            <div class="rounded-3 p-3 {{ $isMe ? 'text-white' : 'bg-light' }}"
                                 style="{{ $isMe ? 'background:linear-gradient(135deg,#667eea,#764ba2);' : '' }}">
                                <div class="fw-bold mb-1" style="font-size:.8rem;">
                                    {{ $comment->user->name }}
                                    @if($comment->user->hasRole('مدير'))
                                    <span class="badge bg-danger ms-1" style="font-size:.65rem;">مدير</span>
                                    @elseif($comment->user->hasRole('محاسب'))
                                    <span class="badge bg-info ms-1" style="font-size:.65rem;">محاسب</span>
                                    @endif
                                </div>
                                <div style="white-space:pre-line; font-size:.9rem;">{{ $comment->body }}</div>
                            </div>
                            <div class="text-muted mt-1" style="font-size:.72rem; {{ $isMe ? 'text-align:end;' : '' }}">
                                {{ $comment->created_at->diffForHumans() }}
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-comments fa-3x mb-2 d-block" style="opacity:.2;"></i>
                        لا توجد تعليقات بعد
                    </div>
                    @endforelse
                </div>

                @if(!$task->isCompleted() && $task->status !== 'cancelled')
                <div class="card-footer">
                    <form action="{{ route('tasks.comment', $task) }}" method="POST">
                        @csrf
                        <div class="d-flex gap-2 align-items-end">
                            <textarea name="body" class="form-control @error('body') is-invalid @enderror"
                                      rows="2" placeholder="اكتب تعليقك هنا..." required></textarea>
                            <button type="submit" class="btn btn-primary flex-shrink-0" style="height:fit-content;">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                        @error('body')<div class="text-danger mt-1" style="font-size:.8rem;">{{ $message }}</div>@enderror
                    </form>
                </div>
                @else
                <div class="card-footer text-center text-muted" style="font-size:.85rem;">
                    <i class="fas fa-lock"></i> المهمة مغلقة - لا يمكن إضافة تعليقات
                </div>
                @endif
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
    // Scroll comments to bottom on load
    const container = document.getElementById('commentsContainer');
    if (container) container.scrollTop = container.scrollHeight;
</script>
@endpush
@endsection
