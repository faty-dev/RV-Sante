<?php
$host = '127.0.0.1';
$db = 'rv_sante';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    echo '<h1>Erreur de connexion à la base de données</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_path', '/');
    ini_set('session.gc_maxlifetime', '3600');
    ini_set('session.name', 'RV_SANTE_SESSION');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function resolveDoctorPhotoUrl(?string $photoUrl): string
{
    $defaultPhotoUrl = 'img/doctors/doctor_1783360040_8c4d9ba2.png';
    $rawPhotoUrl = trim((string)($photoUrl ?? ''));

    if ($rawPhotoUrl === '') {
        return $defaultPhotoUrl;
    }

    $candidates = [
        $rawPhotoUrl,
        rawurldecode($rawPhotoUrl),
        str_replace('%20', ' ', $rawPhotoUrl),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }

        if (preg_match('#^https?://#i', $candidate)) {
            return $candidate;
        }

        $candidatePath = ltrim($candidate, '/');
        $fullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidatePath);
        if (file_exists($fullPath)) {
            return $candidate;
        }
    }

    return $defaultPhotoUrl;
}

function ensureDoctorProfile(PDO $pdo, array $user): int
{
    if (($user['role'] ?? '') !== 'doctor') {
        return 0;
    }

    if (!empty($user['doctor_profile_id'])) {
        $stmt = $pdo->prepare('SELECT id FROM doctors WHERE id = :id');
        $stmt->execute([':id' => $user['doctor_profile_id']]);
        if ($stmt->fetchColumn()) {
            return (int)$user['doctor_profile_id'];
        }
    }

    $name = trim((string)($user['name'] ?? ''));
    $specialty = trim((string)($user['specialty'] ?? ''));
    $city = trim((string)($user['city'] ?? ''));
    $experience = max(1, (int)($user['experience'] ?? 5));
    $fee = max(10000, (int)($user['fee'] ?? 25000));

    $stmt = $pdo->prepare('SELECT id FROM doctors WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => $name]);
    $doctorId = (int)$stmt->fetchColumn();

    if ($doctorId > 0) {
        $updateUser = $pdo->prepare('UPDATE users SET doctor_profile_id = :doctor_profile_id WHERE id = :id');
        $updateUser->execute([':doctor_profile_id' => $doctorId, ':id' => $user['id']]);
        return $doctorId;
    }

    $doctorSummary = 'Médecin inscrit sur RV Santé, prêt à accompagner ses patients avec professionnalisme.';
    $photoUrl = 'https://images.unsplash.com/photo-1550831107-1553da8c8464?auto=format&fit=crop&w=900&q=80';
    $availability = 'Disponible';
    $rating = 4.5;

    $insertDoctor = $pdo->prepare('INSERT INTO doctors (name, specialty, city, experience, availability, rating, fee, photo_url, summary) VALUES (:name, :specialty, :city, :experience, :availability, :rating, :fee, :photo_url, :summary)');
    $insertDoctor->execute([
        ':name' => $name,
        ':specialty' => $specialty,
        ':city' => $city,
        ':experience' => $experience,
        ':availability' => $availability,
        ':rating' => $rating,
        ':fee' => $fee,
        ':photo_url' => $photoUrl,
        ':summary' => $doctorSummary,
    ]);

    $doctorId = (int)$pdo->lastInsertId();
    $updateUser = $pdo->prepare('UPDATE users SET doctor_profile_id = :doctor_profile_id WHERE id = :id');
    $updateUser->execute([':doctor_profile_id' => $doctorId, ':id' => $user['id']]);

    return $doctorId;
}

function ensureAppointmentSchema(PDO $pdo): void
{
    $columns = $pdo->query('SHOW COLUMNS FROM appointments')->fetchAll(PDO::FETCH_COLUMN);
    $columnSet = array_flip($columns);

    $doctorColumns = $pdo->query('SHOW COLUMNS FROM doctors')->fetchAll(PDO::FETCH_COLUMN);
    $doctorColumnSet = array_flip($doctorColumns);
    if (!isset($doctorColumnSet['hospital'])) {
        $pdo->exec("ALTER TABLE doctors ADD COLUMN hospital VARCHAR(255) DEFAULT NULL");
    }

    if (!isset($doctorColumnSet['photo_path'])) {
        $pdo->exec("ALTER TABLE doctors ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL");
    }

    if (!isset($columnSet['patient_id'])) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN patient_id INT UNSIGNED DEFAULT NULL");
    }

    if (!isset($columnSet['status'])) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN status ENUM('pending','confirmed','canceled','rescheduled') NOT NULL DEFAULT 'pending'");
    }

    if (!isset($columnSet['doctor_response'])) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN doctor_response VARCHAR(255) DEFAULT NULL");
    }

    if (!isset($columnSet['updated_at'])) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    if (!isset($columnSet['patient_email'])) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN patient_email VARCHAR(255) DEFAULT NULL");
    }

    if (!isset($columnSet['patient_phone'])) {
        $pdo->exec("ALTER TABLE appointments ADD COLUMN patient_phone VARCHAR(20) DEFAULT NULL");
    }
}

function ensurePatientCommunicationSchema(PDO $pdo): void
{
    $userColumns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    $userColumnSet = array_flip($userColumns);
    if (!isset($userColumnSet['profile_photo_path'])) {
        $pdo->exec("ALTER TABLE users ADD COLUMN profile_photo_path VARCHAR(255) DEFAULT NULL");
    }

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS patient_documents (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT UNSIGNED NOT NULL,
            patient_id INT UNSIGNED NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_type VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS symptom_images (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT UNSIGNED NOT NULL,
            patient_id INT UNSIGNED NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            caption VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
            FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS appointment_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            appointment_id INT UNSIGNED NOT NULL,
            sender_id INT UNSIGNED NOT NULL,
            sender_role ENUM('patient','doctor','admin') NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    SQL);
}

function storeUploadedFile(array $file, string $targetDir, array $allowedExtensions = ['jpg','jpeg','png','gif','webp','pdf']): ?string
{
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return null;
    }

    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        return null;
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $safeFileName = bin2hex(random_bytes(8)) . '_' . time() . '.' . $extension;
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $safeFileName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return null;
    }

    return 'uploads/' . basename($targetDir) . '/' . $safeFileName;
}

ensureAppointmentSchema($pdo);
ensurePatientCommunicationSchema($pdo);
