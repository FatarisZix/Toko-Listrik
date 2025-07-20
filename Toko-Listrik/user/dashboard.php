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

// Get user's customers and bills
$customers = [];
$recent_bills = [];
$total_unpaid = 0;
$unpaid_count = 0;

// Get customers for current user
$stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customers = $stmt->fetchAll();

if (!empty($customers)) {
    $customer_ids = array_column($customers, 'id');
    $placeholders = str_repeat('?,', count($customer_ids) - 1) . '?';
    
    // Get bills with tariff info
    $stmt = $pdo->prepare("
        SELECT b.*, c.name as customer_name, c.customer_number, c.tariff_type,
               t.price_per_kwh, t.admin_fee
        FROM bills b 
        JOIN customers c ON b.customer_id = c.id 
        LEFT JOIN tariffs t ON c.tariff_type = t.tariff_name
        WHERE b.customer_id IN ($placeholders)
        ORDER BY b.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute($customer_ids);
    $recent_bills = $stmt->fetchAll();
    
    // Get total unpaid amount and count
    $stmt = $pdo->prepare("
        SELECT SUM(total_amount) as total_unpaid, COUNT(*) as unpaid_count
        FROM bills b 
        WHERE b.customer_id IN ($placeholders) AND b.status = 'unpaid'
    ");
    $stmt->execute($customer_ids);
    $unpaid_data = $stmt->fetch();
    $total_unpaid = $unpaid_data['total_unpaid'] ?? 0;
    $unpaid_count = $unpaid_data['unpaid_count'] ?? 0;
}

// Get all tariffs for display
$stmt = $pdo->query("SELECT * FROM tariffs ORDER BY tariff_name ASC");
$tariffs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard User - PLN Payment</title>
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
            position: sticky;
            top: 0;
            z-index: 100;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .welcome-card h2 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .welcome-card p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
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
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .btn-pay {
            background: linear-gradient(135deg, #ff6b6b, #feca57);
            color: white;
        }
        
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
            color: white;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .price-display {
            font-weight: bold;
            color: #28a745;
        }
        
        .alert-info {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 15px;
            padding: 20px;
            color: #004085;
            margin-bottom: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .tariff-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .tariff-item:last-child {
            border-bottom: none;
        }
        
        .tariff-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .tariff-price {
            color: #28a745;
            font-weight: bold;
        }
        
        .tariff-desc {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }
            
            .welcome-card {
                padding: 25px;
                text-align: center;
            }
            
            .welcome-card h2 {
                font-size: 1.6rem;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-icon {
                font-size: 2.5rem;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .table-container {
                font-size: 0.9rem;
            }
            
            th, td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-content">
            <a href="#" class="navbar-brand">
                ⚡ PLN Payment
            </a>
            <div class="navbar-user">
                <button class="dropdown-toggle" onclick="toggleDropdown()">
                    👤 <?= $_SESSION['full_name'] ?> ▼
                </button>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="/Toko-Listrik/logout.php" class="dropdown-item">🚪 Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h2>🏠 Dashboard Pelanggan</h2>
            <p>Selamat datang, <?= $_SESSION['full_name'] ?>! Kelola pembayaran listrik Anda dengan mudah dan aman.</p>
        </div>

        <?php if (empty($customers)): ?>
            <div class="alert-info">
                <h5>ℹ️ Informasi Akun</h5>
                <p class="mb-0">Akun Anda belum memiliki data pelanggan listrik. Silakan hubungi admin untuk menambahkan data pelanggan Anda ke sistem.</p>
            </div>
        <?php else: ?>
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?= count($customers) ?></div>
                    <div class="stat-label">Total Pelanggan</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📄</div>
                    <div class="stat-number"><?= $unpaid_count ?></div>
                    <div class="stat-label">Tagihan Belum Bayar</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-number">Rp <?= number_format($total_unpaid, 0, ',', '.') ?></div>
                    <div class="stat-label">Total Belum Dibayar</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">⚡</div>
                    <div class="stat-number">Normal</div>
                    <div class="stat-label">Status Listrik</div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Bills Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h5>🧾 Tagihan Listrik Terbaru</h5>
                    </div>
                    
                    <?php if (empty($recent_bills)): ?>
                        <div class="card-body">
                            <div class="empty-state">
                                <div class="empty-icon">📋</div>
                                <p>Belum ada tagihan tersedia</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No. Pelanggan</th>
                                        <th>Periode</th>
                                        <th>Penggunaan</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bills as $bill): ?>
                                    <tr>
                                        <td>
                                            <strong><?= $bill['customer_number'] ?></strong><br>
                                            <small><?= $bill['customer_name'] ?></small>
                                        </td>
                                        <td><?= date('F Y', strtotime($bill['bill_month'] . '-01')) ?></td>
                                        <td><?= number_format($bill['kwh_usage']) ?> kWh</td>
                                        <td>
                                            <span class="price-display">Rp <?= number_format($bill['total_amount'], 0, ',', '.') ?></span><br>
                                            <small style="color: #666;">
                                                kWh: Rp <?= number_format($bill['amount'], 0, ',', '.') ?> + 
                                                Admin: Rp <?= number_format($bill['admin_fee'], 0, ',', '.') ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($bill['status'] == 'paid'): ?>
                                                <span class="badge badge-success">✅ Lunas</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">⏱️ Belum Bayar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($bill['status'] == 'unpaid'): ?>
                                                <a href="payment.php?bill_id=<?= $bill['id'] ?>" class="btn btn-pay">
                                                    💳 Bayar
                                                </a>
                                            <?php else: ?>
                                                <span class="badge badge-success">Lunas</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="card-body" style="text-align: center; padding-top: 20px;">
                            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                                <a href="lapor-meter.php" class="btn btn-view">
                                    📊 Lapor Meter Reading
                                </a>
                                <a href="bills.php" class="btn btn-view">
                                    📋 Lihat Semua Tagihan
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tariff Info -->
                <div class="content-card">
                    <div class="card-header">
                        <h5>💰 Informasi Tarif Listrik</h5>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (empty($tariffs)): ?>
                            <div style="padding: 25px; text-align: center; color: #666;">
                                Informasi tarif belum tersedia
                            </div>
                        <?php else: ?>
                            <?php foreach ($tariffs as $tariff): ?>
                            <div class="tariff-item">
                                <div class="tariff-name"><?= $tariff['tariff_name'] ?></div>
                                <div class="tariff-price">
                                    Rp <?= number_format($tariff['price_per_kwh'], 0, ',', '.') ?>/kWh
                                </div>
                                <div style="color: #666; font-size: 0.85rem;">
                                    Biaya Admin: Rp <?= number_format($tariff['admin_fee'], 0, ',', '.') ?>
                                </div>
                                <div class="tariff-desc"><?= $tariff['description'] ?></div>
                            </div>
                            <?php endforeach; ?>
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