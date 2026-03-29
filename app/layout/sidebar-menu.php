<?php
// Session-Helper laden mit korrektem relativem Pfad
require_once __DIR__ . '/../lib/session-helper.php';

// $_projectUrl is set by header.php before this file is included
$_p = isset($_projectUrl) ? $_projectUrl : '/stammbaum';
$_layoutUrl = isset($_layoutUrl) ? $_layoutUrl : '/stammbaum/app/layout';
?>

<!-- ← Header wird jetzt OBEN angezeigt (unter dem X) -->
<div class="sidebar-header">
    <h1>🌳 Stammbaum</h1>
</div>

<nav class="sidebar-nav">
    
    <!-- ================================
         🏠 HOME
         ================================ -->
    <div class="nav-section">
        <h3 class="nav-section-title" onclick="toggleSection(this)">
            <span class="section-icon">▶</span>
            🏠 Home
        </h3>
        <ul class="nav-menu" style="display:block;">
            <li><a href="<?= $_p ?>/app/views/user/index.php">🏠 Startseite</a></li>
            <li><a href="<?= $_p ?>/app/views/user/profil.php">👤 Profil</a></li>
            <li><a href="<?= $_p ?>/public/logout.php">🚪 Abmelden</a></li>
        </ul>
    </div>

    <!-- ================================
         🔍 STAMMBAUM - Hauptbereich
         ================================ -->
    <div class="nav-section">
        <h3 class="nav-section-title" onclick="toggleSection(this)">
            <span class="section-icon">▶</span>
            🔍 Stammbaum
        </h3>
        <ul class="nav-menu" style="display:block;">
            <li><a href="<?= $_p ?>/app/views/user/stammbaum-search.php">👤 Personensuche</a></li>
            <li><a href="<?= $_p ?>/app/views/user/traubuch-list.php">📚 Traubuch-Liste</a></li>
            <li><a href="<?= $_p ?>/app/views/user/nachrichten.php">✉️ Nachrichten</a></li>
        </ul>
    </div>

    <!-- ================================
         ⚙️ ADMIN - Nur für angemeldete User
         ================================ -->
    <?php if (isLoggedIn() && isAdmin()): ?>
    <div class="nav-section">
        <h3 class="nav-section-title" onclick="toggleSection(this)">
            <span class="section-icon">▶</span>
            ⚙️ Admin
        </h3>
        <ul class="nav-menu">
            <li class="nav-subsection">
           		<a href="<?= $_p ?>/app/views/admin/home.php">👨🏻‍💻 Admin Startseite</a>
           </li>
            <!-- Datenbank verwalten -->
            <li class="nav-subsection">
                <span class="subsection-toggle collapsed" onclick="toggleSubsection(event)">
                    <span class="subsection-icon">▶</span>
                    🗃️ Datenbank verwalten
                </span>
                <ul class="nav-submenu">
                    <li><a href="<?= $_p ?>/app/views/admin/recreate-db.php">⛃ Datenbank löschen und neu erstellen</a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/import-thierbach.php">📝 Thierbach importieren</a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/import-orte.php">📝 Alle Orte importieren</a></li>
                    <li class="warning-item"><b>
                        <a href="<?= $_p ?>/app/views/admin/re-create-all.php" onclick="return confirm('⚠️ WARNUNG: Dies löscht ALLE Daten und importiert alles neu. Möchten Sie fortfahren?');">
                            🔄 Kompletter Neustart (re create)
                        </a></b>
                    </li>
                </ul>
            </li>

            <!-- Verwaltung -->
            <li class="nav-subsection">
                <span class="subsection-toggle collapsed" onclick="toggleSubsection(event)">
                    <span class="subsection-icon">▶</span>
                    🛠️ Verwaltung
                </span>
                <ul class="nav-submenu">
                    <li><a href="<?= $_p ?>/app/views/admin/vornamen-similar.php">👨≈👨 Ähnliche Vornamen</a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/nachnamen-similar.php">👤≈👤 Ähnliche Nachnamen</a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/admin-nachrichten.php?filter=offen">📬 offene Nachrichten</a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/admin-nachrichten.php?filter=beantwortet">✅ beantwortete Nachrichten</a></li>
                </ul>
            </li>
            
        </ul>
    </div>
    <?php endif; ?>
    
</nav>

<div class="sidebar-footer">
    <p>© Stammbaum 2026</p>
</div>

<script src="<?= $_layoutUrl ?>/script-menu.js"></script>