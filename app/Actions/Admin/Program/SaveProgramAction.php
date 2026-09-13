<?php

namespace App\Actions\Admin\Program;

use App\Enums\ScheduleProgramType;
use App\Models\Program;
use App\Services\ProgramService;
use Illuminate\Support\Facades\DB;

/**
 * Creates/updates a program with its periods and custom attributes.
 * Shared by the admin Blade and API program controllers.
 */
class SaveProgramAction
{
    public function __construct(private readonly ProgramService $service) {}

    public function create(array $data, string $tenantId): Program
    {
        return DB::transaction(function () use ($data, $tenantId) {
            $type = ScheduleProgramType::from($data['type']);

            $program = Program::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'code' => ! empty($data['code'])
                    ? $data['code']
                    : $this->service->uniqueCode($tenantId, $data['name']),
                'type' => $type,
                'description' => $data['description'] ?? null,
                'color' => ! empty($data['color']) ? $data['color'] : $type->color(),
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncRelations($program, $data);

            return $program;
        });
    }

    public function update(Program $program, array $data): Program
    {
        return DB::transaction(function () use ($program, $data) {
            $type = ScheduleProgramType::from($data['type']);

            $program->update([
                'name' => $data['name'],
                'code' => ! empty($data['code']) ? $data['code'] : $program->code,
                'type' => $type,
                'description' => $data['description'] ?? null,
                'color' => ! empty($data['color']) ? $data['color'] : $type->color(),
                'is_active' => $data['is_active'] ?? false,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncRelations($program, $data);

            return $program;
        });
    }

    /**
     * Sync periods/attributes only when the payload includes them, so partial
     * API updates do not wipe existing relations.
     */
    private function syncRelations(Program $program, array $data): void
    {
        if (array_key_exists('periods', $data)) {
            $this->service->syncPeriods($program, $data['periods'] ?? []);
        }

        if (array_key_exists('attributes', $data)) {
            $this->service->syncAttributes($program, $data['attributes'] ?? []);
        }
    }
}
