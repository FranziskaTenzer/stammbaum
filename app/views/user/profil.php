<?php
$pageTitle = "Mein Profil";
require_once '../../layout/header.php';

require_once '../../lib/include.php';

try {
    $pdo = getPDO();
} catch (Exception $e) {
    die("Datenbankverbindung nicht verfügbar: " . htmlspecialchars($e->getMessage()));
}

$username = $_SESSION['username'];
$isTestAccount = ($username === 'TestAccount');

// PROFIL UPDATEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isTestAccount) {
    
    if (isset($_POST['update_profile'])) {
        $fields = [
            'email' => trim($_POST['email']) /*,
            'vorname' => trim($_POST['vorname']),
            'nachname' => trim($_POST['nachname']),
            'adresse' => trim($_POST['adresse']),
            'zahlungstyp' => $_POST['zahlungstyp'] === 'KREDITKARTE' ? 'KREDITKARTE' : 'PAYPAL',
            'zahlungsinfo' => trim($_POST['zahlungsinfo']) */
        ];
        $stmt = $pdo->prepare(
            "UPDATE user_profile SET email=? WHERE username=?"
            );
        // , vorname=?, nachname=?, adresse=?, zahlungstyp=?, zahlungsinfo=?
        // , $fields['vorname'], $fields['nachname'], $fields['adresse'], $fields['zahlungstyp'], $fields['zahlungsinfo']
        $stmt->execute([$fields['email'], $username]);
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
    // Account löschen
    if (isset($_POST['delete_account'])) {
        $username = $_SESSION['username'];
        $stmt = $pdo->prepare("DELETE FROM user_profile WHERE username=?");
        $stmt->execute([$username]);
        session_destroy();
        header("Location: /stammbaum/public/login.php?deleted=1");
        exit;
    }
}

// Daten laden
$stmt = $pdo->prepare("SELECT * FROM user_profile WHERE username=?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die("Profil nicht gefunden!");
}

$extraHead = '<style>
    /* Profile Styles - nutzt die gleichen Classes wie search-box */
    .profile-section {
        margin-bottom: 40px;
    }
    
    .profile-section h2 {
        color: var(--text-primary);
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--primary-light);
        font-size: 1.3em;
    }
    
    .profile-form-wrapper {
        position: relative;
    }
    
    .profile-form {
        background: white;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        width: 50%;
    }
    
    .profile-search-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        align-items: flex-end;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
    }
    
    .form-group label {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 5px;
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px;
        border: 2px solid var(--border-color);
        border-radius: 4px;
        font-size: 1em;
        transition: var(--transition);
        font-family: inherit;
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    /* Disabled State für Formularfelder - DEUTLICH AUSGEGRAUT */
    .form-group input:disabled,
    .form-group select:disabled,
    .form-group textarea:disabled {
        background-color: #d9d9d9 !important;
        color: #999 !important;
        cursor: not-allowed !important;
        opacity: 1 !important;
        border-color: #999 !important;
    }
    
    .test-account-notice {
        background: #fff3cd;
        border-left-color: #ffc107;
        color: #856404;
        margin-bottom: 25px;
    }
    
    .alert {
        padding: 15px;
        border-radius: 6px;
        margin: 25px 0;
        border-left: 4px solid;
    }
    
    .alert-success {
        background: #e8f5e9;
        border-left-color: #4caf50;
        color: #2e7d32;
    }
    
    /* Delete Button - Rot OHNE Gradient */
    button.delete-btn {
        background: #d32f2f !important;
        color: white !important;
        border: none !important;
    }
    
    button.delete-btn:not(:disabled):hover {
        background: #b71c1c !important;
        box-shadow: 0 4px 12px rgba(211, 47, 47, 0.4) !important;
        transform: translateY(-2px) !important;
    }
    
    button.delete-btn:disabled {
        background: #999 !important;
        color: #555 !important;
        opacity: 0.6 !important;
        cursor: not-allowed !important;
        box-shadow: none !important;
        transform: none !important;
    }
    
    /* Primary Buttons - Disabled State */
    button.btn-primary:disabled {
        background: #999 !important;
        color: #555 !important;
        opacity: 0.6 !important;
        cursor: not-allowed !important;
    }
    
    /* OVERLAY LAYER */
    .form-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(200, 200, 200, 0.4);
        border-radius: 8px;
        z-index: 100;
        pointer-events: none;
    }
    
    .divider-text {
        color: var(--text-secondary);
        margin-bottom: 20px;
        line-height: 1.6;
    }
    
    @media (max-width: 768px) {
        .profile-search-form {
            grid-template-columns: 1fr;
        }
    
        .profile-form {
            padding: 20px;
            width: 100%;
        }
    }
