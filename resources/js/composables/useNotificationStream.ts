import { ref, onMounted, onUnmounted } from 'vue';

export interface NotificationItem {
    id: number;
    numero: string;
    status: string;
    status_pharmacie?: string;
    status_label: string;
    client?: { nom: string; prenom?: string } | null;
    pharmacie?: { designation: string } | null;
    url: string;
    created_at: string;
}

export interface Notifications {
    count: number;
    items: NotificationItem[];
}

/**
 * Composable SSE — maintient une connexion persistante avec /notifications/stream.
 * L'EventSource se reconnecte automatiquement en cas de coupure réseau.
 * La connexion est fermée proprement au démontage du composant.
 */
export function useNotificationStream() {
    const notifications = ref<Notifications>({ count: 0, items: [] });
    const connecte = ref(false);
    const erreur = ref(false);

    let source: EventSource | null = null;

    function connecter() {
        if (source) {
            source.close();
        }

        source = new EventSource('/notifications/stream');
        connecte.value = true;
        erreur.value = false;

        source.onmessage = (event: MessageEvent) => {
            try {
                notifications.value = JSON.parse(event.data) as Notifications;
                erreur.value = false;
            } catch {
                // Données malformées, on ignore
            }
        };

        // Événement "reconnect" envoyé par le serveur après le timeout de 5 min
        source.addEventListener('reconnect', () => {
            source?.close();
            // L'EventSource natif ne se reconnecte que sur onerror,
            // donc on reconnecte manuellement après l'événement serveur
            setTimeout(connecter, 1000);
        });

        source.onerror = () => {
            erreur.value = true;
            connecte.value = false;
            // L'EventSource retente automatiquement (délai exponentiel natif)
        };

        source.onopen = () => {
            connecte.value = true;
            erreur.value = false;
        };
    }

    onMounted(() => {
        connecter();
    });

    onUnmounted(() => {
        source?.close();
        source = null;
    });

    return { notifications, connecte, erreur };
}
