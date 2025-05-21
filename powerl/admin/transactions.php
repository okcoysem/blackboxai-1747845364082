<?php
require_once '../includes/auth.php';
requireAdmin();

$conn = getConnection();
$message = '';
$error = '';

// Handle transaction creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_transaction':
                $type = sanitize($_POST['type']);
                $amount = floatval($_POST['amount']);
                $description = sanitize($_POST['description']);
                $responsible_person = sanitize($_POST['responsible_person']);

                if (recordTransaction($type, $amount, $description, $responsible_person)) {
                    $message = "Transaksi berhasil dicatat";
                } else {
                    $error = "Gagal mencatat transaksi";
                }
                break;

            case 'add_mandatory_fee':
                $name = sanitize($_POST['name']);
                $amount = floatval($_POST['amount']);
                $period = sanitize($_POST['period']);

                $sql = "INSERT INTO mandatory_fees (name, amount, period) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sds", $name, $amount, $period);
                
                if ($stmt->execute()) {
                    $message = "Iuran wajib berhasil ditambahkan";
                } else {
                    $error = "Gagal menambahkan iuran wajib";
                }
                break;

            case 'record_payment':
                $user_id = (int)$_POST['user_id'];
                $mandatory_fee_id = (int)$_POST['mandatory_fee_id'];
                $amount = floatval($_POST['amount']);

                if (recordPayment($user_id, $mandatory_fee_id, $amount)) {
                    // Record as income transaction
                    $sql = "SELECT u.name, mf.name as fee_name FROM users u 
                           JOIN mandatory_fees mf ON mf.id = ? 
                           WHERE u.id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $mandatory_fee_id, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    
                    $description = "Pembayaran {$result['fee_name']} dari {$result['name']}";
                    recordTransaction('income', $amount, $description, $_SESSION['user_name']);
                    
                    $message = "Pembayaran berhasil dicatat";
                } else {
                    $error = "Gagal mencatat pembayaran";
                }
                break;
        }
    }
}

// Get all mandatory fees
$mandatory_fees = $conn->query("SELECT * FROM mandatory_fees ORDER BY name");

// Get all users for payment recording
$users = $conn->query("SELECT id, name, kk_number FROM users WHERE role = 'user' ORDER BY name");

// Get recent transactions with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$total_transactions = $conn->query("SELECT COUNT(*) as count FROM transactions")->fetch_assoc()['count'];
$total_pages = ceil($total_transactions / $limit);

$transactions = $conn->query("
    SELECT * FROM transactions 
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi - PowerL</title>
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

        <div class="d-flex justify-between">
            <!-- Record Transaction Form -->
            <div class="retro-card" style="flex: 1; margin-right: 10px;">
                <h2>Catat Transaksi</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_transaction">
                    
                    <div class="form-group">
                        <label for="type">Jenis Transaksi:</label>
                        <select id="type" name="type" required>
                            <option value="income">Pemasukan</option>
                            <option value="expense">Pengeluaran</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="amount">Jumlah:</label>
                        <input type="number" id="amount" name="amount" required min="0" step="1000">
                    </div>

                    <div class="form-group">
                        <label for="description">Keterangan:</label>
                        <textarea id="description" name="description" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="responsible_person">Penanggung Jawab:</label>
                        <input type="text" id="responsible_person" name="responsible_person" 
                               value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" required>
                    </div>

                    <button type="submit" class="btn w-100">Simpan Transaksi</button>
                </form>
            </div>

            <!-- Add Mandatory Fee Form -->
            <div class="retro-card" style="flex: 1; margin-left: 10px;">
                <h2>Tambah Iuran Wajib</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_mandatory_fee">
                    
                    <div class="form-group">
                        <label for="name">Nama Iuran:</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="amount">Jumlah:</label>
                        <input type="number" id="amount" name="amount" required min="0" step="1000">
                    </div>

                    <div class="form-group">
                        <label for="period">Periode:</label>
                        <select id="period" name="period" required>
                            <option value="monthly">Bulanan</option>
                            <option value="yearly">Tahunan</option>
                            <option value="once">Sekali Bayar</option>
                        </select>
                    </div>

                    <button type="submit" class="btn w-100">Simpan Iuran</button>
                </form>
            </div>
        </div>

        <!-- Record Payment Form -->
        <div class="retro-card mt-1">
            <h2>Catat Pembayaran</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="record_payment">
                
                <div class="d-flex justify-between">
                    <div class="form-group" style="flex: 1; margin-right: 10px;">
                        <label for="user_id">Warga:</label>
                        <select id="user_id" name="user_id" required>
                            <option value="">Pilih Warga</option>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['name']); ?> 
                                    (KK: <?php echo htmlspecialchars($user['kk_number']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 1; margin: 0 10px;">
                        <label for="mandatory_fee_id">Jenis Iuran:</label>
                        <select id="mandatory_fee_id" name="mandatory_fee_id" required>
                            <option value="">Pilih Iuran</option>
                            <?php while ($fee = $mandatory_fees->fetch_assoc()): ?>
                                <option value="<?php echo $fee['id']; ?>">
                                    <?php echo htmlspecialchars($fee['name']); ?> 
                                    (<?php echo formatCurrency($fee['amount']); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 1; margin-left: 10px;">
                        <label for="payment_amount">Jumlah Bayar:</label>
                        <input type="number" id="payment_amount" name="amount" required min="0" step="1000">
                    </div>
                </div>

                <button type="submit" class="btn w-100">Catat Pembayaran</button>
            </form>
        </div>

        <!-- Transactions Table -->
        <div class="retro-card mt-1">
            <h2>Riwayat Transaksi</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th>Jumlah</th>
                            <th>Keterangan</th>
                            <th>Penanggung Jawab</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($transaction = $transactions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></td>
                                <td><?php echo $transaction['type'] === 'income' ? 'Masuk' : 'Keluar'; ?></td>
                                <td><?php echo formatCurrency($transaction['amount']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['responsible_person']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-between mt-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?>" class="btn">&laquo; Sebelumnya</a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1); ?>" class="btn">Selanjutnya &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-fill payment amount when mandatory fee is selected
        document.getElementById('mandatory_fee_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const amountMatch = selectedOption.text.match(/Rp ([0-9.,]+)/);
            if (amountMatch) {
                const amount = parseInt(amountMatch[1].replace(/[.,]/g, ''));
                document.getElementById('payment_amount').value = amount;
            }
        });
    </script>
</body>
</html>
