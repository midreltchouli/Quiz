<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\QuestionSuggestion;
use App\Services\Quiz\QuestionEnrichmentAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use RuntimeException;
use Tests\TestCase;

class QuestionEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.quiz_enrichment.provider' => 'mistral',
            'services.mistral.key' => 'test-key', 'services.mistral.model' => 'mistral-small-latest',
            'services.openai.key' => 'test-openai-key', 'services.openai.quiz_model' => 'test-openai-model',
        ]);
        Http::preventStrayRequests();
        Sleep::fake();
    }

    protected function tearDown(): void
    {
        Sleep::fake(false);
        parent::tearDown();
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

    private function fakeAgent(?array $entry = null, ?ResponseSequence $sequence = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $entry ??= $this->entry();
        if ($sequence === null) {
            $sequence = Http::sequence()->push($this->calls([
                'search_references' => ['query' => 'oxygen atomic number'],
                'find_similar_questions' => ['query' => 'oxygen'],
            ]));
            for ($i = 0; $i < 5; $i++) {
                $sequence->push($this->calls(['submit_suggestions' => ['suggestions' => [$entry]]]));
            }
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

    private function openAiCalls(array $tools, bool $withReasoning = false): array
    {
        $output = $withReasoning ? [[
            'id' => 'rs_example', 'type' => 'reasoning', 'summary' => [], 'encrypted_content' => 'opaque-reasoning-state',
        ]] : [];
        foreach ($tools as $name => $args) {
            $output[] = [
                'id' => 'fc_'.$name, 'type' => 'function_call', 'call_id' => 'call_'.$name,
                'name' => $name, 'arguments' => json_encode($args, JSON_THROW_ON_ERROR),
                'status' => 'completed',
            ];
        }

        return ['id' => 'resp_example', 'status' => 'completed', 'output' => $output];
    }

    private function fakeOpenAi(?ResponseSequence $sequence = null): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        if ($sequence === null) {
            $sequence = Http::sequence()->push($this->openAiCalls([
                'search_references' => ['query' => 'oxygen atomic number'],
                'find_similar_questions' => ['query' => 'oxygen'],
            ], withReasoning: true));
            for ($i = 0; $i < 5; $i++) {
                $sequence->push($this->openAiCalls(['submit_suggestions' => ['suggestions' => [$this->entry()]]]));
            }
        }
        Http::fake([
            'api.openai.com/v1/responses' => $sequence,
            'en.wikipedia.org/w/api.php*' => Http::response(['query' => ['pages' => [
                '1' => ['title' => 'Oxygen', 'fullurl' => 'https://en.wikipedia.org/wiki/Oxygen', 'extract' => 'Oxygen is a chemical element with atomic number 8.'],
            ]]]),
        ]);
    }

    public function test_openai_uses_responses_and_replays_reasoning_and_tool_results_without_publishing(): void
    {
        config(['services.mistral.key' => null]);
        $this->fakeOpenAi();
        $this->artisan('quiz:suggest science --count=1 --provider=openai')->assertSuccessful();

        $draft = QuestionSuggestion::sole();
        $this->assertSame('openai', $draft->provider);
        $this->assertSame('test-openai-model', $draft->model);
        $this->assertSame($this->entry()['translations'], $draft->translations);
        $this->assertDatabaseCount('questions', 0);
        $requests = Http::recorded(fn ($request) => str_contains($request->url(), 'api.openai.com'))->values();
        $this->assertCount(2, $requests);
        $first = $requests[0][0];
        $this->assertTrue($first->hasHeader('Authorization', 'Bearer test-openai-key'));
        $this->assertFalse($first['store']);
        $this->assertSame(['reasoning.encrypted_content'], $first['include']);
        $this->assertArrayNotHasKey('temperature', $first->data());
        $this->assertTrue($first['tools'][0]['strict']);
        $this->assertFalse($first['tools'][0]['parameters']['additionalProperties']);
        $translationSchema = $first['tools'][2]['parameters']['properties']['suggestions']['items']['properties']['translations'];
        $this->assertFalse($translationSchema['properties']['fr']['additionalProperties']);
        $input = $requests[1][0]['input'];
        $this->assertSame('reasoning', $input[1]['type']);
        $this->assertSame('opaque-reasoning-state', $input[1]['encrypted_content']);
        $this->assertSame('call_search_references', $input[2]['call_id']);
        $this->assertSame('function_call_output', $input[4]['type']);
        $this->assertSame('call_search_references', $input[4]['call_id']);
        $this->assertSame('https://en.wikipedia.org/wiki/Oxygen', json_decode($input[4]['output'], true, flags: JSON_THROW_ON_ERROR)[0]['url']);
        Http::assertSentCount(3);
        $this->artisan('quiz:suggestions approve '.$draft->id)->assertSuccessful();
        $this->assertSame('openai:'.$draft->fingerprint, Question::sole()->bank_key);
    }

    public function test_default_provider_can_be_openai(): void
    {
        config(['services.quiz_enrichment.provider' => 'openai']);
        $this->fakeOpenAi();
        $this->artisan('quiz:suggest science --count=1')->assertSuccessful();
        $this->assertSame('openai', QuestionSuggestion::sole()->provider);
    }

    public function test_explicit_provider_overrides_the_configured_default(): void
    {
        config(['services.quiz_enrichment.provider' => 'openai']);
        $this->fakeAgent();
        $this->artisan('quiz:suggest science --count=1 --provider=mistral')->assertSuccessful();
        $this->assertSame('mistral', QuestionSuggestion::sole()->provider);
    }

    public function test_invalid_provider_and_missing_openai_settings_do_not_fall_back_to_mistral(): void
    {
        $this->artisan('quiz:suggest science --provider=unknown')->assertFailed();
        config(['services.openai.key' => null]);
        $this->artisan('quiz:suggest science --provider=openai')
            ->expectsOutputToContain('OPENAI_API_KEY')->assertFailed();
        config(['services.openai.key' => 'test-openai-key', 'services.openai.quiz_model' => null, 'services.openai.model' => null]);
        $this->artisan('quiz:suggest science --provider=openai')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_openai_reuses_the_chat_model_when_no_quiz_model_is_configured(): void
    {
        config(['services.openai.quiz_model' => '', 'services.openai.model' => 'existing-chat-model']);
        $this->fakeOpenAi();
        $this->artisan('quiz:suggest science --count=1 --provider=openai')->assertSuccessful();
        $this->assertSame('existing-chat-model', QuestionSuggestion::sole()->model);
    }

    public function test_openai_quota_exhaustion_stops_without_retries_or_other_provider_calls(): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'error' => ['code' => 'insufficient_quota', 'message' => 'Example quota failure'],
        ], 429)]);
        $this->artisan('quiz:suggest science --count=1 --provider=openai')
            ->expectsOutputToContain('Crédits ou plafond API OpenAI insuffisants')->assertFailed();
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
        $this->assertDatabaseCount('question_suggestions', 0);
    }

    public function test_openai_transient_rate_limit_retries_the_same_input(): void
    {
        $this->fakeOpenAi(Http::sequence()->push(['error' => ['code' => 'rate_limit_exceeded']], 429)
            ->push($this->openAiCalls(['search_references' => ['query' => 'oxygen atomic number']]))
            ->push($this->openAiCalls(['submit_suggestions' => ['suggestions' => [$this->entry()]]])));
        $this->artisan('quiz:suggest science --count=1 --provider=openai')
            ->expectsOutputToContain('OpenAI répond HTTP 429')->assertSuccessful();
        Sleep::assertSequence([Sleep::for(5)->seconds()]);
        Http::assertSentCount(4);
        $requests = Http::recorded(fn ($request) => str_contains($request->url(), 'api.openai.com'))->values();
        $this->assertSame($requests[0][0]->data(), $requests[1][0]->data());
    }

    public function test_incomplete_openai_output_is_not_saved(): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'status' => 'incomplete', 'output' => [], 'incomplete_details' => ['reason' => 'max_output_tokens'],
        ])]);
        $this->artisan('quiz:suggest science --count=1 --provider=openai')
            ->expectsOutputToContain('Réponse OpenAI incomplète ou refusée')->assertFailed();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('question_suggestions', 0);
    }

    public function test_duplicates_are_rejected_across_providers(): void
    {
        $this->fakeAgent();
        $this->artisan('quiz:suggest science --count=1 --provider=mistral')->assertSuccessful();
        $this->fakeOpenAi();
        $this->artisan('quiz:suggest science --count=1 --provider=openai')->assertFailed();
        $this->assertDatabaseCount('question_suggestions', 1);
        $this->assertSame('mistral', QuestionSuggestion::sole()->provider);
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

    public function test_persistent_rate_limits_discard_staged_drafts_after_bounded_retries(): void
    {
        $sequence = Http::sequence()
            ->push($this->calls(['search_references' => ['query' => 'oxygen atomic number']]))
            ->push($this->calls(['submit_suggestions' => ['suggestions' => [$this->entry()]]]))
            ->push(['error' => 'quota reached'], 429)
            ->push(['error' => 'quota reached'], 429)
            ->push(['error' => 'quota reached'], 429);
        $this->fakeAgent(sequence: $sequence);
        $this->artisan('quiz:suggest science --count=2')->assertFailed();
        $this->assertDatabaseCount('question_suggestions', 0);
        $this->assertDatabaseCount('questions', 0);
        Http::assertSentCount(6);
        Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(10)->seconds()]);
    }

    public function test_rate_limit_error_stops_after_two_retries_and_displays_each_wait(): void
    {
        Http::fake(['api.mistral.ai/v1/chat/completions' => Http::response([
            'message' => 'Rate limit exceeded', 'type' => 'rate_limited', 'code' => '1300',
        ], 429)]);
        $this->artisan('quiz:suggest science --count=1')
            ->expectsOutputToContain('Nouvelle tentative 1/2 dans 5 secondes')
            ->expectsOutputToContain('Nouvelle tentative 2/2 dans 10 secondes')
            ->expectsOutputToContain('Mistral refuse encore l’appel (HTTP 429)')
            ->assertFailed();
        Http::assertSentCount(3);
        Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(10)->seconds()]);
        $this->assertDatabaseCount('question_suggestions', 0);
    }

    public function test_invalid_key_is_distinguished_from_rate_limits_without_exposing_it(): void
    {
        Http::fake(['api.mistral.ai/v1/chat/completions' => Http::response([
            'message' => 'Invalid key test-key',
        ], 401)]);
        $this->artisan('quiz:suggest science --count=1')
            ->expectsOutputToContain('Clé Mistral refusée (HTTP 401)')->assertFailed();
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
        $this->assertDatabaseCount('question_suggestions', 0);
    }

    public function test_retry_recovers_without_restarting_or_duplicating_the_conversation(): void
    {
        $this->fakeAgent(sequence: Http::sequence()
            ->push([], 429)
            ->push($this->calls(['search_references' => ['query' => 'oxygen atomic number']]))
            ->push([], 429)
            ->push($this->calls(['submit_suggestions' => ['suggestions' => [$this->entry()]]])));

        $this->artisan('quiz:suggest science --count=1')->assertSuccessful();

        $requests = Http::recorded(fn ($request) => str_contains($request->url(), 'api.mistral.ai'))
            ->map(fn ($pair) => $pair[0]->data())->values();
        $this->assertSame($requests[0], $requests[1]);
        $this->assertSame($requests[2], $requests[3]);
        $this->assertGreaterThan(count($requests[0]['messages']), count($requests[2]['messages']));
        $this->assertDatabaseCount('question_suggestions', 1);
        $this->assertDatabaseCount('questions', 0);
        Http::assertSentCount(5);
        Sleep::assertSequence([Sleep::for(5)->seconds(), Sleep::for(10)->seconds()]);
    }

    public function test_retry_budget_is_shared_between_all_agent_rounds(): void
    {
        $this->fakeAgent(sequence: Http::sequence()
            ->push([], 429)->push([], 429)
            ->push($this->calls(['search_references' => ['query' => 'oxygen atomic number']]))
            ->push([], 429));

        $this->artisan('quiz:suggest science --count=1')->assertFailed();
        Http::assertSentCount(5);
        Sleep::assertSleptTimes(2);
        $this->assertDatabaseCount('question_suggestions', 0);
    }

    public function test_retry_after_is_respected_when_within_the_wait_limit(): void
    {
        $this->fakeAgent(sequence: Http::sequence()
            ->push([], 429, ['Retry-After' => '12'])
            ->push($this->calls(['search_references' => ['query' => 'oxygen atomic number']]))
            ->push($this->calls(['submit_suggestions' => ['suggestions' => [$this->entry()]]])));

        $this->artisan('quiz:suggest science --count=1')->assertSuccessful();
        Sleep::assertSequence([Sleep::for(12)->seconds()]);
        Http::assertSentCount(4);
    }

    public function test_excessive_retry_after_stops_instead_of_retrying_too_early(): void
    {
        Http::fake(['api.mistral.ai/v1/chat/completions' => Http::response([], 429, ['Retry-After' => '90'])]);
        $this->artisan('quiz:suggest science --count=1')->assertFailed();
        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    public function test_other_provider_errors_are_not_retried(): void
    {
        foreach ([400, 403, 500] as $status) {
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(['api.mistral.ai/v1/chat/completions' => Http::response([], $status)]);
            $this->artisan('quiz:suggest science --count=1')->assertFailed();
            Http::assertSentCount(1);
        }
        Sleep::assertNeverSlept();
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
