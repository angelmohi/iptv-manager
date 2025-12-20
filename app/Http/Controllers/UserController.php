<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserAccessLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isFullAdministrator()) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index(): View
    {
        $users = User::with('accessLevel')->get();
        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        $roles = UserAccessLevel::all();
        return view('users.create', compact('roles'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'access_level_id' => 'required|exists:user_access_level,id',
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'access_level_id' => $data['access_level_id'],
        ]);

        flashSuccessMessage('Usuario creado correctamente.');
        return jsonIframeRedirection(route('users.index'));
    }

    public function edit(User $user): View
    {
        $roles = UserAccessLevel::all();
        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'access_level_id' => 'required|exists:user_access_level,id',
        ]);

        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'access_level_id' => $data['access_level_id'],
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        flashSuccessMessage('Usuario actualizado correctamente.');
        return jsonIframeRedirection(route('users.index'));
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            flashDangerMessage('No puedes eliminarte a ti mismo.');
            return jsonIframeRedirection(route('users.index'));
        }

        $user->delete();

        flashSuccessMessage('Usuario eliminado correctamente.');
        return jsonIframeRedirection(route('users.index'));
    }
}
