<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditActionCatalog;
use App\Support\AuditLogPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Read-only audit trail (spec §38). Entries are written by AuditLogger across
 * controllers/actions; nothing here ever mutates the trail.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'group' => (string) $request->input('group', ''),
            'user_id' => (string) $request->input('user_id', ''),
            'entity_type' => (string) $request->input('entity_type', ''),
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
        ];

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($filters['q'] !== '', fn (Builder $query) => $this->applySearch($query, $filters['q']))
            ->when(AuditActionCatalog::groupExists($filters['group']), fn (Builder $query) => $this->applyGroup($query, $filters['group']))
            ->when(Str::isUuid($filters['user_id']), fn (Builder $query) => $query->where('user_id', $filters['user_id']))
            ->when($filters['entity_type'] !== '', fn (Builder $query) => $query->where('entity_type', $filters['entity_type']))
            ->when($this->validDate($filters['from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($this->validDate($filters['to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $logs->through(fn (AuditLog $log) => AuditLogPresenter::from($log));

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'entityTypes' => AuditLog::query()
                ->select('entity_type')
                ->distinct()
                ->orderBy('entity_type')
                ->pluck('entity_type'),
            'groups' => AuditActionCatalog::groups(),
            'users' => $this->actorOptions(),
            'dayGroups' => $logs->getCollection()
                ->groupBy(fn (AuditLogPresenter $entry) => $entry->log->created_at?->toDateString() ?? 'unknown')
                ->map(fn ($entries) => $entries->values()),
            'stats' => $this->stats(),
        ]);
    }

    private function applySearch(Builder $query, string $term): void
    {
        $matchingActions = AuditActionCatalog::actionsMatching($term);

        $query->where(function (Builder $inner) use ($term, $matchingActions) {
            $inner->where('action', 'like', '%'.$term.'%')
                ->orWhere('entity_type', 'like', '%'.$term.'%')
                ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', '%'.$term.'%'));

            if ($matchingActions !== []) {
                $inner->orWhereIn('action', $matchingActions);
            }
        });
    }

    private function applyGroup(Builder $query, string $group): void
    {
        $prefixes = AuditActionCatalog::groupPrefixes($group);

        if ($prefixes === []) {
            return;
        }

        $query->where(function (Builder $inner) use ($prefixes) {
            foreach ($prefixes as $prefix) {
                $inner->orWhere('action', 'like', $prefix.'%');
            }
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function actorOptions(): Collection
    {
        $actorIds = AuditLog::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        if ($actorIds->isEmpty()) {
            return collect();
        }

        return User::withoutGlobalScope('tenant')
            ->whereIn('id', $actorIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return array{today: int, week: int, users: int, latest_label: string, latest_hint: ?string}
     */
    private function stats(): array
    {
        $weekStart = now()->subDays(6)->startOfDay();

        $latest = AuditLog::query()->latest('created_at')->first();

        return [
            'today' => AuditLog::query()->whereDate('created_at', today())->count(),
            'week' => AuditLog::query()->where('created_at', '>=', $weekStart)->count(),
            'users' => AuditLog::query()
                ->where('created_at', '>=', $weekStart)
                ->whereNotNull('user_id')
                ->distinct()
                ->count('user_id'),
            'latest_label' => $latest ? AuditActionCatalog::relativeTime($latest->created_at) : '—',
            'latest_hint' => $latest?->created_at
                ? AuditActionCatalog::dateTimeLabel($latest->created_at)
                : null,
        ];
    }

    private function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}
