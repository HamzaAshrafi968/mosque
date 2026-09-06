<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaithMeetingTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaithMeetingTemplateController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.faith-meetings.templates', [
            'templates' => FaithMeetingTemplate::query()
                ->orderByDesc('is_active')
                ->orderBy('title')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $template = FaithMeetingTemplate::create($data);
        $this->audit->logModel('faith_meeting_template.created', $template, actor: $request->user());

        return redirect()->route('admin.faith-meetings.templates')->with('success', 'تم إنشاء قالب اللقاء');
    }

    public function update(Request $request, FaithMeetingTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $before = $template->getAttributes();

        $template->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit->logModel('faith_meeting_template.updated', $template, $before, actor: $request->user());

        return redirect()->route('admin.faith-meetings.templates')->with('success', 'تم تحديث القالب');
    }

    public function destroy(Request $request, FaithMeetingTemplate $template): RedirectResponse
    {
        $this->audit->logModel('faith_meeting_template.deleted', $template, actor: $request->user());
        $template->delete();

        return redirect()->route('admin.faith-meetings.templates')->with('success', 'تم حذف القالب');
    }
}
