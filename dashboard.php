<?php
require_once 'config.php';
startSecureSession();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$userName = $_SESSION['user_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $doctorProfileId = (int)($_SESSION['doctor_profile_id'] ?? 0);
    $feedback = [];
    $uploadBaseDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    $dossierDir = $uploadBaseDir . DIRECTORY_SEPARATOR . 'dossiers';

    if ($appointmentId > 0) {
        $allowedAppointment = false;
        if ($userRole === 'doctor') {
            $checkStmt = $pdo->prepare('SELECT id FROM appointments WHERE id = :id AND doctor_id = :doctor_id LIMIT 1');
            $checkStmt->execute([':id' => $appointmentId, ':doctor_id' => $doctorProfileId]);
            $allowedAppointment = (bool)$checkStmt->fetchColumn();
        } else {
            $checkStmt = $pdo->prepare('SELECT id FROM appointments WHERE id = :id AND (patient_id = :patient_id OR (patient_id IS NULL AND patient_name = :patient_name))');
            $checkStmt->execute([':id' => $appointmentId, ':patient_id' => $userId, ':patient_name' => $_SESSION['user_name'] ?? '']);
            $allowedAppointment = (bool)$checkStmt->fetchColumn();
        }

        if ($allowedAppointment) {
            if ($action === 'confirm') {
                $updateStmt = $pdo->prepare('UPDATE appointments SET status = :status, doctor_response = :doctor_response WHERE id = :id AND doctor_id = :doctor_id');
                $updateStmt->execute([':status' => 'confirmed', ':doctor_response' => 'Rendez-vous confirmé par le médecin.', ':id' => $appointmentId, ':doctor_id' => $doctorProfileId]);
            } elseif ($action === 'cancel') {
                $updateStmt = $pdo->prepare('UPDATE appointments SET status = :status, doctor_response = :doctor_response WHERE id = :id AND doctor_id = :doctor_id');
                $updateStmt->execute([':status' => 'canceled', ':doctor_response' => 'Rendez-vous annulé par le médecin.', ':id' => $appointmentId, ':doctor_id' => $doctorProfileId]);
            } elseif ($action === 'reschedule') {
                $newDate = trim($_POST['appointment_date'] ?? '');
                $newTime = trim($_POST['appointment_time'] ?? '');
                if ($newDate !== '' && $newTime !== '') {
                    $updateStmt = $pdo->prepare('UPDATE appointments SET appointment_date = :appointment_date, appointment_time = :appointment_time, status = :status, doctor_response = :doctor_response WHERE id = :id AND doctor_id = :doctor_id');
                    $updateStmt->execute([
                        ':appointment_date' => $newDate,
                        ':appointment_time' => $newTime,
                        ':status' => 'rescheduled',
                        ':doctor_response' => 'Rendez-vous reporté à une nouvelle date par le médecin.',
                        ':id' => $appointmentId,
                        ':doctor_id' => $doctorProfileId,
                    ]);
                }
            } elseif ($action === 'send_medical_data') {
                $title = trim($_POST['dossier_title'] ?? '');
                $description = trim($_POST['dossier_description'] ?? '');
                $message = trim($_POST['message'] ?? '');
                
                // Upload dossier médical (pour médecins)
                $filePath = null;
                if (!empty($_FILES['dossier_file']['name'])) {
                    $filePath = storeUploadedFile($_FILES['dossier_file'], $dossierDir, ['jpg','jpeg','png','gif','webp','pdf']);
                }
                if ($filePath !== null || $title !== '' || $description !== '') {
                    $stmt = $pdo->prepare('INSERT INTO patient_documents (appointment_id, patient_id, title, description, file_path, file_type) VALUES (:appointment_id, :patient_id, :title, :description, :file_path, :file_type)');
                    $stmt->execute([
                        ':appointment_id' => $appointmentId,
                        ':patient_id' => null,
                        ':title' => $title !== '' ? $title : 'Dossier médical',
                        ':description' => $description,
                        ':file_path' => $filePath ?? '',
                        ':file_type' => $filePath ? pathinfo($filePath, PATHINFO_EXTENSION) : null,
                    ]);
                    $feedback[] = 'Le dossier médical a été ajouté au dossier du patient.';
                }
                
                // Envoi du message
                if ($message !== '') {
                    $stmt = $pdo->prepare('INSERT INTO appointment_messages (appointment_id, sender_id, sender_role, message) VALUES (:appointment_id, :sender_id, :sender_role, :message)');
                    $stmt->execute([
                        ':appointment_id' => $appointmentId,
                        ':sender_id' => $userId,
                        ':sender_role' => $userRole,
                        ':message' => $message,
                    ]);
                    $feedback[] = 'Votre message a été envoyé au patient.';
                }
            }
        }
    }

    header('Location: dashboard.php');
    exit;
}

