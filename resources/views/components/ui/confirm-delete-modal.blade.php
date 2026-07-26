@props([
    'deletingId' => null,
    'deletingDependents' => 0,
    'mathA' => 0,
    'mathB' => 0,
])

<flux:modal :show="$deletingId !== null" @close="cancelDelete" focusable class="max-w-md">
    @if ($deletingId !== null)
        <div class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">{{ __('Delete this?') }}</flux:heading>
                @if ($deletingDependents > 0)
                    <flux:subheading>
                        {{ __(':count record(s) still reference this — deleting it may affect them.', ['count' => $deletingDependents]) }}
                    </flux:subheading>
                @else
                    <flux:subheading>{{ __('This cannot be undone.') }}</flux:subheading>
                @endif
            </div>

            @if ($deletingDependents > 0)
                <flux:input type="number" wire:model="mathAnswer" :label="__(':a + :b = ?', ['a' => $mathA, 'b' => $mathB])" />
            @endif

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost" wire:click="cancelDelete">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteConfirmed">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    @endif
</flux:modal>
