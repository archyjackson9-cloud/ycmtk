@php
    $timeline = \App\Enums\OrderStatus::timeline();
    $currentIndex = array_search($order->status, $timeline, true);
@endphp

@if(in_array($order->status, [\App\Enums\OrderStatus::Cancelled, \App\Enums\OrderStatus::OnHold], true))
    <div class="rounded-2xl border p-4 sm:p-5 text-sm {{ $order->status === \App\Enums\OrderStatus::Cancelled ? 'bg-rose-50/80 border-rose-200 text-rose-900' : 'bg-amber-50/80 border-amber-200 text-amber-900' }}">
        <div class="flex items-center gap-2 font-bold mb-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>Order Status: {{ $order->status->label() }}</span>
        </div>
        <p class="text-xs leading-relaxed opacity-90">{{ $order->status === \App\Enums\OrderStatus::Cancelled ? $order->cancelled_reason : $order->on_hold_reason }}</p>
    </div>
@else
    <div class="py-2">
        <ol class="flex flex-col sm:flex-row sm:flex-nowrap items-stretch sm:items-start gap-y-5 sm:gap-y-0" aria-label="Order progress">
            @foreach($timeline as $index => $status)
                <li class="sm:flex-1 sm:min-w-[5.5rem] flex flex-row sm:flex-col items-center gap-3 sm:gap-0 text-left sm:text-center relative group" @if($index === $currentIndex) aria-current="step" @endif>
                    {{-- Vertical connector on phones (horizontal one is below, sm+) --}}
                    @if(!$loop->last)
                        <div class="sm:hidden absolute left-4 -ml-px top-8 h-[calc(100%+0.75rem)] w-0.5 {{ $index < $currentIndex ? 'bg-brand-600' : 'bg-gray-200' }}" aria-hidden="true"></div>
                    @endif
                    @if(!$loop->first)
                        <div class="hidden sm:block absolute top-4 right-1/2 w-full h-1 {{ $index <= $currentIndex ? 'bg-gradient-to-r from-emerald-500 to-brand-600' : 'bg-gray-200' }}" style="left: -50%;"></div>
                    @endif
                    
                    {{-- Status Indicator Circle --}}
                    <div class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-black transition-all duration-300
                        {{ $index < $currentIndex 
                            ? 'bg-brand-600 text-white shadow-xs' 
                            : ($index === $currentIndex 
                                ? 'bg-brand-600 text-white ring-4 ring-brand-500/20 shadow-md shadow-brand-600/30' 
                                : 'bg-gray-100 text-gray-600 border border-gray-200') }}">
                        @if($index < $currentIndex)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        @elseif($index === $currentIndex)
                            <span class="h-2 w-2 rounded-full bg-white motion-safe:animate-pulse"></span>
                        @else
                            {{ $index + 1 }}
                        @endif
                    </div>

                    <span class="sm:mt-2 text-sm sm:text-[11px] font-bold {{ $index <= $currentIndex ? 'text-gray-900' : 'text-gray-600' }}">
                        {{ $status->label() }}
                        <span class="sr-only">{{ $index < $currentIndex ? '(completed)' : ($index === $currentIndex ? '(current)' : '(upcoming)') }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </div>
@endif
