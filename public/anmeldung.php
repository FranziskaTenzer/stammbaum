<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username    = trim($_POST['username'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $vorname     = trim($_POST['vorname'] ?? '');
    $nachname    = trim($_POST['nachname'] ?? '');
    $adresse     = trim($_POST['adresse'] ?? '');
    $password1   = $_POST['password1'] ?? '';
    $password2   = $_POST['password2'] ?? '';
    $zahlungstyp = $_POST['zahlungstyp'] ?? '';
    $zahlungsinfo = trim($_POST['zahlungsinfo'] ?? '');

    // Validierung
    if ($username === '' || $email === '' || $password1 === '') {
        $error = '❌ Bitte alle Pflichtfelder ausfüllen.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '❌ Bitte eine gültige E-Mail-Adresse eingeben.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = '❌ Benutzername muss zwischen 3 und 50 Zeichen lang sein.';
    } elseif ($password1 !== $password2) {
        $error = '❌ Die Passwörter stimmen nicht überein.';
    } elseif (strlen($password1) < 6) {
        $error = '❌ Das Passwort muss mindestens 6 Zeichen lang sein.';
    } elseif (!in_array($zahlungstyp, ['KREDITKARTE', 'PAYPAL'], true)) {
        $error = '❌ Bitte eine gültige Zahlungsart auswählen.';
    } elseif ($zahlungsinfo === '') {
        $error = '❌ Bitte Zahlungsinformation angeben.';
    } elseif ($zahlungstyp === 'PAYPAL' && !filter_var($zahlungsinfo, FILTER_VALIDATE_EMAIL)) {
        $error = '❌ Bitte eine gültige PayPal E-Mail-Adresse eingeben.';
    } else {
        require_once '../app/lib/include.php';
        $pdo = getPDO();

        // Prüfe auf doppelten Benutzernamen
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_profile WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $error = '❌ Dieser Benutzername ist bereits vergeben.';
        } else {
            // Prüfe auf doppelte E-Mail
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_profile WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = '❌ Diese E-Mail-Adresse ist bereits registriert.';
            } else {
                // Passwort hashen und Benutzer anlegen
                $password_hash = password_hash($password1, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "INSERT INTO user_profile (username, email, vorname, nachname, adresse, zahlungstyp, zahlungsinfo, password_hash)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$username, $email, $vorname, $nachname, $adresse, $zahlungstyp, $zahlungsinfo, $password_hash]);
                $success = '✅ Registrierung erfolgreich! Du wirst zum Login weitergeleitet...';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrierung - Stammbaum</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .register-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 480px;
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 8px;
            font-size: 2em;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 28px;
            font-size: 0.9em;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-weight: 600;
            font-size: 0.95em;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        textarea,
        select {
            width: 100%;
            padding: 11px 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            font-family: inherit;
            transition: border-color 0.3s;
            background: #fff;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
        }

        textarea {
            resize: vertical;
            min-height: 70px;
        }

        button[type="submit"] {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 6px;
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(118, 75, 162, 0.4);
        }

        button[type="submit"]:active {
            transform: translateY(0);
        }

        .error {
            background: #ffebee;
            color: #d32f2f;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #d32f2f;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #4caf50;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9em;
            color: #666;
        }

        .login-link a {
            color: #764ba2;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            color: #5a3a8a;
            text-decoration: underline;
        }

        .section-divider {
            color: #764ba2;
            font-weight: 700;
            font-size: 1em;
            margin: 22px 0 14px;
            border-bottom: 2px solid #e8e1f5;
            padding-bottom: 6px;
        }
    </style>
    <script>
    function switchPaymentFields() {
        var zahlungstyp = document.getElementById('zahlungstyp').value;
        var label = document.getElementById('zahlungsinfo-label');
        var input = document.getElementById('zahlungsinfo');
        if (zahlungstyp === 'KREDITKARTE') {
            label.textContent = 'Kreditkartennummer:';
            input.placeholder = '1234 5678 9012 3456';
            input.type = 'text';
        } else {
            label.textContent = 'PayPal E-Mail:';
            input.placeholder = 'paypal@example.com';
            input.type = 'email';
        }
    }
    </script>
</head>
<body>

<?php if (!empty($success)): ?>
<div class="register-container">
    <h1>🌳 Stammbaum</h1>
    <div class="success"><?= htmlspecialchars($success) ?></div>
    <p style="text-align:center; color:#555;">Du wirst in wenigen Sekunden weitergeleitet.</p>
    <div class="login-link" style="margin-top:18px;">
        <a href="login.php">Jetzt anmelden →</a>
    </div>
</div>
<script>setTimeout(function(){ window.location.href = 'login.php'; }, 5000);</script>
<?php else: ?>
<div class="register-container">
    <h1>🌳 Stammbaum</h1>
    <p class="subtitle">Neuen Account erstellen</p>

    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="" autocomplete="off">

        <div class="section-divider">Zugangsdaten</div>

        <div class="form-group">
            <label for="username">Benutzername: *</label>
            <input type="text" id="username" name="username" required
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   minlength="3" maxlength="50" placeholder="min. 3 Zeichen">
        </div>

        <div class="form-group">
            <label for="email">E-Mail-Adresse: *</label>
            <input type="email" id="email" name="email" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="beispiel@domain.de">
        </div>

        <div class="form-group">
            <label for="password1">Passwort: *</label>
            <input type="password" id="password1" name="password1" required
                   minlength="6" placeholder="min. 6 Zeichen" autocomplete="new-password">
        </div>

        <div class="form-group">
            <label for="password2">Passwort wiederholen: *</label>
            <input type="password" id="password2" name="password2" required
                   minlength="6" placeholder="Passwort bestätigen" autocomplete="new-password">
        </div>

        <div class="section-divider">Persönliche Daten</div>

        <div class="form-group">
            <label for="vorname">Vorname:</label>
            <input type="text" id="vorname" name="vorname"
                   value="<?= htmlspecialchars($_POST['vorname'] ?? '') ?>"
                   placeholder="Vorname">
        </div>

        <div class="form-group">
            <label for="nachname">Nachname:</label>
            <input type="text" id="nachname" name="nachname"
                   value="<?= htmlspecialchars($_POST['nachname'] ?? '') ?>"
                   placeholder="Nachname">
        </div>

        <div class="form-group">
            <label for="adresse">Adresse:</label>
            <textarea id="adresse" name="adresse"
                      placeholder="Straße, Hausnummer, PLZ, Ort"><?= htmlspecialchars($_POST['adresse'] ?? '') ?></textarea>
        </div>

        <div class="section-divider">Zahlungsinformation</div>

        <div class="form-group">
            <label for="zahlungstyp">Zahlungsart: *</label>
            <select name="zahlungstyp" id="zahlungstyp" onchange="switchPaymentFields()" required>
                <option value="KREDITKARTE" <?= (($_POST['zahlungstyp'] ?? '') === 'KREDITKARTE' ? 'selected' : '') ?>>Kreditkarte</option>
                <option value="PAYPAL" <?= (($_POST['zahlungstyp'] ?? '') === 'PAYPAL' ? 'selected' : '') ?>>PayPal</option>
            </select>
        </div>

        <div class="form-group">
            <label for="zahlungsinfo" id="zahlungsinfo-label">Kreditkartennummer: *</label>
            <input type="text" id="zahlungsinfo" name="zahlungsinfo" required
                   value="<?= htmlspecialchars($_POST['zahlungsinfo'] ?? '') ?>"
                   placeholder="1234 5678 9012 3456">
        </div>

        <button type="submit">Jetzt registrieren</button>
    </form>

    <div class="login-link">
        Bereits registriert? <a href="login.php">Zum Login</a>
    </div>
</div>
<script>switchPaymentFields();</script>
<?php endif; ?>

</body>
</html>
