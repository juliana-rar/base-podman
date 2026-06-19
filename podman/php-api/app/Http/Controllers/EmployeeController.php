<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesImages;
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
    use ManagesImages;

    /**
     * Pàgina pública de detall d'un membre de l'equip: bio, obres i serveis que fa.
     */
    public function show(Employee $employee): Response
    {
        // Serveis d'aquest empleat amb les mateixes dades que la secció del home
        // (categoria, opcions, preus i imatges) per renderitzar-los igual.
        $employee->load(['services' => fn ($query) => $query
            ->with('category:id,name,image_path,images', 'options:id,service_id,name,price,duration_minutes,description,image_path,images')
            ->orderBy('name'),
        ]);

        return Inertia::render('EmployeeDetail', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'description' => $employee->description,
                'work_urls' => $employee->work_urls,
                'work_captions' => $employee->captionList(),
                'services' => $employee->services->values(),
            ],
        ]);
    }

    /**
     * Pàgina d'admin: gestió dels empleats i els serveis que fan.
     */
    public function index(): Response
    {
        $employees = Employee::with('services:id', 'serviceOptions:id')
            ->orderBy('name')
            ->get(['id', 'name', 'image_path', 'works', 'description', 'work_captions'])
            ->map(function (Employee $employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'description' => $employee->description,
                    'url' => $employee->url,
                    'works' => $employee->works ?? [],
                    'work_urls' => $employee->work_urls,
                    'work_captions' => $employee->captionList(),
                    'service_ids' => $employee->services->pluck('id'),
                    'option_ids' => $employee->serviceOptions->pluck('id'),
                ];
            });

        $mapService = fn (Service $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'options' => $s->options->map(fn ($o) => ['id' => $o->id, 'name' => $o->name]),
        ];

        return Inertia::render('admin/Empleats', [
            'employees' => $employees,
            'categories' => ServiceCategory::with(['services' => function ($query) {
                $query->with('options:id,service_id,name')->orderBy('name');
            }])->orderBy('name')->get(['id', 'name'])
                ->map(fn (ServiceCategory $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'services' => $c->services->map($mapService),
                ]),
            'uncategorized' => Service::whereNull('service_category_id')
                ->with('options:id,service_id,name')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map($mapService),
        ]);
    }

    /**
     * L'admin crea un empleat nou (amb imatge i serveis assignats opcionals).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateEmployee($request);

        $gallery = $this->syncCaptionedImages($request, 'employee-works');

        $employee = Employee::create([
            'name' => trim($validated['name']),
            'description' => $this->cleanDescription($validated['description'] ?? null),
            'image_path' => $request->hasFile('image')
                ? $request->file('image')->store('employees', 'public')
                : null,
            'works' => $gallery['paths'] ?: null,
            'work_captions' => $gallery['captions'] ?: null,
        ]);

        $employee->services()->sync($validated['service_ids'] ?? []);
        $employee->serviceOptions()->sync($validated['option_ids'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empleat creat.']);

        return back();
    }

    /**
     * L'admin edita el nom, la imatge i els serveis d'un empleat.
     */
    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $this->validateEmployee($request);

        $name = trim($validated['name']);

        // Si canvia el nom, regenerem el slug (URL) mantenint-lo únic.
        if ($employee->name !== $name) {
            $employee->slug = Employee::uniqueSlug($name, $employee->id);
        }

        $employee->name = $name;
        $employee->description = $this->cleanDescription($validated['description'] ?? null);

        if ($request->hasFile('image')) {
            if ($employee->image_path) {
                Storage::disk('public')->delete($employee->image_path);
            }
            $employee->image_path = $request->file('image')->store('employees', 'public');
        }

        $gallery = $this->syncCaptionedImages($request, 'employee-works', $employee->works ?? []);
        $employee->works = $gallery['paths'] ?: null;
        $employee->work_captions = $gallery['captions'] ?: null;

        $employee->save();
        $employee->services()->sync($validated['service_ids'] ?? []);
        $employee->serviceOptions()->sync($validated['option_ids'] ?? []);

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

        foreach ($employee->works ?? [] as $path) {
            Storage::disk('public')->delete($path);
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
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:5120'],
            'captions' => ['nullable', 'string', 'max:20000'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer', 'exists:service_options,id'],
            ...$this->imageRules(30),
        ]);
    }

    /**
     * Normalitza la bio: retalla espais i la converteix a null si queda buida.
     */
    private function cleanDescription(?string $description): ?string
    {
        $description = trim((string) $description);

        return $description !== '' ? $description : null;
    }
}
