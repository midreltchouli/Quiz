<?php

// Ouvre le code PHP du fichier.

namespace App\Models; // Place cette classe dans l'espace de noms des modèles de l'application.

use Illuminate\Database\Eloquent\Attributes\Fillable; // Importe l'attribut qui autorise certains champs lors d'une affectation en masse.
use Illuminate\Database\Eloquent\Model; // Importe la classe Eloquent qui permet de lire et d'écrire dans la base.
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Importe le type de relation plusieurs-à-plusieurs.
use Illuminate\Database\Eloquent\Relations\HasMany; // Importe le type de relation un-à-plusieurs.

#[Fillable(['type'])] // Autorise create() et fill() à renseigner le champ type ; ce n'est pas une validation de sa valeur.
class Conversation extends Model // Définit le modèle Conversation ; Eloquent utilise par convention la table conversations.
{
    // Début de la classe.
    /** @return BelongsToMany<User, $this> Relation vers des utilisateurs, précisée pour l'analyse du code. */
    public function participants(): BelongsToMany // Déclare une méthode publique qui renvoie la relation avec les participants.
    { // Début de la méthode participants().
        return $this->belongsToMany(User::class)->withTimestamps(); // belongsToMany() passe par conversation_user ; withTimestamps() gère les dates de cette association.
    } // Fin de la méthode participants().

    /** @return HasMany<Message, $this> Relation vers plusieurs messages, précisée pour l'analyse du code. */
    public function messages(): HasMany // Déclare la relation donnant accès aux messages de cette conversation.
    { // Début de la méthode messages().
        return $this->hasMany(Message::class)->orderBy('id'); // hasMany() utilise conversation_id ; orderBy() trie les messages par identifiant croissant.
    } // Fin de la méthode messages().
} // Fin de la classe Conversation.
