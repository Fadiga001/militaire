<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once '../../includes/db.php';
require_once '../../includes/logs.php';

// Récupère le nom de l'utilisateur connecté
$stmt = $pdo->prepare('SELECT nom FROM utilisateurs WHERE id_utilisateur = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom']) : '';

// Vérification de l'ID de l'utilisateur à modifier
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: utilisateurs.php');
    exit();
}

$utilisateur_id = (int)$_GET['id'];
$message = '';
$success = false;

// Récupération des données de l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
$stmt->execute([$utilisateur_id]);
$utilisateur = $stmt->fetch();

if (!$utilisateur) {
    $message = "Utilisateur introuvable.";
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? $utilisateur['role'];
    $statut = $_POST['statut'] ?? $utilisateur['statut'];
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    if (empty($nom)) {
        $message = "Veuillez saisir le nom.";
    } elseif (empty($email)) {
        $message = "Veuillez saisir l'email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Email invalide.";
    } elseif (!in_array($role, ['comptable','admin','super_admin'], true)) {
        $message = "Rôle invalide.";
    } elseif (!in_array($statut, ['actif','inactif'], true)) {
        $message = "Statut invalide.";
    } else {
        // Vérifier l'unicité de l'email (hors utilisateur courant)
        $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email = ? AND id_utilisateur != ?");
        $stmt->execute([$email, $utilisateur_id]);
        if ($stmt->fetch()) {
            $message = "Cet email est déjà utilisé par un autre utilisateur.";
        } else {
            try {
                if (!empty($mot_de_passe)) {
                    $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET nom = ?, email = ?, mot_de_passe = ?, role = ?, statut = ? WHERE id_utilisateur = ?");
                    $stmt->execute([$nom, $email, $hash, $role, $statut, $utilisateur_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE utilisateurs SET nom = ?, email = ?, role = ?, statut = ? WHERE id_utilisateur = ?");
                    $stmt->execute([$nom, $email, $role, $statut, $utilisateur_id]);
                }
                $success = true;
                $message = "Utilisateur modifié avec succès.";
                log_activity($pdo, (int)$_SESSION['user_id'], 'Modifier utilisateur', json_encode(['id'=>$utilisateur_id,'email'=>$email]));
                // Recharge les données après modification
                $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
                $stmt->execute([$utilisateur_id]);
                $utilisateur = $stmt->fetch();
            } catch (PDOException $e) {
                $message = "Erreur lors de la modification : " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Modifier un utilisateur</title>
    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <?php include '../../requires/link.php'; ?>
</head>

<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include '../../requires/sidebar.php'; ?>
        <!-- End Sidebar -->

        <div class="main-panel">
            <?php include '../../requires/main-header.php'; ?>

            <div class="container">
                <div class="page-inner">
                    <div class="page-header">
                        <h3 class="fw-bold mb-3">Modifier un utilisateur</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-8 offset-md-2">
                            <?php if ($message): ?>
                                <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?>">
                                    <?= htmlspecialchars($message) ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($utilisateur): ?>
                                <div class="card">
                                    <div class="card-header">
                                        <div class="card-title">Formulaire de modification</div>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="">
                                            <div class="form-group mb-3">
                                                <label for="nom">Nom <span class="text-danger">*</span></label>
                                                <input type="text" id="nom" name="nom" class="form-control" required
                                                    value="<?= htmlspecialchars($_POST['nom'] ?? $utilisateur['nom']) ?>">
                                            </div>
                                            <div class="form-group mb-3">
                                                <label for="email">Email <span class="text-danger">*</span></label>
                                                <input type="email" id="email" name="email" class="form-control" required
                                                    value="<?= htmlspecialchars($_POST['email'] ?? $utilisateur['email']) ?>">
                                            </div>
                                            <div class="form-group mb-3">
                                                <label for="mot_de_passe">Nouveau mot de passe</label>
                                                <input type="password" id="mot_de_passe" name="mot_de_passe"
                                                    class="form-control" placeholder="Laisser vide pour ne pas changer">
                                            </div>
                                            <div class="form-group mb-3">
                                                <label for="role">Rôle</label>
                                                <select id="role" name="role" class="form-control">
                                                    <?php $roleSel = $_POST['role'] ?? $utilisateur['role']; ?>
                                                    <option value="comptable" <?= $roleSel==='comptable'?'selected':'' ?>>Comptable</option>
                                                    <option value="admin" <?= $roleSel==='admin'?'selected':'' ?>>Admin</option>
                                                    <option value="super_admin" <?= $roleSel==='super_admin'?'selected':'' ?>>Super Admin</option>
                                                </select>
                                            </div>
                                            <div class="form-group mb-3">
                                                <label for="statut">Statut</label>
                                                <select id="statut" name="statut" class="form-control">
                                                    <?php $statSel = $_POST['statut'] ?? $utilisateur['statut']; ?>
                                                    <option value="actif" <?= $statSel==='actif'?'selected':'' ?>>Actif</option>
                                                    <option value="inactif" <?= $statSel==='inactif'?'selected':'' ?>>Inactif</option>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-success mt-3">Enregistrer</button>
                                            <a href="utilisateurs.php" class="btn btn-secondary mt-3">Annuler</a>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <a href="utilisateurs.php" class="btn btn-secondary">Retour à la liste</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../requires/script.php'; ?>
</body>

</html>