<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/classes/autoload.php';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom'] . ' ' . $user['prenom']) : '';
$role = $user['role'] ?? '';

$detenuMgr = new DetenuManager($pdo);

// Récupérer le détenu
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: detenus.php');
    exit();
}

$detenuId = (int)$_GET['id'];
$detenu = $detenuMgr->getById($detenuId);

if (!$detenu) {
    header('Location: detenus.php');
    exit();
}

// Récupérer l'historique
$historique = $detenuMgr->getHistorique($detenuId);
$condamnations = $historique['condamnations'] ?? [];
$periodes = $historique['periodes'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Détails Détenu - <?= htmlspecialchars($detenu['nom_complet']) ?></title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
    <style>
    .profile-photo {
        width: 200px;
        height: 200px;
        object-fit: cover;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .info-label {
        font-weight: 600;
        color: #6c757d;
        font-size: 13px;
        text-transform: uppercase;
    }

    .info-value {
        font-size: 16px;
        font-weight: 500;
        color: #2c3e50;
    }

    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 8px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }

    .timeline-item {
        position: relative;
        padding-bottom: 20px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -26px;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #177dff;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px #177dff;
    }

    .stat-box {
        text-align: center;
        padding: 20px;
        border-radius: 10px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .stat-box h3 {
        font-size: 2rem;
        font-weight: 700;
        margin: 0;
    }

    .stat-box p {
        margin: 5px 0 0 0;
        opacity: 0.9;
    }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include '../../requires/sidebar.php'; ?>
        <div class="main-panel">
            <?php include '../../requires/main-header.php'; ?>
            <div class="container">
                <div class="page-inner">
                    <div class="page-header">
                        <h3 class="fw-bold mb-3">
                            <i class="fas fa-user me-2"></i>Profil du Détenu
                        </h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="../dash/dashboard.php"><i class="fas fa-home"></i></a>
                            </li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item">
                                <a href="detenus.php">Détenus</a>
                            </li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item">Détails</li>
                        </ul>
                    </div>

                    <!-- Actions rapides -->
                    <div class="d-flex justify-content-end mb-3">
                        <a href="modifier_detenu.php?id=<?= $detenu['id'] ?>" class="btn btn-warning me-2">
                            <i class="fas fa-edit me-2"></i>Modifier
                        </a>
                        <a href="detenus.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Retour à la liste
                        </a>
                    </div>

                    <div class="row">
                        <!-- Profil principal -->
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <?php if (!empty($detenu['photo_path'])): ?>
                                    <img src="../../<?= htmlspecialchars($detenu['photo_path']) ?>"
                                        class="profile-photo mb-3" alt="Photo">
                                    <?php else: ?>
                                    <div
                                        class="profile-photo mx-auto mb-3 bg-secondary d-flex align-items-center justify-content-center text-white">
                                        <i class="fas fa-user fa-4x"></i>
                                    </div>
                                    <?php endif; ?>

                                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($detenu['nom_complet']) ?></h4>
                                    <p class="text-muted mb-3">
                                        <span
                                            class="badge badge-info"><?= htmlspecialchars($detenu['grade_code']) ?></span>
                                    </p>

                                    <?php 
                                    $statutColors = [
                                        'CONDAMNE' => 'danger',
                                        'DETENTION_PROVISOIRE' => 'warning',
                                        'LIBRE' => 'success',
                                        'EVADE' => 'dark',
                                        'DECEDE' => 'secondary'
                                    ];
                                    $color = $statutColors[$detenu['statut_actuel']] ?? 'secondary';
                                    ?>
                                    <span class="badge badge-<?= $color ?> badge-lg">
                                        <?= str_replace('_', ' ', $detenu['statut_actuel']) ?>
                                    </span>

                                    <?php if ($detenu['is_multrecidiviste']): ?>
                                    <div class="alert alert-danger mt-3">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Multirécidiviste</strong>
                                    </div>
                                    <?php endif; ?>

                                    <hr>

                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="stat-box bg-primary">
                                                <h3><?= (int)$detenu['nombre_condamnations'] ?></h3>
                                                <p>Condamnations</p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="stat-box bg-info">
                                                <h3><?= (int)$detenu['age'] ?></h3>
                                                <p>Ans</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact -->
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-phone me-2"></i>Contact
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <?php if ($detenu['telephone']): ?>
                                    <p>
                                        <i class="fas fa-phone text-primary me-2"></i>
                                        <?= htmlspecialchars($detenu['telephone']) ?>
                                    </p>
                                    <?php endif; ?>

                                    <?php if ($detenu['email']): ?>
                                    <p>
                                        <i class="fas fa-envelope text-primary me-2"></i>
                                        <?= htmlspecialchars($detenu['email']) ?>
                                    </p>
                                    <?php endif; ?>

                                    <?php if ($detenu['adresse']): ?>
                                    <p>
                                        <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                        <?= nl2br(htmlspecialchars($detenu['adresse'])) ?>
                                    </p>
                                    <?php endif; ?>

                                    <?php if ($detenu['personne_contact_nom']): ?>
                                    <hr>
                                    <h6 class="fw-bold">Personne à Contacter</h6>
                                    <p class="mb-1">
                                        <strong><?= htmlspecialchars($detenu['personne_contact_nom']) ?></strong>
                                        <?php if ($detenu['personne_contact_relation']): ?>
                                        <br><small
                                            class="text-muted"><?= htmlspecialchars($detenu['personne_contact_relation']) ?></small>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($detenu['personne_contact_telephone']): ?>
                                    <p class="mb-0">
                                        <i class="fas fa-phone text-success me-2"></i>
                                        <?= htmlspecialchars($detenu['personne_contact_telephone']) ?>
                                    </p>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Informations détaillées -->
                        <div class="col-lg-8">
                            <!-- Informations générales -->
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-info-circle me-2"></i>Informations Générales
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="info-label">Matricule Détenu</p>
                                            <p class="info-value">
                                                <i class="fas fa-barcode me-2"></i>
                                                <?= htmlspecialchars($detenu['matricule']) ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="info-label">Matricule Militaire</p>
                                            <p class="info-value">
                                                <?= $detenu['matricule_militaire'] ? htmlspecialchars($detenu['matricule_militaire']) : 'N/A' ?>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="info-label">Date de Naissance</p>
                                            <p class="info-value">
                                                <?= $detenu['date_naissance'] ? date('d/m/Y', strtotime($detenu['date_naissance'])) : 'N/A' ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="info-label">Lieu de Naissance</p>
                                            <p class="info-value">
                                                <?= htmlspecialchars($detenu['lieu_naissance'] ?? 'N/A') ?></p>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="info-label">Sexe</p>
                                            <p class="info-value">
                                                <?= $detenu['sexe'] === 'M' ? 'Masculin' : 'Féminin' ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="info-label">Nationalité</p>
                                            <p class="info-value"><?= htmlspecialchars($detenu['nationalite']) ?></p>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="info-label">Situation Matrimoniale</p>
                                            <p class="info-value">
                                                <?= $detenu['situation_matrimoniale'] ? ucfirst(strtolower($detenu['situation_matrimoniale'])) : 'N/A' ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="info-label">Nombre d'Enfants</p>
                                            <p class="info-value"><?= (int)$detenu['nombre_enfants'] ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Informations militaires -->
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-shield-alt me-2"></i>Informations Militaires
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p class="info-label">Grade</p>
                                            <p class="info-value">
                                                <span
                                                    class="badge badge-info me-2"><?= htmlspecialchars($detenu['grade_code']) ?></span>
                                                <?= htmlspecialchars($detenu['grade_libelle']) ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="info-label">Unité</p>
                                            <p class="info-value"><?= htmlspecialchars($detenu['unite_nom']) ?></p>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="info-label">Date d'Incorporation</p>
                                            <p class="info-value">
                                                <?= $detenu['date_incorporation'] ? date('d/m/Y', strtotime($detenu['date_incorporation'])) : 'N/A' ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="info-label">Date d'Enregistrement</p>
                                            <p class="info-value">
                                                <?= date('d/m/Y H:i', strtotime($detenu['created_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Condamnations -->
                            <div class="card mt-3">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h4 class="card-title mb-0">
                                            <i class="fas fa-gavel me-2"></i>Condamnations
                                        </h4>
                                        <span class="badge badge-primary"><?= count($condamnations) ?></span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($condamnations)): ?>
                                    <p class="text-muted text-center py-3">Aucune condamnation enregistrée</p>
                                    <?php else: ?>
                                    <div class="timeline">
                                        <?php foreach ($condamnations as $cond): ?>
                                        <div class="timeline-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="fw-bold mb-1">
                                                        <?= htmlspecialchars($cond['infraction']) ?>
                                                        <span class="badge badge-danger ms-2">
                                                            <?= htmlspecialchars($cond['peine_valeur'] . ' ' . strtolower($cond['peine_unite'])) ?>
                                                        </span>
                                                    </h6>
                                                    <p class="text-muted mb-1">
                                                        <i class="fas fa-calendar me-2"></i>
                                                        Jugement:
                                                        <?= date('d/m/Y', strtotime($cond['date_jugement'])) ?>
                                                    </p>
                                                    <?php if ($cond['numero_dossier']): ?>
                                                    <p class="text-muted mb-1">
                                                        <i class="fas fa-folder me-2"></i>
                                                        Dossier: <?= htmlspecialchars($cond['numero_dossier']) ?>
                                                    </p>
                                                    <?php endif; ?>
                                                    <?php if ($cond['lieu_detention']): ?>
                                                    <p class="text-muted mb-0">
                                                        <i class="fas fa-map-marker-alt me-2"></i>
                                                        <?= htmlspecialchars($cond['lieu_detention']) ?>
                                                    </p>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <?php 
                                                            $statutBadge = [
                                                                'EN_COURS' => 'warning',
                                                                'TERMINEE' => 'success',
                                                                'SUSPENDUE' => 'info',
                                                                'ANNULEE' => 'secondary'
                                                            ];
                                                            $badge = $statutBadge[$cond['statut']] ?? 'secondary';
                                                            ?>
                                                    <span class="badge badge-<?= $badge ?>">
                                                        <?= str_replace('_', ' ', $cond['statut']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../requires/script.php'; ?>
</body>

</html>