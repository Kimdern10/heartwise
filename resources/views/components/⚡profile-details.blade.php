<?php

use App\Enums\ModerationStatus;
use App\Support\MessageScreener;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public string $job_title = '';
    public string $school = '';

    /** @var array<string, string> */
    public array $vitals = [];

    /** @var list<string> */
    public array $interests = [];

    /** @var list<array{prompt: string, answer: string}> */
    public array $prompts = [];

    public function mount(): void
    {
        $profile = Auth::user()->profile;

        $this->job_title = (string) $profile?->job_title;
        $this->school = (string) $profile?->school;

        foreach (array_keys(config('profile_options.vitals')) as $key) {
            $this->vitals[$key] = (string) $profile?->{$key};
        }

        $this->interests = $profile?->interests ?? [];
        $this->prompts = Auth::user()->prompts->map(fn ($p) => ['prompt' => $p->prompt, 'answer' => $p->answer])->all();

        if ($this->prompts === []) {
            $this->addPrompt();
        }
    }

    public function addPrompt(): void
    {
        if (count($this->prompts) < config('profile_options.max_prompts')) {
            $this->prompts[] = ['prompt' => '', 'answer' => ''];
        }
    }

    public function removePrompt(int $index): void
    {
        unset($this->prompts[$index]);
        $this->prompts = array_values($this->prompts);
    }

    public function toggleInterest(string $interest): void
    {
        if (in_array($interest, $this->interests, true)) {
            $this->interests = array_values(array_diff($this->interests, [$interest]));
        } elseif (count($this->interests) < config('profile_options.max_interests') && in_array($interest, config('profile_options.interests'), true)) {
            $this->interests[] = $interest;
        }
    }

    public function save(): void
    {
        $profile = Auth::user()->profile;

        if (! $profile) {
            Flux::toast(variant: 'warning', text: __('Save the “About you” section first.'));

            return;
        }

        $rules = [
            'job_title' => ['nullable', 'string', 'max:80'],
            'school' => ['nullable', 'string', 'max:80'],
            'interests' => ['array', 'max:'.config('profile_options.max_interests')],
            'interests.*' => [Rule::in(config('profile_options.interests'))],
            'prompts' => ['array', 'max:'.config('profile_options.max_prompts')],
            'prompts.*.prompt' => ['required_with:prompts.*.answer', 'nullable', Rule::in(config('profile_options.prompts')), 'distinct'],
            'prompts.*.answer' => ['required_with:prompts.*.prompt', 'nullable', 'string', 'max:300'],
        ];

        foreach (config('profile_options.vitals') as $key => $vital) {
            $rules['vitals.'.$key] = ['nullable', Rule::in(array_keys($vital['options']))];
        }

        $this->validate($rules, [
            'prompts.*.prompt.distinct' => __('Pick a different prompt for each answer.'),
            'prompts.*.answer.required_with' => __('Write an answer for this prompt.'),
            'prompts.*.prompt.required_with' => __('Choose a prompt for this answer.'),
        ]);

        $screener = new MessageScreener;
        foreach ($this->prompts as $i => $row) {
            if (filled($row['answer']) && $screener->flag($row['answer'])) {
                $this->addError("prompts.$i.answer", __('Please keep contact details, links, other apps and money talk out of your answers.'));

                return;
            }
        }
        foreach (['job_title', 'school'] as $field) {
            if (filled($this->{$field}) && $screener->flag($this->{$field})) {
                $this->addError($field, __('Please don’t include links or contact details here.'));

                return;
            }
        }

        $rows = collect($this->prompts)->filter(fn ($r) => filled($r['prompt']) && filled($r['answer']))->values();

        DB::transaction(function () use ($profile, $rows) {
            $profile->fill([
                'job_title' => trim($this->job_title) ?: null,
                'school' => trim($this->school) ?: null,
                'interests' => $this->interests ?: null,
                ...array_map(fn ($v) => $v ?: null, $this->vitals),
            ]);

            $changedText = $profile->isDirty(['job_title', 'school']);
            $existing = Auth::user()->prompts()->get()->values();

            foreach ($rows as $i => $row) {
                $prompt = $existing->get($i) ?? Auth::user()->prompts()->make();
                $prompt->fill(['prompt' => $row['prompt'], 'answer' => trim($row['answer']), 'position' => $i]);
                $changedText = $changedText || $prompt->isDirty(['prompt', 'answer']);
                $prompt->save();
            }

            $existing->slice($rows->count())->each->delete();

            // Public text goes back to the moderators when it changes.
            if ($changedText) {
                $profile->status = ModerationStatus::Pending;
            }

            $profile->save();
        });

        Flux::toast(variant: 'success', text: $profile->status === ModerationStatus::Pending
            ? __('Saved. New answers are checked by our team before they show.')
            : __('Details saved.'));
    }
}; ?>

