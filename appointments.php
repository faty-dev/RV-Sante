<?php
require_once 'config.php';
startSecureSession();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'patient';
$appointments = [];
$feedback = [];
if (isset($_GET['booking']) && $_GET['booking'] === 'success') {
    $feedback[] = 'Votre rendez-vous a bien été enregistré. Il apparaît maintenant dans votre espace patient.';
}
$uploadBaseDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
$profilePhotoDir = $uploadBaseDir . DIRECTORY_SEPARATOR . 'avatars';
$dossierDir = $uploadBaseDir . DIRECTORY_SEPARATOR . 'dossiers';
$symptomDir = $uploadBaseDir . DIRECTORY_SEPARATOR . 'symptoms';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    if ($appointmentId > 0) {
        $allowedAppointment = false;
        if ($userRole === 'doctor') {
            $doctorProfileId = (int)($_SESSION['doctor_profile_id'] ?? 0);
            $stmt = $pdo->prepare('SELECT id FROM appointments WHERE id = :id AND doctor_id = :doctor_id');
            $stmt->execute([':id' => $appointmentId, ':doctor_id' => $doctorProfileId]);
            $allowedAppointment = (bool)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare('SELECT id FROM appointments WHERE id = :id AND (patient_id = :patient_id OR (patient_id IS NULL AND patient_name = :patient_name))');
            $stmt->execute([':id' => $appointmentId, ':patient_id' => $userId, ':patient_name' => $_SESSION['user_name'] ?? '']);
            $allowedAppointment = (bool)$stmt->fetchColumn();
        }

        if ($allowedAppointment) {
            if (isset($_POST['action']) && $_POST['action'] === 'send_medical_data') {
                // Upload photo de profil si fournie (pour les patients)
                if ($userRole !== 'doctor' && !empty($_FILES['profile_photo']['name'])) {
                    $photoPath = storeUploadedFile($_FILES['profile_photo'], $profilePhotoDir, ['jpg','jpeg','png','gif','webp']);
                    if ($photoPath !== null) {
                        $stmt = $pdo->prepare('UPDATE users SET profile_photo_path = :profile_photo_path WHERE id = :id');
                        $stmt->execute([':profile_photo_path' => $photoPath, ':id' => $userId]);
                        $_SESSION['profile_photo_path'] = $photoPath;
                        $feedback[] = 'Votre photo de profil a été mise à jour.';
                    }
                }

                $title = trim($_POST['dossier_title'] ?? '');
                $description = trim($_POST['dossier_description'] ?? '');
                $message = trim($_POST['message'] ?? '');
                $caption = trim($_POST['symptom_caption'] ?? '');
                
                // Upload dossier médical (pour patients et médecins)
                $filePath = null;
                if (!empty($_FILES['dossier_file']['name'])) {
                    $filePath = storeUploadedFile($_FILES['dossier_file'], $dossierDir, ['jpg','jpeg','png','gif','webp','pdf']);
                }
                if ($filePath !== null || $title !== '' || $description !== '') {
                    $stmt = $pdo->prepare('INSERT INTO patient_documents (appointment_id, patient_id, title, description, file_path, file_type) VALUES (:appointment_id, :patient_id, :title, :description, :file_path, :file_type)');
                    $stmt->execute([
                        ':appointment_id' => $appointmentId,
                        ':patient_id' => $userRole === 'doctor' ? null : $userId,
                        ':title' => $title !== '' ? $title : 'Dossier médical',
                        ':description' => $description,
                        ':file_path' => $filePath ?? '',
                        ':file_type' => $filePath ? pathinfo($filePath, PATHINFO_EXTENSION) : null,
                    ]);
                    $feedback[] = $userRole === 'doctor' ? 'Le dossier médical a été ajouté au dossier du patient.' : 'Votre dossier médical a été partagé au médecin.';
                }
                
                // Upload photos de symptômes (pour les patients seulement)
                if ($userRole !== 'doctor' && !empty($_FILES['symptom_images']['name'][0])) {
                    foreach ($_FILES['symptom_images']['tmp_name'] as $index => $tmpName) {
                        if (!empty($_FILES['symptom_images']['name'][$index])) {
                            $symptomPath = storeUploadedFile([
                                'tmp_name' => $tmpName,
                                'name' => $_FILES['symptom_images']['name'][$index],
                                'error' => $_FILES['symptom_images']['error'][$index],
                                'size' => $_FILES['symptom_images']['size'][$index],
                            ], $symptomDir, ['jpg','jpeg','png','gif','webp']);
                            if ($symptomPath !== null) {
                                $stmt = $pdo->prepare('INSERT INTO symptom_images (appointment_id, patient_id, image_path, caption) VALUES (:appointment_id, :patient_id, :image_path, :caption)');
                                $stmt->execute([
                                    ':appointment_id' => $appointmentId,
                                    ':patient_id' => $userId,
                                    ':image_path' => $symptomPath,
                                    ':caption' => $caption !== '' ? $caption : null,
                                ]);
                            }
                        }
                    }
                    $feedback[] = 'Vos photos de symptômes ont été ajoutées.';
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
                    $feedback[] = $userRole === 'doctor' ? 'Votre message a été envoyé au patient.' : 'Votre message a été envoyé au médecin.';
                }
            }
        }
    }
}

