<?php

namespace App\Services\Games;

use App\Models\Question;

class QuizCategories
{
    public static function choices(): array
    {
        $counts = Question::inCategory('general')->selectRaw('category, COUNT(*) AS total')->groupBy('category')->pluck('total', 'category');
        $total = Question::inCategory('general')->count();
        $children = Question::where('child_friendly', true)->count();
        $choices = [];

        foreach (config('quiz.categories') as $key => $definition) {
            $count = match ($key) {
                'general' => $total,
                'kids' => $children,
                default => (int) ($counts[$key] ?? 0),
            };
            $choices[$key] = $definition + ['count' => $count];
        }

        return $choices;
    }

    public static function label(string $key): string
    {
        return __(config('quiz.categories.'.$key.'.label', 'General knowledge'));
    }
}
