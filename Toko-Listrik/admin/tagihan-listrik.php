<?php
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: /Toko-Listrik/login.php');
    exit();
}

$message = '';
$error = '';

// Handle actions
if ($_POST) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'create_manual') {
            $customer_id = sanitizeInput($_POST['customer_id']);
            $period_month = sanitizeInput($_POST['period_month']);
            $kwh_usage = sanitizeInput($_POST['kwh_usage']);
            $admin_note = sanitizeInput($_POST['admin_note']);
            
            $result = createManualBill($customer_id, $period_month, $kwh_usage, $admin_note);
            
            if ($result['success']) {
                $message = "Tagihan manual berhasil dibuat! Total: Rp " . number_format($result['total_amount'], 0, ',', '.');
            } else {
                $error = $result['error'];
            }
        } elseif ($_POST['action'] == 'delete') {
            $bill_id = $_POST['bill_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM bills WHERE id = ?");
                $stmt->execute([$bill_id]);
                $message = "Tagihan berhasil dihapus!";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Get all bills
$stmt = $pdo->query("
    SELECT b.*, c.customer_number, c.name as customer_name, c.tariff_type
    FROM bills b
    JOIN customers c ON b.customer_id = c.id
    ORDER BY b.bill_month DESC, c.customer_number ASC
");
$bills = $stmt->fetchAll();

// Get all customers for manual billing
$stmt = $pdo->query("SELECT id, customer_number, name FROM customers ORDER BY customer_number");
$customers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagihan Listrik - PLN Admin</title>
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
            margin-bottom: 30px;
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
            padding: 5px 12px;
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
        
        .price-display {
            font-weight: bold;
            color: #28a745;
        }
        
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: #004085;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
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
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
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
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
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
            <span style="color: white;">👤 <?= $_SESSION['full_name'] ?></span>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 15px;">
                <a href="dashboard.php" class="btn btn-secondary">
                    ← Kembali
                </a>
                <h2 class="page-title">💰 Kelola Tagihan Listrik</h2>
            </div>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <strong>ℹ️ Informasi:</strong> Kelola tagihan listrik pelanggan. Admin dapat membuat tagihan manual dan memonitor status pembayaran.
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

        <!-- Content Grid -->
        <div class="form-grid">
            <!-- Manual Bill Form -->
            <div class="content-card">
                <div class="card-header">
                    <h5>➕ Buat Tagihan Manual</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_manual">
                        
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
                        
                        <div class="form-group">
                            <label class="form-label">Penggunaan (kWh)</label>
                            <input type="number" class="form-control" name="kwh_usage" 
                                   placeholder="Contoh: 150" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Catatan Admin</label>
                            <textarea class="form-control" name="admin_note" rows="3" 
                                      placeholder="Catatan untuk tagihan ini..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            💾 Buat Tagihan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Bills Summary -->
            <div class="content-card">
                <div class="card-header">
                    <h5>📊 Ringkasan Tagihan</h5>
                </div>
                <div class="card-body">
                    <?php
                    $total_bills = count($bills);
                    $paid_bills = array_filter($bills, fn($b) => $b['status'] == 'paid');
                    $unpaid_bills = array_filter($bills, fn($b) => $b['status'] == 'unpaid');
                    $total_revenue = array_sum(array_column($paid_bills, 'total_amount'));
                    $total_pending = array_sum(array_column($unpaid_bills, 'total_amount'));
                    ?>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; text-align: center;">
                        <div>
                            <div style="font-size: 2rem; font-weight: bold; color: #667eea;"><?= $total_bills ?></div>
                            <div style="color: #666;">Total Tagihan</div>
                        </div>
                        <div>
                            <div style="font-size: 2rem; font-weight: bold; color: #28a745;"><?= count($paid_bills) ?></div>
                            <div style="color: #666;">Sudah Lunas</div>
                        </div>
                        <div>
                            <div style="font-size: 2rem; font-weight: bold; color: #ffc107;"><?= count($unpaid_bills) ?></div>
                            <div style="color: #666;">Belum Bayar</div>
                        </div>
                        <div>
                            <div style="font-size: 1.2rem; font-weight: bold; color: #dc3545;">
                                Rp <?= number_format($total_pending, 0, ',', '.') ?>
                            </div>
                            <div style="color: #666;">Total Pending</div>
                        </div>
                        <div>
                            <div style="font-size: 1.2rem; font-weight: bold; color: #28a745;">
                                Rp <?= number_format($total_revenue, 0, ',', '.') ?>
                            </div>
                            <div style="color: #666;">Total Revenue</div>
                        </div>
                        <div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #17a2b8;">
                                <?= array_sum(array_column($bills, 'kwh_usage')) ?> kWh
                            </div>
                            <div style="color: #666;">Total kWh</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bills Table -->
        <div class="content-card">
            <div class="card-header">
                <h5>🧾 Daftar Tagihan</h5>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No. Pelanggan</th>
                            <th>Nama</th>
                            <th>Periode</th>
                            <th>kWh Usage</th>
                            <th>Meter Reading</th>
                            <th>Total Tagihan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bills)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <div class="empty-icon">🧾</div>
                                        <p>Belum ada tagihan</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bills as $bill): ?>
                            <?php
                            // Get meter reading data if exists from usage_records
                            $stmt = $pdo->prepare("SELECT meter_start, meter_end FROM usage_records WHERE customer_id = ? AND period_month = ?");
                            $stmt->execute([$bill['customer_id'], $bill['bill_month']]);
                            $usage_data = $stmt->fetch();
                            ?>
                            <tr>
                                <td><strong><?= $bill['customer_number'] ?></strong></td>
                                <td><?= $bill['customer_name'] ?></td>
                                <td><?= date('F Y', strtotime($bill['bill_month'] . '-01')) ?></td>
                                <td>
                                    <strong><?= number_format($bill['kwh_usage']) ?> kWh</strong>
                                </td>
                                <td>
                                    <?php if ($usage_data): ?>
                                        <small style="color: #666;">
                                            Awal: <?= number_format($usage_data['meter_start']) ?><br>
                                            Akhir: <?= number_format($usage_data['meter_end']) ?>
                                        </small>
                                    <?php else: ?>
                                        <small style="color: #999;">Manual Entry</small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="price-display">Rp <?= number_format($bill['total_amount'], 0, ',', '.') ?></span></td>
                                <td>
                                    <?php if ($bill['status'] == 'paid'): ?>
                                        <span class="badge badge-success">Lunas</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Belum Bayar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus tagihan ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="bill_id" value="<?= $bill['id'] ?>">
                                        <button type="submit" class="btn btn-danger">
                                            🗑️ Hapus
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
</body>
</html>