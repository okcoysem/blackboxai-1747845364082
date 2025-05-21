<?php
require_once '../includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Handle OTP request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kk_number']) && !isset($_POST['otp'])) {
    $result = initiateLogin($_POST['kk_number']);
    if ($result['success']) {
        $success = $result['message'];
        $_SESSION['temp_kk'] = $_POST['kk_number']; // Store KK number temporarily
    } else {
        $error = $result['message'];
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp']) && isset($_SESSION['temp_kk'])) {
    $result = completeLogin($_SESSION['temp_kk'], $_POST['otp']);
    if ($result['success']) {
        unset($_SESSION['temp_kk']);
        header('Location: index.php');
        exit();
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PowerL</title>
    <link rel="stylesheet" href="../assets/css/retro.css">
</head>
<body>
    <div class="container">
        <div class="retro-card" style="max-width: 400px; margin: 50px auto;">
            <h1 class="text-center mb-1">PowerL</h1>
            <h3 class="text-center mb-1">Sistem Keuangan RT</h3>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (!isset($_SESSION['temp_kk'])): ?>
                <!-- KK Number Form -->
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="kk_number">Nomor Kartu Keluarga:</label>
                        <input type="text" 
                               id="kk_number" 
                               name="kk_number" 
                               required 
                               pattern="[0-9]+" 
                               minlength="16" 
                               maxlength="16"
                               placeholder="Masukkan nomor KK">
                    </div>
                    <button type="submit" class="btn w-100">Kirim OTP</button>
                </form>
            <?php else: ?>
                <!-- OTP Verification Form -->
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="otp">Kode OTP:</label>
                        <input type="text" 
                               id="otp" 
                               name="otp" 
                               required 
                               pattern="[0-9]+" 
                               minlength="6" 
                               maxlength="6"
                               placeholder="Masukkan kode OTP">
                    </div>
                    <button type="submit" class="btn w-100">Verifikasi OTP</button>
                </form>
                <div class="text-center mt-1">
                    <a href="login.php" class="nav-link">Kembali ke Login</a>
                </div>
            <?php endif; ?>

            <div class="text-center mt-1">
                <p>Cek tagihan via WhatsApp?</p>
                <a href="check-balance.php" class="nav-link">Klik di sini</a>
            </div>
        </div>
    </div>

    <script>
        // Add input validation
        document.querySelectorAll('input[pattern]').forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > this.maxLength) {
                    this.value = this.value.slice(0, this.maxLength);
                }
            });
        });
    </script>
</body>
</html>
