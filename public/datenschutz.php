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
    <title>Datenschutzerklärung – Stammbaum</title>
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
        ul { margin: 0 0 12px 20px; line-height: 1.7; color: #444; }
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
    <p>Datenschutzerklärung</p>
</div>

<div class="container">
    <div class="card">

        <h2>1. Verantwortliche Stelle</h2>
        <p>
            Verantwortlich für die Verarbeitung personenbezogener Daten auf dieser Website ist:
        </p>
        <p>
            Stammbaum-Projekt<br>
            [Name des Betreibers]<br>
            [Adresse]<br>
            E-Mail: [kontakt@example.com]
        </p>

        <h2>2. Erhobene Daten und Zweck</h2>
        <p>Wir erheben und verarbeiten folgende personenbezogene Daten:</p>
        <ul>
            <li><strong>Benutzername:</strong> zur eindeutigen Identifikation des Nutzerkontos</li>
            <li><strong>E-Mail-Adresse:</strong> zur Kontobestätigung, Passwort-Zurücksetzen und Systembenachrichtigungen</li>
            <li><strong>Optionale Angaben</strong> (Vorname, Nachname, Adresse): zur Personalisierung des Kontos</li>
        </ul>
        <p>Die Verarbeitung erfolgt auf Grundlage von Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung) sowie Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse am sicheren Betrieb der Anwendung).</p>

        <h2>3. Cookies</h2>
        <p>
            Diese Website setzt ausschließlich <strong>technisch notwendige Cookies</strong>. Es werden keine Tracking-, Analyse- oder Werbe-Cookies verwendet.
        </p>
        <p>Folgende Cookies werden verwendet:</p>
        <ul>
            <li><strong>PHPSESSID</strong> (Session-Cookie): Wird für die Verwaltung der Benutzersitzung benötigt. Dieses Cookie wird beim Schließen des Browsers gelöscht.</li>
            <li><strong>stammbaum_cookie_consent</strong>: Speichert, dass der Cookie-Hinweis gelesen und bestätigt wurde. Läuft nach 365 Tagen ab.</li>
        </ul>

        <h2>4. Datenspeicherung und Löschung</h2>
        <p>
            Nutzerdaten werden gespeichert, solange ein aktives Konto vorhanden ist. Nach dem Löschen des Kontos werden alle personenbezogenen Daten entfernt. E-Mail-Protokolle (Versandstatus, kein Inhalt) werden nach spätestens 90 Tagen gelöscht.
        </p>

        <h2>5. Datenweitergabe</h2>
        <p>
            Eine Weitergabe der erhobenen Daten an Dritte findet nicht statt, soweit dies nicht gesetzlich vorgeschrieben ist.
        </p>

        <h2>6. Ihre Rechte</h2>
        <p>Sie haben das Recht auf:</p>
        <ul>
            <li>Auskunft über Ihre gespeicherten Daten (Art. 15 DSGVO)</li>
            <li>Berichtigung unrichtiger Daten (Art. 16 DSGVO)</li>
            <li>Löschung Ihrer Daten (Art. 17 DSGVO)</li>
            <li>Einschränkung der Verarbeitung (Art. 18 DSGVO)</li>
            <li>Datenübertragbarkeit (Art. 20 DSGVO)</li>
            <li>Widerspruch gegen die Verarbeitung (Art. 21 DSGVO)</li>
            <li>Beschwerde bei der zuständigen Aufsichtsbehörde (Art. 77 DSGVO)</li>
        </ul>
        <p>Zur Ausübung Ihrer Rechte wenden Sie sich bitte an die oben genannte verantwortliche Stelle.</p>

        <h2>7. Technische Sicherheit</h2>
        <p>
            Passwörter werden ausschließlich als kryptografische Hashes gespeichert (bcrypt). Vertrauliche Token werden gehasht in der Datenbank abgelegt und haben eine begrenzte Gültigkeit von 24 Stunden.
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
