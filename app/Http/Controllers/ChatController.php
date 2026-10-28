<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Chat\AiReplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->conversations()
            ->with('participants:id,name')
            ->orderByDesc('conversations.updated_at')->orderByDesc('conversations.id')
            ->paginate(20));
    }

    // La recherche expose uniquement les noms publics, jamais les e-mails.
    public function recipients(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = trim($data['q'] ?? '');

        return response()->json(User::query()->whereKeyNot($request->user()->id)
            ->when($query !== '', fn ($users) => $users->where('name', 'like', '%'.addcslashes($query, '%_\\').'%'))
            ->orderBy('name')->orderBy('id')->paginate(20, ['id', 'name']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['human', 'ai'])],
            'recipient_id' => [
                'exclude_unless:type,human', 'required', 'integer',
                Rule::exists('users', 'id'), Rule::notIn([$request->user()->id]),
            ],
        ]);
        // Enregistre la conversation et ses participants ensemble, ou annule le tout.
        $conversation = DB::transaction(function () use ($request, $data) {
            $conversation = Conversation::create(['type' => $data['type']]);
            $participants = [$request->user()->id];
            if ($data['type'] === 'human') {
                $participants[] = $data['recipient_id'];
            }
            $conversation->participants()->attach($participants);

            return $conversation;
        });

        return response()->json(['conversation' => $conversation->load('participants:id,name')], 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);
        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0', 'prohibits:before_id'],
            'before_id' => ['nullable', 'integer', 'min:1'],
            'latest' => ['nullable', 'boolean'],
        ]);
        $query = $conversation->messages()->with('user:id,name');
        if (isset($data['after_id'])) {
            $query->where('id', '>', $data['after_id']);
        } elseif (isset($data['before_id'])) {
            $query->where('id', '<', $data['before_id'])->reorder('id', 'desc');
        } elseif ($request->boolean('latest')) {
            $query->reorder('id', 'desc');
        }
        $messages = $query->paginate(50);
        if (! isset($data['after_id']) && (isset($data['before_id']) || $request->boolean('latest'))) {
            $messages->setCollection($messages->getCollection()->reverse()->values());
        }

        return response()->json([
            'conversation' => $conversation->load('participants:id,name'),
            'messages' => $messages,
        ]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);
        $data = $request->validate([
            'content' => ['required', 'string', 'max:5000', 'regex:/\S/u'],
        ]);
        // Le même verrou protège l'envoi et la réponse IA entre plusieurs onglets.
        $lock = Cache::lock('chat:'.$conversation->id, 90);
        if (! $lock->get()) {
            return response()->json(['message' => __('Chat is busy. Please try again.')], 409);
        }
        try {
            if ($conversation->type === 'ai' && $conversation->messages()->reorder('id', 'desc')->first()?->role === 'user') {
                return response()->json(['message' => __('Retry the AI reply before sending another message.')], 409);
            }
            // L'identité et le rôle viennent du serveur, jamais du navigateur.
            $message = $conversation->messages()->create([
                'user_id' => $request->user()->id, 'role' => 'user', 'content' => $data['content'],
            ]);
            $conversation->touch();

            return response()->json(['message' => $message->load('user:id,name')], 201);
        } finally {
            $lock->release();
        }
    }

    // Réessayer l'IA ne renvoie pas la question et ne la duplique pas en base.
    public function reply(Request $request, Conversation $conversation, AiReplyService $ai): JsonResponse
    {
        Gate::authorize('view', $conversation);
        abort_unless($conversation->type === 'ai', 422);
        $data = $request->validate(['message_id' => ['required', 'integer', 'min:1']]);
        $lock = Cache::lock('chat:'.$conversation->id, 90);
        if (! $lock->get()) {
            return response()->json(['message' => __('Chat is busy. Please try again.')], 409);
        }
        try {
            $question = $conversation->messages()->findOrFail($data['message_id']);
            abort_unless($question->role === 'user', 422);
            $existing = $conversation->messages()->where('id', '>', $question->id)->first();
            if ($existing?->role === 'assistant') {
                return response()->json(['message' => $existing->load('user:id,name')]);
            }
            abort_if($existing !== null, 409);
            if (! $ai->available()) {
                return response()->json(['message' => __('AI is not configured yet. You can retry later.')], 503);
            }
            try {
                $content = $ai->generate($conversation);
            } catch (\Illuminate\Http\Client\ConnectionException|\RuntimeException $exception) {
                // Ne pas exposer la réponse du fournisseur, la clé ni le contenu privé.
                return response()->json(['message' => __('AI is unavailable. Your message is saved. Please retry.')], 503);
            }
            $message = $conversation->messages()->create(['role' => 'assistant', 'content' => $content]);
            $conversation->touch();

            return response()->json(['message' => $message->load('user:id,name')], 201);
        } finally {
            $lock->release();
        }
    }
}
