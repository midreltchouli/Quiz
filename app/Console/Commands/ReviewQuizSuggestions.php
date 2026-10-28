<?php

namespace App\Console\Commands;

use App\Models\QuestionSuggestion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ReviewQuizSuggestions extends Command
{
    protected $signature = 'quiz:suggestions {action=list : list, show, approve ou reject} {id?} {--notes=}';

    protected $description = 'Consulter, publier ou refuser les propositions de questions après relecture';

    public function handle(): int
    {
        if (! Schema::hasTable('question_suggestions')) {
            $this->error('Lance d’abord php artisan migrate.');

            return self::FAILURE;
        }
        $action = $this->argument('action');
        if ($action === 'list') {
            $this->table(['ID', 'Catégorie', 'Question française', 'État'],
                QuestionSuggestion::latest('id')->limit(30)->get()->map(fn ($draft) => [
                    $draft->id, $draft->category, $draft->translations['fr']['question'], $draft->status,
                ])->all());

            return self::SUCCESS;
        }
        if (! in_array($action, ['show', 'approve', 'reject'], true)
            || ! ctype_digit((string) $this->argument('id'))) {
            $this->error('Utilisation : quiz:suggestions list ou quiz:suggestions show|approve|reject ID');

            return self::FAILURE;
        }
        $suggestion = QuestionSuggestion::find($this->argument('id'));
        if (! $suggestion) {
            $this->error('Proposition introuvable.');

            return self::FAILURE;
        }
        if ($action === 'show') {
            $this->line(json_encode($suggestion->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $this->warn('Vérifie la source et les trois traductions avant de publier. Une source présente ne garantit pas une réponse correcte.');

            return self::SUCCESS;
        }
        try {
            if ($action === 'approve') {
                $question = $suggestion->approve($this->option('notes'));
                $this->info('Question publiée : '.$question->id);
            } else {
                DB::transaction(function () use ($suggestion) {
                    $locked = QuestionSuggestion::whereKey($suggestion->id)->lockForUpdate()->firstOrFail();
                    if ($locked->status !== 'pending') {
                        throw new RuntimeException('Seule une proposition en attente peut être refusée.');
                    }
                    $locked->update([
                        'status' => 'rejected', 'review_notes' => $this->option('notes'), 'reviewed_at' => now(),
                    ]);
                });
                $this->info('Proposition refusée, conservée dans l’historique.');
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
