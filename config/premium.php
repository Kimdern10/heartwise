<?php

/*
|--------------------------------------------------------------------------
| Heartwise Premium
|--------------------------------------------------------------------------
|
| Plans are Stripe "Prices" (recurring). Create a product called
| "Heartwise Premium" in the Stripe dashboard with these three prices,
| then paste each price ID (price_...) into .env. Amounts below are only
| what we display — Stripe charges whatever the price in Stripe says, so
| keep them the same.
|
*/

return [

    'plans' => [
        'monthly' => [
            'label' => '1 month',
            'price_id' => env('STRIPE_PRICE_MONTHLY'),
            'amount' => 29.99,
            'months' => 1,
        ],
        'quarterly' => [
            'label' => '3 months',
            'price_id' => env('STRIPE_PRICE_QUARTERLY'),
            'amount' => 59.99,
            'months' => 3,
            'badge' => 'Most popular',
        ],
        'biannual' => [
            'label' => '6 months',
            'price_id' => env('STRIPE_PRICE_BIANNUAL'),
            'amount' => 89.99,
            'months' => 6,
            'badge' => 'Best value',
        ],
    ],

    // Free members: 3 likes a day and no Sparks. Everything else in the features list is Premium.
    'free' => [
        'likes_per_day' => 3,
        'sparks_per_day' => 0,
    ],

    'premium' => [
        'likes_per_day' => 500,
        'sparks_per_day' => 5,
    ],

    // Shown on the Premium page.
    'features' => [
        ['heart', 'Unlimited likes', 'Free members get 3 likes a day. Premium members like as many people as they want.'],
        ['eye', 'See everyone who likes you', 'Skip the guessing and match instantly.'],
        ['user-group', 'See who viewed your profile', 'Know who’s checking you out.'],
        ['bolt', '5 Sparks a day', 'Stand out at the top of their likes.'],
        ['adjustments-horizontal', 'Advanced filters', 'Search by height, religion, family plans, habits and more.'],
        ['eye-slash', 'Browse privately', 'Look around without appearing in “Viewed you”.'],
    ],

];
