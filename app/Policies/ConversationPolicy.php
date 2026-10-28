<?php

namespace App\Policies; // Regroupe les règles d'autorisation de l'application.

use App\Models\Conversation; // Importe le modèle de la conversation à protéger.
use App\Models\User; // Importe le modèle du compte qui fait la demande.

class ConversationPolicy // Laravel découvre cette classe grâce au nom du modèle Conversation.
{
    // Cette règle sert aussi bien à lire la conversation qu'à y envoyer un message.
    public function view(User $user, Conversation $conversation): bool // Renvoie vrai uniquement si le compte participe à cette conversation.
    {
        return $conversation->participants()->whereKey($user->id)->exists(); // whereKey() filtre par identifiant ; exists() vérifie l'association en base sans charger tous les participants.
    } // Fin de la règle d'accès.
} // Fin de la classe ConversationPolicy.
