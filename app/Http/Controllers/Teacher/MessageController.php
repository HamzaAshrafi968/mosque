<?php

namespace App\Http\Controllers\Teacher;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MessageController extends BaseTeacherController
{
    public function index(Request $request): View
    {
        $userId = $request->user()->id;
        $partnerId = $request->string('with')->toString() ?: null;
        $search = trim($request->string('q')->toString());

        $partner = null;
        $thread = null;

        if ($partnerId !== null) {
            $partner = User::query()->whereKey($partnerId)->first();
            abort_if($partner === null || $partner->id === $userId, 404);

            $thread = Message::query()
                ->with(['sender:id,name', 'recipient:id,name'])
                ->where(function ($query) use ($userId, $partner) {
                    $query->where('sender_id', $userId)->where('recipient_id', $partner->id);
                })
                ->orWhere(function ($query) use ($userId, $partner) {
                    $query->where('sender_id', $partner->id)->where('recipient_id', $userId);
                })
                ->orderBy('created_at')
                ->paginate(50)
                ->withQueryString();

            Message::query()
                ->where('sender_id', $partner->id)
                ->where('recipient_id', $userId)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return view('teacher.messages.index', [
            'conversations' => $this->conversations($userId, $search),
            'partner' => $partner,
            'thread' => $thread,
            'recipients' => User::where('id', '!=', $userId)->orderBy('name')->get(['id', 'name', 'role']),
            'search' => $search,
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

        return redirect()
            ->route('teacher.messages.index', ['with' => $data['recipient_id']])
            ->with('success', 'تم إرسال الرسالة');
    }

    /**
     * محادثات المستخدم مجمّعة حسب الطرف الآخر مع آخر رسالة وعدد غير المقروء.
     *
     * @return Collection<int, array{partner: User, last: Message, unread: int}>
     */
    private function conversations(string $userId, string $search = ''): Collection
    {
        $messages = Message::query()
            ->with(['sender:id,name,role', 'recipient:id,name,role'])
            ->where(fn ($query) => $query->where('recipient_id', $userId)->orWhere('sender_id', $userId))
            ->latest()
            ->limit(1000)
            ->get();

        return $messages
            ->groupBy(fn (Message $message) => $message->sender_id === $userId ? $message->recipient_id : $message->sender_id)
            ->map(function (Collection $group) use ($userId): array {
                $last = $group->first();
                $partner = $last->sender_id === $userId ? $last->recipient : $last->sender;

                return [
                    'partner' => $partner,
                    'last' => $last,
                    'unread' => $group
                        ->where('sender_id', '!=', $userId)
                        ->whereNull('read_at')
                        ->count(),
                ];
            })
            ->filter(fn (array $conversation) => $conversation['partner'] !== null)
            ->when($search !== '', fn (Collection $conversations) => $conversations->filter(
                fn (array $conversation) => str_contains(mb_strtolower($conversation['partner']->name), mb_strtolower($search))
            ))
            ->sortByDesc(fn (array $conversation) => $conversation['last']->created_at)
            ->values();
    }
}
