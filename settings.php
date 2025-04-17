<?php
session_start();
require 'config.php';

// Restrict to admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    error_log("settings.php: Access denied for user ID {$_SESSION['user_id']}");
    header('Location: index.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Company Name
        if (isset($_POST['company_name'])) {
            $company_name = trim($_POST['company_name']);
            if (empty($company_name)) {
                $company_error = "Company name is required.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                $stmt->execute(['company_name', $company_name, $company_name]);
                $company_success = "Company name updated.";
            }
        }
        // Tax Rules
        if (isset($_POST['tax_rate'])) {
            $tax_rate = floatval($_POST['tax_rate']);
            if ($tax_rate < 0 || $tax_rate > 100) {
                $tax_error = "Tax rate must be between 0 and 100%.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                $stmt->execute(['tax_rate', $tax_rate, $tax_rate]);
                $tax_success = "Tax rate updated.";
            }
        }
        // User Roles
        if (isset($_POST['user_id']) && isset($_POST['role'])) {
            $user_id = intval($_POST['user_id']);
            $role = $_POST['role'];
            if (!in_array($role, ['admin', 'cashier'])) {
                $role_error = "Invalid role selected.";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$role, $user_id]);
                $role_success = "User role updated.";
            }
        }
        // Receipt Customization
        if (isset($_POST['receipt_footer']) || isset($_FILES['receipt_logo'])) {
            $receipt_footer = trim($_POST['receipt_footer'] ?? '');
            if (isset($_FILES['receipt_logo']) && $_FILES['receipt_logo']['error'] == UPLOAD_ERR_OK) {
                $logo = $_FILES['receipt_logo'];
                $ext = pathinfo($logo['name'], PATHINFO_EXTENSION);
                if (!in_array(strtolower($ext), ['png', 'jpg', 'jpeg'])) {
                    $receipt_error = "Logo must be PNG, JPG, or JPEG.";
                } elseif ($logo['size'] > 2 * 1024 * 1024) {
                    $receipt_error = "Logo must be under 2MB.";
                } else {
                    $logo_path = 'uploads/receipt_logo.' . $ext;
                    if (move_uploaded_file($logo['tmp_name'], $logo_path)) {
                        $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                        $stmt->execute(['receipt_logo', $logo_path, $logo_path]);
                        $receipt_success = "Logo updated.";
                    } else {
                        $receipt_error = "Failed to upload logo.";
                    }
                }
            }
            if ($receipt_footer !== '') {
                $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                $stmt->execute(['receipt_footer', $receipt_footer, $receipt_footer]);
                $receipt_success = $receipt_success ? "Logo and footer updated." : "Footer updated.";
            }
        }
        // Currency Settings
        if (isset($_POST['currency'])) {
            $currency = $_POST['currency'];
            $valid_currencies = [
                'KES' => 'Ksh', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'
            ];
            if (!array_key_exists($currency, $valid_currencies)) {
                $currency_error = "Invalid currency selected.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
                $stmt->execute(['currency_code', $currency, $currency]);
                $stmt->execute(['currency_symbol', $valid_currencies[$currency], $valid_currencies[$currency]]);
                $currency_success = "Currency updated.";
            }
        }
        // Backup Database
        if (isset($_POST['backup'])) {
            $backup_dir = 'backups/';
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            $backup_file = $backup_dir . 'pos_backup_' . date('Ymd_His') . '.sql';
            $tables = [];
            $result = $pdo->query("SHOW TABLES");
            while ($row = $result->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            $backup_sql = '';
            foreach ($tables as $table) {
                $result = $pdo->query("SHOW CREATE TABLE `$table`");
                $row = $result->fetch(PDO::FETCH_NUM);
                $backup_sql .= "\n\n" . $row[1] . ";\n\n";
                $result = $pdo->query("SELECT * FROM `$table`");
                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    $values = array_map(function($value) use ($pdo) {
                        return is_null($value) ? 'NULL' : $pdo->quote($value);
                    }, $row);
                    $backup_sql .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                }
            }
            if (file_put_contents($backup_file, $backup_sql)) {
                $backup_success = "Backup created: $backup_file";
            } else {
                $backup_error = "Failed to create backup.";
            }
        }
    } catch (PDOException $e) {
        error_log("settings.php: Database error - " . $e->getMessage());
        $error = "An error occurred. Please try again.";
    }
}

