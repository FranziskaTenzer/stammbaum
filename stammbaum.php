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

<form action="stammbaum-familien.php" method="post">
<button type="submit"><h3> Stammbaum Suche</h3></button>
</form>


<?php include("../stammbaum-daten/overview.html");?>
    
    

<form action="stammbaum-aehnliche-namen.php" method="get">
    <button type="submit">
        <h3>Ähnliche Namen anzeigen</h3>
    </button>
</form>
<br />   
<hr>
<br />
<form action="re-create-all.php" method="get">
    <button type="submit">
        <h3 style="color:red; font-weight:bold;">Daten komplett löschen und neu importieren<br />(Weiterhin fehlerhaft)</h3>
    </button>
</form> 
 
<br />   
<hr>
<br />  
  
<form action="db_init.php" method="post">
<button type="submit"><h4 style='color:red'>Datenbank neu erstellen</h4></button>
</form>
<br/>    

<h1>KI Daten importieren (Thierbach):<h1/>
<form  action="importThierbach.php" method="post">
<textarea id="daten_import" name="daten_import" rows="5" cols="150" >
<?php include("../stammbaum-daten/Thierbach-komplett.txt");?>

</textarea>
<br />
<br />
<button type="submit">Thierbach Importieren</button>
</form>
<!-- 
<h1>Daten Import Ort :<h1/>
<form  action="importOrt.php" method="post" enctype="multipart/form-data">
<p>Traubuch: <input id="traubuch" name="traubuch" required/></p>
<input type="file" id="daten_import_file" name="daten_import_file"  accept="*.txt" required />
<br />
<br />
<button type="submit">Importieren</button>
</form> -->

<form action="importOrte.php" method="post">
<button type="submit"><h3> alle Orte importieren </h3></button>
</form>


</body> 
</html> 