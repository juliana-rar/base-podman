<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    /**
     * Xat de l'usuari: el seu propi fil amb el negoci.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        // En obrir el xat, marca com a llegits els missatges de l'equip.
        Message::where('user_id', $user->id)
            ->where('sender', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return Inertia::render('Xat', [
            'messages' => $this->serialize(
                Message::where('user_id', $user->id)->orderBy('created_at')->get(),
            ),
        ]);
    }

    /**
     * L'usuari envia un missatge al negoci.
     */
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        Message::create([
            'user_id' => $request->user()->id,
            'sender' => 'user',
            'body' => $validated['body'],
        ]);

        return back();
    }

    /**
     * Panell d'admin: llista de fils i, si se'n tria un, la conversa.
     */
    public function adminIndex(Request $request): Response
    {
        $activeId = $request->integer('user') ?: null;
        $active = $activeId ? User::find($activeId) : null;
        $messages = [];

        if ($active) {
            // En obrir un fil, marca com a llegits els missatges del client i del sistema.
            Message::where('user_id', $active->id)
                ->whereIn('sender', ['user', 'system'])
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            $messages = $this->serialize(
                Message::where('user_id', $active->id)->orderBy('created_at')->get(),
            );
        }

        $threads = User::whereHas('messages')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(function (User $user) {
                $last = Message::where('user_id', $user->id)->latest()->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'last' => $last?->body,
                    'last_at' => $last?->created_at,
                    'unread' => Message::where('user_id', $user->id)
                        ->whereIn('sender', ['user', 'system'])
                        ->whereNull('read_at')
                        ->count(),
                ];
            });

        return Inertia::render('admin/Xat', [
            'threads' => $threads,
            'activeUser' => $active ? ['id' => $active->id, 'name' => $active->name, 'email' => $active->email] : null,
            'messages' => $messages,
        ]);
    }

    /**
     * L'equip respon al fil d'un client.
     */
    public function adminSend(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        Message::create([
            'user_id' => $user->id,
            'sender' => 'admin',
            'body' => $validated['body'],
        ]);

        return back();
    }

    /**
     * Serialitza una col·lecció de missatges per al frontend.
     *
     * @param  Collection<int, Message>  $messages
     * @return list<array{id: int, sender: string, body: string, created_at: mixed}>
     */
    private function serialize(Collection $messages): array
    {
        return $messages->map(fn (Message $message) => [
            'id' => $message->id,
            'sender' => $message->sender,
            'body' => $message->body,
            'created_at' => $message->created_at,
        ])->all();
    }
}
