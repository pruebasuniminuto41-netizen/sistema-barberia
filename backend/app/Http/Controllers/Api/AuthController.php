<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
        ]);

        $user->assignRole('cliente');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'roles' => ['cliente'],
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'roles' => $user->getRoleNames(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('roles', 'sede'),
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        
        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $users = User::with('roles', 'sede')->get();
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        
        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:admin,supervisor,barbero,cliente',
            'sede_id' => 'nullable|exists:sedes,id',
        ]);

        $newUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'sede_id' => $request->sede_id,
        ]);

        $newUser->assignRole($request->role);

        // Si es supervisor o barbero, asignar a la sede
        if ($request->role === 'supervisor' && $request->sede_id) {
            $newUser->supervisoresSedes()->attach($request->sede_id);
        } elseif ($request->role === 'barbero' && $request->sede_id) {
            $newUser->barberosSedes()->attach($request->sede_id);
        }

        return response()->json([
            'user' => $newUser->load('roles', 'sede'),
            'message' => 'Usuario creado exitosamente',
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $targetUser = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:8',
            'phone' => 'sometimes|string|max:20',
            'role' => 'sometimes|in:admin,supervisor,barbero,cliente',
            'sede_id' => 'nullable|exists:sedes,id',
        ]);

        $targetUser->update($request->only(['name', 'email', 'phone', 'sede_id']));

        if ($request->has('password')) {
            $targetUser->update(['password' => Hash::make($request->password)]);
        }

        if ($request->has('role')) {
            $targetUser->syncRoles([$request->role]);
        }

        return response()->json([
            'user' => $targetUser->load('roles', 'sede'),
            'message' => 'Usuario actualizado exitosamente',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$user->hasRole('admin')) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $targetUser = User::findOrFail($id);
        
        if ($targetUser->id === $user->id) {
            return response()->json(['message' => 'No puedes eliminar tu propio usuario'], 400);
        }

        $targetUser->delete();

        return response()->json([
            'message' => 'Usuario eliminado exitosamente',
        ]);
    }
}
