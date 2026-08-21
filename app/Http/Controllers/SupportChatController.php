<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\SupportMessageCreated;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\SupportAiResponder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SupportChatController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $conversations = SupportConversation::query()->where('user_id', $request->user()->id)
            ->latest('last_message_at')->get();
        $conversation = $request->filled('conversation')
            ? $conversations->firstWhere('id', (int) $request->query('conversation'))
            : $conversations->first(fn (SupportConversation $item): bool => in_array($item->status, SupportConversation::OPEN_STATUSES, true))
                ?? $conversations->first();

        if ($request->expectsJson()) {
            return response()->json([
                'conversations' => $conversations->map(fn (SupportConversation $item) => $this->conversationPayload($item)),
                'conversation' => $conversation ? $this->conversationPayload($conversation->load('messages')) : null,
            ]);
        }

        return view('support.index', compact('conversations', 'conversation'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', SupportConversation::CATEGORIES)],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:4000'],
        ]);
        $conversation = SupportConversation::query()->create([
            'user_id' => $request->user()->id, 'category' => $data['category'],
            'subject' => $data['subject'] ?: null, 'status' => 'ai_active', 'last_message_at' => now(),
        ]);
        $this->addUserMessage($conversation, $request->user()->id, $data['message']);
        $this->replyWithAi($conversation, $data['message']);

        if ($request->expectsJson()) { return response()->json(['conversation' => $this->conversationPayload($conversation->load('messages'))], 201); }

        return redirect()->route('support.index', ['conversation' => $conversation->id]);
    }

    public function message(Request $request, SupportConversation $conversation): RedirectResponse|JsonResponse
    {
        abort_unless($conversation->isOwnedBy($request->user()), 403);
        abort_if($conversation->status === 'resolved', 422, 'Cuộc trò chuyện đã được đóng.');
        $data = $request->validate(['message' => ['required', 'string', 'max:4000']]);
        $this->addUserMessage($conversation, $request->user()->id, $data['message']);

        if ($conversation->status === 'ai_active') { $this->replyWithAi($conversation, $data['message']); }

        if ($request->expectsJson()) {
            $updatedConversation = SupportConversation::query()->findOrFail($conversation->id)->load('messages');

            return response()->json(['conversation' => $this->conversationPayload($updatedConversation)]);
        }

        return back();
    }

    private function addUserMessage(SupportConversation $conversation, int $userId, string $body): void
    {
        $message = $conversation->messages()->create(['sender_id' => $userId, 'sender_type' => 'user', 'body' => trim($body)]);
        $conversation->forceFill(['last_message_at' => now()])->save();
        SupportMessageCreated::dispatch($message);
    }

    private function replyWithAi(SupportConversation $conversation, string $prompt): void
    {
        $result = app(SupportAiResponder::class)->reply($conversation->category, $prompt);
        $message = $conversation->messages()->create(['sender_type' => 'ai', 'body' => $result['answer']]);
        $conversation->forceFill(['status' => $result['resolved'] ? 'ai_active' : 'waiting_admin', 'last_message_at' => now()])->save();
        SupportMessageCreated::dispatch($message);
    }

    /** @return array<string, mixed> */
    private function conversationPayload(SupportConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'category' => $conversation->category,
            'subject' => $conversation->subject,
            'status' => $conversation->status,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'messages' => $conversation->relationLoaded('messages') ? $conversation->messages->map(fn (SupportMessage $message) => [
                'id' => $message->id, 'sender_type' => $message->sender_type, 'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values() : [],
        ];
    }
}
