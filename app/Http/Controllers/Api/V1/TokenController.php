<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IssueTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Exchanges a staff member's credentials for an API token.
 *
 * The user lookup runs inside the tenant scope, so credentials that are valid
 * at one business are simply not found at another.
 */
final class TokenController extends Controller
{
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

    public function destroy(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['revoked' => true]);
    }
}