// Fetch current settings
try {
    $stmt = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` IN ('company_name', 'tax_rate', 'receipt_logo', 'receipt_footer', 'currency_code', 'currency_symbol')");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
    $company_name = $settings['company_name'] ?? '';
    $tax_rate = $settings['tax_rate'] ?? '0';
    $receipt_logo = $settings['receipt_logo'] ?? '';
    $receipt_footer = $settings['receipt_footer'] ?? '';
    $currency_code = $settings['currency_code'] ?? 'KES';
    $currency_symbol = $settings['currency_symbol'] ?? 'Ksh';
    // Fetch users for role management
    $stmt = $pdo->query("SELECT id, username, role FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("settings.php: Database fetch error - " . $e->getMessage());
    $error = "Failed to load settings.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Settings - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Settings</h1>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Company Name -->
            <div class="settings-section">
                <h3>Company Name</h3>
                <form method="POST" class="add-product-form">
                    <label for="company_name">Company Name:</label>
                    <input type="text" name="company_name" value="<?php echo htmlspecialchars($company_name); ?>" placeholder="Enter company name" required>
                    <button type="submit" class="orange-btn">Save</button>
                    <?php if (isset($company_error)): ?>
                        <div class="error"><?php echo htmlspecialchars($company_error); ?></div>
                    <?php endif; ?>
                    <?php if (isset($company_success)): ?>
                        <div class="success"><?php echo htmlspecialchars($company_success); ?></div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tax Rules -->
            <div class="settings-section">
                <h3>Tax Rules</h3>
                <form method="POST" class="add-product-form">
                    <label for="tax_rate">Tax Rate (%):</label>
                    <input type="number" name="tax_rate" value="<?php echo htmlspecialchars($tax_rate); ?>" min="0" max="100" step="0.1" required>
                    <button type="submit" class="orange-btn">Save</button>
                    <?php if (isset($tax_error)): ?>
                        <div class="error"><?php echo htmlspecialchars($tax_error); ?></div>
                    <?php endif; ?>
                    <?php if (isset($tax_success)): ?>
                        <div class="success"><?php echo htmlspecialchars($tax_success); ?></div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- User Roles -->
            <div class="settings-section">
                <h3>User Roles</h3>
                <table>
                    <tr>
                        <th>Username</th>
                        <th>Current Role</th>
                        <th>Change Role</th>
                    </tr>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td>
                                <form method="POST" class="add-user-form">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <select name="role" required>
                                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="cashier" <?php echo $user['role'] == 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                                    </select>
                                    <button type="submit" class="orange-btn">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php if (isset($role_error)): ?>
                    <div class="error"><?php echo htmlspecialchars($role_error); ?></div>
                <?php endif; ?>
                <?php if (isset($role_success)): ?>
                    <div class="success"><?php echo htmlspecialchars($role_success); ?></div>
                <?php endif; ?>
            </div>

            <!-- Receipt Customization -->
            <div class="settings-section">
                <h3>Receipt Customization</h3>
                <form method="POST" class="add-product-form" enctype="multipart/form-data">
                    <label for="receipt_logo">Receipt Logo:</label>
                    <input type="file" name="receipt_logo" accept=".png,.jpg,.jpeg">
                    <?php if ($receipt_logo): ?>
                        <img src="<?php echo htmlspecialchars($receipt_logo); ?>" alt="Receipt Logo" style="max-width: 100px; margin-top: 10px;">
                    <?php endif; ?>
                    <label for="receipt_footer">Receipt Footer:</label>
                    <input type="text" name="receipt_footer" value="<?php echo htmlspecialchars($receipt_footer); ?>" placeholder="Enter footer text">
                    <button type="submit" class="orange-btn">Save</button>
                    <?php if (isset($receipt_error)): ?>
                        <div class="error"><?php echo htmlspecialchars($receipt_error); ?></div>
                    <?php endif; ?>
                    <?php if (isset($receipt_success)): ?>
                        <div class="success"><?php echo htmlspecialchars($receipt_success); ?></div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Currency Settings -->
            <div class="settings-section">
                <h3>Currency Settings</h3>
                <form method="POST" class="add-product-form">
                    <label for="currency">Currency:</label>
                    <select name="currency" required>
                        <option value="KES" <?php echo $currency_code == 'KES' ? 'selected' : ''; ?>>KES (Ksh)</option>
                        <option value="USD" <?php echo $currency_code == 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                        <option value="EUR" <?php echo $currency_code == 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                        <option value="GBP" <?php echo $currency_code == 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                    </select>
                    <button type="submit" class="orange-btn">Save</button>
                    <?php if (isset($currency_error)): ?>
                        <div class="error"><?php echo htmlspecialchars($currency_error); ?></div>
                    <?php endif; ?>
                    <?php if (isset($currency_success)): ?>
                        <div class="success"><?php echo htmlspecialchars($currency_success); ?></div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Backup Database -->
            <div class="settings-section">
                <h3>Backup Database</h3>
                <form method="POST" class="add-product-form">
                    <button type="submit" name="backup" value="1" class="orange-btn">Create Backup</button>
                    <?php if (isset($backup_error)): ?>
                        <div class="error"><?php echo htmlspecialchars($backup_error); ?></div>
                    <?php endif; ?>
                    <?php if (isset($backup_success)): ?>
                        <div class="success"><?php echo htmlspecialchars($backup_success); ?></div>
                    <?php endif; ?>
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