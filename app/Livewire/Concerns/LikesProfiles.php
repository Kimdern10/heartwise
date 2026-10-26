<?php

namespace App\Livewire\Concerns;

use App\Actions\Dating\LikeUser;
use App\Exceptions\DatingActionException;
use App\Models\Photo;
use App\Models\ProfilePrompt;
use App\Models\User;
use App\Models\UserMatch;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/**
 * Hinge-style liking: tap the heart on a photo or prompt, add an optional
 * comment (and optionally use today's Spark), then send. Used by Discover's
 * feed and by the profile page. Pair with the partials.like-modal view.
 */
trait LikesProfiles
{
    #[Locked]
    public ?int $likeUserId = null;

    #[Locked]
    public ?int $likePhotoId = null;

    #[Locked]
    public ?int $likePromptId = null;

    public string $likeComment = '';

    public bool $likeSpark = false;

    public function openLike(int $userId, ?string $type = null, ?int $itemId = null): void
    {
        $this->resetErrorBag('likeComment');
        $this->likeUserId = $userId;
        $this->likePhotoId = $type === 'photo' ? $itemId : null;
        $this->likePromptId = $type === 'prompt' ? $itemId : null;
        $this->likeComment = '';
        $this->likeSpark = false;

        Flux::modal('like-item')->show();
    }

    public function sendLike(LikeUser $likeUser): void
    {
        $this->validate(['likeComment' => ['nullable', 'string', 'max:300']]);

        $target = User::findOrFail($this->likeUserId);

        try {
            $match = $likeUser->handle(
                Auth::user(), $target, true, $this->likeComment ?: null,
                $this->likePhotoId, $this->likePromptId, $this->likeSpark,
            );
        } catch (DatingActionException $e) {
            $this->addError('likeComment', $e->getMessage());

            return;
        }

        Flux::modal('like-item')->close();
        $this->afterLike($target, $match);
    }

    public function passOn(int $userId, LikeUser $likeUser): void
    {
        try {
            $likeUser->handle(Auth::user(), User::findOrFail($userId), false);
        } catch (DatingActionException $e) {
            Flux::toast(variant: 'warning', text: $e->getMessage());

            return;
        }

        $this->afterPass();
    }

    /**
     * What the modal shows: the photo or prompt being liked.
     *
     * @return array{photo: Photo|null, prompt: ProfilePrompt|null, name: string|null}
     */
    public function likeContext(): array
    {
        return [
            'photo' => $this->likePhotoId ? Photo::find($this->likePhotoId) : null,
            'prompt' => $this->likePromptId ? ProfilePrompt::find($this->likePromptId) : null,
            'name' => $this->likeUserId ? User::find($this->likeUserId)?->displayName() : null,
        ];
    }

    protected function afterLike(User $target, ?UserMatch $match): void
    {
        if ($match) {
            $this->dispatch('matched', name: $target->displayName(), url: route('messages.show', $match));
        } else {
            Flux::toast(variant: 'success', text: __('Like sent to :name.', ['name' => $target->displayName()]));
        }
    }

    protected function afterPass(): void
    {
        //
    }
}
