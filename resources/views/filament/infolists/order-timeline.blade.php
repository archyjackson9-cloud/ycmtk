@php
    $timeline = \App\Support\OrderTimeline::for($getRecord());
@endphp

<div class="cy-timeline-wrap">
    <ol class="cy-timeline" aria-label="Order progress">
        @foreach ($timeline['steps'] as $step)
            <li class="cy-step cy-step-{{ $step['state'] }}" @if ($step['state'] === 'current') aria-current="step" @endif>
                <span class="cy-step-dot" aria-hidden="true">
                    @if ($step['state'] === 'done')
                        <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                    @endif
                </span>
                <span class="cy-step-label">{{ $step['label'] }}</span>
                <span class="cy-step-time">{{ $step['at'] }}</span>
                <span class="sr-only">{{ ['done' => 'Completed', 'current' => 'Current step', 'upcoming' => 'Not reached yet'][$step['state']] }}</span>
            </li>
        @endforeach
    </ol>

    @if ($timeline['banner'])
        <div class="cy-banner cy-banner-{{ $timeline['banner']['tone'] }}" role="status">
            <strong>{{ $timeline['banner']['label'] }}.</strong>
            {{ $timeline['banner']['text'] ?: 'No reason was recorded.' }}
        </div>
    @endif
</div>
