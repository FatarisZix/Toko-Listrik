<?php
// Include database connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'pembayaran_listrik';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function untuk login
function login($username, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        return true;
    }
    return false;
}

// Function untuk register
function register($username, $email, $password, $full_name) {
    global $pdo;
    
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        return false; // User already exists
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$username, $email, $hashed_password, $full_name]);
}

// Function untuk check login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function untuk check admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function untuk logout
function logout() {
    session_destroy();
    header('Location: /Toko-Listrik/login.php');
    exit();
}

// Function untuk redirect berdasarkan role
function redirectByRole() {
    if (isAdmin()) {
        header('Location: /Toko-Listrik/admin/dashboard.php');
    } else {
        header('Location: /Toko-Listrik/user/dashboard.php');
    }
    exit();
}

// Function untuk sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Function untuk create manual bill by admin
function createManualBill($customer_id, $period_month, $kwh_usage, $admin_note = '') {
    global $pdo;
    
    try {
        // Get customer and tariff info
        $stmt = $pdo->prepare("
            SELECT c.*, t.price_per_kwh, t.admin_fee 
            FROM customers c 
            LEFT JOIN tariffs t ON c.tariff_type = t.tariff_name 
            WHERE c.id = ?
        ");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        if (!$customer || !$customer['price_per_kwh']) {
            throw new Exception("Data pelanggan atau tarif tidak ditemukan!");
        }
        
        // Check if bill already exists
        $stmt = $pdo->prepare("SELECT id FROM bills WHERE customer_id = ? AND bill_month = ?");
        $stmt->execute([$customer_id, $period_month]);
        if ($stmt->fetch()) {
            throw new Exception("Tagihan untuk periode ini sudah ada!");
        }
        
        // Calculate amounts
        $kwh_amount = $kwh_usage * $customer['price_per_kwh'];
        $admin_fee = $customer['admin_fee'];
        $total_amount = $kwh_amount + $admin_fee;
        
        // Set due date (30 days from now)
        $due_date = date('Y-m-d', strtotime('+30 days'));
        
        // Insert bill
        $stmt = $pdo->prepare("
            INSERT INTO bills (customer_id, bill_month, kwh_usage, amount, admin_fee, total_amount, due_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'unpaid')
        ");
        $stmt->execute([
            $customer_id, 
            $period_month, 
            $kwh_usage, 
            $kwh_amount, 
            $admin_fee, 
            $total_amount, 
            $due_date
        ]);
        
        return [
            'success' => true,
            'bill_id' => $pdo->lastInsertId(),
            'total_amount' => $total_amount
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>