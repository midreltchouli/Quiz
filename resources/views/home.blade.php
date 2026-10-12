<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Play area</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, sans-serif; background: #f4f4f8; color: #202039; }
        a { color: #4f46e5; }
        .container { max-width: 1040px; margin: auto; padding: 24px; }
        header, nav { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        header { justify-content: space-between; padding-bottom: 24px; border-bottom: 1px solid #e0e0e5; }
        .brand { font-weight: 800; font-size: 1.2rem; color: #202039; text-decoration: none; }
        .button { display: inline-block; background: #4f46e5; color: white; border: 0; border-radius: 8px; padding: 12px 20px; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .button:hover { background: #4338ca; }
        .secondary { background: white; color: #4f46e5; border: 1px solid #dedcf4; }
        .secondary:hover { background: #eef0ff; }
        a:focus-visible, button:focus-visible { outline: 3px solid #818cf8; outline-offset: 4px; }
        .hero { padding: 48px 0 32px; }
        .eyebrow { color: #4f46e5; font-size: .85rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
        h1 { font-size: clamp(1.8rem, 5vw, 2.8rem); margin: 12px 0; }
        h2 { font-size: 1.4rem; }
        p { color: #62627a; line-height: 1.6; }
        .games { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 20px; }
        .card { padding: 28px; background: white; border: 1px solid #e0e0e5; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,.03); }
        .card h3 { margin: 16px 0 8px; font-size: 1.2rem; }
        .badge { display: inline-block; padding: 5px 10px; background: #eef0ff; color: #4f46e5; border-radius: 20px; font-size: .8rem; }
        .payment { margin-top: 36px; scroll-margin-top: 24px; }
        .payment h2 { margin-top: 0; }
        .offers { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 16px; margin: 24px 0; }
        .offer { display: flex; flex-direction: column; padding: 22px; border: 1px solid #e0e0e5; border-radius: 12px; }
        .offer h3 { margin: 16px 0 8px; }
        .offer p { margin-top: 0; }
        .offer ul { padding-left: 20px; color: #62627a; line-height: 1.7; margin: 0 0 20px; }
        .offer .price { margin-top: auto; font-weight: 600; color: #202039; }
        .button:disabled { background: #e9e9f2; color: #68687c; cursor: not-allowed; }
        .notice { padding: 18px; border-radius: 10px; background: #f4f4f8; }
        footer { margin-top: 32px; color: #77778b; font-size: .85rem; }
        @media (max-width: 600px) { .container { padding: 18px; } .hero { padding-top: 32px; } nav { gap: 10px; } }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <a class="brand" href="{{ route('accueil') }}">Espace jeux</a>
            <nav aria-label="Navigation principale">
                <a href="#jeux">Les jeux</a>
                <a class="button" href="#paiement">Paiement</a>
                <form method="POST" action="{{ route('quiz.logout') }}">
                    @csrf
                    <button class="button secondary" type="submit">Déconnexion</button>
                </form>
            </nav>
        </header>
        <main>
            <section class="hero">
                <span class="eyebrow">À toi de jouer</span>
                <h1>Bienvenue, {{ auth()->user()->name }} !</h1>
                <p>Choisis un jeu, teste tes connaissances et amuse-toi à ton rythme.</p>
            </section>
            <section id="jeux" aria-labelledby="games-title">
                <h2 id="games-title">Les jeux</h2>
                <div class="games">
                    <article class="card">
                        <span class="badge">Disponible</span>
                        <h3>Quiz de culture générale</h3>
                        <p>Réponds aux questions et découvre ton score à la fin de la partie.</p>
                        <a class="button" href="{{ route('quiz') }}">Jouer au quiz</a>
                    </article>
                    <article class="card">
                        <span class="badge">À venir</span>
                        <h3>De nouveaux jeux</h3>
                        <p>Retrouve ici les prochains jeux lorsqu’ils seront disponibles.</p>
                    </article>
                </div>
            </section>
            <section id="paiement" class="card payment" aria-labelledby="payment-title">
                <h2 id="payment-title">Paiement</h2>
                <p>Ton espace de paiement, directement dans l’application.</p>
                <p>Voici les offres envisagées. Leur contenu et leurs tarifs restent à définir ; elles ne sont pas encore disponibles à l’achat.</p>
                <div class="offers">
                    <article class="offer">
                        <span class="badge">Offre envisagée</span>
                        <h3>Accès à tous les jeux</h3>
                        <p>Un achat unique pour débloquer un ensemble de jeux.</p>
                        <ul>
                            <li>Un seul paiement</li>
                            <li>Jeux inclus et durée d’accès à définir</li>
                        </ul>
                        <p class="price">Tarif à définir</p>
                        <button class="button" type="button" disabled>Achat bientôt disponible</button>
                    </article>
                    <article class="offer">
                        <span class="badge">Offre envisagée</span>
                        <h3>Accès à un jeu</h3>
                        <p>Choisis uniquement le jeu qui t’intéresse.</p>
                        <ul>
                            <li>Achat d’un jeu au choix</li>
                            <li>Durée d’accès à définir</li>
                        </ul>
                        <p class="price">Tarif à définir par jeu</p>
                        <button class="button" type="button" disabled>Achat bientôt disponible</button>
                    </article>
                    <article class="offer">
                        <span class="badge">Offre envisagée</span>
                        <h3>Abonnement</h3>
                        <p>Une formule renouvelable pour profiter des jeux inclus.</p>
                        <ul>
                            <li>Formule mensuelle ou annuelle envisagée</li>
                            <li>Avantages et conditions à définir</li>
                        </ul>
                        <p class="price">Tarif à définir</p>
                        <button class="button" type="button" disabled>Abonnement bientôt disponible</button>
                    </article>
                    <article class="offer">
                        <span class="badge">Offre envisagée</span>
                        <h3>Pack de crédits</h3>
                        <p>Des crédits à utiliser au fil de tes envies dans les jeux.</p>
                        <ul>
                            <li>Achat ponctuel d’un pack</li>
                            <li>Quantité et utilisation des crédits à définir</li>
                        </ul>
                        <p class="price">Tarif à définir par pack</p>
                        <button class="button" type="button" disabled>Crédits bientôt disponibles</button>
                    </article>
                </div>
                <div id="payment-element" class="notice">
                    Le paiement n’est pas encore disponible. Tu pourras payer ici dès son activation.
                </div>
            </section>
        </main>
        <footer>Espace jeux · Ton prochain défi commence ici.</footer>
    </div>
</body>
</html>
