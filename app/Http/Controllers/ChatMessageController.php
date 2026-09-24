<?php

namespace App\Http\Controllers;

use App\Http\Requests\Chat\IndexChatMessageRequest;
use App\Http\Requests\Chat\StoreChatMessageRequest;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\ChatDocumentReferenceService;
use App\Services\ChatMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class ChatMessageController extends Controller
{
    public function __construct(private readonly ChatDocumentReferenceService $documentReferences) {}

    public function index(IndexChatMessageRequest $request, ChatConversation $chatConversation): JsonResponse
    {
        $beforeId = $request->validated('before_id');
        $limit = $beforeId === null ? 100 : 50;
        $query = $chatConversation->messages()->with([
            'sender:id,name',
            ...ChatDocumentReferenceService::EAGER_LOADS,
        ]);

        if ($beforeId !== null) {
            $messages = $query->where('id', '<', $beforeId)
                ->orderByDesc('id')
                ->limit($limit + 1)
                ->get();
            $hasMore = $messages->count() > $limit;
            $messages = $messages->take($limit)->reverse()->values();
        } else {
            $messages = $query->where('id', '>', $request->integer('after_id'))
                ->orderBy('id')
                ->limit($limit)
                ->get();
            $hasMore = $messages->count() === $limit;
        }

        return $this->privateJson([
            'messages' => $this->serializeMessages($messages, $request->user()),
            'has_more' => $hasMore,
        ]);
    }

    public function store(
        StoreChatMessageRequest $request,
        ChatConversation $chatConversation,
        ChatMessageService $service
    ): JsonResponse {
        $message = $service->send(
            $chatConversation,
            $request->user(),
            $request->validated('body'),
            $request->documentReference(),
        );

        return $this->privateJson([
            'message' => $this->serializeMessage($message, $request->user()),
        ], 201);
    }

    /** @param Collection<int, ChatMessage> $messages */
    private function serializeMessages(Collection $messages, User $viewer): array
    {
        return $messages->map(fn (ChatMessage $message): array => $this->serializeMessage($message, $viewer))->all();
    }

    private function serializeMessage(ChatMessage $message, User $viewer): array
    {
        return [
            'id' => $message->getKey(),
            'body' => $message->body,
            'sender_name' => $message->sender->name,
            'is_mine' => $message->sender_id === $viewer->getKey(),
            'sent_at_iso' => $message->created_at->toIso8601String(),
            'sent_at_display' => $message->created_at->format('M j, Y g:i A').' UTC',
            'document_reference' => $this->documentReferences->present($message->documentReference, $viewer),
        ];
    }

    private function privateJson(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->withHeaders([
            'Cache-Control' => 'private, no-store, no-cache, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
