<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "Admin - Startseite";
$extraHead = '<style>
    .admin-group {
        margin-bottom: 35px;
    }

    .admin-group-title {
        margin: 0 0 15px 0;
        color: var(--text-primary);
        border-left: 4px solid var(--primary-color);
        padding-left: 12px;
    }

    .admin-group-title.warning {
        border-left-color: #d32f2f;
    }

    .admin-group-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 18px;
    }

    .todo-card {
        grid-column: 1 / -1;
    }

    .todo-card ul {
        margin: 0;
        padding-left: 1.4rem;
    }

    .todo-card li {
        margin: 0.35rem 0;
        line-height: 1.45;
    }

    .todo-card li > ul {
        margin-top: 0.45rem;
        margin-bottom: 0.45rem;
        padding-left: 1.3rem;
        border-left: 2px solid #d8deea;
    }

    .todo-card li > ul > li > ul {
        border-left-color: #e6eaf2;
    }

    @media (max-width: 1300px) {
        .admin-group-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 900px) {
        .admin-group-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .admin-group-grid {
            grid-template-columns: 1fr;
        }
    }
</style>';

require_once '../../layout/header.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur für Administratoren.');
}
?>

<div class="page-header">
    <h1>⚙️ Admin-Bereich</h1>
    <p class="subtitle">Verwaltung und Import-Funktionen</p>
</div>

<div class="admin-group">
    <h2 class="admin-group-title">1) Verwaltung & Kommunikation</h2>
    <div class="admin-group-grid">
        <div class="content-card admin-card">
            <h3>👥 Benutzer-Verwaltung</h3>
            <p>Alle registrierten Benutzer und deren Verifizierungsstatus anzeigen</p>
            <a href="benutzer-verwaltung.php" class="btn btn-primary">Benutzer verwalten</a>
        </div>

        <div class="content-card admin-card">
            <h3>📬 Neue Nachrichten</h3>
            <p>Offene Anfragen der Benutzer prüfen und beantworten</p>
            <a href="admin-nachrichten.php?filter=offen&amp;typ=Nachricht" class="btn btn-primary">Nachrichten öffnen</a>
        </div>

        <div class="content-card admin-card">
            <h3>🔎 Neue Recherche-Anfragen</h3>
            <p>Offene Recherche-Anfragen mit Person-ID bzw. Person-Name bearbeiten</p>
            <a href="admin-nachrichten.php?filter=offen&amp;typ=Recherche" class="btn btn-primary">Recherche-Anfragen öffnen</a>
        </div>

        <div class="content-card admin-card">
            <h3>📍 Neuen Ort importieren</h3>
            <p>Ort anlegen und passende Datei aus stammbaum-daten auswählen</p>
            <a href="orte-verwaltung.php" class="btn btn-primary">Orte-Verwaltung öffnen</a>
        </div>

        <div class="content-card admin-card todo-card">
        <h3>To Do List:</h3>
        <ul>
            <li>Impressum und Datenschutz - von mir überarbeiten</li>
            <li>Speichern für welchen Namen bezahlt wurde, dieser kann sich jeder zeit wieder angezeigt werden</li>
            <li>Wenn vergleiche fertig sind, noch scha&uuml;n was an ??? übrig ist</li>
            <li>SUCHE anpassen: c/k, b/p, u/ü a/ä Varianten mit erlauben
                Info: meist gängige Schreibweise von heute z.B. Flatscher (in den Büchern mehr als Flatschner )
Christoph Christian 
                Matthäus und Co => Matthias
            </li>
            <li>Blau: Repräsentiert typischerweise die Linie des Großvaters väterlicherseits.
Grün: Repräsentiert die Linie der Großmutter väterlicherseits.
Rot: Repräsentiert die Linie des Großvaters mütterlicherseits.
Gelb: Repräsentiert die Linie der Großmutter mütterlicherseits.
Lila: Wird oft für die direkten Nachkommen verwendet.</li>
            <li>import name "unbekannt" dann den Namen NICHT speichern in Person Tabelle sondern überspringen  </li>
           <br /><br />
            <li><b>Für später:</b>
                <ul>
                    <li>e2e Tests für
                        <ul>
                            <li>katharina Kostenzer (generelle Suche, stimmt gena&uuml; Anzahl?)</li>
                            <li>10.01.1927 Alois Muther</li>
                            <li>29.05.1848 Andreas Eder Bemerkung!</li>
                        </ul>
                        
                    </li>
                    <li>Preisvergleiche:
                        <ul>
                            <li>copecard 4,9% plus 1€ pro Transaktion
                                <ul>
                                    <li>200€ => 9,80€ plus 1€ => 10,80€ damit bleibt bei mir 189,20€ </li>
                                    <li>250€ => 12,25€ => 237,75€ </li>
                                </ul>
                            </li>
                             <li>xxx 4,9% plus 1€ pro Transaktion
                                <ul>
                                    <li>200€ => € </li>
                                    <li>250€ => € </li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <li>paypal Anbindung bzw. Kreditkarte - Bezahlanbieter?</li>
                    <li>kompletten Stammbaum wenn 250€ bezahlt wurden</li>
                    <li>Stammbaum inkl. Tanten und Onkel 500€</li>
                    <li>Transkripbus für die Traubücher ohne Tabellenlayout in latein prüfen</li>
                </ul>
            </li>
        </ul>
        </div>
    </div>
