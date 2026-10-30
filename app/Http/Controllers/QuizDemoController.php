<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuizDemoController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        $request->session()->put('quiz_demo', [
            'ids' => Question::where('child_friendly', true)->inRandomOrder()->limit(5)->pluck('id')->all(),
            'index' => 0, 'score' => 0, 'review' => null,
        ]);

        return to_route('quiz.demo');
    }

    public function show(Request $request): View
    {
        $demo = $request->session()->get('quiz_demo');
        $question = null;
        if ($demo && ! $demo['review'] && isset($demo['ids'][$demo['index']])) {
            $question = Question::whereKey($demo['ids'][$demo['index']])->first()?->forLocale(app()->getLocale());
        }

        return view('demo', ['demo' => $demo, 'question' => $question]);
    }

    public function answer(Request $request): RedirectResponse
    {
        $demo = $request->session()->get('quiz_demo');
        if (! $demo || $demo['review'] || ! isset($demo['ids'][$demo['index']])) {
            return to_route('quiz.demo');
        }
        $question = Question::whereKey($demo['ids'][$demo['index']])->first()?->forLocale(app()->getLocale());
        if (! $question) {
            return to_route('quiz.demo');
        }
        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'answer' => ['required', 'string', Rule::in($question->options)],
        ]);
        if ((int) $data['question_id'] !== $question->id) {
            return to_route('quiz.demo');
        }
        $correct = $data['answer'] === $question->answer;
        $demo['score'] += (int) $correct;
        $demo['index']++;
        $demo['review'] = [
            'question' => $question->question, 'answer' => $question->answer, 'correct' => $correct,
            'explanation' => $question->translations[app()->getLocale()]['explanation'] ?? null,
        ];
        $request->session()->put('quiz_demo', $demo);

        return to_route('quiz.demo');
    }

    public function next(Request $request): RedirectResponse
    {
        if ($request->session()->has('quiz_demo')) {
            $request->session()->put('quiz_demo.review', null);
        }

        return to_route('quiz.demo');
    }
}
