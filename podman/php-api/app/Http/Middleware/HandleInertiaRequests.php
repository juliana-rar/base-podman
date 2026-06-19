<?php

namespace App\Http\Middleware;

use App\Models\BusinessHour;
use App\Models\Message;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // Pantalles del dashboard a què pot accedir l'usuari (per filtrar el menú).
            'screens' => rescue(fn () => $request->user()?->accessibleScreens() ?? [], [], false),
            // Missatges de xat sense llegir (per al badge): per a l'equip, els dels
            // clients/sistema; per a un client, els de l'equip al seu fil.
            'unreadChat' => rescue(function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return 0;
                }

                return $user->canAccessScreen('xat')
                    ? Message::whereIn('sender', ['user', 'system'])->whereNull('read_at')->count()
                    : Message::where('user_id', $user->id)->where('sender', 'admin')->whereNull('read_at')->count();
            }, 0, false),
            'businessHours' => rescue(
                fn () => BusinessHour::orderBy('weekday')->get(['weekday', 'closed', 'opens', 'closes']),
                [],
                false,
            ),
            'businessAddress' => rescue(fn () => Setting::get('address'), null, false),
            'siteContact' => rescue(fn () => [
                'email' => Setting::get('email'),
                'phone' => Setting::get('phone'),
                'instagram' => Setting::get('instagram'),
                'facebook' => Setting::get('facebook'),
                'linkedin' => Setting::get('linkedin'),
            ], [], false),
            'siteName' => rescue(fn () => Setting::get('site_name') ?: 'ReservaHores', 'ReservaHores', false),
            'logoUrl' => rescue(fn () => ($p = Setting::get('logo')) ? Storage::url($p) : null, null, false),
            // Dades fiscals de l'empresa (per a l'Excel de facturació).
            'fiscal' => rescue(fn () => [
                'legalName' => Setting::get('legal_name'),
                'taxId' => Setting::get('tax_id'),
                'fiscalAddress' => Setting::get('fiscal_address'),
            ], [], false),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
