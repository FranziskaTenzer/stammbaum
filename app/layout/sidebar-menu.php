<?php
// Sidebar Menu - wird auf allen Seiten über header.php includiert
require_once dirname(__DIR__) . '/lib/session-helper.php';

$baseUrl = getBaseUrl();
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <h1>🌳 Stammbaum</h1>
        <span class="mobile-toggle" onclick="toggleMobileMenu()">≡</span>
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
                <li><a href="<?= htmlspecialchars($baseUrl) ?>/public/index.php">🏠 Startseite</a></li>
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
                <li><a href="<?= htmlspecialchars($baseUrl) ?>/app/views/user/stammbaum-search.php">👤 Personensuche</a></li>
                <li><a href="<?= htmlspecialchars($baseUrl) ?>/app/views/user/stammbaum-display.php">📊 Stammbaum anzeigen</a></li>
                <li><a href="<?= htmlspecialchars($baseUrl) ?>/app/views/user/traubuch-list.php">📚 Traubuch-Liste</a></li>
            </ul>
        </div>

        <!-- ================================
             ⚙️ ADMIN - Nur für angemeldete User
             ================================ -->
        <?php if (isLoggedIn()): ?>
        <div class="nav-section">
            <h3 class="nav-section-title" onclick="toggleSection(this)">
                <span class="section-icon">▶</span>
                ⚙️ Admin
            </h3>
            <ul class="nav-menu">
                
                <!-- Daten verwalten -->
                <li class="nav-subsection">
                    <span class="subsection-toggle" onclick="toggleSubsection(event)">
                        <span class="subsection-icon">▶</span>
                        📋 Daten verwalten
                    </span>
                    <ul class="nav-submenu">
                        <li><a href="<?= htmlspecialchars($baseUrl) ?>/app/views/admin/import-orte.php">➕ Neue Orte importieren</a></li>
                        <li class="warning-item">
                            <a href="<?= htmlspecialchars($baseUrl) ?>/app/views/admin/re-create-all.php" onclick="return confirm('⚠️ WARNUNG: Dies löscht ALLE Daten und importiert alles neu. Möchten Sie fortfahren?');">
                                🔄 Kompletter Neustart
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Verwaltung -->
                <li class="nav-subsection">
                    <span class="subsection-toggle" onclick="toggleSubsection(event)">
                        <span class="subsection-icon">▶</span>
                        🛠️ Verwaltung
                    </span>
                    <ul class="nav-submenu">
                        <li><a href="<?= htmlspecialchars($baseUrl) ?>/app/views/admin/vornamen-aehnlich.php">👨≈👨 Ähnliche Vornamen</a></li>
                        <li><a href="<?= htmlspecialchars($baseUrl) ?>/app/views/admin/nachnamen-aehnlich.php">👤≈👤 Ähnliche Nachnamen</a></li>
                    </ul>
                </li>

                <!-- Profil / Ausloggen -->
                <li class="nav-subsection">
                    <span class="subsection-toggle" onclick="toggleSubsection(event)">
                        <span class="subsection-icon">▶</span>
                        👤 Profil
                    </span>
                    <ul class="nav-submenu">
                        <li>
                            <span style="display:block; padding:10px; color:#666; font-size:0.9em;">
                                Angemeldet als: <strong><?= htmlspecialchars(getCurrentUser()) ?></strong>
                                <?php if (isAdmin()): ?>
                                    <br><span style="color:#ff9800;">⭐ Admin</span>
                                <?php endif; ?>
                            </span>
                        </li>
                        <li><a href="<?= htmlspecialchars($baseUrl) ?>/public/logout.php" style="color:#d32f2f;">🚪 Ausloggen</a></li>
                    </ul>
                </li>

            </ul>
        </div>
        <?php endif; ?>

    </nav>

    <footer class="sidebar-footer">
        <p>Stammbaum Wildschönau</p>
        <p style="font-size:0.8em; color:#999;">
            <?php if (isLoggedIn()): ?>
                Angemeldet seit: 
                <?php echo date('H:i:s', $_SESSION['login_time']); ?>
            <?php endif; ?>
        </p>
    </footer>

</aside>
