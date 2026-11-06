<?php

/*
| Photos on the home page and the login / sign-up pages.
|
| These are free stock photos from Unsplash (unsplash.com/license: free to use,
| no permission needed). They show models, not HeartWise members, and the pages
| say so. They load straight from Unsplash's servers.
|
| To use your own photo instead, put a file with the same name in
| public/images/marketing/ (e.g. portrait-1.jpg) - a local file always wins.
| If a photo can't load, the drawn illustration (portrait-1.svg) is shown.
*/

return [
    'photos' => [
        // Smiling woman in the sunlight - unsplash.com/photos/D20wICKIqFY
        'portrait-1' => 'https://images.unsplash.com/photo-1743642887694-12042391ede9',
        // Young man outdoors - unsplash.com/photos/znbCV1MIlM0
        'portrait-2' => 'https://images.unsplash.com/photo-1762708590808-c453c0e4fb0f',
        // Smiling woman in a floral dress - unsplash.com/photos/C-rfXWOrllg
        'portrait-3' => 'https://images.unsplash.com/photo-1773216282433-1d79669534c6',
    ],
];
