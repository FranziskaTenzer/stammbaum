<?php
// Session starten (kein Login erforderlich)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impressum – Stammbaum</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        .page-header h1 { margin: 0 0 6px; font-size: 1.8em; }
        .page-header p  { margin: 0; opacity: 0.9; font-size: 0.95em; }
        .container {
            max-width: 860px;
            margin: 40px auto;
            padding: 0 20px;
            flex: 1;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        h2 { color: #764ba2; font-size: 1.3em; margin: 28px 0 10px; }
        h2:first-of-type { margin-top: 0; }
        p  { line-height: 1.7; margin-bottom: 12px; color: #444; }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #764ba2;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover { text-decoration: underline; }
        .page-footer {
            text-align: center;
            padding: 16px 20px;
            font-size: 0.85em;
            color: #888;
            border-top: 1px solid #ddd;
            background: #fff;
        }
        .page-footer a { color: #764ba2; text-decoration: none; margin: 0 8px; }
        .page-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="page-header">
    <h1>🌳 Stammbaum</h1>
    <p>Impressum</p>
</div>

<div class="container">
    <div class="card">

        <h2>Angaben gemäß § 5 TMG</h2>
        <p>
            [Name des Betreibers]<br>
            [Straße und Hausnummer]<br>
            [PLZ und Ort]<br>
            Deutschland
        </p>

        <h2>Kontakt</h2>
        <p>
            E-Mail: [kontakt@example.com]
        </p>

        <h2>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV</h2>
        <p>
            [Name des Verantwortlichen]<br>
            [Adresse wie oben]
        </p>

        <h2>Haftung für Inhalte</h2>
        <p>
            Als Diensteanbieter sind wir gemäß § 7 Abs. 1 TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.
        </p>

        <h2>Haftung für Links</h2>
        <p>
            Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich.
        </p>

        <h2>Urheberrecht</h2>
        <p>
            Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers.
        </p>

        <a class="back-link" href="javascript:history.back()">← Zurück</a>
    </div>
</div>

<footer class="page-footer">
    <a href="/stammbaum/public/datenschutz.php">Datenschutz</a>
    &middot;
    <a href="/stammbaum/public/impressum.php">Impressum</a>
    &middot;
    <span>© Stammbaum 2026</span>
</footer>

<?php include __DIR__ . '/../app/layout/cookie-consent.php'; ?>
</body>
</html>
