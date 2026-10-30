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
        $output = $response->json('output', []);
        if (! is_array($output)) {
            throw new RuntimeException('AI returned invalid output.');
        }
        $parts = [];
        foreach ($output as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message' || ! is_array($item['content'] ?? null)) {
                continue;
            }
            foreach ($item['content'] as $content) {
                if (is_array($content) && ($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $parts[] = $content['text'];
                }
            }
        }
        $text = implode("\n", $parts);
        if (trim($text) === '') {
            throw new RuntimeException('AI returned no text.');
        }

        return $text;
    }
}
