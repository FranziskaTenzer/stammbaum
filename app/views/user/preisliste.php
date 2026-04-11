<?php
$pageTitle = "Preisliste";
require_once '../../layout/header.php';
?>

<div class="page-header">
    <h1>💶 Preisliste</h1>
    <p class="subtitle">&Uuml;bersicht der aktuellen Preise und Leistungen</p>
    <br/>
    <h2>Hinweis</h2>
    <p>Alle Preise verstehen sich inkl. gesetzlicher Abgaben. Bei R&uuml;ckfragen nutze bitte den Bereich "Nachrichten".</p>
</div>
    <div class="content-grid" style="grid-template-columns: repeat(2, 1fr);">
    <div class="content-card">
        <h3>🔍 Basis Funktionen</h3>
        <p>Suche nach Personen im Stammbaum & Anzeige der Ergebnisse als Tabelle</p>
        <p>Nachrichten an den Admin senden und empfangen</p>
        
        <p><strong>Kosten:</strong> Kostenlos</p>
    </div>

    <div class="content-card">
        <h3>🌳 Anzeige des Stammbaums</h3>
        <p>Auswahl zwischen vertikaler und horizontaler Ansicht (kann auch gewechselt werden)</p>
        <p><strong>Kosten:</strong> 150€ einmalig je ausgewählter Person</p>
    </div>

    <div class="content-card">
        <h3>🌳 Anzeige des kompletten Stammbaums</h3>
        <p>Tanten und Onkel jeder Generation werden mit ausgegeben<br/>Auswahl zwischen vertikaler und horizontaler Ansicht (kann auch gewechselt werden)</p>
        <p><strong>Kosten:</strong> 250€ einmalig je ausgewählter Person</p>
    </div>

    <div class="content-card">
        <h3>📜 Recherche-Anfrage</h3>
        <p>Gezielte Recherche zu Personen, Orten oder Ehen (inkl. Auszug aus den Traubüchern)</p>
        <p><strong>Kosten:</strong> 15,00 EUR pro Person</p>
    </div>

    <div class="content-card">
        <h3>📜 Erweiterte Recherche-Anfrage</h3>
        <p>Erweiterte Recherche zu Personen, Orten oder Ehen (inkl. Auszug aus den Tauf-, Trau- und Sterbebüchern)</p>
        <p><strong>Kosten:</strong> 25,00 EUR pro Person</p>
    </div>
</div>



<?php require_once '../../layout/footer.php'; ?>
