<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Custody;
use App\Models\Expense;
use App\Models\SocialCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * N-01: the global record search behind the command palette.
 *
 * Each group is scoped by the rule of the list it stands for — nothing here
 * is wider than what the user already sees:
 *   - custodies:      the management list (manage_treasury) sees all; an agent sees the custodies they hold
 *   - expenses:       the management list (view_all_expenses) sees all; everyone sees what they recorded
 *   - social_cases:   an accountant or a manager sees all; a researcher sees the cases they research
 *                     (the list endpoint itself checks nothing — see the round-6 changelog)
 *   - users:          managers only (manage_users)
 * A group the user has no right to is empty, never a refusal. This class is
 * the only place that knows these rules: the resource only relays them.
 */
final class GlobalSearchService
{
    /** the most results one group returns */
    public const PER_GROUP = 5;

    /** the groups, in the order the palette shows them */
    public const GROUPS = ['custodies', 'expenses', 'social_cases', 'users'];

    /**
     * @return array{groups: array<string, array<int, array{id: int, title: string, subtitle: string|null}>>, searched: list<string>}
     */
    public function search(User $user, string $term): array
    {
        $like = $this->pattern($term);
        $byNumber = ctype_digit($term) ? (int) $term : null;

        return [
            'groups' => [
                'custodies' => $this->custodies($user, $like, $byNumber),
                'expenses' => $this->expenses($user, $like),
                'social_cases' => $this->socialCases($user, $like),
                'users' => $this->users($user, $like),
            ],
            'searched' => $this->searchedGroups($user),
        ];
    }

    /** the groups this user's rules let them search */
    public function searchedGroups(User $user): array
    {
        $groups = ['expenses'];
        if ($user->can('manage_treasury') || $user->hasRole('مندوب')) {
            $groups[] = 'custodies';
        }
        if ($this->seesAllCases($user) || $user->hasRole('باحث اجتماعي')) {
            $groups[] = 'social_cases';
        }
        if ($user->can('manage_users')) {
            $groups[] = 'users';
        }

        // in the palette's order
        return array_values(array_intersect(self::GROUPS, $groups));
    }

    /**
     * @return array<int, array{id: int, title: string, subtitle: string|null}>
     */
    private function custodies(User $user, string $like, ?int $byNumber): array
    {
        $query = Custody::query()->with(['agent', 'treasury'])->orderByDesc('id');
        if ($user->can('manage_treasury')) {
            // the management list
        } elseif ($user->hasRole('مندوب')) {
            // "my custodies"
            $query->where('agent_id', $user->id);
        } else {
            return [];
        }
        // the agent's name, or the custody's number
        $query->where(function (Builder $q) use ($like, $byNumber): void {
            $q->whereHas('agent', fn (Builder $a) => $a->where('name', 'like', $like));
            if ($byNumber !== null) {
                $q->orWhere('id', $byNumber);
            }
        });

        return $query->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Custody $c) => [
                'id' => (int) $c->id,
                'title' => (string) ($c->agent?->name ?? "#{$c->id}"),
                'subtitle' => $c->treasury?->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string, subtitle: string|null}>
     */
    private function expenses(User $user, string $like): array
    {
        $query = Expense::query()->with(['user'])->orderByDesc('id')
            // the description, or who recorded it
            ->where(function (Builder $q) use ($like): void {
                $q->where('description', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like));
            });
        if (! $user->can('view_all_expenses')) {
            // "my expenses"
            $query->where('user_id', $user->id);
        }

        return $query->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Expense $e) => [
                'id' => (int) $e->id,
                'title' => (string) ($e->description !== '' && $e->description !== null ? $e->description : "#{$e->id}"),
                'subtitle' => $e->user?->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string, subtitle: string|null}>
     */
    private function socialCases(User $user, string $like): array
    {
        $query = SocialCase::query()->orderByDesc('id')
            // the name, or the phone
            ->where(function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });

        if ($this->seesAllCases($user)) {
            // all of them
        } elseif ($user->hasRole('باحث اجتماعي')) {
            // the researcher's own cases
            $query->where('researcher_id', $user->id);
        } else {
            return [];
        }

        return $query->limit(self::PER_GROUP)
            ->get()
            ->map(fn (SocialCase $c) => [
                'id' => (int) $c->id,
                'title' => (string) $c->name,
                'subtitle' => $c->phone,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string, subtitle: string|null}>
     */
    private function users(User $user, string $like): array
    {
        if (! $user->can('manage_users')) {
            return [];
        }

        return User::query()->orderBy('name')
            // the name, or the email
            ->where(function (Builder $q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (User $u) => [
                'id' => (int) $u->id,
                'title' => (string) $u->name,
                'subtitle' => $u->email,
            ])
            ->all();
    }

    private function seesAllCases(User $user): bool
    {
        return $user->hasRole('محاسب') || $user->hasRole('مدير');
    }

    /** a LIKE pattern from the search text: the user's own wildcards do not count */
    private function pattern(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}
