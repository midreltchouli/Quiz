<?php

namespace Tests\Feature; // Place ces tests parmi les tests fonctionnels de l'application.

use App\Models\Conversation; // Importe le modèle pour préparer les conversations de test.
use App\Models\User; // Importe le modèle pour créer des comptes fictifs.
use Illuminate\Foundation\Testing\RefreshDatabase; // Importe le mécanisme qui isole les données des tests.
use Tests\TestCase; // Importe la classe permettant de tester les requêtes HTTP Laravel.

class ChatMessagingTest extends TestCase // Regroupe les vérifications du fonctionnement et de la confidentialité du chat.
{
    use RefreshDatabase; // Prépare une base de test isolée ; les comptes réels ne sont pas modifiés.

    public function test_guest_cannot_use_any_chat_endpoint(): void // Vérifie que toutes les entrées du chat exigent une authentification.
    {
        $conversation = Conversation::create(['type' => 'ai']); // Prépare une conversation dont l'identifiant existe.
        $this->getJson(route('chat.index'))->assertUnauthorized(); // getJson() simule une lecture JSON ; assertUnauthorized() exige le statut 401.
        $this->postJson(route('chat.store'), ['type' => 'ai'])->assertUnauthorized(); // Vérifie qu'un visiteur ne peut pas créer de conversation.
        $this->getJson(route('chat.show', $conversation))->assertUnauthorized(); // Vérifie qu'un visiteur ne peut pas lire l'historique.
        $this->postJson(route('chat.send', $conversation), ['content' => 'Bonjour'])->assertUnauthorized(); // Vérifie qu'un visiteur ne peut pas envoyer de message.
    } // Fin du test des visiteurs.

    public function test_human_and_ai_conversations_have_the_expected_participants(): void // Vérifie que le serveur choisit correctement les participants pour chaque mode.
    {
        [$alice, $bob] = User::factory()->count(2)->create()->all(); // Crée deux comptes fictifs et les affecte à deux variables.
        $human = $this->actingAs($alice)->postJson(route('chat.store'), ['type' => 'human', 'recipient_id' => $bob->id]); // actingAs() connecte Alice ; postJson() simule la création d'une discussion avec Bob.
        $human->assertCreated()->assertJsonPath('conversation.type', 'human'); // Vérifie le statut 201 et le type de conversation renvoyé.
        $this->assertDatabaseHas('conversation_user', ['conversation_id' => $human->json('conversation.id'), 'user_id' => $alice->id]); // Vérifie que le créateur est participant dans la base.
        $this->assertDatabaseHas('conversation_user', ['conversation_id' => $human->json('conversation.id'), 'user_id' => $bob->id]); // Vérifie que le destinataire est également participant.
        $ai = $this->postJson(route('chat.store'), ['type' => 'ai', 'recipient_id' => $bob->id]); // Envoie volontairement un destinataire dans une demande de conversation IA.
        $ai->assertCreated(); // Vérifie que la conversation IA peut être créée.
        $this->assertSame([$alice->id], Conversation::findOrFail($ai->json('conversation.id'))->participants->pluck('id')->all()); // findOrFail() relit la conversation ; pluck() extrait les identifiants ; seul Alice doit participer.
    } // Fin du test des deux modes.

    public function test_invalid_conversation_requests_do_not_create_records(): void // Vérifie que les créations incorrectes sont rejetées avant toute écriture.
    {
        $user = User::factory()->create(); // Crée le compte connecté utilisé pour les demandes invalides.
        $this->actingAs($user); // Connecte ce compte pour les requêtes suivantes.
        foreach ([['type' => 'unknown'], ['type' => 'human'], ['type' => 'human', 'recipient_id' => $user->id], ['type' => 'human', 'recipient_id' => 999999]] as $data) { // Teste un type invalide, un destinataire absent, soi-même et un compte inexistant.
            $this->postJson(route('chat.store'), $data)->assertUnprocessable(); // assertUnprocessable() exige le statut 422 de validation.
        } // Fin des cas incorrects.
        $this->assertDatabaseCount('conversations', 0); // Vérifie qu'aucune conversation partielle n'a été enregistrée.
    } // Fin du test de validation des conversations.

