<?php
require_once 'config.php';
startSecureSession();
$term = trim($_GET['term'] ?? '');
$city = trim($_GET['city'] ?? '');
$termMappings = [
    'Cardiologie' => 'Cardiologie',
    'Cardiologue' => 'Cardiologie',
    'Dermatologie' => 'Dermatologie',
    'Dermatologue' => 'Dermatologie',
    'Psychologie clinique' => 'Psychologie clinique',
    'Aide psychologique' => 'Aide psychologique',
    'Gastro-entérologie' => 'Gastro-entérologie',
    'Pneumologie' => 'Pneumologie',
    'Rhumatologie' => 'Rhumatologie',
    'Neurologie' => 'Neurologie',
    'Chirurgie générale' => 'Chirurgie générale',
    'Chirurgie viscérale' => 'Chirurgie viscérale',
];
$searchTerm = "%$term%";
$searchMappedTerm = '';
if ($term !== '' && isset($termMappings[$term])) {
    $searchMappedTerm = "%{$termMappings[$term]}%";
}
$query = 'SELECT * FROM doctors WHERE 1=1';
$params = [];
if ($term !== '') {
    $query .= ' AND (specialty LIKE :specialtyTerm OR name LIKE :nameTerm OR summary LIKE :summaryTerm';
    if ($searchMappedTerm !== '') {
        $query .= ' OR specialty LIKE :mappedTerm';
    }
    $query .= ')';
    $params[':specialtyTerm'] = $searchTerm;
    $params[':nameTerm'] = $searchTerm;
    $params[':summaryTerm'] = $searchTerm;
    if ($searchMappedTerm !== '') {
        $params[':mappedTerm'] = $searchMappedTerm;
    }
}
if ($city !== '') {
    $query .= ' AND city LIKE :city';
    $params[':city'] = "%$city%";
}
$query .= ' ORDER BY rating DESC';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$doctors = $stmt->fetchAll();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trouver un médecin - RV Santé</title>
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
        <div class="row mb-4 align-items-center">
            <div class="col-md-8">
                <h1 class="fw-bold">Trouver un médecin</h1>
                <p class="text-secondary">Recherchez par spécialité, médecin ou ville et consultez les disponibilités en direct.</p>
                <?php if ($term !== ''): ?>
                    <div class="alert alert-info mt-3 text-dark">
                        <strong class="text-dark">Spécialistes en :</strong> <?php echo htmlspecialchars($term); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-12">
                <div class="doctor-hero-card rounded-4 overflow-hidden shadow-sm">
                    <img src="img/banniers.jpg" alt="Trouver un médecin" class="img-fluid w-100">
                </div>
            </div>
        </div>
        <form class="row g-3 mb-5" method="get" action="doctors.php">
            <div class="col-md-6">
                <label for="term" class="form-label">Spécialité, médecin ou symptôme</label>
                <input id="term" name="term" class="form-control" value="<?php echo htmlspecialchars($term); ?>">
            </div>
            <div class="col-md-4">
                <label for="city" class="form-label">Ville ou code postal</label>
                <input id="city" name="city" class="form-control" value="<?php echo htmlspecialchars($city); ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-dark">Rechercher</button>
            </div>
        </form>

        <?php if (!empty($doctors)): ?>
            <div class="row g-4">
                <?php foreach ($doctors as $doctor): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="card shadow-sm h-100 overflow-hidden">
                            <?php $imagePath = resolveDoctorPhotoUrl($doctor['photo_url'] ?? ''); ?>
                            <img src="<?php echo htmlspecialchars($imagePath); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($doctor['name']); ?>" style="height: 240px; object-fit: cover;">
                            <div class="card-body">
                                <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($doctor['specialty']); ?></span>
                                <h5 class="card-title"><?php echo htmlspecialchars($doctor['name']); ?></h5>
                                <p class="card-text text-muted mb-2"><?php echo htmlspecialchars($doctor['city']); ?> · <?php echo htmlspecialchars($doctor['experience']); ?> ans</p>
                                <p class="card-text mb-2 small">Disponibilité : <?php echo htmlspecialchars($doctor['availability'] ?? 'Disponible'); ?></p>
                                <p class="card-text fw-semibold mb-3"><?php echo isset($doctor['fee']) ? number_format($doctor['fee'], 0, ',', ' ') . ' FCFA' : 'Tarif sur demande'; ?></p>
                            </div>
                            <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-warning">★ <?php echo htmlspecialchars($doctor['rating']); ?></span>
                                </div>
                                <a href="book.php?doctor_id=<?php echo (int)$doctor['id']; ?>" class="btn btn-sm btn-dark">Prendre RDV</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">Aucun médecin ne correspond à votre recherche. Essayez une autre spécialité ou une autre localisation.</div>
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
                <p class="footer-copy text-secondary">Un service digital pour trouver un médecin au Sénégal et commander votre rendez-vous en toute confiance.</p>
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
                <p class="text-secondary mb-3">Recevez des alertes de disponibilité et des conseils santé chaque semaine.</p>
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
