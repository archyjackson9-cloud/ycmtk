@php
    $timeline = \App\Enums\OrderStatus::timeline();
    $currentIndex = array_search($order->status, $timeline, true);
@endphp

@if(in_array($order->status, [\App\Enums\OrderStatus::Cancelled, \App\Enums\OrderStatus::OnHold], true))
    <div class="rounded-2xl border p-5 text-sm backdrop-blur-md {{ $order->status === \App\Enums\OrderStatus::Cancelled ? 'bg-rose-50/80 border-rose-200 text-rose-900' : 'bg-amber-50/80 border-amber-200 text-amber-900' }}">
        <div class="flex items-center gap-2 font-bold mb-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>Order Status: {{ $order->status->label() }}</span>
        </div>
        <p class="text-xs leading-relaxed opacity-90">{{ $order->status === \App\Enums\OrderStatus::Cancelled ? $order->cancelled_reason : $order->on_hold_reason }}</p>
    </div>
@else
    <div class="py-2">
        <ol class="flex flex-wrap sm:flex-nowrap items-start gap-y-6">
            @foreach($timeline as $index => $status)
                <li class="flex-1 min-w-[5.5rem] flex flex-col items-center text-center relative group">
                    @if(!$loop->first)
                        <div class="hidden sm:block absolute top-4 right-1/2 w-full h-1 {{ $index <= $currentIndex ? 'bg-gradient-to-r from-emerald-500 to-brand-600' : 'bg-gray-200' }}" style="left: -50%;"></div>
                    @endif
                    
                    {{-- Status Indicator Circle --}}
                    <div class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full text-xs font-black transition-all duration-300
                        {{ $index < $currentIndex 
                            ? 'bg-brand-600 text-white shadow-xs' 
                            : ($index === $currentIndex 
                                ? 'bg-brand-600 text-white ring-4 ring-brand-500/20 shadow-md shadow-brand-600/30' 
                                : 'bg-gray-100 text-gray-400 border border-gray-200') }}">
                        @if($index < $currentIndex)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        @elseif($index === $currentIndex)
                            <span class="h-2 w-2 rounded-full bg-white animate-ping"></span>
                        @else
                            {{ $index + 1 }}
                        @endif
                    </div>

                    <span class="mt-2 text-[11px] font-bold {{ $index <= $currentIndex ? 'text-gray-900' : 'text-gray-400' }}">
                        {{ $status->label() }}
                    </span>
                </li>
            @endforeach
        </ol>
    </div>
@endif
