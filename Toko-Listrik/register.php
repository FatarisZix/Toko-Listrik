<?php
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole();
}

$error = '';
$success = '';

// Get available tariffs for dropdown
$stmt = $pdo->query("SELECT tariff_name FROM tariffs ORDER BY tariff_name ASC");
$tariffs = $stmt->fetchAll();

if ($_POST) {
    // User data
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitizeInput($_POST['full_name']);
    
    // Customer data
    $address = sanitizeInput($_POST['address']);
    $tariff_type = sanitizeInput($_POST['tariff_type']);
    $power_capacity = sanitizeInput($_POST['power_capacity']);
    $phone = sanitizeInput($_POST['phone']);
    
    // Validation
    if (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif ($password !== $confirm_password) {
        $error = 'Konfirmasi password tidak cocok!';
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                throw new Exception("Username atau email sudah digunakan!");
            }
            
            // Insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hashed_password, $full_name]);
            $user_id = $pdo->lastInsertId();
            
            // Generate customer number (year + month + random 6 digits)
            $customer_number = date('Ym') . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Check if customer number already exists, regenerate if needed
            do {
                $stmt = $pdo->prepare("SELECT id FROM customers WHERE customer_number = ?");
                $stmt->execute([$customer_number]);
                if ($stmt->fetch()) {
                    $customer_number = date('Ym') . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                } else {
                    break;
                }
            } while (true);
            
            // Insert customer data linked to user
            $stmt = $pdo->prepare("INSERT INTO customers (user_id, customer_number, name, address, tariff_type, power_capacity, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $customer_number, $full_name, $address, $tariff_type, $power_capacity, $phone]);
            
            // Commit transaction
            $pdo->commit();
            
            $success = "Registrasi berhasil! No. Pelanggan Anda: <strong>$customer_number</strong><br>Silakan login untuk mengakses layanan.";
            
        } catch (Exception $e) {
            // Rollback transaction
            $pdo->rollback();
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pelanggan - PLN Payment System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        
        .container {
            width: 90%;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            padding: 40px;
            backdrop-filter: blur(10px);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .user-plus-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }
        
        .brand-title {
            background: linear-gradient(135deg, #ff6b6b, #feca57);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #666;
            font-size: 1rem;
            margin-bottom: 30px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            border-left: 4px solid #667eea;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
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
        
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            margin-bottom: 20px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .alert-danger {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .footer-text {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
        }
        
        .footer-text a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .footer-text a:hover {
            text-decoration: underline;
        }
        
        .info-box {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: #004085;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .register-card {
                padding: 25px;
                margin: 10px;
            }
            
            .brand-title {
                font-size: 1.8rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .form-section {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-card">
            <div class="register-header">
                <div class="user-plus-icon">👤⚡</div>
                <h2 class="brand-title">Daftar Pelanggan PLN</h2>
                <p class="subtitle">Lengkapi data untuk menjadi pelanggan listrik PLN</p>
            </div>
            
            <div class="info-box">
                <strong>ℹ️ Informasi:</strong> Dengan mendaftar, Anda akan mendapatkan nomor pelanggan PLN dan dapat mengakses layanan pembayaran listrik online.
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    ⚠️ <?= $error ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✅ <?= $success ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST">
                <!-- Data Akun -->
                <div class="form-section">
                    <div class="section-title">
                        👤 Data Akun
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" name="full_name" 
                                   placeholder="Nama sesuai KTP" required 
                                   value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">No. Telepon</label>
                            <input type="tel" class="form-control" name="phone" 
                                   placeholder="08xxxxxxxxxx" required
                                   value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" 
                                   placeholder="Username untuk login" required
                                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   placeholder="email@example.com" required
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" 
                                   placeholder="Minimal 6 karakter" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password</label>
                            <input type="password" class="form-control" name="confirm_password" 
                                   placeholder="Ulangi password" required>
                        </div>
                    </div>
                </div>

                <!-- Data Pelanggan Listrik -->
                <div class="form-section">
                    <div class="section-title">
                        ⚡ Data Instalasi Listrik
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea class="form-control" name="address" rows="3" 
                                  placeholder="Alamat lengkap tempat instalasi listrik" required><?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Jenis Tarif</label>
                            <select class="form-control" name="tariff_type" required>
                                <option value="">Pilih Jenis Tarif</option>
                                <?php foreach ($tariffs as $tariff): ?>
                                    <option value="<?= $tariff['tariff_name'] ?>" 
                                            <?= (isset($_POST['tariff_type']) && $_POST['tariff_type'] == $tariff['tariff_name']) ? 'selected' : '' ?>>
                                        <?= $tariff['tariff_name'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Daya Listrik (VA)</label>
                            <select class="form-control" name="power_capacity" required>
                                <option value="">Pilih Daya</option>
                                <option value="450" <?= (isset($_POST['power_capacity']) && $_POST['power_capacity'] == '450') ? 'selected' : '' ?>>450 VA</option>
                                <option value="900" <?= (isset($_POST['power_capacity']) && $_POST['power_capacity'] == '900') ? 'selected' : '' ?>>900 VA</option>
                                <option value="1300" <?= (isset($_POST['power_capacity']) && $_POST['power_capacity'] == '1300') ? 'selected' : '' ?>>1300 VA</option>
                                <option value="2200" <?= (isset($_POST['power_capacity']) && $_POST['power_capacity'] == '2200') ? 'selected' : '' ?>>2200 VA</option>
                                <option value="3500" <?= (isset($_POST['power_capacity']) && $_POST['power_capacity'] == '3500') ? 'selected' : '' ?>>3500 VA</option>
                                <option value="5500" <?= (isset($_POST['power_capacity']) && $_POST['power_capacity'] == '5500') ? 'selected' : '' ?>>5500 VA</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    ⚡ Daftar Sebagai Pelanggan PLN
                </button>
            </form>
            <?php endif; ?>
            
            <div class="footer-text">
                Sudah punya akun? <a href="login.php">Login di sini</a>
            </div>
        </div>
    </div>
</body>
</html>