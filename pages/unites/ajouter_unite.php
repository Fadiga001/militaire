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

        // Vérifier que le code n'existe pas déjà
        $stmt = $pdo->prepare("SELECT COUNT(*) as nb FROM unites WHERE code = ?");
        $stmt->execute([$validated['code']]);
        if ((int)$stmt->fetch()['nb'] > 0) {
            $errors['code'] = "Ce code existe déjà.";
        }

        if (empty($errors)) {
            $uniteId = $refMgr->createUnite([
                'code' => strtoupper($validated['code']),
                'nom' => $validated['nom'],
                'type' => $validated['type'],
                'localisation' => $_POST['localisation'] ?? null
            ]);

            if ($uniteId) {
                log_activity($pdo, $_SESSION['user_id'], 'Création unité', "Unité: {$validated['nom']} (ID: $uniteId)");
                $_SESSION['success_message'] = "Unité créée avec succès.";
                header('Location: unites.php');
                exit();
            } else {
                $errors['general'] = "Une erreur s'est produite lors de la création.";
            }
        }
    } catch (ValidationException $e) {
        $errors = json_decode($e->getMessage(), true);
    } catch (Exception $e) {
        $errors['general'] = "Une erreur s'est produite.";
        error_log($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Ajouter une Unité - Système Militaire</title>
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
                            <i class="fas fa-plus-circle me-2"></i>Ajouter une Unité
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
                            <li class="nav-item">Ajouter</li>
                        </ul>
                    </div>

                    <?php if (isset($errors['general'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($errors['general']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
                                                id="code" name="code"
                                                value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" maxlength="20"
                                                style="text-transform: uppercase;" required>
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
                                                id="nom" name="nom" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
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
                                                    <?= ($_POST['type'] ?? '') === 'ARMEE' ? 'selected' : '' ?>>
                                                    Armée
                                                </option>
                                                <option value="GENDARMERIE"
                                                    <?= ($_POST['type'] ?? '') === 'GENDARMERIE' ? 'selected' : '' ?>>
                                                    Gendarmerie
                                                </option>
                                                <option value="POLICE"
                                                    <?= ($_POST['type'] ?? '') === 'POLICE' ? 'selected' : '' ?>>
                                                    Police
                                                </option>
                                                <option value="AUTRES"
                                                    <?= ($_POST['type'] ?? '') === 'AUTRES' ? 'selected' : '' ?>>
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
                                                value="<?= htmlspecialchars($_POST['localisation'] ?? '') ?>"
                                                maxlength="200">
                                            <small class="form-text text-muted">
                                                <i class="fas fa-info-circle"></i>
                                                Ville ou région où se trouve l'unité (facultatif)
                                            </small>
                                        </div>

                                        <!-- Boutons -->
                                        <div class="form-group mt-4">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Créer l'Unité
                                            </button>
                                            <a href="unites.php" class="btn btn-secondary">
                                                <i class="fas fa-times me-2"></i>Annuler
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Colonne droite: Aide -->
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

                                    <h6 class="fw-bold mt-3">Code de l'Unité</h6>
                                    <p class="small">
                                        Le code doit être court, unique et facilement identifiable.
                                        Utilisez les abréviations officielles quand elles existent.
                                    </p>

                                    <h6 class="fw-bold mt-3">Exemples</h6>
                                    <div class="small">
                                        <div class="mb-2">
                                            <strong>1RIA</strong><br>
                                            <span class="text-muted">1er Régiment d'Infanterie d'Abidjan</span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>GSPR</strong><br>
                                            <span class="text-muted">Groupe Spécial de Protection Rapprochée</span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>BAB</strong><br>
                                            <span class="text-muted">Base Aérienne de Bouaké</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Statistiques -->
                            <div class="card card-secondary mt-3">
                                <div class="card-header">
                                    <h4 class="card-title">
                                        <i class="fas fa-chart-bar me-2"></i>Statistiques Actuelles
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $stats = [
                                        'total' => count($refMgr->getAllUnites()),
                                        'armees' => count($refMgr->getAllUnites('ARMEE')),
                                        'gendarmeries' => count($refMgr->getAllUnites('GENDARMERIE')),
                                        'polices' => count($refMgr->getAllUnites('POLICE'))
                                    ];
                                    ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Total unités</span>
                                        <strong><?= $stats['total'] ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Armée</span>
                                        <strong><?= $stats['armees'] ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Gendarmerie</span>
                                        <strong><?= $stats['gendarmeries'] ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Police</span>
                                        <strong><?= $stats['polices'] ?></strong>
                                    </div>
                                </div>
                            </div>
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

    // Validation du formulaire
    document.querySelector('form').addEventListener('submit', function(e) {
        const code = document.getElementById('code').value.trim();
        const nom = document.getElementById('nom').value.trim();
        const type = document.getElementById('type').value;

        if (!code || !nom || !type) {
            e.preventDefault();
            alert('Veuillez remplir tous les champs obligatoires.');
            return false;
        }
    });
    </script>
</body>

</html>