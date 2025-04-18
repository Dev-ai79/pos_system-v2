<?php
// File Signature: index.php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("index.php: Unauthorized access attempt at " . date('Y-m-d H:i:s'));
    header('Location: login.php');
    exit('Please log in.');
}

// Fetch user details
try {
    $stmt = $pdo->prepare('SELECT username, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        error_log("index.php: User ID {$_SESSION['user_id']} not found at " . date('Y-m-d H:i:s'));
        session_destroy();
        header('Location: login.php');
        exit;
    }
    $username = htmlspecialchars($user['username']);
    $role = htmlspecialchars($user['role']);
} catch (PDOException $e) {
    error_log("index.php: Database error - " . $e->getMessage());
    $username = 'Unknown';
    $role = 'unknown';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .dashboard-card {
            background-color: #fff;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Dashboard</h1>
            <div class="dashboard-card">
                <h2>Welcome, <?php echo $username; ?></h2>
                <p>Role: <?php echo $role; ?></p>
                <p>This is your POS system dashboard. Use the sidebar to navigate to Sell, Reports, or Settings.</p>
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