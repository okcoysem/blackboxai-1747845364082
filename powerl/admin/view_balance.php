<?php
require_once '../includes/auth.php';
requireAdmin();

if (!isset($_GET['user_id'])) {
    header('Location: users.php');
    exit();
}

$conn = getConnection();
$user_id = (int)$_GET['user_id'];
$message = '';
$error = '';

// Get user details
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = $conn->query($sql);
if (!$result || $result->num_rows === 0) {
    header('Location: users.php');
    exit();
}
$user = $result->fetch_assoc();

// Get mandatory fees and payment status
$sql = "SELECT 
            mf.*,
            COALESCE(SUM(p.amount), 0) as paid_amount,
            (mf.amount - COALESCE(SUM(p.amount), 0)) as remaining
        FROM mandatory_fees mf
        LEFT JOIN payments p ON mf.id = p.mandatory_fee_id AND p.user_id = $user_id
        GROUP BY mf.id
        ORDER BY mf.name";
$fees = $conn->query($sql);

// Get payment history
$sql = "SELECT 
            p.*,
            mf.name as fee_name
        FROM payments p
        JOIN mandatory_fees mf ON p.mandatory_fee_id = mf.id
        WHERE p.user_id = $user_id
        ORDER BY p.payment_date DESC";
$payments = $conn->query($sql);

// Handle new payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_payment') {
    $mandatory_fee_id = (int)$_POST['mandatory_fee_id'];
    $amount = floatval($_POST['amount']);
    
    if (recordPayment($user_id, $mandatory_fee_id, $amount)) {
        // Record as income transaction
        $sql = "SELECT name FROM mandatory_fees WHERE id = $mandatory_fee_id";
        $fee_name = $conn->query($sql)->fetch_assoc()['name'];
        
        $description = "Pembayaran {$fee_name} dari {$user['name']}";
        recordTransaction('income', $amount, $description, $_SESSION['user_name']);
        
        $message = "Pembayaran berhasil dicatat";
        
        // Refresh the page to show updated data
        header("Location: view_balance.php?user_id=$user_id&success=1");
        exit();
    } else {
        $error = "Gagal mencatat pembayaran";
    }
}

// Show success message from redirect
if (isset($_GET['success'])) {
    $message = "Pembayaran berhasil dicatat";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Tagihan - PowerL</title>
    <link rel="stylesheet" href="../assets/css/retro.css">
</head>
<body>
    <nav class="nav">
        <ul class="nav-list">
            <li><a href="../public/index.php" class="nav-link">Dashboard</a></li>
            <li><a href="users.php" class="nav-link">Kelola Warga</a></li>
            <li><a href="transactions.php" class="nav-link">Transaksi</a></li>
            <li><a href="reports.php" class="nav-link">Laporan</a></li>
            <li><a href="../public/logout.php" class="nav-link">Keluar</a></li>
        </ul>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="retro-card">
            <h1>Detail Tagihan Warga</h1>
            <p>Nama: <?php echo htmlspecialchars($user['name']); ?></p>
            <p>Nomor KK: <?php echo htmlspecialchars($user['kk_number']); ?></p>
            <p>Alamat: <?php echo htmlspecialchars($user['address']); ?></p>
        </div>

        <!-- Current Balance -->
        <div class="retro-card">
            <h2>Status Tagihan</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Jenis Iuran</th>
                            <th>Total Tagihan</th>
                            <th>Sudah Dibayar</th>
                            <th>Sisa</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($fee = $fees->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fee['name']); ?></td>
                                <td><?php echo formatCurrency($fee['amount']); ?></td>
                                <td><?php echo formatCurrency($fee['paid_amount']); ?></td>
                                <td class="<?php echo $fee['remaining'] > 0 ? 'text-error' : 'text-success'; ?>">
                                    <?php echo formatCurrency($fee['remaining']); ?>
                                </td>
                                <td>
                                    <?php if ($fee['remaining'] > 0): ?>
                                        <button onclick="showPaymentForm(<?php 
                                            echo $fee['id']; ?>, '<?php 
                                            echo htmlspecialchars($fee['name']); ?>', <?php 
                                            echo $fee['remaining']; 
                                        ?>)" class="btn">Bayar</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment Form (Hidden by default) -->
        <div id="paymentForm" class="retro-card" style="display: none;">
            <h2>Catat Pembayaran</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_payment">
                <input type="hidden" name="mandatory_fee_id" id="mandatory_fee_id">
                
                <div class="form-group">
                    <label for="fee_name">Jenis Iuran:</label>
                    <input type="text" id="fee_name" readonly>
                </div>

                <div class="form-group">
                    <label for="amount">Jumlah Bayar:</label>
                    <input type="number" id="amount" name="amount" required min="0" step="1000">
                </div>

                <button type="submit" class="btn">Simpan Pembayaran</button>
                <button type="button" class="btn" onclick="hidePaymentForm()">Batal</button>
            </form>
        </div>

        <!-- Payment History -->
        <div class="retro-card">
            <h2>Riwayat Pembayaran</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis Iuran</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($payments && $payments->num_rows > 0): ?>
                            <?php while ($payment = $payments->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['fee_name']); ?></td>
                                    <td><?php echo formatCurrency($payment['amount']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center">Belum ada riwayat pembayaran</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-1">
            <a href="users.php" class="btn">&laquo; Kembali ke Daftar Warga</a>
        </div>
    </div>

    <script>
        function showPaymentForm(feeId, feeName, remaining) {
            document.getElementById('paymentForm').style.display = 'block';
            document.getElementById('mandatory_fee_id').value = feeId;
            document.getElementById('fee_name').value = feeName;
            document.getElementById('amount').max = remaining;
            document.getElementById('amount').value = remaining;
        }

        function hidePaymentForm() {
            document.getElementById('paymentForm').style.display = 'none';
        }
    </script>
</body>
</html>
