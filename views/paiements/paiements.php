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

// Récupère la liste des paiements avec informations étudiant et année
$stmt = $pdo->query('
    SELECT p.*,
       e.matricule, e.nom AS etudiant_nom, e.prenom AS etudiant_prenom, e.id_filiere AS filiere, e.niveau AS niveau,
       a.libelle AS annee_libelle,
       u.nom AS utilisateur_nom,
       r.id_recu, r.numero_recu, r.statut AS recu_statut
    FROM paiements p
    LEFT JOIN etudiants e ON e.id_etudiant = p.id_etudiant
    LEFT JOIN annees_academiques a ON a.id_annee = p.id_annee
    LEFT JOIN utilisateurs u ON u.id_utilisateur = p.id_utilisateur
    LEFT JOIN recus r ON r.id_paiement = p.id_paiement
    ORDER BY p.date_paiement DESC
');
$paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Paiements</title>
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
                        <h3 class="fw-bold mb-3">Liste des paiements</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">

                                <div class="card-body">

                                    <div class="table-responsive">
                                        <table id="datatable-table" class="display table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Étudiant</th>
                                                    <th>Montant</th>
                                                    <th>Mode</th>
                                                    <th>Référence</th>
                                                    <th>Date</th>
                                                    <th>Expiration</th>
                                                    <th>Niveau</th>
                                                    <th>Validé</th>
                                                    <th style="width: 10%;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $i = 1; ?>
                                                <?php foreach ($paiements as $paiement) : ?>
                                                    <tr>
                                                        <td><?= $i++ ?></td>
                                                        <td><?= htmlspecialchars($paiement['etudiant_nom'] . ' ' . $paiement['etudiant_prenom']) ?>
                                                        </td>
                                                        <td><?= number_format($paiement['montant'], 0, ',', ' ') ?> FCFA
                                                        </td>
                                                        <td><span
                                                                class="badge bg-<?= $paiement['mode_paiement'] === 'BACI' ? 'primary' : 'info' ?>"><?= htmlspecialchars($paiement['mode_paiement']) ?></span>
                                                        </td>
                                                        <td><?= htmlspecialchars($paiement['ref_transaction']) ?></td>
                                                        <td><?= date('d/m/Y', strtotime($paiement['date_paiement'])) ?></td>
                                                        <td>
                                                            <?php if ($paiement['date_expiration']): ?>
                                                                <?php
                                                                $isExpired = strtotime($paiement['date_expiration']) < time();
                                                                $expirationDate = date('d/m/Y', strtotime($paiement['date_expiration']));
                                                                ?>
                                                                <span class="badge bg-<?= $isExpired ? 'danger' : 'success' ?>">
                                                                    <?= $expirationDate ?>
                                                                    <?= $isExpired ? ' (Expiré)' : '' ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($paiement['niveau']) ?></td>
                                                        <td>
                                                            <?php
                                                            // Vérifier si le paiement a un reçu (est validé)
                                                            $hasRecu = !empty($paiement['numero_recu']);
                                                            ?>
                                                            <span class="badge bg-<?= $hasRecu ? 'success' : 'warning' ?>">
                                                                <?= $hasRecu ? 'Oui' : 'Non' ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="form-button-action">
                                                                <a href="edit_paiement.php?id=<?= $paiement['id_paiement'] ?>"
                                                                    class="btn btn-link btn-primary btn-lg"
                                                                    data-bs-toggle="tooltip" title="Modifier">
                                                                    <i class="fa fa-edit"></i>
                                                                </a>
                                                                <a href="delete_paiement.php?id=<?= $paiement['id_paiement'] ?>"
                                                                    class="btn btn-link btn-danger" data-bs-toggle="tooltip"
                                                                    title="Supprimer"
                                                                    onclick="return confirm('Voulez-vous vraiment supprimer ce paiement ?');">
                                                                    <i class="fa fa-times"></i>
                                                                </a>

                                                                <?php if ($hasRecu): ?>
                                                                    <a href="imprimer_recu.php?id=<?= $paiement['id_recu'] ?>"
                                                                        class="btn btn-link btn-info" data-bs-toggle="tooltip"
                                                                        title="Imprimer reçu">
                                                                        <i class="fa fa-print"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                                <a href="add_paiement.php?id_paiement=<?= $paiement['id_paiement'] ?>"
                                                                    class="btn btn-link btn-success"
                                                                    data-bs-toggle="tooltip" title="Nouveau paiement">
                                                                    <i class="fa fa-money-bill-wave"></i>
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