<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiReplyService
{
    public function available(): bool
    {
        return filled(config('services.openai.key')) && filled(config('services.openai.model'));
    }

    public function generate(Conversation $conversation): string
    {
        // Limite le contexte aux vingt derniers messages, remis dans leur ordre d'origine.
        $input = $conversation->messages()->reorder('id', 'desc')->limit(20)->get()
            ->reverse()->values()->map(fn ($message) => [
                'role' => $message->role, 'content' => $message->content,
            ])->all();
        $response = Http::withToken(config('services.openai.key'))
            ->acceptJson()->connectTimeout(5)->timeout(45)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model'),
                'instructions' => 'Tu es un assistant pédagogique dans un espace de jeux et de quiz. Réponds dans la langue de la personne, de façon claire et concise. Tu ne peux pas agir sur son compte ni connaître les autres conversations.',
                'input' => $input,
                'store' => false,
                'max_output_tokens' => 1200,
            ]);
        if (! $response->successful() || $response->json('status') !== 'completed') {
            throw new RuntimeException('AI response failed.');
        }
        $text = collect($response->json('output', []))
            ->where('type', 'message')->flatMap(fn ($item) => $item['content'] ?? [])
            ->where('type', 'output_text')->pluck('text')->implode("\n");
        if (trim($text) === '') {
            throw new RuntimeException('AI returned no text.');
        }

        return $text;
    }
}