if ($userRole === 'doctor') {
    $doctorProfileId = (int)($_SESSION['doctor_profile_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT a.id, a.patient_name, a.appointment_date, a.appointment_time, a.status, a.doctor_response, a.patient_id, a.patient_email, a.patient_phone, u.email AS user_email, u.phone AS user_phone, d.name AS doctor_name, d.specialty FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.id LEFT JOIN users u ON a.patient_id = u.id WHERE a.doctor_id = :doctor_id ORDER BY a.appointment_date DESC, a.appointment_time DESC');
    $stmt->execute([':doctor_id' => $doctorProfileId]);
    $appointments = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT a.id, a.patient_name, a.appointment_date, a.appointment_time, a.status, a.doctor_response, d.name AS doctor_name, d.specialty FROM appointments a LEFT JOIN doctors d ON a.doctor_id = d.id WHERE (a.patient_id = :patient_id OR (a.patient_id IS NULL AND a.patient_name = :patient_name)) ORDER BY a.appointment_date DESC, a.appointment_time DESC');
    $stmt->execute([':patient_id' => $userId, ':patient_name' => $_SESSION['user_name'] ?? '']);
    $appointments = $stmt->fetchAll();
}

foreach ($appointments as &$appointment) {
    $appointmentId = (int)$appointment['id'];
    $documentsStmt = $pdo->prepare('SELECT * FROM patient_documents WHERE appointment_id = :appointment_id ORDER BY created_at DESC');
    $documentsStmt->execute([':appointment_id' => $appointmentId]);
    $appointment['documents'] = $documentsStmt->fetchAll();

    $symptomsStmt = $pdo->prepare('SELECT * FROM symptom_images WHERE appointment_id = :appointment_id ORDER BY created_at DESC');
    $symptomsStmt->execute([':appointment_id' => $appointmentId]);
    $appointment['symptoms'] = $symptomsStmt->fetchAll();

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
    <title>Mes rendez-vous - RV Santé</title>
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
                <li class="nav-item"><a class="nav-link" href="doctors.php">Trouver un médecin</a></li>
                <li class="nav-item"><a class="nav-link active" href="appointments.php">Mes rendez-vous</a></li>
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
                <h1 class="fw-bold">Mes rendez-vous</h1>
                <p class="text-secondary">Retrouvez vos rendez-vous confirmés et passez à l'étape suivante si nécessaire.</p>
            </div>
        </div>
        <div class="alert alert-light border mb-4" style="color: black;">
            <strong>Zone de préparation du rendez-vous :</strong> vous pourrez ici partager votre dossier médical, envoyer des photos de symptômes, joindre une photo de profil visible pour le médecin et échanger en message privé.
        </div>
        <?php foreach ($feedback as $message): ?>
            <div class="alert alert-light border" style="color: black;"><?php echo htmlspecialchars($message); ?></div>
        <?php endforeach; ?>
        <?php if (!empty($appointments)): ?>
            <div class="row g-4">
                <?php foreach ($appointments as $appointment): ?>
                    <div class="col-md-6">
                        <div class="card shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($appointment['doctor_name'] ?? $appointment['patient_name']); ?></h5>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($appointment['specialty'] ?? 'Médecin traitant'); ?></p>
                                <p class="card-text mb-1"><strong><?php echo $userRole === 'doctor' ? 'Patient' : 'Médecin'; ?> :</strong> <?php echo htmlspecialchars($userRole === 'doctor' ? $appointment['patient_name'] : $appointment['doctor_name']); ?></p>
                                
                                <?php if ($userRole === 'doctor'): ?>
                                    <?php 
                                    // Priorité aux coordonnées directement sur le RDV, sinon celles du compte utilisateur
                                    $patientEmail = $appointment['patient_email'] ?? $appointment['user_email'] ?? '';
                                    $patientPhone = $appointment['patient_phone'] ?? $appointment['user_phone'] ?? '';
                                    ?>
                                    <?php if (!empty($patientEmail)): ?>
                                        <p class="card-text mb-1"><strong>Email du patient :</strong> <a href="mailto:<?php echo htmlspecialchars($patientEmail); ?>"><?php echo htmlspecialchars($patientEmail); ?></a></p>
                                    <?php endif; ?>
                                    <?php if (!empty($patientPhone)): ?>
                                        <p class="card-text mb-1"><strong>Téléphone du patient :</strong> <a href="tel:<?php echo htmlspecialchars($patientPhone); ?>"><?php echo htmlspecialchars($patientPhone); ?></a></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <p class="card-text mb-1"><strong>Date :</strong> <?php echo htmlspecialchars($appointment['appointment_date']); ?></p>
                                <p class="card-text mb-1"><strong>Heure :</strong> <?php echo htmlspecialchars($appointment['appointment_time']); ?></p>
                                <?php
                                    $status = $appointment['status'] ?? 'pending';
                                    $badgeClass = 'bg-secondary';
                                    if ($status === 'confirmed') { $badgeClass = 'bg-success'; }
                                    elseif ($status === 'canceled') { $badgeClass = 'bg-danger'; }
                                    elseif ($status === 'rescheduled') { $badgeClass = 'bg-warning text-dark'; }
                                ?>
                                <p class="card-text mb-1"><strong>Statut :</strong> <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></p>
                                <?php if (!empty($appointment['doctor_response'])): ?>
                                    <p class="card-text"><strong>Décision médecin :</strong> <?php echo htmlspecialchars($appointment['doctor_response']); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($userRole === 'doctor'): ?>
                                    <hr>
                                    <h6 class="mt-3">Ajouter un dossier médical pour le patient</h6>
                                    <form method="post" enctype="multipart/form-data" class="mt-3">
                                        <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['id']; ?>">
                                        <input type="hidden" name="action" value="send_medical_data">
                                        
                                        <div class="mb-2">
                                            <label class="form-label">Titre du dossier médical</label>
                                            <input type="text" name="dossier_title" class="form-control" placeholder="Ex. Compte rendu, ordonnance, examens">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Description du dossier médical</label>
                                            <textarea name="dossier_description" class="form-control" rows="2" placeholder="Ajoutez les informations utiles au patient"></textarea>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Fichier joint (PDF ou image)</label>
                                            <input type="file" name="dossier_file" class="form-control" accept=".pdf,image/*">
                                        </div>
                                        
                                        <div class="mb-2">
                                            <label class="form-label">Message privé au patient (optionnel)</label>
                                            <textarea name="message" class="form-control" rows="2" placeholder="Écrivez un message au patient"></textarea>
                                        </div>
                                        
                                        <button class="btn btn-primary w-100">Envoyer le dossier au patient</button>
                                    </form>
                                <?php else: ?>
                                    <hr>
                                    <h6 class="mt-3">Préparer votre consultation - Envoyer tout le dossier médical</h6>
                                    <form method="post" enctype="multipart/form-data" class="mt-3">
                                        <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment['id']; ?>">
                                        <input type="hidden" name="action" value="send_medical_data">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Photo de profil visible pour le médecin (optionnel)</label>
                                            <input type="file" name="profile_photo" class="form-control" accept="image/*">
                                        </div>
                                        
                                        <div class="mb-2">
                                            <label class="form-label">Titre du dossier médical</label>
                                            <input type="text" name="dossier_title" class="form-control" placeholder="Ex. Antécédents, examens, allergies">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Description du dossier médical</label>
                                            <textarea name="dossier_description" class="form-control" rows="2" placeholder="Ajoutez les informations utiles au médecin"></textarea>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Fichier joint (PDF ou image)</label>
                                            <input type="file" name="dossier_file" class="form-control" accept=".pdf,image/*">
                                        </div>
                                        
                                        <div class="mb-2">
                                            <label class="form-label">Photos des symptômes (optionnel)</label>
                                            <input type="file" name="symptom_images[]" class="form-control" multiple accept="image/*">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Légende des symptômes</label>
                                            <input type="text" name="symptom_caption" class="form-control" placeholder="Ex. Éruption sur le bras">
                                        </div>
                                        
                                        <div class="mb-2">
                                            <label class="form-label">Message privé au médecin (optionnel)</label>
                                            <textarea name="message" class="form-control" rows="2" placeholder="Expliquez vos symptômes ou posez une question"></textarea>
                                        </div>
                                        
                                        <button class="btn btn-primary w-100">Envoyer tout au médecin</button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if (!empty($appointment['documents'])): ?>
                                    <div class="mt-3">
                                        <strong><?php echo $userRole === 'doctor' ? 'Dossiers du patient' : 'Dossiers partagés'; ?></strong>
                                        <ul class="small mt-2">
                                            <?php foreach ($appointment['documents'] as $document): ?>
                                                <li>
                                                    <?php echo htmlspecialchars($document['title'] ?? 'Dossier médical'); ?>
                                                    <?php if (!empty($document['file_path'])): ?>
                                                        <br><a href="<?php echo htmlspecialchars($document['file_path']); ?>" target="_blank" rel="noopener">Voir le fichier</a>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($appointment['symptoms'])): ?>
                                    <div class="mt-3">
                                        <strong>Photos de symptômes</strong>
                                        <div class="row g-2 mt-2">
                                            <?php foreach ($appointment['symptoms'] as $symptom): ?>
                                                <div class="col-6">
                                                    <img src="<?php echo htmlspecialchars($symptom['image_path']); ?>" class="img-fluid rounded" alt="Symptôme">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($appointment['messages'])): ?>
                                    <div class="mt-3">
                                        <strong>Messages privés</strong>
                                        <div class="mt-2">
                                            <?php foreach ($appointment['messages'] as $message): ?>
                                                <div class="border rounded p-2 mb-2">
                                                    <small class="text-muted"><?php echo htmlspecialchars($message['sender_name'] ?? 'Utilisateur'); ?></small>
                                                    <div><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Aucun rendez-vous enregistré. Utilisez la page <a href="doctors.php" class="alert-link">Trouver un médecin</a> pour réserver, puis cette page affichera automatiquement les nouveaux formulaires de préparation du rendez-vous.
            </div>
        <?php endif; ?>
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
                <p class="footer-copy text-secondary">Simplifiez vos rendez-vous médicaux au Sénégal avec un service rapide et moderne.</p>
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
                <p class="text-secondary mb-3">Recevez des alertes de disponibilité et des conseils santé directement par email.</p>
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
