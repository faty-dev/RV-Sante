<?php
require_once 'config.php';
startSecureSession();
$stmt = $pdo->query('SELECT * FROM doctors ORDER BY rating DESC LIMIT 6');
$topDoctors = $stmt->fetchAll();
$specialties = [
    'Cardiologie',
    'Dermatologie',
    'Gastro-entérologie',
    'Neurologie',
    'Pneumologie',
    'Rhumatologie',
    'Pédiatrie',
    'Gynécologie-Obstétrique',
    'Psychiatrie',
    'Psychologie clinique',
    'Aide psychologique',
    'Chirurgie générale',
    'Chirurgie viscérale'
];

$specialtyDetails = [
    'Cardiologie' => 'Soins et suivi du cœur, hypertension, et maladies cardiovasculaires.',
    'Dermatologie' => 'Traitement des affections cutanées et soins de la peau.',
    'Gastro-entérologie' => 'Prise en charge des troubles digestifs et du système gastro-intestinal.',
    'Neurologie' => 'Suivi des troubles du système nerveux et des migraines.',
    'Pneumologie' => 'Soins des maladies respiratoires et suivi pulmonaire.',
    'Rhumatologie' => 'Traitement des douleurs articulaires et des affections musculo-squelettiques.',
    'Pédiatrie' => 'Suivi de santé des enfants et accompagnement pédiatrique.',
    'Gynécologie-Obstétrique' => 'Suivi de grossesse, santé féminine et examens spécialisés.',
    'Psychiatrie' => 'Accompagnement des troubles de l\'humeur, de l\'anxiété et du stress.',
    'Psychologie clinique' => 'Consultations psychologiques et soutien psychothérapeutique.',
    'Aide psychologique' => 'Soutien émotionnel et accompagnement en situation difficile.',
    'Chirurgie générale' => 'Interventions chirurgicales pour les urgences et les opérations programmées.',
    'Chirurgie viscérale' => 'Chirurgie abdominale et prise en charge des organes internes.'
];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RV Santé</title>
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
                <li class="nav-item"><a class="nav-link active" href="index.php#accueil">Accueil</a></li>
                <li class="nav-item"><a class="nav-link" href="doctors.php">Trouver un médecin</a></li>
                <li class="nav-item"><a class="nav-link" href="appointments.php">Mes rendez-vous</a></li>
                <li class="nav-item"><a class="nav-link" href="#disponibilites">Disponibilités hospitalière</a></li>
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
<main>
    <section id="accueil" class="hero-section position-relative overflow-hidden text-white">
        <div class="hero-shape"></div>
        <div class="container py-5">
            <div class="row align-items-center gy-4">
                <div class="col-lg-6">
                    <span class="eyebrow">VOTRE SANTÉ, NOTRE PRIORITÉ</span>
                    <h1 class="display-5 fw-bold">Prenez rendez-vous avec les meilleurs médecins</h1>
                    <p class="lead text-secondary">Accédez à plus de 2 000 professionnels de santé, vérifiez leurs disponibilités en temps réel et confirmez votre rendez-vous en quelques clics.</p>
                    <form class="hero-search p-3 rounded-4 bg-white shadow-sm" method="get" action="doctors.php">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-5">
                                <label class="form-label visually-hidden" for="term">Spécialité, médecin, symptôme</label>
                                <input id="term" name="term" type="search" class="form-control form-control-lg" placeholder="Spécialité, médecin, symptôme">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label visually-hidden" for="city">Ville ou code postal</label>
                                <input id="city" name="city" type="search" class="form-control form-control-lg" placeholder="Ville ou code postal">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-dark btn-lg">Recherche</button>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="col-lg-6 text-center">
                    <div class="hero-illustration mb-4 rounded-4 overflow-hidden shadow-lg">
                        <img src="img/doctors/FATOU BA Nutritionniste.jpeg" alt="Dr. Fatou Bâ - Nutritionniste" class="img-fluid w-100">
                    </div>
                    <div class="hero-card p-4 rounded-4 shadow-lg">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <p class="mb-1 text-uppercase text-secondary small">Spécialité en vedette</p>
                                <h2 class="h5 mb-0">Dr. Fatou Bâ</h2>
                            </div>
                            <span class="badge rounded-pill bg-success">4.9</span>
                        </div>
                        <p class="text-muted mb-3">Nutritionniste</p>
                        <div class="d-flex gap-2 flex-wrap justify-content-center">
                            <span class="badge bg-light text-dark">Dakar</span>
                            <span class="badge bg-light text-dark">Santé globale</span>
                            <span class="badge bg-light text-dark">Disponible</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-5 gy-3">
                <div class="col-12">
                    <div class="category-list d-flex flex-wrap gap-2">
                        <?php foreach ($specialties as $item): ?>
                            <a href="doctors.php?term=<?php echo urlencode($item); ?>" class="badge btn btn-outline-light btn-sm rounded-pill px-3 py-2"><?php echo htmlspecialchars($item); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section bg-soft py-5">
        <div class="container">
            <div class="text-center mb-5">
                <span class="eyebrow text-primary">Nos services</span>
                <h2 class="fw-bold">Services médicaux disponibles</h2>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="service-card p-4 rounded-4 h-100 text-white">
                        <div class="service-icon">🩺</div>
                        <h5>Consultation générale</h5>
                        <p>Accédez à des médecins généralistes pour un diagnostic rapide et un suivi personnalisé.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card p-4 rounded-4 h-100 text-white">
                        <div class="service-icon">🚑</div>
                        <h5>Urgences</h5>
                        <p>Réservez un créneau prioritaire pour les situations urgentes et les consultations rapides.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card p-4 rounded-4 h-100 text-white">
                        <div class="service-icon">💻</div>
                        <h5>Téléconsultation</h5>
                        <p>Consultez un spécialiste à distance depuis votre domicile, sans attendre.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card p-4 rounded-4 h-100 text-white">
                        <div class="service-icon">📋</div>
                        <h5>Suivi médical</h5>
                        <p>Suivez vos consultations, renouvellement d’ordonnances et conseils en continu.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card p-4 rounded-4 h-100 text-white">
                        <div class="service-icon">🤰</div>
                        <h5>Santé maternelle</h5>
                        <p>Bénéficiez d’un suivi dédié pour la grossesse et la santé de la femme.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card p-4 rounded-4 h-100 text-white">
                        <div class="service-icon">🧬</div>
                        <h5>Examens spécialisés</h5>
                        <p>Accédez aux spécialistes pour des examens plus pointus et des diagnostics précis.</p>
                    </div>
                </div>
            </div>
            <div class="row g-4 mt-4">
                <div class="col-md-4">
                    <div class="image-card rounded-4 overflow-hidden shadow-sm">
                        <img src="img/groupe medical.jpg" alt="Équipe médicale" class="img-fluid w-100">
                        <div class="image-card-body p-3 bg-dark bg-opacity-75 text-white">
                            <h5 class="mb-1">Equipe de soins</h5>
                            <p class="mb-0 small">Rencontrez une équipe dédiée à votre bien-être.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="image-card rounded-4 overflow-hidden shadow-sm">
                        <img src="img/teleconsultation.jpg" alt="Consultation en ligne" class="img-fluid w-100">
                        <div class="image-card-body p-3 bg-dark bg-opacity-75 text-white">
                            <h5 class="mb-1">Téléconsultation</h5>
                            <p class="mb-0 small">Suivez vos consultations directement depuis chez vous.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="image-card rounded-4 overflow-hidden shadow-sm">
                        <img src="img/pediatrie.jpg" alt="Soins de proximité" class="img-fluid w-100">
                        <div class="image-card-body p-3 bg-dark bg-opacity-75 text-white">
                            <h5 class="mb-1">Soins proches de vous</h5>
                            <p class="mb-0 small">Des rendez-vous rapides à Dakar et dans toute la région.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section py-5">
        <div class="container">
            <div class="text-center mb-5">
                <span class="eyebrow text-primary">Témoignages</span>
                <h2 class="fw-bold">Ils nous font confiance</h2>
                <p class="text-secondary">Découvrez les retours de patients et de familles qui utilisent RV Santé pour leurs rendez-vous.</p>
            </div>
            <div id="testimonialCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <div class="testimonial-card rounded-4 p-4 shadow-sm mx-auto" style="max-width: 720px;">
                            <div class="d-flex align-items-center mb-3">
                                <div class="testimonial-avatar me-3">AB</div>
                                <div>
                                    <h6 class="mb-0">Aïcha Barry</h6>
                                    <small class="text-secondary">Mère de famille, Dakar</small>
                                </div>
                            </div>
                            <p class="mb-3">"J'ai trouvé un médecin rapidement et la prise de rendez-vous s'est faite en quelques clics. Un service pratique et rassurant."</p>
                            <div><span class="text-warning">★ ★ ★ ★ ★</span></div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <div class="testimonial-card rounded-4 p-4 shadow-sm mx-auto" style="max-width: 720px;">
                            <div class="d-flex align-items-center mb-3">
                                <div class="testimonial-avatar me-3">MD</div>
                                <div>
                                    <h6 class="mb-0">Mamadou Diop</h6>
                                    <small class="text-secondary">Ingénieur, Thiès</small>
                                </div>
                            </div>
                            <p class="mb-3">"La recherche par spécialité et par ville est très pratique. J'ai pu prendre un rendez-vous le jour même."</p>
                            <div><span class="text-warning">★ ★ ★ ★ ☆</span></div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <div class="testimonial-card rounded-4 p-4 shadow-sm mx-auto" style="max-width: 720px;">
                            <div class="d-flex align-items-center mb-3">
                                <div class="testimonial-avatar me-3">SF</div>
                                <div>
                                    <h6 class="mb-0">Seynabou Faye</h6>
                                    <small class="text-secondary">Étudiante, Saint-Louis</small>
                                </div>
                            </div>
                            <p class="mb-3">"Le site est clair et moderne. J'apprécie le suivi des rendez-vous et les profils des médecins."</p>
                            <div><span class="text-warning">★ ★ ★ ★ ★</span></div>
                        </div>
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Précédent</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#testimonialCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Suivant</span>
                </button>
            </div>
        </div>
    </section>

    <section class="section bg-light py-5">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <span class="eyebrow text-primary">Nos médecins</span>
                    <h2 class="h3 fw-bold">Médecins les mieux notés</h2>
                </div>
                <a href="doctors.php" class="text-decoration-none">Voir tous les médecins →</a>
            </div>
            <div class="row g-4">
                <?php if (!empty($topDoctors)): ?>
                    <?php foreach ($topDoctors as $doctor): ?>
                        <div class="col-md-6 col-xl-3">
                            <div class="card shadow-sm h-100 overflow-hidden">
                                <?php $doctorPhoto = resolveDoctorPhotoUrl($doctor['photo_url'] ?? ''); ?>
                                <img src="<?php echo htmlspecialchars($doctorPhoto); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($doctor['name']); ?>">
                                <div class="card-body">
                                    <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($doctor['specialty']); ?></span>
                                    <h5 class="card-title"><?php echo htmlspecialchars($doctor['name']); ?></h5>
                                    <p class="card-text text-muted mb-2"><?php echo htmlspecialchars($doctor['city']); ?> · <?php echo htmlspecialchars($doctor['experience']); ?> ans</p>
                                    <p class="card-text mb-2 small">Disponibilité : <?php echo htmlspecialchars($doctor['availability']); ?></p>
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
                <?php else: ?>
                    <div class="col-12"><p>Aucun médecin trouvé pour le moment.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section id="disponibilites" class="section py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6">
                    <h2 class="fw-bold">Disponibilités hospitalières en temps réel</h2>
                    <p class="disponibilites-intro">Consultez les créneaux disponibles, réservez un rendez-vous et recevez une confirmation immédiate pour les services de consultation, urgence et suivi.</p>
                    <ul class="list-unstyled">
                        <li class="mb-3"><strong>Plus de 2 000 professionnels</strong> accessibles partout au Sénégal.</li>
                        <li class="mb-3"><strong>Agenda optimisé</strong> pour réduire votre temps d'attente.</li>
                        <li class="mb-3"><strong>Notifications</strong> par email et SMS pour vos rendez-vous.</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <div class="p-4 rounded-4 shadow-sm bg-white">
                        <h3 class="h5 mb-3">Comment ça marche</h3>
                        <div class="d-flex gap-3 mb-3">
                            <div class="step-number">1</div>
                            <div>
                                <h6>Choisissez un professionnel</h6>
                                <p class="mb-0 text-muted">Recherchez par spécialité, symptôme ou localisation.</p>
                            </div>
                        </div>
                        <div class="d-flex gap-3 mb-3">
                            <div class="step-number">2</div>
                            <div>
                                <h6>Réservez votre créneau</h6>
                                <p class="mb-0 text-muted">Sélectionnez l'horaire qui vous convient et confirmez en ligne.</p>
                            </div>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="step-number">3</div>
                            <div>
                                <h6>Recevez votre confirmation</h6>
                                <p class="mb-0 text-muted">Un email de rappel et les informations de rendez-vous vous sont envoyés immédiatement.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
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
                <p class="footer-copy text-secondary">Plateforme sénégalaise pour trouver un médecin, réserver un rendez-vous et gérer facilement votre santé.</p>
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
                <p class="text-secondary mb-3">Recevez les dernières disponibilités et conseils santé directement par email.</p>
                <form class="footer-newsletter d-flex flex-column flex-sm-row gap-2" action="#" method="post">
                    <input type="email" class="form-control form-control-sm" placeholder="Votre adresse email">
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
