<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\Report\GenerateReportAction;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends BaseApiController
{
    public function __construct(
        private readonly GenerateReportAction $generateReport,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type', 'students');
        $from = $request->input('from', now()->subMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $rows = $this->generateReport->execute([
            'type' => $type,
            'from' => $from,
            'to' => $to,
        ]);

        return $this->success([
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
        ]);
    }
}
