<?php

/*
|--------------------------------------------------------------------------
| Profile options
|--------------------------------------------------------------------------
|
| Lifestyle details ("vitals"), prompts and interests members can add.
| Keys are stored in the database; labels are what members see.
|
*/

return [

    'vitals' => [
        'education' => [
            'label' => 'Education',
            'icon' => 'academic-cap',
            'options' => [
                'high_school' => 'High school', 'some_college' => 'Some college', 'trade' => 'Trade school',
                'associates' => 'Associate degree', 'bachelors' => 'Bachelor’s degree', 'masters' => 'Master’s degree',
                'doctorate' => 'Doctorate',
            ],
        ],
        'religion' => [
            'label' => 'Religion',
            'icon' => 'sparkles',
            'options' => [
                'christian' => 'Christian', 'catholic' => 'Catholic', 'jewish' => 'Jewish', 'muslim' => 'Muslim',
                'hindu' => 'Hindu', 'buddhist' => 'Buddhist', 'spiritual' => 'Spiritual', 'agnostic' => 'Agnostic',
                'atheist' => 'Atheist', 'other' => 'Other',
            ],
        ],
        'politics' => [
            'label' => 'Politics',
            'icon' => 'building-library',
            'options' => [
                'liberal' => 'Liberal', 'moderate' => 'Moderate', 'conservative' => 'Conservative',
                'not_political' => 'Not political', 'other' => 'Other',
            ],
        ],
        'has_kids' => [
            'label' => 'Children',
            'icon' => 'user-group',
            'options' => ['no' => 'Don’t have children', 'yes' => 'Have children'],
        ],
        'wants_kids' => [
            'label' => 'Family plans',
            'icon' => 'home',
            'options' => [
                'want' => 'Want children', 'dont_want' => 'Don’t want children',
                'open' => 'Open to children', 'not_sure' => 'Not sure yet',
            ],
        ],
        'drinking' => [
            'label' => 'Drinking',
            'icon' => 'beaker',
            'options' => ['yes' => 'Drinks', 'sometimes' => 'Drinks sometimes', 'no' => 'Doesn’t drink'],
        ],
        'smoking' => [
            'label' => 'Smoking',
            'icon' => 'fire',
            'options' => ['yes' => 'Smokes', 'sometimes' => 'Smokes sometimes', 'no' => 'Doesn’t smoke'],
        ],
    ],

    /*
     | Dealbreakers members can switch on in Preferences. A dealbreaker turns a
     | preference into a hard filter; otherwise it only affects compatibility.
     */
    'dealbreakers' => ['age', 'distance', 'religion', 'wants_kids', 'has_kids', 'drinking', 'smoking', 'verified'],

    'max_prompts' => 3,

    'prompts' => [
        'My ideal Sunday looks like',
        'The way to win me over is',
        'I’m at my happiest when',
        'A perfect first date would be',
        'I’m looking for someone who',
        'My friends would describe me as',
        'A random fact I love',
        'The best trip I’ve ever taken',
        'My most unpopular food opinion',
        'Green flags I look out for',
        'We’ll get along if',
        'My simple pleasures',
        'Let’s settle this debate',
        'Together we could',
        'I could talk for hours about',
        'This year, I really want to',
    ],

    'max_interests' => 8,

    'interests' => [
        'Hiking', 'Coffee', 'Cooking', 'Travel', 'Live music', 'Concerts', 'Dogs', 'Cats', 'Fitness', 'Yoga',
        'Running', 'Football', 'Basketball', 'Baseball', 'Movies', 'Reading', 'Podcasts', 'Gaming', 'Art',
        'Photography', 'Dancing', 'Wine', 'Craft beer', 'Foodie', 'Brunch', 'Beach', 'Camping', 'Church',
        'Volunteering', 'Fashion', 'Tech', 'Road trips', 'Gardening', 'Board games', 'Comedy', 'Theater',
    ],

];
