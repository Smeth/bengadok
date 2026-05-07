<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reçu Commande {{ $commande->numero }} - BengaDok</title>
    <style>
        @page {
            margin: 24px 28px;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            line-height: 1.45;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .actions {
            margin-bottom: 16px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-block;
            margin-right: 8px;
        }
        .btn-primary { background: #0d6efd; color: #fff; }
        .btn-secondary { background: #fff; color: #0d6efd; border: 1px solid #0d6efd; }

        /* Conteneur principal : pas de bordure arrondie globale (casse DomPDF sur plusieurs pages) */
        .recu {
            max-width: 100%;
            border-top: 3px solid #3995d2;
            padding-top: 16px;
        }

        .logo-wrap {
            text-align: center;
            margin-bottom: 12px;
        }
        .logo-wrap img {
            max-width: 140px;
            height: auto;
        }
        .logo-text {
            font-size: 18pt;
            font-weight: 700;
            color: #3995d2;
        }

        h1 {
            text-align: center;
            font-size: 14pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #4b5563;
            margin: 0 0 16px;
            letter-spacing: 0.02em;
        }

        /* En-tête commande : tableau pleine largeur (fiable sous DomPDF) */
        .tbl {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .tbl td {
            vertical-align: top;
            padding: 4px 0;
            font-size: 11pt;
        }
        .tbl .numero {
            font-weight: 700;
            font-size: 12pt;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 6px;
            background: #016630;
            color: #fff;
            font-size: 10pt;
            font-weight: 700;
            white-space: nowrap;
        }

        h2 {
            font-size: 11pt;
            font-weight: 700;
            color: #6b7280;
            margin: 0 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #e5e7eb;
        }

        .section {
            margin-bottom: 14px;
        }
        .section p {
            margin: 3px 0;
            font-size: 11pt;
        }

        .hr {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 14px 0;
        }

        /* Tableau médicaments */
        table.meds {
            width: 100%;
            border-collapse: collapse;
            font-size: 10pt;
            margin-top: 4px;
        }
        table.meds th,
        table.meds td {
            padding: 8px 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.meds th {
            text-align: left;
            font-weight: 700;
            color: #374151;
            background: #f9fafb;
            border-bottom: 2px solid #d1d5db;
        }
        table.meds .col-qty { width: 12%; text-align: center; }
        table.meds .col-pu { width: 22%; text-align: right; }
        table.meds .col-tot { width: 22%; text-align: right; }
        table.meds td.col-qty { text-align: center; }
        table.meds td.col-pu,
        table.meds td.col-tot { text-align: right; }

        /* Bloc paiement : éviter coupure page au milieu des totaux */
        .paiement-wrap {
            page-break-inside: avoid;
            margin-top: 8px;
        }
        .totaux {
            width: 100%;
            max-width: 320px;
            margin-left: auto;
            border-collapse: collapse;
            font-size: 11pt;
        }
        .totaux td {
            padding: 6px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .totaux td:last-child {
            text-align: right;
            font-weight: 600;
        }
        .totaux tr.total-final td {
            border-bottom: none;
            border-top: 2px solid #374151;
            padding-top: 10px;
            font-size: 12pt;
            font-weight: 700;
        }

        .mode-paiement {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 12px;
            border: 1px solid #016630;
            border-radius: 6px;
            font-size: 10pt;
            font-weight: 700;
            color: #016630;
        }

        .footer {
            margin-top: 20px;
            padding: 12px 16px;
            background: #0d6efd;
            color: #fff;
            font-size: 10pt;
            text-align: center;
            page-break-inside: avoid;
        }
        .footer strong {
            font-weight: 700;
        }

        @media print {
            .actions { display: none !important; }
        }
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

    @php
        $logoPath = public_path('images/figma-assets/sidebar-logo-benga.png');
        $logoBase64 = (is_readable($logoPath ?? '')) ? base64_encode(file_get_contents($logoPath)) : null;
    @endphp

    <div class="recu">
        <div class="logo-wrap">
            @if($logoBase64)
                <img src="data:image/png;base64,{{ $logoBase64 }}" alt="BengaDok" />
            @else
                <div class="logo-text">BengaDok</div>
            @endif
        </div>

        <h1>Reçu commande client</h1>

        <table class="tbl">
            <tr>
                <td>
                    <p style="margin:0 0 6px">Numéro commande : <span class="numero">{{ $commande->numero }}</span></p>
                    <p style="margin:0">Date : <strong>{{ $commande->date?->format('d/m/Y') ?? '-' }}</strong></p>
                </td>
                <td style="text-align: right; width: 120px;">
                    <span class="badge">Livrée</span>
                </td>
            </tr>
        </table>

        <div class="section">
            <h2>Informations du client</h2>
            <p><strong>Nom :</strong> {{ $commande->client?->prenom }} {{ $commande->client?->nom }}</p>
            <p><strong>Tél. :</strong> {{ $commande->client?->tel ?? '-' }}</p>
            <p><strong>Adresse :</strong> {{ $commande->client?->adresse ?? '-' }}</p>
        </div>

        <hr class="hr">

        <div class="section">
            <h2>Pharmacie</h2>
            <p><strong>{{ $commande->pharmacie?->designation ?? '-' }}</strong></p>
            <p>{{ $commande->pharmacie?->adresse ?? '-' }}</p>
        </div>

        @php
            $comCommande = trim((string) ($commande->commentaire ?? ''));
            $comPharma = trim((string) ($commande->commentaire_pharmacie ?? ''));
        @endphp
        @if($comCommande !== '' || $comPharma !== '')
            <hr class="hr">
            <div class="section">
                <h2>Commentaires</h2>
                @if($comCommande !== '')
                    <p style="margin:0 0 8px"><strong>Commande (back-office)</strong></p>
                    <p style="margin:0;white-space:pre-wrap">{{ $comCommande }}</p>
                @endif
                @if($comPharma !== '')
                    <p style="margin:12px 0 8px"><strong>Pharmacie</strong></p>
                    <p style="margin:0;white-space:pre-wrap">{{ $comPharma }}</p>
                @endif
            </div>
        @endif

        <hr class="hr">

        <div class="section">
            <h2>Détails des médicaments</h2>
            <table class="meds">
                <thead>
                    <tr>
                        <th>Médicament</th>
                        <th class="col-qty">Qté</th>
                        <th class="col-pu">Prix unitaire</th>
                        <th class="col-tot">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php $sousTotal = 0; @endphp
                    @foreach($commande->produits ?? [] as $p)
                        @php
                            $status = $p->pivot->status ?? '';
                            $pu = 0.0;
                            $qte = 0;
                            $ligne = 0.0;
                            if ($status !== 'indisponible') {
                                $qte = (int) ($p->pivot->quantite_confirmee ?? $p->pivot->quantite ?? 0);
                                $pu = (float) ($p->pivot->prix_unitaire ?? 0);
                                $ligne = $qte * $pu;
                            }
                            $sousTotal += $ligne;
                        @endphp
                        <tr>
                            <td><strong>{{ $p->designation }}@if($p->dosage) {{ $p->dosage }}@endif</strong>@if($p->forme) <br><span style="font-size:9pt;color:#6b7280">Forme : {{ $p->forme }}</span>@endif</td>
                            <td class="col-qty">{{ $qte }}</td>
                            <td class="col-pu">{{ number_format($pu, 0, ',', ' ') }} FCFA</td>
                            <td class="col-tot">{{ number_format($ligne, 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <hr class="hr">

        @php
            $livraison = (float) ($commande->montantLivraison?->designation ?? 0);
            $total = $sousTotal + $livraison;
        @endphp

        <div class="paiement-wrap">
            <h2>Informations paiement</h2>
            <table class="totaux">
                <tr>
                    <td>Sous-total</td>
                    <td>{{ number_format($sousTotal, 0, ',', ' ') }} FCFA</td>
                </tr>
                @if($livraison > 0)
                    <tr>
                        <td>Livraison</td>
                        <td>{{ number_format($livraison, 0, ',', ' ') }} FCFA</td>
                    </tr>
                @endif
                <tr class="total-final">
                    <td>Total payé</td>
                    <td>{{ number_format($total, 0, ',', ' ') }} FCFA</td>
                </tr>
            </table>
            @if($commande->modePaiement)
                <span class="mode-paiement">{{ $commande->modePaiement->designation }}</span>
            @endif
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
