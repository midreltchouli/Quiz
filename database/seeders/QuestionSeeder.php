<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questions = [
            ['question' => 'What is the capital of France?', 'options' => ['Paris', 'London', 'Berlin', 'Madrid'], 'answer' => 'Paris'],
            ['question' => 'What is 2 + 2?', 'options' => ['3', '4', '5', '6'], 'answer' => '4'],
            ['question' => 'What is the capital of Germany?', 'options' => ['Berlin', 'Hamburg', 'Munich', 'Frankfurt'], 'answer' => 'Berlin'],
            ['question' => 'What is the largest planet in our solar system?', 'options' => ['Earth', 'Mars', 'Jupiter', 'Saturn'], 'answer' => 'Jupiter'],
            ['question' => 'What is the chemical symbol for water?', 'options' => ['H2O', 'O2', 'CO2', 'NaCl'], 'answer' => 'H2O'],
            ['question' => 'What is the currency of Japan?', 'options' => ['Yen', 'Dollar', 'Euro', 'Pound'], 'answer' => 'Yen'],
            ['question' => 'Who wrote "Romeo and Juliet"?', 'options' => ['Shakespeare', 'Dickens', 'Hemingway', 'Twain'], 'answer' => 'Shakespeare'],
            ['question' => 'What is the smallest prime number?', 'options' => ['0', '1', '2', '3'], 'answer' => '2'],
            ['question' => 'What is the capital of Italy?', 'options' => ['Rome', 'Milan', 'Venice', 'Naples'], 'answer' => 'Rome'],
            ['question' => 'How many continents are there on Earth?', 'options' => ['5', '6', '7', '8'], 'answer' => '7'],
            ['question' => 'What is the boiling point of water in Celsius?', 'options' => ['90', '100', '110', '120'], 'answer' => '100'],
            ['question' => 'What is the capital of Spain?', 'options' => ['Madrid', 'Barcelona', 'Seville', 'Valencia'], 'answer' => 'Madrid'],
            ['question' => 'Which planet is known as the Red Planet?', 'options' => ['Venus', 'Mars', 'Jupiter', 'Mercury'], 'answer' => 'Mars'],
            ['question' => 'What is the square root of 64?', 'options' => ['6', '7', '8', '9'], 'answer' => '8'],
            ['question' => 'What is the capital of the United Kingdom?', 'options' => ['London', 'Manchester', 'Liverpool', 'Bristol'], 'answer' => 'London'],
            ['question' => 'Who painted the Mona Lisa?', 'options' => ['Da Vinci', 'Picasso', 'Van Gogh', 'Monet'], 'answer' => 'Da Vinci'],
            ['question' => 'What is the largest ocean on Earth?', 'options' => ['Atlantic', 'Indian', 'Arctic', 'Pacific'], 'answer' => 'Pacific'],
            ['question' => 'What gas do plants absorb from the atmosphere?', 'options' => ['Oxygen', 'Nitrogen', 'Carbon Dioxide', 'Hydrogen'], 'answer' => 'Carbon Dioxide'],
            ['question' => 'What is the capital of Russia?', 'options' => ['Moscow', 'St. Petersburg', 'Kiev', 'Minsk'], 'answer' => 'Moscow'],
            ['question' => 'How many legs does a spider have?', 'options' => ['6', '8', '10', '12'], 'answer' => '8'],
            ['question' => 'What is the tallest mountain in the world?', 'options' => ['K2', 'Everest', 'Kilimanjaro', 'Denali'], 'answer' => 'Everest'],
            ['question' => 'What is 9 x 9?', 'options' => ['72', '81', '90', '99'], 'answer' => '81'],
            ['question' => 'What is the capital of Canada?', 'options' => ['Toronto', 'Vancouver', 'Ottawa', 'Montreal'], 'answer' => 'Ottawa'],
            ['question' => 'Which planet has the most moons?', 'options' => ['Earth', 'Mars', 'Saturn', 'Venus'], 'answer' => 'Saturn'],
            ['question' => 'What is the freezing point of water in Celsius?', 'options' => ['-10', '0', '10', '32'], 'answer' => '0'],
            ['question' => 'What is the capital of Egypt?', 'options' => ['Cairo', 'Alexandria', 'Giza', 'Luxor'], 'answer' => 'Cairo'],
            ['question' => 'Who developed the theory of relativity?', 'options' => ['Newton', 'Einstein', 'Galileo', 'Darwin'], 'answer' => 'Einstein'],
            ['question' => 'What is the largest mammal in the world?', 'options' => ['Elephant', 'Blue Whale', 'Giraffe', 'Hippopotamus'], 'answer' => 'Blue Whale'],
            ['question' => 'What is the capital of Australia?', 'options' => ['Sydney', 'Melbourne', 'Canberra', 'Perth'], 'answer' => 'Canberra'],
            ['question' => 'How many sides does a hexagon have?', 'options' => ['5', '6', '7', '8'], 'answer' => '6'],
            ['question' => 'Which organ pumps blood around the human body?', 'options' => ['Liver', 'Heart', 'Lung', 'Stomach'], 'answer' => 'Heart'],
            ['question' => 'Which gas do humans need to breathe to survive?', 'options' => ['Helium', 'Oxygen', 'Hydrogen', 'Neon'], 'answer' => 'Oxygen'],
            ['question' => 'What is the chemical symbol for gold?', 'options' => ['Ag', 'Fe', 'Au', 'Cu'], 'answer' => 'Au'],
            ['question' => 'Which force keeps planets in orbit around the Sun?', 'options' => ['Friction', 'Gravity', 'Magnetism', 'Buoyancy'], 'answer' => 'Gravity'],
            ['question' => 'How many bones does a typical adult human skeleton have?', 'options' => ['106', '206', '306', '406'], 'answer' => '206'],
            ['question' => 'Which part of a plant usually absorbs water from the soil?', 'options' => ['Flowers', 'Leaves', 'Roots', 'Seeds'], 'answer' => 'Roots'],
            ['question' => 'What is the capital of Portugal?', 'options' => ['Porto', 'Lisbon', 'Coimbra', 'Faro'], 'answer' => 'Lisbon'],
            ['question' => 'On which continent is Kenya located?', 'options' => ['Asia', 'Europe', 'Africa', 'South America'], 'answer' => 'Africa'],
            ['question' => 'Which river flows through Paris?', 'options' => ['Thames', 'Seine', 'Danube', 'Rhine'], 'answer' => 'Seine'],
            ['question' => 'Which country is home to the city of Kyoto?', 'options' => ['China', 'Japan', 'Thailand', 'India'], 'answer' => 'Japan'],
            ['question' => 'Which ocean lies between Africa and Australia?', 'options' => ['Arctic', 'Atlantic', 'Indian', 'Southern'], 'answer' => 'Indian'],
            ['question' => 'Which country is home to the ancient city of Machu Picchu?', 'options' => ['Peru', 'Mexico', 'Brazil', 'Chile'], 'answer' => 'Peru'],
            ['question' => 'What is 15 multiplied by 4?', 'options' => ['45', '50', '60', '75'], 'answer' => '60'],
            ['question' => 'How many degrees are there in a right angle?', 'options' => ['45', '60', '90', '180'], 'answer' => '90'],
            ['question' => 'What is one quarter of 100?', 'options' => ['10', '20', '25', '50'], 'answer' => '25'],
            ['question' => 'How many millimetres are in one centimetre?', 'options' => ['10', '100', '1000', '5'], 'answer' => '10'],
            ['question' => 'What is the next number in the sequence 2, 4, 8, 16?', 'options' => ['18', '24', '30', '32'], 'answer' => '32'],
            ['question' => 'How many faces does a cube have?', 'options' => ['4', '6', '8', '12'], 'answer' => '6'],
            ['question' => 'Who wrote the novel Pride and Prejudice?', 'options' => ['Jane Austen', 'Mary Shelley', 'Agatha Christie', 'Virginia Woolf'], 'answer' => 'Jane Austen'],
            ['question' => 'Which composer wrote The Four Seasons?', 'options' => ['Mozart', 'Beethoven', 'Vivaldi', 'Chopin'], 'answer' => 'Vivaldi'],
            ['question' => 'Which artist painted The Starry Night?', 'options' => ['Claude Monet', 'Vincent van Gogh', 'Salvador Dali', 'Pablo Picasso'], 'answer' => 'Vincent van Gogh'],
            ['question' => 'Which musical instrument normally has 88 keys?', 'options' => ['Violin', 'Flute', 'Piano', 'Trumpet'], 'answer' => 'Piano'],
            ['question' => 'In which year did humans first land on the Moon?', 'options' => ['1959', '1965', '1969', '1975'], 'answer' => '1969'],
            ['question' => 'Which ancient civilisation built the pyramids at Giza?', 'options' => ['Romans', 'Egyptians', 'Vikings', 'Aztecs'], 'answer' => 'Egyptians'],
            ['question' => 'How many players from one team are on the court at the start of a basketball game?', 'options' => ['4', '5', '6', '7'], 'answer' => '5'],
            ['question' => 'Which sport uses a shuttlecock?', 'options' => ['Tennis', 'Badminton', 'Squash', 'Table tennis'], 'answer' => 'Badminton'],
            ['question' => 'Which chess piece moves in an L shape?', 'options' => ['Bishop', 'Rook', 'Knight', 'Queen'], 'answer' => 'Knight'],
            ['question' => 'How many rings are in the Olympic symbol?', 'options' => ['4', '5', '6', '7'], 'answer' => '5'],
            ['question' => 'What does CPU stand for in computing?', 'options' => ['Central Processing Unit', 'Computer Power Utility', 'Central Program User', 'Control Panel Unit'], 'answer' => 'Central Processing Unit'],
            ['question' => 'Which two digits are used in the binary number system?', 'options' => ['1 and 2', '0 and 1', '0 and 9', '2 and 8'], 'answer' => '0 and 1'],
        ];

        $originalTranslations = File::json(database_path('seeders/data/original-translations.json'), JSON_THROW_ON_ERROR);
        $bank = File::json(database_path('seeders/data/questions.json'), JSON_THROW_ON_ERROR);
        $learning = File::json(database_path('seeders/data/learning-questions.json'), JSON_THROW_ON_ERROR);
        $challenges = File::json(database_path('seeders/data/challenge-questions.json'), JSON_THROW_ON_ERROR);
        foreach ($learning['questions'] as &$entry) {
            $entry['difficulty'] = 'easy';
        }
        unset($entry);
        $bank['questions'] = array_merge($bank['questions'], $learning['questions'], $challenges['questions']);
        $originalCategories = [
            'geography' => [1, 3, 6, 9, 10, 12, 15, 17, 19, 21, 23, 26, 29, 37, 38, 39, 40, 41, 42],
            'space' => [4, 13, 24, 53],
            'science' => [5, 11, 25, 27, 33, 34],
            'nature' => [18, 20, 28, 36],
            'health' => [31, 32, 35],
            'math' => [2, 8, 14, 22, 30, 43, 44, 45, 46, 47, 48],
            'literature' => [7, 49],
            'arts' => [16, 50, 51, 52],
            'history' => [54],
            'technology' => [59, 60],
            'sports' => [55, 56, 57, 58],
        ];
        $categoriesByNumber = [];
        foreach ($originalCategories as $category => $numbers) {
            foreach ($numbers as $number) {
                $categoriesByNumber[$number] = $category;
            }
        }
        $children = [1, 2, 3, 4, 5, 8, 9, 12, 13, 14, 15, 16, 17, 20, 22, 23, 24, 25, 28, 29, 30, 31, 32, 36, 37, 38, 39, 40, 42, 43, 44, 45, 46, 47, 48, 52, 55, 56, 57, 58, 60];

        DB::transaction(function () use ($questions, $originalTranslations, $bank, $categoriesByNumber, $children) {
            foreach ($questions as $index => $q) {
                $translations = ['en' => $q];
                if ($index === 23) {
                    $translations['en']['question'] = 'Which of these planets is famous for its prominent ring system?';
                }
                foreach (['fr', 'de'] as $locale) {
                    $dictionary = $originalTranslations[$locale]['options'];
                    $translations[$locale] = [
                        'question' => $originalTranslations[$locale]['questions'][$index],
                        'options' => array_map(fn ($option) => $dictionary[$option] ?? $option, $q['options']),
                        'answer' => $dictionary[$q['answer']] ?? $q['answer'],
                    ];
                }
                Question::updateOrCreate(['question' => $q['question']], $q + [
                    'bank_key' => 'original:'.($index + 1),
                    'translations' => $translations,
                    'category' => $categoriesByNumber[$index + 1] ?? throw new \LogicException('Missing category for original question '.($index + 1)),
                    'child_friendly' => in_array($index + 1, $children, true),
                    'difficulty' => 'easy',
                ]);
            }

            $originalTexts = array_column($questions, 'question');
            $introductoryCities = ['Paris', 'London', 'New York City', 'Berlin', 'Tokyo', 'Rome', 'Madrid', 'Barcelona', 'Moscow', 'Beijing', 'Shanghai', 'Sydney', 'Melbourne', 'Cairo', 'Delhi', 'New Delhi', 'Mumbai', 'Buenos Aires', 'Rio de Janeiro', 'Los Angeles', 'Las Vegas', 'Amsterdam', 'Brussels', 'Vienna', 'Copenhagen', 'Lisbon', 'Munich', 'Istanbul', 'Dubai', 'Toronto', 'Montreal', 'San Francisco', 'Seoul', 'Singapore', 'Athens', 'Dublin', 'Prague'];
            $introductoryCityQuestions = array_map(fn ($city) => 'In which country is the city of '.$city.'?', $introductoryCities);
            foreach ($bank['questions'] as $entry) {
                $english = array_intersect_key($entry['translations']['en'], array_flip(['question', 'options', 'answer']));
                if (in_array($english['question'], $originalTexts, true)) {
                    continue;
                }
                $introductoryCity = $entry['category'] === 'cities' && in_array($english['question'], $introductoryCityQuestions, true);
                Question::updateOrCreate(['bank_key' => $entry['key']], $english + [
                    'translations' => $entry['translations'],
                    'category' => match ($entry['category']) {
                        'capitals', 'cities' => 'geography',
                        'chemical-symbols', 'atomic-numbers', 'elements-from-symbols' => 'science',
                        'painting' => 'arts',
                        default => $entry['category'],
                    },
                    'source_url' => $entry['source_url'] ?? null,
                    'child_friendly' => $entry['child_friendly'] ?? $introductoryCity,
                    'difficulty' => $entry['difficulty'] ?? ($introductoryCity ? 'easy' : ($entry['category'] === 'atomic-numbers' ? 'hard' : 'medium')),
                ]);
            }
        });

        $this->call(QuestionExplanationSeeder::class);
    }
}
