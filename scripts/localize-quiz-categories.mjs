import fs from 'node:fs';

const entries = {
    'Difficulty': ['Difficulté', 'Schwierigkeitsgrad'],
    'Easy': ['Facile', 'Leicht'],
    'Intermediate': ['Intermédiaire', 'Mittel'],
    'Difficult': ['Difficile', 'Schwierig'],
    'Standard categories contain intermediate and difficult questions. Kids discovery keeps a simpler selection.': ['Les catégories standard proposent des questions intermédiaires et difficiles. Le parcours enfants conserve une sélection plus simple.', 'Die Standardkategorien enthalten mittelschwere und schwierige Fragen. Der Kinderbereich bietet eine einfachere Auswahl.'],
    'General knowledge': ['Culture générale', 'Allgemeinwissen'],
    'History': ['Histoire', 'Geschichte'],
    'Geography': ['Géographie', 'Geografie'],
    'Nature and animals': ['Nature et animaux', 'Natur und Tiere'],
    'Space': ['Espace', 'Weltraum'],
    'The human body': ['Santé et corps humain', 'Gesundheit und Körper'],
    'Science': ['Sciences', 'Naturwissenschaften'],
    'Literature': ['Littérature', 'Literatur'],
    'Arts and music': ['Arts et musique', 'Kunst und Musik'],
    'Math and logic': ['Maths et logique', 'Mathematik und Logik'],
    'Technology': ['Technologie', 'Technik'],
    'Sports and games': ['Sports et jeux', 'Sport und Spiele'],
    'Kids discovery': ['Découvertes pour enfants', 'Entdecken für Kinder'],
    'A mix of every topic.': ['Un mélange de tous les thèmes.', 'Eine Mischung aus allen Themen.'],
    'Travel through time and discover civilizations.': ['Voyage dans le temps et découvre les civilisations.', 'Reise durch die Zeit und entdecke Zivilisationen.'],
    'Explore countries, capitals and cities.': ['Explore les pays, les capitales et les villes.', 'Entdecke Länder, Hauptstädte und Städte.'],
    'Discover animals, plants and the living world.': ['Découvre les animaux, les plantes et le monde vivant.', 'Entdecke Tiere, Pflanzen und die belebte Natur.'],
    'Explore planets, stars and the Solar System.': ['Explore les planètes, les étoiles et le système solaire.', 'Entdecke Planeten, Sterne und das Sonnensystem.'],
    'Learn about organs, senses and the human body.': ['Découvre les organes, les sens et le fonctionnement du corps.', 'Lerne Organe, Sinne und den menschlichen Körper kennen.'],
    'Discover matter, elements and scientific facts.': ['Découvre la matière, les éléments et les sciences.', 'Entdecke Materie, Elemente und wissenschaftliche Fakten.'],
    'Meet authors and discover their books.': ['Découvre des auteurs et leurs livres.', 'Lerne Autoren und ihre Bücher kennen.'],
    'Discover paintings, artists and musical instruments.': ['Découvre les tableaux, les artistes et les instruments.', 'Entdecke Gemälde, Künstler und Musikinstrumente.'],
    'Practise numbers, shapes and logical thinking.': ['Entraîne-toi avec les nombres, les formes et la logique.', 'Übe Zahlen, Formen und logisches Denken.'],
    'Explore computers and digital technology.': ['Explore les ordinateurs et le numérique.', 'Entdecke Computer und digitale Technik.'],
    'Discover sports, rules and board games.': ['Découvre les sports, les règles et les jeux de société.', 'Entdecke Sportarten, Regeln und Brettspiele.'],
    'A selection of accessible questions across different topics.': ['Une sélection de questions simples sur plusieurs thèmes.', 'Eine Auswahl einfacher Fragen aus verschiedenen Themen.'],
    'Choose a category': ['Choisis une catégorie', 'Wähle eine Kategorie'],
    'Choose what you want to learn. Questions follow your language setting.': ['Choisis ce que tu veux apprendre. Les questions suivent la langue de ton application.', 'Wähle aus, was du lernen möchtest. Die Fragen erscheinen in der Sprache deiner App.'],
    'Category': ['Catégorie', 'Kategorie'],
    ':count question|:count questions': [':count question|:count questions', ':count Frage|:count Fragen'],
    'Change category or players': ['Changer de catégorie ou de joueurs', 'Kategorie oder Spieler ändern'],
    'Not enough questions in this category for this number of players.': ['Cette catégorie ne contient pas assez de questions pour ce nombre de joueurs.', 'Diese Kategorie enthält nicht genügend Fragen für diese Spielerzahl.'],
};

for (const [index, locale] of ['fr', 'de'].entries()) {
    const path = `lang/${locale}.json`;
    const data = JSON.parse(fs.readFileSync(path, 'utf8'));
    for (const [key, values] of Object.entries(entries)) data[key] = values[index];
    fs.writeFileSync(path, JSON.stringify(data, null, 2) + '\n');
}

// Correct older dictionary labels written through a legacy shell encoding.
const originalPath = 'database/seeders/data/original-translations.json';
const original = JSON.parse(fs.readFileSync(originalPath, 'utf8'));
Object.assign(original.fr.options, { Montreal: 'Montréal', 'St. Petersburg': 'Saint-Pétersbourg' });
Object.assign(original.de.options, { Munich: 'München' });
fs.writeFileSync(originalPath, JSON.stringify(original, null, 2) + '\n');
