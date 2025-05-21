<?php
require_once '../includes/auth.php';
requireAdmin();

$conn = getConnection();
$message = '';
$error = '';

// Handle user creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $name = sanitize($_POST['name']);
        $kk_number = sanitize($_POST['kk_number']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $role = sanitize($_POST['role']);

        // Only superadmin can create admin users
        if ($role === 'admin' && !isSuperAdmin()) {
            $error = "Anda tidak memiliki izin untuk membuat admin";
        } else {
            if ($_POST['action'] === 'create') {
                if (createUser($name, $kk_number, $phone, $address, $role)) {
                    $message = "Berhasil menambahkan warga baru";
                } else {
                    $error = "Gagal menambahkan warga. Nomor KK atau nomor telepon mungkin sudah terdaftar.";
                }
            }
        }
    }
}

// Get all users
$sql = "SELECT * FROM users ORDER BY created_at DESC";
$users = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Warga - PowerL</title>
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
        <div class="retro-card">
            <h1>Kelola Warga</h1>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <button class="btn mb-1" onclick="toggleForm()">Tambah Warga Baru</button>

            <!-- Add User Form -->
            <div id="addUserForm" style="display: none;">
                <form method="POST" action="" class="retro-card">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="name">Nama Lengkap:</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="kk_number">Nomor KK:</label>
                        <input type="text" 
                               id="kk_number" 
                               name="kk_number" 
                               required 
                               pattern="[0-9]+" 
                               minlength="16" 
                               maxlength="16">
                    </div>

                    <div class="form-group">
                        <label for="phone">Nomor Telepon:</label>
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               required 
                               pattern="[0-9]+" 
                               minlength="10" 
                               maxlength="13">
                    </div>

                    <div class="form-group">
                        <label for="address">Alamat:</label>
                        <textarea id="address" name="address" required></textarea>
                    </div>

                    <div class="form-group">
                        <label for="role">Peran:</label>
                        <select id="role" name="role" required>
                            <option value="user">Warga</option>
                            <?php if (isSuperAdmin()): ?>
                                <option value="admin">Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn">Simpan</button>
                    <button type="button" class="btn" onclick="toggleForm()">Batal</button>
                </form>
            </div>

            <!-- Users Table -->
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Nomor KK</th>
                            <th>Telepon</th>
                            <th>Alamat</th>
                            <th>Peran</th>
                            <th>Terdaftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['kk_number']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><?php echo htmlspecialchars($user['address']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <a href="view_balance.php?user_id=<?php echo $user['id']; ?>" 
                                       class="btn">Lihat Tagihan</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleForm() {
            const form = document.getElementById('addUserForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

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
