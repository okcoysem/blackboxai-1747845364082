<?php
require_once 'functions.php';

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /powerl/public/login.php');
        exit();
    }
}

// Redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /powerl/public/index.php');
        exit();
    }
}

// Redirect if not superadmin
function requireSuperAdmin() {
    requireLogin();
    if (!isSuperAdmin()) {
        header('Location: /powerl/public/index.php');
        exit();
    }
}

// Handle OTP generation and storage
function generateAndStoreOTP($user_id) {
    $conn = getConnection();
    $otp = generateOTP();
    
    // Delete any existing OTP for this user
    $sql = "DELETE FROM otp_codes WHERE user_id = $user_id";
    $conn->query($sql);
    
    // Store new OTP
    $sql = "INSERT INTO otp_codes (user_id, code, expires_at) 
            VALUES ($user_id, '$otp', DATE_ADD(NOW(), INTERVAL 5 MINUTE))";
    
    if ($conn->query($sql)) {
        return $otp;
    }
    return false;
}

// Initialize login process
function initiateLogin($kk_number) {
    $conn = getConnection();
    $kk_number = $conn->real_escape_string($kk_number);
    
    $sql = "SELECT id, phone FROM users WHERE kk_number = '$kk_number'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $otp = generateAndStoreOTP($user['id']);
        
        if ($otp && sendOTP($user['phone'], $otp)) {
            return [
                'success' => true,
                'message' => 'OTP has been sent to your registered phone number'
            ];
        }
    }
    
    return [
        'success' => false,
        'message' => 'Invalid KK number or failed to send OTP'
    ];
}

// Complete login process
function completeLogin($kk_number, $otp) {
    $user = verifyOTP($kk_number, $otp);
    
    if ($user) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['kk_number'] = $user['kk_number'];
        
        // Delete used OTP
        $conn = getConnection();
        $sql = "DELETE FROM otp_codes WHERE user_id = {$user['id']} AND code = '$otp'";
        $conn->query($sql);
        
        return [
            'success' => true,
            'message' => 'Login successful'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Invalid OTP or OTP expired'
    ];
}

// Logout function
function logout() {
    session_unset();
    session_destroy();
    header('Location: /powerl/public/login.php');
    exit();
}
