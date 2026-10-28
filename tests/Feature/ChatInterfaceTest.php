<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_contains_the_widget_and_only_public_recipient_data_is_exposed(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create()->all();
        $this->actingAs($alice)->get(route('accueil'))->assertOk()
            ->assertSee('id="chatbox-root"', false)->assertSee('js/chatbox.js');
        $this->getJson(route('chat.recipients', ['q' => $bob->name]))->assertOk()
            ->assertJsonPath('data.0.name', $bob->name)->assertJsonMissingPath('data.0.email');
        $this->getJson(route('chat.recipients'))->assertJsonCount(1, 'data');
    }

    public function test_latest_history_older_messages_and_incremental_updates_do_not_skip_messages(): void
    {
        [$user, $conversation] = $this->prepareConversation('human');
        $messages = collect();
        for ($index = 1; $index <= 55; $index++) {
            $messages->push($conversation->messages()->create([
                'user_id' => $user->id, 'role' => 'user', 'content' => 'Message '.$index,
            ]));
        }
        $url = route('chat.show', $conversation);
        $this->getJson($url.'?latest=1')->assertOk()->assertJsonCount(50, 'messages.data')
            ->assertJsonPath('messages.data.0.content', 'Message 6')
            ->assertJsonPath('messages.data.49.content', 'Message 55');
        $this->getJson($url.'?before_id='.$messages[5]->id)->assertOk()->assertJsonCount(5, 'messages.data')
            ->assertJsonPath('messages.data.0.content', 'Message 1');
        $this->getJson($url.'?after_id='.$messages[52]->id)->assertOk()->assertJsonCount(2, 'messages.data')
            ->assertJsonPath('messages.data.0.content', 'Message 54');
    }

    public function test_ai_reply_is_saved_once_with_bounded_context_and_no_user_identity_sent_to_provider(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.model' => 'test-model']);
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Bonjour !']]]],
        ])]);
        [$user, $conversation] = $this->prepareConversation();
        // Le service doit garder les vingt derniers messages dans le bon ordre.
        for ($index = 0; $index < 12; $index++) {
            $conversation->messages()->create(['user_id' => $user->id, 'role' => 'user', 'content' => 'Question '.$index]);
            $conversation->messages()->create(['role' => 'assistant', 'content' => 'Réponse '.$index]);
        }
        $sent = $this->postJson(route('chat.send', $conversation), ['content' => 'Bonjour'])->assertCreated();
        $payload = ['message_id' => $sent->json('message.id')];
        $this->postJson(route('chat.reply', $conversation), $payload)->assertCreated()
            ->assertJsonPath('message.role', 'assistant')->assertJsonPath('message.user_id', null)
            ->assertJsonPath('message.content', 'Bonjour !');
        $this->postJson(route('chat.reply', $conversation), $payload)->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/responses'
            && $request['store'] === false && count($request['input']) === 20
            && $request['input'][0]['content'] === 'Réponse 2'
            && $request['input'][19]['content'] === 'Bonjour'
            && ! isset($request['input'][19]['user_id']));
        $this->assertDatabaseCount('messages', 26);
    }

    public function test_ai_failure_keeps_the_question_and_retry_does_not_duplicate_it(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => 'test-key', 'services.openai.model' => 'test-model']);
        Http::fakeSequence()->push(['error' => ['message' => 'private provider detail']], 500)
            ->push(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Réponse']]]]]);
        [, $conversation] = $this->prepareConversation();
        $sent = $this->postJson(route('chat.send', $conversation), ['content' => 'Question'])->assertCreated();
        $payload = ['message_id' => $sent->json('message.id')];
        $this->postJson(route('chat.reply', $conversation), $payload)->assertStatus(503)->assertDontSee('private provider detail');
        $this->assertDatabaseCount('messages', 1);
        $this->postJson(route('chat.send', $conversation), ['content' => 'Autre question'])->assertConflict();
        $this->postJson(route('chat.reply', $conversation), $payload)->assertCreated();
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_missing_ai_configuration_never_calls_the_provider(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => null, 'services.openai.model' => null]);
        [, $conversation] = $this->prepareConversation();
        $sent = $this->postJson(route('chat.send', $conversation), ['content' => 'Question'])->assertCreated();
        $this->postJson(route('chat.reply', $conversation), ['message_id' => $sent->json('message.id')])->assertStatus(503);
        Http::assertNothingSent();
        $this->assertDatabaseCount('messages', 1);
    }

    public function test_reply_and_recipients_require_authentication_and_reply_requires_membership(): void
    {
        $this->getJson(route('chat.recipients'))->assertUnauthorized();
        $conversation = Conversation::create(['type' => 'ai']);
        $this->postJson(route('chat.reply', $conversation), ['message_id' => 1])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson(route('chat.reply', $conversation), ['message_id' => 1])->assertForbidden();
    }

    public function test_generation_lock_prevents_concurrent_sends_and_replies(): void
    {
        Http::preventStrayRequests();
        [, $conversation] = $this->prepareConversation();
        $lock = Cache::lock('chat:'.$conversation->id, 90);
        $this->assertTrue($lock->get());
        try {
            $this->postJson(route('chat.send', $conversation), ['content' => 'Question'])->assertConflict();
            $this->postJson(route('chat.reply', $conversation), ['message_id' => 1])->assertConflict();
            $this->assertDatabaseCount('messages', 0);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    /** @return array{User, Conversation} */
    private function prepareConversation(string $type = 'ai'): array
    {
        $user = User::factory()->create();
        $conversation = Conversation::create(['type' => $type]);
        $conversation->participants()->attach($user);
        $this->actingAs($user);

        return [$user, $conversation];
    }
}
