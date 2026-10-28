<?php

// Ouvre le code PHP du fichier.

namespace App\Models; // Place la classe dans l'espace de noms des modèles de l'application.

use Illuminate\Database\Eloquent\Attributes\Fillable; // Importe l'attribut qui définit les champs autorisés lors d'une affectation en masse.
use Illuminate\Database\Eloquent\Model; // Importe le modèle de base Eloquent, qui communique avec la base de données.
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Importe le type de relation reliant un message à son parent.

#[Fillable(['user_id', 'role', 'content'])] // Autorise ces champs dans create() et fill() ; leurs valeurs devront être contrôlées par le futur code d'envoi.
class Message extends Model // Définit le modèle Message ; Eloquent utilise par convention la table messages.
{
    // Début de la classe.
    /** @return BelongsTo<Conversation, $this> Relation vers la conversation parente, précisée pour l'analyse du code. */
    public function conversation(): BelongsTo // Déclare la méthode permettant de retrouver la conversation du message.
    { // Début de la méthode conversation().
        return $this->belongsTo(Conversation::class); // belongsTo() recherche la conversation à partir du champ conversation_id de ce message.
    } // Fin de la méthode conversation().

    /** @return BelongsTo<User, $this> Relation vers l'auteur humain, précisée pour l'analyse du code. */
    public function user(): BelongsTo // Déclare la méthode donnant accès au compte de l'auteur, s'il existe.
    { // Début de la méthode user().
        return $this->belongsTo(User::class); // belongsTo() utilise user_id ; l'accès à $message->user renvoie null pour une réponse IA sans compte.
    } // Fin de la méthode user().
} // Fin de la classe Message.
