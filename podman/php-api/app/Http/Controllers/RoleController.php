<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Support\Screens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RoleController extends Controller
{
    /**
     * Crea un rol de personal amb les pantalles que pot veure.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRole($request);

        Role::create([
            'name' => trim($validated['name']),
            'screens' => Screens::sanitize($validated['screens'] ?? []),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol creat.']);

        return back();
    }

    /**
     * Edita el nom i les pantalles d'un rol.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $this->validateRole($request, $role->id);

        $role->name = trim($validated['name']);
        $role->screens = Screens::sanitize($validated['screens'] ?? []);
        $role->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol actualitzat.']);

        return back();
    }

    /**
     * Elimina un rol (els usuaris que el tenien queden sense rol de personal).
     */
    public function destroy(Role $role): RedirectResponse
    {
        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rol eliminat.']);

        return back();
    }

    /**
     * Validació compartida per crear i editar un rol.
     *
     * @return array<string, mixed>
     */
    private function validateRole(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('roles', 'name')->ignore($ignoreId)],
            'screens' => ['nullable', 'array'],
            'screens.*' => ['string', Rule::in(Screens::ALL)],
        ]);
    }
}
