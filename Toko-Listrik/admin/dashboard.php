<?php
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: /Toko-Listrik/login.php');
    exit();
}

// Get only necessary data for dashboard
$stmt = $pdo->query("SELECT COUNT(*) as unpaid_bills FROM bills WHERE status = 'unpaid'");
$unpaid_bills = $stmt->fetch()['unpaid_bills'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PLN Payment</title>
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
            display: inline-block;
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
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .menu-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            text-align: center;
        }
        
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            text-decoration: none;
            color: inherit;
        }
        
        .menu-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: white;
        }
        
        .menu-card h5 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: #333;
        }
        
        .menu-card p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .icon-usage { background: linear-gradient(135deg, #667eea, #764ba2); }
        .icon-customers { background: linear-gradient(135deg, #43e97b, #38f9d7); }
        .icon-bills { background: linear-gradient(135deg, #fa709a, #fee140); }
        .icon-tariff { background: linear-gradient(135deg, #ff9a9e, #fecfef); }
        .icon-payment { background: linear-gradient(135deg, #4facfe, #00f2fe); }
        .icon-users { background: linear-gradient(135deg, #a8edea, #fed6e3); }
        
        @media (max-width: 768px) {
            .navbar-content {
                padding: 0 15px;
            }
            
            .navbar-brand {
                font-size: 1.3rem;
            }
            
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
            
            .menu-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .menu-card {
                padding: 25px;
            }
            
            .menu-icon {
                width: 70px;
                height: 70px;
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-content">
            <a href="#" class="navbar-brand">
                ⚡ PLN Admin Panel
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
            <h2>📊 Dashboard Admin</h2>
            <p>Selamat datang, <?= $_SESSION['full_name'] ?>! Kelola sistem pembayaran listrik PLN dengan mudah.</p>
        </div>

        <!-- Menu Management -->
        <div class="menu-grid">
            <a href="penggunaan-listrik.php" class="menu-card">
                <div class="menu-icon icon-usage">
                    📈
                </div>
                <h5>Penggunaan Listrik</h5>
                <p>Monitor dan kelola data penggunaan listrik pelanggan</p>
            </a>
            
            <a href="pelanggan-listrik.php" class="menu-card">
                <div class="menu-icon icon-customers">
                    👥
                </div>
                <h5>Pelanggan Listrik</h5>
                <p>Kelola data pelanggan listrik PLN</p>
            </a>
            
            <a href="tagihan-listrik.php" class="menu-card">
                <div class="menu-icon icon-bills">
                    💰
                </div>
                <h5>Tagihan Listrik</h5>
                <p>Kelola tagihan dan billing pelanggan</p>
            </a>
            
            <a href="tarif.php" class="menu-card">
                <div class="menu-icon icon-tariff">
                    🧮
                </div>
                <h5>Tarif Listrik</h5>
                <p>Kelola tarif dan perhitungan biaya listrik</p>
            </a>
            
            <a href="pembayaran.php" class="menu-card">
                <div class="menu-icon icon-payment">
                    💳
                </div>
                <h5>Pembayaran</h5>
                <p>Monitor transaksi dan pembayaran</p>
            </a>
            
            <a href="user-level.php" class="menu-card">
                <div class="menu-icon icon-users">
                    ⚙️
                </div>
                <h5>User & Level</h5>
                <p>Kelola pengguna dan level akses sistem</p>
            </a>
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
    </script>
</body>
</html>