<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Database\Seeders\QuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_and_data_require_login(): void
    {
        $this->get(route('preferences.edit'))->assertRedirect(route('login'));
        $this->get(route('preferences.export'))->assertRedirect(route('login'));
        $this->patch(route('preferences.update'), ['locale' => 'fr'])->assertRedirect(route('login'));
    }

    public function test_english_is_the_default_and_settings_are_separate_from_profile(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('preferences.edit'))->assertOk()->assertViewIs('settings')
            ->assertSee('lang="en"', false)->assertSee('General settings')
            ->assertSee('Account')->assertSee('Privacy')->assertSee('name="locale"', false)
            ->assertDontSee('name="password"', false);
        $this->get(route('profile.edit'))->assertOk()->assertSee('My profile')
            ->assertDontSee('Payment')->assertDontSee('name="locale"', false);
    }

    public function test_account_and_privacy_have_their_own_sections(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('preferences.edit', ['section' => 'account']))->assertOk()
            ->assertSee('Account details')->assertSee('Change password')
            ->assertDontSee('name="locale"', false);
        $this->get(route('preferences.edit', ['section' => 'privacy']))->assertOk()
            ->assertSee('Privacy and data protection')->assertSee('Download my account data')
            ->assertDontSee('name="name"', false);
    }

    public function test_language_is_saved_for_the_user_and_survives_logging_in_again(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('preferences.update'), ['locale' => 'fr'])
            ->assertSessionHasNoErrors()->assertRedirect(route('preferences.edit'));
        $this->assertSame('fr', $user->fresh()->locale);
        $this->get(route('preferences.edit'))->assertSee('Paramètres généraux')
            ->assertSee('lang="fr"', false);
        $this->get(route('accueil'))->assertSee('Bienvenue, '.$user->name);

        $this->post(route('quiz.logout'));
        $this->post(route('login.store'), [
            'name' => $user->name, 'email' => $user->email, 'password' => 'password',
        ])->assertRedirect(route('accueil'));
        $this->get(route('accueil'))->assertSee('lang="fr"', false)->assertSee('Les jeux');
    }

    public function test_german_and_returning_to_english_work_without_affecting_other_users(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($first)->patch(route('preferences.update'), ['locale' => 'de']);
        $this->get(route('preferences.edit'))->assertSee('Allgemeine Einstellungen')
            ->assertSee('Datenschutz')->assertSee('lang="de"', false);
        $this->actingAs($second)->get(route('accueil'))->assertSee('lang="en"', false);
        $this->actingAs($first)->patch(route('preferences.update'), ['locale' => 'en']);
        $this->get(route('preferences.edit'))->assertSee('General settings');
    }

    public function test_unsupported_languages_cannot_be_saved(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from(route('preferences.edit'))
            ->patch(route('preferences.update'), ['locale' => 'xx'])
            ->assertSessionHasErrors('locale');
        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_font_size_and_notification_preferences_are_saved_and_applied(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('preferences.update'), [
            'locale' => 'en', 'font_family' => 'georgia', 'text_size' => 'large', 'quiz_notifications' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertSame('georgia', $user->fresh()->font_family);
        $this->assertFalse($user->fresh()->quiz_notifications);
        foreach (['accueil', 'profile.edit', 'preferences.edit'] as $route) {
            $this->get(route($route))->assertSee('font-family: Georgia, serif; font-size: 18px;', false);
        }
        $this->seed(QuestionSeeder::class);
        $this->withSession(['score' => 9, 'question_index' => 0, 'resultat' => 'feedback-only'])
            ->get('/quiz')->assertDontSee('feedback-only')->assertSessionHas('score', 9);
        $this->patch(route('preferences.update'), ['locale' => 'en', 'quiz_notifications' => '1']);
        $this->withSession(['resultat' => 'feedback-only'])->get('/quiz')->assertSee('feedback-only');
    }

    public function test_invalid_font_and_text_size_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('preferences.update'), [
            'locale' => 'en', 'font_family' => 'invalid', 'text_size' => 'huge', 'quiz_notifications' => 'yes',
        ])->assertSessionHasErrors(['font_family', 'text_size', 'quiz_notifications']);
        $this->assertSame('system', $user->fresh()->font_family);
    }

    public function test_password_changes_return_to_account_settings(): void
    {
        $user = User::factory()->create();
        $account = route('preferences.edit', ['section' => 'account']);
        $this->actingAs($user)->from($account)->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors()->assertRedirect($account)
            ->assertSessionHas('status', 'password-updated');
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_export_contains_only_the_current_users_public_account_data(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $response = $this->actingAs($user)->get(route('preferences.export'));
        $response->assertOk()->assertDownload('account-data.json');
        $data = json_decode($response->streamedContent(), true);
        $this->assertSame($user->email, $data['email']);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('two_factor_secret', $data);
        $this->assertNotContains($other->email, $data);
    }
}
