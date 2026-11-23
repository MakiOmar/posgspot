<?php

namespace App\Http\Controllers\Api;

use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ApiTokenController extends Controller
{
    /**
     * Mint a Passport personal access token for an allowlisted integration user only.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = (string) $request->input('username');
        $user = User::where('username', $username)->first();

        // Always hash-check to avoid username enumeration via timing.
        $dummy = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWX12';
        $passwordOk = Hash::check(
            (string) $request->input('password'),
            $user?->password ?? $dummy
        );

        if (! $user || ! $passwordOk) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (! $this->isAllowlisted($user)) {
            return response()->json(['message' => 'API login not permitted for this user'], 403);
        }

        if ($user->user_type !== 'user'
            || $user->status !== 'active'
            || ! $user->allow_login
            || ! $user->business
            || ! $user->business->is_active
        ) {
            return response()->json(['message' => 'Account is not eligible for API login'], 403);
        }

        $scopes = config('api.login_scopes', []);
        if ($scopes === []) {
            Log::error('API login refused: api.login_scopes is empty');

            return response()->json(['message' => 'API tokens are not configured'], 500);
        }

        $user->tokens()->where('revoked', false)->update(['revoked' => true]);

        $tokenResult = $user->createToken('API Token', $scopes);
        $expiresAt = now()->addDays((int) config('api.token_expire_days', 7));

        return response()->json([
            'token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
        ], 200);
    }

    private function isAllowlisted(User $user): bool
    {
        $ids = config('api.login_user_ids', []);
        $usernames = config('api.login_usernames', []);

        if ($ids === [] && $usernames === []) {
            return false;
        }

        if ($ids !== [] && in_array((int) $user->id, $ids, true)) {
            return true;
        }

        return $usernames !== []
            && in_array(strtolower((string) $user->username), $usernames, true);
    }
}
