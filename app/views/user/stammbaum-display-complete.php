<?php
$pageTitle = "Stammbaum Komplette Ansicht";
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

.tree-actions .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    line-height: 1.2;
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

.blurred-text {
    color: transparent;
    text-shadow: 0 0 8px rgba(0,0,0,0.5);
    filter: blur(4px);
    user-select: none;
}

@media (max-width: 1100px) {
    .tree-panel {
        max-height: none;
    }

    .generation-row {
        grid-template-columns: 1fr;
    }
}

@media print {
    @page {
        size: auto;
        margin: 12mm;
    }

    .sidebar,
    .hamburger-menu,
    .sidebar-overlay,
    .sidebar-backdrop,
    .tree-actions {
        display: none !important;
    }

    .main-content,
    .content-wrapper {
        height: auto !important;
        overflow: visible !important;
        padding: 0 !important;
    }

    .tree-grid {
        display: block !important;
    }

    .tree-panel,
    .focus-card {
        max-height: none !important;
        overflow: visible !important;
        box-shadow: none !important;
        page-break-inside: avoid;
        margin-bottom: 10mm;
    }

    .generation,
    .person-card {
        page-break-inside: avoid;
    }
}
</style>';

require_once '../../layout/header.php';
require_once '../../lib/include.php';

$pdo = getPDO();
$startId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$username = $_SESSION['username'] ?? '';
$isTestAccount = ($username === 'TestAccount');

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

function getBlurClass(bool $isTestAccount): string
{
    return '';
}

function formatBirthDeathSuffixForPerson(array $person): string
{
    $parts = [];
    if (!empty($person['geburtsdatum'])) {
        $parts[] = '* ' . formatDBDateOrNull($person['geburtsdatum']);
    }
    if (!empty($person['sterbedatum'])) {
        $parts[] = '† ' . formatDBDateOrNull($person['sterbedatum']);
    }
    return !empty($parts) ? (' | ' . implode(' | ', $parts)) : '';
}

function anonymizedDescendantLabelExtended(int $depth, int $index): string
{
    if ($depth <= 1) {
        return 'Kind ' . $index;
    }
    if ($depth === 2) {
        return 'Enkel ' . $index;
    }
    return str_repeat('Ur', $depth - 2) . 'enkel ' . $index;
}

function anonymizedAncestorRoleExtended(bool $isFather, int $depth): string
{
    if ($depth <= 1) {
        return $isFather ? 'Vater' : 'Mutter';
    }
    if ($depth === 2) {
        return $isFather ? 'Grossvater' : 'Grossmutter';
    }
    return str_repeat('Ur', $depth - 2) . ($isFather ? 'grossvater' : 'grossmutter');
}

function anonymizedAncestorSourceLabelExtended(int $depth, int $index, ?array $person): string
{
    if ($depth <= 1) {
        return 'Kind ' . $index;
    }

    $isMale = (($person['geschlecht'] ?? '') === 'm');
    return anonymizedAncestorRoleExtended($isMale, $depth - 1);
}

