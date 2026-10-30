<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            {{ $this->sendAction }}
            {{ $this->testAction }}
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
