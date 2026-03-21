<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotificationStreamController extends Controller
{
    /**
     * Flux SSE (Server-Sent Events) de notifications en temps réel.
     * Chaque utilisateur authentifié maintient une connexion persistante.
     * Le serveur pousse les données toutes les INTERVALLE secondes.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->stream(function () use ($user) {
            // Timeout max : 5 minutes, l'EventSource reconnecte automatiquement
            $fin = time() + 300;

            while (time() < $fin) {
                if (connection_aborted()) {
                    break;
                }

                $data = $this->getNotifications($user);
                echo 'data: ' . json_encode($data) . "\n\n";

                // flush() envoie les données au client immédiatement
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                sleep(15);
            }

            // Signale au client de se reconnecter immédiatement
            echo "event: reconnect\ndata: {}\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',  // Désactive le buffer nginx
            'Connection'        => 'keep-alive',
        ]);
    }

    /**
     * Même logique que HandleInertiaRequests::getNotifications().
     * Retourne les notifications selon le rôle de l'utilisateur.
     */
    private function getNotifications(User $user): array
    {
        $roles = $user->getRoleNames()->toArray();
        $isPharmacie = in_array('gerant', $roles) || in_array('vendeur', $roles);
        $isBackoffice = in_array('admin', $roles) || in_array('agent_call_center', $roles) || in_array('super_admin', $roles);

        $query = Commande::query()->with(['client:id,nom,prenom', 'pharmacie:id,designation']);

        if ($isPharmacie && $user->pharmacie_id) {
            $query->where('pharmacie_id', $user->pharmacie_id)
                ->where('status_pharmacie', 'nouvelle');
        } elseif ($isBackoffice) {
            $query->where('status', 'en_attente')
                ->where('updated_at', '>=', now()->subDays(3));
        } else {
            return ['count' => 0, 'items' => []];
        }

        $count = (clone $query)->count();
        $items = $query->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'numero', 'status', 'status_pharmacie', 'client_id', 'pharmacie_id', 'created_at', 'updated_at'])
            ->map(fn (Commande $c) => [
                'id'           => $c->id,
                'numero'       => $c->numero,
                'status'       => $c->status,
                'status_pharmacie' => $c->status_pharmacie,
                'status_label' => Commande::STATUSES[$c->status] ?? $c->status,
                'client'       => $c->client ? ['nom' => $c->client->nom, 'prenom' => $c->client->prenom] : null,
                'pharmacie'    => $c->pharmacie ? ['designation' => $c->pharmacie->designation] : null,
                'created_at'   => $c->created_at?->toIso8601String(),
                'url'          => '/commandes/' . $c->id,
            ])
            ->values()
            ->toArray();

        return ['count' => $count, 'items' => $items];
    }
}
