<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch company name from settings table
require_once 'config.php'; // Use same PDO connection as settings.php
try {
    $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'company_name'");
    $stmt->execute();
    $company_name = $stmt->fetch(PDO::FETCH_ASSOC)['value'] ?? 'POS System';
} catch (PDOException $e) {
    error_log("sidebar.php: Failed to fetch company name - " . $e->getMessage());
    $company_name = 'POS System';
}
?>
<nav class="sidebar">
    <div class="sidebar-header">
        <h2><?php echo htmlspecialchars($company_name); ?></h2>
    </div>
    <div class="user-details">
        <?php if (isset($_SESSION['username'], $_SESSION['role'])): ?>
            <p>Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo ucfirst($_SESSION['role']); ?>)</p>
        <?php else: ?>
            <p>Not logged in</p>
        <?php endif; ?>
    </div>
    <ul>
        <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
            <li><a href="inventory.php"><i class="fas fa-box"></i> Manage Inventory</a></li>
            <li><a href="users.php"><i class="fas fa-users"></i> Manage Users</a></li>
        <?php endif; ?>
        <li><a href="sell.php"><i class="fas fa-cash-register"></i> Sell</a></li>
        <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        <?php endif; ?>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</nav>