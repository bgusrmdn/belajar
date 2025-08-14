<?php
session_start();
include 'koneksi.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$login_status = ''; // 'success', 'failed', 'empty'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik = $_POST['nik'];
    $plant = $_POST['plant'];
    $password = $_POST['password'];

    if (empty($nik) || empty($plant) || empty($password)) {
        $login_status = 'empty';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE nik = ? AND plant = ?");
            $stmt->execute([$nik, $plant]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nik'] = $user['nik'];
                $_SESSION['user_nama'] = $user['nama_lengkap'];
                $_SESSION['user_role'] = $user['role'];

                $login_status = 'success';
            } else {
                $login_status = 'failed';
            }
        } catch (PDOException $e) {
            $login_status = 'db_error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Manajemen Stok</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f0f2f5;
        }

        .login-card {
            max-width: 400px;
            width: 100%;
        }
    </style>
</head>

<body>
    <div class="card login-card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <img src="images/mayora.jpg" alt="Logo Perusahaan" style="width: 135px; height: auto;">
                <h3 class="fw-bold mt-2">Mayora Portal</h3>
                <p class="text-muted">Silakan login untuk melanjutkan</p>
            </div>

            <form action="login.php" method="POST" id="loginForm">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="nik" name="nik" placeholder="NIK" required>
                    <label for="nik"><i class="bi bi-person-badge me-2"></i>NIK</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="plant" name="plant" placeholder="PLANT" required>
                    <label for="plant"><i class="bi bi-buildings me-2"></i>PLANT</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password"><i class="bi bi-shield-lock me-2"></i>Password</label>
                </div>
                <div class="d-grid">
                    <button class="btn btn-primary btn-lg" type="submit">Login</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>

    <?php if ($login_status): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                <?php if ($login_status === 'success'): ?>
                    Swal.fire({
                        title: 'Login Berhasil!',
                        text: 'Selamat datang kembali, <?= htmlspecialchars($_SESSION['user_nama']) ?>!',
                        icon: 'success',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading()
                        }
                    }).then(() => {
                        window.location.href = 'index.php?page=beranda';
                    });
                <?php elseif ($login_status === 'failed'): ?>
                    Swal.fire({
                        title: 'Login Gagal!',
                        text: 'NIK, Plant, atau Password yang Anda masukkan salah.',
                        icon: 'error',
                        confirmButtonColor: '#d33',
                        confirmButtonText: 'Coba Lagi'
                    });
                <?php elseif ($login_status === 'empty'): ?>
                    Swal.fire({
                        title: 'Input Tidak Lengkap!',
                        text: 'Mohon isi semua kolom yang tersedia.',
                        icon: 'warning',
                        confirmButtonColor: '#ffc107',
                        confirmButtonText: 'Baik'
                    });
                <?php endif; ?>
            });
        </script>
    <?php endif; ?>

</body>

</html>