</style>';
?>

<div class="page-header">
    <h1>👤 Mein Profil</h1>
    <p class="subtitle">Verwalte deine Kontoinformationen</p>
</div>

<?php if ($isTestAccount): ?>
    <div class="alert alert-warning test-account-notice">
        ⚠️ Dies ist ein Test-Account. Bearbeitungen sind deaktiviert.
    </div>
<?php endif; ?>

<?php if (!empty($message)): ?>
    <div class="alert alert-success">
        ✅ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Allgemeine Informationen -->
<div class="search-box">
    <div class="profile-section">
        <h2>📋 Allgemeine Informationen</h2>
        <br>
    </div>
    
    <div class="profile-form-wrapper">
        <form method="post" autocomplete="off" class="profile-form">
            <div class="profile-search-form">
                <div class="form-group">
                    <label for="username">Benutzername:</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                </div>
                <br>
                <div class="form-group">
                    <label for="email">E-Mail:</label>
                    <input type="email" required id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" <?php echo $isTestAccount ? 'disabled' : ''; ?>>
                </div>
            </div>
            
            <br>
            <br>
            
            <button class="btn btn-primary" type="submit" name="update_profile" <?php echo $isTestAccount ? 'disabled' : ''; ?>>
                💾 Änderungen speichern
            </button>
        </form>
        <?php if ($isTestAccount): ?>
            <div class="form-overlay"></div>
        <?php endif; ?>
    </div>
</div>

<br>
<hr>
<br>

<!-- Passwort ändern -->
<div class="search-box">
    <div class="profile-section">
        <h2>🔐 Passwort ändern</h2>
        <br>
    </div>
    
    <div class="profile-form-wrapper">
        <form method="post" autocomplete="off" class="profile-form">
            <div class="profile-search-form">
                <div class="form-group">
                    <label for="new_password1">Neues Passwort:</label>
                    <input type="password" id="new_password1" name="new_password1" autocomplete="new-password" <?php echo $isTestAccount ? 'disabled' : ''; ?>>
                </div>
                <br>
                <div class="form-group">
                    <label for="new_password2">Passwort wiederholen:</label>
                    <input type="password" id="new_password2" name="new_password2" autocomplete="new-password" <?php echo $isTestAccount ? 'disabled' : ''; ?>>
                </div>
            </div>
            
            <br>
            <br>
            
            <button class="btn btn-primary" type="submit" name="pwchange" <?php echo $isTestAccount ? 'disabled' : ''; ?>>
                🔄 Passwort ändern
            </button>
        </form>
        <?php if ($isTestAccount): ?>
            <div class="form-overlay"></div>
        <?php endif; ?>
    </div>
</div>

<br>
<hr>
<br>

<!-- Dangerzone -->
<div class="search-box">
    <div class="profile-section">
        <h2>⚠️ Dangerzone</h2>
        <br>
    </div>
    
    <div class="profile-form-wrapper">
        <form method="post" onsubmit="return confirm('Willst du deinen Account wirklich unwiderruflich löschen?');" class="profile-form">
            <p class="divider-text">
                Diese Aktion kann nicht rückgängig gemacht werden. Bitte sei sicher, dass du dies wirklich möchtest.
            </p>
            
            <br>
            
            <button class="btn btn-primary delete-btn" type="submit" name="delete_account" <?php echo $isTestAccount ? 'disabled' : ''; ?>>
                🗑️ Account unwiderruflich löschen
            </button>
        </form>
        <?php if ($isTestAccount): ?>
            <div class="form-overlay"></div>
        <?php endif; ?>
    </div>
</div>

<br>
<br>
<br>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>