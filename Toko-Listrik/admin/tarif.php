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
            $tariff_name = sanitizeInput($_POST['tariff_name']);
            $price_per_kwh = sanitizeInput($_POST['price_per_kwh']);
            $admin_fee = sanitizeInput($_POST['admin_fee']);
            $description = sanitizeInput($_POST['description']);
            
            try {
                // Check if tariff already exists
                $stmt = $pdo->prepare("SELECT id FROM tariffs WHERE tariff_name = ?");
                $stmt->execute([$tariff_name]);
                if ($stmt->fetch()) {
                    $error = "Tarif dengan nama tersebut sudah ada!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO tariffs (tariff_name, price_per_kwh, admin_fee, description) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$tariff_name, $price_per_kwh, $admin_fee, $description]);
                    $message = "Tarif berhasil ditambahkan!";
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = sanitizeInput($_POST['id']);
            $tariff_name = sanitizeInput($_POST['tariff_name']);
            $price_per_kwh = sanitizeInput($_POST['price_per_kwh']);
            $admin_fee = sanitizeInput($_POST['admin_fee']);
            $description = sanitizeInput($_POST['description']);
            
            try {
                $stmt = $pdo->prepare("UPDATE tariffs SET tariff_name = ?, price_per_kwh = ?, admin_fee = ?, description = ? WHERE id = ?");
                $stmt->execute([$tariff_name, $price_per_kwh, $admin_fee, $description, $id]);
                $message = "Tarif berhasil diupdate!";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        } elseif ($_POST['action'] == 'delete') {
            $id = $_POST['id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM tariffs WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Tarif berhasil dihapus!";
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Create tariffs table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tariffs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tariff_name VARCHAR(100) UNIQUE NOT NULL,
        price_per_kwh DECIMAL(10,2) NOT NULL,
        admin_fee DECIMAL(10,2) DEFAULT 2500.00,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Insert default tariffs if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tariffs");
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        $default_tariffs = [
            ['Rumah Tangga', 1444.70, 2500.00, 'Tarif untuk pelanggan rumah tangga dengan daya 450VA - 2200VA'],
            ['Bisnis Kecil', 1699.53, 3000.00, 'Tarif untuk usaha kecil dengan daya 1300VA - 5500VA'],
            ['Bisnis Menengah', 1956.72, 5000.00, 'Tarif untuk usaha menengah dengan daya di atas 5500VA']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO tariffs (tariff_name, price_per_kwh, admin_fee, description) VALUES (?, ?, ?, ?)");
        foreach ($default_tariffs as $tariff) {
            $stmt->execute($tariff);
        }
    }
} catch (Exception $e) {
    // Table might already exist
}

// Get all tariffs
$stmt = $pdo->query("SELECT * FROM tariffs ORDER BY tariff_name ASC");
$tariffs = $stmt->fetchAll();

// Get specific tariff for editing
$edit_tariff = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM tariffs WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_tariff = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tarif - PLN Admin</title>
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
        
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
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
        
        .price-display {
            font-size: 1.2rem;
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
            
            .content-grid {
                grid-template-columns: 1fr;
                gap: 20px;
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
                <h2 class="page-title">🧮 Kelola Tarif Listrik</h2>
            </div>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <strong>ℹ️ Informasi:</strong> Kelola tarif harga per kWh untuk berbagai jenis pelanggan. Tarif ini akan digunakan untuk perhitungan tagihan otomatis berdasarkan penggunaan listrik.
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
        <div class="content-grid">
            <!-- Tariff Form -->
            <div class="content-card">
                <div class="card-header">
                    <h5><?= $edit_tariff ? '✏️ Edit Tarif' : '➕ Tambah Tarif Baru' ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $edit_tariff ? 'edit' : 'add' ?>">
                        <?php if ($edit_tariff): ?>
                            <input type="hidden" name="id" value="<?= $edit_tariff['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label class="form-label">Nama Tarif</label>
                            <input type="text" class="form-control" name="tariff_name" 
                                   placeholder="Contoh: Rumah Tangga" required
                                   value="<?= $edit_tariff ? $edit_tariff['tariff_name'] : '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Harga per kWh (Rp)</label>
                            <input type="number" step="0.01" class="form-control" name="price_per_kwh" 
                                   placeholder="Contoh: 1444.70" required
                                   value="<?= $edit_tariff ? $edit_tariff['price_per_kwh'] : '' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Biaya Admin (Rp)</label>
                            <input type="number" step="0.01" class="form-control" name="admin_fee" 
                                   placeholder="Contoh: 2500.00" required
                                   value="<?= $edit_tariff ? $edit_tariff['admin_fee'] : '2500.00' ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Deskripsi tarif..."><?= $edit_tariff ? $edit_tariff['description'] : '' ?></textarea>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">
                                💾 <?= $edit_tariff ? 'Update' : 'Simpan' ?>
                            </button>
                            <?php if ($edit_tariff): ?>
                                <a href="tarif.php" class="btn btn-secondary">Batal</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tariff List -->
            <div class="content-card">
                <div class="card-header">
                    <h5>💰 Daftar Tarif Listrik</h5>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Jenis Tarif</th>
                                <th>Harga/kWh</th>
                                <th>Biaya Admin</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tariffs)): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <div class="empty-icon">🧮</div>
                                            <p>Belum ada data tarif</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tariffs as $tariff): ?>
                                <tr>
                                    <td>
                                        <strong><?= $tariff['tariff_name'] ?></strong><br>
                                        <small style="color: #666;"><?= $tariff['description'] ?></small>
                                    </td>
                                    <td>
                                        <span class="price-display">Rp <?= number_format($tariff['price_per_kwh'], 2, ',', '.') ?></span>
                                    </td>
                                    <td>Rp <?= number_format($tariff['admin_fee'], 0, ',', '.') ?></td>
                                    <td>
                                        <a href="?edit=<?= $tariff['id'] ?>" class="btn btn-success">
                                            ✏️
                                        </a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus tarif ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $tariff['id'] ?>">
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