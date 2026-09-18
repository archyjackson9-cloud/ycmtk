@extends('layouts.storefront')

@section('title', 'My Account — CY-Market')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">

    <div class="mb-8 pb-4 border-b border-gray-200/70">
        <h1 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight">Account Settings</h1>
        <p class="text-xs text-gray-500 mt-1">Manage your personal profile and default harvest delivery destinations</p>
    </div>

    <div class="grid lg:grid-cols-12 gap-8 items-start">
        
        {{-- Profile Card --}}
        <div class="lg:col-span-6 liquid-glass rounded-[28px] p-6 sm:p-8 shadow-xs space-y-6">
            <div class="flex items-center gap-2 pb-3 border-b border-gray-100">
                <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-gray-900">Personal Information</h2>
            </div>

            <form action="{{ route('account.update') }}" method="POST" class="space-y-4">
                @csrf @method('PATCH')

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">Full Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">Email Address</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">Phone Number (For Automated SMS Updates)</label>
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}"
                           placeholder="024 XXX XXXX"
                           class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
                </div>

                <div class="pt-2">
                    <button type="submit" 
                            class="inline-flex items-center justify-center bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs px-6 py-3 rounded-full shadow-xs hover:shadow-md hover:shadow-brand-600/20 transition-all">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        {{-- Saved Addresses Card --}}
        <div class="lg:col-span-6 liquid-glass rounded-[28px] p-6 sm:p-8 shadow-xs space-y-6">
            <div class="flex items-center gap-2 pb-3 border-b border-gray-100">
                <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <h2 class="text-sm font-extrabold uppercase tracking-wider text-gray-900">Saved Delivery Addresses</h2>
            </div>

            <div class="space-y-3">
                @forelse($user->addresses as $address)
                    <div class="rounded-2xl liquid-input p-4 text-xs space-y-1 transition-colors">
                        <div class="flex items-center justify-between font-bold text-gray-900">
                            <span class="flex items-center gap-1.5">
                                {{ $address->label ?? 'Address' }}
                                @if($address->is_default)
                                    <span class="text-[10px] font-bold text-brand-700 bg-brand-50 border border-brand-200 px-2 py-0.5 rounded-full">Default</span>
                                @endif
                            </span>
                            <form action="{{ route('addresses.destroy', $address) }}" method="POST" onsubmit="return confirm('Remove this address?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-gray-400 hover:text-rose-600 transition-colors">Remove</button>
                            </form>
                        </div>
                        <p class="text-gray-600">{{ $address->recipient_name }} • {{ $address->phone }}</p>
                        <p class="text-gray-500">{{ $address->address_line }}</p>
                    </div>
                @empty
                    <p class="text-xs text-gray-400 py-2">No saved delivery addresses yet.</p>
                @endforelse
            </div>

            {{-- Collapsible Add Address Form --}}
            <details class="group pt-2 border-t border-gray-100">
                <summary class="text-xs font-bold text-brand-700 cursor-pointer list-none flex items-center justify-between hover:text-brand-900 transition-colors py-2">
                    <span>+ Add a New Delivery Address</span>
                    <svg class="w-4 h-4 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                
                <form action="{{ route('addresses.store') }}" method="POST" class="space-y-3 pt-3">
                    @csrf
                    <div>
                        <input type="text" name="label" placeholder="Address label (e.g. Home, Office, Campus)"
                               class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" name="recipient_name" placeholder="Recipient name" required
                               class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
                        <input type="tel" name="phone" placeholder="Phone number" required
                               class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
                    </div>
                    <div>
                        <textarea name="address_line" placeholder="Detailed delivery address..." required rows="2"
                                  class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none"></textarea>
                    </div>
                    <div>
                        <input type="text" name="landmark" placeholder="Nearest landmark (optional)"
                               class="w-full rounded-2xl liquid-input text-xs px-3.5 py-2.5 focus:outline-none">
                    </div>
                    <div>
                        @include('partials.location-picker', ['mapId' => 'account-address-map'])
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                            <input type="checkbox" name="is_default" value="1" class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            <span>Set as default checkout address</span>
                        </label>
                    </div>
                    <button type="submit" 
                            class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs py-2.5 rounded-full shadow-xs transition-all">
                        Save Address
                    </button>
                </form>
            </details>
        </div>

    </div>

</div>
@endsection
