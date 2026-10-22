@php
    $tasks = $this->getTasks();
    $open = collect($tasks)->where('count', '>', 0);
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        :heading="$tasks ? 'Needs your attention' : 'Quick links'"
        :description="$tasks ? 'Start at the top. Each line opens the filtered list where the work is done.' : null"
        icon="heroicon-o-bell-alert"
    >
        @if ($tasks)
            @if ($open->isEmpty())
                <div class="cy-allclear">
                    <x-filament::icon icon="heroicon-o-check-circle" class="cy-allclear-icon" />
                    <div>
                        <p class="cy-allclear-title">You're all caught up</p>
                        <p class="cy-allclear-text">No payments to confirm, no orders waiting, nothing low on stock.</p>
                    </div>
                </div>
            @else
                <ul class="cy-tasks">
                    @foreach ($open as $task)
                        <li>
                            <a href="{{ $task['url'] }}" class="cy-task">
                                <span class="cy-task-icon cy-tone-{{ $task['color'] }}">
                                    <x-filament::icon :icon="$task['icon']" class="cy-task-svg" />
                                </span>
                                <span class="cy-task-body">
                                    <span class="cy-task-label">{{ $task['label'] }}</span>
                                    <span class="cy-task-hint">{{ $task['hint'] }}</span>
                                </span>
                                <span class="cy-task-count cy-tone-{{ $task['color'] }}">{{ $task['count'] }}</span>
                                <x-filament::icon icon="heroicon-m-chevron-right" class="cy-task-chevron" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif

        @if ($shortcuts = $this->getShortcuts())
            <div class="cy-shortcuts @if ($tasks) cy-spaced @endif">
                @foreach ($shortcuts as $shortcut)
                    <a href="{{ $shortcut['url'] }}" class="cy-shortcut">
                        <x-filament::icon :icon="$shortcut['icon']" class="cy-shortcut-icon" />
                        {{ $shortcut['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
