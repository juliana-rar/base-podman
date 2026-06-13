<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    /**
     * Pàgina d'admin: gestió dels empleats i els serveis que fan.
     */
    public function index(): Response
    {
        $employees = Employee::with('services:id')
            ->orderBy('name')
            ->get(['id', 'name', 'image_path'])
            ->map(function (Employee $employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'url' => $employee->url,
                    'service_ids' => $employee->services->pluck('id'),
                ];
            });

        return Inertia::render('admin/Empleats', [
            'employees' => $employees,
            'categories' => ServiceCategory::with(['services' => function ($query) {
                $query->orderBy('name');
            }])->orderBy('name')->get(['id', 'name'])
                ->map(fn (ServiceCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'services' => $c->services->map(fn (Service $s) => ['id' => $s->id, 'name' => $s->name]),
                ]),
            'uncategorized' => Service::whereNull('service_category_id')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Service $s) => ['id' => $s->id, 'name' => $s->name]),
        ]);
    }

    /**
     * L'admin crea un empleat nou (amb imatge i serveis assignats opcionals).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateEmployee($request);

        $employee = Employee::create([
            'name' => trim($validated['name']),
            'image_path' => $request->hasFile('image')
                ? $request->file('image')->store('employees', 'public')
                : null,
        ]);

        $employee->services()->sync($validated['service_ids'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empleat creat.']);

        return back();
    }

    /**
     * L'admin edita el nom, la imatge i els serveis d'un empleat.
     */
    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $this->validateEmployee($request);

        $employee->name = trim($validated['name']);

        if ($request->hasFile('image')) {
            if ($employee->image_path) {
                Storage::disk('public')->delete($employee->image_path);
            }
            $employee->image_path = $request->file('image')->store('employees', 'public');
        }

        $employee->save();
        $employee->services()->sync($validated['service_ids'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empleat actualitzat.']);

        return back();
    }

    /**
     * L'admin elimina un empleat.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        if ($employee->image_path) {
            Storage::disk('public')->delete($employee->image_path);
        }

        $employee->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empleat eliminat.']);

        return back();
    }

    /**
     * Validació compartida per crear i editar.
     *
     * @return array<string, mixed>
     */
    private function validateEmployee(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'max:5120'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ]);
    }
}
