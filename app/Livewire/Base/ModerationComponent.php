<?php

namespace App\Livewire\Base;

use App\Livewire\Concerns\ModeratesMembers;
use Livewire\Component;

/**
 * Base for admin pages that can suspend, ban, reactivate and change roles.
 */
abstract class ModerationComponent extends Component
{
    use ModeratesMembers;
}
