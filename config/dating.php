<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Minimum age
    |--------------------------------------------------------------------------
    |
    | Members must be at least this old to sign up. Do not lower this.
    |
    */

    'minimum_age' => 18,

    // Shown on the legal pages. Set DATING_CONTACT_EMAIL before launch.
    'contact_email' => env('DATING_CONTACT_EMAIL', 'support@heartwise.test'),

    // The business that runs the site, shown on the Terms. Set before launch.
    'operator' => env('DATING_OPERATOR', 'Heartwise (operator name to be confirmed)'),

    'maximum_age' => 99,

    /*
    |--------------------------------------------------------------------------
    | Limits (anti-spam)
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'likes_per_day' => (int) env('DATING_LIKES_PER_DAY', 50),
        'likes_per_minute' => 20,
        'messages_per_minute' => 15,
        'messages_per_day_new_account' => 100,
        'max_photos' => 6,
        'max_photo_kb' => 8192,
        'reports_per_day' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scam / off-platform phrases
    |--------------------------------------------------------------------------
    |
    | Messages containing these are still delivered, but the receiver sees a
    | safety warning and the message is listed for the moderators.
    |
    */

    'scam_phrases' => [
        'gift card', 'giftcard', 'itunes card', 'steam card', 'google play card',
        'western union', 'moneygram', 'wire transfer', 'wire me', 'bank transfer',
        'send money', 'send me money', 'send me $', 'loan me', 'lend me',
        'cash app', 'cashapp', 'zelle', 'venmo', 'paypal', 'apple pay',
        'bitcoin', 'btc', 'crypto', 'usdt', 'ethereum', 'forex', 'investment opportunity',
        'customs fee', 'hospital bill', 'stuck abroad', 'oil rig', 'military deployment',
        'inheritance', 'verify your account', 'your password', 'social security',
    ],

    'off_platform_phrases' => [
        'whatsapp', 'telegram', 'signal app', 'google hangouts', 'hangouts', 'snapchat', 'kik',
    ],

    /*
    |--------------------------------------------------------------------------
    | Selfie verification gestures
    |--------------------------------------------------------------------------
    */

    'gestures' => [
        'Hold up three fingers next to your face',
        'Give a thumbs up with your left hand',
        'Touch your right ear',
        'Make a peace sign under your chin',
        'Hold your open palm next to your cheek',
    ],

    'us_states' => [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
        'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
        'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
        'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
        'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
        'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
        'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
        'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
        'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
        'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
        'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
    ],

];
