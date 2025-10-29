<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/classes/autoload.php';
require_once '../../includes/logs.php';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom'] . ' ' . $user['prenom']) : '';

$refMgr = new ReferenceManager($pdo);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: lieux_detention.php');
    exit();
}

$lieuId = (int)$_GET['id'];
$lieu = $refMgr->getLieuDetentionById($lieuId);

if (!$lieu) {
    header('Location: lieux_detention.php');
    exit();
}

$capaciteInfo = $refMgr->getCapaciteDisponible($lieuId);
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $required = ['code', 'nom', 'type'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Le champ " . ucfirst($field) . " est obligatoire.";
        }
    }

    // Vérifier code unique (sauf actuel)
    if (!empty($_POST['code'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM lieux_detention WHERE code = ? AND id != ?");
        $stmt->execute([$_POST['code'], $lieuId]);
        if ((int)$stmt->fetch()['nb'] > 0) {
            $errors[] = "Ce code existe déjà.";
        }
    }

    if (empty($errors)) {
        $data = [
            'code' => strtoupper(trim($_POST['code'])),
            'nom' => trim($_POST['nom']),
            'type' => $_POST['type'],
            'capacite' => !empty($_POST['capacite']) ? (int)$_POST['capacite'] : null,
            'adresse' => trim($_POST['adresse']) ?: null,
            'ville' => trim($_POST['ville']) ?: null,
            'telephone' => trim($_POST['telephone']) ?: null
        ];

        $updated = $refMgr->updateLieuDetention($lieuId, $data);

        if ($updated) {
            log_activity($pdo, $_SESSION['user_id'], 'Modification lieu', "Lieu ID: $lieuId - " . $data['nom']);
            $success = "Lieu modifié avec succès !";
            $lieu = $refMgr->getLieuDetentionById($lieuId);
            header("refresh:2;url=lieux_detention.php");
        } else {
            $errors[] = "Erreur lors de la modification.";
        }
    }
}

// Compter condamnations
$stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM condamnations WHERE lieu_detention_id = ? AND statut = 'EN_COURS'");
$stmt->execute([$lieuId]);
$nbCondamnations = (int)$stmt->fetch()['nb'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Modifier Lieu - <?= htmlspecialchars($lieu['nom']) ?></title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
</head>

<body>
    <div class="wrapper">
        <?php include '../../requires/sidebar.php'; ?>
        <div class="main-panel">
            <?php include '../../requires/main-header.php'; ?>
            <div class="container">
                <div class="page-inner">
                    <div class="page-header">
                        <h3 class="fw-bold mb-3"><i class="fas fa-edit me-2"></i>Modifier le Lieu</h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home"><a href="../dash/dashboard.php"><i class="fas fa-home"></i></a></li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item"><a href="lieux_detention.php">Lieux</a></li>
                            <li class="separator"><i class="fas fa-arrow-right"></i></li>
                            <li class="nav-item">Modifier</li>
                        </ul>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <strong><i class="fas fa-exclamation-circle me-2"></i>Erreurs :</strong>
                            <ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?></ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <strong><i class="fas fa-check-circle me-2"></i></strong><?= htmlspecialchars($success) ?>
                            <p class="mb-0"><i class="fas fa-spinner fa-spin me-2"></i>Redirection...</p>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-2"><strong><?= htmlspecialchars($lieu['code']) ?></strong> -
                                    <?= htmlspecialchars($lieu['nom']) ?></h5>
                                <p class="mb-0">
                                    <span
                                        class="badge badge-<?= $lieu['type'] === 'PRISON_MILITAIRE' ? 'primary' : 'info' ?>">
                                        <?= str_replace('_', ' ', $lieu['type']) ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6 text-end">
                                <p class="mb-1">Détenus: <strong><?= $capaciteInfo['nb_detenus'] ?></strong> /
                                    <?= $capaciteInfo['capacite_max'] ?></p>
                                <p class="mb-0">Occupation: <strong><?= $capaciteInfo['taux_occupation'] ?>%</strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title"><i class="fas fa-info-circle me-2"></i>Informations</h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Code <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" name="code" class="form-control" required
                                                    style="text-transform: uppercase;"
                                                    value="<?= htmlspecialchars($lieu['code']) ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Type <span
                                                        class="text-danger">*</span></label>
                                                <select name="type" class="form-select" required>
                                                    <option value="PRISON_MILITAIRE"
                                                        <?= $lieu['type'] === 'PRISON_MILITAIRE' ? 'selected' : '' ?>>
                                                        Prison Militaire</option>
                                                    <option value="PRISON_CIVILE"
                                                        <?= $lieu['type'] === 'PRISON_CIVILE' ? 'selected' : '' ?>>
                                                        Prison Civile</option>
                                                    <option value="MAISON_ARRET"
                                                        <?= $lieu['type'] === 'MAISON_ARRET' ? 'selected' : '' ?>>Maison
                                                        d'Arrêt</option>
                                                    <option value="AUTRES"
                                                        <?= $lieu['type'] === 'AUTRES' ? 'selected' : '' ?>>Autres
                                                    </option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                                            <input type="text" name="nom" class="form-control" required
                                                value="<?= htmlspecialchars($lieu['nom']) ?>">
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Capacité</label>
                                                <input type="number" name="capacite" class="form-control" min="0"
                                                    value="<?= htmlspecialchars($lieu['capacite'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Ville</label>
                                                <input type="text" name="ville" class="form-control"
                                                    value="<?= htmlspecialchars($lieu['ville'] ?? '') ?>">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Adresse</label>
                                            <textarea name="adresse" class="form-control"
                                                rows="2"><?= htmlspecialchars($lieu['adresse'] ?? '') ?></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Téléphone</label>
                                            <input type="tel" name="telephone" class="form-control"
                                                value="<?= htmlspecialchars($lieu['telephone'] ?? '') ?>">
                                        </div>

                                        <?php if ($nbCondamnations > 0): ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Attention :</strong> Ce lieu est utilisé par
                                                <strong><?= $nbCondamnations ?></strong> condamnation(s) active(s).
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header bg-primary text-white">
                                        <h4 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Statistiques
                                        </h4>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="text-muted">Capacité Totale</label>
                                            <h4><?= $capaciteInfo['capacite_max'] ?></h4>
                                        </div>
                                        <div class="mb-3">
                                            <label class="text-muted">Détenus Actuels</label>
                                            <h4><?= $capaciteInfo['nb_detenus'] ?></h4>
                                        </div>
                                        <div class="mb-3">
                                            <label class="text-muted">Places Disponibles</label>
                                            <h4><?= $capaciteInfo['disponible'] ?></h4>
                                        </div>
                                        <div>
                                            <label class="text-muted">Taux Occupation</label>
                                            <h3
                                                class="text-<?= $capaciteInfo['taux_occupation'] >= 90 ? 'danger' : ($capaciteInfo['taux_occupation'] >= 70 ? 'warning' : 'success') ?>">
                                                <?= $capaciteInfo['taux_occupation'] ?>%
                                            </h3>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mt-3">
                                    <div class="card-header bg-info text-white">
                                        <h4 class="card-title mb-0"><i class="fas fa-history me-2"></i>Historique</h4>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-2"><strong>Créé le
                                                :</strong><br><?= date('d/m/Y H:i', strtotime($lieu['created_at'])) ?>
                                        </p>
                                        <p class="mb-0"><strong>Modifié le
                                                :</strong><br><?= date('d/m/Y H:i', strtotime($lieu['updated_at'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body text-end">
                                        <a href="lieux_detention.php" class="btn btn-secondary me-2">
                                            <i class="fas fa-times me-2"></i>Annuler
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Enregistrer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../requires/script.php'; ?>
    <script>
        document.querySelector('input[name="code"]').addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9_]/g, '');
        });
    </script>
</body>

</html>