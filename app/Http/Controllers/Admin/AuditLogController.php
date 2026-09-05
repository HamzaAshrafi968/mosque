<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only audit trail (spec §38). Entries are written by AuditLogger across
 * controllers/actions; nothing here ever mutates the trail.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', '%'.$request->string('action')->toString().'%'))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->input('entity_type')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->input('date')))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'entityTypes' => AuditLog::query()
                ->select('entity_type')
                ->distinct()
                ->orderBy('entity_type')
                ->pluck('entity_type'),
        ]);
    }
}
