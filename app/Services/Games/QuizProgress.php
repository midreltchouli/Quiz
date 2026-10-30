<?php

namespace App\Services\Games;

use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizProgress
{
    /** @param list<string> $names */
    public function start(Request $request, string $category, int $length, array $names, bool $daily = false): QuizAttempt
    {
        $attempt = DB::transaction(function () use ($request, $category, $length, $names, $daily) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $day = now()->toDateString();
            if ($daily) {
                $existing = QuizAttempt::where('user_id', $user->id)->whereDate('daily_date', $day)->first();
                if ($existing) {
                    return $existing;
                }
            }
            $ids = Question::inCategory($category)->pluck('id')->all();
            if ($daily) {
                // The first launch freezes today's selection, including if the bank changes later.
                $ids = collect($ids)->sortBy(fn ($id) => hash('sha256', $day.':'.$id))->take(5)->values()->all();
                if ($ids !== []) {
                    DB::table('daily_quizzes')->insertOrIgnore([
                        'day' => $day, 'question_ids' => json_encode($ids, JSON_THROW_ON_ERROR),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $stored = DB::table('daily_quizzes')->where('day', $day)->value('question_ids');
                    $ids = json_decode($stored, true, flags: JSON_THROW_ON_ERROR);
                }
            } else {
                shuffle($ids);
                $count = min(count($ids), $length * count($names));
                $ids = array_slice($ids, 0, intdiv($count, count($names)) * count($names));
            }
            if ($ids === []) {
                throw ValidationException::withMessages(['player_count' => __('Not enough questions in this category for this number of players.')]);
            }

            return QuizAttempt::create([
                'user_id' => $user->id, 'category' => $category, 'length' => $length,
                'daily_date' => $daily ? $day : null,
                'is_trial' => ! $daily && $user->quiz_questions_answered === 0
                    && ! QuizAttempt::where('user_id', $user->id)->where('is_trial', true)->exists(),
                'question_ids' => $ids, 'players' => array_map(fn ($name) => ['name' => $name, 'score' => 0, 'answered' => 0], $names),
                'answers' => [], 'score' => 0,
            ]);
        });
        $request->session()->forget(['quiz_review', 'resultat']);
        $this->restore($request, $attempt);

        return $attempt;
    }

    public function current(Request $request): ?QuizAttempt
    {
        if (! $request->session()->has('quiz_attempt_id')) {
            return null;
        }

        $attempt = QuizAttempt::where('user_id', $request->user()->id)->whereKey($request->session()->get('quiz_attempt_id'))->first();
        if (! $attempt) {
            $request->session()->forget(['quiz_attempt_id', 'quiz_question_ids', 'quiz_players', 'question_index', 'score', 'quiz_review']);
        }

        return $attempt;
    }

    public function restore(Request $request, QuizAttempt $attempt): void
    {
        $request->session()->put([
            'quiz_attempt_id' => $attempt->id, 'quiz_category' => $attempt->category,
            'quiz_length' => $attempt->length, 'quiz_question_ids' => $attempt->question_ids,
            'quiz_players' => $attempt->players, 'score' => $attempt->score,
            'question_index' => count($attempt->answers),
        ]);
    }

    public function canPlay(Request $request): bool
    {
        $attempt = $this->current($request);

        return ($attempt && ($attempt->is_trial || $attempt->daily_date !== null))
            || ! $request->user()->refresh()->hasReachedFreeQuizLimit();
    }

    /** Save a durable, localized snapshot so reviewed answers survive bank edits. */
    public function answer(Request $request, Question $question, string $chosen): bool
    {
        return DB::transaction(function () use ($request, $question, $chosen) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $attempt = QuizAttempt::where('user_id', $user->id)->whereKey($request->session()->get('quiz_attempt_id'))->lockForUpdate()->firstOrFail();
            $index = count($attempt->answers);
            if ($attempt->completed_at || ($attempt->question_ids[$index] ?? null) !== $question->id
                || (! $attempt->is_trial && ! $attempt->daily_date && $user->hasReachedFreeQuizLimit())) {
                $this->restore($request, $attempt);

                return false;
            }
            $correct = $chosen === $question->answer;
            $players = $attempt->players;
            $player = $index % count($players);
            $current = $players[$player];
            $current['answered']++;
            $current['score'] += (int) $correct;
            $players[$player] = $current;
            $answers = $attempt->answers;
            $locale = app()->getLocale();
            $answers[] = [
                'question_id' => $question->id, 'question' => $question->question,
                'chosen' => $chosen, 'answer' => $question->answer, 'correct' => $correct,
                'player' => $current['name'], 'locale' => $locale,
                'explanation' => $question->translations[$locale]['explanation'] ?? null,
                'source_url' => $question->translations[$locale]['source_url'] ?? $question->source_url,
            ];
            $attempt->update([
                'players' => $players, 'answers' => $answers,
                'score' => $attempt->score + (int) $correct,
                'completed_at' => count($answers) === count($attempt->question_ids) ? now() : null,
            ]);
            // Daily play is independent from the classic quiz's free allowance.
            if (! $attempt->daily_date) {
                $user->increment('quiz_questions_answered');
            }

            return true;
        });
    }
}