    public function test_outsider_cannot_list_read_or_write_another_conversation(): void // Vérifie les droits de lecture et d'écriture d'un compte non participant.
    {
        [$member, $outsider] = User::factory()->count(2)->create()->all(); // Crée un participant et un compte extérieur.
        $conversation = Conversation::create(['type' => 'ai']); // Crée une conversation privée avec l'IA.
        $conversation->participants()->attach($member); // Inscrit uniquement le participant autorisé.
        $this->actingAs($outsider)->getJson(route('chat.index'))->assertOk()->assertJsonCount(0, 'data'); // assertJsonCount() vérifie que la liste du compte extérieur ne contient pas cette conversation.
        $this->getJson(route('chat.show', $conversation))->assertForbidden(); // assertForbidden() exige le statut 403 lorsque le compte devine l'URL privée.
        $this->postJson(route('chat.send', $conversation), ['content' => 'Intrusion'])->assertForbidden(); // Vérifie que le même compte ne peut pas écrire dans la conversation.
        $this->assertDatabaseCount('messages', 0); // Confirme que la tentative interdite n'a enregistré aucun message.
    } // Fin du test des autorisations.

    public function test_sender_and_role_are_fixed_by_server_and_recipient_can_read(): void // Vérifie l'identité de l'expéditeur et la lecture par le deuxième participant.
    {
        [$alice, $bob] = User::factory()->count(2)->create()->all(); // Crée les deux comptes de la discussion.
        $conversation = Conversation::create(['type' => 'human']); // Prépare une conversation entre personnes.
        $conversation->participants()->attach([$alice->id, $bob->id]); // Autorise Alice et Bob à participer.
        $this->actingAs($alice)->postJson(route('chat.send', $conversation), [ // Envoie une demande contenant volontairement un faux auteur et un faux rôle.
            'content' => 'Bonjour Bob', 'user_id' => $bob->id, 'role' => 'assistant', // Tente de se faire passer pour Bob ou pour l'IA.
        ])->assertCreated()->assertJsonPath('message.user_id', $alice->id)->assertJsonPath('message.role', 'user'); // Vérifie que le serveur impose malgré tout Alice et le rôle humain.
        $this->actingAs($bob)->getJson(route('chat.show', $conversation)) // Simule la lecture par le destinataire.
            ->assertOk()->assertJsonPath('messages.data.0.content', 'Bonjour Bob') // Vérifie que le contenu envoyé est bien accessible à Bob.
            ->assertJsonPath('messages.data.0.user.name', $alice->name) // Vérifie que le nom de l'auteur est disponible pour l'affichage.
            ->assertJsonMissingPath('messages.data.0.user.email'); // Vérifie que la réponse n'expose pas l'adresse électronique de l'auteur.
        $this->assertDatabaseCount('messages', 1); // Confirme qu'un seul message a été créé.
    } // Fin du test de l'identité et de la lecture.

    public function test_empty_and_oversized_messages_are_rejected(): void // Vérifie les limites du texte accepté.
    {
        $user = User::factory()->create(); // Crée un utilisateur de test.
        $conversation = Conversation::create(['type' => 'ai']); // Prépare une conversation acceptant les messages de cet utilisateur.
        $conversation->participants()->attach($user); // Inscrit le compte dans la conversation.
        $this->actingAs($user); // Connecte le participant pour les requêtes suivantes.
        foreach (['', '   ', str_repeat('a', 5001)] as $content) { // str_repeat() produit un texte de 5 001 caractères ; les autres cas sont vides ou composés d'espaces.
            $this->postJson(route('chat.send', $conversation), ['content' => $content])->assertUnprocessable(); // Vérifie que chaque contenu invalide reçoit une erreur 422.
        } // Fin des contenus incorrects.
        $this->assertDatabaseCount('messages', 0); // Vérifie qu'aucun message invalide n'est conservé.
    } // Fin du test de validation du texte.

    public function test_history_is_paginated_and_preserves_ai_role(): void // Vérifie la limite de chargement et la présence des réponses IA dans l'historique.
    {
        $user = User::factory()->create(); // Crée le participant de la conversation IA.
        $conversation = Conversation::create(['type' => 'ai']); // Crée une conversation de type IA.
        $conversation->participants()->attach($user); // Autorise ce compte à consulter la conversation.
        for ($index = 0; $index < 51; $index++) { // Prépare suffisamment de messages pour dépasser une page de cinquante éléments.
            $conversation->messages()->create(['role' => 'assistant', 'content' => 'Réponse de test']); // Enregistre une réponse fictive sans appeler de service d'IA.
        } // Fin de la préparation de l'historique.
        $this->actingAs($user)->getJson(route('chat.show', $conversation)) // Demande la première page de l'historique.
            ->assertOk()->assertJsonCount(50, 'messages.data')->assertJsonPath('messages.total', 51) // Vérifie que la réponse reste limitée à cinquante messages sur cinquante et un.
            ->assertJsonPath('messages.data.0.role', 'assistant')->assertJsonPath('messages.data.0.user', null); // Vérifie que la réponse IA garde son rôle et n'a pas de compte auteur.
    } // Fin du test de pagination.
} // Fin de la classe ChatMessagingTest.
