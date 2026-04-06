<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "Admin - Ehen-Dubletten";
require_once '../../layout/header.php';
require_once '../../lib/include.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur für Administratoren.');
}

$pdo = getPDO();

function formatDateAdmin($date) {
    if (!$date) {
        return '—';
    }

    try {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d ? $d->format('d.m.Y') : '—';
    } catch (Exception $e) {
        return '—';
    }
}

$rows = [];
$errorMessage = null;

try {
    $sql = "
        SELECT
            e.id AS ehe_id,
            e.heiratsdatum,
            e.traubuch,
            b.vorname AS braeutigam_vorname,
            b.nachname AS braeutigam_nachname,
            f.vorname AS braut_vorname,
            f.nachname AS braut_nachname,
            dup.anzahl AS duplikat_anzahl
        FROM ehe e
        JOIN person b ON b.id = e.mann_id
        JOIN person f ON f.id = e.frau_id
        JOIN (
            SELECT
                e2.heiratsdatum,
                b2.vorname AS braeutigam_vorname,
                b2.nachname AS braeutigam_nachname,
                f2.vorname AS braut_vorname,
                f2.nachname AS braut_nachname,
                COUNT(*) AS anzahl
            FROM ehe e2
            JOIN person b2 ON b2.id = e2.mann_id
            JOIN person f2 ON f2.id = e2.frau_id
            GROUP BY
                e2.heiratsdatum,
                b2.vorname,
                b2.nachname,
                f2.vorname,
                f2.nachname
            HAVING COUNT(*) > 1
        ) dup
            ON (dup.heiratsdatum <=> e.heiratsdatum)
           AND dup.braeutigam_vorname = b.vorname
           AND dup.braeutigam_nachname = b.nachname
           AND dup.braut_vorname = f.vorname
           AND dup.braut_nachname = f.nachname
        ORDER BY
            e.heiratsdatum,
            b.nachname,
            b.vorname,
            f.nachname,
            f.vorname,
            e.id
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}

$grouped = [];
foreach ($rows as $row) {
    $key = implode('|', [
        (string)($row['heiratsdatum'] ?? ''),
        (string)($row['braeutigam_vorname'] ?? ''),
        (string)($row['braeutigam_nachname'] ?? ''),
        (string)($row['braut_vorname'] ?? ''),
        (string)($row['braut_nachname'] ?? ''),
    ]);

    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'heiratsdatum' => $row['heiratsdatum'],
            'braeutigam_vorname' => $row['braeutigam_vorname'],
            'braeutigam_nachname' => $row['braeutigam_nachname'],
            'braut_vorname' => $row['braut_vorname'],
            'braut_nachname' => $row['braut_nachname'],
            'duplikat_anzahl' => (int)$row['duplikat_anzahl'],
            'eintraege' => [],
        ];
    }

    $grouped[$key]['eintraege'][] = [
        'ehe_id' => (int)$row['ehe_id'],
        'traubuch' => (string)($row['traubuch'] ?? ''),
    ];
}
?>

<div class="page-header">
    <h1>🧭 Ehen-Dubletten</h1>
    <p class="subtitle">Dubletten anhand von Hochzeitsdatum + Name Bräutigam + Name Braut</p>
</div>

<div class="content-grid">
    <div class="content-card" style="grid-column: 1 / -1;">
        <a href="home.php" class="btn btn-primary" style="margin-bottom: 15px; display: inline-block;">← Zurück zum Admin-Bereich</a>

        <?php if ($errorMessage): ?>
            <p style="color:#b00020;">❌ Fehler beim Laden: <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
        <?php elseif (empty($grouped)): ?>
            <p style="color:#2e7d32; font-weight:bold;">✅ Keine Dubletten gefunden.</p>
        <?php else: ?>
            <p style="margin-bottom: 16px;">
                Gefundene Dublettengruppen: <strong><?= (int)count($grouped) ?></strong>
            </p>

            <?php foreach ($grouped as $group): ?>
                <div style="border:1px solid #e1e5ef; border-radius:8px; padding:14px; margin-bottom:12px; background:#fafbff;">
                    <div style="font-weight:bold; margin-bottom:8px;">
                        <?= htmlspecialchars($group['braeutigam_vorname'] . ' ' . $group['braeutigam_nachname'], ENT_QUOTES, 'UTF-8') ?>
                        &amp;
                        <?= htmlspecialchars($group['braut_vorname'] . ' ' . $group['braut_nachname'], ENT_QUOTES, 'UTF-8') ?>
                    </div>

                    <div style="font-size:0.95em; color:#555; margin-bottom:8px;">
                        Hochzeitsdatum: <strong><?= htmlspecialchars(formatDateAdmin($group['heiratsdatum']), ENT_QUOTES, 'UTF-8') ?></strong>
                        | Dubletteneinträge: <strong><?= (int)$group['duplikat_anzahl'] ?></strong>
                    </div>

                    <ul style="margin:0; padding-left:18px;">
                        <?php foreach ($group['eintraege'] as $entry): ?>
                            <li>
                                Ehe-ID <?= (int)$entry['ehe_id'] ?>
                                | Traubuch: <strong><?= htmlspecialchars($entry['traubuch'] !== '' ? $entry['traubuch'] : '—', ENT_QUOTES, 'UTF-8') ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>
