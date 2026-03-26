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
   

<form action="stammbaum-recreateAll.php" method="get">
    <button type="submit">
        <h3>Daten komplett löschen und neu importieren</h3>
    </button>
</form>   
   
    
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


<form action="importOrte.php" method="post">
<button type="submit"><h3> alle Orte importieren </h3></button>
</form>


</body> 
</html> 