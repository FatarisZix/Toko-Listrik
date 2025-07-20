<?php
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectByRole();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLN Payment System</title>
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
        }
        
        .container {
            width: 90%;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .main-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            padding: 50px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        
        .bolt-icon {
            font-size: 4rem;
            color: #feca57;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .brand-title {
            background: linear-gradient(135deg, #ff6b6b, #feca57);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .description {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 40px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            min-width: 150px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-register {
            background: linear-gradient(135deg, #ff6b6b, #feca57);
            color: white;
        }
        
        .btn-register:hover {
            background: linear-gradient(135deg, #feca57, #ff6b6b);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255, 107, 107, 0.4);
            color: white;
        }
        
        @media (max-width: 768px) {
            .main-card {
                padding: 30px;
            }
            
            .brand-title {
                font-size: 2.5rem;
            }
            
            .button-group {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-card">
            <div class="bolt-icon">⚡</div>
            <h1 class="brand-title">PLN Payment</h1>
            <p class="description">
                Sistem Pembayaran Listrik Online<br>
                Mudah, Cepat & Aman
            </p>
            
            <div class="button-group">
                <a href="login.php" class="btn btn-login">
                    🔑 Login
                </a>
                <a href="register.php" class="btn btn-register">
                    👤 Daftar
                </a>
            </div>
        </div>
    </div>
</body>
</html>