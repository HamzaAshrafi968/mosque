<?php

namespace App\Support;

use App\Models\AuditLog;

final class AuditLogPresenter
{
    public function __construct(public readonly AuditLog $log) {}

    public static function from(AuditLog $log): self
    {
        return new self($log);
    }

    public function actionLabel(): string
    {
        return AuditActionCatalog::actionLabel((string) $this->log->action);
    }

    public function group(): string
    {
        return AuditActionCatalog::actionGroup((string) $this->log->action);
    }

    public function groupLabel(): string
    {
        return AuditActionCatalog::groupLabel($this->group());
    }

    public function groupIcon(): string
    {
        return AuditActionCatalog::groupIcon($this->group());
    }

    public function groupTone(): string
    {
        return AuditActionCatalog::groupTone($this->group());
    }

    public function entityLabel(): string
    {
        return AuditActionCatalog::entityLabel((string) $this->log->entity_type);
    }

    public function isSystem(): bool
    {
        return $this->log->user_id === null;
    }

    public function actorName(): string
    {
        return $this->log->user?->name ?? 'النظام';
    }

    public function actorInitial(): string
    {
        $initial = mb_substr(trim($this->actorName()), 0, 1);

        return $initial !== '' ? $initial : '؟';
    }

    public function entityName(): ?string
    {
        $candidates = [
            'name', 'full_name', 'title', 'student_name', 'teacher_name',
            'guardian_name', 'classroom_name', 'section_name', 'label',
        ];

        foreach ([$this->log->after ?? [], $this->log->before ?? []] as $snapshot) {
            foreach ($candidates as $key) {
                $value = $snapshot[$key] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        return null;
    }

    public function relativeTime(): string
    {
        return AuditActionCatalog::relativeTime($this->log->created_at);
    }

    public function exactTime(): string
    {
        return $this->log->created_at
            ? AuditActionCatalog::dateTimeLabel($this->log->created_at)
            : '—';
    }

    public function dayLabel(): string
    {
        return $this->log->created_at
            ? AuditActionCatalog::dayLabel($this->log->created_at)
            : '—';
    }

    public function reference(): string
    {
        return strtoupper(substr((string) $this->log->id, 0, 8));
    }

    public function hasChanges(): bool
    {
        return $this->changes() !== [];
    }

    public function hasBeforeValues(): bool
    {
        foreach ($this->changes() as $change) {
            if ($change['before'] !== '—') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{label: string, before: string, after: string}>
     */
    public function changes(): array
    {
        $before = $this->log->before ?? [];
        $after = $this->log->after ?? [];

        if ($before === [] && $after === []) {
            return [];
        }

        if ($before === []) {
            return $this->snapshotChanges($after, null);
        }

        if ($after === []) {
            return $this->snapshotChanges($before, true);
        }

        $changes = [];

        foreach ($before as $key => $old) {
            $key = (string) $key;

            if ($this->isHidden($key)) {
                continue;
            }

            $new = $after[$key] ?? null;

            if (json_encode($old) === json_encode($new)) {
                continue;
            }

            $changes[] = $this->change($key, $old, $new);
        }

        foreach ($after as $key => $new) {
            $key = (string) $key;

            if (array_key_exists($key, $before) || $this->isHidden($key) || $new === null) {
                continue;
            }

            $changes[] = $this->change($key, null, $new);
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<int, array{label: string, before: string, after: string}>
     */
    private function snapshotChanges(array $snapshot, ?bool $asBefore): array
    {
        $changes = [];

        foreach ($snapshot as $key => $value) {
            $key = (string) $key;

            if ($this->isHidden($key)) {
                continue;
            }

            $changes[] = $asBefore === true
                ? $this->change($key, $value, null)
                : $this->change($key, null, $value);
        }

        return $changes;
    }

    /**
     * @return array{label: string, before: string, after: string}
     */
    private function change(string $key, mixed $before, mixed $after): array
    {
        return [
            'label' => AuditActionCatalog::fieldLabel($key),
            'before' => AuditActionCatalog::formatValue($before, 0, $key),
            'after' => AuditActionCatalog::formatValue($after, 0, $key),
        ];
    }

    private function isHidden(string $key): bool
    {
        return in_array($key, AuditActionCatalog::HIDDEN_FIELDS, true);
    }
}
