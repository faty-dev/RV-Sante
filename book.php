<?php
require_once 'config.php';
startSecureSession();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userRole = $_SESSION['user_role'] ?? 'patient';
$doctorId = (int)($_GET['doctor_id'] ?? 0);
$doctor = null;
if ($doctorId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM doctors WHERE id = :id');
    $stmt->execute([':id' => $doctorId]);
    $doctor = $stmt->fetch();
}
if (!$doctor) {
    header('Location: doctors.php');
    exit;
}
$errors = [];
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientName = trim($_POST['patient_name'] ?? $_SESSION['user_name'] ?? '');
    $date = trim($_POST['appointment_date'] ?? '');
    $time = trim($_POST['appointment_time'] ?? '');

    if ($patientName === '') {
        $errors[] = 'Le nom du patient est obligatoire.';
    }
    if ($date === '') {
        $errors[] = 'La date du rendez-vous est obligatoire.';
    }
    if ($time === '') {
        $errors[] = "L'heure du rendez-vous est obligatoire.";
    }

    if (empty($errors)) {
        $patientUserId = (int)($_SESSION['user_id'] ?? 0);
        
        // Récupérer les coordonnées du patient depuis son compte
        $patientEmail = '';
        $patientPhone = '';
        if ($patientUserId > 0) {
            $userStmt = $pdo->prepare('SELECT email, phone FROM users WHERE id = :id');
            $userStmt->execute([':id' => $patientUserId]);
            $userData = $userStmt->fetch();
            $patientEmail = $userData['email'] ?? '';
            $patientPhone = $userData['phone'] ?? '';
        }
        
        $insert = $pdo->prepare('INSERT INTO appointments (doctor_id, patient_id, patient_name, patient_email, patient_phone, appointment_date, appointment_time, status) VALUES (:doctor_id, :patient_id, :patient_name, :patient_email, :patient_phone, :appointment_date, :appointment_time, :status)');
        $insert->execute([
            ':doctor_id' => $doctorId,
            ':patient_id' => $patientUserId > 0 ? $patientUserId : null,
            ':patient_name' => $patientName,
            ':patient_email' => $patientEmail,
            ':patient_phone' => $patientPhone,
            ':appointment_date' => $date,
            ':appointment_time' => $time,
            ':status' => 'pending',
        ]);
        $success = true;
        if ($userRole !== 'doctor') {
            header('Location: appointments.php?booking=success');
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
    <title>Prendre RDV - RV Santé</title>
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
                <li class="nav-item"><a class="nav-link" href="index.php#accueil">Accueil</a></li>
                <li class="nav-item"><a class="nav-link active" href="doctors.php">Trouver un médecin</a></li>
                <li class="nav-item"><a class="nav-link" href="appointments.php">Mes rendez-vous</a></li>
                <li class="nav-item"><a class="nav-link" href="index.php#disponibilites">Disponibilités hospitalière</a></li>
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Mon espace</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Déconnexion</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link btn btn-outline-light px-3 py-2" href="login.php">Connexion</a></li>
                    <li class="nav-item"><a class="nav-link btn btn-primary px-3 py-2 text-white" href="register.php">Inscription</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
</header>
<main class="py-5">
    <div class="container">
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="fw-bold">Prendre rendez-vous</h1>
                <p class="text-white">Vous êtes sur le point de réserver avec <?php echo htmlspecialchars($doctor['name']); ?>.</p>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-12">
                <div class="book-hero-card rounded-4 overflow-hidden shadow-sm">
                    <img src="https://images.unsplash.com/photo-1580281657929-3750280e1dfd?auto=format&fit=crop&w=1200&q=80" alt="Réservation médicale" class="img-fluid w-100">
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">Votre rendez-vous a été enregistré avec succès.</div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm bg-light text-dark overflow-hidden">
            <?php $doctorPhoto = resolveDoctorPhotoUrl($doctor['photo_url'] ?? ''); ?>
            <img src="<?php echo htmlspecialchars($doctorPhoto); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($doctor['name']); ?>">
            <div class="card-body">
                <h5 class="card-title"><?php echo htmlspecialchars($doctor['name']); ?></h5>
                <p class="card-text mb-2"><?php echo htmlspecialchars($doctor['specialty']); ?> · <?php echo htmlspecialchars($doctor['city']); ?></p>
                <p class="card-text mb-3 fw-semibold"><?php echo isset($doctor['fee']) ? number_format($doctor['fee'], 0, ',', ' ') . ' FCFA' : 'Tarif sur demande'; ?></p>
                <form method="post" action="book.php?doctor_id=<?php echo (int)$doctorId; ?>">
                    <div class="mb-3">
                        <label for="patient_name" class="form-label">Nom du patient</label>
                        <input type="text" id="patient_name" name="patient_name" class="form-control" value="<?php echo htmlspecialchars($_POST['patient_name'] ?? ''); ?>">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="appointment_date" class="form-label">Date</label>
                            <input type="date" id="appointment_date" name="appointment_date" class="form-control" value="<?php echo htmlspecialchars($_POST['appointment_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="appointment_time" class="form-label">Heure</label>
                            <input type="time" id="appointment_time" name="appointment_time" class="form-control" value="<?php echo htmlspecialchars($_POST['appointment_time'] ?? ''); ?>">
                        </div>
                    </div>
                    <button class="btn btn-dark">Confirmer le rendez-vous</button>
                </form>
            </div>
        </div>
    </div>
</main>
<footer class="footer">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <a href="index.php" class="footer-brand d-flex align-items-center mb-3">
                    <span class="brand-icon d-flex align-items-center justify-content-center me-2">❤</span>
                    <div>
                        <div class="brand-name">RV Santé</div>
                    </div>
                </a>
                <p class="footer-copy text-secondary">Un service digital fiable pour réserver vos consultations médicales au Sénégal.</p>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <h6 class="mb-3">Liens</h6>
                <ul class="list-unstyled footer-links mb-0">
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="doctors.php">Trouver un médecin</a></li>
                    <li><a href="appointments.php">Mes rendez-vous</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <h6 class="mb-3">Contact</h6>
                <p class="text-secondary mb-2">contact@rvsante.sn</p>
                <p class="text-secondary mb-2">+221 77 123 45 67</p>
                <p class="text-secondary mb-0">Dakar, Sénégal</p>
            </div>
            <div class="col-md-12 col-lg-3">
                <h6 class="mb-3">Newsletter</h6>
                <p class="text-secondary mb-3">Recevez des mises à jour santé et des créneaux disponibles.</p>
                <form class="footer-newsletter d-flex flex-column flex-sm-row gap-2" action="#" method="post">
                    <input type="email" class="form-control form-control-sm" placeholder="Votre email">
                    <button type="submit" class="btn btn-primary btn-sm">S'abonner</button>
                </form>
            </div>
        </div>
        <div class="footer-bottom mt-4 pt-4 border-top border-white border-opacity-10 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3">
            <p class="mb-0 text-secondary small">© 2026 RV Santé. Tous droits réservés.</p>
            <div class="d-flex gap-2">
                <a href="#" class="footer-social">F</a>
                <a href="#" class="footer-social">T</a>
                <a href="#" class="footer-social">I</a>
            </div>
        </div>
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
