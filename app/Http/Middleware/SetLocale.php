<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()->locale ?? $request->session()->get('locale', $request->getPreferredLanguage(['en', 'fr', 'de']) ?? 'en');
        app()->setLocale(in_array($locale, ['en', 'fr', 'de'], true) ? $locale : 'en');

        return $next($request);
    }
}
