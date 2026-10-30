<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class QuestionExplanationSeeder extends Seeder
{
    public function run(): void
    {
        $bank = File::json(database_path('seeders/data/history-explanations.json'), JSON_THROW_ON_ERROR);
        $challenges = File::json(database_path('seeders/data/challenge-questions.json'), JSON_THROW_ON_ERROR);
        foreach ($challenges['questions'] as $entry) {
            if ($entry['category'] === 'math') {
                $bank['questions'][$entry['key']] = [
                    'explanations' => array_map(fn ($translation) => $translation['explanation'], $entry['translations']),
                ];
            }
        }
        DB::transaction(function () use ($bank) {
            foreach ($bank['questions'] as $key => $entry) {
                $question = Question::where('bank_key', $key)->first();
                if (! $question) {
                    continue;
                }
                $translations = $question->translations ?? [];
                foreach ($entry['explanations'] as $locale => $explanation) {
                    // Preserve the existing question, answer, choices and other metadata.
                    if (isset($translations[$locale])) {
                        $translations[$locale]['explanation'] = $explanation;
                        if (isset($entry['source_urls'][$locale])) {
                            $translations[$locale]['source_url'] = $entry['source_urls'][$locale];
                        }
                    }
                }
                $question->fill(['translations' => $translations]);
                if (isset($entry['source_url'])) {
                    $question->source_url = $entry['source_url'];
                }
                if ($question->isDirty()) {
                    $question->save();
                }
            }
        });
    }
}