function anonymizedSpouseSummaryExtended(int $personId, array $spouseMap): string
{
    if (empty($spouseMap[$personId])) {
        return '';
    }

    $items = [];
    $index = 0;
    foreach ($spouseMap[$personId] as $entry) {
        $index++;
        $events = [];

        if (!empty($entry['heiratsdatum'])) {
            $events[] = '⚭ ' . formatDBDateOrNull($entry['heiratsdatum']);
        }

        if (!empty($entry['scheidungsdatum'])) {
            $events[] = 'geschieden ' . formatDBDateOrNull($entry['scheidungsdatum']);
        }

        $label = 'Ehepartner ' . $index;
        $items[] = !empty($events) ? ($label . ' (' . implode(' | ', $events) . ')') : $label;
    }

    return implode(', ', $items);
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

function renderPersonCard(
    int $personId,
    array $personsById,
    array $spouseMap,
    string $relationLine = '',
    bool $isTestAccount = false,
    int $depth = 1,
    int $index = 1
): string {
    if (!isset($personsById[$personId])) {
        return '';
    }

    $person = $personsById[$personId];
    $personLineRaw = $isTestAccount
        ? (anonymizedDescendantLabelExtended($depth, $index) . formatBirthDeathSuffixForPerson($person))
        : formatPersonLine($person);
    $line = htmlspecialchars($personLineRaw, ENT_QUOTES, 'UTF-8');
    $spouseSummary = $isTestAccount
        ? anonymizedSpouseSummaryExtended($personId, $spouseMap)
        : formatSpouseSummary($personId, $spouseMap);
    $spouseLine = htmlspecialchars($spouseSummary !== '' ? $spouseSummary : '-', ENT_QUOTES, 'UTF-8');
    $blurClass = getBlurClass($isTestAccount);
    $lineClass = $blurClass !== '' ? ' class="person-line ' . $blurClass . '"' : ' class="person-line"';
    $spouseClass = $blurClass !== '' ? ' class="subtle person-spouse ' . $blurClass . '"' : ' class="subtle person-spouse"';
    $relationClass = $blurClass !== '' ? ' class="subtle person-relation ' . $blurClass . '"' : ' class="subtle person-relation"';

    $html = '<article class="person-card">';
    $html .= '<p' . $lineClass . '>' . $line . '</p>';
    $html .= '<p' . $spouseClass . '>Ehepartner: ' . $spouseLine . '</p>';
    if ($relationLine !== '') {
        $html .= '<p' . $relationClass . '>' . htmlspecialchars($relationLine, ENT_QUOTES, 'UTF-8') . '</p>';
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

function collectAncestorPeopleByLevel(int $startId, array $personsById, int $maxDepth = 12): array
{
    if (!isset($personsById[$startId])) {
        return [];
    }

    $rootUnit = parentUnitForPerson($startId, $personsById);
    if (!$rootUnit) {
        return [];
    }

    $currentLevel = [];
    if (!empty($rootUnit['father_id'])) {
        $currentLevel[(int)$rootUnit['father_id']] = true;
    }
    if (!empty($rootUnit['mother_id'])) {
        $currentLevel[(int)$rootUnit['mother_id']] = true;
    }

    $levels = [];
    $visited = [$startId => true];
    $depth = 1;

    while (!empty($currentLevel) && $depth <= $maxDepth) {
        $ids = array_map('intval', array_keys($currentLevel));
        sort($ids);
        $levels[$depth] = $ids;

        $nextLevel = [];
        foreach ($ids as $personId) {
            $visited[$personId] = true;
            $parentUnit = parentUnitForPerson($personId, $personsById);
            if (!$parentUnit) {
                continue;
            }

            $fatherId = (int)($parentUnit['father_id'] ?? 0);
            $motherId = (int)($parentUnit['mother_id'] ?? 0);

            if ($fatherId > 0 && !isset($visited[$fatherId])) {
                $nextLevel[$fatherId] = true;
            }
            if ($motherId > 0 && !isset($visited[$motherId])) {
                $nextLevel[$motherId] = true;
            }
        }

        $currentLevel = $nextLevel;
        $depth++;
    }

    return $levels;
}

function collectSiblingIds(int $personId, array $personsById, array $childrenMap): array
{
    if (!isset($personsById[$personId])) {
        return [];
    }

    $person = $personsById[$personId];
    $fatherId = !empty($person['vater_id']) ? (int)$person['vater_id'] : 0;
    $motherId = !empty($person['mutter_id']) ? (int)$person['mutter_id'] : 0;

    if ($fatherId <= 0 && $motherId <= 0) {
        return [];
    }

    $candidateIds = [];
    if ($fatherId > 0 && $motherId > 0) {
        $fatherChildren = array_map('intval', $childrenMap[$fatherId] ?? []);
        $motherChildren = array_map('intval', $childrenMap[$motherId] ?? []);
        $candidateIds = array_values(array_intersect($fatherChildren, $motherChildren));
    } elseif ($fatherId > 0) {
        $candidateIds = array_map('intval', $childrenMap[$fatherId] ?? []);
    } else {
        $candidateIds = array_map('intval', $childrenMap[$motherId] ?? []);
    }

    $result = [];
    foreach ($candidateIds as $candidateId) {
        $candidateId = (int)$candidateId;
        if ($candidateId > 0 && $candidateId !== $personId && isset($personsById[$candidateId])) {
            $result[$candidateId] = true;
        }
    }

    $ids = array_map('intval', array_keys($result));
    sort($ids);
    return $ids;
}

function collectCollateralAncestorsByLevel(array $ancestorPeopleByLevel, array $personsById, array $childrenMap): array
{
    $collateral = [];

    foreach ($ancestorPeopleByLevel as $depth => $personIds) {
        $entriesById = [];

        foreach ($personIds as $personId) {
            $siblings = collectSiblingIds((int)$personId, $personsById, $childrenMap);
            if (empty($siblings)) {
                continue;
            }

            $originName = personByIdShortName((int)$personId, $personsById);
            foreach ($siblings as $siblingId) {
                if (!isset($entriesById[$siblingId])) {
                    $entriesById[$siblingId] = [
                        'id' => (int)$siblingId,
                        'origin_names' => []
                    ];
                }
                $entriesById[$siblingId]['origin_names'][$originName] = true;
            }
        }

        if (!empty($entriesById)) {
            $items = [];
            foreach ($entriesById as $entry) {
                $names = array_keys($entry['origin_names']);
                sort($names);
                $entry['origin_names'] = $names;
                $items[] = $entry;
            }

            usort($items, static function (array $a, array $b) use ($personsById): int {
                return strcmp(
                    personByIdShortName((int)$a['id'], $personsById),
                    personByIdShortName((int)$b['id'], $personsById)
                );
            });

            $collateral[(int)$depth] = $items;
        }
    }

    return $collateral;
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

function renderAncestorGenerationsWithEvents(array $levels, array $personsById, array $coupleEventMap, bool $isTestAccount = false): string
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

            if ($isTestAccount) {
                $fatherLabel = $fatherId > 0 ? anonymizedAncestorRoleExtended(true, $depth) : '';
                $motherLabel = $motherId > 0 ? anonymizedAncestorRoleExtended(false, $depth) : '';
                if ($fatherLabel !== '' && $motherLabel !== '') {
                    $lineRaw = $fatherLabel . ' + ' . $motherLabel;
                } elseif ($fatherLabel !== '') {
                    $lineRaw = $fatherLabel;
                } elseif ($motherLabel !== '') {
                    $lineRaw = $motherLabel;
                } else {
                    $lineRaw = 'Unbekannt';
                }
            } else {
                $lineRaw = formatCoupleLine($fatherId, $motherId, $personsById);
            }
            $line1 = htmlspecialchars($lineRaw, ENT_QUOTES, 'UTF-8');

            $originNames = [];
            $originIndex = 0;
            foreach ($fromIds as $fromId) {
                $originIndex++;
                if ($isTestAccount) {
                    $originNames[] = anonymizedAncestorSourceLabelExtended($depth, $originIndex, $personsById[(int)$fromId] ?? null);
                } else {
                    $originNames[] = personByIdShortName((int)$fromId, $personsById);
                }
            }
            $originNames = array_values(array_unique($originNames));
            sort($originNames);
            $relation = 'Eltern von: ' . (!empty($originNames) ? implode(', ', $originNames) : '-');

            $events = formatCoupleEvents($fatherId, $motherId, $coupleEventMap);
            $blurClass = getBlurClass($isTestAccount);
            $lineClass = $blurClass !== '' ? ' class="person-line ' . $blurClass . '"' : ' class="person-line"';
            $eventClass = $blurClass !== '' ? ' class="subtle person-spouse ' . $blurClass . '"' : ' class="subtle person-spouse"';
            $relationClass = $blurClass !== '' ? ' class="subtle person-relation ' . $blurClass . '"' : ' class="subtle person-relation"';

            $html .= '<article class="person-card">';
            $html .= '<p' . $lineClass . '>' . $line1 . '</p>';
            if ($events !== '') {
                $html .= '<p' . $eventClass . '>Ehe: ' . htmlspecialchars($events, ENT_QUOTES, 'UTF-8') . '</p>';
            }
            $html .= '<p' . $relationClass . '>' . htmlspecialchars($relation, ENT_QUOTES, 'UTF-8') . '</p>';
            $html .= '</article>';
        }

        $html .= '</div>';
        $html .= '</section>';
    }
    $html .= '</div>';

    return $html;
}

function renderAncestorGenerationsComplete(
    array $directLevels,
    array $collateralLevels,
    array $personsById,
    array $spouseMap,
    array $coupleEventMap,
    bool $isTestAccount = false
): string {
    if (empty($directLevels)) {
        return '<p class="subtle">Keine Eintraege vorhanden.</p>';
    }

    krsort($directLevels);

    $html = '<div class="generation-list">';
    foreach ($directLevels as $depth => $units) {
        $depth = (int)$depth;
        $title = ancestorLabelByDepth($depth);

        $html .= '<section class="generation">';
        $html .= '<h4 class="generation-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h4>';
        $html .= '<div class="generation-row">';

        foreach ($units as $unit) {
            $fatherId = (int)($unit['father_id'] ?? 0);
            $motherId = (int)($unit['mother_id'] ?? 0);
            $fromIds = $unit['from_ids'] ?? [];

            if ($isTestAccount) {
                $fatherLabel = $fatherId > 0 ? anonymizedAncestorRoleExtended(true, $depth) : '';
                $motherLabel = $motherId > 0 ? anonymizedAncestorRoleExtended(false, $depth) : '';
                if ($fatherLabel !== '' && $motherLabel !== '') {
                    $lineRaw = $fatherLabel . ' + ' . $motherLabel;
                } elseif ($fatherLabel !== '') {
                    $lineRaw = $fatherLabel;
                } elseif ($motherLabel !== '') {
                    $lineRaw = $motherLabel;
                } else {
                    $lineRaw = 'Unbekannt';
                }
            } else {
                $lineRaw = formatCoupleLine($fatherId, $motherId, $personsById);
            }
            $line1 = htmlspecialchars($lineRaw, ENT_QUOTES, 'UTF-8');

            $originNames = [];
            $originIndex = 0;
            foreach ($fromIds as $fromId) {
                $originIndex++;
                if ($isTestAccount) {
                    $originNames[] = anonymizedAncestorSourceLabelExtended($depth, $originIndex, $personsById[(int)$fromId] ?? null);
                } else {
                    $originNames[] = personByIdShortName((int)$fromId, $personsById);
                }
            }
            $originNames = array_values(array_unique($originNames));
            sort($originNames);
            $relation = 'Eltern von: ' . (!empty($originNames) ? implode(', ', $originNames) : '-');

            $events = formatCoupleEvents($fatherId, $motherId, $coupleEventMap);
            $blurClass = getBlurClass($isTestAccount);
            $lineClass = $blurClass !== '' ? ' class="person-line ' . $blurClass . '"' : ' class="person-line"';
            $eventClass = $blurClass !== '' ? ' class="subtle person-spouse ' . $blurClass . '"' : ' class="subtle person-spouse"';
            $relationClass = $blurClass !== '' ? ' class="subtle person-relation ' . $blurClass . '"' : ' class="subtle person-relation"';

            $html .= '<article class="person-card">';
            $html .= '<p' . $lineClass . '>' . $line1 . '</p>';
            if ($events !== '') {
                $html .= '<p' . $eventClass . '>Ehe: ' . htmlspecialchars($events, ENT_QUOTES, 'UTF-8') . '</p>';
            }
            $html .= '<p' . $relationClass . '>' . htmlspecialchars($relation, ENT_QUOTES, 'UTF-8') . '</p>';
            $html .= '</article>';
        }

        $html .= '</div>';

        $sideItems = $collateralLevels[$depth] ?? [];
        if (!empty($sideItems)) {
            $html .= '<h5 class="generation-title" style="margin-top:10px;">Seitenlinie (Tanten/Onkel dieser Generation)</h5>';
            $html .= '<div class="generation-row">';

            $itemIndex = 0;
            foreach ($sideItems as $item) {
                $itemIndex++;
                $personId = (int)($item['id'] ?? 0);
                if (!isset($personsById[$personId])) {
                    continue;
                }

                $originNames = $item['origin_names'] ?? [];
                $relation = $isTestAccount
                    ? 'Seitenlinie dieser Generation'
                    : ('Geschwister von: ' . (!empty($originNames) ? implode(', ', $originNames) : '-'));
                $html .= renderPersonCard($personId, $personsById, $spouseMap, $relation, $isTestAccount, $depth + 1, $itemIndex);
            }

            $html .= '</div>';
        }

        $html .= '</section>';
    }
    $html .= '</div>';

    return $html;
}

function renderDescendantGenerations(array $levels, array $personsById, array $spouseMap, bool $isTestAccount = false): string
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

        $itemIndex = 0;
        foreach ($items as $item) {
            $itemIndex++;
            $personId = (int)($item['id'] ?? 0);
            $fromId = (int)($item['from'] ?? 0);
            if ($fromId > 0) {
                if ($isTestAccount) {
                    $fromLabel = $depth <= 1
                        ? 'Kind 1'
                        : anonymizedDescendantLabelExtended($depth - 1, $itemIndex);
                    $relation = 'Kind von: ' . $fromLabel;
                } else {
                    $relation = 'Kind von: ' . personByIdShortName($fromId, $personsById);
                }
            } else {
                $relation = '';
            }
            $html .= renderPersonCard($personId, $personsById, $spouseMap, $relation, $isTestAccount, $depth, $itemIndex);
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
$ancestorPeopleByLevel = collectAncestorPeopleByLevel($startId, $personsById, 12);
$collateralAncestorLevels = collectCollateralAncestorsByLevel($ancestorPeopleByLevel, $personsById, $childrenMap);
$descendantLevels = collectDescendantLevels($startId, $personsById, $childrenMap, 12);

$focusNameRaw = $isTestAccount ? 'Kind 1' : trim(($focusPerson['vorname'] ?? '') . ' ' . ($focusPerson['nachname'] ?? ''));
$focusName = htmlspecialchars($focusNameRaw, ENT_QUOTES, 'UTF-8');
$focusBirth = !empty($focusPerson['geburtsdatum']) ? formatDBDateOrNull($focusPerson['geburtsdatum']) : '-';
$focusDeath = !empty($focusPerson['sterbedatum']) ? formatDBDateOrNull($focusPerson['sterbedatum']) : '-';
$focusSpouseRaw = $isTestAccount ? (anonymizedSpouseSummaryExtended($startId, $spouseMap) ?: '-') : (formatSpouseSummary($startId, $spouseMap) ?: '-');
$focusSpouses = htmlspecialchars($focusSpouseRaw, ENT_QUOTES, 'UTF-8');
$focusNameClass = 'focus-name';
$focusMetaClass = 'meta-line';
$focusTextClass = 'subtle';
?>

<div class="tree-page">
    <div class="tree-actions">
        <a href="stammbaum-search.php?vorname=<?= urlencode($focusPerson['vorname'] ?? '') ?>&nachname=<?= urlencode($focusPerson['nachname'] ?? '') ?>" class="btn btn-primary">Zur&uuml;ck zur Suche</a>
        <a href="stammbaum-display.php?id=<?= (int)$startId ?>" class="btn btn-primary">horizontale Ansicht</a>
        <a href="stammbaum-display-horizontal-complete.php?id=<?= (int)$startId ?>" class="btn btn-primary">horizontale Ansicht komplett</a>
        <a href="stammbaum-display-extended.php?id=<?= (int)$startId ?>" class="btn btn-primary">vertikale Ansicht</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">🖨 Druckversion (PDF)</button>
    </div>

    <div class="tree-grid">
        <section class="tree-panel ancestors">
            <h3>Vorfahren inkl. Seitenlinien (komplett aus DB)</h3>
            <?= renderAncestorGenerationsComplete($ancestorLevels, $collateralAncestorLevels, $personsById, $spouseMap, $coupleEventMap, $isTestAccount) ?>
        </section>

        <section class="focus-card">
            <h2 class="<?= htmlspecialchars($focusNameClass, ENT_QUOTES, 'UTF-8') ?>"><?= $focusName ?></h2>
            <p class="<?= htmlspecialchars($focusMetaClass, ENT_QUOTES, 'UTF-8') ?>">Geboren: <?= htmlspecialchars($focusBirth, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="<?= htmlspecialchars($focusMetaClass, ENT_QUOTES, 'UTF-8') ?>">Gestorben: <?= htmlspecialchars($focusDeath, ENT_QUOTES, 'UTF-8') ?></p>
            <p class="<?= htmlspecialchars($focusMetaClass, ENT_QUOTES, 'UTF-8') ?>">Ehepartner: <?= $focusSpouses ?></p>
            <p class="<?= htmlspecialchars($focusTextClass, ENT_QUOTES, 'UTF-8') ?>">Diese Ansicht zeigt Vorfahren inkl. Seitenlinien (z.B. Tanten/Onkel) und Nachkommen so tief, wie sie in der Datenbank verf&uuml;gbar sind.</p>
        </section>

        <section class="tree-panel descendants">
            <h3>Nachkommen (komplett aus DB)</h3>
            <?= renderDescendantGenerations($descendantLevels, $personsById, $spouseMap, $isTestAccount) ?>
        </section>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>
