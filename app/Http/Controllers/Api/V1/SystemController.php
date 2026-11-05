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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SystemController extends Controller
{
    // ── Tasks ────────────────────────────────────────────────

    public function tasks(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            Task::with(['creator', 'assignee']),
            $request,
            sortable: ['date' => 'created_at', 'due_date' => 'due_date', 'title' => 'title', 'assigned_to' => 'assigned_to', 'status' => 'status', 'priority' => 'priority'],
            filterable: [
                'status' => ['type' => 'select', 'column' => 'status', 'options' => fn () => [
                    ['value' => 'pending', 'label' => 'معلنة'],
                    ['value' => 'in_progress', 'label' => 'جارية'],
                    ['value' => 'completed', 'label' => 'مكتملة'],
                    ['value' => 'cancelled', 'label' => 'ملغاة'],
                ]],
                'priority' => ['type' => 'select', 'column' => 'priority'],
                'assigned_to' => ['type' => 'lookup', 'column' => 'assigned_to'],
            ],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")),
            filters: [
                'status' => fn ($q, $v) => $q->where('status', $v),
                'assigned_to' => fn ($q, $v) => $q->where('assigned_to', (int) $v),
            ],
        );
        $paginator->getCollection()->transform(fn ($t) => [
            'id' => $t->id,
            'title' => $t->title,
            'created_by' => $t->creator?->name,
            'assigned_to' => $t->assignee?->name,
            'assigned_to_id' => $t->assigned_to,
            'status' => $t->status,
            'priority' => $t->priority,
            'due_date' => $t->due_date?->toIso8601ZuluString(),
            'created_at' => $t->created_at?->toIso8601ZuluString(),
        ]);

        return response()->json(ApiList::envelope($paginator, fn () => [
            'pending' => Task::where('status', 'pending')->count(),
            'in_progress' => Task::where('status', 'in_progress')->count(),
            'completed' => Task::where('status', 'completed')->count(),
            'cancelled' => Task::where('status', 'cancelled')->count(),
        ]));
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
            Notification::where('user_id', $request->user()->id),
            $request,
            sortable: ['date' => 'created_at', 'created_at' => 'created_at', 'title' => 'title', 'message' => 'message'],
            filters: ['unread' => fn ($q, $v) => $v === '1' ? $q->where('is_read', false) : null],
            filterable: [
                'unread' => ['type' => 'boolean', 'column' => 'is_read'],
                'type' => ['type' => 'select', 'column' => 'type'],
            ],
        );
        $paginator->getCollection()->transform(fn ($n) => [
            'id' => $n->id,
            'title' => $n->title,
            'message' => $n->message,
            'type' => $n->type,
            'is_read' => (bool) $n->is_read,
            'related_id' => $n->related_id,
            'related_type' => $n->related_type,
            'link' => NotificationLink::spa($n->related_type, $n->related_id),
            'created_at' => $n->created_at?->toIso8601ZuluString(),
        ]);

        return response()->json(ApiList::envelope($paginator, fn () => [
            'unread_count' => Notification::where('user_id', $request->user()->id)
                ->where('is_read', false)->count(),
        ]));
    }

    // ── Broadcasts ───────────────────────────────────────────

    public function broadcasts(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            Broadcast::with('creator'),
            $request,
            sortable: ['date' => 'created_at', 'created_at' => 'created_at', 'expires_at' => 'expires_at', 'title' => 'title', 'level' => 'level', 'sender' => 'created_by'],
            filters: ['status' => fn ($q, $v) => $q->where('is_active', $v === 'active')],
            filterable: [
                'status' => ['type' => 'select', 'column' => 'is_active', 'options' => fn () => [
                    ['value' => 'active', 'label' => 'نشطة'],
                    ['value' => 'expired', 'label' => 'منتهية'],
                ]],
                'level' => ['type' => 'select', 'column' => 'level', 'options' => fn () => [
                    ['value' => 'info', 'label' => 'معلومة'],
                    ['value' => 'warning', 'label' => 'تحذير'],
                    ['value' => 'danger', 'label' => 'خطورة'],
                ]],
            ],
        );
        $paginator->getCollection()->transform(fn ($b) => [
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
        ]);

        return response()->json(ApiList::envelope($paginator));
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
        $scope = $request->query('scope') === 'mine'
            ? fn ($q) => $q->where('user_id', $request->user()->id)
            : fn ($q) => $q;

        $paginator = ApiList::paginate(
            $scope(ActivityLog::with('user')),
            $request,
            sortable: ['date' => 'created_at', 'created_at' => 'created_at', 'user' => 'user_id', 'event' => 'event', 'description' => 'description'],
            filterable: [
                'event' => ['type' => 'select', 'column' => 'event', 'options' => fn () => ActivityLog::distinct()->pluck('event')->map(fn ($e) => ['value' => $e, 'label' => $e])->values()->all()],
                'user' => ['type' => 'lookup', 'column' => 'user_id'],
                'created_at' => ['type' => 'date-range', 'column' => 'created_at'],
            ],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('description', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"))),
            filters: [
                'user_id' => fn ($q, $v) => $q->where('user_id', (int) $v),
                'event' => fn ($q, $v) => $q->where('event', $v),
            ],
        );
        $paginator->getCollection()->transform(fn ($l) => [
            'id' => $l->id,
            'user' => $l->user?->name,
            'event' => $l->event,
            'subject_type' => $l->subject_type,
            'subject_id' => $l->subject_id,
            'description' => $l->description,
            'created_at' => $l->created_at?->toIso8601ZuluString(),
        ]);

        return response()->json(ApiList::envelope($paginator));
    }

    // ── Users ────────────────────────────────────────────────

    public function users(Request $request): JsonResponse
    {
        $this->authorize('manage_users');

        $paginator = ApiList::paginate(
            User::with('roles'),
            $request,
            sortable: ['name' => 'name', 'date' => 'created_at', 'email' => 'email', 'phone' => 'phone', 'is_active' => 'is_active'],
            filterable: [
                'status' => ['type' => 'select', 'column' => 'is_active', 'options' => fn () => [
                    ['value' => '1', 'label' => 'نشط'],
                    ['value' => '0', 'label' => 'موقوف'],
                ]],
            ],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")),
            filters: [
                'role' => fn ($q, $v) => $q->whereHas('roles', fn ($r) => $r->where('name', $v)),
                'status' => fn ($q, $v) => $q->where('is_active', $v === 'active'),
            ],
        );
        $paginator->getCollection()->transform(fn ($u) => $this->userRow($u));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
            'roles_count' => \Spatie\Permission\Models\Role::count(),
        ]));
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