</div>

<div class="admin-group">
    <h2 class="admin-group-title">2) Prüfungen</h2>
    <div class="admin-group-grid">
        <div class="content-card admin-card">
            <h3>👨≈👨 Ähnliche Vornamen</h3>
            <p>Gruppen ähnlicher Vornamen anzeigen</p>
            <a href="vornamen-similar.php" class="btn btn-primary">Vornamen prüfen</a>
        </div>

        <div class="content-card admin-card">
            <h3>👤≈👤 Ähnliche Nachnamen</h3>
            <p>Gruppen ähnlicher Nachnamen anzeigen und mit Tirol-Archiv vergleichen</p>
            <a href="nachnamen-similar.php" class="btn btn-primary">Nachnamen prüfen</a>
        </div>

        <div class="content-card admin-card">
            <h3>🧭 Ehen-Dubletten prüfen</h3>
            <p>Dubletten anhand von Hochzeitsdatum + vollständigen Namen von Bräutigam und Braut</p>
            <a href="ehen-dubletten.php" class="btn btn-primary">Dubletten anzeigen</a>
        </div>

        <div class="content-card admin-card">
            <h3>🗺️ Nachnamen nach Ort</h3>
            <p>Tirol-Archiv-Nachnamen je Ort alphabetisch auflisten</p>
            <a href="nachnamen-orte.php" class="btn btn-primary">Ortslisten öffnen</a>
        </div>

        <div class="content-card admin-card">
            <h3>📚 Nachnamen Tirol (A-Z)</h3>
            <p>Alle vorhandenen Tirol-Archiv-Nachnamen alphabetisch gruppiert anzeigen</p>
            <a href="nachnamen-tirol.php" class="btn btn-primary">Gesamtliste öffnen</a>
        </div>
    </div>
</div>

<?php if (isSuperAdmin()): ?>
<div class="admin-group">
    <h2 class="admin-group-title warning">3) Datenbank verwalten</h2>
    <div class="admin-group-grid">
        <div class="content-card admin-card">
        <h3>⛃ Datenbank löschen und neu erstellen</h3>
        <p>Die Datenbank wird komplett gelöscht und neu erstellt</p>
     	<a href="recreate-db.php" onclick="return confirm('⚠️ WARNUNG: Die Datenbank wird komplett gelöscht und neu erstellt. Möchten Sie fortfahren?');" class="btn btn-warning">⛃ Datenbank löschen und neu erstellen</a>
        </div>

        <div class="content-card admin-card">
        <h3>📝 Thierbach importieren</h3>
        <p>Die Daten von Thierbach werden importiert</p>
        <a href="import-thierbach.php" onclick="return confirm('⚠️ WARNUNG: Die Thierbach-Daten werden importiert. Möchten Sie fortfahren?');" class="btn btn-warning">Thierbach importieren</a>
        </div>

        <div class="content-card admin-card">
        <h3>📝 Ne&uuml; Orte importieren</h3>
        <p>Die Daten von allen anderen Orten werden importiert</p>
        <a href="import-orte.php" onclick="return confirm('⚠️ WARNUNG: Alle Orte-Daten werden importiert. Möchten Sie fortfahren?');" class="btn btn-warning">Orte importieren</a>
        </div>

        <div class="content-card admin-card">
        <h3>🔄 Kompletter Neustart</h3>
        <p>Datenbank zurücksetzen und alle Daten neu importieren</p>
        <a href="re-create-all.php"
           onclick="return confirm('⚠️ WARNUNG: Dies löscht ALLE Daten und importiert alles neu. Möchten Sie fortfahren?');"
           class="btn btn-warning">Neustart</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once '../../layout/footer.php'; ?>