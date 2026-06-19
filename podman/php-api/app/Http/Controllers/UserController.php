<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\Screens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Panell d'administració d'usuaris, rols i permisos per pantalla.
     */
    public function index(): Response
    {
        return Inertia::render('admin/Usuaris', [
            'users' => User::with('staffRole:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone', 'role', 'role_id', 'created_at'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_admin' => $user->isAdmin(),
                    'role_id' => $user->role_id,
                    'role_name' => $user->staffRole?->name,
                    'created_at' => $user->created_at,
                ]),
            'roles' => Role::orderBy('name')->get(['id', 'name', 'screens'])
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'screens' => Screens::sanitize($role->screens ?? []),
                ]),
            'screens' => Screens::ALL,
        ]);
    }

    /**
     * Canvia el rol (admin) i el rol de personal d'un usuari.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'is_admin' => ['required', 'boolean'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
        ]);

        // Un admin no es pot treure a si mateix el rol d'administrador (evita autobloqueig).
        if ($user->id === $request->user()->id && ! $validated['is_admin']) {
            abort(403, 'No et pots treure el rol d’administrador a tu mateix.');
        }

        $user->role = $validated['is_admin'] ? 'admin' : 'client';
        // Els admins ho veuen tot, no necessiten rol de personal.
        $user->role_id = $validated['is_admin'] ? null : ($validated['role_id'] ?? null);
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuari actualitzat.']);

        return back();
    }

    /**
     * Elimina un usuari (no es pot eliminar a si mateix).
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            abort(403, 'No et pots eliminar a tu mateix.');
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuari eliminat.']);

        return back();
    }
}
