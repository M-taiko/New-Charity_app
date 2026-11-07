<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Broadcast;
use App\Models\ChatMessage;
use App\Models\Notification;
use App\Support\Api\NotificationLink;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\Api\ApiList;
use App\Support\Api\ListExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SystemController extends Controller
{
    // ── Tasks ────────────────────────────────────────────────

    public function tasks(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            Task::with(['creator', 'assignee']),
            $request,
            sortable: $this->tasksSortable(),
            filterable: $this->tasksFilterable(),
            search: fn ($q, $term) => $this->tasksSearch($q, $term),
            filters: $this->tasksFilters(),
        );
        $paginator->getCollection()->transform(fn ($t) => $this->taskRow($t));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'pending' => Task::where('status', 'pending')->count(),
            'in_progress' => Task::where('status', 'in_progress')->count(),
            'completed' => Task::where('status', 'completed')->count(),
            'cancelled' => Task::where('status', 'cancelled')->count(),
        ]));
    }

    /** @return array<string, string> */
    private function tasksSortable(): array
    {
        return ['date' => 'created_at', 'due_date' => 'due_date', 'title' => 'title', 'assigned_to' => 'assigned_to', 'status' => 'status', 'priority' => 'priority'];
    }

    /** @return array<string, array{type:string, column?:string, options?:callable}> */
    private function tasksFilterable(): array
    {
        return [
            'status' => ['type' => 'select', 'column' => 'status', 'options' => fn () => [
                ['value' => 'pending', 'label' => 'معلنة'],
                ['value' => 'in_progress', 'label' => 'جارية'],
                ['value' => 'completed', 'label' => 'مكتملة'],
                ['value' => 'cancelled', 'label' => 'ملغاة'],
            ]],
            'priority' => ['type' => 'select', 'column' => 'priority'],
            'assigned_to' => ['type' => 'lookup', 'column' => 'assigned_to'],
        ];
    }

    /** @return array<string, callable> */
    private function tasksFilters(): array
    {
        return [
            'status' => fn ($q, $v) => $q->where('status', $v),
            'assigned_to' => fn ($q, $v) => $q->where('assigned_to', (int) $v),
        ];
    }

    private function tasksSearch($q, string $term): void
    {
        $q->where(fn ($w) => $w
            ->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
    }

    /** the tasks list row — shared by the list and its export (T7-06) */
    private function taskRow(Task $t): array
    {
        return [
            'id' => $t->id,
            'title' => $t->title,
            'created_by' => $t->creator?->name,
            'assigned_to' => $t->assignee?->name,
            'assigned_to_id' => $t->assigned_to,
            'status' => $t->status,
            'priority' => $t->priority,
            'due_date' => $t->due_date?->toIso8601ZuluString(),
            'created_at' => $t->created_at?->toIso8601ZuluString(),
        ];
    }

    /**
     * GET /api/v1/tasks/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as tasks().
     */
    public function tasksExport(Request $request): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            Task::with(['creator', 'assignee']),
            $this->tasksSortable(),
            $this->tasksFilterable(),
            $this->tasksFilters(),
            search: fn ($q, $term) => $this->tasksSearch($q, $term),
            row: fn ($t) => $this->taskRow($t),
        )->map(fn (array $t) => [
            'id' => $t['id'],
            'title' => $t['title'],
            'status' => $this->taskStatusLabel((string) $t['status']),
            'priority' => $this->taskPriorityLabel((string) $t['priority']),
            'created_by' => $t['created_by'],
            'assigned_to' => $t['assigned_to'],
            'created_at' => $this->exportDate($t['created_at']),
        ]);

        return ListExport::stream($request, $rows, [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'title', 'label' => 'العنوان'],
            ['key' => 'status', 'label' => 'الحالة'],
            ['key' => 'priority', 'label' => 'الأولوية'],
            ['key' => 'created_by', 'label' => 'أنشأها'],
            ['key' => 'assigned_to', 'label' => 'المسند إليه'],
            ['key' => 'created_at', 'label' => 'التاريخ'],
        ], 'tasks');
    }

    /** Arabic label for a task status (export rows) — matches enums.task in ar.json */
    private function taskStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'قيد الانتظار',
            'in_progress' => 'جاري التنفيذ',
            'completed' => 'مكتملة',
            'cancelled' => 'ملغاة',
            default => $status,
        };
    }

    /** Arabic label for a task priority (export rows) — matches enums.priority in ar.json */
    private function taskPriorityLabel(string $priority): string
    {
        return match ($priority) {
            'low' => 'منخفضة',
            'medium' => 'متوسطة',
            'high' => 'عالية',
            'urgent' => 'عاجلة',
            default => $priority,
        };
    }

    public function taskShow(Request $request, Task $task): JsonResponse
    {
        $task->load(['creator', 'assignee']);

        return response()->json([
            'data' => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'created_by' => $task->creator?->name,
                'assigned_to' => $task->assignee?->name,
                'assigned_to_id' => $task->assigned_to,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date?->toIso8601ZuluString(),
                'completed_at' => $task->completed_at?->toIso8601ZuluString(),
                'created_at' => $task->created_at?->toIso8601ZuluString(),
                'comments' => TaskComment::where('task_id', $task->id)->with('user')->latest()->get()
                    ->map(fn ($c) => [
                        'id' => $c->id,
                        'user' => $c->user?->name,
                        'body' => $c->body,
                        'created_at' => $c->created_at?->toIso8601ZuluString(),
                    ]),
            ],
        ]);
    }

    // ── Chat ─────────────────────────────────────────────────

    /** GET /api/v1/chat — latest messages for the group chat */
    public function chat(Request $request): JsonResponse
    {
        $since = (int) $request->query('since', 0);

        $messages = ChatMessage::with(['user', 'poll.votes', 'poll.creator'])
            ->when($since > 0, fn ($q) => $q->where('id', '>', $since))
            ->orderByDesc('id')->limit(100)->get()->reverse()->values();

        return response()->json([
            'data' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name,
                'body' => $m->body,
                'created_at' => $m->created_at?->toIso8601ZuluString(),
                'is_mine' => $m->user_id === $request->user()->id,
                'poll' => $m->poll ? [
                    'id' => $m->poll->id,
                    'question' => $m->poll->question,
                    'options' => $m->poll->options,
                    'is_closed' => (bool) $m->poll->is_closed,
                    'my_vote' => $m->poll->votes->firstWhere('user_id', $request->user()->id)?->option_index,
                    'votes_count' => $m->poll->votes->count(),
                ] : null,
            ]),
        ]);
    }

    // ── Notifications ────────────────────────────────────────

    public function notifications(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            $this->notificationsQuery($request),
            $request,
            sortable: $this->notificationsSortable(),
            filters: $this->notificationsFilters(),
            filterable: $this->notificationsFilterable(),
        );
        $paginator->getCollection()->transform(fn ($n) => $this->notificationRow($n));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'unread_count' => Notification::where('user_id', $request->user()->id)
                ->where('is_read', false)->count(),
        ]));
    }

    /** the notifications query — always scoped to the current user (shared by the list and its export, T7-06) */
    private function notificationsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return Notification::where('user_id', $request->user()->id);
    }

    /** @return array<string, string> */
    private function notificationsSortable(): array
    {
        return ['date' => 'created_at', 'created_at' => 'created_at', 'title' => 'title', 'message' => 'message'];
    }

    /** @return array<string, array{type:string, column?:string, options?:callable}> */
    private function notificationsFilterable(): array
    {
        return [
            'unread' => ['type' => 'boolean', 'column' => 'is_read'],
            'type' => ['type' => 'select', 'column' => 'type'],
        ];
    }

    /** @return array<string, callable> */
    private function notificationsFilters(): array
    {
        return ['unread' => fn ($q, $v) => $v === '1' ? $q->where('is_read', false) : null];
    }

    /** the notifications list row — shared by the list and its export (T7-06) */
    private function notificationRow(Notification $n): array
    {
        return [
            'id' => $n->id,
            'title' => $n->title,
            'message' => $n->message,
            'type' => $n->type,
            'is_read' => (bool) $n->is_read,
            'related_id' => $n->related_id,
            'related_type' => $n->related_type,
            'link' => NotificationLink::spa($n->related_type, $n->related_id),
            'created_at' => $n->created_at?->toIso8601ZuluString(),
        ];
    }

    /**
     * GET /api/v1/notifications/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as notifications(), still scoped to the current user.
     */
    public function notificationsExport(Request $request): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            $this->notificationsQuery($request),
            $this->notificationsSortable(),
            $this->notificationsFilterable(),
            $this->notificationsFilters(),
            row: fn ($n) => $this->notificationRow($n),
        )->map(fn (array $n) => [
            'id' => $n['id'],
            'title' => $n['title'],
            'message' => $n['message'],
            'read' => $n['is_read'] ? 'مقروءة' : 'جديدة',
            'created_at' => $this->exportDate($n['created_at']),
        ]);

        return ListExport::stream($request, $rows, [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'title', 'label' => 'العنوان'],
            ['key' => 'message', 'label' => 'الرسالة'],
            ['key' => 'read', 'label' => 'الحالة'],
            ['key' => 'created_at', 'label' => 'التاريخ'],
        ], 'notifications');
    }

    // ── Broadcasts ───────────────────────────────────────────

    public function broadcasts(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            Broadcast::with('creator'),
            $request,
            sortable: $this->broadcastsSortable(),
            filters: $this->broadcastsFilters(),
            filterable: $this->broadcastsFilterable(),
        );
        $paginator->getCollection()->transform(fn ($b) => $this->broadcastRow($b));

        return response()->json(ApiList::envelope($paginator));
    }

    /** @return array<string, string> */
    private function broadcastsSortable(): array
    {
        return ['date' => 'created_at', 'created_at' => 'created_at', 'expires_at' => 'expires_at', 'title' => 'title', 'level' => 'level', 'sender' => 'created_by'];
    }

    /** @return array<string, array{type:string, column?:string, options?:callable}> */
    private function broadcastsFilterable(): array
    {
        return [
            'status' => ['type' => 'select', 'column' => 'is_active', 'options' => fn () => [
                ['value' => 'active', 'label' => 'نشطة'],
                ['value' => 'expired', 'label' => 'منتهية'],
            ]],
            'level' => ['type' => 'select', 'column' => 'level', 'options' => fn () => [
                ['value' => 'info', 'label' => 'معلومة'],
                ['value' => 'warning', 'label' => 'تحذير'],
                ['value' => 'danger', 'label' => 'خطورة'],
            ]],
        ];
    }

    /** @return array<string, callable> */
    private function broadcastsFilters(): array
    {
        return ['status' => fn ($q, $v) => $q->where('is_active', $v === 'active')];
    }

    /** the broadcasts list row — shared by the list and its export (T7-06) */
    private function broadcastRow(Broadcast $b): array
    {
        return [
            'id' => $b->id,
            'title' => $b->title,
            'message' => $b->message,
            'level' => $b->level,
            'is_active' => (bool) $b->is_active,
            'sender' => $b->creator?->name,
            'target_type' => $b->target_type,
            'target_user_id' => $b->target_user_id,
            'expires_at' => $b->expires_at?->toIso8601ZuluString(),
            'created_at' => $b->created_at?->toIso8601ZuluString(),
        ];
    }

    /**
     * GET /api/v1/broadcasts/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as broadcasts().
     */
    public function broadcastsExport(Request $request): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            Broadcast::with('creator'),
            $this->broadcastsSortable(),
            $this->broadcastsFilterable(),
            $this->broadcastsFilters(),
            row: fn ($b) => $this->broadcastRow($b),
        )->map(fn (array $b) => [
            'id' => $b['id'],
            'title' => $b['title'],
            'message' => $b['message'],
            'active' => $b['is_active'] ? 'نشط' : 'منتهي',
            'created_by' => $b['sender'],
            'created_at' => $this->exportDate($b['created_at']),
        ]);

        return ListExport::stream($request, $rows, [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'title', 'label' => 'العنوان'],
            ['key' => 'message', 'label' => 'الرسالة'],
            ['key' => 'active', 'label' => 'الحالة'],
            ['key' => 'created_by', 'label' => 'المرسِل'],
            ['key' => 'created_at', 'label' => 'التاريخ'],
        ], 'broadcasts');
    }

    /** The banner message shown to every user in the shell (dismissed ones live in the session) */
    public function activeBroadcast(Request $request): JsonResponse
    {
        $dismissed = (array) $request->session()->get('dismissed_broadcasts', []);

        $broadcast = Broadcast::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->where('target_type', 'all')
                ->orWhere(fn ($t) => $t->where('target_type', 'user')->where('target_user_id', $request->user()->id)))
            ->when($dismissed !== [], fn ($q) => $q->whereNotIn('id', $dismissed))
            ->latest()
            ->first();

        return response()->json([
            'data' => $broadcast ? [
                'id' => $broadcast->id,
                'title' => $broadcast->title,
                'message' => $broadcast->message,
                'level' => $broadcast->level,
            ] : null,
        ]);
    }

    // ── Activity log ─────────────────────────────────────────

    public function activityLogs(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            $this->activityLogsQuery($request),
            $request,
            sortable: $this->activityLogsSortable(),
            filterable: $this->activityLogsFilterable(),
            search: fn ($q, $term) => $this->activityLogsSearch($q, $term),
            filters: $this->activityLogsFilters(),
        );
        $paginator->getCollection()->transform(fn ($l) => $this->activityLogRow($l));

        return response()->json(ApiList::envelope($paginator));
    }

    /** the activity log query — the 'mine' scope is shared by the list and its export (T7-06) */
    private function activityLogsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $scope = $request->query('scope') === 'mine'
            ? fn ($q) => $q->where('user_id', $request->user()->id)
            : fn ($q) => $q;

        return $scope(ActivityLog::with('user'));
    }

    /** @return array<string, string> */
    private function activityLogsSortable(): array
    {
        return ['date' => 'created_at', 'created_at' => 'created_at', 'user' => 'user_id', 'event' => 'event', 'description' => 'description'];
    }

    /** @return array<string, array{type:string, column?:string, options?:callable}> */
    private function activityLogsFilterable(): array
    {
        return [
            'event' => ['type' => 'select', 'column' => 'event', 'options' => fn () => ActivityLog::distinct()->pluck('event')->map(fn ($e) => ['value' => $e, 'label' => $e])->values()->all()],
            'user' => ['type' => 'lookup', 'column' => 'user_id'],
            'created_at' => ['type' => 'date-range', 'column' => 'created_at'],
        ];
    }

    /** @return array<string, callable> */
    private function activityLogsFilters(): array
    {
        return [
            'user_id' => fn ($q, $v) => $q->where('user_id', (int) $v),
            'event' => fn ($q, $v) => $q->where('event', $v),
        ];
    }

    private function activityLogsSearch($q, string $term): void
    {
        $q->where(fn ($w) => $w
            ->where('description', 'like', "%{$term}%")
            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")));
    }

    /** the activity log list row — shared by the list and its export (T7-06) */
    private function activityLogRow(ActivityLog $l): array
    {
        return [
            'id' => $l->id,
            'user' => $l->user?->name,
            'event' => $l->event,
            'subject_type' => $l->subject_type,
            'subject_id' => $l->subject_id,
            'description' => $l->description,
            'created_at' => $l->created_at?->toIso8601ZuluString(),
        ];
    }

    /**
     * GET /api/v1/activity-logs/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as activityLogs().
     */
    public function activityLogsExport(Request $request): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            $this->activityLogsQuery($request),
            $this->activityLogsSortable(),
            $this->activityLogsFilterable(),
            $this->activityLogsFilters(),
            search: fn ($q, $term) => $this->activityLogsSearch($q, $term),
            row: fn ($l) => $this->activityLogRow($l),
        )->map(fn (array $l) => [
            'id' => $l['id'],
            'action' => $l['event'],
            'description' => $l['description'],
            'user' => $l['user'],
            'created_at' => $this->exportDateTime($l['created_at']),
        ]);

        return ListExport::stream($request, $rows, [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'action', 'label' => 'الحدث'],
            ['key' => 'description', 'label' => 'الوصف'],
            ['key' => 'user', 'label' => 'المستخدم'],
            ['key' => 'created_at', 'label' => 'الوقت'],
        ], 'activity-logs');
    }

    // ── Users ────────────────────────────────────────────────

    public function users(Request $request): JsonResponse
    {
        $this->authorize('manage_users');

        $paginator = ApiList::paginate(
            User::with('roles'),
            $request,
            sortable: $this->usersSortable(),
            filterable: $this->usersFilterable(),
            search: fn ($q, $term) => $this->usersSearch($q, $term),
            filters: $this->usersFilters(),
        );
        $paginator->getCollection()->transform(fn ($u) => $this->userRow($u));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
            'roles_count' => \Spatie\Permission\Models\Role::count(),
        ]));
    }

    /** @return array<string, string> */
    private function usersSortable(): array
    {
        return ['name' => 'name', 'date' => 'created_at', 'email' => 'email', 'phone' => 'phone', 'is_active' => 'is_active'];
    }

    /** @return array<string, array{type:string, column?:string, options?:callable}> */
    private function usersFilterable(): array
    {
        return [
            'status' => ['type' => 'select', 'column' => 'is_active', 'options' => fn () => [
                ['value' => '1', 'label' => 'نشط'],
                ['value' => '0', 'label' => 'موقوف'],
            ]],
        ];
    }

    /** @return array<string, callable> */
    private function usersFilters(): array
    {
        return [
            'role' => fn ($q, $v) => $q->whereHas('roles', fn ($r) => $r->where('name', $v)),
            'status' => fn ($q, $v) => $q->where('is_active', $v === 'active'),
        ];
    }

    private function usersSearch($q, string $term): void
    {
        $q->where(fn ($w) => $w
            ->where('name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%"));
    }

    /**
     * GET /api/v1/users/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as users(); exports ONLY the columns the
     * list exposes — never password or other sensitive fields.
     */
    public function usersExport(Request $request): StreamedResponse
    {
        $this->authorize('manage_users');

        $rows = ListExport::rows(
            $request,
            User::with('roles'),
            $this->usersSortable(),
            $this->usersFilterable(),
            $this->usersFilters(),
            search: fn ($q, $term) => $this->usersSearch($q, $term),
            row: fn ($u) => $this->userRow($u),
        )->map(fn (array $u) => [
            'name' => $u['name'],
            'email' => $u['email'],
            'roles' => $u['roles']->implode(','),
            'is_active' => $u['is_active'] ? 'نشط' : 'موقوف',
            'created_at' => $this->exportDate($u['created_at']),
        ]);

        return ListExport::stream($request, $rows, [
            ['key' => 'name', 'label' => 'الاسم'],
            ['key' => 'email', 'label' => 'البريد الإلكتروني'],
            ['key' => 'roles', 'label' => 'الأدوار'],
            ['key' => 'is_active', 'label' => 'الحالة'],
            ['key' => 'created_at', 'label' => 'التاريخ'],
        ], 'users');
    }

    public function userShow(Request $request, User $user): JsonResponse
    {
        // IDOR: user details only for self or user managers
        abort_unless($request->user()->id === $user->id || $request->user()->can('manage_users'), 403);

        return response()->json(['data' => $this->userRow($user)]);
    }

    // ── Settings and profile ─────────────────────────────────

    public function settings(Request $request): JsonResponse
    {
        $this->authorize('manage_settings');

        return response()->json([
            'data' => Setting::pluck('value', 'key'),
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $this->userRow($user) + [
                'profile_picture' => $user->profile_picture,
            ],
        ]);
    }

    // ── Export helpers (T7-06) ───────────────────────────────

    /** Y-m-d portion of an ISO-8601 row date (export rows) */
    private function exportDate(?string $iso): string
    {
        return $iso !== null && $iso !== '' ? substr($iso, 0, 10) : '';
    }

    /** Y-m-d H:i portion of an ISO-8601 row date (export rows) */
    private function exportDateTime(?string $iso): string
    {
        return $iso !== null && $iso !== '' ? substr($iso, 0, 10) . ' ' . substr($iso, 11, 8) : '';
    }

    private function userRow(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'roles' => $u->getRoleNames()->values(),
            'is_active' => (bool) $u->is_active,
            'created_at' => $u->created_at?->toIso8601ZuluString(),
        ];
    }
}
