<?php
session_start();
require 'config.php';

// Restrict to admin/manager
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: index.php');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle product addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("Add Product CSRF Failure: " . print_r($_POST, true));
        $_SESSION['error'] = "Invalid request.";
    } else {
        $name = trim($_POST['name']);
        $buying_price = floatval($_POST['buying_price']);
        $selling_price = floatval($_POST['selling_price']);
        $stock = intval($_POST['stock']);
        
        if (empty($name) || $selling_price <= 0 || $buying_price < 0 || $stock < 0) {
            $_SESSION['error'] = "Please fill in all fields correctly.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO products (name, selling_price, buying_price, stock) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $selling_price, $buying_price, $stock]);
                $_SESSION['success'] = "Product added successfully.";
            } catch (PDOException $e) {
                error_log("Add Product Error: " . $e->getMessage());
                $_SESSION['error'] = "Error adding product: " . htmlspecialchars($e->getMessage());
            }
        }
    }
    header('Location: inventory.php');
    exit;
}

// Handle stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("Stock Update CSRF Failure: " . print_r($_POST, true));
        $_SESSION['error'] = "Invalid request.";
    } else {
        $product_id = intval($_POST['product_id']);
        $stock_change = isset($_POST['stock_change']) ? trim($_POST['stock_change']) : '';
        
        if ($stock_change === '' || !is_numeric($stock_change)) {
            $_SESSION['error'] = "Please enter a valid stock change amount.";
        } elseif (intval($stock_change) == 0) {
            $_SESSION['error'] = "Stock change cannot be zero.";
        } else {
            $stock_change = intval($stock_change);
            try {
                $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $current_stock = $stmt->fetchColumn();
                
                if ($current_stock === false) {
                    $_SESSION['error'] = "Product not found.";
                } elseif ($current_stock + $stock_change < 0) {
                    $_SESSION['error'] = "Cannot adjust stock: would result in negative stock.";
                } else {
                    $stmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                    $stmt->execute([$stock_change, $product_id]);
                    if ($stmt->rowCount() > 0) {
                        $_SESSION['success'] = "Stock updated successfully by " . ($stock_change > 0 ? "+" : "") . $stock_change . ".";
                    } else {
                        $_SESSION['error'] = "No stock changes were made.";
                    }
                }
            } catch (PDOException $e) {
                error_log("Stock Update Error: " . $e->getMessage());
                $_SESSION['error'] = "Error updating stock: " . htmlspecialchars($e.getMessage());
            }
        }
    }
    header('Location: inventory.php');
    exit;
}

// Handle product edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    error_log("Save Product: " . print_r($_POST, true));
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("Save Product CSRF Failure: " . print_r($_POST, true));
        $_SESSION['error'] = "Invalid CSRF token.";
    } else {
        $product_id = intval($_POST['product_id']);
        $name = trim($_POST['name']);
        $buying_price = floatval($_POST['buying_price']);
        $selling_price = floatval($_POST['selling_price']);
        $stock = intval($_POST['stock']);
        
        if (empty($name) || $selling_price <= 0 || $buying_price < 0 || $stock < 0) {
            $_SESSION['error'] = "Please fill in all fields correctly.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                if (!$stmt->fetch()) {
                    $_SESSION['error'] = "Product not found.";
                } else {
                    $stmt = $pdo->prepare("UPDATE products SET name = ?, selling_price = ?, buying_price = ?, stock = ? WHERE id = ?");
                    $stmt->execute([$name, $selling_price, $buying_price, $stock, $product_id]);
                    if ($stmt->rowCount() > 0) {
                        $_SESSION['success'] = "Product updated successfully.";
                    } else {
                        $_SESSION['error'] = "No changes were made to the product.";
                    }
                }
            } catch (PDOException $e) {
                error_log("Save Product Error: " . $e->getMessage());
                $_SESSION['error'] = "Error updating product: " . htmlspecialchars($e->getMessage());
            }
        }
    }
    header('Location: inventory.php');
    exit;
}

// Handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    error_log("Delete Product: " . print_r($_POST, true));
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("Delete Product CSRF Failure: " . print_r($_POST, true));
        $_SESSION['error'] = "Invalid request.";
    } else {
        $product_id = intval($_POST['product_id']);
        try {
            $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            if (!$stmt->fetch()) {
                $_SESSION['error'] = "Product not found.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success'] = "Product deleted successfully.";
                } else {
                    $_SESSION['error'] = "No product was deleted.";
                }
            }
        } catch (PDOException $e) {
            error_log("Delete Product Error: " . $e->getMessage());
            $_SESSION['error'] = "Error deleting product: " . htmlspecialchars($e->getMessage());
        }
    }
    header('Location: inventory.php');
    exit;
}

