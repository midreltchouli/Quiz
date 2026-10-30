<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $category
 * @property int $length
 * @property int $score
 * @property bool $is_trial
 * @property list<int> $question_ids
 * @property list<array{name: string, score: int, answered: int}> $players
 * @property list<array{question_id: int, question: string, chosen: string, answer: string, correct: bool, player: string, locale: string, explanation: string|null, source_url: string|null}> $answers
 * @property Carbon|null $daily_date
 * @property Carbon|null $completed_at
 */
class QuizAttempt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'daily_date' => 'date', 'completed_at' => 'datetime',
            'is_trial' => 'boolean', 'length' => 'integer', 'score' => 'integer',
            'question_ids' => 'array', 'players' => 'array', 'answers' => 'array',
        ];
    }
}
