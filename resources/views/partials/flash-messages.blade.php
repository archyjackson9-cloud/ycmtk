@if(session('success'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-4">
        <div class="flex items-center gap-3 rounded-2xl bg-emerald-500/10 backdrop-blur-md border border-emerald-500/30 text-emerald-900 px-4 py-3 text-sm shadow-sm">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white shadow-xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </span>
            <p class="font-medium flex-1">{{ session('success') }}</p>
        </div>
    </div>
@endif

@if(session('info'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-4">
        <div class="flex items-center gap-3 rounded-2xl bg-blue-500/10 backdrop-blur-md border border-blue-500/30 text-blue-900 px-4 py-3 text-sm shadow-sm">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-white shadow-xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <p class="font-medium flex-1">{{ session('info') }}</p>
        </div>
    </div>
@endif

@if(session('error'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-4">
        <div class="flex items-center gap-3 rounded-2xl bg-rose-500/10 backdrop-blur-md border border-rose-500/30 text-rose-900 px-4 py-3 text-sm shadow-sm">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-rose-600 text-white shadow-xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </span>
            <p class="font-medium flex-1">{{ session('error') }}</p>
        </div>
    </div>
@endif

@if($errors->any())
    <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-4">
        <div class="rounded-2xl bg-rose-500/10 backdrop-blur-md border border-rose-500/30 text-rose-900 px-4 py-3.5 text-sm shadow-sm">
            <div class="flex items-center gap-2 font-semibold mb-1 text-rose-800">
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Please review the following issues:</span>
            </div>
            <ul class="list-disc list-inside space-y-1 text-xs text-rose-800/90 pl-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

@if(session('checkout_issues'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-4">
        <div class="rounded-2xl bg-amber-500/10 backdrop-blur-md border border-amber-500/30 text-amber-900 px-4 py-3.5 text-sm shadow-sm">
            <div class="flex items-center gap-2 font-semibold mb-1 text-amber-800">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>Notice regarding your cart items:</span>
            </div>
            <ul class="list-disc list-inside space-y-1 text-xs text-amber-800 pl-1">
                @foreach(session('checkout_issues') as $issue)
                    <li>{{ $issue }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
