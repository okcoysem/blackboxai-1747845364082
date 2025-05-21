<?php
require_once '../includes/functions.php';

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kk_number = sanitize($_POST['kk_number']);
    $phone = sanitize($_POST['phone']);
    
    $balance_info = checkBalanceByKK($kk_number, $phone);
    
    if ($balance_info) {
        // Send balance info via WhatsApp
        if (sendWhatsAppMessage($phone, $balance_info)) {
            $message = "Informasi tagihan telah dikirim ke nomor WhatsApp Anda";
        } else {
            $error = "Gagal mengirim informasi via WhatsApp. Silakan coba lagi.";
        }
    } else {
        $error = "Nomor KK atau nomor telepon tidak valid";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Tagihan - PowerL</title>
    <link rel="stylesheet" href="../assets/css/retro.css">
</head>
<body>
    <div class="container">
        <div class="retro-card" style="max-width: 400px; margin: 50px auto;">
            <h1 class="text-center mb-1">Cek Tagihan</h1>
            <h3 class="text-center mb-1">via WhatsApp</h3>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

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

                <div class="form-group">
                    <label for="phone">Nomor WhatsApp:</label>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           required 
                           pattern="[0-9]+" 
                           minlength="10" 
                           maxlength="13"
                           placeholder="Contoh: 08123456789">
                </div>

                <button type="submit" class="btn w-100">Cek Tagihan</button>
            </form>

            <div class="text-center mt-1">
                <a href="login.php" class="nav-link">Kembali ke Login</a>
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
