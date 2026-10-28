<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PreferencesController extends Controller
{
    public function edit(Request $request)
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location(route('preferences.edit'));
        }

        return view('settings', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(['en', 'fr', 'de'])],
            'font_family' => ['sometimes', Rule::in(['system', 'arial', 'georgia', 'monospace'])],
            'text_size' => ['sometimes', Rule::in(['small', 'normal', 'large'])],
            'quiz_notifications' => ['sometimes', 'boolean'],
        ]);
        $request->user()->forceFill($validated)->save();

        return redirect()->route($request->input('return_to') === 'home' ? 'accueil' : 'preferences.edit')
            ->with('status', 'preferences-updated');
    }

    public function export(Request $request)
    {
        $user = $request->user();
        $data = $user->only(['name', 'email', 'locale', 'font_family', 'text_size', 'quiz_notifications', 'created_at', 'updated_at']);
        $data['has_profile_photo'] = (bool) $user->avatar_path;

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }, 'account-data.json', ['Content-Type' => 'application/json']);
    }
}
