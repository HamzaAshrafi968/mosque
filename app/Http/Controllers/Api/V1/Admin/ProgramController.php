<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\Program\SaveProgramAction;
use App\Enums\ScheduleProgramType;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\Admin\StoreProgramRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProgramRequest;
use App\Http\Resources\Api\V1\ProgramResource;
use App\Models\Program;
use App\Services\AuditLogger;
use App\Services\ProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends BaseApiController
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ProgramService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $type = ScheduleProgramType::tryFrom((string) $request->input('type'));
        $sessionId = $request->input('study_session_id');

        $programs = Program::query()
            ->with(['periods', 'attributes', 'studySessions:id,name'])
            ->withCount('schedules')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->input('search').'%'))
            ->when($type, fn ($query) => $query->where('type', $type->value))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->boolean('status')))
            ->when(
                $sessionId && $this->service->sessionRestrictsPrograms($sessionId),
                fn ($query) => $query->whereHas('studySessions', fn ($q) => $q->where('study_sessions.id', $sessionId))
            )
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success(['programs' => ProgramResource::collection($programs)]);
    }

    public function show(Program $program): JsonResponse
    {
        $program->load(['periods', 'attributes', 'studySessions:id,name'])->loadCount('schedules');

        return $this->success(['program' => ProgramResource::make($program)]);
    }

    public function store(StoreProgramRequest $request, SaveProgramAction $save): JsonResponse
    {
        $tenantId = config('app.current_tenant_id') ?? $request->user()->tenant_id;

        if (blank($tenantId)) {
            return $this->error('لا يوجد جامع محدد', 422);
        }

        $program = $save->create($request->validated(), (string) $tenantId);

        $this->audit->logModel('program.created', $program, actor: $request->user());

        return $this->created(ProgramResource::make($program->load(['periods', 'attributes'])), 'تم إنشاء البرنامج');
    }

    public function update(UpdateProgramRequest $request, Program $program, SaveProgramAction $save): JsonResponse
    {
        $before = $program->getAttributes();

        $save->update($program, $request->validated());

        $this->audit->logModel('program.updated', $program, $before, actor: $request->user());

        return $this->success(
            ProgramResource::make($program->fresh()->load(['periods', 'attributes'])),
            'تم تحديث البرنامج'
        );
    }

    public function destroy(Request $request, Program $program): JsonResponse
    {
        if (! $this->service->canDelete($program)) {
            return $this->error('لا يمكن حذف برنامج له حصص في الجداول — عطّله بدل ذلك', 422);
        }

        $this->audit->logModel('program.deleted', $program, actor: $request->user());
        $program->delete();

        return $this->success(message: 'تم حذف البرنامج');
    }
}
