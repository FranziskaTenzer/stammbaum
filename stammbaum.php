<?php

$vorname = $_POST['vorname'];
$nachname = $_POST['nachname'];

if ($vorname != null or $Nachname != null) {
    echo ("Vorname: $vorname Nachname: $nachname");
}
?>

<html lang="de">
<head>
<meta charset="UTF-8">
<title>Stammbäume Wildschönau</title>
 <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f4f4f4; }
        input {padding:5px;}
    </style>
</head>
<body>
<br />
<form action="/deletedb.php" method="post">
<button type="submit"><h4 style='color:red'>Datenbank neu erstellen</h4></button>
</form>
<br/>
<form action="/KI-validierung.php" method="post">
<button type="submit"><h4 style='color:blue'>Validierung</h4></button>
</form>
<br/>
<form action="/stammbaum-familien.php" method="post">
<button type="submit"><h3> Stammbaum Suche</h3></button>
</form>

<h1>KI Daten importieren (Thierbach):<h1/>
<form  action="/KI-importThierbach.php" method="post">
<textarea id="daten_import" name="daten_import" rows="5" cols="150" >
<?php include("Trauungen-Thierbach-komplett.txt");?>
</textarea>
<br />
<br />
<button type="submit">Thierbach Importieren</button>
</form>

<h1>Daten Import KI :<h1/>
<form  action="/KI-import.php" method="post" enctype="multipart/form-data">
<p>Traubuch: <input id="traubuch" name="traubuch" required/></p>
<input type="file" id="daten_import_file" name="daten_import_file"  accept="*.txt" required />
<br />
<br />
<button type="submit">Importieren</button>
</form>

<h1>Daten importieren (Thierbach):<h1/>
<form  action="/stammbaum-import-Thierbach.php" method="post">
<textarea id="daten_import" name="daten_import" rows="5" cols="150" >
</textarea>
<br />
<br />
<button type="submit">Thierbach Importieren</button>
</form>

</body> 
</html> 