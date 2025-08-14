<?php
$hashed_password = '';
$original_password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $original_password = $_POST['new_password'];
    if (!empty($original_password)) {
        $hashed_password = password_hash($original_password, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generator Password Hash</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f0f2f5;
        }

        .hash-card {
            width: 100%;
            max-width: 600px;
        }

        .hash-result {
            background-color: #e9ecef;
            padding: .75rem 1rem;
            border-radius: .375rem;
            word-wrap: break-word;
            
            font-family: var(--bs-font-monospace);
        }
    </style>
</head>

<body>
    <div class="card hash-card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock-fill text-primary" style="font-size: 3rem;"></i>
                <h3 class="fw-bold mt-2">Password Hash Generator</h3>
                <p class="text-muted">Gunakan halaman ini untuk membuat hash password yang aman untuk database.</p>
            </div>

            <form action="buat_password_hash.php" method="POST">
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="new_password" name="new_password" placeholder="Ketik password baru" required autofocus>
                    <label for="new_password"><i class="bi bi-key me-2"></i>Ketik Password Baru</label>
                </div>
                <div class="d-grid">
                    <button class="btn btn-primary btn-lg" type="submit">
                        <i class="bi bi-gear-fill me-2"></i>Buat Hash
                    </button>
                </div>
            </form>

            <?php if ($hashed_password): ?>
                <hr class="my-4">
                <div class="result-container">
                    <h5 class="fw-bold">Hasil:</h5>
                    <p>
                        Password Asli: <strong><?= htmlspecialchars($original_password) ?></strong>
                    </p>
                    <p class="mb-2">Password Hash (salin teks di bawah ini ke kolom `password` di database):</p>
                    <div class="hash-result">
                        <code><?= htmlspecialchars($hashed_password) ?></code>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary mt-2" onclick="copyToClipboard()">
                        <i class="bi bi-clipboard"></i> Salin Hash
                    </button>
                </div>
            <?php endif; ?>
            <div class="text-center mt-4">
                <a href="/beranda"><i class="bi bi-arrow-left-circle"></i> Kembali ke Aplikasi</a>
            </div>
        </div>
    </div>

    <script>
        function copyToClipboard() {
            const hashText = document.querySelector('.hash-result code').innerText;
            navigator.clipboard.writeText(hashText).then(function() {
                alert('Password hash berhasil disalin ke clipboard!');
            }, function(err) {
                alert('Gagal menyalin hash. Coba salin secara manual.');
            });
        }
    </script>
</body>

</html>