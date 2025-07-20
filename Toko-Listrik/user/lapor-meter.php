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

$message = '';
$error = '';

// Get user's customers
$stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customers = $stmt->fetchAll();

// Handle form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'submit_report') {
    $customer_id = sanitizeInput($_POST['customer_id']);
    $period_month = sanitizeInput($_POST['period_month']);
    $meter_start = sanitizeInput($_POST['meter_start']);
    $meter_end = sanitizeInput($_POST['meter_end']);
    $kwh_usage = $meter_end - $meter_start;
    $notes = sanitizeInput($_POST['notes']);
    
    try {
        // Verify customer belongs to user
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND user_id = ?");
        $stmt->execute([$customer_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Customer tidak valid!");
        }
        
        // Validate meter readings
        if ($meter_end <= $meter_start) {
            throw new Exception("Meter akhir harus lebih besar dari meter awal!");
        }
        
        if ($kwh_usage < 0 || $kwh_usage > 10000) {
            throw new Exception("Penggunaan kWh tidak wajar! Periksa kembali angka meter Anda.");
        }
        
        // Check if report already exists
        $stmt = $pdo->prepare("SELECT id, status FROM meter_reports WHERE customer_id = ? AND period_month = ?");
        $stmt->execute([$customer_id, $period_month]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            if ($existing['status'] == 'pending') {
                throw new Exception("Laporan meter untuk periode ini sudah ada dan menunggu persetujuan admin!");
            } elseif ($existing['status'] == 'approved') {
                throw new Exception("Laporan meter untuk periode ini sudah disetujui!");
            } else {
                // If rejected, allow new report (update existing)
                $stmt = $pdo->prepare("UPDATE meter_reports SET meter_start = ?, meter_end = ?, kwh_usage = ?, report_date = NOW(), status = 'pending', admin_note = NULL WHERE id = ?");
                $stmt->execute([$meter_start, $meter_end, $kwh_usage, $existing['id']]);
                $message = "Laporan meter berhasil diupdate dan dikirim ulang untuk review!";
            }
        } else {
            // Create new report
            $stmt = $pdo->prepare("INSERT INTO meter_reports (customer_id, period_month, meter_start, meter_end, kwh_usage, status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$customer_id, $period_month, $meter_start, $meter_end, $kwh_usage]);
            $message = "Laporan meter reading berhasil dikirim! Penggunaan: {$kwh_usage} kWh. Menunggu persetujuan admin.";
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get user's meter reports with customer info
if (!empty($customers)) {
    $customer_ids = array_column($customers, 'id');
    $placeholders = str_repeat('?,', count($customer_ids) - 1) . '?';
    
    $stmt = $pdo->prepare("
        SELECT mr.*, c.customer_number, c.name as customer_name
        FROM meter_reports mr
        JOIN customers c ON mr.customer_id = c.id
        WHERE mr.customer_id IN ($placeholders)
        ORDER BY mr.period_month DESC, mr.report_date DESC
    ");
    $stmt->execute($customer_ids);
    $meter_reports = $stmt->fetchAll();
} else {
    $meter_reports = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lapor Meter Reading - PLN Payment</title>
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
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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
        
        .alert-info {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 15px;
            padding: 20px;
            color: #004085;
            margin-bottom: 20px;
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
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
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
        
        .meter-tips {
            background: #f0f8ff;
            border: 1px solid #b8daff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .meter-tips h6 {
            color: #004085;
            margin-bottom: 10px;
        }
        
        .meter-tips ul {
            margin: 0;
            padding-left: 20px;
            color: #004085;
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
            <h2 class="page-title">📊 Lapor Meter Reading</h2>
        </div>

        <?php if (empty($customers)): ?>
            <div class="alert-info">
                <h5>ℹ️ Informasi</h5>
                <p class="mb-0">Anda belum memiliki data pelanggan listrik. Silakan hubungi admin untuk menambahkan data pelanggan Anda ke sistem.</p>
            </div>
        <?php else: ?>
            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    ✅ <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    ⚠️ <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Report Form -->
                <div class="content-card">
                    <div class="card-header">
                        <h5>📝 Lapor Meter Reading Baru</h5>
                    </div>
                    <div class="card-body">
                        <div class="meter-tips">
                            <h6>💡 Tips Membaca Meter:</h6>
                            <ul>
                                <li>Baca angka dari kiri ke kanan</li>
                                <li>Abaikan angka desimal (biasanya warna merah)</li>
                                <li>Pastikan angka yang dilaporkan lebih besar dari reading sebelumnya</li>
                                <li>Foto meter sebagai bukti (opsional)</li>
                            </ul>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="submit_report">
                            
                            <div class="form-group">
                                <label class="form-label">Pilih Instalasi</label>
                                <select class="form-control" name="customer_id" required>
                                    <option value="">Pilih No. Pelanggan</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?= $customer['id'] ?>">
                                            <?= $customer['customer_number'] ?> - <?= $customer['name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Periode Bulan</label>
                                <input type="month" class="form-control" name="period_month" 
                                       value="<?= date('Y-m') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Meter Awal</label>
                                <input type="number" class="form-control" name="meter_start" id="meterStart"
                                       placeholder="Contoh: 12000" min="0" required onchange="calculateKwh()">
                                <small style="color: #666;">
                                    Angka meter pada awal periode/bulan lalu
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Meter Akhir (Saat Ini)</label>
                                <input type="number" class="form-control" name="meter_end" id="meterEnd"
                                       placeholder="Contoh: 12150" min="0" required onchange="calculateKwh()">
                                <small style="color: #666;">
                                    Angka meter yang tertera saat ini
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Penggunaan kWh (Otomatis)</label>
                                <input type="number" class="form-control" id="kwhUsage" 
                                       placeholder="Akan otomatis terhitung" readonly 
                                       style="background: #f8f9fa; font-weight: bold; font-size: 1.1rem;">
                                <small style="color: #666;">
                                    Penggunaan = Meter Akhir - Meter Awal
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Catatan (Opsional)</label>
                                <textarea class="form-control" name="notes" rows="3" 
                                          placeholder="Catatan tambahan..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                📤 Kirim Laporan
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Reports History -->
                <div class="content-card">
                    <div class="card-header">
                        <h5>📋 Riwayat Laporan</h5>
                    </div>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Periode</th>
                                    <th>Meter Awal</th>
                                    <th>Meter Akhir</th>
                                    <th>kWh Usage</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($meter_reports)): ?>
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state">
                                                <div class="empty-icon">📊</div>
                                                <p>Belum ada laporan meter</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($meter_reports as $report): ?>
                                    <tr>
                                        <td><?= date('M Y', strtotime($report['period_month'] . '-01')) ?></td>
                                        <td><?= isset($report['meter_start']) ? number_format($report['meter_start']) : '-' ?></td>
                                        <td><?= isset($report['meter_end']) ? number_format($report['meter_end']) : number_format($report['meter_reading']) ?></td>
                                        <td>
                                            <?php if (isset($report['kwh_usage'])): ?>
                                                <strong><?= number_format($report['kwh_usage']) ?> kWh</strong>
                                            <?php elseif ($report['previous_reading'] && $report['status'] == 'approved'): ?>
                                                <?= number_format($report['meter_reading'] - $report['previous_reading']) ?> kWh
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($report['status'] == 'pending'): ?>
                                                <span class="badge badge-warning">⏱️ Menunggu</span>
                                            <?php elseif ($report['status'] == 'approved'): ?>
                                                <span class="badge badge-success">✅ Disetujui</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">❌ Ditolak</span>
                                                <?php if ($report['admin_note']): ?>
                                                    <br><small style="color: #666;"><?= $report['admin_note'] ?></small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
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

        function calculateKwh() {
            var meterStart = parseInt(document.getElementById('meterStart').value) || 0;
            var meterEnd = parseInt(document.getElementById('meterEnd').value) || 0;
            var usage = meterEnd - meterStart;
            
            if (meterStart > 0 && meterEnd > 0) {
                if (usage >= 0) {
                    document.getElementById('kwhUsage').value = usage + ' kWh';
                    document.getElementById('kwhUsage').style.color = '#28a745';
                } else {
                    document.getElementById('kwhUsage').value = 'Error: Meter akhir harus > meter awal';
                    document.getElementById('kwhUsage').style.color = '#dc3545';
                }
            } else {
                document.getElementById('kwhUsage').value = '';
            }
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