$appointments = [];
if ($userRole === 'patient') {
    $stmt = $pdo->prepare('SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.doctor_response, d.name AS doctor_name, d.specialty FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.id WHERE (a.patient_id = :patient_id OR (a.patient_id IS NULL AND a.patient_name = :patient_name)) ORDER BY a.appointment_date DESC, a.appointment_time DESC');
    $stmt->execute([':patient_id' => $userId, ':patient_name' => $_SESSION['user_name'] ?? '']);
    $appointments = $stmt->fetchAll();
} elseif ($userRole === 'doctor') {
    $doctorProfileId = (int)($_SESSION['doctor_profile_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT a.id, a.patient_name, a.appointment_date, a.appointment_time, a.status, a.doctor_response, a.updated_at FROM appointments a WHERE a.doctor_id = :doctor_id ORDER BY a.appointment_date DESC, a.appointment_time DESC');
    $stmt->execute([':doctor_id' => $doctorProfileId]);
    $appointments = $stmt->fetchAll();
} else {
    $stmt = $pdo->query('SELECT a.id, a.appointment_date, a.appointment_time, a.patient_name, d.name AS doctor_name FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.id ORDER BY a.created_at DESC');
    $appointments = $stmt->fetchAll();
}

// Charger les documents, symptômes et messages pour chaque rendez-vous
foreach ($appointments as &$appointment) {
    $appointmentId = (int)$appointment['id'];
    
    // Documents du patient
    $documentsStmt = $pdo->prepare('SELECT * FROM patient_documents WHERE appointment_id = :appointment_id ORDER BY created_at DESC');
    $documentsStmt->execute([':appointment_id' => $appointmentId]);
    $appointment['documents'] = $documentsStmt->fetchAll();

    // Photos de symptômes
    $symptomsStmt = $pdo->prepare('SELECT * FROM symptom_images WHERE appointment_id = :appointment_id ORDER BY created_at DESC');
    $symptomsStmt->execute([':appointment_id' => $appointmentId]);
    $appointment['symptoms'] = $symptomsStmt->fetchAll();

    // Messages
    $messagesStmt = $pdo->prepare('SELECT m.*, u.name AS sender_name FROM appointment_messages m LEFT JOIN users u ON u.id = m.sender_id WHERE appointment_id = :appointment_id ORDER BY created_at ASC');
    $messagesStmt->execute([':appointment_id' => $appointmentId]);
    $appointment['messages'] = $messagesStmt->fetchAll();
}
unset($appointment);
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RV Santé</title>
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
                <li class="nav-item"><a class="nav-link" href="doctors.php">Trouver un médecin</a></li>
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Mon espace</a></li>
                <li class="nav-item"><a class="nav-link" href="logout.php">Déconnexion</a></li>
            </ul>
        </div>
    </nav>
</header>
<main class="py-5">
    <div class="container">
        <div class="row align-items-center mb-4">
            <div class="col-md-8">
                <h1 class="fw-bold">Bienvenue, <?php echo htmlspecialchars($userName); ?></h1>
                <p class="text-secondary">Espace privé <?php echo htmlspecialchars($userRole); ?>. Gérez vos rendez-vous et votre profil.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="logout.php" class="btn btn-outline-light">Se déconnecter</a>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card bg-dark text-white shadow-sm rounded-4 p-4">
                    <h5 class="mb-3">Profil</h5>
                    <p><strong>Nom :</strong> <?php echo htmlspecialchars($userName); ?></p>
                    <p><strong>Rôle :</strong> <?php echo htmlspecialchars(ucfirst($userRole)); ?></p>
                    <?php if ($userRole === 'doctor'): ?>
                        <p><a href="book.php" class="btn btn-light btn-sm">Voir mes rendez-vous</a></p>
                    <?php else: ?>
                        <p><a href="doctors.php" class="btn btn-light btn-sm">Chercher un médecin</a></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card bg-dark bg-opacity-75 text-white shadow-sm rounded-4 p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h5 class="mb-1">Rendez-vous récents</h5>
                            <p class="text-secondary mb-0">Gérez vos rendez-vous et annulez si nécessaire.</p>
                        </div>
                        <?php if ($userRole === 'patient'): ?>
                            <a href="doctors.php" class="btn btn-primary btn-sm">Réserver un nouveau RDV</a>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($appointments)): ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Heure</th>
                                        <th>Patient / Médecin</th>
                                        <th>Spécialité</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($appointment['appointment_date']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['appointment_time']); ?></td>
                                            <td><?php echo htmlspecialchars($userRole === 'patient' ? $appointment['doctor_name'] : ($appointment['patient_name'] ?? '-')); ?></td>
                                            <td>
                                                <?php if ($userRole === 'doctor'): ?>
                                                    <div class="d-flex flex-column gap-2">
                                                        <?php
                                                            $status = $appointment['status'] ?? 'pending';
                                                            $badgeClass = 'bg-secondary';
                                                            if ($status === 'confirmed') { $badgeClass = 'bg-success'; }
                                                            elseif ($status === 'canceled') { $badgeClass = 'bg-danger'; }
                                                            elseif ($status === 'rescheduled') { $badgeClass = 'bg-warning text-dark'; }
                                                        ?>
                                                        <span class="badge <?php echo $badgeClass; ?> align-self-start"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <form method="post" class="d-flex gap-2 flex-wrap">
                                                                <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['id']; ?>">
                                                                <button type="submit" name="action" value="confirm" class="btn btn-sm btn-success">Confirmer</button>
                                                                <button type="submit" name="action" value="cancel" class="btn btn-sm btn-outline-danger">Annuler</button>
                                                            </form>
                                                        </div>
                                                        <form method="post" class="row g-2 align-items-end">
                                                            <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['id']; ?>">
                                                            <div class="col-6">
                                                                <label class="form-label small mb-1">Date</label>
                                                                <input type="date" name="appointment_date" class="form-control form-control-sm" required>
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label small mb-1">Heure</label>
                                                                <input type="time" name="appointment_time" class="form-control form-control-sm" required>
                                                            </div>
                                                            <div class="col-12">
                                                                <button type="submit" name="action" value="reschedule" class="btn btn-sm btn-outline-light">Reporter</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($appointment['specialty'] ?? '-'); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($userRole === 'doctor'): ?>
                                                    <div class="small text-secondary mb-2"><?php echo !empty($appointment['doctor_response']) ? htmlspecialchars($appointment['doctor_response']) : 'En attente de décision.'; ?></div>
                                                    
                                                    <?php if (!empty($appointment['documents'])): ?>
                                                        <div class="mt-2">
                                                            <strong class="small">Dossiers médicaux du patient</strong>
                                                            <ul class="small mt-1 mb-0">
                                                                <?php foreach ($appointment['documents'] as $document): ?>
                                                                    <li>
                                                                        <?php echo htmlspecialchars($document['title'] ?? 'Dossier médical'); ?>
                                                                        <?php if (!empty($document['file_path'])): ?>
                                                                            <br><a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank" class="text-info">Voir le fichier</a>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($document['description'])): ?>
                                                                            <br><span class="text-muted"><?php echo htmlspecialchars($document['description']); ?></span>
                                                                        <?php endif; ?>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($appointment['symptoms'])): ?>
                                                        <div class="mt-2">
                                                            <strong class="small">Photos de symptômes</strong>
                                                            <div class="row g-1 mt-1">
                                                                <?php foreach ($appointment['symptoms'] as $symptom): ?>
                                                                    <div class="col-4">
                                                                        <img src="<?php echo htmlspecialchars($symptom['image_path']); ?>" class="img-fluid rounded" alt="Symptôme" style="max-height: 60px; object-fit: cover;">
                                                                        <?php if (!empty($symptom['caption'])): ?>
                                                                            <small class="text-muted d-block"><?php echo htmlspecialchars($symptom['caption']); ?></small>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($appointment['messages'])): ?>
                                                        <div class="mt-2">
                                                            <strong class="small">Messages du patient</strong>
                                                            <div class="mt-1">
                                                                <?php foreach ($appointment['messages'] as $msg): ?>
                                                                    <div class="border rounded p-1 mb-1 bg-dark bg-opacity-50">
                                                                        <small class="text-muted"><?php echo htmlspecialchars($msg['sender_name'] ?? 'Patient'); ?></small>
                                                                        <div class="small"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <hr class="my-2">
                                                    <h6 class="small mb-2">Ajouter un dossier médical pour le patient</h6>
                                                    <form method="post" enctype="multipart/form-data" class="mt-2">
                                                        <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['id']; ?>">
                                                        <input type="hidden" name="action" value="send_medical_data">
                                                        
                                                        <div class="mb-2">
                                                            <label class="form-label small mb-1">Titre du dossier</label>
                                                            <input type="text" name="dossier_title" class="form-control form-control-sm" placeholder="Ex. Compte rendu, ordonnance">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label small mb-1">Description</label>
                                                            <textarea name="dossier_description" class="form-control form-control-sm" rows="1" placeholder="Informations utiles"></textarea>
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label small mb-1">Fichier (PDF ou image)</label>
                                                            <input type="file" name="dossier_file" class="form-control form-control-sm" accept=".pdf,image/*">
                                                        </div>
                                                        <div class="mb-2">
                                                            <label class="form-label small mb-1">Message privé (optionnel)</label>
                                                            <textarea name="message" class="form-control form-control-sm" rows="1" placeholder="Message au patient"></textarea>
                                                        </div>
                                                        <button class="btn btn-sm btn-primary w-100">Envoyer le dossier</button>
                                                    </form>
                                                <?php else: ?>
                                                    <div class="d-flex flex-column gap-1">
                                                        <?php
                                                            $status = $appointment['status'] ?? 'pending';
                                                            $badgeClass = 'bg-secondary';
                                                            if ($status === 'confirmed') { $badgeClass = 'bg-success'; }
                                                            elseif ($status === 'canceled') { $badgeClass = 'bg-danger'; }
                                                            elseif ($status === 'rescheduled') { $badgeClass = 'bg-warning text-dark'; }
                                                        ?>
                                                        <span class="badge <?php echo $badgeClass; ?> align-self-start">
                                                            <?php echo htmlspecialchars(ucfirst($status)); ?>
                                                        </span>
                                                        <?php if (!empty($appointment['doctor_response'])): ?>
                                                            <span class="small text-light"><?php echo htmlspecialchars($appointment['doctor_response']); ?></span>
                                                        <?php endif; ?>
                                                        <a href="cancel.php?id=<?php echo (int)$appointment['id']; ?>" class="btn btn-sm btn-outline-light">Annuler</a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light text-dark mb-0">Aucun rendez-vous enregistré pour le moment.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>