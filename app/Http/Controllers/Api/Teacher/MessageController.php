<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends BaseTeacherController
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $messages = Message::query()
            ->with(['sender:id,name', 'recipient:id,name'])
            ->where(fn ($q) => $q->where('recipient_id', $userId)->orWhere('sender_id', $userId))
            ->latest()
            ->paginate(20);

        Message::where('recipient_id', $userId)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages,
            'recipients' => User::where('id', '!=', $userId)->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipient_id' => ['required', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $message = Message::create([...$data, 'sender_id' => $request->user()->id]);

        return response()->json([
            'message' => 'تم إرسال الرسالة',
            'data' => $message,
        ], 201);
    }
}
