<?php
require_once 'config.php';
startSecureSession();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'patient';
$appointmentId = (int)($_GET['id'] ?? 0);

if ($appointmentId > 0) {
    if ($userRole === 'doctor') {
        $doctorProfileId = (int)($_SESSION['doctor_profile_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE appointments SET status = :status, doctor_response = :doctor_response WHERE id = :id AND doctor_id = :doctor_id');
        $stmt->execute([
            ':status' => 'canceled',
            ':doctor_response' => 'Rendez-vous annulé par le médecin.',
            ':id' => $appointmentId,
            ':doctor_id' => $doctorProfileId,
        ]);
    } else {
        $stmt = $pdo->prepare('UPDATE appointments SET status = :status, doctor_response = :doctor_response WHERE id = :id AND patient_id = :patient_id');
        $stmt->execute([
            ':status' => 'canceled',
            ':doctor_response' => 'Rendez-vous annulé par le patient.',
            ':id' => $appointmentId,
            ':patient_id' => $userId,
        ]);
    }
}

header('Location: dashboard.php');
exit;
