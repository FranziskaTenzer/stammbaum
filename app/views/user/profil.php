<?php
$pageTitle = "Traubuch-Liste";
require_once '../../layout/header.php';

$username = $_SESSION['username'];
if (!function_exists('getPDO')) {
    include '../../lib/include.php';
    global $pdo;
    
}

// PROFIL UPDATEN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['update_profile'])) {
        $fields = [
            'email' => trim($_POST['email']),
            'vorname' => trim($_POST['vorname']),
            'nachname' => trim($_POST['nachname']),
            'adresse' => trim($_POST['adresse']),
            'zahlungstyp' => $_POST['zahlungstyp'] === 'KREDITKARTE' ? 'KREDITKARTE' : 'PAYPAL',
            'zahlungsinfo' => trim($_POST['zahlungsinfo'])
        ];
        $stmt = $pdo->prepare(
            "UPDATE user_profile SET email=?, vorname=?, nachname=?, adresse=?, zahlungstyp=?, zahlungsinfo=? WHERE username=?"
        );
        $stmt->execute([$fields['email'], $fields['vorname'], $fields['nachname'], $fields['adresse'], $fields['zahlungstyp'], $fields['zahlungsinfo'], $username]);
        $message = "Profil erfolgreich gespeichert!";
    }
    // Passwort ändern
    if (isset($_POST['pwchange']) && $_POST['new_password1'] && $_POST['new_password2']) {
        if ($_POST['new_password1'] === $_POST['new_password2']) {
            $pwhash = password_hash($_POST['new_password1'], PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE user_profile SET password_hash=? WHERE username=?")->execute([$pwhash, $username]);
            $message = "Passwort erfolgreich geändert!";
        } else {
            $message = "Passwörter stimmen nicht überein!";
        }
    }
}

// Daten laden
$stmt = $pdo->prepare("SELECT * FROM user_profile WHERE username=?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die("Profil nicht gefunden!");
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Mein Profil</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5;}
        .profile-container { max-width: 500px; margin: 30px auto; background: #fff; 
            border-radius: 10px; box-shadow: 0 2px 16px rgba(0,0,0,0.10); padding: 30px;}
        h1 { text-align:center; color:#764ba2; margin-bottom: 18px;}
        .form-group { margin-bottom: 20px; }
        label { font-weight:600; display:block; margin-bottom:6px;}
        input,select,textarea { width:100%; padding:10px; border-radius:5px; border:1px solid #bbb;}
        .submit-btn { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:#fff; border:none; font-weight:600; cursor:pointer; margin-top:10px;}
        .msg { background:#e8e1f5; color:#764ba2; border-left:4px solid #764ba2; padding:10px; margin-bottom:18px;}
    </style>
    <script>
    function switchPaymentFields() {
        let t = document.getElementById('zahlungstyp').value;
        let label = document.getElementById('zahlungsinfo-label');
        let input = document.getElementById('zahlungsinfo');
        if (t === "KREDITKARTE") {
            label.textContent = 'Kreditkartennummer:';
            input.placeholder = '1234 5678 9012 3456';
        } else {
            label.textContent = 'Paypal E-Mail:';
            input.placeholder = 'paypal@example.com';
        }
    }
    </script>
</head>
<body>
<div class="profile-container">
    <h1>Mein Profil</h1>
    <?php if (!empty($message)): ?><div class="msg"><?=htmlspecialchars($message)?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <div class="form-group">
            <label for="username">Benutzername:</label>
            <input type="text" id="username" name="username" value="<?=htmlspecialchars($user['username'])?>" readonly>
        </div>
        <div class="form-group">
            <label for="email">E-Mail:</label>
            <input type="email" required id="email" name="email" value="<?=htmlspecialchars($user['email'])?>">
        </div>
        <div class="form-group">
            <label for="vorname">Vorname:</label>
            <input type="text" id="vorname" name="vorname" value="<?=htmlspecialchars($user['vorname'])?>">
        </div>
        <div class="form-group">
            <label for="nachname">Nachname:</label>
            <input type="text" id="nachname" name="nachname" value="<?=htmlspecialchars($user['nachname'])?>">
        </div>
        <div class="form-group">
            <label for="adresse">Adresse:</label>
            <textarea id="adresse" name="adresse"><?=htmlspecialchars($user['adresse'])?></textarea>
        </div>
        <div class="form-group">
            <label for="zahlungstyp">Zahlungsinformation:</label>
            <select name="zahlungstyp" id="zahlungstyp" onchange="switchPaymentFields()" required>
                <option value="KREDITKARTE" <?=($user['zahlungstyp']=='KREDITKARTE'?'selected':'')?>>Kreditkarte</option>
                <option value="PAYPAL" <?=($user['zahlungstyp']=='PAYPAL'?'selected':'')?>>Paypal</option>
            </select>
        </div>
        <div class="form-group">
            <label for="zahlungsinfo" id="zahlungsinfo-label">
                <?= $user['zahlungstyp']=='KREDITKARTE'?'Kreditkartennummer:':'Paypal E-Mail:' ?>
            </label>
            <input type="text" name="zahlungsinfo" id="zahlungsinfo" required value="<?=htmlspecialchars($user['zahlungsinfo'])?>">
        </div>
        <button class="submit-btn" type="submit" name="update_profile">Speichern</button>
    </form>
    <form method="post" autocomplete="off" style="margin-top:28px;">
        <h2 style="color:#764ba2; font-size:1.14em;">Passwort ändern</h2>
        <div class="form-group">
            <label for="new_password1">Neues Passwort:</label>
            <input type="password" id="new_password1" name="new_password1" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="new_password2">Wiederholen:</label>
            <input type="password" id="new_password2" name="new_password2" autocomplete="new-password">
        </div>
        <button class="submit-btn" type="submit" name="pwchange">Passwort ändern</button>
    </form>
</div>
<script>switchPaymentFields();</script>
</body>
</html>