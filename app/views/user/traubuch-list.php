<?php
$pageTitle = "Traubuch-Liste";
require_once dirname(__DIR__, 2) . '/layout/header.php';
require_once dirname(__DIR__, 2) . '/lib/include.php';

$pdo = getPDO();

// Alle verfügbaren Traubücher laden
$stmt = $pdo->query("SELECT DISTINCT traubuch FROM ehe WHERE traubuch IS NOT NULL AND traubuch != '' ORDER BY traubuch");
$traubuecher = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-header">
    <h1>📚 Traubuch-Liste</h1>
    <p class="subtitle">Alle verfügbaren Traubücher im Überblick</p>
</div>

<?php if (!empty($traubuecher)): ?>
<div class="results-section">
    <h2>Verfügbare Traubücher (<?= count($traubuecher) ?>)</h2>
    <div class="table-responsive">
        <table class="results-table">
            <thead>
                <tr>
                    <th>Traubuch</th>
                    <th>Anzahl Einträge</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($traubuecher as $traubuch): ?>
                <?php
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ehe WHERE traubuch = ?");
                    $countStmt->execute([$traubuch]);
                    $count = $countStmt->fetchColumn();
                ?>
                <tr>
                    <td><?= htmlspecialchars($traubuch) ?></td>
                    <td><?= intval($count) ?></td>
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
