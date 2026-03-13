<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends BaseController
{
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'              => 'required|string|max:255',
            'email'             => 'required|string|email|max:255|unique:users',
            'password'          => 'required|string|min:8|confirmed',
            'native_language'   => 'nullable|string|max:10',
            'target_language'   => 'nullable|string|max:10',
            'daily_goal_minutes'=> 'nullable|integer|min:5|max:120',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos.', $validator->errors(), 422);
        }

        $user = User::create([
            'name'               => $request->name,
            'email'              => $request->email,
            'password'           => Hash::make($request->password),
            'native_language'    => $request->native_language ?? 'pt',
            'target_language'    => $request->target_language ?? 'en',
            'daily_goal_minutes' => $request->daily_goal_minutes ?? 15,
        ]);

        $token = JWTAuth::fromUser($user);

        return $this->created([
            'user'  => $this->formatUser($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Conta criada com sucesso!');
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos.', $validator->errors(), 422);
        }

        $credentials = $request->only('email', 'password');

        if (!$token = auth()->attempt($credentials)) {
            return $this->unauthorized('E-mail ou senha incorretos.');
        }

        $user = auth()->user();

        return $this->success([
            'user'        => $this->formatUser($user),
            'token'       => $token,
            'token_type'  => 'Bearer',
            'expires_in'  => config('jwt.ttl') * 60,
        ], 'Login realizado com sucesso!');
    }

    public function logout(): JsonResponse
    {
        auth()->logout();

        return $this->success(null, 'Logout realizado com sucesso.');
    }

    public function refresh(): JsonResponse
    {
        try {
            $token = auth()->refresh();

            return $this->success([
                'token'      => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60,
            ], 'Token atualizado com sucesso.');
        } catch (\Exception $e) {
            return $this->unauthorized('Token inválido ou expirado.');
        }
    }

    public function me(): JsonResponse
    {
        $user = auth()->user();

        return $this->success($this->formatUser($user));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'name'               => 'sometimes|string|max:255',
            'native_language'    => 'sometimes|string|max:10',
            'target_language'    => 'sometimes|string|max:10',
            'daily_goal_minutes' => 'sometimes|integer|min:5|max:120',
            'avatar'             => 'sometimes|string|nullable',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos.', $validator->errors(), 422);
        }

        $user->update($request->only([
            'name', 'native_language', 'target_language', 'daily_goal_minutes', 'avatar',
        ]));

        return $this->success($this->formatUser($user), 'Perfil atualizado com sucesso.');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error('Dados inválidos.', $validator->errors(), 422);
        }

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->error('Senha atual incorreta.', null, 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return $this->success(null, 'Senha alterada com sucesso.');
    }

    private function formatUser(User $user): array
    {
        return [
            'id'                 => $user->id,
            'name'               => $user->name,
            'email'              => $user->email,
            'native_language'    => $user->native_language,
            'target_language'    => $user->target_language,
            'level'              => $user->level,
            'daily_goal_minutes' => $user->daily_goal_minutes,
            'total_xp'           => $user->total_xp,
            'streak_days'        => $user->streak_days,
            'avatar'             => $user->avatar,
            'created_at'         => $user->created_at->toISOString(),
        ];
    }
}
