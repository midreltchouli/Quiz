<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): View|Response
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location(route('profile.edit'));
        }

        return view('profile', ['user' => $request->user()]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $oldAvatar = $user->avatar_path;
        $newAvatar = null;
        $user->fill($request->safe()->only(['name', 'email']));

        if ($request->hasFile('avatar')) {
            $newAvatar = $request->file('avatar')->store('avatars', 'local');
            if ($newAvatar === false) {
                throw ValidationException::withMessages(['avatar' => __('The photo could not be saved. Please try again.')]);
            }
            $user->avatar_path = $newAvatar;
        } elseif ($request->boolean('remove_avatar')) {
            $user->avatar_path = null;
        }

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        try {
            $user->save();
        } catch (\Throwable $exception) {
            if ($newAvatar) {
                Storage::disk('local')->delete($newAvatar);
            }
            throw $exception;
        }

        if ($oldAvatar && $oldAvatar !== $user->avatar_path) {
            Storage::disk('local')->delete($oldAvatar);
        }

        $request->session()->flash('status', 'profile-updated');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return redirect()->route('preferences.edit', ['section' => 'account']);
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function avatar(Request $request): BinaryFileResponse
    {
        $path = $request->user()->avatar_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
