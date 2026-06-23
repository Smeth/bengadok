<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Reçu Commande {{ $commande->numero }} - BengaDok</title>
    <style>
        @page {
            margin: 20px 24px;
        }
        * {
            box-sizing: border-box;
            font-family: DejaVu Sans, sans-serif;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            font-weight: 400;
            line-height: 1.45;
            color: rgba(0, 0, 0, 0.74);
            margin: 0;
            padding: 0;
        }
        .actions {
            margin-bottom: 16px;
            font-family: DejaVu Sans, sans-serif;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            font-weight: 700;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-block;
            margin-right: 8px;
        }
        .btn-primary { background: #0d6efd; color: #fff; }
        .btn-secondary { background: #fff; color: #0d6efd; border: 1px solid #0d6efd; }

        .recu {
            border: 2px solid #3995d2;
            padding: 18px 20px;
        }

        .logo-wrap {
            text-align: center;
            margin-bottom: 10px;
        }
        .logo-wrap img {
            max-width: 120px;
            height: auto;
        }
        .logo-text {
            font-size: 14pt;
            font-weight: 700;
            color: #3995d2;
        }

        h1 {
            font-family: DejaVu Sans, sans-serif;
            text-align: center;
            font-size: 12pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #666666;
            margin: 0 0 14px;
        }

        .header-tbl {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .header-tbl td {
            vertical-align: top;
            padding: 0;
            font-size: 9.5pt;
            color: rgba(0, 0, 0, 0.74);
        }
        .header-tbl .numero {
            font-weight: 700;
            font-size: 10pt;
            color: #111827;
        }
        .header-tbl .date-val {
            font-weight: 700;
        }
        .badge-livree {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 8px;
            background: #016630;
            border: 1px solid #016630;
            color: #ffffff;
            font-size: 9pt;
            font-weight: 700;
            white-space: nowrap;
        }

        h2 {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            font-weight: 700;
            color: rgba(92, 89, 89, 0.85);
            margin: 0 0 6px;
        }

        .section {
            margin-bottom: 12px;
        }
        .section-body {
            padding-left: 22px;
            font-size: 9.5pt;
        }
        .section-body p {
            margin: 2px 0;
        }
        .section-body .label {
            font-weight: 400;
            color: #000000;
        }
        .section-body .pharma-name {
            font-weight: 700;
            color: #000000;
        }

        .hr {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 12px 0;
        }

        table.meds {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
            margin-top: 4px;
        }
        table.meds th,
        table.meds td {
            padding: 6px 4px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
            font-family: DejaVu Sans, sans-serif;
        }
        table.meds th {
            text-align: left;
            font-weight: 400;
            color: #000000;
            border-bottom: 1px solid #e5e7eb;
            background: transparent;
        }
        table.meds td.designation {
            font-weight: 700;
            color: #111827;
        }
        table.meds .col-qty { width: 10%; }
        table.meds .col-pu { width: 24%; text-align: right; }
        table.meds .col-tot { width: 24%; text-align: right; }
        table.meds td.col-pu,
        table.meds td.col-tot {
            font-weight: 400;
        }

        .paiement-wrap {
            page-break-inside: avoid;
        }
        .paiement-body {
            padding-left: 22px;
        }
        .totaux {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-top: 4px;
        }
        .totaux td {
            padding: 4px 0;
            font-family: DejaVu Sans, sans-serif;
        }
        .totaux td:first-child {
            font-weight: 400;
            color: #000000;
        }
        .totaux td:last-child {
            text-align: right;
            font-weight: 700;
            color: #000000;
        }
        .totaux tr.total-final td {
            padding-top: 8px;
            font-size: 11pt;
            font-weight: 700;
        }

        .mode-paiement {
            display: inline-block;
            margin-top: 8px;
            padding: 5px 10px;
            border: 1px solid #016630;
            border-radius: 6px;
            font-size: 9.5pt;
            font-weight: 700;
            color: #016630;
            background: #ffffff;
        }

        .footer {
            margin-top: 16px;
            padding: 10px 14px;
            background: #0d6efd;
            color: #ffffff;
            font-size: 9pt;
            text-align: left;
            page-break-inside: avoid;
            border-radius: 6px;
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
        $produitsRecu = $produitsRecu ?? collect();
        $sousTotal = (float) ($sousTotal ?? 0);
        $dateAffichage = $dateAffichage ?? '-';
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

        <table class="header-tbl">
            <tr>
                <td>
                    <p style="margin:0 0 4px">Numéro commande : <span class="numero">{{ $commande->numero }}</span></p>
                    <p style="margin:0">Date : <span class="date-val">{{ $dateAffichage }}</span></p>
                </td>
                <td style="text-align: right; width: 100px;">
                    <span class="badge-livree">Livrée</span>
                </td>
            </tr>
        </table>

        <div class="section">
            <h2>Informations du client</h2>
            <div class="section-body">
                <p><span class="label">Nom :</span> {{ $commande->client?->prenom }} {{ $commande->client?->nom }}</p>
                <p><span class="label">Tel :</span> {{ $commande->client?->tel ?? '-' }}</p>
                <p><span class="label">Adresse :</span> {{ $commande->client?->adresse ?? '-' }}</p>
            </div>
        </div>

        <hr class="hr">

        <div class="section">
            <h2>Pharmacie</h2>
            <div class="section-body">
                <p class="pharma-name">{{ $commande->pharmacie?->designation ?? '-' }}</p>
                <p>{{ $commande->pharmacie?->adresse ?? '-' }}</p>
            </div>
        </div>

        @php
            $comCommande = trim((string) ($commande->commentaire ?? ''));
            $comPharma = trim((string) ($commande->commentaire_pharmacie ?? ''));
        @endphp
        @if($comCommande !== '' || $comPharma !== '')
            <hr class="hr">
            <div class="section">
                <h2>Commentaires</h2>
                <div class="section-body">
                    @if($comCommande !== '')
                        <p style="margin:0 0 6px;font-weight:700">Commande (back-office)</p>
                        <p style="margin:0;white-space:pre-wrap">{{ $comCommande }}</p>
                    @endif
                    @if($comPharma !== '')
                        <p style="margin:10px 0 6px;font-weight:700">Pharmacie</p>
                        <p style="margin:0;white-space:pre-wrap">{{ $comPharma }}</p>
                    @endif
                </div>
            </div>
        @endif

        <hr class="hr">

        <div class="section">
            <h2>Détails des médicaments</h2>
            <table class="meds">
                <thead>
                    <tr>
                        <th>Médicaments</th>
                        <th class="col-qty">Qté</th>
                        <th class="col-pu">Prix unit.</th>
                        <th class="col-tot">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($produitsRecu as $p)
                        @php
                            $qte = (int) ($p->pivot->quantite_confirmee ?? $p->pivot->quantite ?? 0);
                            $pu = (float) ($p->pivot->prix_unitaire ?? 0);
                            $ligne = $qte * $pu;
                        @endphp
                        <tr>
                            <td class="designation">{{ $p->designation }}@if($p->dosage) {{ $p->dosage }}@endif</td>
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
            <div class="paiement-body">
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
