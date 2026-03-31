<?php
$pageTitle = "Stammbaum Uebersicht";
$extraHead = '<style>
.tree-page {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}

.tree-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.tree-grid {
    display: grid;
    grid-template-columns: 1fr;
    grid-template-rows: auto auto auto;
    gap: 16px;
    align-items: start;
}

.tree-panel {
    background: #ffffff;
    border: 1px solid #dce4ef;
    border-radius: 14px;
    box-shadow: 0 10px 24px rgba(7, 37, 72, 0.07);
    padding: 16px;
    overflow: auto;
    max-height: 55vh;
}

.tree-panel.ancestors {
    border-top: 4px solid #2c8a67;
}

.tree-panel.descendants {
    border-top: 4px solid #a55e1d;
}

.tree-panel h3 {
    margin: 0 0 10px;
    color: #103d63;
}

.focus-card {
    background: linear-gradient(145deg, #f6fbff 0%, #eef7ff 100%);
    border: 1px solid #c9def4;
    border-radius: 14px;
    padding: 16px;
    box-shadow: 0 10px 24px rgba(7, 37, 72, 0.1);
}

.focus-name {
    margin: 0;
    font-size: 1.3rem;
    color: #0a3353;
}

.meta-line {
    margin: 4px 0;
    color: #2b4a66;
}

.generation-list {
    display: grid;
    gap: 14px;
}

.generation {
    background: #f8fbff;
    border: 1px solid #d6e3f0;
    border-radius: 12px;
    padding: 10px;
}

.tree-panel.ancestors .generation {
    background: #f4fbf7;
    border-color: #d3e9dd;
}

.tree-panel.descendants .generation {
    background: #fdf8f3;
    border-color: #eddcc8;
}

.generation-title {
    margin: 0 0 8px;
    font-weight: 700;
    color: #103d63;
}

.generation-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 10px;
}

.person-card {
    background: #ffffff;
    border: 1px solid #ccdcec;
    border-left: 4px solid #2f7cad;
    border-radius: 10px;
    padding: 8px 10px;
    box-shadow: 0 3px 10px rgba(7, 37, 72, 0.05);
}

.tree-panel.ancestors .person-card {
    border-left-color: #2c8a67;
}

.tree-panel.descendants .person-card {
    border-left-color: #a55e1d;
}

.person-line {
    margin: 0;
    color: #123b5c;
    font-weight: 600;
}

.person-line.secondary {
    font-weight: 500;
    color: #2a587d;
    margin-top: 4px;
}

.person-spouse {
    margin-top: 4px;
}

.subtle {
    color: #4a6781;
    font-size: 0.9rem;
}

.person-relation {
    margin-top: 4px;
}

@media (max-width: 1100px) {
    .tree-panel {
        max-height: none;
    }

    .generation-row {
        grid-template-columns: 1fr;
    }
}
</style>';

require_once '../../layout/header.php';
require_once '../../lib/include.php';

$pdo = getPDO();
$startId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($startId <= 0) {
    echo '<div class="alert alert-warning">Keine gueltige Personen-ID uebergeben.</div>';
    require_once '../../layout/footer.php';
    exit;
}

function fetchPersonById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM person WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $person = $stmt->fetch(PDO::FETCH_ASSOC);
    return $person ?: null;
}

function collectRelevantIds(PDO $pdo, int $startId): array
{
    $visited = [];
    $queue = [$startId];

    while (!empty($queue)) {
        $current = array_pop($queue);
        if (isset($visited[$current])) {
            continue;
        }

        $visited[$current] = true;

        $stmtParents = $pdo->prepare('SELECT vater_id, mutter_id FROM person WHERE id = :id');
        $stmtParents->execute(['id' => $current]);
        $parents = $stmtParents->fetch(PDO::FETCH_ASSOC) ?: [];

        if (!empty($parents['vater_id'])) {
            $queue[] = (int)$parents['vater_id'];
        }
        if (!empty($parents['mutter_id'])) {
            $queue[] = (int)$parents['mutter_id'];
        }

        $stmtChildren = $pdo->prepare('SELECT id FROM person WHERE vater_id = :id OR mutter_id = :id');
        $stmtChildren->execute(['id' => $current]);
        $children = $stmtChildren->fetchAll(PDO::FETCH_COLUMN);

        foreach ($children as $childId) {
            $queue[] = (int)$childId;
        }
    }

    return array_map('intval', array_keys($visited));
}

