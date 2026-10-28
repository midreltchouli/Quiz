<?php

// Ouvre le code PHP de la migration.

use Illuminate\Database\Migrations\Migration; // Importe la classe de base d'une migration, c'est-à-dire une modification versionnée de la base.
use Illuminate\Database\Schema\Blueprint; // Importe l'objet permettant de décrire les colonnes, les clés et les index d'une table.
use Illuminate\Support\Facades\Schema; // Importe l'outil Laravel qui crée et supprime les tables.

return new class extends Migration // Renvoie une instance de classe anonyme héritant de Migration, que Laravel pourra exécuter.
{
    // Début de la classe de migration.
    public function up(): void // Laravel appelle up() pour appliquer la migration ; void signifie qu'elle ne renvoie aucune valeur.
    { // Début de l'application de la migration.
        Schema::create('conversations', function (Blueprint $table) { // create() crée la table ; la fonction anonyme reçoit sa structure à définir.
            $table->id(); // id() crée une clé primaire entière générée automatiquement pour identifier chaque conversation.
            $table->enum('type', ['human', 'ai']); // enum() limite le type de conversation aux valeurs human (personnes) et ai (IA).
            $table->timestamps(); // timestamps() ajoute created_at et updated_at, gérés automatiquement lors des sauvegardes Eloquent.
        }); // Termine la définition et la création de la table conversations.

        Schema::create('conversation_user', function (Blueprint $table) { // Crée la table d'association reliant les utilisateurs à leurs conversations.
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete(); // foreignId() crée la colonne ; constrained() référence conversations.id ; cascadeOnDelete() supprime l'association si la conversation disparaît.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Crée la référence vers users.id et supprime l'association si cet utilisateur est supprimé.
            $table->timestamps(); // Ajoute les dates de création et de modification de l'association.
            $table->primary(['conversation_id', 'user_id']); // primary() crée une clé composée empêchant d'inscrire deux fois le même utilisateur dans une conversation.
            $table->index('user_id'); // index() facilite la recherche des conversations d'un utilisateur.
        }); // Termine la création de la table des participants.

        Schema::create('messages', function (Blueprint $table) { // Crée la table qui conserve le contenu des échanges.
            $table->id(); // Crée l'identifiant unique et automatique de chaque message.
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete(); // Relie le message à conversations.id et le supprime si sa conversation est supprimée.
            // Une réponse IA n'a pas de compte utilisateur ; les messages humains restent conservés après suppression de leur auteur.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // nullable() autorise l'absence d'auteur ; constrained() référence users.id ; nullOnDelete() met cette référence à null si le compte disparaît.
            $table->enum('role', ['user', 'assistant']); // Définit si le message vient d'une personne ou de l'IA ; le rôle reste conservé même sans compte auteur.
            $table->text('content'); // text() crée une colonne destinée au texte du message.
            $table->timestamps(); // Ajoute les dates de création et de modification du message.
            $table->index(['conversation_id', 'id']); // Crée un index composé pour retrouver les messages d'une conversation dans l'ordre des identifiants.
        }); // Termine la création de la table messages.
    } // Fin de la méthode up().

    public function down(): void // Laravel appelle down() pour annuler cette migration.
    { // Début de l'annulation ; les tables dépendantes doivent être supprimées avant conversations.
        Schema::dropIfExists('messages'); // dropIfExists() supprime la table messages si elle existe, ainsi que ses données.
        Schema::dropIfExists('conversation_user'); // Supprime ensuite la table des associations entre utilisateurs et conversations.
        Schema::dropIfExists('conversations'); // Supprime enfin la table des conversations, maintenant qu'aucune de ces tables ne la référence.
    } // Fin de la méthode down().
}; // Termine la classe anonyme et l'instruction return.
