<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Question;
class Quizcontroller extends Controller
{
    // private array $questions = [
    //     [
    //         'question' => 'What is the capital of France?',
    //         'options' => ['Paris', 'London', 'Berlin', 'Madrid'],
    //         'answer' => 'Paris',
    //     ],
    //     [
    //         'question' => 'What is 2 + 2?',
    //         'options' => ['3', '4', '5', '6'],
    //         'answer' => '4',
    //     ],
    //     [
    //         'question' => 'What is the capital of Germany?',
    //         'options' => ['Berlin', 'Hamburg', 'Dortmund', 'München'],
    //         'answer' => 'Berlin',
    //     ],
    //     [
    //         'question' => 'What is the largest planet in our solar system?',
    //         'options' => ['Earth', 'Mars', 'Jupiter', 'Saturn'],
    //         'answer' => 'Jupiter',
    //     ],
    //     [
    //         'question' => 'What is the chemical symbol for water?',
    //         'options' => ['H2O', 'O2', 'CO2', 'NaCl'],
    //         'answer' => 'H2O',
    //     ],
    //     [
    //         'question' => 'What is the currency of Japan?',
    //         'options' => ['Yen', 'Dollar', 'Euro', 'Pound'],
    //         'answer' => 'Yen',
    //     ]
    //     // Add more questions as needed
    // ];
    public function index()
    {
        $questions= Question::all();
         // Si c'est la première visite, on initialise l'index et le score à 0
        if (!session()->has('question_index')) {
            session(['question_index' => 0, 'score' => 0]);
        }

        $index = session('question_index');

        // Si toutes les questions sont terminées, on affiche le résultat final
        if ($index >= $questions->count()) {
            $score = session('score');
            session()->forget(['question_index', 'score']); // on réinitialise pour un nouveau tour
            return view('quiz_finish', ['score' => $score, 'total' => $questions->count()]);
        }

        $question = $questions[$index];

        return view('quiz', [
            'question' => $question,
            'number' => $index + 1,
            'total' => $questions->count(),
        ]);
    }

    public function verified(Request $request){
        $reponse = $request->input('reponse');
        $answer = $request->input('bonne_reponse');
        if($reponse === $answer){
            $result = 'the answer is correct';
                //return back()->with('resultat', 'True');
            }
            else{
                $result = 'the answer is not correct. the correct answer is: ' . $answer;
                //return back()->with('resultat', 'False');
            }           
            if ($reponse === $answer) {
                session(['score' => session('score', 0) + 1]);
            }

            session(['question_index' => session('question_index', 0) + 1]);
            return redirect('/quiz')->with('resultat', $result);
    }
}
