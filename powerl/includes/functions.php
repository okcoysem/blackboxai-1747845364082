<?php
session_start();

// Database connection function
function getConnection() {
    require_once __DIR__ . '/../config/database.php';
    return $conn;
}

// Security functions
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateOTP() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendOTP($phone, $otp) {
    // TODO: Implement actual WhatsApp/SMS integration
    // For development, just return true
    return true;
}

// Authentication functions
function createUser($name, $kk_number, $phone, $address, $role = 'user') {
    $conn = getConnection();
    $name = $conn->real_escape_string($name);
    $kk_number = $conn->real_escape_string($kk_number);
    $phone = $conn->real_escape_string($phone);
    $address = $conn->real_escape_string($address);
    $role = $conn->real_escape_string($role);

    $sql = "INSERT INTO users (name, kk_number, phone, address, role) 
            VALUES ('$name', '$kk_number', '$phone', '$address', '$role')";
    
    return $conn->query($sql);
}

function verifyOTP($kk_number, $otp) {
    $conn = getConnection();
    $kk_number = $conn->real_escape_string($kk_number);
    $otp = $conn->real_escape_string($otp);

    $sql = "SELECT u.*, o.code FROM users u 
            JOIN otp_codes o ON u.id = o.user_id 
            WHERE u.kk_number = '$kk_number' 
            AND o.code = '$otp' 
            AND o.expires_at > NOW()
            ORDER BY o.created_at DESC LIMIT 1";

    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return false;
}

// Transaction functions
function recordTransaction($type, $amount, $description, $responsible_person) {
    $conn = getConnection();
    $type = $conn->real_escape_string($type);
    $amount = floatval($amount);
    $description = $conn->real_escape_string($description);
    $responsible_person = $conn->real_escape_string($responsible_person);

    $sql = "INSERT INTO transactions (type, amount, description, responsible_person) 
            VALUES ('$type', $amount, '$description', '$responsible_person')";
    
    return $conn->query($sql);
}

function recordPayment($user_id, $mandatory_fee_id, $amount) {
    $conn = getConnection();
    $user_id = (int)$user_id;
    $mandatory_fee_id = (int)$mandatory_fee_id;
    $amount = floatval($amount);

    $sql = "INSERT INTO payments (user_id, mandatory_fee_id, amount, payment_date, status) 
            VALUES ($user_id, $mandatory_fee_id, $amount, CURDATE(), 'paid')";
    
    return $conn->query($sql);
}

// Report functions
function getUserBalance($user_id) {
    $conn = getConnection();
    $user_id = (int)$user_id;

    $sql = "SELECT 
                mf.id,
                mf.name,
                mf.amount as required_amount,
                COALESCE(SUM(p.amount), 0) as paid_amount,
                (mf.amount - COALESCE(SUM(p.amount), 0)) as balance
            FROM mandatory_fees mf
            LEFT JOIN payments p ON mf.id = p.mandatory_fee_id AND p.user_id = $user_id
            GROUP BY mf.id";

    $result = $conn->query($sql);
    if ($result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

function getTransactionsByPeriod($start_date, $end_date) {
    $conn = getConnection();
    $start_date = $conn->real_escape_string($start_date);
    $end_date = $conn->real_escape_string($end_date);

    $sql = "SELECT * FROM transactions 
            WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'
            ORDER BY created_at DESC";

    $result = $conn->query($sql);
    if ($result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    return [];
}

// Utility functions
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function isAdmin() {
    return isset($_SESSION['user_role']) && 
           ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'superadmin');
}

function isSuperAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'superadmin';
}

// WhatsApp integration function
function sendWhatsAppMessage($phone, $message) {
    // TODO: Implement actual WhatsApp API integration
    // For development, just return true
    return true;
}

// Check user balance via WhatsApp/SMS
function checkBalanceByKK($kk_number, $phone) {
    $conn = getConnection();
    $kk_number = $conn->real_escape_string($kk_number);
    $phone = $conn->real_escape_string($phone);

    $sql = "SELECT id FROM users WHERE kk_number = '$kk_number' AND phone = '$phone'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $balance = getUserBalance($user['id']);
        
        $message = "Detail tagihan untuk KK: $kk_number\n\n";
        $total = 0;
        
        foreach ($balance as $item) {
            $message .= "{$item['name']}: " . formatCurrency($item['balance']) . "\n";
            $total += $item['balance'];
        }
        
        $message .= "\nTotal: " . formatCurrency($total);
        return $message;
    }
    
    return false;
}
