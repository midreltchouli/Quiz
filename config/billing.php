<?php

return [
    'free_question_limit' => (int) env('QUIZ_FREE_QUESTION_LIMIT', 10),
    // Integer in cents: 100 = EUR 1.00. One-time purchase for this quiz.
    'amount' => (int) env('QUIZ_UPGRADE_AMOUNT', 100),
    'currency' => env('QUIZ_UPGRADE_CURRENCY', 'eur'),
    'stripe_secret' => env('STRIPE_SECRET_KEY'),
    'stripe_webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
];
