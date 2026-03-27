<?php
$pageTitle = "Traubuch-Liste";
require_once dirname(__DIR__, 2) . '/layout/header.php';
require_once dirname(__DIR__, 2) . '/lib/include.php';

$pdo = getPDO();

// Traubücher aus der Datenbank laden
$traubuecher = [];
try {
    $stmt = $pdo->query("
        SELECT
            e.traubuch,
            COUNT(DISTINCT e.id) AS ehen_anzahl,
            COUNT(DISTINCT p.id) AS personen_anzahl,
            MIN(e.heiratsdatum) AS erstes_datum,
            MAX(e.heiratsdatum) AS letztes_datum
        FROM ehe e
        LEFT JOIN person p ON (p.vater_id IN (SELECT vater_id FROM ehe WHERE traubuch = e.traubuch)
                               OR p.mutter_id IN (SELECT mutter_id FROM ehe WHERE traubuch = e.traubuch))
        WHERE e.traubuch IS NOT NULL AND e.traubuch != ''
        GROUP BY e.traubuch
        ORDER BY e.traubuch
    ");
    $traubuecher = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $traubuecher = [];
}

// Gesamtstatistik
$totalEhen = 0;
foreach ($traubuecher as $tb) {
    $totalEhen += $tb['ehen_anzahl'];
}
?>

<div class="page-header">
    <h1>📚 Traubuch-Liste</h1>
    <p class="subtitle">Übersicht aller verfügbaren Traubücher</p>
</div>

<?php if (!empty($traubuecher)): ?>
<div class="results-section">
    <p style="color:#666; margin-bottom:20px;">
        <strong><?= count($traubuecher) ?></strong> Traubücher gefunden mit insgesamt <strong><?= $totalEhen ?></strong> Ehen-Einträgen.
    </p>
    
    <div class="table-responsive">
        <table class="results-table">
            <thead>
                <tr>
                    <th>Traubuch</th>
                    <th>Anzahl Ehen</th>
                    <th>Erstes Datum</th>
                    <th>Letztes Datum</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($traubuecher as $tb): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($tb['traubuch']) ?></strong></td>
                    <td><?= intval($tb['ehen_anzahl']) ?></td>
                    <td><?= $tb['erstes_datum'] ? formatDBDateOrNull($tb['erstes_datum']) : '—' ?></td>
                    <td><?= $tb['letztes_datum'] ? formatDBDateOrNull($tb['letztes_datum']) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="alert alert-info">
    ℹ️ Keine Traubücher gefunden. Bitte importieren Sie zuerst Daten.
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>
