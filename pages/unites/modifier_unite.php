<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/classes/autoload.php';
require_once '../../includes/logs.php';
require_once '../../includes/csrf.php';
require_once '../../includes/validator.php';

// Vérification du rôle admin
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom'] . ' ' . $user['prenom']) : '';
$role = $user['role'] ?? '';

if ($role !== 'ADMIN') {
    header('Location: unites.php');
    exit();
}

$refMgr = new ReferenceManager($pdo);
$errors = [];
$success = '';

// Récupérer l'ID de l'unité
$uniteId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$unite = $refMgr->getUniteById($uniteId);

if (!$unite) {
    header('Location: unites.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        CSRF::verify();

        $validated = Validator::make($_POST)
            ->required('code', 'Le code est requis')
            ->max('code', 20, 'Le code ne doit pas dépasser 20 caractères')
            ->required('nom', 'Le nom est requis')
            ->max('nom', 200, 'Le nom ne doit pas dépasser 200 caractères')
            ->required('type', 'Le type est requis')
            ->in('type', ['ARMEE', 'GENDARMERIE', 'POLICE', 'AUTRES'], 'Type invalide')
            ->validate();

        // Vérifier que le code n'existe pas déjà (sauf pour cette unité)
        $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM unites WHERE code = ? AND id != ?");
        $stmt->execute([$validated['code'], $uniteId]);
        if ((int)$stmt->fetch()['nb'] > 0) {
            $errors['code'] = "Ce code existe déjà.";
        }

        if (empty($errors)) {
            $success = $refMgr->updateUnite($uniteId, [
                'code' => strtoupper($validated['code']),
                'nom' => $validated['nom'],
                'type' => $validated['type'],
                'localisation' => $_POST['localisation'] ?? null
            ]);

            if ($success) {
                log_activity($pdo, $_SESSION['user_id'], 'Modification unité', "Unité: {$validated['nom']} (ID: $uniteId)");
                $success = "Unité modifiée avec succès.";

                // Recharger l'unité
                $unite = $refMgr->getUniteById($uniteId);
            } else {
                $errors['general'] = "Une erreur s'est produite lors de la modification.";
            }
        }
    } catch (ValidationException $e) {
        $errors = json_decode($e->getMessage(), true);
    } catch (Exception $e) {
        $errors['general'] = "Une erreur s'est produite.";
        error_log($e->getMessage());
    }
}

