<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\AudioUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = Announcement::query()
            ->with(['author:id,name', 'classroom:id,name'])
            ->latest('published_at')
            ->paginate(15);

        return view('admin.announcements.index', [
            'announcements' => $announcements,
            'classrooms' => Classroom::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'required_without:audio'],
            'audience' => ['required', 'in:all,teachers,guardians,classroom'],
            'classroom_id' => ['nullable', 'required_if:audience,classroom', 'exists:classrooms,id'],
            'audio' => AudioUpload::rules(),
            'auto_delete' => ['nullable', 'boolean'],
        ]);

        $audio = $request->file('audio');

        $announcement = Announcement::create([
            ...Arr::except($data, ['audio', 'auto_delete']),
            'user_id' => $request->user()->id,
            'published_at' => now(),
            'audio_path' => $audio ? AudioUpload::store($audio) : null,
            'audio_original_name' => $audio?->getClientOriginalName(),
            // Audio announcements disappear automatically one week after
            // publishing unless the manager opted out of the auto-delete.
            'expires_at' => ($audio && $request->boolean('auto_delete', true))
                ? now()->addWeek()
                : null,
        ]);

        $this->notifyAudience($request, $announcement);

        return back()->with('success', $audio ? 'تم نشر الإعلان الصوتي' : 'تم نشر الإعلان');
    }

    /** Fan-out to the matching audience (spec §39: new announcement). */
    private function notifyAudience(Request $request, Announcement $announcement): void
    {
        $tenantId = $request->user()->tenant_id;
        $notifier = app(NotificationService::class);
        $title = 'إعلان جديد';
        $body = "«{$announcement->title}»";

        if (filled($announcement->body)) {
            $body .= ' — '.mb_substr((string) $announcement->body, 0, 150);
        } elseif ($announcement->hasAudio()) {
            $body .= ' — إعلان صوتي';
        }

        $userIds = collect();

        switch ($announcement->audience) {
            case 'all':
                $userIds = User::query()
                    ->where('tenant_id', $tenantId)
                    ->pluck('id');
                break;

            case 'teachers':
                $userIds = User::query()
                    ->where('tenant_id', $tenantId)
                    ->where('role', User::ROLE_TEACHER)
                    ->pluck('id');
                break;

            case 'guardians':
                $userIds = User::query()
                    ->where('tenant_id', $tenantId)
                    ->where('role', User::ROLE_GUARDIAN)
                    ->pluck('id');
                break;

            case 'classroom':
                $roster = Student::query()
                    ->active()
                    ->where('classroom_id', $announcement->classroom_id)
                    ->get(['id', 'tenant_id', 'user_id']);

                $userIds = $notifier->recipientsForStudents($roster);
                break;
        }

        $notifier->send($userIds, $title, $body);
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', 'تم حذف الإعلان');
    }
}
