<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BengaDok — Maintenance en cours</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 480px;
        }
        .logo {
            font-size: 2rem;
            font-weight: 700;
            color: #6366f1;
            margin-bottom: 2rem;
            letter-spacing: -0.5px;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        p {
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }
        .badge {
            display: inline-block;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 9999px;
            padding: 0.375rem 1rem;
            font-size: 0.875rem;
            color: #6366f1;
        }
        .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #6366f1;
            margin-right: 6px;
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">BengaDok</div>
        <h1>Maintenance en cours</h1>
        <p>
            La plateforme est temporairement indisponible pour une mise à jour.
            Elle sera de nouveau accessible dans quelques instants.
        </p>
        <span class="badge">
            <span class="dot"></span>
            Mise à jour en cours...
        </span>
    </div>
</body>
</html>
