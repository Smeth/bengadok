import { formatCommandeDateHeure } from '@/lib/formatDateLocal';
import {
    classesStatutDisponibiliteLigne,
    libelleStatutDisponibiliteLigne,
} from '@/lib/commandeProduitStatus';
import type { CommandeDetail } from '@/types';

export function civiliteFromSexe(sexe?: string | null): string {
    if (sexe === 'F') return 'Mme';
    if (sexe === 'M') return 'Mr';
    return '';
}

export function getClientDisplayName(
    client: { nom?: string; prenom?: string; sexe?: string } | undefined,
): string {
    if (!client) return '-';
    const prenom = (client.prenom ?? '').trim();
    const nom = (client.nom ?? '').trim();
    if (!prenom && !nom) return '-';
    const core =
        prenom === nom ? prenom : [prenom, nom].filter(Boolean).join(' ');
    const civ = civiliteFromSexe(client.sexe);
    return civ ? `${civ} ${core}` : core;
}

export function formatDateHeureCommande(c: CommandeDetail): string {
    return formatCommandeDateHeure(c.date, c.heurs, c.created_at ?? c.updated_at);
}

export function libellePivotStatusProduit(status: string | undefined | null): string {
    return libelleStatutDisponibiliteLigne(status);
}

export function classesPivotStatusProduit(status: string | undefined | null): string {
    return classesStatutDisponibiliteLigne(status);
}

export function estVenteLibrePivot(venteLibre: boolean | undefined | null): boolean {
    return Boolean(venteLibre);
}

export function classesVenteLibrePivot(venteLibre: boolean | undefined | null): string {
    return estVenteLibrePivot(venteLibre)
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : 'border-gray-200 bg-gray-50 text-gray-600';
}

export function libelleVenteLibrePivot(venteLibre: boolean | undefined | null): string {
    return estVenteLibrePivot(venteLibre)
        ? 'En vente libre'
        : 'Pas en vente libre';
}

export function libelleDecisionVerification(decision: string | undefined): string {
    const map: Record<string, string> = {
        pass: 'Validé',
        review: 'À revoir',
        fail: 'Refusé',
        pending: 'En attente',
        skipped: 'Non analysé',
    };
    const key = (decision ?? '').toLowerCase();
    return map[key] ?? (decision ? decision : '—');
}

export function descriptionDecisionVerification(decision: string | undefined): string {
    const key = (decision ?? '').toLowerCase();
    const map: Record<string, string> = {
        pass:
            'Le score dépasse le seuil de validation : les critères (dates, mots-clés, fichier unique, etc.) sont suffisamment remplis.',
        review:
            'Contrôle manuel conseillé : score moyen ou règle métier (par ex. même fichier déjà utilisé). Vérifiez les détails dans la liste des critères.',
        fail:
            'Score sous le seuil minimum : trop peu de critères validés par l’OCR et les règles. Vérifiez la qualité du scan et le contenu.',
        pending:
            'Analyse en cours ou en file d’attente : le score et le statut final seront mis à jour automatiquement.',
        skipped:
            'Vérification automatique non exécutée : désactivée dans les paramètres ou configuration absente.',
    };
    return map[key] ?? 'Décision de vérification ordonnance (OCR + règles).';
}

export function getMedicamentsText(
    produits: Array<{ designation: string; dosage?: string }> | undefined,
): string {
    return (
        produits
            ?.map((p) => p.designation + (p.dosage ? ' ' + p.dosage : ''))
            .join(', ') || '-'
    );
}
