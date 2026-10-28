<?php

// Ouvre le code PHP du fichier de tests.

namespace Tests\Feature; // Place les tests dans l'espace de noms des tests fonctionnels.

use App\Models\Conversation; // Importe le modèle des conversations à tester.
use App\Models\User; // Importe le modèle permettant de créer les utilisateurs de test.
use Illuminate\Database\QueryException; // Importe l'exception attendue lorsqu'une requête viole une contrainte de la base.
use Illuminate\Foundation\Testing\RefreshDatabase; // Importe le trait Laravel qui prépare et isole la base de test.
use Tests\TestCase; // Importe la classe de base qui démarre l'application pour les tests.

class ChatStorageTest extends TestCase // Définit la classe regroupant les tests de stockage du chat.
{
    // Début de la classe de tests.
    use RefreshDatabase; // Active la préparation de la base et l'isolation des données entre les tests.

    public function test_human_conversation_keeps_participants_and_message_order(): void // Teste les participants et l'ordre des messages dans une discussion humaine.
    { // Début du premier test.
        [$alice, $bob, $outsider] = User::factory()->count(3)->create()->all(); // factory() prépare des données fictives ; count(3) choisit leur nombre ; create() enregistre les comptes ; all() renvoie un tableau réparti dans trois variables.
        $conversation = Conversation::create(['type' => 'human']); // create() enregistre une conversation humaine et renvoie le modèle créé.
        $conversation->participants()->attach([$alice->id, $bob->id]); // attach() inscrit Alice et Bob dans la table d'association conversation_user.
        $conversation->messages()->create(['user_id' => $alice->id, 'role' => 'user', 'content' => 'Bonjour']); // create() sur la relation enregistre le message d'Alice avec le conversation_id automatiquement renseigné.
        $conversation->messages()->create(['user_id' => $bob->id, 'role' => 'user', 'content' => 'Salut']); // Enregistre de la même manière la réponse de Bob dans cette conversation.

        $this->assertCount(2, $conversation->participants); // assertCount() vérifie que la collection chargée via la relation contient exactement deux participants.
        $this->assertTrue($alice->conversations->contains($conversation)); // contains() cherche ce modèle dans la collection ; assertTrue() vérifie que le résultat est vrai.
        $this->assertCount(0, $outsider->conversations); // Vérifie que le troisième compte n'a aucune association avec une conversation ; ce n'est pas encore un test d'autorisation HTTP.
        $this->assertSame(['Bonjour', 'Salut'], $conversation->messages->pluck('content')->all()); // pluck() extrait les contenus ; all() les transforme en tableau ; assertSame() compare les valeurs, les types et l'ordre.
        $this->assertTrue($conversation->messages->first()->user->is($alice)); // first() prend le premier message ; user charge son auteur ; is() compare l'identité des modèles Eloquent.
        $this->assertTrue($conversation->messages->first()->conversation->is($conversation)); // Vérifie que la relation conversation du premier message retrouve bien la conversation créée.
    } // Fin du premier test.

    public function test_ai_reply_has_no_user_and_history_survives_user_deletion(): void // Teste le stockage d'une réponse IA et le comportement lors des suppressions.
    { // Début du deuxième test.
        $user = User::factory()->create(); // Crée et enregistre un utilisateur fictif dans la base de test.
        $conversation = Conversation::create(['type' => 'ai']); // Crée une conversation destinée aux échanges avec une IA.
        $conversation->participants()->attach($user); // attach() accepte aussi un modèle et utilise son identifiant pour l'inscrire comme participant.
        $question = $conversation->messages()->create(['user_id' => $user->id, 'role' => 'user', 'content' => 'Aide-moi']); // Enregistre la question humaine et conserve le modèle obtenu dans $question.
        $reply = $conversation->messages()->create(['role' => 'assistant', 'content' => 'Quelle est ta question ?']); // Enregistre une réponse IA fictive sans user_id ; aucun service d'IA n'est appelé dans ce test.

        $this->assertNull($reply->user); // assertNull() vérifie que la réponse de l'IA n'est associée à aucun compte utilisateur.
        $user->delete(); // delete() supprime le compte ; les contraintes de la base détachent le participant et mettent l'auteur de ses messages à null.
        $this->assertNull($question->fresh()->user_id); // fresh() relit le message depuis la base ; vérifie que la référence vers le compte supprimé est devenue null.
        $this->assertSame('user', $question->fresh()->role); // Vérifie que le rôle humain reste conservé après la suppression du compte.
        $this->assertCount(2, $conversation->messages); // Vérifie que les deux messages restent accessibles malgré la suppression de l'utilisateur.

        $conversation->delete(); // Supprime la conversation ; la suppression en cascade doit aussi supprimer ses messages.
        $this->assertDatabaseCount('messages', 0); // assertDatabaseCount() vérifie directement que la table messages est vide.
        $this->assertDatabaseCount('conversation_user', 0); // Vérifie que la table des participants ne contient plus d'association.
    } // Fin du deuxième test.

    public function test_same_participant_cannot_be_added_twice(): void // Teste la contrainte qui interdit de dupliquer un participant dans une conversation.
    { // Début du troisième test.
        $user = User::factory()->create(); // Crée un compte fictif pour ce test.
        $conversation = Conversation::create(['type' => 'human']); // Crée une conversation humaine dans la base de test.
        $conversation->participants()->attach($user); // Ajoute une première fois l'utilisateur à la conversation.

        $this->expectException(QueryException::class); // expectException() indique que l'opération suivante doit provoquer une erreur SQL encapsulée par Laravel.
        $conversation->participants()->attach($user); // Tente une seconde inscription identique : la clé primaire composée doit refuser ce doublon.
    } // Fin du troisième test.
} // Fin de la classe ChatStorageTest.
