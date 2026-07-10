<?php
require_once 'config.php';
startSecureSession();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($phone === '' || !preg_match('/^\+?[0-9]{8,20}$/', $phone)) {
        $errors[] = 'Un numéro de téléphone valide est requis.';
    }
    if ($password === '') {
        $errors[] = 'Le mot de passe est obligatoire.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE phone = :phone OR email = :email');
        $stmt->execute([':phone' => $phone, ':email' => $phone]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Numéro de téléphone ou mot de passe incorrect.';
        } else {
            session_regenerate_id(true);
            $doctorProfileId = ensureDoctorProfile($pdo, $user);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['doctor_profile_id'] = $doctorProfileId;
            header('Location: ' . (($user['role'] ?? '') === 'admin' ? 'admin.php' : 'dashboard.php'));
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - RV Santé</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header shadow-sm">
    <nav class="navbar navbar-expand-lg container py-3">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <span class="brand-icon d-flex align-items-center justify-content-center me-2">❤</span>
            <div>
                <div class="brand-name">RV Santé</div>
            </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-4">
                <li class="nav-item"><a class="nav-link" href="index.php">Accueil</a></li>
                <li class="nav-item"><a class="nav-link btn btn-primary px-3 py-2 text-white" href="register.php">Inscription</a></li>
            </ul>
        </div>
    </nav>
</header>
<main class="auth-page py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="auth-card p-4 p-md-5 shadow-sm rounded-4">
                    <div class="mb-4 text-center">
                        <h1 class="h3 fw-bold">Connexion</h1>
                        <p class="text-secondary">Accédez à votre espace privé RV Santé.</p>
                    </div>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="login.php">
                        <div class="mb-3">
                            <label for="phone" class="form-label">Numéro de téléphone</label>
                            <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="+221770000000">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" id="password" name="password" class="form-control">
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Se connecter</button>
                        </div>
                    </form>
                    <div class="mt-4 text-center text-secondary">
                        Pas encore de compte ? <a href="register.php">Inscrivez-vous</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
