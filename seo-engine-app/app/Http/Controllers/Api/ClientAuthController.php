<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClientAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'password' => $data['password'],
        ]);

        return response()->json($this->issueToken($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', Str::lower($data['email']))
            ->first();

        if (! $user || ! Hash::check($data['password'], (string) $user->password)) {
            return response()->json([
                'message' => 'Identifiants invalides.',
            ], 422);
        }

        return response()->json($this->issueToken($user));
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $this->serializeUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var UserAccessToken|null $token */
        $token = $request->attributes->get('frontend_access_token');

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Déconnecté.',
        ]);
    }

    /**
     * @return array{token:string,user:array{name:string,email:string,id:int}}
     */
    private function issueToken(User $user): array
    {
        $plainToken = 'prv_usr_'.Str::random(48);

        UserAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'frontend',
            'token_hash' => hash('sha256', $plainToken),
        ]);

        return [
            'token' => $plainToken,
            'user' => $this->serializeUser($user),
        ];
    }

    /**
     * @return array{name:string,email:string,id:int}
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
        ];
    }
}
