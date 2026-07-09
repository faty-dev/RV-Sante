<?php
require_once 'config.php';
startSecureSession();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'patient';
    $specialty = trim($_POST['specialty'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $hospital = trim($_POST['hospital'] ?? '');
    $experience = max(1, (int)($_POST['experience'] ?? 5));
    $fee = max(10000, (int)($_POST['fee'] ?? 25000));

    if ($name === '') {
        $errors[] = 'Le nom complet est obligatoire.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Une adresse email valide est requise.';
    }
    if ($phone === '') {
        $errors[] = 'Le numéro de téléphone est obligatoire.';
    }
    if (!preg_match('/^\+?[0-9]{8,20}$/', $phone)) {
        $errors[] = 'Le numéro de téléphone doit être valide et contenir uniquement des chiffres.';
    }
    if ($password === '') {
        $errors[] = 'Le mot de passe est obligatoire.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }
    if ($role === 'doctor') {
        if ($specialty === '') {
            $errors[] = 'La spécialité est obligatoire pour un médecin.';
        }
        if ($city === '') {
            $errors[] = 'La ville est obligatoire pour un médecin.';
        }
        if ($hospital === '') {
            $errors[] = 'L’hôpital ou le poste de santé est obligatoire pour un médecin.';
        }
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email OR phone = :phone');
    $stmt->execute([':email' => $email, ':phone' => $phone]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Cette adresse email ou ce numéro de téléphone est déjà utilisé.';
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $doctorProfileId = null;

        $pdo->beginTransaction();
        try {
            if ($role === 'doctor') {
                $doctorSummary = 'Médecin inscrit sur RV Santé, prêt à accompagner ses patients avec professionnalisme.';
                $photoUrl = 'https://images.unsplash.com/photo-1550831107-1553da8c8464?auto=format&fit=crop&w=900&q=80';
                $photoPath = null;

                if (!empty($_FILES['doctor_photo']['name'])) {
                    $uploadDir = __DIR__ . '/img/doctors/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileExtension = strtolower(pathinfo($_FILES['doctor_photo']['name'], PATHINFO_EXTENSION));
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                    if (!in_array($fileExtension, $allowedExtensions, true)) {
                        throw new RuntimeException('Format d’image non pris en charge.');
                    }

                    if ($_FILES['doctor_photo']['error'] !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('Erreur lors de l’upload de l’image.');
                    }

                    $safeName = 'doctor_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
                    $targetPath = $uploadDir . $safeName;
                    if (!move_uploaded_file($_FILES['doctor_photo']['tmp_name'], $targetPath)) {
                        throw new RuntimeException('Impossible d’enregistrer l’image.');
                    }
                    $photoPath = 'img/doctors/' . $safeName;
                    $photoUrl = $photoPath;
                }

                $availability = 'Disponible';
                $rating = 4.5;
                $insertDoctor = $pdo->prepare('INSERT INTO doctors (name, specialty, city, hospital, experience, availability, rating, fee, photo_url, photo_path, summary) VALUES (:name, :specialty, :city, :hospital, :experience, :availability, :rating, :fee, :photo_url, :photo_path, :summary)');
                $insertDoctor->execute([
                    ':name' => $name,
                    ':specialty' => $specialty,
                    ':city' => $city,
                    ':hospital' => $hospital,
                    ':experience' => $experience,
                    ':availability' => $availability,
                    ':rating' => $rating,
                    ':fee' => $fee,
                    ':photo_url' => $photoUrl,
                    ':photo_path' => $photoPath,
                    ':summary' => $doctorSummary,
                ]);
                $doctorProfileId = (int)$pdo->lastInsertId();
            }

            $insertUser = $pdo->prepare('INSERT INTO users (name, email, phone, password, role, specialty, city, experience, fee, doctor_profile_id) VALUES (:name, :email, :phone, :password, :role, :specialty, :city, :experience, :fee, :doctor_profile_id)');
            $insertUser->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':password' => $passwordHash,
                ':role' => $role,
                ':specialty' => $role === 'doctor' ? $specialty : null,
                ':city' => $role === 'doctor' ? $city : null,
                ':experience' => $role === 'doctor' ? $experience : null,
                ':fee' => $role === 'doctor' ? $fee : null,
                ':doctor_profile_id' => $doctorProfileId,
            ]);

            $userId = (int)$pdo->lastInsertId();
            $pdo->commit();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_role'] = $role;
            $_SESSION['doctor_profile_id'] = $doctorProfileId;

            header('Location: dashboard.php');
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Une erreur est survenue lors de l’inscription. Veuillez réessayer.';
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - RV Santé</title>
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
                <li class="nav-item"><a class="nav-link btn btn-outline-light px-3 py-2" href="login.php">Connexion</a></li>
            </ul>
        </div>
    </nav>
</header>
<main class="auth-page py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="auth-card p-4 p-md-5 shadow-sm rounded-4">
                    <div class="mb-4 text-center">
                        <h1 class="h3 fw-bold">Créer un compte RV Santé</h1>
                        <p class="text-secondary">Inscrivez-vous en tant que patient ou médecin et accédez à votre espace privé.</p>
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
                    <form method="post" action="register.php" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Nom complet</label>
                                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Numéro de téléphone</label>
                                <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" placeholder="+221770000000">
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" id="password" name="password" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label d-block mb-2">Vous vous inscrivez en tant que :</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="role" id="rolePatient" value="patient" <?php echo (!isset($_POST['role']) || $_POST['role'] === 'patient') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="rolePatient">Patient</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="role" id="roleDoctor" value="doctor" <?php echo (isset($_POST['role']) && $_POST['role'] === 'doctor') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="roleDoctor">Médecin</label>
                                </div>
                            </div>
                        </div>
                        <div id="doctorFields" class="mt-4" style="display: none;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="specialty" class="form-label">Spécialité</label>
                                    <input type="text" id="specialty" name="specialty" class="form-control" value="<?php echo htmlspecialchars($_POST['specialty'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="city" class="form-label">Ville</label>
                                    <input type="text" id="city" name="city" class="form-control" value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="hospital" class="form-label">Hôpital ou poste de santé</label>
                                    <input type="text" id="hospital" name="hospital" class="form-control" value="<?php echo htmlspecialchars($_POST['hospital'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="experience" class="form-label">Années d'expérience</label>
                                    <input type="number" id="experience" name="experience" class="form-control" min="1" value="<?php echo htmlspecialchars($_POST['experience'] ?? '5'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="fee" class="form-label">Tarif indicatif (FCFA)</label>
                                    <input type="number" id="fee" name="fee" class="form-control" min="10000" value="<?php echo htmlspecialchars($_POST['fee'] ?? '25000'); ?>">
                                </div>
                                <div class="col-12">
                                    <label for="doctor_photo" class="form-label">Photo du médecin</label>
                                    <input type="file" id="doctor_photo" name="doctor_photo" class="form-control" accept="image/*">
                                    <div class="form-text">Formats acceptés : JPG, JPEG, PNG, WEBP.</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Créer mon compte</button>
                        </div>
                    </form>
                    <div class="mt-4 text-center text-secondary">
                        Déjà inscrit ? <a href="login.php">Connectez-vous</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const roleInputs = document.querySelectorAll('input[name="role"]');
        const doctorFields = document.getElementById('doctorFields');
        function updateDoctorFields() {
            const selectedRole = document.querySelector('input[name="role"]:checked').value;
            doctorFields.style.display = selectedRole === 'doctor' ? 'block' : 'none';
        }
        roleInputs.forEach(input => input.addEventListener('change', updateDoctorFields));
        updateDoctorFields();
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
