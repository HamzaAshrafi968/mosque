<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Contracts\Repositories\MessageRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Http\Requests\Api\V1\Teacher\StoreMessageRequest;
use App\Http\Resources\Api\V1\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends BaseTeacherController
{
    public function index(Request $request, MessageRepositoryInterface $messageRepository, UserRepositoryInterface $userRepository): JsonResponse
    {
        $userId = $request->user()->id;

        $messages = $messageRepository->paginateByUser($userId);
        $messageRepository->markRead($userId);

        return $this->success([
            'messages' => MessageResource::collection($messages),
            'recipients' => $userRepository->getRecipientsExcept($userId),
        ]);
    }

    public function store(StoreMessageRequest $request, MessageRepositoryInterface $messageRepository): JsonResponse
    {
        $message = $messageRepository->create([
            ...$request->validated(),
            'sender_id' => $request->user()->id,
        ]);

        return $this->created(
            MessageResource::make($message),
            'تم إرسال الرسالة'
        );
    }
}
