<?php

namespace App\Livewire\Base;

use App\Livewire\Concerns\LikesProfiles;
use Livewire\Component;

/**
 * Base for pages where members like photos or prompts (Discover, a profile).
 */
abstract class ProfileLikingComponent extends Component
{
    use LikesProfiles;
}