<form wire:submit="save" class="space-y-8">
    {{-- Prompts --}}
    <div class="hw-card space-y-5 p-6">
        <div>
            <flux:heading size="lg" class="font-display !text-2xl">{{ __('Prompts') }}</flux:heading>
            <flux:text>{{ __('Answer up to :n prompts. They give people an easy way to start a conversation.', ['n' => config('profile_options.max_prompts')]) }}</flux:text>
        </div>

        @foreach ($prompts as $i => $row)
            <div wire:key="prompt-{{ $i }}" class="space-y-3 rounded-2xl border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:select wire:model="prompts.{{ $i }}.prompt" class="flex-1" :placeholder="__('Choose a prompt…')">
                        @foreach (config('profile_options.prompts') as $option)
                            <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="removePrompt({{ $i }})" :aria-label="__('Remove prompt')" />
                </div>
                <flux:textarea wire:model="prompts.{{ $i }}.answer" rows="2" maxlength="300" :placeholder="__('Your answer…')" />
                <flux:error name="prompts.{{ $i }}.prompt" />
                <flux:error name="prompts.{{ $i }}.answer" />
            </div>
        @endforeach

        @if (count($prompts) < config('profile_options.max_prompts'))
            <flux:button size="sm" icon="plus" wire:click="addPrompt">{{ __('Add a prompt') }}</flux:button>
        @endif
    </div>

    {{-- Vitals --}}
    <div class="hw-card space-y-5 p-6">
        <div>
            <flux:heading size="lg" class="font-display !text-2xl">{{ __('Your details') }}</flux:heading>
            <flux:text>{{ __('Everything here is optional. Leave anything blank to keep it private.') }}</flux:text>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <flux:input wire:model="job_title" :label="__('Job title')" maxlength="80" icon="briefcase" />
            <flux:input wire:model="school" :label="__('School')" maxlength="80" icon="academic-cap" />

            @foreach (config('profile_options.vitals') as $key => $vital)
                <flux:select wire:model="vitals.{{ $key }}" :label="__($vital['label'])">
                    <flux:select.option value="">{{ __('Prefer not to say') }}</flux:select.option>
                    @foreach ($vital['options'] as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endforeach
        </div>
    </div>

    {{-- Interests --}}
    <div class="hw-card space-y-4 p-6">
        <div>
            <flux:heading size="lg" class="font-display !text-2xl">{{ __('Interests') }}</flux:heading>
            <flux:text>{{ __('Pick up to :n. We use them to find people you have things in common with.', ['n' => config('profile_options.max_interests')]) }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach (config('profile_options.interests') as $interest)
                @php($on = in_array($interest, $interests, true))
                <button type="button" wire:click="toggleInterest(@js($interest))"
                    class="rounded-full border px-4 py-1.5 text-sm font-medium transition active:scale-95 {{ $on ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-300 hover:border-zinc-900 dark:border-zinc-700 dark:hover:border-white' }}">
                    {{ $interest }}
                </button>
            @endforeach
        </div>
        <flux:text class="text-xs">{{ count($interests) }}/{{ config('profile_options.max_interests') }}</flux:text>
    </div>

    <div class="flex justify-end">
        <flux:button type="submit" variant="primary" data-test="save-details">{{ __('Save details') }}</flux:button>
    </div>
</form>
