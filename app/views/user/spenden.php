<?php
$pageTitle = 'Spenden – Stammbaum';
require_once __DIR__ . '/../../layout/header.php';
?>

<div class="page-header">
    <h1>💝 Spenden</h1>
    <p class="subtitle">Unterstütze das Stammbaum-Projekt</p>
</div>

<div style="max-width:700px;">

    <div style="background:white; border-radius:10px; padding:32px; box-shadow:0 2px 12px rgba(0,0,0,0.08); margin-bottom:24px;">
        <h2 style="color:#764ba2; margin-bottom:14px;">Danke für deine Unterstützung! 🙏</h2>
        <p style="color:#555; line-height:1.7; margin-bottom:0;">
            Das Stammbaum-Projekt wird in der Freizeit entwickelt und gepflegt. Wenn dir die Anwendung gefällt und du möchtest,
            kannst du die Weiterentwicklung mit einer kleinen Spende unterstützen – das ist vollkommen freiwillig und wird sehr geschätzt.
        </p>
    </div>

    <!-- PayPal -->
    <div style="background:white; border-radius:10px; padding:32px; box-shadow:0 2px 12px rgba(0,0,0,0.08); margin-bottom:24px;">
        <h2 style="color:#764ba2; margin-bottom:18px;">💳 PayPal</h2>
        <p style="color:#555; line-height:1.7; margin-bottom:18px;">
            Du kannst eine Spende bequem über PayPal senden. Klicke auf den Button oder sende den Betrag direkt an:
        </p>
        <div style="background:#f3f0f8; border-radius:8px; padding:16px 20px; margin-bottom:18px; font-family:monospace; font-size:1.05em; color:#333; word-break:break-all;">
            franziskatenzer@hotmail.com
        </div>
         <p style="color:#555; line-height:1.7; margin-bottom:18px;">(bitte Freunde & Familie auswählen)</p>
        <a href="https://www.paypal.me/FranziskaTenzer130"
           target="_blank" rel="noopener noreferrer"
           style="display:inline-block; padding:12px 28px; background:linear-gradient(135deg,#009cde 0%,#003087 100%);
                  color:white; text-decoration:none; border-radius:6px; font-weight:600; font-size:1em;">
            💳 Mit PayPal spenden 
        </a>
        <p style="color:#888; font-size:0.85em; margin-top:12px;">
            * Du wirst zur PayPal-Website weitergeleitet. Stammbaum hat keinen Einblick in deine Zahlungsdaten.
        </p>
    </div>

    <!-- IBAN -->
    <div style="background:white; border-radius:10px; padding:32px; box-shadow:0 2px 12px rgba(0,0,0,0.08); margin-bottom:24px;">
        <h2 style="color:#764ba2; margin-bottom:18px;">🏦 Banküberweisung (IBAN)</h2>
        <p style="color:#555; line-height:1.7; margin-bottom:18px;">
            Alternativ kannst du eine Überweisung auf folgendes Konto vornehmen:
        </p>
        <table style="width:100%; border-collapse:collapse; font-size:0.95em;">
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:10px 8px; color:#888; width:140px; font-weight:600;">Empfänger</td>
                <td style="padding:10px 8px; color:#333;">Franziska Tenzer</td>
            </tr>
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:10px 8px; color:#888; font-weight:600;">IBAN</td>
                <td style="padding:10px 8px; color:#333; font-family:monospace; font-size:1.05em;">AT88 1420 0200 1291 9531</td>
            </tr>
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:10px 8px; color:#888; font-weight:600;">BIC</td>
                <td style="padding:10px 8px; color:#333; font-family:monospace;">BAWAATWW</td>
            </tr>
            <tr>
                <td style="padding:10px 8px; color:#888; font-weight:600;">Verwendungszweck</td>
                <td style="padding:10px 8px; color:#333;">Spende Stammbaum</td>
            </tr>
        </table>
    </div>

    <div style="background:#f3f0f8; border-radius:10px; padding:20px 24px; color:#555; font-size:0.9em; line-height:1.6; margin-bottom:24px;">
        <strong>Hinweis:</strong> Spenden an das Stammbaum-Projekt sind freiwillige Zuwendungen und berechtigen nicht zu steuerlichen Absetzungen.
        Jede Unterstützung hilft dabei, die Anwendung weiterzuentwickeln und zu verbessern. ❤️
    </div>

</div>

<?php require_once __DIR__ . '/../../layout/footer.php'; ?>
