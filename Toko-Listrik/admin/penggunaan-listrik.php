<?php
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: /Toko-Listrik/login.php');
    exit();
}

$message = '';
$error = '';

// Handle form submission
if ($_POST) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $customer_id = sanitizeInput($_POST['customer_id']);
            $period_month = sanitizeInput($_POST['period_month']);
            $kwh_usage = sanitizeInput($_POST['kwh_usage']);
            $meter_start = sanitizeInput($_POST['meter_start']);
            $meter_end = sanitizeInput($_POST['meter_end']);
            
            try {
                // Check if usage for this customer and period already exists
                $stmt = $pdo->prepare("SELECT id FROM usage_records WHERE customer_id = ? AND period_month = ?");
                $stmt->execute([$customer_id, $period_month]);
                if ($stmt->fetch()) {
                    $error = "Data penggunaan untuk pelanggan ini di bulan tersebut sudah ada!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO usage_records (customer_id, period_month, kwh_usage, meter_start, meter_end) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$customer_id, $period_month, $kwh_usage, $meter_start, $meter_end]);
                    $message = "Data penggunaan berhasil ditambahkan!";
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] == 'approve_report') {
            $report_id = sanitizeInput($_POST['report_id']);
            $admin_note = sanitizeInput($_POST['admin_note']);
            
            try {
                // Get report details
                $stmt = $pdo->prepare("
                    SELECT mr.*, c.customer_number, c.name as customer_name,
                           (SELECT meter_reading FROM meter_reports mr2 
                            WHERE mr2.customer_id = mr.customer_id 
                            AND mr2.period_month < mr.period_month 
                            AND mr2.status = 'approved' 
                            ORDER BY mr2.period_month DESC LIMIT 1) as previous_reading
                    FROM meter_reports mr
                    JOIN customers c ON mr.customer_id = c.id
                    WHERE mr.id = ?
                ");
                $stmt->execute([$report_id]);
                $report = $stmt->fetch();
                
                if (!$report) {
                    throw new Exception("Laporan tidak ditemukan!");
                }
                
                // Calculate kWh usage
                $meter_start = $report['previous_reading'] ?? 0;
                $meter_end = $report['meter_reading'];
                $kwh_usage = $meter_end - $meter_start;
                
                if ($kwh_usage < 0) {
                    throw new Exception("Meter reading tidak valid! Angka harus lebih besar dari reading sebelumnya.");
                }
                
                // Update report status
                $stmt = $pdo->prepare("UPDATE meter_reports SET status = 'approved', admin_note = ?, approved_by = ?, approved_date = NOW() WHERE id = ?");
                $stmt->execute([$admin_note, $_SESSION['user_id'], $report_id]);
                
                // Create usage record
                $stmt = $pdo->prepare("INSERT INTO usage_records (customer_id, period_month, kwh_usage, meter_start, meter_end) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE kwh_usage = ?, meter_start = ?, meter_end = ?");
                $stmt->execute([$report['customer_id'], $report['period_month'], $kwh_usage, $meter_start, $meter_end, $kwh_usage, $meter_start, $meter_end]);
                
                $message = "Laporan meter disetujui dan data penggunaan berhasil dibuat! Usage: {$kwh_usage} kWh";
                
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] == 'reject_report') {
            $report_id = sanitizeInput($_POST['report_id']);
            $admin_note = sanitizeInput($_POST['admin_note']);
            
            try {
                $stmt = $pdo->prepare("UPDATE meter_reports SET status = 'rejected', admin_note = ?, approved_by = ?, approved_date = NOW() WHERE id = ?");
                $stmt->execute([$admin_note, $_SESSION['user_id'], $report_id]);
                $message = "Laporan meter ditolak.";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM usage_records WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Data penggunaan berhasil dihapus!";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Create usage_records table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS usage_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        period_month VARCHAR(7) NOT NULL,
        kwh_usage INT NOT NULL,
        meter_start INT NOT NULL,
        meter_end INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        UNIQUE KEY unique_customer_period (customer_id, period_month)
    )");
} catch (Exception $e) {
    // Table might already exist
}

// Get all usage records with customer info
$stmt = $pdo->query("
    SELECT ur.*, c.customer_number, c.name as customer_name, c.tariff_type, c.power_capacity
    FROM usage_records ur
    JOIN customers c ON ur.customer_id = c.id 
    ORDER BY ur.period_month DESC, c.customer_number ASC
");
$usage_records = $stmt->fetchAll();

// Get all customers for dropdown
$stmt = $pdo->query("SELECT id, customer_number, name FROM customers ORDER BY customer_number");
$customers = $stmt->fetchAll();

// Get pending meter reports for approval
$stmt = $pdo->query("
    SELECT mr.*, c.customer_number, c.name as customer_name,
           (SELECT meter_reading FROM meter_reports mr2 
            WHERE mr2.customer_id = mr.customer_id 
            AND mr2.period_month < mr.period_month 
            AND mr2.status = 'approved' 
            ORDER BY mr2.period_month DESC LIMIT 1) as previous_reading
    FROM meter_reports mr
    JOIN customers c ON mr.customer_id = c.id
    WHERE mr.status = 'pending'
    ORDER BY mr.report_date ASC
");
$pending_reports = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penggunaan Listrik - PLN Admin</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
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
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b, #feca57);
            color: white;
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
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
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
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
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal.show {
            display: block;
        }
        
        .modal-dialog {
            position: relative;
            width: 90%;
            max-width: 600px;
            margin: 50px auto;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.3rem;
            margin: 0;
        }
        
        .close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
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
        
        .modal-footer {
            padding: 20px 25px;
            background: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: #004085;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px 15px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
                gap: 20px;
            }
            
            .page-header > div {
                flex-direction: column;
                gap: 15px;
                align-items: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .modal-dialog {
                margin: 20px auto;
                width: 95%;
            }
            
            .modal-body {
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
                ⚡ PLN Admin Panel
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
            <div style="display: flex; align-items: center; gap: 15px;">
                <a href="dashboard.php" class="btn btn-secondary">
                    ← Kembali
                </a>
                <h2 class="page-title">📈 Kelola Penggunaan Listrik</h2>
            </div>
            <button class="btn btn-primary" onclick="openModal()">
                ➕ Input Penggunaan
            </button>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <strong>ℹ️ Informasi:</strong> Kelola penggunaan listrik dan review laporan meter reading dari pelanggan. Data yang disetujui akan digunakan untuk generate tagihan otomatis.
        </div>

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

        <!-- Pending Meter Reports -->
        <?php if (!empty($pending_reports)): ?>
        <div class="content-card">
            <div class="card-header">
                <h5>📋 Laporan Meter Menunggu Persetujuan (<?= count($pending_reports) ?>)</h5>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No. Pelanggan</th>
                            <th>Nama</th>
                            <th>Periode</th>
                            <th>Meter Reading</th>
                            <th>Estimasi kWh</th>
                            <th>Tanggal Lapor</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_reports as $report): ?>
                        <?php 
                        $meter_start = $report['previous_reading'] ?? 0;
                        $meter_end = $report['meter_reading'];
                        $estimated_kwh = $meter_end - $meter_start;
                        ?>
                        <tr>
                            <td><strong><?= $report['customer_number'] ?></strong></td>
                            <td><?= $report['customer_name'] ?></td>
                            <td><?= date('F Y', strtotime($report['period_month'] . '-01')) ?></td>
                            <td>
                                <strong><?= number_format($report['meter_reading']) ?></strong><br>
                                <small style="color: #666;">
                                    Sebelumnya: <?= number_format($meter_start) ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($estimated_kwh >= 0): ?>
                                    <strong style="color: #28a745;"><?= number_format($estimated_kwh) ?> kWh</strong>
                                <?php else: ?>
                                    <span style="color: #dc3545;">Invalid!</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($report['report_date'])) ?></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve_report">
                                        <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                        <input type="hidden" name="admin_note" value="Disetujui - Usage: <?= $estimated_kwh ?> kWh">
                                        <button type="submit" class="btn btn-success" style="padding: 5px 10px; font-size: 0.8rem;"
                                                onclick="return confirm('Setujui laporan meter ini?')" 
                                                <?= $estimated_kwh < 0 ? 'disabled' : '' ?>>
                                            ✅ Setujui
                                        </button>
                                    </form>
                                    <button onclick="openRejectModal(<?= $report['id'] ?>, '<?= $report['customer_name'] ?>')" 
                                            class="btn btn-danger" style="padding: 5px 10px; font-size: 0.8rem;">
                                        ❌ Tolak
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Usage Records Table -->
        <div class="content-card">
            <div class="card-header">
                <h5>📊 Data Penggunaan Listrik</h5>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No. Pelanggan</th>
                            <th>Nama</th>
                            <th>Periode</th>
                            <th>Meter Awal</th>
                            <th>Meter Akhir</th>
                            <th>Penggunaan (kWh)</th>
                            <th>Tarif</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usage_records)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="empty-icon">📊</div>
                                        <p>Belum ada data penggunaan listrik</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usage_records as $record): ?>
                            <tr>
                                <td><strong><?= $record['customer_number'] ?></strong></td>
                                <td><?= $record['customer_name'] ?></td>
                                <td><?= date('F Y', strtotime($record['period_month'] . '-01')) ?></td>
                                <td><?= number_format($record['meter_start']) ?></td>
                                <td><?= number_format($record['meter_end']) ?></td>
                                <td><strong><?= number_format($record['kwh_usage']) ?> kWh</strong></td>
                                <td><span class="badge badge-info"><?= $record['tariff_type'] ?></span></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus data penggunaan ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $record['id'] ?>">
                                        <button type="submit" class="btn btn-danger">
                                            🗑️
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Usage Modal -->
    <div class="modal" id="addModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📈 Input Penggunaan Listrik</h5>
                    <button type="button" class="close" onclick="closeModal()">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-group">
                            <label class="form-label">Pelanggan</label>
                            <select class="form-control" name="customer_id" required>
                                <option value="">Pilih Pelanggan</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['id'] ?>"><?= $customer['customer_number'] ?> - <?= $customer['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Periode Bulan</label>
                            <input type="month" class="form-control" name="period_month" 
                                   value="<?= date('Y-m') ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Meter Stand Awal</label>
                                <input type="number" class="form-control" name="meter_start" 
                                       placeholder="Contoh: 12500" min="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Meter Stand Akhir</label>
                                <input type="number" class="form-control" name="meter_end" 
                                       placeholder="Contoh: 12650" min="0" required 
                                       onchange="calculateUsage()">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Total Penggunaan (kWh)</label>
                            <input type="number" class="form-control" name="kwh_usage" 
                                   placeholder="Akan otomatis terhitung" min="0" required readonly>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            💾 Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">❌ Tolak Laporan Meter</h5>
                    <button type="button" class="close" onclick="closeRejectModal()">&times;</button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject_report">
                        <input type="hidden" name="report_id" id="reject_report_id">
                        
                        <p>Tolak laporan meter untuk: <strong id="reject_customer_name"></strong></p>
                        
                        <div class="form-group">
                            <label class="form-label">Alasan Penolakan</label>
                            <textarea class="form-control" name="admin_note" rows="3" 
                                      placeholder="Berikan alasan penolakan..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Batal</button>
                        <button type="submit" class="btn btn-danger">
                            ❌ Tolak Laporan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Styles -->
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal.show {
            display: block;
        }
        
        .modal-dialog {
            position: relative;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #ff6b6b, #feca57);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.3rem;
            margin: 0;
        }
        
        .close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .modal-footer {
            padding: 20px 25px;
            background: #f8f9fa;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white;
            border-radius: 20px;
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            color: white;
        }
    </style>

    <script>
        function toggleDropdown() {
            var dropdown = document.getElementById("dropdownMenu");
            dropdown.classList.toggle("show");
        }

        function openModal() {
            document.getElementById("addModal").classList.add("show");
        }

        function closeModal() {
            document.getElementById("addModal").classList.remove("show");
        }

        function openRejectModal(reportId, customerName) {
            document.getElementById("reject_report_id").value = reportId;
            document.getElementById("reject_customer_name").textContent = customerName;
            document.getElementById("rejectModal").classList.add("show");
        }

        function closeRejectModal() {
            document.getElementById("rejectModal").classList.remove("show");
        }

        function calculateUsage() {
            var meterStart = parseInt(document.querySelector('input[name="meter_start"]').value) || 0;
            var meterEnd = parseInt(document.querySelector('input[name="meter_end"]').value) || 0;
            var usage = meterEnd - meterStart;
            
            if (usage >= 0) {
                document.querySelector('input[name="kwh_usage"]').value = usage;
            } else {
                alert('Meter akhir harus lebih besar dari meter awal!');
                document.querySelector('input[name="meter_end"]').value = '';
                document.querySelector('input[name="kwh_usage"]').value = '';
            }
        }

        // Auto calculate when meter start changes too
        document.querySelector('input[name="meter_start"]').addEventListener('input', calculateUsage);

        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('.dropdown-toggle')) {
                var dropdown = document.getElementById("dropdownMenu");
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
            
            // Close modal when clicking outside
            var modal = document.getElementById("addModal");
            if (event.target == modal) {
                closeModal();
            }
            
            var rejectModal = document.getElementById("rejectModal");
            if (event.target == rejectModal) {
                closeRejectModal();
            }
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>