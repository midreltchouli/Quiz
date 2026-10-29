<?php

namespace App\Console\Commands;

use App\Services\Quiz\QuestionEnrichmentAgent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class SuggestQuizQuestions extends Command
{
    protected $signature = 'quiz:suggest {category=science} {--count=5} {--difficulty=medium}';

    protected $description = 'Préparer 1 à 5 brouillons multilingues avec Mistral, sans publication automatique';

    public function handle(QuestionEnrichmentAgent $agent): int
    {
        if (! Schema::hasTable('question_suggestions')) {
            $this->error('Lance d’abord php artisan migrate pour créer la table des propositions.');

            return self::FAILURE;
        }
        $count = filter_var($this->option('count'), FILTER_VALIDATE_INT);
        if ($count === false || $count < 1 || $count > 5) {
            $this->error('--count doit être un entier entre 1 et 5.');

            return self::FAILURE;
        }
        $this->info('Lancement manuel : 5 tours maximum, avec 2 nouvelles tentatives au total uniquement après HTTP 429 (7 appels maximum).');
        try {
            $suggestions = $agent->generate(
                $this->argument('category'), $count, $this->option('difficulty'),
                fn (int $retry, int $delay) => $this->warn("Mistral répond HTTP 429. Nouvelle tentative {$retry}/2 dans {$delay} secondes…")
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Échec de la préparation. Vérifie la connexion, la configuration et les migrations. Aucun brouillon de ce lancement enregistré.');

            return self::FAILURE;
        }
        $this->table(['ID', 'Catégorie', 'Question française', 'État'], array_map(fn ($draft) => [
            $draft->id, $draft->category, $draft->translations['fr']['question'], $draft->status,
        ], $suggestions));
        $this->info(count($suggestions).' proposition(s) enregistrée(s). Aucune question publiée.');
        if (count($suggestions) < $count) {
            $this->warn('Le lot est incomplet : les propositions invalides ou en double ont été écartées.');
        }
        $this->line('Pour relire : php artisan quiz:suggestions show ID');

        return self::SUCCESS;
    }
}
