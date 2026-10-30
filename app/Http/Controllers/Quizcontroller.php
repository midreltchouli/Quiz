<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\User;
use App\Services\Games\QuizCategories;
use App\Services\Games\QuizProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class Quizcontroller extends Controller
{
    public function index(Request $request): View
    {
        $progress = app(QuizProgress::class);
        $attempt = $progress->current($request);
        if ($attempt) {
            $progress->restore($request, $attempt);
        }
        if (! Question::exists()) {
            $request->session()->forget(['question_index', 'score', 'quiz_question_ids', 'quiz_review']);

            return view('quiz_empty');
        }
        // Keep solo games started before player selection was introduced.
        if (! $request->session()->has('quiz_players') && $request->session()->has('question_index')) {
            $request->session()->put('quiz_players', [[
                'name' => $request->user()->name,
                'score' => $request->session()->get('score', 0),
                'answered' => $request->session()->get('question_index', 0),
            ]]);
        }
        $players = $request->session()->get('quiz_players', []);
        $questionIds = $this->questionIds($request, max(1, count($players)));
        $index = $request->session()->get('question_index', 0);
        $review = $this->reviewData($request);
        if ($players !== [] && $index >= count($questionIds)) {
            return view('quiz_finish', ['score' => $request->session()->get('score'), 'total' => count($questionIds), 'players' => $players, 'review' => $review, 'attempt' => $attempt]);
        }
        if (! $progress->canPlay($request)) {
            return view('quiz_upgrade', [
                'review' => $review,
                'limit' => config('billing.free_question_limit'),
                'price' => number_format(config('billing.amount') / 100, 2, ',', ' ').' '.strtoupper(config('billing.currency')),
                'paymentReady' => filled(config('billing.stripe_secret')) && filled(config('billing.stripe_webhook_secret')),
            ]);
        }

        if ($players === []) {
            return view('games.players', ['gameType' => 'quiz', 'quizCategories' => QuizCategories::choices()]);
        }

        $question = Question::where('id', $questionIds[$index])->first();
        if (! $question) {
            return view('quiz_empty');
        }
        $question = $question->forLocale(app()->getLocale());

        return view('quiz', ['question' => $question, 'number' => $index + 1, 'total' => count($questionIds), 'players' => $players, 'currentPlayer' => $index % count($players), 'review' => $review]);
    }

    public function continue(Request $request): RedirectResponse
    {
        $request->session()->forget(['quiz_review', 'resultat']);

        return redirect()->route('quiz');
    }

    public function restart(Request $request): RedirectResponse
    {
        $progress = app(QuizProgress::class);
        $attempt = $progress->current($request);
        if ($attempt) {
            if ($attempt->daily_date) {
                return redirect()->route('accueil');
            }
            $progress->start($request, $attempt->category, $attempt->length, array_column($attempt->players, 'name'));

            return redirect()->route('quiz');
        }
        $request->session()->forget(['question_index', 'score', 'resultat', 'quiz_question_ids', 'quiz_review']);
        $players = $request->session()->get('quiz_players', []);
        if ($players !== []) {
            foreach ($players as &$player) {
                $player['score'] = 0;
                $player['answered'] = 0;
            }
            unset($player);
            $request->session()->put(['quiz_players' => $players, 'question_index' => 0, 'score' => 0]);
        }

        return redirect()->route('quiz');
    }

    public function verified(Request $request): RedirectResponse
    {
        $progress = app(QuizProgress::class);
        $attempt = $progress->current($request);
        if ($attempt) {
            $progress->restore($request, $attempt);
        }
        $players = $request->session()->get('quiz_players', []);
        if ($players === []) {
            return redirect()->route('quiz');
        }
        if (! $progress->canPlay($request)) {
            return redirect()->route('quiz');
        }
        $index = $request->session()->get('question_index', 0);
        $questionIds = $this->questionIds($request, count($players));
        if ($index >= count($questionIds)) {
            return redirect()->route('quiz');
        }
        $question = Question::where('id', $questionIds[$index])->first();
        if (! $question) {
            return redirect()->route('quiz');
        }
        $question = $question->forLocale(app()->getLocale());
        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'reponse' => ['required', 'string', Rule::in($question->options)],
        ]);
        if ((int) $validated['question_id'] !== $question->id) {
            return redirect()->route('quiz');
        }
        $accepted = $attempt ? $progress->answer($request, $question, $validated['reponse']) : DB::transaction(function () use ($request) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($user->hasReachedFreeQuizLimit()) {
                return false;
            }
            $user->increment('quiz_questions_answered');

            return true;
        });
        if (! $accepted) {
            return redirect()->route('quiz');
        }
        $correct = $validated['reponse'] === $question->answer;
        $currentPlayer = $index % count($players);
        $players[$currentPlayer]['answered']++;
        if ($correct) {
            $players[$currentPlayer]['score']++;
        }
        $request->session()->put('quiz_players', $players);
        if ($correct) {
            $request->session()->increment('score');
        }
        $request->session()->increment('question_index');

        $feedback = $correct
            ? __('The answer is correct.')
            : __('The answer is incorrect. The correct answer is: :answer', ['answer' => $question->answer]);
        $explanation = $question->translations[app()->getLocale()]['explanation'] ?? null;
        if ($explanation) {
            $feedback .= ' '.$explanation;
        }
        if (($request->user()->quiz_notifications ?? true) && ($explanation || $question->source_url)) {
            $request->session()->put('quiz_review', [
                'question_id' => $question->id, 'correct' => $correct,
                'player_name' => $players[$currentPlayer]['name'],
                'number' => $index + 1, 'total' => count($questionIds),
            ]);
        } else {
            $request->session()->forget('quiz_review');
        }

        return redirect()->route('quiz')->with('resultat', $players[$currentPlayer]['name'].' : '.$feedback);
    }

    /** @return array<string, mixed>|null */
    private function reviewData(Request $request): ?array
    {
        $saved = $request->session()->get('quiz_review');
        if (! is_array($saved) || ! ($request->user()->quiz_notifications ?? true)) {
            return null;
        }
        $question = Question::where('id', $saved['question_id'])->first();
        if (! $question) {
            $request->session()->forget('quiz_review');

            return null;
        }
        $locale = app()->getLocale();
        $source = $question->translations[$locale]['source_url'] ?? $question->source_url;
        $safeSource = is_string($source) && filter_var($source, FILTER_VALIDATE_URL)
            && parse_url($source, PHP_URL_SCHEME) === 'https';

        return $saved + [
            'question' => $question->forLocale($locale),
            'explanation' => $question->translations[$locale]['explanation'] ?? null,
            'source_url' => $safeSource ? $source : null,
            'source_name' => $safeSource ? parse_url($source, PHP_URL_HOST) : null,
        ];
    }

    /** @return list<int> */
    private function questionIds(Request $request, int $playerCount): array
    {
        if (! $request->session()->has('quiz_question_ids')) {
            // Preserve the order of older games already in progress.
            $query = Question::inCategory($request->session()->get('quiz_category', 'general'));
            if ($request->session()->get('question_index', 0) > 0) {
                $query->orderBy('id');
            } else {
                $query->inRandomOrder();
            }
            $ids = $query->pluck('id')->all();
            // Complete rounds give every participant the same number of questions.
            $ids = array_slice($ids, 0, intdiv(count($ids), $playerCount) * $playerCount);
            $request->session()->put('quiz_question_ids', $ids);
        }

        return $request->session()->get('quiz_question_ids');
    }
}
