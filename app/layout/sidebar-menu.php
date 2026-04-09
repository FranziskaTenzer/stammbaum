<?php
// Session-Helper laden mit korrektem relativem Pfad
require_once __DIR__ . '/../lib/session-helper.php';

// $_projectUrl is set by header.php before this file is included
$_p = isset($_projectUrl) ? $_projectUrl : '/stammbaum';
$_layoutUrl = isset($_layoutUrl) ? $_layoutUrl : '/stammbaum/app/layout';

// Aktuelle Seite ermitteln
$currentPage = basename($_SERVER['PHP_SELF']);
$currentFilter = isset($_GET['filter']) ? $_GET['filter'] : '';
$currentType = isset($_GET['typ']) ? $_GET['typ'] : 'Nachricht';
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
            <li><a href="<?= $_p ?>/app/views/user/index.php" <?= $currentPage === 'index.php' ? 'class="active"' : '' ?>>🏠 Startseite</a></li>
            <li><a href="<?= $_p ?>/app/views/user/profil.php" <?= $currentPage === 'profil.php' ? 'class="active"' : '' ?>>👤 Profil</a></li>
            <li><a href="<?= $_p ?>/public/logout.php" <?= $currentPage === 'logout.php' ? 'class="active"' : '' ?>>🚪 Abmelden</a></li>
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
            <li><a href="<?= $_p ?>/app/views/user/stammbaum-search.php" <?= $currentPage === 'stammbaum-search.php' ? 'class="active"' : '' ?>>👤 Personensuche</a></li>
            <li><a href="<?= $_p ?>/app/views/user/traubuch-list.php" <?= $currentPage === 'traubuch-list.php' ? 'class="active"' : '' ?>>📚 Traubuch-Liste</a></li>
            <li><a href="<?= $_p ?>/app/views/user/nachrichten.php" <?= $currentPage === 'nachrichten.php' ? 'class="active"' : '' ?>>✉️ Nachrichten</a></li>
            <li><a href="<?= $_p ?>/app/views/user/recherche-anfrage.php" <?= $currentPage === 'recherche-anfrage.php' ? 'class="active"' : '' ?>>🔎 Recherche-Anfrage</a></li>
        </ul>
    </div>

    <!-- ================================
         ⚙️ ADMIN - Nur für angemeldete User
         ================================ -->
    <?php if (isLoggedIn() && isAdmin()): 
    $offen = "";
    // Zähle unbeantwortete Nachrichten
    require_once '../../lib/include.php';
    $offen_count = 0;
    $style="";
    $recherche_offen_count = 0;
    $recherche_style = "";
    try {
        $pdo = getPDO();
        $count_stmt = $pdo->query("SELECT COUNT(*) as count FROM nachrichten WHERE antwort IS NULL AND typ = 'Nachricht'");
        $offen_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $recherche_stmt = $pdo->query("SELECT COUNT(*) as count FROM nachrichten WHERE antwort IS NULL AND typ = 'Recherche'");
        $recherche_offen_count = $recherche_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($offen_count > 0) {
            $style="style='color:red; font-weight:bold;'";
        }

        if ($recherche_offen_count > 0) {
            $recherche_style="style='color:red; font-weight:bold;'";
        }
    } catch (Exception $e) {
        // Fehler ignorieren, falls Tabelle nicht existiert
    }
    
    
    ?>
    <div class="nav-section">
        <h3 class="nav-section-title" onclick="toggleSection(this)">
            <span class="section-icon">▶</span>
            ⚙️ Admin
        </h3>
        <ul class="nav-menu">
            <li class="nav-subsection">
               <a href="<?= $_p ?>/app/views/admin/home.php" <?= $currentPage === 'home.php' && empty($currentFilter) ? 'class="active"' : '' ?>>👨🏻‍💻 Admin Startseite</a>
            </li>
            <!-- Verwaltung -->
            <li class="nav-subsection">
                <span class="subsection-toggle collapsed" onclick="toggleSubsection(event)">
                    <span class="subsection-icon">▶</span>
                    🛠️ Verwaltung
                </span>
                <ul class="nav-submenu">
                    <li><a href="<?= $_p ?>/app/views/admin/admin-nachrichten.php?filter=offen&typ=Nachricht" <?= $currentPage === 'admin-nachrichten.php' && $currentFilter === 'offen' && $currentType !== 'Recherche' ? 'class="active"' : '' ?> <?= $style; ?>>📬 offene Nachrichten</a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/admin-nachrichten.php?filter=offen&typ=Recherche" <?= $currentPage === 'admin-nachrichten.php' && $currentFilter === 'offen' && $currentType === 'Recherche' ? 'class="active"' : '' ?> <?= $recherche_style; ?>>🔎 neue Recherche-Anfragen</a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/admin-nachrichten.php?filter=beantwortet&typ=Nachricht" <?= $currentPage === 'admin-nachrichten.php' && $currentFilter === 'beantwortet' ? 'class="active"' : '' ?>>✅ beantwortete Nachrichten</a></li>
                	<li><a href="<?= $_p ?>/app/views/admin/benutzer-verwaltung.php" <?= $currentPage === 'benutzer-verwaltung.php' ? 'class="active"' : '' ?>>👥 Benutzer-Verwaltung</a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/orte-verwaltung.php" <?= $currentPage === 'orte-verwaltung.php' ? 'class="active"' : '' ?>>📍 Ort einzeln importieren</a></li>
                </ul>
            </li>
        </ul>

        <!-- Prüfung der Daten -->
          <ul class="nav-menu">  
            <li class="nav-subsection">
                <span class="subsection-toggle collapsed" onclick="toggleSubsection(event)" >
                    <span class="subsection-icon">▶</span>
                    🧐 Daten prüfen
                </span>
                <ul class="nav-submenu">
                    <li><a href="<?= $_p ?>/app/views/admin/vornamen-similar.php" <?= $currentPage === 'vornamen-similar.php' ? 'class="active"' : '' ?>>👨≈👨 Ähnliche Vornamen</a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/nachnamen-similar.php" <?= $currentPage === 'nachnamen-similar.php' ? 'class="active"' : '' ?>>👤≈👤 Ähnliche Nachnamen</a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/ehen-dubletten.php" <?= $currentPage === 'ehen-dubletten.php' ? 'class="active"' : '' ?>>💍 Ehe Dubletten anzeigen</a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/nachnamen-orte.php" <?= $currentPage === 'nachnamen-orte.php' ? 'class="active"' : '' ?>>🗺️ Ortsliste Nachnamen </a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/nachnamen-tirol.php" <?= $currentPage === 'nachnamen-tirol.php' ? 'class="active"' : '' ?>>📚 Nachnamen Tirol (A-Z)</a></li>
                </ul>
            </li>   
          </ul> 

        <!-- Datenbank verwalten (komplett neu importen) -->            
        <ul class="nav-menu">  
            <li class="nav-subsection">
                <span class="subsection-toggle collapsed" onclick="toggleSubsection(event)"  style='color:red; font-weight:bold;'>
                    <span class="subsection-icon">▶</span>
                    🗃️ Datenbank verwalten
                </span>
                <ul class="nav-submenu">
                    <li><a href="<?= $_p ?>/app/views/admin/recreate-db.php" onclick="return confirm('⚠️ WARNUNG: Die Datenbank wird komplett gelöscht und neu erstellt. Möchten Sie fortfahren?');" <?= $currentPage === 'recreate-db.php' ? 'class="active"' : '' ?>>⛃ Datenbank löschen und neu erstellen</a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/import-thierbach.php" onclick="return confirm('⚠️ WARNUNG: Die Thierbach-Daten werden importiert. Möchten Sie fortfahren?');" <?= $currentPage === 'import-thierbach.php' ? 'class="active"' : '' ?>>📝 Thierbach importieren</a></li>
                    <li><a href="<?= $_p ?>/app/views/admin/import-orte.php" onclick="return confirm('⚠️ WARNUNG: Alle Orte-Daten werden importiert. Möchten Sie fortfahren?');" <?= $currentPage === 'import-orte.php' ? 'class="active"' : '' ?>>📝 Alle Orte importieren</a></li>
                    <li class="warning-item"><b>
                        <a href="<?= $_p ?>/app/views/admin/re-create-all.php" onclick="return confirm('⚠️ WARNUNG: Dies löscht ALLE Daten und importiert alles neu. Möchten Sie fortfahren?');" <?= $currentPage === 're-create-all.php' ? 'class="active"' : '' ?>>
                            🔄 Kompletter Neustart (re create)
                        </a></b>
                    </li>
                </ul>
            </li>

            
            
        </ul>
    </div>
    <?php endif; ?>
    
</nav>

<!-- In app/layout/sidebar-menu.php -->

<div class="sidebar-footer">
    <div style="background: linear-gradient(135deg, #764ba2, #764ba2);
            padding: 10px 8px;
            border-radius: 6px;
            margin-bottom: 8px;
            text-align: center;
            transition: all 0.3s ease;"
     onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(118, 75, 162, 0.45)';"
     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 6px 16px rgba(118, 75, 162, 0.35)';">
    <a href="<?= $_p ?>/app/views/user/spenden.php" 
       style="display: block;
              color: white;
              font-weight: 800;
              font-size: 1.3em;
              text-decoration: none;
              margin-bottom: 4px;">
        💝 Spenden<br/>
        <span style="color: rgba(255,255,255,0.9); font-size: 0.65em;">
        Unterstütze das Projekt
    </span>
    </a>
    
</div>
</div>

<script src="<?= $_layoutUrl ?>/script-menu.js"></script>