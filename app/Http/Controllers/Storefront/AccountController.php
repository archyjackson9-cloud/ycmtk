<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Customer account dashboard (TOR §8.2 - profile, addresses, wishlist,
 * notification preferences).
 */
class AccountController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $user->load('addresses.deliveryZone');

        return view('storefront.account.edit', compact('user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
        ]);

        $user->update($data);

        return back()->with('success', 'Your profile has been updated.');
    }
}
