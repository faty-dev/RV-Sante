<?php
require_once 'config.php';
startSecureSession();

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0 && $userId !== (int)$_SESSION['user_id']) {
            $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $userId]);
            $successMessage = 'Profil supprimé avec succès.';
        } else {
            $errorMessage = 'Impossible de supprimer ce profil.';
        }
    } elseif ($action === 'update_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'patient';
        $specialty = trim($_POST['specialty'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $hospital = trim($_POST['hospital'] ?? '');
        $experience = max(1, (int)($_POST['experience'] ?? 5));
        $fee = max(10000, (int)($_POST['fee'] ?? 25000));

        if ($userId > 0 && $name !== '' && $email !== '' && $phone !== '') {
            $stmt = $pdo->prepare('UPDATE users SET name = :name, email = :email, phone = :phone, role = :role, specialty = :specialty, city = :city, experience = :experience, fee = :fee WHERE id = :id');
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':role' => $role,
                ':specialty' => $role === 'doctor' ? $specialty : null,
                ':city' => $role === 'doctor' ? $city : null,
                ':experience' => $role === 'doctor' ? $experience : null,
                ':fee' => $role === 'doctor' ? $fee : null,
                ':id' => $userId,
            ]);

            if ($role === 'doctor') {
                $doctorStmt = $pdo->prepare('SELECT doctor_profile_id FROM users WHERE id = :id');
                $doctorStmt->execute([':id' => $userId]);
                $doctorProfileId = (int)$doctorStmt->fetchColumn();

                if ($doctorProfileId > 0) {
                    $pdo->prepare('UPDATE doctors SET name = :name, specialty = :specialty, city = :city, hospital = :hospital, experience = :experience, fee = :fee WHERE id = :id')->execute([
                        ':name' => $name,
                        ':specialty' => $specialty,
                        ':city' => $city,
                        ':hospital' => $hospital,
                        ':experience' => $experience,
                        ':fee' => $fee,
                        ':id' => $doctorProfileId,
                    ]);
                }
            }

            $successMessage = 'Profil mis à jour avec succès.';
        } else {
            $errorMessage = 'Veuillez remplir tous les champs obligatoires.';
        }
    } elseif ($action === 'update_appointment') {
        $appointmentId = (int)($_POST['appointment_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $doctorResponse = trim($_POST['doctor_response'] ?? '');
        $appointmentDate = trim($_POST['appointment_date'] ?? '');
        $appointmentTime = trim($_POST['appointment_time'] ?? '');

        if ($appointmentId > 0) {
            if ($status === 'rescheduled' && ($appointmentDate === '' || $appointmentTime === '')) {
                $errorMessage = 'La date et l’heure sont obligatoires pour un report.';
            } else {
                if ($status === 'rescheduled') {
                    $pdo->prepare('UPDATE appointments SET appointment_date = :appointment_date, appointment_time = :appointment_time, status = :status, doctor_response = :doctor_response WHERE id = :id')->execute([
                        ':appointment_date' => $appointmentDate,
                        ':appointment_time' => $appointmentTime,
                        ':status' => $status,
                        ':doctor_response' => $doctorResponse !== '' ? $doctorResponse : 'Rendez-vous reporté par l’administrateur.',
                        ':id' => $appointmentId,
                    ]);
                } else {
                    $pdo->prepare('UPDATE appointments SET status = :status, doctor_response = :doctor_response WHERE id = :id')->execute([
                        ':status' => $status,
                        ':doctor_response' => $doctorResponse !== '' ? $doctorResponse : 'Décision administrateur appliquée.',
                        ':id' => $appointmentId,
                    ]);
                }
                $successMessage = 'Rendez-vous mis à jour.';
            }
        }
    } elseif ($action === 'delete_appointment') {
        $appointmentId = (int)($_POST['appointment_id'] ?? 0);
        if ($appointmentId > 0) {
            $pdo->prepare('DELETE FROM appointments WHERE id = :id')->execute([':id' => $appointmentId]);
            $successMessage = 'Rendez-vous supprimé.';
        }
    }
}

$users = $pdo->query('SELECT id, name, email, phone, role, specialty, city, experience, fee, doctor_profile_id FROM users ORDER BY role, name')->fetchAll();
$appointments = $pdo->query('SELECT a.id, a.patient_name, a.appointment_date, a.appointment_time, a.status, a.doctor_response, a.created_at, d.name AS doctor_name, d.specialty FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.id ORDER BY a.appointment_date DESC, a.appointment_time DESC')->fetchAll();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - RV Santé</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header shadow-sm">
    <nav class="navbar navbar-expand-lg container py-3">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <span class="brand-icon d-flex align-items-center justify-content-center me-2">❤</span>
            <div><div class="brand-name">RV Santé</div></div>
        </a>
        <div class="ms-auto">
            <a href="logout.php" class="btn btn-outline-light btn-sm">Déconnexion</a>
        </div>
    </nav>
</header>
<main class="py-5">
    <div class="container">
        <div class="mb-4">
            <h1 class="fw-bold">Administration</h1>
            <p class="text-secondary">Gérez tous les profils médecins et patients présents sur la plateforme.</p>
        </div>

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Gestion des rendez-vous</h4>
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Médecin</th>
                                        <th>Date</th>
                                        <th>Heure</th>
                                        <th>Statut</th>
                                        <th>Décision</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['doctor_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['appointment_date']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['appointment_time']); ?></td>
                                            <td>
                                                <form method="post" class="d-flex flex-column gap-2">
                                                    <input type="hidden" name="action" value="update_appointment">
                                                    <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['id']; ?>">
                                                    <select name="status" class="form-select form-select-sm">
                                                        <option value="pending" <?php echo ($appointment['status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                                        <option value="confirmed" <?php echo ($appointment['status'] ?? 'pending') === 'confirmed' ? 'selected' : ''; ?>>Confirmé</option>
                                                        <option value="canceled" <?php echo ($appointment['status'] ?? 'pending') === 'canceled' ? 'selected' : ''; ?>>Annulé</option>
                                                        <option value="rescheduled" <?php echo ($appointment['status'] ?? 'pending') === 'rescheduled' ? 'selected' : ''; ?>>Reporté</option>
                                                    </select>
                                                    <input type="date" name="appointment_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($appointment['appointment_date']); ?>">
                                                    <input type="time" name="appointment_time" class="form-control form-control-sm" value="<?php echo htmlspecialchars($appointment['appointment_time']); ?>">
                                                    <input type="text" name="doctor_response" class="form-control form-control-sm" value="<?php echo htmlspecialchars($appointment['doctor_response'] ?? ''); ?>" placeholder="Message au patient">
                                                    <button class="btn btn-sm btn-dark">Enregistrer</button>
                                                </form>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['doctor_response'] ?? ''); ?></td>
                                            <td>
                                                <form method="post" onsubmit="return confirm('Supprimer ce rendez-vous ?');">
                                                    <input type="hidden" name="action" value="delete_appointment">
                                                    <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['id']; ?>">
                                                    <button class="btn btn-sm btn-outline-danger">Supprimer</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php foreach ($users as $user): ?>
                <div class="col-lg-6">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($user['name']); ?></h5>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span>
                                </div>
                                <div class="d-flex gap-2">
                                    <form method="post" onsubmit="return confirm('Voulez-vous vraiment supprimer ce profil ?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger">Supprimer</button>
                                    </form>
                                </div>
                            </div>

                            <form method="post" class="row g-3">
                                <input type="hidden" name="action" value="update_user">
                                <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                                <div class="col-md-6">
                                    <label class="form-label">Nom</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Téléphone</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Rôle</label>
                                    <select name="role" class="form-select">
                                        <option value="patient" <?php echo $user['role'] === 'patient' ? 'selected' : ''; ?>>Patient</option>
                                        <option value="doctor" <?php echo $user['role'] === 'doctor' ? 'selected' : ''; ?>>Médecin</option>
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                                <?php if ($user['role'] === 'doctor' || $user['role'] === 'admin'): ?>
                                    <div class="col-md-6">
                                        <label class="form-label">Spécialité</label>
                                        <input type="text" name="specialty" class="form-control" value="<?php echo htmlspecialchars($user['specialty'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Ville</label>
                                        <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Hôpital / Poste</label>
                                        <input type="text" name="hospital" class="form-control" value="">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Expérience</label>
                                        <input type="number" name="experience" class="form-control" min="1" value="<?php echo htmlspecialchars((string)($user['experience'] ?? 5)); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tarif</label>
                                        <input type="number" name="fee" class="form-control" min="10000" value="<?php echo htmlspecialchars((string)($user['fee'] ?? 25000)); ?>">
                                    </div>
                                <?php endif; ?>
                                <div class="col-12">
                                    <button class="btn btn-dark">Enregistrer les modifications</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
