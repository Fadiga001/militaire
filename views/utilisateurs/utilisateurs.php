<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once '../../includes/db.php';

// Récupère le nom de l'utilisateur connecté
$stmt = $pdo->prepare('SELECT nom FROM utilisateurs WHERE id_utilisateur = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom']) : '';

// Récupère la liste des utilisateurs (hors admins)
$stmt = $pdo->query("SELECT * FROM utilisateurs WHERE role <> 'admin' AND role <> 'super_admin' ORDER BY date_creation DESC");
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Utilisateurs</title>
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
                        <h3 class="fw-bold mb-3">Liste des utilisateurs</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="d-flex align-items-center">
                                        <a href="add_utilisateur.php" class="btn btn-primary btn-round ms-auto">
                                            <i class="fa fa-plus"></i>
                                            Ajouter
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body">

                                    <div class="table-responsive">
                                        <table id="datatable-table" class="display table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Nom</th>
                                                    <th>Email</th>
                                                    <th>Rôle</th>
                                                    <th>Statut</th>
                                                    <th>Créé le</th>
                                                    <th>Dernière connexion</th>
                                                    <th style="width: 15%;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $i = 1; ?>
                                                <?php foreach ($utilisateurs as $utilisateur): ?>
                                                <tr>
                                                    <td><?= $i++ ?></td>
                                                    <td><?= htmlspecialchars($utilisateur['nom']) ?></td>
                                                    <td><?= htmlspecialchars($utilisateur['email']) ?></td>
                                                    <td><?= htmlspecialchars($utilisateur['role']) ?></td>
                                                    <td><span class="badge bg-<?= $utilisateur['statut']==='actif'?'success':'secondary' ?>"><?= htmlspecialchars($utilisateur['statut']) ?></span></td>
                                                    <td><?= htmlspecialchars($utilisateur['date_creation']) ?></td>
                                                    <td><?= htmlspecialchars($utilisateur['derniere_connexion'] ?? '-') ?></td>
                                                    <td>
                                                        <div class="form-button-action">
                                                            <a href="edit_utilisateur.php?id=<?= $utilisateur['id_utilisateur'] ?>"
                                                                class="btn btn-link btn-primary btn-lg"
                                                                title="Modifier">
                                                                <i class="fa fa-edit"></i>
                                                            </a>
                                                            <a href="delete_utilisateur.php?id=<?= $utilisateur['id_utilisateur'] ?>"
                                                                class="btn btn-link btn-danger" title="Supprimer"
                                                                onclick="return confirm('Voulez-vous vraiment supprimer cet utilisateur ?');">
                                                                <i class="fa fa-times"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
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
    <?php include '../../requires/script.php'; ?>
</body>

</html>