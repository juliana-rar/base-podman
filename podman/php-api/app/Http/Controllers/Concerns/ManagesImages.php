<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Gestiona una galeria d'imatges ordenada per a un model (categoria, servei, opció).
 *
 * El client envia:
 *  - `images[]`  → fitxers nous pujats.
 *  - `order`     → JSON amb la seqüència final; cada token és o bé una ruta existent
 *                  que es manté, o bé `new:N` (índex dins de `images[]`) per intercalar
 *                  una imatge nova. S'envia com a string (un `[]` buit vol dir "cap imatge").
 *
 * Les rutes existents que no apareixen a `order` s'eliminen del disc. Si `order` no
 * arriba (clients antics), es mantenen les actuals i s'afegeixen les noves al final.
 */
trait ManagesImages
{
    /**
     * Regles de validació de la galeria (per defecte fins a 10 imatges).
     *
     * @return array<string, list<string>>
     */
    protected function imageRules(int $max = 10): array
    {
        return [
            'images' => ['nullable', 'array', 'max:'.$max],
            'images.*' => ['image', 'max:5120'],
            'order' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * Reconstrueix la galeria final, desant fitxers nous i esborrant els descartats.
     *
     * @param  list<string>  $current  Rutes actuals del model (buides en crear).
     * @return list<string> Rutes finals, en ordre (la primera és la portada).
     */
    protected function syncImages(Request $request, string $directory, array $current = []): array
    {
        $newFiles = array_values($request->file('images', []));
        $order = json_decode((string) $request->input('order'), true);
        $final = [];

        if (! is_array($order)) {
            // Sense ordre explícit: mantenim les actuals i afegim les noves al final.
            $final = $current;
            foreach ($newFiles as $file) {
                $final[] = $file->store($directory, 'public');
            }
        } else {
            foreach ($order as $token) {
                if (is_string($token) && str_starts_with($token, 'new:')) {
                    $index = (int) substr($token, 4);
                    if (isset($newFiles[$index])) {
                        $final[] = $newFiles[$index]->store($directory, 'public');
                    }
                } elseif (in_array($token, $current, true)) {
                    $final[] = $token;
                }
            }
        }

        $final = array_values(array_unique($final));

        foreach (array_diff($current, $final) as $removed) {
            Storage::disk('public')->delete($removed);
        }

        return $final;
    }

    /**
     * Com syncImages, però mantenint un peu de foto (caption) per imatge.
     *
     * El client envia, a més de `images[]` i `order`, un `captions` (JSON) amb un text
     * paral·lel a cada token d'`order`. La caption viatja amb la imatge encara que es
     * reordeni o s'intercalin imatges noves.
     *
     * @param  list<string>  $current  Rutes actuals del model (buides en crear).
     * @return array{paths: list<string>, captions: array<string, string>}
     *               Rutes finals en ordre i el mapa { ruta => caption } (sense captions buides).
     */
    protected function syncCaptionedImages(Request $request, string $directory, array $current = []): array
    {
        $newFiles = array_values($request->file('images', []));
        $order = json_decode((string) $request->input('order'), true);
        $captions = json_decode((string) $request->input('captions'), true);
        $captions = is_array($captions) ? array_values($captions) : [];

        $paths = [];
        $captionMap = [];

        if (! is_array($order)) {
            // Sense ordre explícit: mantenim les actuals i afegim les noves al final (sense captions).
            $paths = $current;
            foreach ($newFiles as $file) {
                $paths[] = $file->store($directory, 'public');
            }
        } else {
            foreach ($order as $position => $token) {
                $path = null;
                if (is_string($token) && str_starts_with($token, 'new:')) {
                    $index = (int) substr($token, 4);
                    if (isset($newFiles[$index])) {
                        $path = $newFiles[$index]->store($directory, 'public');
                    }
                } elseif (in_array($token, $current, true)) {
                    $path = $token;
                }

                if ($path === null || in_array($path, $paths, true)) {
                    continue;
                }

                $paths[] = $path;
                $caption = trim((string) ($captions[$position] ?? ''));
                if ($caption !== '') {
                    $captionMap[$path] = $caption;
                }
            }
        }

        foreach (array_diff($current, $paths) as $removed) {
            Storage::disk('public')->delete($removed);
        }

        return ['paths' => $paths, 'captions' => $captionMap];
    }
}
