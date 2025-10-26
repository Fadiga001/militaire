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

// Traitement du formulaire
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $role = $_POST['role'] ?? 'comptable';
    $statut = $_POST['statut'] ?? 'actif';

    if (empty($nom)) {
        $error = "Veuillez saisir le nom.";
    } elseif (empty($email)) {
        $error = "Veuillez saisir l'email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email invalide.";
    } elseif (empty($mot_de_passe)) {
        $error = "Veuillez saisir le mot de passe.";
    } elseif (!in_array($role, ['comptable','admin','super_admin'], true)) {
        $error = "Rôle invalide.";
    } elseif (!in_array($statut, ['actif','inactif'], true)) {
        $error = "Statut invalide.";
    } else {
        // Vérifier l'unicité de l'email
        $stmt = $pdo->prepare("SELECT id_utilisateur FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Cet email est déjà utilisé.";
        } else {
            try {
                $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe, role, statut) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nom, $email, $hash, $role, $statut]);
                $success = true;
                log_activity($pdo, (int)$_SESSION['user_id'], 'Créer utilisateur', json_encode(['email'=>$email,'role'=>$role]));
            } catch (PDOException $e) {
                $error = "Erreur lors de l'ajout : " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Ajouter un utilisateur</title>
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
                    <div class="page-header text-center">
                        <h3 class="fw-bold mb-3">Ajouter un utilisateur</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title">Formulaire d'ajout</div>
                                </div>
                                <div class="card-body">
                                    <?php if ($success): ?>
                                        <div class="alert alert-success">Utilisateur ajouté avec succès.</div>
                                    <?php elseif ($error): ?>
                                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                                    <?php endif; ?>

                                    <form method="POST" action="">
                                        <div class="form-group mb-3">
                                            <label for="nom">Nom <span class="text-danger">*</span></label>
                                            <input type="text" id="nom" name="nom" class="form-control"
                                                placeholder="Nom de l'agent" required
                                                value="<?= isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : '' ?>">
                                        </div>
                                        <div class="form-group mb-3">
                                            <label for="email">Email <span class="text-danger">*</span></label>
                                            <input type="email" id="email" name="email" class="form-control"
                                                placeholder="Email" required
                                                value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                                        </div>
                                        <div class="form-group mb-3">
                                            <label for="mot_de_passe">Mot de passe <span class="text-danger">*</span></label>
                                            <input type="password" id="mot_de_passe" name="mot_de_passe"
                                                class="form-control" placeholder="Mot de passe" required>
                                        </div>
                                        <div class="form-group mb-3">
                                            <label for="role">Rôle</label>
                                            <select id="role" name="role" class="form-control">
                                                <?php $roleSel = $_POST['role'] ?? 'comptable'; ?>
                                                <option value="comptable" <?= $roleSel==='comptable'?'selected':'' ?>>Comptable</option>
                                                <option value="admin" <?= $roleSel==='admin'?'selected':'' ?>>Admin</option>
                                                <option value="super_admin" <?= $roleSel==='super_admin'?'selected':'' ?>>Super Admin</option>
                                            </select>
                                        </div>
                                        <div class="form-group mb-3">
                                            <label for="statut">Statut</label>
                                            <select id="statut" name="statut" class="form-control">
                                                <?php $statSel = $_POST['statut'] ?? 'actif'; ?>
                                                <option value="actif" <?= $statSel==='actif'?'selected':'' ?>>Actif</option>
                                                <option value="inactif" <?= $statSel==='inactif'?'selected':'' ?>>Inactif</option>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-success mt-3">Ajouter</button>
                                        <a href="utilisateurs.php" class="btn btn-secondary mt-3">Retour</a>
                                    </form>
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