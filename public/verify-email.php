<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../app/lib/include.php';

$error   = '';
$success = '';

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    $error = '❌ Ungültiger oder fehlender Bestätigungslink.';
} else {
    try {
        $pdo = getPDO();
        ensureEmailVerificationColumns($pdo);

        $tokenHash = hash('sha256', $token);

        $stmt = $pdo->prepare(
            "SELECT id, username, verification_token_expires
             FROM user_profile
             WHERE verification_token = ? AND email_verified = 0"
        );
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = '❌ Ungültiger Bestätigungslink oder E-Mail-Adresse bereits bestätigt.';
        } elseif ($user['verification_token_expires'] !== null
               && new DateTime() > new DateTime($user['verification_token_expires'])) {
            $error = '❌ Der Bestätigungslink ist abgelaufen. Bitte registriere dich erneut.';
        } else {
            $stmt = $pdo->prepare(
                "UPDATE user_profile
                 SET email_verified = 1,
                     verification_token = NULL,
                     verification_token_expires = NULL
                 WHERE id = ?"
            );
            $stmt->execute([$user['id']]);
            $success = '✅ E-Mail-Adresse erfolgreich bestätigt! Du kannst dich jetzt anmelden.';
        }
    } catch (Exception $e) {
        $error = '❌ Ein Fehler ist aufgetreten. Bitte versuche es später erneut.';
        error_log('verify-email.php error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Mail bestätigen – Stammbaum</title>
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
            max-width: 480px;
            text-align: center;
        }
        h1 { color: #333; margin-bottom: 24px; font-size: 2em; }
        .message {
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 24px;
            font-size: 1em;
            line-height: 1.5;
        }
        .success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; text-align: left; }
        .error   { background: #ffebee; color: #d32f2f; border-left: 4px solid #d32f2f; text-align: left; }
        a.btn {
            display: inline-block;
            padding: 12px 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1em;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        a.btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(118, 75, 162, 0.4);
        }
    </style>
</head>
<body>
<div class="box">
    <h1>🌳 Stammbaum</h1>

    <?php if (!empty($success)): ?>
        <div class="message success"><?= htmlspecialchars($success) ?></div>
        <a href="login.php" class="btn">Zum Login →</a>
    <?php else: ?>
        <div class="message error"><?= htmlspecialchars($error) ?></div>
        <a href="anmeldung.php" class="btn">Zur Registrierung →</a>
    <?php endif; ?>
</div>
</body>
</html>
