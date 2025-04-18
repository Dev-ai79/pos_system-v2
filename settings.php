<?php
session_start();
require 'config.php';

// Restrict to admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    error_log("settings.php: Unauthorized access attempt by user ID " . ($_SESSION['user_id'] ?? 'unknown'));
    header('Location: index.php');
    exit('Unauthorized access.');
}

// Handle reset action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_system'])) {
    if ($_SESSION['role'] !== 'admin') {
        $_SESSION['error'] = 'Only admins can reset the system.';
        header('Location: settings.php');
        exit;
    }
    try {
        // Check if tables exist
        $tables = ['inventory', 'sales', 'products'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() == 0) {
                throw new PDOException("Table $table does not exist.");
            }
        }
        // Use DELETE for transactional safety, clear dependent tables first
        $pdo->beginTransaction();
        $pdo->exec('DELETE FROM inventory');
        $pdo->exec('DELETE FROM sales');
        $pdo->exec('DELETE FROM products');
        // Add other tables here after schema confirmation
        $pdo->commit();
        error_log("settings.php: System reset by user ID " . $_SESSION['user_id']);
        $_SESSION['success'] = 'System reset successfully. All data has been cleared.';
    } catch (PDOException $e) {
        // Only rollback if transaction is active
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("settings.php: Reset failed - " . $e->getMessage());
        $_SESSION['error'] = 'Failed to reset system: ' . htmlspecialchars($e->getMessage());
    }
    header('Location: settings.php');
    exit;
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        $company_name = trim($_POST['company_name'] ?? '');
        if ($company_name !== '') {
            $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?');
            $stmt->execute(['company_name', $company_name, $company_name]);
            error_log("settings.php: Updated company_name by user ID " . $_SESSION['user_id']);
        }
        $_SESSION['success'] = 'Settings updated successfully.';
    } catch (PDOException $e) {
        error_log("settings.php: Failed to update settings - " . $e->getMessage());
        $_SESSION['error'] = 'Failed to update settings: ' . htmlspecialchars($e->getMessage());
    }
    header('Location: settings.php');
    exit;
}

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    try {
        $user_id = $_POST['user_id'] ?? 0;
        $role = $_POST['role'] ?? '';
        if (!in_array($role, ['admin', 'manager', 'cashier', 'waiter'])) {
            throw new Exception('Invalid role.');
        }
        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$role, $user_id]);
        error_log("settings.php: Updated role for user ID $user_id to $role by user ID " . $_SESSION['user_id']);
        $_SESSION['success'] = 'User role updated successfully.';
    } catch (Exception $e) {
        error_log("settings.php: Failed to update role - " . $e->getMessage());
        $_SESSION['error'] = 'Failed to update role: ' . htmlspecialchars($e->getMessage());
    }
    header('Location: settings.php');
    exit;
}

// Fetch existing settings
$settings = [];
try {
    $stmt = $pdo->query('SELECT `key`, `value` FROM settings');
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("settings.php: Failed to fetch settings - " . $e->getMessage());
    $_SESSION['error'] = 'Failed to load settings.';
}

// Fetch users for role management
$users = [];
try {
    $stmt = $pdo->query('SELECT id, username, role FROM users ORDER BY username');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("settings.php: Failed to fetch users - " . $e->getMessage());
    $_SESSION['error'] = 'Failed to load users.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .error, .success {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .settings-section {
            max-width: 600px;
            margin-bottom: 40px;
        }
        .settings-section h2 {
            margin-top: 0;
        }
        .settings-form label {
            display: block;
            margin: 10px 0 5px;
        }
        .settings-form input, .settings-form select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .settings-form button {
            padding: 10px 20px;
            background-color: #2a2f43;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        .settings-form button:hover {
            background-color: #0471bd;
        }
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .users-table th, .users-table td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        .users-table th {
            background-color: #2a2f43;
            color: white;
            font-weight: bold;
        }
        .users-table td {
            background-color: #fff;
        }
        .users-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .danger-zone {
            max-width: 600px;
            border: 2px solid #dc3545;
            border-radius: 5px;
            padding: 20px;
            margin-top: 40px;
            background-color: #fff;
        }
        .danger-zone h3 {
            color: #dc3545;
            margin-top: 0;
        }
        .danger-zone p {
            margin: 10px 0;
            font-size: 16px;
            color: #333;
        }
        .danger-zone p i {
            color: #dc3545;
            margin-right: 5px;
        }
        .danger-zone button {
            padding: 10px 20px;
            background-color: white;
            color: #dc3545;
            border: 2px solid #dc3545;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .danger-zone button:hover {
            background-color: #dc3545;
            color: white;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            .settings-section, .danger-zone {
                max-width: 100%;
            }
            .users-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Settings</h1>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <!-- General Settings -->
            <div class="settings-section">
                <h2>General Settings</h2>
                <form class="settings-form" method="post" action="settings.php">
                    <label for="company_name">Company Name</label>
                    <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>">
                    <input type="hidden" name="update_settings" value="1">
                    <button type="submit">Save</button>
                </form>
            </div>
            <!-- User Role Management -->
            <div class="settings-section">
                <h2>User Role Management</h2>
                <?php if (empty($users)): ?>
                    <p>No users found.</p>
                <?php else: ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td>
                                        <form class="settings-form" method="post" action="settings.php">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="role">
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                <option value="manager" <?php echo $user['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                                <option value="cashier" <?php echo $user['role'] === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                                                <option value="waiter" <?php echo $user['role'] === 'waiter' ? 'selected' : ''; ?>>Waiter</option>
                                            </select>
                                            <input type="hidden" name="update_role" value="1">
                                            <button type="submit">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <!-- Danger Zone -->
            <div class="danger-zone">
                <h3>Danger Zone</h3>
                <p><i class="fas fa-exclamation-triangle"></i> Warning: Resetting this system will result to loosing all the data.</p>
                <form method="post" action="settings.php" onsubmit="return confirm('Are you sure you want to reset the system? This will permanently delete all products, sales, and other data. This action cannot be undone.');">
                    <input type="hidden" name="reset_system" value="1">
                    <button type="submit">Reset</button>
                </form>
            </div>
        </div>
    </div>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
    </script>
</body>
</html>