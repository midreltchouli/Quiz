<?php

namespace Database\Seeders;
use App\Models\Question;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
        ];

        foreach ($questions as $q) {
            Question::create($q);
        }
    }
}
