<?php
require_once '../includes/auth.php';
requireAdmin();

$conn = getConnection();

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Get transactions based on filters
$where_clause = "WHERE DATE(created_at) BETWEEN '$start_date' AND '$end_date'";
if ($type !== 'all') {
    $where_clause .= " AND type = '$type'";
}

$transactions = $conn->query("
    SELECT * FROM transactions 
    $where_clause
    ORDER BY created_at DESC
");

// Calculate summaries
$summaries = [
    'income' => 0,
    'expense' => 0,
    'balance' => 0
];

if ($transactions) {
    while ($row = $transactions->fetch_assoc()) {
        if ($row['type'] === 'income') {
            $summaries['income'] += $row['amount'];
        } else {
            $summaries['expense'] += $row['amount'];
        }
    }
    $summaries['balance'] = $summaries['income'] - $summaries['expense'];
    // Reset pointer for table display
    $transactions->data_seek(0);
}

// Get payment status for all users
$payment_status = $conn->query("
    SELECT 
        u.name,
        u.kk_number,
        mf.name as fee_name,
        mf.amount as required_amount,
        COALESCE(SUM(p.amount), 0) as paid_amount,
        (mf.amount - COALESCE(SUM(p.amount), 0)) as remaining
    FROM users u
    CROSS JOIN mandatory_fees mf
    LEFT JOIN payments p ON u.id = p.user_id AND mf.id = p.mandatory_fee_id
    WHERE u.role = 'user'
    GROUP BY u.id, mf.id
    ORDER BY u.name, mf.name
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - PowerL</title>
    <link rel="stylesheet" href="../assets/css/retro.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            .retro-card {
                border: 1px solid #000;
                box-shadow: none;
                margin: 10px 0;
                padding: 10px;
            }
            body {
                background: white;
                color: black;
            }
            .table th {
                background: #eee !important;
                color: black !important;
            }
        }
        .print-only {
            display: none;
        }
    </style>
</head>
<body>
    <nav class="nav no-print">
        <ul class="nav-list">
            <li><a href="../public/index.php" class="nav-link">Dashboard</a></li>
            <li><a href="users.php" class="nav-link">Kelola Warga</a></li>
            <li><a href="transactions.php" class="nav-link">Transaksi</a></li>
            <li><a href="reports.php" class="nav-link">Laporan</a></li>
            <li><a href="../public/logout.php" class="nav-link">Keluar</a></li>
        </ul>
    </nav>

    <div class="container">
        <!-- Print Header -->
        <div class="print-only">
            <h1 class="text-center">Laporan Keuangan RT</h1>
            <p class="text-center">Periode: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
            <hr>
        </div>

        <!-- Filters -->
        <div class="retro-card no-print">
            <h2>Filter Laporan</h2>
            <form method="GET" action="" class="d-flex justify-between">
                <div class="form-group" style="flex: 1; margin-right: 10px;">
                    <label for="start_date">Tanggal Mulai:</label>
                    <input type="date" id="start_date" name="start_date" 
                           value="<?php echo $start_date; ?>" required>
                </div>

                <div class="form-group" style="flex: 1; margin: 0 10px;">
                    <label for="end_date">Tanggal Selesai:</label>
                    <input type="date" id="end_date" name="end_date" 
                           value="<?php echo $end_date; ?>" required>
                </div>

                <div class="form-group" style="flex: 1; margin-left: 10px;">
                    <label for="type">Jenis Transaksi:</label>
                    <select id="type" name="type">
                        <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>Semua</option>
                        <option value="income" <?php echo $type === 'income' ? 'selected' : ''; ?>>Pemasukan</option>
                        <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>Pengeluaran</option>
                    </select>
                </div>

                <div class="form-group" style="flex: 0.5; margin-left: 10px;">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn w-100">Filter</button>
                </div>
            </form>
        </div>

        <!-- Summary -->
        <div class="retro-card">
            <h2>Ringkasan Keuangan</h2>
            <div class="d-flex justify-between">
                <div style="flex: 1; text-align: center;">
                    <h3>Total Pemasukan</h3>
                    <p class="text-success"><?php echo formatCurrency($summaries['income']); ?></p>
                </div>
                <div style="flex: 1; text-align: center;">
                    <h3>Total Pengeluaran</h3>
                    <p class="text-error"><?php echo formatCurrency($summaries['expense']); ?></p>
                </div>
                <div style="flex: 1; text-align: center;">
                    <h3>Saldo</h3>
                    <p class="<?php echo $summaries['balance'] >= 0 ? 'text-success' : 'text-error'; ?>">
                        <?php echo formatCurrency($summaries['balance']); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Transactions -->
        <div class="retro-card">
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
                        <?php if ($transactions && $transactions->num_rows > 0): ?>
                            <?php while ($transaction = $transactions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></td>
                                    <td><?php echo $transaction['type'] === 'income' ? 'Masuk' : 'Keluar'; ?></td>
                                    <td><?php echo formatCurrency($transaction['amount']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['responsible_person']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">Tidak ada transaksi</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment Status -->
        <div class="retro-card">
            <h2>Status Pembayaran Warga</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Nomor KK</th>
                            <th>Jenis Iuran</th>
                            <th>Total Tagihan</th>
                            <th>Sudah Dibayar</th>
                            <th>Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($payment_status && $payment_status->num_rows > 0): ?>
                            <?php while ($payment = $payment_status->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['name']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['kk_number']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['fee_name']); ?></td>
                                    <td><?php echo formatCurrency($payment['required_amount']); ?></td>
                                    <td><?php echo formatCurrency($payment['paid_amount']); ?></td>
                                    <td class="<?php echo $payment['remaining'] > 0 ? 'text-error' : 'text-success'; ?>">
                                        <?php echo formatCurrency($payment['remaining']); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Tidak ada data pembayaran</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Print Button -->
        <div class="mt-1 no-print">
            <button onclick="window.print()" class="btn">Cetak Laporan</button>
        </div>
    </div>
</body>
</html>