// Fetch products
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = $search ? "WHERE name LIKE ?" : "";
$query = "SELECT * FROM products $where ORDER BY name";
$stmt = $pdo->prepare($query);
if ($search) {
    $stmt->execute(["%$search%"]);
} else {
    $stmt->execute();
}
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Inventory - POS System</title>
    <link rel="stylesheet" href="styles.css?v=<?php echo filemtime('styles.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .table-responsive {
            max-width: 100%;
            overflow-x: auto;
            margin-top: 20px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .table th, .table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background-color: #f8f8f8;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .table tr:hover {
            background-color: #f0f0f0;
        }
        .table input {
            width: 100%;
            max-width: 100%;
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .table input[readonly] {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            cursor: not-allowed;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: nowrap;
            align-items: center;
        }
        .edit-btn, .save-btn, .delete-btn, .stock-btn {
            padding: 6px 12px;
            font-size: 14px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .edit-btn {
            background-color: #f0ad4e;
            color: white;
        }
        .save-btn {
            background-color: #5cb85c;
            color: white;
        }
        .delete-btn {
            background-color: #d9534f;
            color: white;
        }
        .stock-btn {
            background-color: #f0ad4e;
            color: white;
        }
        .edit-btn:hover {
            background-color: #ec971f;
        }
        .save-btn:hover {
            background-color: #4cae4c;
        }
        .delete-btn:hover {
            background-color: #c9302c;
        }
        .stock-btn:hover {
            background-color: #ec971f;
        }
        .stock-input {
            width: 70px;
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        .error, .success {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .error {
            background-color: #f2dede;
            color: #a94442;
        }
        .success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .numeric {
            text-align: right;
        }
        @media (max-width: 768px) {
            .table th, .table td {
                padding: 8px;
                font-size: 12px;
            }
            .table input, .stock-input {
                font-size: 12px;
            }
            .stock-input {
                width: 60px;
            }
            .edit-btn, .save-btn, .delete-btn, .stock-btn {
                padding: 5px 10px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <i class="fas fa-bars hamburger" onclick="toggleSidebar()"></i>
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <h1>Manage Inventory</h1>
            <?php if (isset($_SESSION['error'])): ?>
                <p class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <p class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
            <?php endif; ?>
            
            <!-- Add Product Form -->
            <form method="POST" class="add-product-form" action="inventory.php">
                <h2>Add New Product</h2>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="text" name="name" placeholder="Product Name" required>
                <input type="number" name="buying_price" placeholder="Buying Price (Ksh)" step="0.01" min="0" required>
                <input type="number" name="selling_price" placeholder="Selling Price (Ksh)" step="0.01" min="0.01" required>
                <input type="number" name="stock" placeholder="Initial Stock" min="0" required>
                <button type="submit" name="add_product" class="orange-btn">Add Product</button>
            </form>
            
            <!-- Search Products -->
            <form method="GET" action="inventory.php">
                <input type="text" name="search" class="search-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="orange-btn">Search</button>
            </form>
            
            <!-- Product List -->
            <div class="table-responsive">
                <?php if (empty($products)): ?>
                    <p>No products found. Please add products using the form above.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th class="numeric">Buying Price (Ksh)</th>
                                <th class="numeric">Selling Price (Ksh)</th>
                                <th class="numeric">Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr data-product-id="<?php echo $product['id']; ?>">
                                    <td>
                                        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" readonly required data-input="name">
                                    </td>
                                    <td class="numeric">
                                        <input type="number" name="buying_price" value="<?php echo $product['buying_price']; ?>" step="0.01" min="0" readonly required data-input="buying_price">
                                    </td>
                                    <td class="numeric">
                                        <input type="number" name="selling_price" value="<?php echo $product['selling_price']; ?>" step="0.01" min="0.01" readonly required data-input="selling_price">
                                    </td>
                                    <td class="numeric">
                                        <input type="number" name="stock" value="<?php echo $product['stock']; ?>" min="0" readonly required data-input="stock">
                                    </td>
                                    <td>
                                        <form method="POST" class="edit-product-form" action="inventory.php" id="edit-form-<?php echo $product['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="hidden" name="name" class="form-name">
                                            <input type="hidden" name="buying_price" class="form-buying_price">
                                            <input type="hidden" name="selling_price" class="form-selling_price">
                                            <input type="hidden" name="stock" class="form-stock">
                                            <div class="action-buttons">
                                                <button type="button" class="edit-btn" data-product-id="<?php echo $product['id']; ?>">Edit</button>
                                                <button type="submit" name="save_product" class="save-btn" style="display: none;">Save</button>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" class="delete-form" action="inventory.php" id="delete-form-<?php echo $product['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" name="delete_product" class="delete-btn">Delete</button>
                                        </form>
                                        <form method="POST" class="add-stock-form-inline" action="inventory.php" id="stock-form-<?php echo $product['id']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="number" name="stock_change" class="stock-input" placeholder="+/- Stock" required>
                                            <button type="submit" name="update_stock" class="stock-btn">Adjust Stock</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing edit buttons');
            
            function enableEdit(button) {
                console.log('Edit button clicked for element:', button);
                let form = button.closest('.edit-product-form');
                
                // Fallback: Use data-product-id to find form
                if (!form) {
                    const productId = button.getAttribute('data-product-id');
                    console.log('Trying fallback with product ID:', productId);
                    form = document.getElementById('edit-form-' + productId);
                }
                
                if (!form) {
                    console.error('Edit form not found for button:', button);
                    alert('Error: Edit form not found. Please check the console for details.');
                    return;
                }
                
                console.log('Form found:', form);
                console.log('Form HTML:', form.innerHTML);
                
                // Get the parent row to find inputs
                const row = button.closest('tr');
                if (!row) {
                    console.error('Parent row not found for button:', button);
                    alert('Error: Parent row not found. Please check the console.');
                    return;
                }
                
                // Select inputs from the row
                const inputs = row.querySelectorAll('input[data-input]');
                
                console.log('Inputs found:', inputs.length);
                inputs.forEach(input => {
                    console.log('Input details:', {
                        name: input.name,
                        type: input.type,
                        readonly: input.readOnly,
                        value: input.value,
                        dataInput: input.getAttribute('data-input')
                    });
                });
                
                const saveButton = form.querySelector('.save-btn');
                const editButton = form.querySelector('.edit-btn');
                
                console.log('Save button:', saveButton);
                console.log('Edit button:', editButton);
                
                if (!inputs.length) {
                    console.error('No inputs found in row:', row);
                    alert('Error: No inputs found. Please check the console.');
                    return;
                }
                if (!saveButton || !editButton) {
                    console.error('Save or Edit button not found in form:', form);
                    alert('Error: Save or Edit button missing. Please check the console.');
                    return;
                }
                
                const productId = form.querySelector('input[name=product_id]').value;
                console.log('Product ID:', productId);
                
                // Update inputs
                inputs.forEach(input => {
                    console.log('Processing input:', input.name);
                    input.removeAttribute('readonly');
                    input.style.cursor = 'text';
                    console.log('Removed readonly from:', input.name);
                });
                
                // Update hidden form inputs for submission
                const formName = form.querySelector('.form-name');
                const formBuyingPrice = form.querySelector('.form-buying_price');
                const formSellingPrice = form.querySelector('.form-selling_price');
                const formStock = form.querySelector('.form-stock');
                
                function updateHiddenInputs() {
                    formName.value = row.querySelector('input[name="name"]').value;
                    formBuyingPrice.value = row.querySelector('input[name="buying_price"]').value;
                    formSellingPrice.value = row.querySelector('input[name="selling_price"]').value;
                    formStock.value = row.querySelector('input[name="stock"]').value;
                }
                
                updateHiddenInputs();
                
                // Add input event listeners to sync hidden inputs
                inputs.forEach(input => {
                    input.addEventListener('input', updateHiddenInputs);
                });
                
                saveButton.style.display = 'inline-block';
                editButton.style.display = 'none';
                console.log('Save button shown, Edit button hidden');
            }

            // Use event delegation for edit buttons
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('edit-btn')) {
                    console.log('Delegated click detected on edit button');
                    enableEdit(e.target);
                }
            });

            // Log number of edit buttons
            const editButtons = document.querySelectorAll('.edit-btn');
            console.log('Found', editButtons.length, 'edit buttons');
        });
    </script>
</body>
</html>