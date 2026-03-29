<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF-Token erzeugen
if (empty($_SESSION['csrf_token_forgot'])) {
    $_SESSION['csrf_token_forgot'] = bin2hex(random_bytes(32));
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Prüfung
    if (!hash_equals($_SESSION['csrf_token_forgot'], $_POST['csrf_token'] ?? '')) {
        $error = '❌ Ungültige Anfrage. Bitte lade die Seite neu und versuche es erneut.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '❌ Bitte eine gültige E-Mail-Adresse eingeben.';
        } else {
            require_once '../app/lib/email-handler.php';

            try {
                $pdo = getPDO();
                ensureEmailVerificationColumns($pdo);

                // Immer die gleiche Meldung zeigen, um E-Mail-Enumeration zu verhindern.
                $success = '✅ Falls ein Account mit dieser E-Mail-Adresse existiert, '
                         . 'wurde eine E-Mail zum Zurücksetzen des Passworts gesendet. '
                         . 'Bitte prüfe auch deinen Spam-Ordner.';

                $stmt = $pdo->prepare("SELECT id, username FROM user_profile WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $token     = generateToken();
                    $tokenHash = hashToken($token);
                    $expires   = date('Y-m-d H:i:s', time() + 86400); // 24 Stunden

                    $stmt = $pdo->prepare(
                        "UPDATE user_profile
                         SET password_reset_token = ?,
                             password_reset_token_expires = ?
                         WHERE id = ?"
                    );
                    $stmt->execute([$tokenHash, $expires, $user['id']]);

                    sendPasswordReset($pdo, $user['username'], $email, $token);
                }

                // CSRF-Token erneuern nach erfolgreicher Aktion
                $_SESSION['csrf_token_forgot'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $error = '❌ Ein Fehler ist aufgetreten. Bitte versuche es später erneut.';
                error_log('forgot-password.php error: ' . $e->getMessage());
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
    <title>Passwort vergessen – Stammbaum</title>
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
        input[type="email"] {
            width: 100%; padding: 11px 12px;
            border: 2px solid #ddd; border-radius: 6px;
            font-size: 1em; font-family: inherit;
            transition: border-color 0.3s;
        }
        input[type="email"]:focus {
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
    <p class="subtitle">Passwort zurücksetzen</p>

    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
        <div class="back-link"><a href="login.php">← Zurück zum Login</a></div>
    <?php else: ?>
        <p style="color:#555; margin-bottom:20px; line-height:1.6;">
            Gib deine registrierte E-Mail-Adresse ein. Du erhältst einen Link,
            um dein Passwort zurückzusetzen.
        </p>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token_forgot']) ?>">

            <div class="form-group">
                <label for="email">E-Mail-Adresse:</label>
                <input type="email" id="email" name="email" required autofocus
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="beispiel@domain.de">
            </div>

            <button type="submit">🔑 Zurücksetzen-Link senden</button>
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
