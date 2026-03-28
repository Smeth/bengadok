<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reçu Commande {{ $commande->numero }} - BengaDok</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 20px; color: #333; }
        .recu { max-width: 700px; margin: 0 auto; border: 2px solid #3995d2; border-radius: 17px; padding: 40px; background: #fff; }
        .logo { width: 120px; height: 48px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; }
        .logo img { width: 100%; height: 100%; object-fit: contain; }
        h1 { text-align: center; font-size: 22px; font-weight: 700; text-transform: uppercase; color: #666; margin: 0 0 24px; }
        .header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px; padding-bottom: 24px; border-bottom: 1px solid #e5e7eb; margin-bottom: 24px; }
        .header p { margin: 0; font-size: 15px; color: rgba(0,0,0,0.74); }
        .header .numero { font-weight: 700; font-size: 18px; }
        .badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 16px; border-radius: 12px; background: #016630; color: #fff; font-size: 14px; font-weight: 700; }
        .section { margin-bottom: 24px; }
        .section-title { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
        .section-icon { width: 32px; height: 32px; border-radius: 50%; background: rgba(92,89,89,0.25); display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .section-title h2 { margin: 0; font-size: 18px; font-weight: 700; color: rgba(92,89,89,0.6); }
        .section-content { padding-left: 40px; font-size: 16px; }
        .section-content p { margin: 4px 0; }
        .divider { border-top: 1px solid #e5e7eb; margin: 24px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 15px; }
        th { text-align: left; padding: 8px 0; border-bottom: 1px solid #e5e7eb; font-weight: 400; }
        td { padding: 8px 0; border-bottom: 1px solid #f3f4f6; }
        td:last-child { text-align: right; }
        .total-row { font-size: 20px; font-weight: 700; padding-top: 12px; }
        .mode-paiement { display: inline-block; margin-top: 12px; padding: 8px 16px; border: 1px solid #016630; border-radius: 8px; font-size: 13px; font-weight: 700; color: #016630; }
        .footer { margin-top: 24px; padding: 16px 24px; background: #0d6efd; color: #fff; border-radius: 8px; font-size: 15px; }
        .footer strong { font-weight: 700; }
        .actions { display: flex; gap: 12px; margin-bottom: 20px; }
        .btn { padding: 10px 24px; border-radius: 10px; font-size: 16px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-primary { background: #0d6efd; color: #fff; }
        .btn-secondary { background: #fff; color: #0d6efd; border: 1px solid #0d6efd; }
        @media print { .actions { display: none !important; } body { padding: 0; } }
    </style>
</head>
<body>
    @if(empty($hideActions))
    <div class="actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">Imprimer reçu client</button>
        <a href="{{ route('commandes.recu', $commande) }}?download=1" target="_blank" class="btn btn-secondary">Télécharger PDF</a>
        <a href="javascript:window.close()" class="btn btn-secondary">Fermer</a>
    </div>
    @endif

    <div class="recu">
        <div class="logo">
            <img src="{{ asset('images/figma-assets/sidebar-logo-benga.svg') }}" alt="BengaDok" />
        </div>
        <h1>Reçu Commande Client</h1>

        <div class="header">
            <div>
                <p>Numéro Commande : <span class="numero">{{ $commande->numero }}</span></p>
                <p style="margin-top:8px">Date : <strong>{{ $commande->date?->format('d/m/Y') ?? '-' }}</strong></p>
            </div>
            <div class="badge">Livrée</div>
        </div>

        <div class="section">
            <div class="section-title">
                <div class="section-icon">👤</div>
                <h2>Informations du client</h2>
            </div>
            <div class="section-content">
                <p>Nom : {{ $commande->client?->prenom }} {{ $commande->client?->nom }}</p>
                <p>Tel : {{ $commande->client?->tel ?? '-' }}</p>
                <p>Adresse : {{ $commande->client?->adresse ?? '-' }}</p>
            </div>
        </div>

        <div class="divider"></div>

        <div class="section">
            <div class="section-title">
                <div class="section-icon">🏥</div>
                <h2>Pharmacie</h2>
            </div>
            <div class="section-content">
                <p><strong>{{ $commande->pharmacie?->designation ?? '-' }}</strong></p>
                <p>{{ $commande->pharmacie?->adresse ?? '-' }}</p>
            </div>
        </div>

        <div class="divider"></div>

        <div class="section">
            <div class="section-title">
                <div class="section-icon">💊</div>
                <h2>Détails des Médicaments</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Médicaments</th>
                        <th>Quantité</th>
                        <th>Prix unitaire</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php $sousTotal = 0; @endphp
                    @foreach($commande->produits ?? [] as $p)
                        @php
                            $qte = $p->pivot->quantite ?? 0;
                            $pu = (float) ($p->pivot->prix_unitaire ?? 0);
                            $ligne = $qte * $pu;
                            $sousTotal += $ligne;
                        @endphp
                        <tr>
                            <td><strong>{{ $p->designation }} {{ $p->dosage ?? '' }}</strong></td>
                            <td>{{ $qte }}</td>
                            <td>{{ number_format($pu, 0, ',', ' ') }} FCFA</td>
                            <td>{{ number_format($ligne, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="divider"></div>

        @php
            $livraison = (float) ($commande->montantLivraison?->designation ?? 0);
            $total = $sousTotal + $livraison;
        @endphp

        <div class="section">
            <div class="section-title">
                <div class="section-icon">💰</div>
                <h2>Informations paiement</h2>
            </div>
            <div class="section-content">
                <p>Sous-Total : <strong>{{ number_format($sousTotal, 0, ',', ' ') }} FCFA</strong></p>
                @if($livraison > 0)
                    <p>Livraison : <strong>{{ number_format($livraison, 0, ',', ' ') }} FCFA</strong></p>
                @endif
                <p class="total-row">Total payé : <strong>{{ number_format($total, 0, ',', ' ') }} FCFA</strong></p>
                @if($commande->modePaiement)
                    <span class="mode-paiement">{{ $commande->modePaiement->designation }}</span>
                @endif
            </div>
        </div>

        <div class="footer">
            Pour tous vos besoins en médicaments contactez-nous : <strong>+242 06 121 21 54</strong> — <strong>BengaDok</strong>
        </div>
    </div>

    @if(request('print'))
    <script>window.onload = function() { window.print(); }</script>
    @endif
</body>
</html>
