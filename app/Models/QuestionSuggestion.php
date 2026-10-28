<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @property array<string, array{question: string, options: list<string>, answer: string}> $translations
 */
class QuestionSuggestion extends Model
{
    protected $fillable = [
        'fingerprint', 'category', 'difficulty', 'child_friendly', 'translations',
        'source_url', 'source_excerpt', 'model', 'status', 'review_notes', 'reviewed_at', 'question_id',
    ];

    protected function casts(): array
    {
        return ['translations' => 'array', 'child_friendly' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    public static function normalize(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text))));
    }

    public static function alreadyPlayable(string $text): bool
    {
        $normalized = self::normalize($text);

        return Question::select(['id', 'question', 'translations'])->lazyById()->contains(
            fn (Question $question) => self::normalize($question->question) === $normalized
                || self::normalize($question->translations['en']['question'] ?? '') === $normalized
        );
    }

    public function approve(?string $notes = null): Question
    {
        return DB::transaction(function () use ($notes) {
            $suggestion = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
            if ($suggestion->status === 'approved') {
                return Question::findOrFail($suggestion->question_id);
            }
            if ($suggestion->status !== 'pending') {
                throw new RuntimeException('Cette proposition a déjà été refusée.');
            }
            $english = $suggestion->translations['en'];
            if (self::alreadyPlayable($english['question'])) {
                throw new RuntimeException('Cette question existe déjà dans le quiz.');
            }
            $question = Question::create([
                'bank_key' => 'mistral:'.$suggestion->fingerprint,
                'question' => $english['question'],
                'options' => $english['options'],
                'answer' => $english['answer'],
                'translations' => $suggestion->translations,
                'category' => $suggestion->category,
                'difficulty' => $suggestion->difficulty,
                'child_friendly' => $suggestion->child_friendly,
                'source_url' => $suggestion->source_url,
            ]);
            $suggestion->update([
                'status' => 'approved', 'question_id' => $question->id,
                'reviewed_at' => now(), 'review_notes' => $notes,
            ]);

            return $question;
        });
    }
}
