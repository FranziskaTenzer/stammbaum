<!-- Cookie Consent Banner -->
<div id="cookie-consent-banner" style="display:none; position:fixed; bottom:0; left:0; right:0; z-index:9999;
     background:#2c2c2c; color:#fff; padding:16px 24px; font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
     font-size:0.9em; box-shadow:0 -2px 12px rgba(0,0,0,0.3);">
    <div style="max-width:900px; margin:0 auto; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
        <span style="flex:1; min-width:200px;">
            🍪 Diese Website verwendet ausschließlich <strong>technisch notwendige Cookies</strong> (Session-Cookie für die Anmeldung). Es werden keine Tracking- oder Werbe-Cookies eingesetzt.
            <a href="/stammbaum/public/datenschutz.php" style="color:#b39ddb; text-decoration:none; margin-left:6px;">Mehr erfahren</a>
        </span>
        <button onclick="acceptCookieConsent()" style="padding:9px 22px; background:#764ba2; color:#fff;
                border:none; border-radius:6px; cursor:pointer; font-size:0.95em; font-weight:600;
                white-space:nowrap; flex-shrink:0;">
            Verstanden
        </button>
    </div>
</div>

<script>
(function() {
    function getCookie(name) {
        var value = '; ' + document.cookie;
        var parts = value.split('; ' + name + '=');
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    if (!getCookie('stammbaum_cookie_consent')) {
        document.getElementById('cookie-consent-banner').style.display = 'block';
    }
})();

function acceptCookieConsent() {
    var expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1);
    document.cookie = 'stammbaum_cookie_consent=1; expires=' + expires.toUTCString() + '; path=/; SameSite=Lax';
    document.getElementById('cookie-consent-banner').style.display = 'none';
}
</script>
