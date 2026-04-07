<?php
$pageTitle = "Personensuche";
require_once '../../layout/header.php';
require_once '../../lib/include.php';

$nameVariantConfigPath = __DIR__ . '/../../lib/stammbaum-search-mappings.php';
$nameVariantConfig = file_exists($nameVariantConfigPath) ? require $nameVariantConfigPath : ['vorname' => [], 'nachname' => []];

function normalizeNameForVariantLookup(string $value): string
{
    return mb_strtolower(trim($value), 'UTF-8');
}

function expandNameVariants(string $input, array $groups): array
{
    $trimmed = trim($input);
    if ($trimmed === '') {
        return [];
    }

    $normalizedInput = normalizeNameForVariantLookup($trimmed);
    $terms = [$trimmed => true];

    foreach ($groups as $group) {
        if (!is_array($group) || empty($group)) {
            continue;
        }

        $normalizedGroup = array_map(
            static fn($entry) => normalizeNameForVariantLookup((string)$entry),
            $group
        );

        if (!in_array($normalizedInput, $normalizedGroup, true)) {
            continue;
        }

        foreach ($group as $variant) {
            $variant = trim((string)$variant);
            if ($variant !== '') {
                $terms[$variant] = true;
            }
        }
    }

    return array_keys($terms);
}

$pdo = getPDO();
$results = [];

$vorname = $_GET['vorname'] ?? '';
$nachname = $_GET['nachname'] ?? '';
$geburtsdatum = $_GET['geburtsdatum'] ?? null;

