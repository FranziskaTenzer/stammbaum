<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../app/lib/include.php';

$error   = '';
$success = '';
$token   = trim($_GET['token'] ?? $_POST['token'] ?? '');

// Früh prüfen ob der Token-Parameter vorhanden ist
if ($token === '') {
    $error = '❌ Ungültiger oder fehlender Link.';
}

// CSRF-Token erzeugen
if (empty($_SESSION['csrf_token_reset'])) {
    $_SESSION['csrf_token_reset'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    // CSRF-Prüfung
    if (!hash_equals($_SESSION['csrf_token_reset'], $_POST['csrf_token'] ?? '')) {
        $error = '❌ Ungültige Anfrage. Bitte lade die Seite neu und versuche es erneut.';
    } else {
        $password1 = $_POST['password1'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if (strlen($password1) < 6) {
            $error = '❌ Das Passwort muss mindestens 6 Zeichen lang sein.';
        } elseif ($password1 !== $password2) {
            $error = '❌ Die Passwörter stimmen nicht überein.';
        } else {
            try {
                $pdo = getPDO();
                ensureEmailVerificationColumns($pdo);

                $tokenHash = hash('sha256', $token);

                $stmt = $pdo->prepare(
                    "SELECT id, username, password_reset_token_expires
                     FROM user_profile
                     WHERE password_reset_token = ?"
                );
                $stmt->execute([$tokenHash]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $error = '❌ Ungültiger oder bereits verwendeter Zurücksetzen-Link.';
                } elseif ($user['password_reset_token_expires'] !== null
                       && new DateTime() > new DateTime($user['password_reset_token_expires'])) {
                    $error = '❌ Der Link ist abgelaufen. Bitte fordere einen neuen an.';
                } else {
                    $newHash = password_hash($password1, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare(
                        "UPDATE user_profile
                         SET password_hash = ?,
                             password_reset_token = NULL,
                             password_reset_token_expires = NULL
                         WHERE id = ?"
                    );
                    $stmt->execute([$newHash, $user['id']]);

                    $success = '✅ Dein Passwort wurde erfolgreich geändert! Du kannst dich jetzt anmelden.';

                    // CSRF-Token erneuern
                    $_SESSION['csrf_token_reset'] = bin2hex(random_bytes(32));
                }
            } catch (Exception $e) {
                $error = '❌ Ein Fehler ist aufgetreten. Bitte versuche es später erneut.';
                error_log('reset-password.php error: ' . $e->getMessage());
            }
        }
    }
}

// Bei GET-Anfrage prüfen ob der Token gültig ist (für sinnvolle Fehlermeldung)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $token !== '') {
    try {
        $pdo = getPDO();
        ensureEmailVerificationColumns($pdo);

        $tokenHash = hash('sha256', $token);
        $stmt = $pdo->prepare(
            "SELECT id, password_reset_token_expires FROM user_profile WHERE password_reset_token = ?"
        );
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = '❌ Ungültiger oder bereits verwendeter Zurücksetzen-Link.';
        } elseif ($user['password_reset_token_expires'] !== null
               && new DateTime() > new DateTime($user['password_reset_token_expires'])) {
            $error = '❌ Der Link ist abgelaufen. Bitte fordere einen neuen an.';
        }
    } catch (Exception $e) {
        $error = '❌ Ein Fehler ist aufgetreten. Bitte versuche es später erneut.';
        error_log('reset-password.php GET check error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neues Passwort setzen – Stammbaum</title>
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
        .box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 440px;
        }
        h1 { text-align: center; color: #333; margin-bottom: 8px; font-size: 2em; }
        .subtitle { text-align: center; color: #666; margin-bottom: 28px; font-size: 0.9em; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; color: #333; font-weight: 600; font-size: 0.95em; }
        input[type="password"] {
            width: 100%; padding: 11px 12px;
            border: 2px solid #ddd; border-radius: 6px;
            font-size: 1em; font-family: inherit;
            transition: border-color 0.3s;
        }
        input[type="password"]:focus {
            outline: none; border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
        }
        button[type="submit"] {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; border-radius: 6px;
            font-size: 1em; font-weight: 600; cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s; margin-top: 6px;
        }
        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(118, 75, 162, 0.4);
        }
        .error   { background: #ffebee; color: #d32f2f; padding: 12px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #d32f2f; }
        .success { background: #e8f5e9; color: #2e7d32; padding: 12px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #4caf50; }
        .back-link { text-align: center; margin-top: 20px; font-size: 0.9em; color: #666; }
        .back-link a { color: #764ba2; text-decoration: none; font-weight: 600; }
        .back-link a:hover { color: #5a3a8a; text-decoration: underline; }
    </style>
</head>
<body>
<div class="box">
    <h1>🌳 Stammbaum</h1>
    <p class="subtitle">Neues Passwort festlegen</p>

    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <div class="back-link"><a href="forgot-password.php">← Erneut anfragen</a></div>
    <?php elseif (!empty($success)): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
        <div class="back-link"><a href="login.php">→ Zum Login</a></div>
    <?php else: ?>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token_reset']) ?>">
            <input type="hidden" name="token"
                   value="<?= htmlspecialchars($token) ?>">

            <div class="form-group">
                <label for="password1">Neues Passwort: *</label>
                <input type="password" id="password1" name="password1" required
                       minlength="6" placeholder="min. 6 Zeichen"
                       autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="password2">Passwort wiederholen: *</label>
                <input type="password" id="password2" name="password2" required
                       minlength="6" placeholder="Passwort bestätigen"
                       autocomplete="new-password">
            </div>

            <button type="submit">💾 Passwort speichern</button>
        </form>

        <div class="back-link"><a href="login.php">← Zurück zum Login</a></div>
    <?php endif; ?>
</div>

<footer style="text-align:center; padding:14px 20px; font-size:0.85em; color:#aaa; background:rgba(255,255,255,0.1); margin-top:20px;">
    <a href="datenschutz.php" style="color:#b39ddb; text-decoration:none; margin:0 8px;">Datenschutz</a>
    &middot;
    <a href="impressum.php" style="color:#b39ddb; text-decoration:none; margin:0 8px;">Impressum</a>
</footer>

<?php include __DIR__ . '/../app/layout/cookie-consent.php'; ?>
</body>
</html>
