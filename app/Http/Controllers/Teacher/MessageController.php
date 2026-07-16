<?php

namespace App\Http\Controllers\Teacher;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends BaseTeacherController
{
    public function index(Request $request): View
    {
        $userId = $request->user()->id;

        $messages = Message::query()
            ->with(['sender:id,name', 'recipient:id,name'])
            ->where(fn ($q) => $q->where('recipient_id', $userId)->orWhere('sender_id', $userId))
            ->latest()
            ->paginate(20);

        Message::where('recipient_id', $userId)->whereNull('read_at')->update(['read_at' => now()]);

        return view('teacher.messages.index', [
            'messages' => $messages,
            'recipients' => User::where('id', '!=', $userId)->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient_id' => ['required', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        Message::create([...$data, 'sender_id' => $request->user()->id]);

        return back()->with('success', 'تم إرسال الرسالة');
    }
}