if (!empty($vorname) && !empty($nachname)) {
    $vornameTerms = expandNameVariants($vorname, $nameVariantConfig['vorname'] ?? []);
    $nachnameTerms = expandNameVariants($nachname, $nameVariantConfig['nachname'] ?? []);

    $vornameConditions = [];
    $nachnameConditions = [];
    $params = [];

    foreach ($vornameTerms as $index => $term) {
        $key = ':vorname' . $index;
        $vornameConditions[] = 'p.vorname LIKE ' . $key;
        $params[$key] = '%' . $term . '%';
    }

    foreach ($nachnameTerms as $index => $term) {
        $key = ':nachname' . $index;
        $nachnameConditions[] = 'p.nachname LIKE ' . $key;
        $params[$key] = '%' . $term . '%';
    }

    if (empty($vornameConditions) || empty($nachnameConditions)) {
        $results = [];
    } else {
    $sql = "
SELECT
    p.id,
    p.vorname,
    p.nachname,
    p.geburtsdatum,
    p.geburtsort,
    p.sterbedatum,
    p.sterbeort,
    p.hof,
    p.ort,
    p.bemerkung,
        
    v.vorname AS vater_vorname,
    v.nachname AS vater_nachname,
        
    m.vorname AS mutter_vorname,
    m.nachname AS mutter_nachname,
    (
        SELECT GROUP_CONCAT(
            DISTINCT TRIM(CONCAT(sp.vorname, ' ', sp.nachname))
            ORDER BY sp.nachname, sp.vorname
            SEPARATOR ', '
        )
        FROM ehe e
        JOIN person sp
          ON sp.id = CASE
                            WHEN e.mann_id = p.id THEN e.frau_id
                            ELSE e.mann_id
          END
                WHERE e.mann_id = p.id OR e.frau_id = p.id
    ) AS ehepartner
        
FROM person p
        
LEFT JOIN person v ON v.id = p.vater_id
LEFT JOIN person m ON m.id = p.mutter_id
        
WHERE (" . implode(' OR ', $vornameConditions) . ")
AND (" . implode(' OR ', $nachnameConditions) . ")
";
    
    if (!empty($geburtsdatum)) {
        $sql .= " AND p.geburtsdatum = :geburtsdatum ";
    }
    
    $stmt = $pdo->prepare($sql);
    
    if (!empty($geburtsdatum)) {
        $params[':geburtsdatum'] = $geburtsdatum;
    }
    
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<div class="page-header">
    <h1>👤 Personensuche</h1>
    <p class="subtitle">Suchen Sie nach Personen im Stammbaum. Die Kirchenbucheinträge gehen nur bis 1939.</p>
    <p>Hinweis: zur besseren Darstellung und Zuordnung, sind schreibweisen mit C zu K geändert z.B. Katharina, Kaspar, Klar
        Ausnahme: Cäcilia
        und ph zu f z.B. Josef, Stefan etc.
    Johann zählt auch für Johannes, Andrä ist teilweise als Andreas gespeichert
    Nachnamen eher mit der altdeutschen Variante z.B. Schießling, Weißbacher 
    </p>
</div>

<div class="search-box">
    <form method="GET" class="search-form">
        <div class="form-group">
            <label for="vorname">Vorname:</label>
            <input type="text" id="vorname" name="vorname" placeholder="z.B. Maria" value="<?= htmlspecialchars($vorname) ?>">
        </div>
        
        <div class="form-group">
            <label for="nachname">Nachname:</label>
            <input type="text" id="nachname" name="nachname" placeholder="z.B. Müller" value="<?= htmlspecialchars($nachname) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="geburtsdatum">Geburtsdatum (optional):</label>
      		<input type="text" id="geburtsdatum" name="geburtsdatum" placeholder="z.B. 25.12.1982 oder xx.12.1982" value="<?= htmlspecialchars($geburtsdatum ?? '') ?>">  </div>
        
        <button type="submit" class="btn btn-primary">🔍 Suchen</button>
    </form>
</div>

<?php if (!empty($vorname) && !empty($nachname)): ?>
<div class="results-section">
    <h2>Suchergebnisse (<?= count($results) ?> Treffer)</h2>
    
    <?php if (count($results) > 0): ?>
        <div class="table-responsive">
            <table class="results-table">
                <thead>
                    <tr  style="height:20px; overflow:hidden">
                        <th>ID</th>
                        <th>Name</th>
                        <th>Vater</th>
                        <th>Mutter</th>
                        <th>Ehepartner</th>
                        <th>Geburtsdatum</th>
                        <th>Geburtsort</th>
                        <th>Sterbedatum</th>
                        <th>Sterbeort</th>
                        <th>Hof</th>
                        <th>Ort</th>
                        <th>Bemerkung</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                    <tr  style="height:20px; overflow:hidden">
                        <td ><?= htmlspecialchars($row['id']) ?></td>
                        <td><strong><?= htmlspecialchars($row['vorname'] . ' ' . $row['nachname']) ?></strong></td>
                        <td><?= htmlspecialchars(($row['vater_vorname'] ?? '') . ' ' . ($row['vater_nachname'] ?? '')) ?></td>
                        <td><?= htmlspecialchars(($row['mutter_vorname'] ?? '') . ' ' . ($row['mutter_nachname'] ?? '')) ?></td>
                        <td><?= htmlspecialchars(!empty($row['ehepartner']) ? $row['ehepartner'] : '-') ?></td>
                        <td><?= formatDBDateOrNull($row['geburtsdatum']) ?></td>
                        <td><?= htmlspecialchars($row['geburtsort'] ?? '') ?></td>
                        <td><?= formatDBDateOrNull($row['sterbedatum']) ?></td>
                        <td><?= htmlspecialchars($row['sterbeort'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['hof'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['ort'] ?? '') ?></td>
                        <td><?= htmlspecialchars(substr($row['bemerkung'] ?? '', 0, 30)) ?></td>
                        <td>
                            <a href="stammbaum-display.php?id=<?= $row['id'] ?>" class="btn btn-small btn-link">Stammbaum</a>
                            <a href="stammbaum-display-extended.php?id=<?= $row['id'] ?>" class="btn btn-small btn-link">Stammbaum komplett</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            ℹ️ Keine Ergebnisse gefunden. Versuchen Sie, die Suchkriterien zu ändern.
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once '../../layout/footer.php'; ?>