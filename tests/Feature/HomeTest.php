<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_must_log_in_to_access_the_home_page(): void
    {
        $this->get(route('accueil'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_see_their_games_and_a_profile_link(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Camille']));

        $this->get(route('home'))->assertRedirect(route('accueil'));
        $this->get(route('accueil'))->assertOk()->assertViewIs('home')
            ->assertSee('Camille')->assertSee(route('quiz'), false)
            ->assertSee(route('profile.edit'), false)
            ->assertSee('id="account-toggle"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('Settings')
            ->assertDontSee('>Mon profil</a>', false)
            ->assertDontSee('id="paiement"', false)
            ->assertDontSee('Access to all games');
    }

    public function test_login_lands_on_home_even_after_attempting_to_visit_a_game(): void
    {
        $user = User::factory()->create();
        $this->get('/quiz')->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('accueil'))->assertSessionMissing('url.intended');
    }

    public function test_registration_lands_on_home(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Camille',
            'email' => 'camille@example.com',
            'password' => 'password123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('accueil'));

        $this->assertAuthenticated();
    }
}
