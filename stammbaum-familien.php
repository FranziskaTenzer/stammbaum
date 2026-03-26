<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'KI-include.php';

$pdo = getPDO();

$results = [];

$vorname = $_GET['vorname'] ?? '';
$nachname = $_GET['nachname'] ?? '';
$geburtsdatum = $_GET['geburtsdatum'] ?? null;

if (!empty($vorname) && !empty($nachname)) {
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
    m.nachname AS mutter_nachname

FROM person p

LEFT JOIN person v ON v.id = p.vater_id
LEFT JOIN person m ON m.id = p.mutter_id

WHERE p.vorname LIKE :vorname
AND p.nachname LIKE :nachname
";
   
    if (!empty($geburtsdatum)) {
        $sql .= " AND p.geburtsdatum = :geburtsdatum ";
    }
        
        $stmt = $pdo->prepare($sql);
        
        $params = [
            ':vorname' => $vorname,
            ':nachname' => $nachname
        ];
        
        if (!empty($geburtsdatum)) {
            $params[':geburtsdatum'] = $geburtsdatum;
        }
        
        $stmt->execute($params);
        
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>

<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Personensuche</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f4f4f4; }
        input {padding:5px;}
    </style>
</head>
<body>
<br/>
<a href='stammbaum.php' style='background:#667eea; color:white; padding:10px 20px; border-radius:6px; text-decoration:none;'>← Zurück zur Startseite</a>


<h1>Personensuche</h1>

<form method="GET">
    <input type="text" name="vorname" placeholder="Vorname" value="<?= htmlspecialchars($vorname) ?>" required>
    <input type="text" name="nachname" placeholder="Nachname" value="<?= htmlspecialchars($nachname) ?>" required>
    <input type="date" name="geburtsdatum" value="<?= htmlspecialchars($geburtsdatum) ?>">
    <button type="submit">Suchen</button>
</form>
<h2>Personen</h2>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Geburtsdatum</th>
            <th>Geburtsort</th>
            <th>Sterbedatum</th>
            <th>Sterbeort</th>
            <th>Hof</th>
            <th>Ort</th>
            <th>Bemerkung</th>
            <th>Vater</th>
            <th>Mutter</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($results as $row): ?>
        <tr>
            <td><a href='KI-ausgabe.php?id=<?=$row['id']?>'><?= htmlspecialchars($row['id']) ?></a></td>
            <td>
                <?= htmlspecialchars($row['vorname'] . ' ' . $row['nachname']) ?>
            </td>
            <td><?= formatDBDateOrNull($row['geburtsdatum']) ?></td>
            <td><?= htmlspecialchars($row['geburtsort']."") ?></td>
            <td><?= formatDBDateOrNull($row['sterbedatum']) ?></td>
            <td><?= htmlspecialchars($row['sterbeort']."") ?></td>
            <td><?= htmlspecialchars($row['hof']."") ?></td>
            <td><?= htmlspecialchars($row['ort']."") ?></td>
            <td><?= htmlspecialchars($row['bemerkung']."") ?></td>
            <td>
                <?= htmlspecialchars($row['vater_vorname'] . ' ' . $row['vater_nachname']) ?>
            </td>
            <td>
                <?= htmlspecialchars($row['mutter_vorname'] . ' ' . $row['mutter_nachname']) ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>

</body>
</html>
      