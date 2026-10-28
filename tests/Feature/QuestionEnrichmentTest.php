<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionSuggestion;
use App\Services\Quiz\QuestionEnrichmentAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class QuestionEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.mistral.key' => 'test-key', 'services.mistral.model' => 'mistral-small-latest']);
        Http::preventStrayRequests();
    }

    private function entry(): array
    {
        return [
            'source_url' => 'https://en.wikipedia.org/wiki/Oxygen',
            'child_friendly' => false,
            'translations' => [
                'en' => ['question' => 'Which element has atomic number 8?', 'options' => ['Carbon', 'Oxygen', 'Iron', 'Gold'], 'answer' => 'Oxygen'],
                'fr' => ['question' => 'Quel élément a le numéro atomique 8 ?', 'options' => ['Carbone', 'Oxygène', 'Fer', 'Or'], 'answer' => 'Oxygène'],
                'de' => ['question' => 'Welches Element hat die Ordnungszahl 8?', 'options' => ['Kohlenstoff', 'Sauerstoff', 'Eisen', 'Gold'], 'answer' => 'Sauerstoff'],
            ],
        ];
    }

    private function calls(array $tools): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => array_map(
            fn ($name, $args) => [
                'id' => 'call_'.substr(hash('sha256', $name), 0, 8), 'type' => 'function',
                'function' => ['name' => $name, 'arguments' => json_encode($args, JSON_THROW_ON_ERROR)],
            ], array_keys($tools), array_values($tools)
        )]]]];
    }

    private function fakeAgent(?array $entry = null): void
    {
        $entry ??= $this->entry();
        $sequence = Http::sequence()->push($this->calls([
            'search_references' => ['query' => 'oxygen atomic number'],
            'find_similar_questions' => ['query' => 'oxygen'],
        ]));
        for ($i = 0; $i < 5; $i++) {
            $sequence->push($this->calls(['submit_suggestions' => ['suggestions' => [$entry]]]));
        }
        Http::fake([
            'api.mistral.ai/v1/chat/completions' => $sequence,
            'en.wikipedia.org/w/api.php*' => Http::response(['query' => ['pages' => [
                '1' => ['title' => 'Oxygen', 'fullurl' => 'https://en.wikipedia.org/wiki/Oxygen', 'extract' => 'Oxygen is a chemical element with atomic number 8.'],
            ]]]),
        ]);
    }

    private function assertGenerationFails(): void
    {
        try {
            app(QuestionEnrichmentAgent::class)->generate('science', 1);
            $this->fail('Invalid proposals must not be saved.');
        } catch (RuntimeException $exception) {
            $this->assertDatabaseCount('question_suggestions', 0);
        }
    }

    public function test_manual_command_saves_a_sourced_trilingual_draft_without_publishing(): void
    {
        $this->fakeAgent();
        $this->artisan('quiz:suggest science --count=1')->assertSuccessful();
        $draft = QuestionSuggestion::sole();
        $this->assertSame('pending', $draft->status);
        $this->assertSame($this->entry()['translations'], $draft->translations);
        $this->assertSame('Oxygen is a chemical element with atomic number 8.', $draft->source_excerpt);
        $this->assertDatabaseCount('questions', 0);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.mistral.ai')
            && $request['max_tokens'] === 6000 && count($request['tools']) === 3);
        Http::assertSentCount(3);
    }

    public function test_missing_key_and_invalid_counts_do_not_call_any_provider(): void
    {
        config(['services.mistral.key' => null]);
        $this->artisan('quiz:suggest science --count=1')->assertFailed();
        $this->artisan('quiz:suggest science --count=6')->assertFailed();
        $this->artisan('quiz:suggest science --count=0')->assertFailed();
        $this->artisan('quiz:suggest science --count=invalid')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_invalid_category_and_difficulty_do_not_call_any_provider(): void
    {
        $this->artisan('quiz:suggest general --count=1')->assertFailed();
        $this->artisan('quiz:suggest science --difficulty=invalid')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_source_must_have_been_returned_by_the_reference_tool(): void
    {
        $this->fakeAgent(array_replace($this->entry(), ['source_url' => 'https://example.com/invented']));
        $this->assertGenerationFails();
        Http::assertSentCount(6); // Five Mistral calls and one reference search.
    }

    public function test_all_languages_and_four_distinct_choices_are_required(): void
    {
        $entry = $this->entry();
        unset($entry['translations']['de']);
        $this->fakeAgent($entry);
        $this->assertGenerationFails();

        $entry = $this->entry();
        $entry['translations']['fr']['options'] = ['Oxygène', 'Oxygène', 'Fer', 'Or'];
        $this->fakeAgent($entry);
        $this->assertGenerationFails();
    }

    public function test_correct_answer_must_exist_at_the_same_index_in_each_language(): void
    {
        $entry = $this->entry();
        $entry['translations']['fr']['answer'] = 'Inconnu';
        $this->fakeAgent($entry);
        $this->assertGenerationFails();

        $entry = $this->entry();
        $entry['translations']['fr']['options'] = ['Oxygène', 'Carbone', 'Fer', 'Or'];
        $this->fakeAgent($entry);
        $this->assertGenerationFails();
    }

    public function test_normalized_duplicates_of_playable_questions_are_rejected(): void
    {
        $english = $this->entry()['translations']['en'];
        Question::create(array_replace($english, ['question' => 'WHICH ELEMENT HAS ATOMIC NUMBER 8 !', 'category' => 'science']));
        $this->fakeAgent();
        $this->assertGenerationFails();
        $this->assertDatabaseCount('questions', 1);
    }

    public function test_existing_drafts_are_not_duplicated(): void
    {
        $this->fakeAgent();
        app(QuestionEnrichmentAgent::class)->generate('science', 1);
        $this->fakeAgent();
        try {
            app(QuestionEnrichmentAgent::class)->generate('science', 1);
            $this->fail('Existing draft must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertDatabaseCount('question_suggestions', 1);
        }
    }

    public function test_provider_failure_discards_staged_drafts_without_retrying(): void
    {
        $this->fakeAgent();
        $sequence = Http::sequence()
            ->push($this->calls(['search_references' => ['query' => 'oxygen atomic number']]))
            ->push($this->calls(['submit_suggestions' => ['suggestions' => [$this->entry()]]]))
            ->push(['error' => 'quota reached'], 429);
        Http::fake(['api.mistral.ai/v1/chat/completions' => $sequence]);
        $this->artisan('quiz:suggest science --count=2')->assertFailed();
        $this->assertDatabaseCount('question_suggestions', 0);
        $this->assertDatabaseCount('questions', 0);
        Http::assertSentCount(4);
    }

    public function test_unknown_tools_cannot_write_or_publish_questions(): void
    {
        Http::fake(['api.mistral.ai/v1/chat/completions' => Http::response($this->calls([
            'publish_question' => $this->entry(),
        ]))]);
        $this->assertGenerationFails();
        $this->assertDatabaseCount('questions', 0);
        Http::assertSentCount(5);
    }

    public function test_approval_publishes_once_with_the_original_source_and_translations(): void
    {
        $this->fakeAgent();
        app(QuestionEnrichmentAgent::class)->generate('science', 1);
        $draft = QuestionSuggestion::sole();
        $this->artisan('quiz:suggestions show '.$draft->id)->assertSuccessful();
        $this->artisan('quiz:suggestions approve '.$draft->id)->assertSuccessful();
        $this->artisan('quiz:suggestions approve '.$draft->id)->assertSuccessful();
        $this->assertDatabaseCount('questions', 1);
        $question = Question::sole();
        $this->assertSame($draft->translations, $question->translations);
        $this->assertSame($draft->source_url, $question->source_url);
        $this->assertSame('medium', $question->difficulty);
        $this->assertSame('approved', $draft->fresh()->status);
        Http::assertSentCount(3);
    }

    public function test_rejected_proposals_remain_in_history_and_cannot_be_published(): void
    {
        $this->fakeAgent();
        app(QuestionEnrichmentAgent::class)->generate('science', 1);
        $draft = QuestionSuggestion::sole();
        $this->artisan('quiz:suggestions reject '.$draft->id.' --notes="Source insuffisante"')->assertSuccessful();
        $this->artisan('quiz:suggestions approve '.$draft->id)->assertFailed();
        $this->assertDatabaseCount('questions', 0);
        $this->assertSame('rejected', $draft->fresh()->status);
        $this->assertSame('Source insuffisante', $draft->fresh()->review_notes);
        $this->artisan('quiz:suggestions list')->assertSuccessful();
        $this->artisan('quiz:suggestions show 999')->assertFailed();
    }
}
