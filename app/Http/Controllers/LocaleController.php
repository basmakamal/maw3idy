<?php

namespace App\Http\Controllers;

use App\Support\Localization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Remembers which language a visitor wants. The choice lives in the session,
 * which is host-only, so it applies to this business's pages and no other.
 */
final class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(Localization::supported())],
        ]);

        $request->session()->put(Localization::SESSION_KEY, $validated['locale']);

        return back();
    }
}