// Vérifier si l'unité est utilisée
$stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM detenus WHERE unite_id = ? AND is_deleted = FALSE");
$stmt->execute([$uniteId]);
$nbDetenus = (int)$stmt->fetch()['nb'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Modifier une Unité - Système Militaire</title>
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
                        <h3 class="fw-bold mb-3">
                            <i class="fas fa-edit me-2"></i>Modifier une Unité
                        </h3>
                        <ul class="breadcrumbs mb-3">
                            <li class="nav-home">
                                <a href="../dash/dashboard.php"><i class="fas fa-home"></i></a>
                            </li>
                            <li class="separator"><i class="fas fa-chevron-right"></i></li>
                            <li class="nav-item">
                                <a href="unites.php">Unités</a>
                            </li>
                            <li class="separator"><i class="fas fa-chevron-right"></i></li>
                            <li class="nav-item">Modifier</li>
                        </ul>
                    </div>

                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($errors['general']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($nbDetenus > 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Information :</strong> Cette unité est actuellement utilisée par
                        <strong><?= $nbDetenus ?></strong> détenu(s).
                        Les modifications seront appliquées à tous les détenus de cette unité.
                    </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title">Informations de l'Unité</h4>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <?= CSRF::field() ?>

                                        <!-- Code -->
                                        <div class="form-group mb-3">
                                            <label for="code" class="form-label">
                                                Code de l'Unité <span class="text-danger">*</span>
                                            </label>
                                            <input type="text"
                                                class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>"
                                                id="code" name="code" value="<?= htmlspecialchars($unite['code']) ?>"
                                                maxlength="20" style="text-transform: uppercase;" required>
                                            <?php if (isset($errors['code'])): ?>
                                            <div class="invalid-feedback">
                                                <?= htmlspecialchars($errors['code'][0] ?? $errors['code']) ?>
                                            </div>
                                            <?php endif; ?>
                                            <small class="form-text text-muted">
                                                <i class="fas fa-info-circle"></i>
                                                Ex: 1RIA, GSPR, BAB (max 20 caractères)
                                            </small>
                                        </div>

                                        <!-- Nom -->
                                        <div class="form-group mb-3">
                                            <label for="nom" class="form-label">
                                                Nom de l'Unité <span class="text-danger">*</span>
                                            </label>
                                            <input type="text"
                                                class="form-control <?= isset($errors['nom']) ? 'is-invalid' : '' ?>"
                                                id="nom" name="nom" value="<?= htmlspecialchars($unite['nom']) ?>"
                                                maxlength="200" required>
                                            <?php if (isset($errors['nom'])): ?>
                                            <div class="invalid-feedback">
                                                <?= htmlspecialchars($errors['nom'][0] ?? $errors['nom']) ?>
                                            </div>
                                            <?php endif; ?>
                                            <small class="form-text text-muted">
                                                <i class="fas fa-info-circle"></i>
                                                Ex: 1er Régiment d'Infanterie d'Abidjan (max 200 caractères)
                                            </small>
                                        </div>

                                        <!-- Type -->
                                        <div class="form-group mb-3">
                                            <label for="type" class="form-label">
                                                Type d'Unité <span class="text-danger">*</span>
                                            </label>
                                            <select
                                                class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>"
                                                id="type" name="type" required>
                                                <option value="">-- Sélectionner un type --</option>
                                                <option value="ARMEE"
                                                    <?= $unite['type'] === 'ARMEE' ? 'selected' : '' ?>>
                                                    Armée
                                                </option>
                                                <option value="GENDARMERIE"
                                                    <?= $unite['type'] === 'GENDARMERIE' ? 'selected' : '' ?>>
                                                    Gendarmerie
                                                </option>
                                                <option value="POLICE"
                                                    <?= $unite['type'] === 'POLICE' ? 'selected' : '' ?>>
                                                    Police
                                                </option>
                                                <option value="AUTRES"
                                                    <?= $unite['type'] === 'AUTRES' ? 'selected' : '' ?>>
                                                    Autres
                                                </option>
                                            </select>
                                            <?php if (isset($errors['type'])): ?>
                                            <div class="invalid-feedback">
                                                <?= htmlspecialchars($errors['type'][0] ?? $errors['type']) ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Localisation -->
                                        <div class="form-group mb-3">
                                            <label for="localisation" class="form-label">
                                                Localisation
                                            </label>
                                            <input type="text" class="form-control" id="localisation"
                                                name="localisation"
                                                value="<?= htmlspecialchars($unite['localisation'] ?? '') ?>"
                                                maxlength="200">
                                            <small class="form-text text-muted">
                                                <i class="fas fa-info-circle"></i>
                                                Ville ou région où se trouve l'unité (facultatif)
                                            </small>
                                        </div>

                                        <!-- Informations supplémentaires -->
                                        <div class="alert alert-light border">
                                            <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Informations</h6>
                                            <ul class="mb-0">
                                                <li>Date de création :
                                                    <strong><?= date('d/m/Y à H:i', strtotime($unite['created_at'])) ?></strong>
                                                </li>
                                                <li>Statut : <span
                                                        class="badge badge-<?= $unite['is_active'] ? 'success' : 'secondary' ?>">
                                                        <?= $unite['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span></li>
                                                <li>Détenus dans cette unité : <strong><?= $nbDetenus ?></strong></li>
                                            </ul>
                                        </div>

                                        <!-- Boutons -->
                                        <div class="form-group mt-4">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                            </button>
                                            <a href="unites.php" class="btn btn-secondary">
                                                <i class="fas fa-times me-2"></i>Annuler
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Colonne droite: Aide et Statistiques -->
                        <div class="col-md-4">
                            <div class="card card-info">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-question-circle me-2"></i>Aide
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <h6 class="fw-bold">Types d'Unités</h6>
                                    <ul class="small">
                                        <li><strong>Armée :</strong> Unités militaires terrestres, aériennes ou navales
                                        </li>
                                        <li><strong>Gendarmerie :</strong> Brigades et unités de gendarmerie</li>
                                        <li><strong>Police :</strong> Commissariats et unités de police</li>
                                        <li><strong>Autres :</strong> Services spéciaux, unités administratives</li>
                                    </ul>

                                    <h6 class="fw-bold mt-3">Impact des Modifications</h6>
                                    <p class="small">
                                        Les modifications seront immédiatement appliquées à tous
                                        les détenus appartenant à cette unité. Vérifiez bien avant de valider.
                                    </p>

                                    <div class="alert alert-warning mt-3">
                                        <small>
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <strong>Attention :</strong> Changer le type d'unité peut
                                            affecter les statistiques et rapports existants.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistiques de l'unité -->
                            <div class="card card-secondary mt-3">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-chart-bar me-2"></i>Statistiques
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Détenus actifs</span>
                                        <strong><?= $nbDetenus ?></strong>
                                    </div>

                                    <?php if ($nbDetenus > 0): ?>
                                    <a href="../detenus/detenus.php?unite_id=<?= $uniteId ?>"
                                        class="btn btn-sm btn-outline-primary w-100">
                                        <i class="fas fa-eye me-1"></i>Voir les détenus
                                    </a>
                                    <?php endif; ?>

                                    <!-- Répartition par grade dans l'unité -->
                                    <?php
                                    $stmtGrades = $pdo->prepare("
                                        SELECT g.libelle, COUNT(*) as nb
                                        FROM detenus d
                                        JOIN grades g ON d.grade_id = g.id
                                        WHERE d.unite_id = ? AND d.is_deleted = FALSE
                                        GROUP BY g.libelle
                                        ORDER BY nb DESC
                                        LIMIT 5
                                    ");
                                    $stmtGrades->execute([$uniteId]);
                                    $gradesStats = $stmtGrades->fetchAll();

                                    if (!empty($gradesStats)):
                                    ?>
                                    <hr class="my-3">
                                    <h6 class="fw-bold small">Répartition par grade</h6>
                                    <?php foreach ($gradesStats as $gradeStat): ?>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span><?= htmlspecialchars($gradeStat['libelle']) ?></span>
                                        <strong><?= $gradeStat['nb'] ?></strong>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Historique récent -->
                            <?php
                            $stmtHistorique = $pdo->prepare("
                                SELECT d.nom_complet, d.created_at
                                FROM detenus d
                                WHERE d.unite_id = ? AND d.is_deleted = FALSE
                                ORDER BY d.created_at DESC
                                LIMIT 5
                            ");
                            $stmtHistorique->execute([$uniteId]);
                            $historique = $stmtHistorique->fetchAll();

                            if (!empty($historique)):
                            ?>
                            <div class="card card-light mt-3">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-history me-2"></i>Derniers Enregistrements
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled small mb-0">
                                        <?php foreach ($historique as $item): ?>
                                        <li class="mb-2">
                                            <i class="fas fa-user-circle text-muted me-1"></i>
                                            <?= htmlspecialchars($item['nom_complet']) ?>
                                            <br>
                                            <small class="text-muted">
                                                <?= date('d/m/Y', strtotime($item['created_at'])) ?>
                                            </small>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../../requires/script.php'; ?>

    <script>
    // Convertir le code en majuscules automatiquement
    document.getElementById('code').addEventListener('input', function(e) {
        this.value = this.value.toUpperCase();
    });

    // Confirmation si l'unité est utilisée
    <?php if ($nbDetenus > 0): ?>
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!confirm(
                'Cette unité est utilisée par <?= $nbDetenus ?> détenu(s).\n\nLes modifications seront appliquées à tous.\n\nVoulez-vous continuer ?'
            )) {
            e.preventDefault();
        }
    });
    <?php endif; ?>

    // Auto-dismiss des alertes après 5 secondes
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert-success');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    </script>
</body>

</html>