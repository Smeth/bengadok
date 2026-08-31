import type { Page } from '@inertiajs/core';

export type BackofficeAlertKind = 'en_attente' | 'nouvelle';

export type BackofficeNotificationItem = {
    id: number;
    numero: string;
    alert_kind?: BackofficeAlertKind | string | null;
    status_label?: string;
    client?: { nom: string; prenom?: string | null } | null;
    pharmacie?: { designation: string } | null;
    url: string;
};

export type BackofficeStats = {
    en_attente: number;
    nouvelles: number;
};

export type BackofficeOrderAlert = {
    kind: BackofficeAlertKind;
    title: string;
    description: string;
    url: string;
    count: number;
    soundProfile: 'urgent' | 'info';
    toastVariant: 'urgent' | 'info';
};

export type BackofficeAlertSnapshot = {
    stats: BackofficeStats | null;
    notificationIds: Set<number>;
    items: BackofficeNotificationItem[];
};

function readStats(page: Page): BackofficeStats | null {
    const props = page.props as {
        stats?: {
            en_attente?: number;
            nouvelles?: number;
        };
        backofficeStats?: BackofficeStats | null;
    };

    if (props.backofficeStats) {
        return props.backofficeStats;
    }

    if (
        props.stats &&
        typeof props.stats.en_attente === 'number' &&
        typeof props.stats.nouvelles === 'number'
    ) {
        return {
            en_attente: props.stats.en_attente,
            nouvelles: props.stats.nouvelles,
        };
    }

    return null;
}

function readNotificationItems(page: Page): BackofficeNotificationItem[] {
    const notifications = (
        page.props as {
            notifications?: { items?: BackofficeNotificationItem[] };
        }
    ).notifications;

    return notifications?.items ?? [];
}

export function takeBackofficeAlertSnapshot(page: Page): BackofficeAlertSnapshot {
    const items = readNotificationItems(page);

    return {
        stats: readStats(page),
        notificationIds: new Set(items.map((item) => item.id)),
        items,
    };
}

function formatClientLabel(item: BackofficeNotificationItem): string {
    const client = item.client;
    if (!client) {
        return 'Client';
    }

    const full = [client.prenom, client.nom].filter(Boolean).join(' ').trim();

    return full || 'Client';
}

function buildAlert(
    kind: BackofficeAlertKind,
    count: number,
    sample?: BackofficeNotificationItem,
): BackofficeOrderAlert {
    const clientLabel = sample ? formatClientLabel(sample) : 'Client';
    const numero = sample?.numero;
    const pharmacie = sample?.pharmacie?.designation;
    const url =
        sample?.url ??
        (kind === 'en_attente'
            ? '/commandes?status=en_attente'
            : '/commandes?status=nouvelle');

    if (kind === 'en_attente') {
        return {
            kind,
            count,
            url,
            soundProfile: 'urgent',
            toastVariant: 'urgent',
            title:
                count > 1
                    ? `${count} retours pharmacie`
                    : 'Retour pharmacie',
            description:
                count > 1
                    ? 'Des pharmacies ont envoyé disponibilité et prix — validez les commandes.'
                    : numero
                      ? `${numero} · ${clientLabel}${pharmacie ? ` · ${pharmacie}` : ''} — disponibilité reçue, à valider.`
                      : `${clientLabel} — disponibilité reçue, à valider.`,
        };
    }

    return {
        kind,
        count,
        url,
        soundProfile: 'info',
        toastVariant: 'info',
        title:
            count > 1 ? `${count} nouvelles commandes` : 'Nouvelle commande',
        description:
            count > 1
                ? 'De nouvelles commandes sont entrées dans le système.'
                : numero
                  ? `${numero} · ${clientLabel}${pharmacie ? ` · ${pharmacie}` : ''} — commande à traiter.`
                  : `${clientLabel} — commande à traiter.`,
    };
}

function sampleItemForKind(
    items: BackofficeNotificationItem[],
    kind: BackofficeAlertKind,
): BackofficeNotificationItem | undefined {
    return items.find((item) => item.alert_kind === kind);
}

export function detectBackofficeOrderAlerts(
    before: BackofficeAlertSnapshot,
    after: BackofficeAlertSnapshot,
): BackofficeOrderAlert[] {
    const alerts: BackofficeOrderAlert[] = [];
    const newItems = after.items.filter(
        (item) => !before.notificationIds.has(item.id),
    );

    const newByKind: Record<BackofficeAlertKind, BackofficeNotificationItem[]> =
        {
            en_attente: [],
            nouvelle: [],
        };

    for (const item of newItems) {
        if (item.alert_kind === 'en_attente') {
            newByKind.en_attente.push(item);
        } else if (item.alert_kind === 'nouvelle') {
            newByKind.nouvelle.push(item);
        }
    }

    let deltaEnAttente = newByKind.en_attente.length;
    let deltaNouvelles = newByKind.nouvelle.length;

    if (before.stats && after.stats) {
        deltaEnAttente = Math.max(
            deltaEnAttente,
            after.stats.en_attente - before.stats.en_attente,
        );
        deltaNouvelles = Math.max(
            deltaNouvelles,
            after.stats.nouvelles - before.stats.nouvelles,
        );
    }

    if (deltaEnAttente > 0) {
        alerts.push(
            buildAlert(
                'en_attente',
                deltaEnAttente,
                newByKind.en_attente[0] ??
                    sampleItemForKind(after.items, 'en_attente'),
            ),
        );
    }

    if (deltaNouvelles > 0) {
        alerts.push(
            buildAlert(
                'nouvelle',
                deltaNouvelles,
                newByKind.nouvelle[0] ??
                    sampleItemForKind(after.items, 'nouvelle'),
            ),
        );
    }

    return alerts;
}
