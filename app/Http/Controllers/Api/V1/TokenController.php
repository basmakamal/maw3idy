<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IssueTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;
use Knuckles\Scribe\Attributes\UrlParam;

/**
 * Exchanges a staff member's credentials for an API token.
 *
 * The user lookup runs inside the tenant scope, so credentials that are valid
 * at one business are simply not found at another.
 */
#[Group('Tokens', 'Getting and giving up API access.')]
#[UrlParam('tenant', description: 'The business\'s subdomain. The token will only work here.', example: 'demo')]
final class TokenController extends Controller
{
    /**
     * Create a token
     *
     * Send a staff account's email and password and receive a bearer token.
     * Ask for `abilities` explicitly to be able to write; without them the
     * token can only read.
     *
     * Throttled to five attempts a minute per caller and per email address.
     */
    #[Unauthenticated]
    #[Response(content: [
        'token' => '7|xGqz0Rk2mVb8yQ1pLw4TfHsDcE6aJhN3',
        'abilities' => ['services:read', 'bookings:read'],
        'tenant' => 'demo',
    ], status: 201)]
    #[Response(content: [
        'message' => 'These credentials do not match our records.',
        'errors' => ['email' => ['These credentials do not match our records.']],
    ], status: 422, description: 'Wrong credentials, or an account that belongs to another business.')]
    #[Response(content: ['message' => 'Too Many Attempts.'], status: 429)]
    public function store(IssueTokenRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $request->string('email')->lower()->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $token = $user->createToken($request->string('device_name')->toString(), $request->abilities());

        return response()->json([
            'token' => $token->plainTextToken,
            'abilities' => $request->abilities(),
            'tenant' => tenant()->slug,
        ], 201);
    }

    /**
     * Revoke the current token
     *
     * Deletes the token this request was made with. Other tokens are untouched.
     */
    #[Authenticated]
    #[Response(content: ['revoked' => true], status: 200)]
    public function destroy(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['revoked' => true]);
    }
}
