<?php

namespace App\Http\Controllers;

use App\Http\Requests\Chat\MarkChatConversationReadRequest;
use App\Http\Requests\Chat\StoreChatConversationRequest;
use App\Models\ChatConversation;
use App\Models\User;
use App\Services\ChatConversationService;
use App\Services\ChatDocumentReferenceService;
use App\Services\ChatUnreadService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ChatConversationController extends Controller
{
    private const HISTORY_PAGE_SIZE = 50;

    public function __construct(
        private readonly ChatUnreadService $unreadService,
        private readonly ChatDocumentReferenceService $documentReferences,
    ) {}

    public function index(Request $request): Response
    {
        return $this->render($request);
    }

    public function store(StoreChatConversationRequest $request, ChatConversationService $service): RedirectResponse
    {
        $conversation = $service->start($request->user(), $request->integer('recipient_id'));

        return redirect()
            ->route('chat.conversations.show', $conversation)
            ->withHeaders($this->privateHeaders());
    }

    public function show(Request $request, ChatConversation $chatConversation): Response
    {
        Gate::authorize('view', $chatConversation);

        return $this->render($request, $chatConversation);
    }

    public function unread(Request $request): JsonResponse
    {
        return $this->privateJson([
            'unread' => $this->unreadService->summary($request->user()),
        ]);
    }

    public function markRead(
        MarkChatConversationReadRequest $request,
        ChatConversation $chatConversation,
    ): JsonResponse {
        $unread = $this->unreadService->markRead(
            $chatConversation,
            $request->user(),
            $request->integer('through_id'),
        );

        return $this->privateJson([
            'unread' => $unread,
        ]);
    }

    private function render(Request $request, ?ChatConversation $selected = null): Response
    {
        $user = $request->user();
        $supportedRoles = array_keys(config('shield.roles', []));
        $unread = $this->unreadService->summary($user);

        $conversations = ChatConversation::query()
            ->forUser($user)
            ->with(['userOne:id,name,role,is_active', 'userTwo:id,name,role,is_active'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $conversationItems = $conversations->map(function (ChatConversation $conversation) use ($user, $selected, $unread): array {
            $other = $conversation->user_one_id === $user->getKey()
                ? $conversation->userTwo
                : $conversation->userOne;

            return [
                'id' => $conversation->getKey(),
                'name' => $other->name,
                'role' => config("shield.roles.{$other->role}.label", $other->role),
                'is_active' => $other->is_active,
                'is_selected' => $selected?->is($conversation) ?? false,
                'unread_count' => $unread['conversations'][$conversation->getKey()]['count'] ?? 0,
                'unread_count_text' => $unread['conversations'][$conversation->getKey()]['count_text'] ?? '0',
            ];
        });

        $recipients = User::query()
            ->select(['id', 'name', 'role'])
            ->where('is_active', true)
            ->whereIn('role', $supportedRoles)
            ->whereKeyNot($user->getKey())
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (User $recipient): array => [
                'id' => $recipient->getKey(),
                'name' => $recipient->name,
                'role' => config("shield.roles.{$recipient->role}.label", $recipient->role),
            ]);

        $messages = collect();
        $hasOlderMessages = false;
        $selectedParticipant = null;
        $canSend = false;
        $messageReferences = collect();
        $documentReferenceOptions = collect();

        if ($selected) {
            $selected->loadMissing(['userOne:id,name,role,is_active', 'userTwo:id,name,role,is_active']);
            $other = $selected->user_one_id === $user->getKey() ? $selected->userTwo : $selected->userOne;
            $selectedParticipant = [
                'name' => $other->name,
                'role' => config("shield.roles.{$other->role}.label", $other->role),
                'is_active' => $other->is_active,
            ];
            $canSend = $user->can('send', $selected);

            $messages = $selected->messages()
                ->with(['sender:id,name', ...ChatDocumentReferenceService::EAGER_LOADS])
                ->orderByDesc('id')
                ->limit(self::HISTORY_PAGE_SIZE + 1)
                ->get();
            $hasOlderMessages = $messages->count() > self::HISTORY_PAGE_SIZE;
            $messages = $messages->take(self::HISTORY_PAGE_SIZE)->reverse()->values();
            $messageReferences = $messages->mapWithKeys(fn ($message): array => [
                $message->getKey() => $this->documentReferences->present($message->documentReference, $user),
            ]);
            if ($canSend) {
                $documentReferenceOptions = $this->documentReferences->availableFor($selected, $user);
            }
        }

        /** @var View $view */
        $view = view('chat.index', [
            'layout' => $user->hasRole('admin', 'afp') ? 'layouts.skydash-h' : 'layouts.skydash-v',
            'conversationItems' => $conversationItems,
            'recipients' => $recipients,
            'selectedConversation' => $selected,
            'selectedParticipant' => $selectedParticipant,
            'messages' => $messages,
            'hasOlderMessages' => $hasOlderMessages,
            'canSend' => $canSend,
            'messageReferences' => $messageReferences,
            'documentReferenceOptions' => $documentReferenceOptions,
        ]);

        return response($view)->withHeaders($this->privateHeaders());
    }

    private function privateHeaders(): array
    {
        return ['Cache-Control' => 'private, no-store, no-cache, max-age=0', 'Pragma' => 'no-cache'];
    }

    private function privateJson(array $data): JsonResponse
    {
        return response()->json($data)->withHeaders($this->privateHeaders());
    }
}
