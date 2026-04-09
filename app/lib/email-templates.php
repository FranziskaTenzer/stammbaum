<?php

/**
 * Email Templates für Stammbaum
 *
 * Gibt HTML-Email-Templates als ['subject' => ..., 'body' => ...] zurück.
 * Platzhalter für Impressum und Datenschutz sind als Kommentare enthalten.
 */

function getRegistrationConfirmationTemplate(string $username, string $confirmationLink): array
{
    $subject = 'E-Mail-Adresse bestätigen – Stammbaum';
    $usernameEsc = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $linkEsc     = htmlspecialchars($confirmationLink, ENT_QUOTES, 'UTF-8');
    $baseUrl     = function_exists('getBaseUrl') ? getBaseUrl() : '';

    $body = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Mail bestätigen</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f4;
               font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff;
                   border-radius: 12px; overflow: hidden;
                   box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header  { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                   padding: 40px 30px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 1.8em; }
        .content { padding: 40px 30px; color: #333; line-height: 1.6; }
        .btn     { display: inline-block; margin: 20px 0; padding: 14px 30px;
                   background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                   color: white !important; text-decoration: none;
                   border-radius: 6px; font-weight: 600; font-size: 1em; }
        .link-box { word-break: break-all; background: #f4f4f4;
                    padding: 10px; border-radius: 4px; font-size: 0.9em; }
        .footer  { background: #f8f8f8; padding: 20px 30px; text-align: center;
                   font-size: 0.85em; color: #888; border-top: 1px solid #eee; }
        .footer a { color: #764ba2; text-decoration: none; }
        @media (max-width: 600px) {
            .wrapper  { margin: 0; border-radius: 0; }
            .content  { padding: 25px 20px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>🌳 Stammbaum</h1>
        <p style="margin:8px 0 0; opacity:0.9;">E-Mail-Adresse bestätigen</p>
    </div>
    <div class="content">
        <p>Hallo <strong>{$usernameEsc}</strong>,</p>
        <p>vielen Dank für deine Registrierung! Bitte bestätige deine E-Mail-Adresse,
           indem du auf den folgenden Link klickst:</p>
        <p style="text-align:center;">
            <a href="{$linkEsc}" class="btn">✅ E-Mail bestätigen</a>
        </p>
        <p>Falls der Button nicht funktioniert, kopiere diesen Link in deinen Browser:</p>
        <p class="link-box">{$linkEsc}</p>
        <p><strong>Dieser Link ist 24 Stunden gültig.</strong></p>
        <p>Falls du dich nicht registriert hast, kannst du diese E-Mail ignorieren.</p>
    </div>
    <div class="footer">
        <p>Du erhältst diese E-Mail, weil eine Registrierung mit dieser Adresse durchgeführt wurde.</p>
        <a href="{$baseUrl}/stammbaum/public/impressum.php">Impressum</a> &middot; <a href="{$baseUrl}/stammbaum/public/datenschutz.php">Datenschutz</a>
    </div>
</div>
</body>
</html>
HTML;

    return ['subject' => $subject, 'body' => $body];
}

function getPasswordResetTemplate(string $username, string $resetLink): array
{
    $subject     = 'Passwort zurücksetzen – Stammbaum';
    $usernameEsc = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $linkEsc     = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
    $baseUrl     = function_exists('getBaseUrl') ? getBaseUrl() : '';

    $body = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort zurücksetzen</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f4;
               font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff;
                   border-radius: 12px; overflow: hidden;
                   box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header  { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                   padding: 40px 30px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 1.8em; }
        .content { padding: 40px 30px; color: #333; line-height: 1.6; }
        .btn     { display: inline-block; margin: 20px 0; padding: 14px 30px;
                   background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                   color: white !important; text-decoration: none;
                   border-radius: 6px; font-weight: 600; font-size: 1em; }
        .link-box { word-break: break-all; background: #f4f4f4;
                    padding: 10px; border-radius: 4px; font-size: 0.9em; }
        .footer  { background: #f8f8f8; padding: 20px 30px; text-align: center;
                   font-size: 0.85em; color: #888; border-top: 1px solid #eee; }
        .footer a { color: #764ba2; text-decoration: none; }
        @media (max-width: 600px) {
            .wrapper  { margin: 0; border-radius: 0; }
            .content  { padding: 25px 20px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>🌳 Stammbaum</h1>
        <p style="margin:8px 0 0; opacity:0.9;">Passwort zurücksetzen</p>
    </div>
    <div class="content">
        <p>Hallo <strong>{$usernameEsc}</strong>,</p>
        <p>wir haben eine Anfrage erhalten, das Passwort für deinen Account zurückzusetzen.
           Klicke auf den folgenden Link, um ein neues Passwort festzulegen:</p>
        <p style="text-align:center;">
            <a href="{$linkEsc}" class="btn">🔑 Neues Passwort festlegen</a>
        </p>
        <p>Falls der Button nicht funktioniert, kopiere diesen Link in deinen Browser:</p>
        <p class="link-box">{$linkEsc}</p>
        <p><strong>Dieser Link ist 24 Stunden gültig.</strong></p>
        <p>Falls du kein neues Passwort angefordert hast, kannst du diese E-Mail ignorieren.
           Dein Passwort bleibt unverändert.</p>
    </div>
    <div class="footer">
        <p>Du erhältst diese E-Mail aufgrund einer Passwort-Zurücksetzen-Anfrage für deinen Stammbaum-Account.</p>
        <a href="{$baseUrl}/stammbaum/public/impressum.php">Impressum</a> &middot; <a href="{$baseUrl}/stammbaum/public/datenschutz.php">Datenschutz</a>
    </div>
</div>
</body>
</html>
HTML;

    return ['subject' => $subject, 'body' => $body];
}

function getAccountDeletedTemplate(string $username, string $email): array
{
    $deletedAt   = date('d.m.Y H:i:s');
    $subject     = 'Account gelöscht – Stammbaum';
    $usernameEsc = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $emailEsc    = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $baseUrl     = function_exists('getBaseUrl') ? getBaseUrl() : '';

    $body = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account gelöscht</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f4;
               font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff;
                   border-radius: 12px; overflow: hidden;
                   box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header  { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                   padding: 40px 30px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 1.8em; }
        .content { padding: 40px 30px; color: #333; line-height: 1.6; }
        .info-box { background: #f8f8f8; border-left: 4px solid #764ba2;
                    padding: 15px 20px; border-radius: 4px; margin: 20px 0; }
        .footer  { background: #f8f8f8; padding: 20px 30px; text-align: center;
                   font-size: 0.85em; color: #888; border-top: 1px solid #eee; }
        .footer a { color: #764ba2; text-decoration: none; }
        @media (max-width: 600px) {
            .wrapper  { margin: 0; border-radius: 0; }
            .content  { padding: 25px 20px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>🌳 Stammbaum</h1>
        <p style="margin:8px 0 0; opacity:0.9;">Account gelöscht</p>
    </div>
    <div class="content">
        <p>Hallo <strong>{$usernameEsc}</strong>,</p>
        <p>dein Stammbaum-Account wurde erfolgreich gelöscht. Alle deine persönlichen Daten
           wurden aus unserem System entfernt.</p>
        <div class="info-box">
            <p><strong>Gelöschter Account:</strong></p>
            <p>
                Benutzername: {$usernameEsc}<br>
                E-Mail: {$emailEsc}<br>
                Gelöscht am: {$deletedAt}
            </p>
        </div>
        <p>Falls du dies nicht selbst veranlasst hast, kontaktiere uns bitte umgehend.</p>
        <p>Wir wünschen dir alles Gute!</p>
    </div>
    <div class="footer">
        <p>Diese Nachricht wurde automatisch nach der Account-Löschung gesendet.</p>
        <a href="{$baseUrl}/stammbaum/public/impressum.php">Impressum</a> &middot; <a href="{$baseUrl}/stammbaum/public/datenschutz.php">Datenschutz</a>
    </div>
</div>
</body>
</html>
HTML;

    return ['subject' => $subject, 'body' => $body];
}

function getNewTraubuchTemplate(string $username, string $traubuchName, string $ortName): array
{
    $subject      = 'Neues Traubuch verfügbar – Stammbaum';
    $usernameEsc  = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $traubuchEsc  = htmlspecialchars($traubuchName, ENT_QUOTES, 'UTF-8');
    $ortEsc       = htmlspecialchars($ortName, ENT_QUOTES, 'UTF-8');
    $baseUrl      = function_exists('getBaseUrl') ? getBaseUrl() : '';
    $listLinkEsc  = htmlspecialchars($baseUrl . '/stammbaum/app/views/user/traubuch-list.php', ENT_QUOTES, 'UTF-8');

    $body = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neues Traubuch verfügbar</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f4f4;
               font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff;
                   border-radius: 12px; overflow: hidden;
                   box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header  { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                   padding: 40px 30px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 1.8em; }
        .content { padding: 40px 30px; color: #333; line-height: 1.6; }
        .info-box { background: #f8f8f8; border-left: 4px solid #764ba2;
                    padding: 15px 20px; border-radius: 4px; margin: 20px 0; }
        .btn     { display: inline-block; margin: 20px 0; padding: 14px 30px;
                   background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                   color: white !important; text-decoration: none;
                   border-radius: 6px; font-weight: 600; font-size: 1em; }
        .footer  { background: #f8f8f8; padding: 20px 30px; text-align: center;
                   font-size: 0.85em; color: #888; border-top: 1px solid #eee; }
        .footer a { color: #764ba2; text-decoration: none; }
        @media (max-width: 600px) {
            .wrapper  { margin: 0; border-radius: 0; }
            .content  { padding: 25px 20px; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>🌳 Stammbaum</h1>
        <p style="margin:8px 0 0; opacity:0.9;">Neues Traubuch eingepflegt</p>
    </div>
    <div class="content">
        <p>Hallo <strong>{$usernameEsc}</strong>,</p>
        <p>es wurde ein neues Traubuch in das System aufgenommen.</p>
        <div class="info-box">
            <p><strong>Traubuch:</strong> {$traubuchEsc}</p>
            <p><strong>Ort:</strong> {$ortEsc}</p>
        </div>
        <p style="text-align:center;">
            <a href="{$listLinkEsc}" class="btn">📚 Traubücher ansehen</a>
        </p>
        <p>Du erhältst diese E-Mail, weil du Benachrichtigungen für neue Traubücher aktiviert hast.</p>
    </div>
    <div class="footer">
        <a href="{$baseUrl}/stammbaum/public/impressum.php">Impressum</a> &middot; <a href="{$baseUrl}/stammbaum/public/datenschutz.php">Datenschutz</a>
    </div>
</div>
</body>
</html>
HTML;

    return ['subject' => $subject, 'body' => $body];
}