function loadTreeData(PDO $pdo, int $startId): array
{
    $ids = collectRelevantIds($pdo, $startId);
    if (empty($ids)) {
        return [[], [], []];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmtPersons = $pdo->prepare("SELECT * FROM person WHERE id IN ($placeholders)");
    $stmtPersons->execute($ids);
    $persons = $stmtPersons->fetchAll(PDO::FETCH_ASSOC);

    $personsById = [];
    $childrenMap = [];

    foreach ($persons as $person) {
        $personId = (int)$person['id'];
        $personsById[$personId] = $person;

        if (!empty($person['vater_id'])) {
            $fatherId = (int)$person['vater_id'];
            $childrenMap[$fatherId][] = $personId;
        }

        if (!empty($person['mutter_id'])) {
            $motherId = (int)$person['mutter_id'];
            $childrenMap[$motherId][] = $personId;
        }
    }

    $stmtEhen = $pdo->prepare(
        "SELECT e.mann_id, e.frau_id, e.heiratsdatum, e.scheidungsdatum,
                v.vorname AS v_vorname, v.nachname AS v_nachname,
                m.vorname AS m_vorname, m.nachname AS m_nachname
         FROM ehe e
         LEFT JOIN person v ON v.id = e.mann_id
         LEFT JOIN person m ON m.id = e.frau_id
         WHERE e.mann_id IN ($placeholders) OR e.frau_id IN ($placeholders)"
    );
    $stmtEhen->execute(array_merge($ids, $ids));
    $ehen = $stmtEhen->fetchAll(PDO::FETCH_ASSOC);

    $spouseMap = [];
    $coupleEventMap = [];
    foreach ($ehen as $ehe) {
        $fatherId = !empty($ehe['mann_id']) ? (int)$ehe['mann_id'] : 0;
        $motherId = !empty($ehe['frau_id']) ? (int)$ehe['frau_id'] : 0;

        if ($fatherId > 0 && $motherId > 0) {
            $pairEvents = [
                'heiratsdatum' => $ehe['heiratsdatum'] ?? null,
                'scheidungsdatum' => $ehe['scheidungsdatum'] ?? null
            ];
            $coupleEventMap[$fatherId . '-' . $motherId] = $pairEvents;
            $coupleEventMap[$motherId . '-' . $fatherId] = $pairEvents;
        }

        if ($fatherId > 0 && !empty($ehe['m_vorname'])) {
            $spouseMap[$fatherId][] = [
                'name' => trim($ehe['m_vorname'] . ' ' . $ehe['m_nachname']),
                'heiratsdatum' => $ehe['heiratsdatum'] ?? null,
                'scheidungsdatum' => $ehe['scheidungsdatum'] ?? null
            ];
        }

        if ($motherId > 0 && !empty($ehe['v_vorname'])) {
            $spouseMap[$motherId][] = [
                'name' => trim($ehe['v_vorname'] . ' ' . $ehe['v_nachname']),
                'heiratsdatum' => $ehe['heiratsdatum'] ?? null,
                'scheidungsdatum' => $ehe['scheidungsdatum'] ?? null
            ];
        }
    }

    return [$personsById, $childrenMap, $spouseMap, $coupleEventMap];
}

function formatPersonLine(array $person): string
{
    $parts = [];
    $parts[] = trim(($person['vorname'] ?? '') . ' ' . ($person['nachname'] ?? ''));

    if (!empty($person['geburtsdatum'])) {
        $parts[] = '* ' . formatDBDateOrNull($person['geburtsdatum']);
    }

    if (!empty($person['sterbedatum'])) {
        $parts[] = '† ' . formatDBDateOrNull($person['sterbedatum']);
    }

    return implode(' | ', $parts);
}

function personShortName(?array $person): string
{
    if (!$person) {
        return 'Unbekannt';
    }

    $name = trim(($person['vorname'] ?? '') . ' ' . ($person['nachname'] ?? ''));
    return $name !== '' ? $name : 'Unbekannt';
}

function personByIdShortName(int $personId, array $personsById): string
{
    return personShortName($personsById[$personId] ?? null);
}

function formatCoupleLine(int $fatherId, int $motherId, array $personsById): string
{
    $fatherName = $fatherId > 0 ? personByIdShortName($fatherId, $personsById) : '';
    $motherName = $motherId > 0 ? personByIdShortName($motherId, $personsById) : '';

    if ($fatherName !== '' && $motherName !== '') {
        return $fatherName . ' + ' . $motherName;
    }
    if ($fatherName !== '') {
        return $fatherName;
    }
    if ($motherName !== '') {
        return $motherName;
    }

    return 'Unbekannt';
}

function formatSpouseSummary(int $personId, array $spouseMap): string
{
    if (empty($spouseMap[$personId])) {
        return '';
    }

    $items = [];
    foreach ($spouseMap[$personId] as $entry) {
        $events = [];

        if (!empty($entry['heiratsdatum'])) {
            $events[] = '⚭ ' . formatDBDateOrNull($entry['heiratsdatum']);
        }

        if (!empty($entry['scheidungsdatum'])) {
            $events[] = 'geschieden ' . formatDBDateOrNull($entry['scheidungsdatum']);
        }

        $items[] = !empty($events)
            ? ($entry['name'] . ' (' . implode(' | ', $events) . ')')
            : $entry['name'];
    }

    $items = array_values(array_unique($items));
    return implode(', ', $items);
}

function renderPersonCard(int $personId, array $personsById, array $spouseMap, string $relationLine = ''): string
{
    if (!isset($personsById[$personId])) {
        return '';
    }

    $person = $personsById[$personId];
    $line = htmlspecialchars(formatPersonLine($person), ENT_QUOTES, 'UTF-8');
    $spouseSummary = formatSpouseSummary($personId, $spouseMap);
    $spouseLine = htmlspecialchars($spouseSummary !== '' ? $spouseSummary : '-', ENT_QUOTES, 'UTF-8');

    $html = '<article class="person-card">';
    $html .= '<p class="person-line">' . $line . '</p>';
    $html .= '<p class="subtle person-spouse">Ehepartner: ' . $spouseLine . '</p>';
    if ($relationLine !== '') {
        $html .= '<p class="subtle person-relation">' . htmlspecialchars($relationLine, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $html .= '</article>';

    return $html;
}

function ancestorLabelByDepth(int $depth): string
{
    if ($depth === 1) {
        return 'Eltern';
    }
    if ($depth === 2) {
        return 'Grosseltern';
    }
    return $depth . '. Vorfahrengeneration';
}

function descendantLabelByDepth(int $depth): string
{
    if ($depth === 1) {
        return 'Kinder';
    }
    if ($depth === 2) {
        return 'Enkelkinder';
    }
    return $depth . '. Nachkommengeneration';
}

function parentUnitForPerson(int $personId, array $personsById): ?array
{
    if (!isset($personsById[$personId])) {
        return null;
    }

    $person = $personsById[$personId];
    $fatherId = !empty($person['vater_id']) ? (int)$person['vater_id'] : 0;
    $motherId = !empty($person['mutter_id']) ? (int)$person['mutter_id'] : 0;

    if ($fatherId <= 0 && $motherId <= 0) {
        return null;
    }

    return ['father_id' => $fatherId, 'mother_id' => $motherId];
}

function parentUnitKey(int $fatherId, int $motherId): string
{
    return $fatherId . '-' . $motherId;
}

function collectAncestorUnitsByLevel(int $startId, array $personsById, int $maxDepth = 12): array
{
    if (!isset($personsById[$startId])) {
        return [];
    }

    $levels = [];
    $rootUnit = parentUnitForPerson($startId, $personsById);
    if (!$rootUnit) {
        return [];
    }

    $currentLevel = [[
        'father_id' => $rootUnit['father_id'],
        'mother_id' => $rootUnit['mother_id'],
        'from_ids' => [$startId]
    ]];

    $depth = 1;
    while (!empty($currentLevel) && $depth <= $maxDepth) {
        $levels[$depth] = $currentLevel;

        $nextMap = [];
        foreach ($currentLevel as $unit) {
            $memberIds = [];
            if (!empty($unit['father_id'])) {
                $memberIds[] = (int)$unit['father_id'];
            }
            if (!empty($unit['mother_id'])) {
                $memberIds[] = (int)$unit['mother_id'];
            }

            foreach ($memberIds as $memberId) {
                $parentUnit = parentUnitForPerson($memberId, $personsById);
                if (!$parentUnit) {
                    continue;
                }

                $fatherId = (int)$parentUnit['father_id'];
                $motherId = (int)$parentUnit['mother_id'];
                $key = parentUnitKey($fatherId, $motherId);

                if (!isset($nextMap[$key])) {
                    $nextMap[$key] = [
                        'father_id' => $fatherId,
                        'mother_id' => $motherId,
                        'from_ids' => []
                    ];
                }

                $nextMap[$key]['from_ids'][$memberId] = true;
            }
        }

        $nextLevel = [];
        foreach ($nextMap as $unit) {
            $fromIds = array_map('intval', array_keys($unit['from_ids']));
            sort($fromIds);
            $unit['from_ids'] = $fromIds;
            $nextLevel[] = $unit;
        }

        $currentLevel = $nextLevel;
        $depth++;
    }

    return $levels;
}

function collectDescendantLevels(int $startId, array $personsById, array $childrenMap, int $maxDepth = 12): array
{
    if (!isset($personsById[$startId])) {
        return [];
    }

    $levels = [];
    $seen = [$startId => true];
    $currentLevel = [];

    foreach ($childrenMap[$startId] ?? [] as $childId) {
        $childId = (int)$childId;
        if (isset($personsById[$childId])) {
            $currentLevel[$childId] = $startId;
            $seen[$childId] = true;
        }
    }

    $depth = 1;
    while (!empty($currentLevel) && $depth <= $maxDepth) {
        ksort($currentLevel);
        $levelItems = [];
        foreach ($currentLevel as $personId => $fromId) {
            $levelItems[] = ['id' => (int)$personId, 'from' => (int)$fromId];
        }
        $levels[$depth] = $levelItems;

        $nextLevel = [];
        foreach ($currentLevel as $personId => $_fromId) {
            foreach ($childrenMap[$personId] ?? [] as $childId) {
                $childId = (int)$childId;
                if (isset($personsById[$childId]) && !isset($seen[$childId])) {
                    $nextLevel[$childId] = (int)$personId;
                    $seen[$childId] = true;
                }
            }
        }

        $currentLevel = $nextLevel;
        $depth++;
    }

    return $levels;
}

function renderAncestorGenerations(array $levels, array $personsById): string
{
    if (empty($levels)) {
        return '<p class="subtle">Keine Eintraege vorhanden.</p>';
    }

    krsort($levels);

    $html = '<div class="generation-list">';
    foreach ($levels as $depth => $units) {
        $depth = (int)$depth;
        $title = ancestorLabelByDepth($depth);

        $html .= '<section class="generation">';
        $html .= '<h4 class="generation-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h4>';
        $html .= '<div class="generation-row">';

        foreach ($units as $unit) {
            $fatherId = (int)($unit['father_id'] ?? 0);
            $motherId = (int)($unit['mother_id'] ?? 0);
            $fromIds = $unit['from_ids'] ?? [];

            $line1 = htmlspecialchars(formatCoupleLine($fatherId, $motherId, $personsById), ENT_QUOTES, 'UTF-8');

            $originNames = [];
            foreach ($fromIds as $fromId) {
                $originNames[] = personByIdShortName((int)$fromId, $personsById);
            }
            $originNames = array_values(array_unique($originNames));
            sort($originNames);
            $relation = 'Eltern von: ' . (!empty($originNames) ? implode(', ', $originNames) : '-');

            $html .= '<article class="person-card">';
            $html .= '<p class="person-line">' . $line1 . '</p>';
            $html .= '<p class="subtle person-relation">' . htmlspecialchars($relation, ENT_QUOTES, 'UTF-8') . '</p>';
            $html .= '</article>';
        }

        $html .= '</div>';
        $html .= '</section>';
    }
    $html .= '</div>';

    return $html;
}

function formatCoupleEvents(int $personAId, int $personBId, array $coupleEventMap): string
{
    if ($personAId <= 0 || $personBId <= 0) {
        return '';
    }

    $events = $coupleEventMap[$personAId . '-' . $personBId] ?? null;
    if (!$events) {
        return '';
    }

    $parts = [];
    if (!empty($events['heiratsdatum'])) {
        $parts[] = '⚭ ' . formatDBDateOrNull($events['heiratsdatum']);
    }
    if (!empty($events['scheidungsdatum'])) {
        $parts[] = 'geschieden ' . formatDBDateOrNull($events['scheidungsdatum']);
    }

    return implode(' | ', $parts);
}

function renderAncestorGenerationsWithEvents(array $levels, array $personsById, array $coupleEventMap): string
{
    if (empty($levels)) {
        return '<p class="subtle">Keine Eintraege vorhanden.</p>';
    }

    krsort($levels);

    $html = '<div class="generation-list">';
    foreach ($levels as $depth => $units) {
        $depth = (int)$depth;
        $title = ancestorLabelByDepth($depth);

        $html .= '<section class="generation">';
        $html .= '<h4 class="generation-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h4>';
        $html .= '<div class="generation-row">';

        foreach ($units as $unit) {
            $fatherId = (int)($unit['father_id'] ?? 0);
            $motherId = (int)($unit['mother_id'] ?? 0);
            $fromIds = $unit['from_ids'] ?? [];

            $line1 = htmlspecialchars(formatCoupleLine($fatherId, $motherId, $personsById), ENT_QUOTES, 'UTF-8');

            $originNames = [];
            foreach ($fromIds as $fromId) {
                $originNames[] = personByIdShortName((int)$fromId, $personsById);
            }
            $originNames = array_values(array_unique($originNames));
            sort($originNames);
            $relation = 'Eltern von: ' . (!empty($originNames) ? implode(', ', $originNames) : '-');

            $events = formatCoupleEvents($fatherId, $motherId, $coupleEventMap);

            $html .= '<article class="person-card">';
            $html .= '<p class="person-line">' . $line1 . '</p>';
            if ($events !== '') {
                $html .= '<p class="subtle person-spouse">Ehe: ' . htmlspecialchars($events, ENT_QUOTES, 'UTF-8') . '</p>';
            }
            $html .= '<p class="subtle person-relation">' . htmlspecialchars($relation, ENT_QUOTES, 'UTF-8') . '</p>';
            $html .= '</article>';
        }

        $html .= '</div>';
        $html .= '</section>';
    }
    $html .= '</div>';

    return $html;
}

function renderDescendantGenerations(array $levels, array $personsById, array $spouseMap): string
{
    if (empty($levels)) {
        return '<p class="subtle">Keine Eintraege vorhanden.</p>';
    }

    $html = '<div class="generation-list">';
    foreach ($levels as $depth => $items) {
        $depth = (int)$depth;
        $title = descendantLabelByDepth($depth);

        $html .= '<section class="generation">';
        $html .= '<h4 class="generation-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h4>';
        $html .= '<div class="generation-row">';

        foreach ($items as $item) {
            $personId = (int)($item['id'] ?? 0);
            $fromId = (int)($item['from'] ?? 0);
            $relation = $fromId > 0 ? ('Kind von: ' . personByIdShortName($fromId, $personsById)) : '';
            $html .= renderPersonCard($personId, $personsById, $spouseMap, $relation);
        }

        $html .= '</div>';
        $html .= '</section>';
    }
    $html .= '</div>';

    return $html;
}

$focusPerson = fetchPersonById($pdo, $startId);
if (!$focusPerson) {
    echo '<div class="alert alert-warning">Person nicht gefunden.</div>';
    require_once '../../layout/footer.php';
    exit;
}

list($personsById, $childrenMap, $spouseMap, $coupleEventMap) = loadTreeData($pdo, $startId);

$ancestorLevels = collectAncestorUnitsByLevel($startId, $personsById, 12);
$descendantLevels = collectDescendantLevels($startId, $personsById, $childrenMap, 12);

$focusName = htmlspecialchars(trim(($focusPerson['vorname'] ?? '') . ' ' . ($focusPerson['nachname'] ?? '')), ENT_QUOTES, 'UTF-8');
$focusBirth = !empty($focusPerson['geburtsdatum']) ? formatDBDateOrNull($focusPerson['geburtsdatum']) : '-';
$focusDeath = !empty($focusPerson['sterbedatum']) ? formatDBDateOrNull($focusPerson['sterbedatum']) : '-';
$focusSpouses = htmlspecialchars(formatSpouseSummary($startId, $spouseMap) ?: '-', ENT_QUOTES, 'UTF-8');
?>

<div class="tree-page">
    <div class="tree-actions">
        <a href="stammbaum-search.php?vorname=<?= urlencode($focusPerson['vorname'] ?? '') ?>&nachname=<?= urlencode($focusPerson['nachname'] ?? '') ?>" class="btn btn-primary">Zur&uuml;ck zur Suche</a>
        <a href="stammbaum-display.php?id=<?= (int)$startId ?>" class="btn btn-primary">Standardansicht</a>
    </div>

    <div class="tree-grid">
        <section class="tree-panel ancestors">
            <h3>Vorfahren (komplett aus DB)</h3>
            <?= renderAncestorGenerationsWithEvents($ancestorLevels, $personsById, $coupleEventMap) ?>
        </section>

        <section class="focus-card">
            <h2 class="focus-name"><?= $focusName ?></h2>
            <p class="meta-line">Geboren: <?= htmlspecialchars($focusBirth, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="meta-line">Gestorben: <?= htmlspecialchars($focusDeath, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="meta-line">Ehepartner: <?= $focusSpouses ?></p>
            <p class="subtle">Diese Ansicht zeigt Vorfahren und Nachkommen so tief, wie sie in der Datenbank verf&uuml;gbar sind.</p>
        </section>

        <section class="tree-panel descendants">
            <h3>Nachkommen (komplett aus DB)</h3>
            <?= renderDescendantGenerations($descendantLevels, $personsById, $spouseMap) ?>
        </section>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>
