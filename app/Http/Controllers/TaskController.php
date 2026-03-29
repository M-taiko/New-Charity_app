<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('مدير') || $user->hasRole('محاسب')) {
            $tasks = Task::with(['creator', 'assignee'])->latest()->get();
        } else {
            $tasks = Task::with(['creator', 'assignee'])
                ->where('assigned_to', $user->id)
                ->latest()
                ->get();
        }

        $grouped = [
            'pending'     => $tasks->where('status', 'pending'),
            'in_progress' => $tasks->where('status', 'in_progress'),
            'completed'   => $tasks->where('status', 'completed'),
            'cancelled'   => $tasks->where('status', 'cancelled'),
        ];

        return view('tasks.index', compact('grouped'));
    }

    public function create()
    {
        $this->authorizeManagerOrAccountant();

        $users = User::where('is_active', true)
            ->where('is_hidden', false)
            ->get();

        return view('tasks.create', compact('users'));
    }

    public function store(Request $request)
    {
        $this->authorizeManagerOrAccountant();

        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'assigned_to' => 'required|exists:users,id',
            'priority'    => 'required|in:low,medium,high',
            'due_date'    => 'nullable|date|after_or_equal:today',
        ]);

        $task = Task::create([
            'title'       => $request->title,
            'description' => $request->description,
            'created_by'  => auth()->id(),
            'assigned_to' => $request->assigned_to,
            'priority'    => $request->priority,
            'due_date'    => $request->due_date,
            'status'      => 'pending',
        ]);

        // Notify the assignee
        NotificationService::notifyUser(
            $task->assigned_to,
            'مهمة جديدة',
            'تم تعيينك لمهمة جديدة: ' . $task->title,
            'task',
            $task->id,
            'task'
        );

        return redirect()->route('tasks.show', $task)->with('success', 'تم إنشاء المهمة بنجاح');
    }

    public function show(Task $task)
    {
        $user = auth()->user();

        // Only assignee, creator, managers, and accountants can view
        if (
            $task->assigned_to !== $user->id &&
            $task->created_by !== $user->id &&
            !$user->hasRole('مدير') &&
            !$user->hasRole('محاسب')
        ) {
            abort(403);
        }

        $task->load(['creator', 'assignee', 'comments.user']);

        return view('tasks.show', compact('task'));
    }

    public function addComment(Request $request, Task $task)
    {
        $user = auth()->user();

        if (
            $task->assigned_to !== $user->id &&
            $task->created_by !== $user->id &&
            !$user->hasRole('مدير') &&
            !$user->hasRole('محاسب')
        ) {
            abort(403);
        }

        $request->validate(['body' => 'required|string|max:2000']);

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body'    => $request->body,
        ]);

        // If assignee commented → notify creator (manager/accountant)
        if ($user->id === $task->assigned_to) {
            NotificationService::notifyUser(
                $task->created_by,
                'رد على مهمة',
                $user->name . ' أضاف تعليقاً على المهمة: ' . $task->title,
                'task',
                $task->id,
                'task'
            );
        } else {
            // If manager/accountant commented → notify assignee
            NotificationService::notifyUser(
                $task->assigned_to,
                'تعليق جديد على مهمتك',
                $task->creator->name . ' علّق على المهمة: ' . $task->title,
                'task',
                $task->id,
                'task'
            );
        }

        return back()->with('success', 'تم إضافة التعليق');
    }

    public function updateStatus(Request $request, Task $task)
    {
        $user = auth()->user();
        $isManagerOrAccountant = $user->hasRole('مدير') || $user->hasRole('محاسب');

        $request->validate(['status' => 'required|in:pending,in_progress,completed,cancelled']);

        $newStatus = $request->status;

        // Assignee can only change to in_progress
        if (!$isManagerOrAccountant) {
            if ($newStatus !== 'in_progress') {
                abort(403, 'يمكنك فقط تحديث الحالة إلى "جاري التنفيذ"');
            }
            if ($task->assigned_to !== $user->id) {
                abort(403);
            }
        }

        $task->status = $newStatus;
        if ($newStatus === 'completed') {
            $task->completed_at = now();
        } else {
            $task->completed_at = null;
        }
        $task->save();

        // Notify assignee if manager marked as completed/cancelled
        if ($isManagerOrAccountant && in_array($newStatus, ['completed', 'cancelled'])) {
            $label = $newStatus === 'completed' ? 'مكتملة' : 'ملغاة';
            NotificationService::notifyUser(
                $task->assigned_to,
                'تحديث حالة المهمة',
                'تم تحديد المهمة "' . $task->title . '" كـ ' . $label,
                'task',
                $task->id,
                'task'
            );
        }

        // Notify creator if assignee changed to in_progress
        if (!$isManagerOrAccountant && $newStatus === 'in_progress') {
            NotificationService::notifyUser(
                $task->created_by,
                'تحديث حالة المهمة',
                $user->name . ' بدأ العمل على المهمة: ' . $task->title,
                'task',
                $task->id,
                'task'
            );
        }

        return back()->with('success', 'تم تحديث حالة المهمة');
    }

    public function destroy(Task $task)
    {
        $this->authorizeManagerOrAccountant();
        $task->delete();
        return redirect()->route('tasks.index')->with('success', 'تم حذف المهمة');
    }

    private function authorizeManagerOrAccountant(): void
    {
        $user = auth()->user();
        if (!$user->hasRole('مدير') && !$user->hasRole('محاسب')) {
            abort(403, 'غير مصرح');
        }
    }
}
