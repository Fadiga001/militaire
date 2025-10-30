<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'ADMIN') {
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

$userMgr = new UserManager($pdo);

$message = '';
$messageType = '';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_active' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        $isActive = (bool)$_POST['is_active'];

        if ($userMgr->toggleActive($userId, $isActive)) {
            log_activity($pdo, $_SESSION['user_id'], $isActive ? 'Activation utilisateur' : 'Désactivation utilisateur', "User ID: $userId");
            $message = "Statut modifié avec succès.";
            $messageType = "success";
        } else {
            $message = "Erreur lors de la modification.";
            $messageType = "danger";
        }
    } elseif ($_POST['action'] === 'unlock' && isset($_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];

        if ($userMgr->unlock($userId)) {
            log_activity($pdo, $_SESSION['user_id'], 'Déverrouillage compte', "User ID: $userId");
            $message = "Compte déverrouillé avec succès.";
            $messageType = "success";
        } else {
            $message = "Erreur lors du déverrouillage.";
            $messageType = "danger";
        }
    }
}

// Filtres
$filters = [];
if (!empty($_GET['role'])) {
    $filters['role'] = $_GET['role'];
}
if (isset($_GET['is_active']) && $_GET['is_active'] !== '') {
    $filters['is_active'] = (bool)$_GET['is_active'];
}
if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

$utilisateurs = $userMgr->getAll($filters);
$stats = $userMgr->getStatistiques();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Gestion des Utilisateurs - Système Militaire</title>
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
                    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                        <div>
                            <h3 class="fw-bold mb-3">
                                <i class="fas fa-user-shield me-2"></i>Gestion des Utilisateurs
                            </h3>
                            <h6 class="op-7 mb-2">
                                <?= count($utilisateurs) ?> utilisateur(s) trouvé(s)
                            </h6>
                        </div>
                        <div class="ms-md-auto py-2 py-md-0">
                            <a href="ajouter_utilisateur.php" class="btn btn-primary btn-round">
                                <i class="fas fa-plus me-2"></i>Nouvel Utilisateur
                            </a>
                        </div>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                            <strong><i class="fas fa-check-circle me-2"></i></strong>
                            <?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistiques -->
                    <div class="row mb-4">
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-primary bubble-shadow-small">
                                                <i class="fas fa-users"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Total</p>
                                                <h4 class="card-title"><?= $stats['total'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-success bubble-shadow-small">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Actifs</p>
                                                <h4 class="card-title"><?= $stats['actifs'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-info bubble-shadow-small">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Connectés 24h</p>
                                                <h4 class="card-title"><?= $stats['connectes_24h'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div
                                                class="icon-big text-center icon-<?= $stats['verrouilles'] > 0 ? 'danger' : 'secondary' ?> bubble-shadow-small">
                                                <i class="fas fa-lock"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Verrouillés</p>
                                                <h4 class="card-title"><?= $stats['verrouilles'] ?></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtres -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <form method="GET" class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">Recherche</label>
                                            <input type="text" name="search" class="form-control"
                                                placeholder="Nom, email, username..."
                                                value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Rôle</label>
                                            <select name="role" class="form-select">
                                                <option value="">Tous les rôles</option>
                                                <option value="ADMIN"
                                                    <?= ($_GET['role'] ?? '') === 'ADMIN' ? 'selected' : '' ?>>
                                                    Administrateur</option>
                                                <option value="USER"
                                                    <?= ($_GET['role'] ?? '') === 'USER' ? 'selected' : '' ?>>
                                                    Utilisateur</option>
                                                <option value="READONLY"
                                                    <?= ($_GET['role'] ?? '') === 'READONLY' ? 'selected' : '' ?>>
                                                    Lecture seule</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Statut</label>
                                            <select name="is_active" class="form-select">
                                                <option value="">Tous</option>
                                                <option value="1"
                                                    <?= ($_GET['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Actifs
                                                </option>
                                                <option value="0"
                                                    <?= ($_GET['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Inactifs
                                                </option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label d-block">&nbsp;</label>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-filter me-2"></i>Filtrer
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Liste -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="users-table" class="table table-hover table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Utilisateur</th>
                                                    <th>Email</th>
                                                    <th>Rôle</th>
                                                    <th>Dernière connexion</th>
                                                    <th>Statut</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($utilisateurs)): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center text-muted py-4">
                                                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                                            Aucun utilisateur trouvé
                                                        </td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($utilisateurs as $u): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <div class="avatar avatar-sm me-2">
                                                                        <img src="../../assets/img/profile.jpg"
                                                                            class="avatar-img rounded-circle">
                                                                    </div>
                                                                    <div>
                                                                        <strong><?= htmlspecialchars($u['nom'] . ' ' . $u['prenom']) ?></strong>
                                                                        <br><small
                                                                            class="text-muted">@<?= htmlspecialchars($u['username']) ?></small>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td><?= htmlspecialchars($u['email']) ?></td>
                                                            <td>
                                                                <span class="badge badge-<?=
                                                                                            $u['role'] === 'ADMIN' ? 'danger' : ($u['role'] === 'USER' ? 'primary' : 'info')
                                                                                            ?>">
                                                                    <?= $u['role'] ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php if ($u['last_login_at']): ?>
                                                                    <?= date('d/m/Y H:i', strtotime($u['last_login_at'])) ?>
                                                                    <br><small class="text-muted">Il y a
                                                                        <?= $u['jours_depuis_connexion'] ?> jour(s)</small>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Jamais connecté</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($u['locked_until'] && strtotime($u['locked_until']) > time()): ?>
                                                                    <span class="badge badge-danger">
                                                                        <i class="fas fa-lock"></i> Verrouillé
                                                                    </span>
                                                                <?php elseif ($u['is_active']): ?>
                                                                    <span class="badge badge-success">
                                                                        <i class="fas fa-check"></i> Actif
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-secondary">
                                                                        <i class="fas fa-ban"></i> Inactif
                                                                    </span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group">
                                                                    <a href="voir_utilisateur.php?id=<?= $u['id'] ?>"
                                                                        class="btn btn-sm btn-info" title="Voir">
                                                                        <i class="fas fa-eye"></i>
                                                                    </a>
                                                                    <a href="modifier_utilisateur.php?id=<?= $u['id'] ?>"
                                                                        class="btn btn-sm btn-warning" title="Modifier">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                    <?php if ($u['locked_until'] && strtotime($u['locked_until']) > time()): ?>
                                                                        <button type="button" class="btn btn-sm btn-success"
                                                                            onclick="unlockUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')"
                                                                            title="Déverrouiller">
                                                                            <i class="fas fa-unlock"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal déverrouillage -->
    <div class="modal fade" id="unlockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-unlock me-2"></i>Déverrouiller le compte
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p>Déverrouiller le compte de :</p>
                        <p class="text-center"><strong id="username" class="h5"></strong></p>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="action" value="unlock">
                        <input type="hidden" name="user_id" id="userIdToUnlock">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-unlock me-2"></i>Déverrouiller
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../../requires/script.php'; ?>
    <script>
        function unlockUser(id, username) {
            document.getElementById('userIdToUnlock').value = id;
            document.getElementById('username').textContent = username;
            new bootstrap.Modal(document.getElementById('unlockModal')).show();
        }

        $(document).ready(function() {
            $('#users-table').DataTable({
                pageLength: 25,
                order: [
                    [3, 'desc']
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
                }
            });
        });
    </script>
</body>

</html>