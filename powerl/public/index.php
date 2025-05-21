<?php
require_once '../includes/auth.php';
requireLogin();

$conn = getConnection();

// Get user's data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

// Get financial summaries
$totalIncome = 0;
$totalExpense = 0;
$sql = "SELECT type, SUM(amount) as total FROM transactions GROUP BY type";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    if ($row['type'] === 'income') {
        $totalIncome = $row['total'];
    } else {
        $totalExpense = $row['total'];
    }
}

// Get recent transactions
$sql = "SELECT * FROM transactions ORDER BY created_at DESC LIMIT 5";
$recentTransactions = $conn->query($sql);

// Get user's balance if regular user
$userBalance = [];
if (!isAdmin()) {
    $userBalance = getUserBalance($user_id);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PowerL</title>
    <link rel="stylesheet" href="../assets/css/retro.css">
</head>
<body>
    <nav class="nav">
        <ul class="nav-list">
            <li><a href="index.php" class="nav-link">Dashboard</a></li>
            <?php if (isAdmin()): ?>
                <li><a href="admin/users.php" class="nav-link">Kelola Warga</a></li>
                <li><a href="admin/transactions.php" class="nav-link">Transaksi</a></li>
                <li><a href="admin/reports.php" class="nav-link">Laporan</a></li>
            <?php endif; ?>
            <li><a href="logout.php" class="nav-link">Keluar</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="retro-card">
            <h1>Selamat Datang, <?php echo htmlspecialchars($user['name']); ?></h1>
            <p>Nomor KK: <?php echo htmlspecialchars($user['kk_number']); ?></p>
        </div>

        <?php if (isAdmin()): ?>
            <!-- Admin Dashboard -->
            <div class="d-flex justify-between">
                <div class="retro-card" style="flex: 1; margin-right: 10px;">
                    <h3>Total Pemasukan</h3>
                    <p class="text-success"><?php echo formatCurrency($totalIncome); ?></p>
                </div>
                <div class="retro-card" style="flex: 1; margin-left: 10px;">
                    <h3>Total Pengeluaran</h3>
                    <p class="text-error"><?php echo formatCurrency($totalExpense); ?></p>
                </div>
            </div>

            <div class="retro-card mt-1">
                <h3>Transaksi Terbaru</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Tipe</th>
                            <th>Jumlah</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($transaction = $recentTransactions->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?></td>
                                <td><?php echo $transaction['type'] === 'income' ? 'Masuk' : 'Keluar'; ?></td>
                                <td><?php echo formatCurrency($transaction['amount']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-between mt-1">
                <a href="admin/transactions.php?type=income" class="btn">Tambah Pemasukan</a>
                <a href="admin/transactions.php?type=expense" class="btn">Tambah Pengeluaran</a>
            </div>
        <?php else: ?>
            <!-- User Dashboard -->
            <div class="retro-card">
                <h3>Ringkasan Tagihan</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Jenis Iuran</th>
                            <th>Total Tagihan</th>
                            <th>Sudah Dibayar</th>
                            <th>Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userBalance as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo formatCurrency($item['required_amount']); ?></td>
                                <td><?php echo formatCurrency($item['paid_amount']); ?></td>
                                <td class="<?php echo $item['balance'] > 0 ? 'text-error' : 'text-success'; ?>">
                                    <?php echo formatCurrency($item['balance']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
