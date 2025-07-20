<?php
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: /Toko-Listrik/login.php');
    exit();
}

// Redirect admin to admin dashboard
if (isAdmin()) {
    header('Location: /Toko-Listrik/admin/dashboard.php');
    exit();
}

$error = '';
$success = '';

// Get bill ID from URL
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;

if (!$bill_id) {
    header('Location: dashboard.php');
    exit();
}

// Get bill details with customer info
$stmt = $pdo->prepare("
    SELECT b.*, c.name as customer_name, c.customer_number, c.tariff_type, c.user_id,
           t.price_per_kwh, t.admin_fee
    FROM bills b
    JOIN customers c ON b.customer_id = c.id
    LEFT JOIN tariffs t ON c.tariff_type = t.tariff_name
    WHERE b.id = ?
");
$stmt->execute([$bill_id]);
$bill = $stmt->fetch();

// Check if bill exists and belongs to current user
if (!$bill || $bill['user_id'] != $_SESSION['user_id']) {
    header('Location: dashboard.php');
    exit();
}

// Check if bill is already paid
if ($bill['status'] == 'paid') {
    $error = 'Tagihan ini sudah dibayar!';
}

// Handle payment submission
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'pay' && $bill['status'] == 'unpaid') {
    $payment_method = sanitizeInput($_POST['payment_method']);
    $customer_name = sanitizeInput($_POST['customer_name']);
    $customer_phone = sanitizeInput($_POST['customer_phone']);
    
    try {
        // Generate unique transaction ID
        $transaction_id = 'TRX' . date('YmdHis') . rand(1000, 9999);
        
        // Insert payment record
        $stmt = $pdo->prepare("
            INSERT INTO payments (bill_id, user_id, payment_method, payment_amount, transaction_id, status) 
            VALUES (?, ?, ?, ?, ?, 'success')
        ");
        $stmt->execute([
            $bill_id, 
            $_SESSION['user_id'], 
            $payment_method, 
            $bill['total_amount'], 
            $transaction_id
        ]);
        
        // Update bill status to paid
        $stmt = $pdo->prepare("UPDATE bills SET status = 'paid' WHERE id = ?");
        $stmt->execute([$bill_id]);
        
        $success = 'Pembayaran berhasil! ID Transaksi: ' . $transaction_id;
        
        // Refresh bill data
        $stmt = $pdo->prepare("
            SELECT b.*, c.name as customer_name, c.customer_number, c.tariff_type, c.user_id,
                   t.price_per_kwh, t.admin_fee
            FROM bills b
            JOIN customers c ON b.customer_id = c.id
            LEFT JOIN tariffs t ON c.tariff_type = t.tariff_name
            WHERE b.id = ?
        ");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Listrik - PLN Payment</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            line-height: 1.6;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }
        
        .navbar-user {
            position: relative;
        }
        
        .dropdown-toggle {
            background: none;
            border: none;
            color: white;
            padding: 10px 15px;
            border-radius: 25px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
            display: none;
            z-index: 1000;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-item {
            display: block;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: background 0.3s ease;
        }
        
        .dropdown-item:hover {
            background: #f8f9fa;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .page-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 2rem;
            color: #333;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 1rem;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            color: white;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            position: relative;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
        }
        
        .card-header h5 {
            font-size: 1.3rem;
            margin: 0;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .bill-detail {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .bill-detail:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
            color: #28a745;
        }
        
        .bill-detail .label {
            color: #666;
        }
        
        .bill-detail .value {
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            outline: none;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .payment-method {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-method:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }
        
        .payment-method input[type="radio"] {
            margin-right: 12px;
        }
        
        .payment-method.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .method-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        
        .method-info {
            flex: 1;
        }
        
        .method-name {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .method-desc {
            color: #666;
            font-size: 0.9rem;
        }
        
        .success-message {
            text-align: center;
            padding: 40px 20px;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            
            .card-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-content">
            <a href="dashboard.php" class="navbar-brand">
                ⚡ PLN Payment
            </a>
            <div class="navbar-user">
                <button class="dropdown-toggle" onclick="toggleDropdown()">
                    👤 <?= $_SESSION['full_name'] ?> ▼
                </button>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="dashboard.php" class="dropdown-item">🏠 Dashboard</a>
                    <a href="/Toko-Listrik/logout.php" class="dropdown-item">🚪 Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <a href="dashboard.php" class="btn btn-secondary">
                ← Kembali
            </a>
            <h2 class="page-title">💳 Pembayaran Listrik</h2>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                ⚠️ <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($bill['status'] == 'paid' && $success): ?>
            <!-- Success State -->
            <div class="content-card">
                <div class="card-body">
                    <div class="success-message">
                        <div class="success-icon">✅</div>
                        <h3>Pembayaran Berhasil!</h3>
                        <p>Tagihan listrik Anda telah berhasil dibayar.</p>
                        <div style="margin: 20px 0;">
                            <strong>No. Pelanggan:</strong> <?= $bill['customer_number'] ?><br>
                            <strong>Periode:</strong> <?= date('F Y', strtotime($bill['bill_month'] . '-01')) ?><br>
                            <strong>Total Dibayar:</strong> Rp <?= number_format($bill['total_amount'], 0, ',', '.') ?>
                        </div>
                        <a href="dashboard.php" class="btn btn-secondary">Kembali ke Dashboard</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Payment Form -->
            <div class="content-grid">
                <!-- Bill Details -->
                <div class="content-card">
                    <div class="card-header">
                        <h5>🧾 Detail Tagihan</h5>
                    </div>
                    <div class="card-body">
                        <div class="bill-detail">
                            <span class="label">No. Pelanggan:</span>
                            <span class="value"><?= $bill['customer_number'] ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Nama:</span>
                            <span class="value"><?= $bill['customer_name'] ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Periode:</span>
                            <span class="value"><?= date('F Y', strtotime($bill['bill_month'] . '-01')) ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Tarif:</span>
                            <span class="value"><?= $bill['tariff_type'] ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Penggunaan:</span>
                            <span class="value"><?= number_format($bill['kwh_usage']) ?> kWh</span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Biaya kWh:</span>
                            <span class="value">Rp <?= number_format($bill['amount'], 0, ',', '.') ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Biaya Admin:</span>
                            <span class="value">Rp <?= number_format($bill['admin_fee'], 0, ',', '.') ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Total Bayar:</span>
                            <span class="value">Rp <?= number_format($bill['total_amount'], 0, ',', '.') ?></span>
                        </div>
                        <div class="bill-detail">
                            <span class="label">Jatuh Tempo:</span>
                            <span class="value"><?= date('d F Y', strtotime($bill['due_date'])) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Payment Form -->
                <div class="content-card">
                    <div class="card-header">
                        <h5>💳 Metode Pembayaran</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($bill['status'] == 'unpaid'): ?>
                            <form method="POST" id="paymentForm">
                                <input type="hidden" name="action" value="pay">
                                
                                <div class="form-group">
                                    <label class="form-label">Pilih Metode Pembayaran</label>
                                    
                                    <div class="payment-method" onclick="selectMethod('bank_transfer')">
                                        <input type="radio" name="payment_method" value="bank_transfer" id="bank_transfer" required>
                                        <div class="method-icon">🏦</div>
                                        <div class="method-info">
                                            <div class="method-name">Transfer Bank</div>
                                            <div class="method-desc">BCA, Mandiri, BNI, BRI</div>
                                        </div>
                                    </div>
                                    
                                    <div class="payment-method" onclick="selectMethod('ewallet')">
                                        <input type="radio" name="payment_method" value="ewallet" id="ewallet" required>
                                        <div class="method-icon">📱</div>
                                        <div class="method-info">
                                            <div class="method-name">E-Wallet</div>
                                            <div class="method-desc">GoPay, OVO, DANA, ShopeePay</div>
                                        </div>
                                    </div>
                                    
                                    <div class="payment-method" onclick="selectMethod('credit_card')">
                                        <input type="radio" name="payment_method" value="credit_card" id="credit_card" required>
                                        <div class="method-icon">💳</div>
                                        <div class="method-info">
                                            <div class="method-name">Kartu Kredit</div>
                                            <div class="method-desc">Visa, Mastercard</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Nama Pembayar</label>
                                    <input type="text" class="form-control" name="customer_name" 
                                           value="<?= $_SESSION['full_name'] ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">No. Telepon</label>
                                    <input type="tel" class="form-control" name="customer_phone" 
                                           placeholder="08xxxxxxxxxx" required>
                                </div>
                                
                                <button type="submit" class="btn btn-success" style="width: 100%;" 
                                        onclick="return confirm('Konfirmasi pembayaran sebesar Rp <?= number_format($bill['total_amount'], 0, ',', '.') ?>?')">
                                    💳 Bayar Sekarang - Rp <?= number_format($bill['total_amount'], 0, ',', '.') ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <div style="text-align: center; color: #28a745; padding: 20px;">
                                <div style="font-size: 3rem; margin-bottom: 15px;">✅</div>
                                <h4>Tagihan Sudah Lunas</h4>
                                <p>Tagihan ini telah dibayar sebelumnya.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleDropdown() {
            var dropdown = document.getElementById("dropdownMenu");
            dropdown.classList.toggle("show");
        }

        function selectMethod(method) {
            // Remove selected class from all methods
            var methods = document.querySelectorAll('.payment-method');
            methods.forEach(function(m) {
                m.classList.remove('selected');
            });
            
            // Add selected class to clicked method
            event.currentTarget.classList.add('selected');
            
            // Select the radio button
            document.getElementById(method).checked = true;
        }

        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.dropdown-toggle')) {
                var dropdown = document.getElementById("dropdownMenu");
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        }
    </script>
</body>
</html>