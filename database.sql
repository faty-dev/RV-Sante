-- Base de données RV Santé
CREATE DATABASE IF NOT EXISTS rv_sante CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rv_sante;

CREATE TABLE IF NOT EXISTS doctors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    specialty VARCHAR(255) NOT NULL,
    city VARCHAR(255) NOT NULL,
    experience INT UNSIGNED NOT NULL,
    availability VARCHAR(255) NOT NULL,
    rating DECIMAL(2,1) NOT NULL,
    fee INT UNSIGNED NOT NULL DEFAULT 0,
    photo_url TEXT NOT NULL,
    summary TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('patient','doctor','admin') NOT NULL DEFAULT 'patient',
    specialty VARCHAR(255) DEFAULT NULL,
    city VARCHAR(255) DEFAULT NULL,
    experience INT UNSIGNED DEFAULT NULL,
    fee INT UNSIGNED DEFAULT NULL,
    doctor_profile_id INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_profile_id) REFERENCES doctors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (name, email, phone, password, role, specialty, city, experience, fee, doctor_profile_id) VALUES
('Admin RV Santé', '+221770000001', '+221770000001', '$2y$10$3Hg/07Fht1VdiL2gXT4K3OGlQHJZtsa2uzCsm3obVwJFv0gr79b3.', 'admin', NULL, NULL, NULL, NULL, NULL),
('Patient Test', 'patient@rvsante.sn', '+221770000002', '$2y$10$78PhE2ZUAs/WWJ0Y9XpoW.4GNNjp5KkFoge68bw9G.m2rMpRdRZgm', 'patient', NULL, NULL, NULL, NULL, NULL),
('Dr. Samba Ndiaye', 'doctor@rvsante.sn', '+221770000003', '$2y$10$MOOaJrkN/EzTf8z5MTj7muKNmOpvgDWwQj7040bhL4Ztw2xAogpge', 'doctor', 'Cardiologue', 'Dakar', 15, 30000, NULL);

CREATE TABLE IF NOT EXISTS appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT UNSIGNED NOT NULL,
    patient_id INT UNSIGNED DEFAULT NULL,
    patient_name VARCHAR(255) NOT NULL,
    patient_email VARCHAR(255) DEFAULT NULL,
    patient_phone VARCHAR(20) DEFAULT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending','confirmed','canceled','rescheduled') NOT NULL DEFAULT 'pending',
    doctor_response VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (
-- Tables pour la communication patient-médecin
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS symptom_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL,
    patient_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    caption VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS appointment_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    sender_role ENUM('patient','doctor','admin') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO doctors (name, specialty, city, experience, availability, rating, fee, photo_url, summary) VALUES
('Dr. Aissatou Diop', 'Médecine générale', 'Dakar', 14, 'Disponible aujourd\'hui', 4.9, 25000, 'img/1.jpg', 'Médecin généraliste avec une forte expérience en médecine familiale et prévention.'),
('Dr. Mamadou Ndiaye', 'Cardiologie', 'Thiès', 11, 'Disponible cette semaine', 4.8, 32000, 'img/2.jpg', 'Cardiologue spécialisé en suivi du cœur et prise en charge des maladies cardiovasculaires.'),
('Dr. Mariama Fall', 'Dentiste', 'Saint-Louis', 9, 'Disponible demain', 4.7, 22000, 'img/3.jpeg', 'Dentiste experte en soins conservateurs et esthétique dentaire pour tous les âges.'),
('Dr. Ousmane Sarr', 'Dermatologie', 'Rufisque', 12, 'Consultations samedi', 4.6, 28000, 'img/4.jpg', 'Dermatologue spécialisé en pathologies cutanées et traitement des peaux sensibles.'),
('Dr. Fatou Ndiaye', 'Nutritionniste', 'Dakar', 10, 'Disponible demain', 4.8, 21000, 'img/5.jpg', 'Nutritionniste dédiée aux bilans alimentaires et à l'accompagnement nutritionnel personnalisé.'),
('Dr. Seynabou Diouf', 'Ophtalmologue', 'Mbour', 13, 'Disponible cette semaine', 4.7, 26000, 'img/6.jpg', 'Ophtalmologue spécialisée dans la santé visuelle et les consultations de vue.'),
('Dr. Ibrahim Cissé', 'Endocrinologue', 'Kaolack', 15, 'Consultations vendredi', 4.9, 33000, 'img/1.jpg', 'Endocrinologue expert en diabète, hormones et métabolisme.'),
('Dr. Awa Faye', 'Neurologue', 'Dakar', 12, 'Disponible aujourd\'hui', 4.8, 30000, 'img/2.jpg', 'Neurologue spécialisée en migraines, troubles du sommeil et pathologies nerveuses.'),
('Dr. Aïssatou Mendy', 'Gastro-entérologie', 'Dakar', 11, 'Disponible cette semaine', 4.7, 31000, 'img/3.jpeg', 'Gastro-entérologue spécialisée dans les maladies digestives et le suivi nutritionnel.'),
('Dr. Mamadou Ba', 'Pneumologie', 'Thiès', 13, 'Consultations lundi', 4.8, 29000, 'img/4.jpg', 'Pneumologue expert en respiratoire et prise en charge des maladies pulmonaires.'),
('Dr. Fatou Diouf', 'Rhumatologie', 'Saint-Louis', 10, 'Disponible demain', 4.6, 27000, 'img/5.jpg', 'Rhumatologue dédiée aux douleurs articulaires et aux pathologies inflammatoires.'),
('Dr. Ndeye Coumba', 'Pédiatrie', 'Dakar', 9, 'Disponible aujourd\'hui', 4.9, 24000, 'img/6.jpg', 'Pédiatre bienveillante accompagnant les enfants et leurs familles.'),
('Dr. Khadija Fall', 'Gynécologie-Obstétrique', 'Mbour', 14, 'Consultations mercredi', 4.8, 32000, 'img/7.jpg', 'Gynécologue-obstétricienne spécialisée en suivi de grossesse et santé féminine.'),
('Dr. Boubacar Sarr', 'Psychiatrie', 'Dakar', 12, 'Disponible cette semaine', 4.7, 28000, 'img/1.jpg', 'Psychiatre engagé dans le soutien des troubles de l\'humeur et de l\'anxiété.'),
('Dr. Aminata Diop', 'Psychologie clinique', 'Kaolack', 10, 'Disponible demain', 4.8, 26000, 'img/2.jpg', 'Psychologue clinique spécialisée en aide psychologique individuelle et de couple.'),
('Dr. Ousmane Ndiaye', 'Chirurgie générale', 'Rufisque', 16, 'Consultations vendredi', 4.9, 34000, 'img/3.jpeg', 'Chirurgien général assurant des interventions en urgence et en chirurgie programmée.'),
('Dr. Mariam Kane', 'Chirurgie viscérale', 'Dakar', 15, 'Disponible cette semaine', 4.8, 35000, 'img/4.jpg', 'Chirurgienne viscérale experte en prise en charge abdominale et digestive.');