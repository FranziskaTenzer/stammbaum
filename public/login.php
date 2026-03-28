<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $projectUrl = '';

    require_once '../app/lib/include.php';
    $pdo = getPDO();

    // Ensure is_admin column exists in user_profile (safe one-time migration)
    try {
        $colCheck = $pdo->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'user_profile'
               AND COLUMN_NAME = 'is_admin'"
        );
        if ($colCheck->fetchColumn() == 0) {
            $pdo->exec("ALTER TABLE user_profile ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0");
        }
    } catch (PDOException $e) {
        // Table may not exist yet – proceed normally
        error_log('login.php migration check failed: ' . $e->getMessage());
    }

    $stmt = $pdo->prepare("SELECT username, password_hash, is_admin FROM user_profile WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Always run password_verify to prevent timing-based username enumeration
    $hashToCheck = $user ? $user['password_hash'] : '$2y$10$invalidhashforenumerationprotection00000000000000000000';
    if ($user && password_verify($password, $hashToCheck)) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = (bool)$user['is_admin'];
        $_SESSION['login_time'] = time();

        if ($user['is_admin']) {
            header('Location: ' . $projectUrl . '/stammbaum/app/views/admin/home.php');
        } else {
            header('Location: ' . $projectUrl . '/stammbaum/app/views/user/index.php');
        }
        exit;
    } else {
        $error = '❌ Benutzername oder Passwort ist falsch!';
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Stammbaum</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-size: 2em;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 0.9em;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        button {
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
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        button:active {
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
        
        .register-link {
            text-align: center;
            margin-top: 18px;
            font-size: 0.9em;
            color: #666;
        }
        
        .register-link a {
            color: #764ba2;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            color: #5a3a8a;
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="login-container">
    <h1>🌳 Stammbaum</h1>
    <p class="subtitle">Anmeldung erforderlich</p>
    
    <?php if (isset($error)): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="username">Benutzername:</label>
            <input type="text" id="username" name="username" required autofocus>
        </div>
        
        <div class="form-group">
            <label for="password">Passwort:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit">Anmelden</button>
    </form>
    
    <div class="register-link">
        Noch kein Account? <a href="anmeldung.php">Jetzt registrieren</a>
    </div>
</div>

</body>
</html>
