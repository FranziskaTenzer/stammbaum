<?php
$pageTitle = "Benutzer-Verwaltung";
require_once '../../layout/header.php';
require_once '../../lib/include.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur für Administratoren.');
}

$pdo = getPDO();

// Filter
$filter = $_GET['filter'] ?? 'alle';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// SQL-Query basierend auf Filter
$where = "";

if ($filter === 'verified') {
    $where = "WHERE email_verified = 1";
} elseif ($filter === 'unverified') {
    $where = "WHERE email_verified = 0";
}

// Gesamtanzahl
$total_stmt = $pdo->query("SELECT COUNT(*) as count FROM user_profile $where");
$total = $total_stmt->fetch(PDO::FETCH_ASSOC)['count'];
$total_pages = ceil($total / $per_page);

// Benutzer laden
try {
    $stmt = $pdo->query(
        "SELECT id, username, email, email_verified, notifications_enabled, created_at
         FROM user_profile
         $where
         ORDER BY created_at DESC
         LIMIT $per_page OFFSET $offset"
    );
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback fuer Alt-DBs ohne notifications_enabled-Spalte
    $stmt = $pdo->query(
        "SELECT id, username, email, email_verified, created_at
         FROM user_profile
         $where
         ORDER BY created_at DESC
         LIMIT $per_page OFFSET $offset"
    );
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as &$user) {
        $user['notifications_enabled'] = 1;
    }
    unset($user);
}

$extraHead = '<style>
    .filter-buttons {
        display: flex;
        gap: 12px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 11px 24px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
        font-size: 0.95em;
        background: white;
        color: var(--text-primary);
        border: 2px solid var(--border-color);
    }
    
    .filter-btn.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .filter-btn.active:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
    }
    
    .filter-btn:not(.active):hover {
        border-color: var(--primary-color);
        background: rgba(102, 126, 234, 0.05);
    }
    
    .results-count {
        color: var(--text-secondary);
        font-size: 0.95em;
        margin-bottom: 20px;
        font-weight: 600;
    }
    
    .results-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
        background: white;
    }
    
    .results-table thead {
        background: var(--primary-color);
        color: white;
        font-weight: 600;
    }
    
    .results-table th {
        padding: 15px;
        text-align: left;
        font-size: 0.95em;
        border: none;
    }
    
    .results-table tbody tr {
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s ease;
    }
    
    .results-table tbody tr:hover {
        background: #f9f9f9;
    }
    
    .results-table td {
        padding: 15px;
        font-size: 0.95em;
    }
    
    .results-table .username {
        font-weight: 600;
        color: var(--primary-color);
    }
    
    .results-table .email {
        color: #666;
        font-size: 0.9em;
    }
    
    .status-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 12px;
        font-size: 0.85em;
        font-weight: 600;
        white-space: nowrap;
    }
    
    .status-verified {
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .status-unverified {
        background: #fff3cd;
        color: #856404;
    }

    .status-notify-on {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .status-notify-off {
        background: #ffebee;
        color: #c62828;
    }
    
    .registration-time {
        color: #888;
        font-size: 0.9em;
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 30px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .pagination a, .pagination span {
        padding: 9px 12px;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        text-decoration: none;
        color: var(--primary-color);
        font-size: 0.9em;
        transition: all 0.2s;
    }
    
    .pagination a:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .pagination span.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        font-weight: 600;
    }
    
    .pagination span:not(.active) {
        color: #888;
        border-color: #ddd;
    }
    
    .no-results {
        text-align: center;
        color: var(--text-secondary);
        padding: 60px 20px;
        font-size: 1.05em;
    }
</style>';
?>

<div class="page-header">
    <h1>👥 Benutzer-Verwaltung</h1>
    <p class="subtitle">Alle registrierten Benutzer und deren Verifizierungsstatus</p>
</div>

<div class="search-box">
    <div class="filter-buttons">
        <button class="btn btn-primary"><a href="?filter=alle" style="color:white" class="filter-btn <?= $filter === 'alle' ? 'active' : '' ?>">
            📋 Alle (<?= $total ?>)
        </a></button>
        <?php
        $verified_stmt = $pdo->query("SELECT COUNT(*) as count FROM user_profile WHERE email_verified = 1");
        $verified_count = $verified_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $unverified_count = $total - $verified_count;
        ?>
        <button class="btn btn-primary"><a href="?filter=verified" style="color:white" class="filter-btn <?= $filter === 'verified' ? 'active' : '' ?>">
            ✅ Verifiziert (<?= $verified_count ?>)
        </a></button>
        
        <button class="btn btn-primary"><a href="?filter=unverified" style="color:white" class="filter-btn <?= $filter === 'unverified' ? 'active' : '' ?>">
            ⏳ Nicht verifiziert (<?= $unverified_count ?>)
        </a></button>
    </div>
    <br />
    <div class="results-count">
        📊 Ergebnisse: <?= $total ?> Benutzer gefunden (Seite <?= $page ?> von <?= $total_pages ?>)
    </div>
    <br />
    <?php if (!empty($users)): ?>
        <table class="results-table">
            <thead>
                <tr>
                    <th>👤 Benutzername</th>
                    <th>📧 E-Mail</th>
                    <th>✓ Status</th>
                    <th>🔔 Benachrichtigung</th>
                    <th>📅 Registriert</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="username"><?= htmlspecialchars($user['username']) ?></td>
                        <td class="email"><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <?php if ($user['email_verified']): ?>
                                <span class="status-badge status-verified">✅ Verifiziert</span>
                            <?php else: ?>
                                <span class="status-badge status-unverified">⏳ Nicht verifiziert</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($user['notifications_enabled'])): ?>
                                <span class="status-badge status-notify-on">🔔 Aktiviert</span>
                            <?php else: ?>
                                <span class="status-badge status-notify-off">🔕 Deaktiviert</span>
                            <?php endif; ?>
                        </td>
                        <td class="registration-time"><?= $user['created_at'] ? date('d.m.Y H:i', strtotime($user['created_at'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?filter=<?= $filter ?>&page=1">« Erste</a>
                    <a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>">‹ Zurück</a>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                if ($start > 1): ?>
                    <a href="?filter=<?= $filter ?>&page=1">1</a>
                    <?php if ($start > 2): ?><span>…</span><?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?filter=<?= $filter ?>&page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?><span>…</span><?php endif; ?>
                    <a href="?filter=<?= $filter ?>&page=<?= $total_pages ?>"><?= $total_pages ?></a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>">Weiter ›</a>
                    <a href="?filter=<?= $filter ?>&page=<?= $total_pages ?>">Letzte »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="no-results">
            📭 Keine Benutzer gefunden.
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../layout/footer.php'; ?>