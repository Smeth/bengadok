import type { Page } from '@inertiajs/core';

export type PharmacyAlertKind = 'nouvelle' | 'a_preparer';

export type PharmacyNotificationItem = {
    id: number;
    numero: string;
    alert_kind?: PharmacyAlertKind | string | null;
    status_label?: string;
    client?: { nom: string; prenom?: string | null } | null;
    beneficiaire?: string | null;
    url: string;
};

export type PharmacyStats = {
    nouvelles: number;
    a_preparer: number;
};

export type PharmacyOrderAlert = {
    kind: PharmacyAlertKind;
    title: string;
    description: string;
    url: string;
    count: number;
};

export type PharmacyAlertSnapshot = {
    stats: PharmacyStats | null;
    notificationIds: Set<number>;
    items: PharmacyNotificationItem[];
};

function readStats(page: Page): PharmacyStats | null {
    const props = page.props as {
        stats?: PharmacyStats;
        pharmacyStats?: PharmacyStats | null;
    };

    return props.stats ?? props.pharmacyStats ?? null;
}

function readNotificationItems(page: Page): PharmacyNotificationItem[] {
    const notifications = (
        page.props as {
            notifications?: { items?: PharmacyNotificationItem[] };
        }
    ).notifications;

    return notifications?.items ?? [];
}

export function takePharmacyAlertSnapshot(page: Page): PharmacyAlertSnapshot {
    const items = readNotificationItems(page);

    return {
        stats: readStats(page),
        notificationIds: new Set(items.map((item) => item.id)),
        items,
    };
}

function formatClientLabel(item: PharmacyNotificationItem): string {
    const client = item.client;
    if (client) {
        const full = [client.prenom, client.nom].filter(Boolean).join(' ').trim();
        if (full) {
            return full;
        }
    }

    const beneficiaire = item.beneficiaire?.trim();
    if (beneficiaire && beneficiaire !== 'Soi-même') {
        return beneficiaire;
    }

    return 'Client';
}

function buildAlert(
    kind: PharmacyAlertKind,
    count: number,
    sample?: PharmacyNotificationItem,
): PharmacyOrderAlert {
    const clientLabel = sample ? formatClientLabel(sample) : null;
    const numero = sample?.numero;
    const url =
        sample?.url ??
        (kind === 'nouvelle'
            ? '/dok-pharma/commandes?onglet=nouvelles'
            : '/dok-pharma/commandes?onglet=a_preparer');

    if (kind === 'nouvelle') {
        return {
            kind,
            count,
            url,
            title:
                count > 1
                    ? `${count} nouvelles commandes`
                    : 'Nouvelle commande',
            description:
                count > 1
                    ? 'Des commandes viennent d’arriver — saisissez disponibilité et prix.'
                    : numero
                      ? `${numero} · ${clientLabel} — saisissez disponibilité et prix.`
                      : `${clientLabel} — saisissez disponibilité et prix.`,
        };
    }

    return {
        kind,
        count,
        url,
        title:
            count > 1
                ? `${count} commandes à préparer`
                : 'Commande validée',
        description:
            count > 1
                ? 'Le back-office a validé plusieurs commandes — préparez-les maintenant.'
                : numero
                  ? `${numero} · ${clientLabel} — préparez la commande et remettez au livreur.`
                  : `${clientLabel} — préparez la commande et remettez au livreur.`,
    };
}

function sampleItemForKind(
    items: PharmacyNotificationItem[],
    kind: PharmacyAlertKind,
): PharmacyNotificationItem | undefined {
    return items.find((item) => item.alert_kind === kind);
}

export function detectPharmacyOrderAlerts(
    before: PharmacyAlertSnapshot,
    after: PharmacyAlertSnapshot,
): PharmacyOrderAlert[] {
    const alerts: PharmacyOrderAlert[] = [];
    const newItems = after.items.filter(
        (item) => !before.notificationIds.has(item.id),
    );

    const newByKind: Record<PharmacyAlertKind, PharmacyNotificationItem[]> = {
        nouvelle: [],
        a_preparer: [],
    };

    for (const item of newItems) {
        if (item.alert_kind === 'a_preparer') {
            newByKind.a_preparer.push(item);
        } else if (item.alert_kind === 'nouvelle') {
            newByKind.nouvelle.push(item);
        }
    }

    let deltaNouvelles = newByKind.nouvelle.length;
    let deltaAPreparer = newByKind.a_preparer.length;

    if (before.stats && after.stats) {
        deltaNouvelles = Math.max(
            deltaNouvelles,
            after.stats.nouvelles - before.stats.nouvelles,
        );
        deltaAPreparer = Math.max(
            deltaAPreparer,
            after.stats.a_preparer - before.stats.a_preparer,
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

    if (deltaAPreparer > 0) {
        alerts.push(
            buildAlert(
                'a_preparer',
                deltaAPreparer,
                newByKind.a_preparer[0] ??
                    sampleItemForKind(after.items, 'a_preparer'),
            ),
        );
    }

    return alerts;
}
