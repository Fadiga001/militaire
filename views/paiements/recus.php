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

// Récupère les années académiques pour affichage (optionnel)
$stmt = $pdo->query("SELECT id_annee, libelle FROM annees_academiques ORDER BY date_debut DESC");
$annees = $stmt->fetchAll();

// Requête principale : récupère tous les reçus sans filtre
$sql = "
    SELECT r.*, 
           p.montant, p.mode_paiement, p.ref_transaction, p.date_paiement, p.date_expiration,
           e.matricule, e.id_etudiant as etudiant_id, e.nom as etudiant_nom, e.prenom as etudiant_prenom, e.niveau,
           f.nom_filiere,
           a.libelle as annee_libelle,
           u.nom as utilisateur_nom
    FROM recus r
    INNER JOIN paiements p ON p.id_paiement = r.id_paiement
    INNER JOIN etudiants e ON e.id_etudiant = p.id_etudiant
    INNER JOIN filieres f ON f.id_filiere = e.id_filiere
    INNER JOIN annees_academiques a ON a.id_annee = r.id_annee
    INNER JOIN utilisateurs u ON u.id_utilisateur = r.id_utilisateur
    ORDER BY r.date_generation DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$recus = $stmt->fetchAll();

// Statistiques
$total_recus = count($recus);
$recus_generes = count(array_filter($recus, function ($r) {
    return $r['statut'] === 'généré';
}));

$montant_total = array_sum(array_column($recus, 'montant'));
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Gestion des reçus</title>
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
                        <h3 class="fw-bold mb-3">Gestion des reçus</h3>
                    </div>

                    <!-- Statistiques -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card card-stats card-primary">
                                <div class="card-body">
                                    <div class="numbers">
                                        <p class="card-category">Total reçus</p>
                                        <h4 class="card-title"><?= $total_recus ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card card-stats card-success">
                                <div class="card-body">
                                    <div class="numbers">
                                        <p class="card-category">Générés</p>
                                        <h4 class="card-title"><?= $recus_generes ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card card-stats card-info">
                                <div class="card-body">
                                    <div class="numbers">
                                        <p class="card-category">Montant total</p>
                                        <h4 class="card-title"><?= number_format($montant_total, 0, ',', ' ') ?> FCFA
                                        </h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tableau des reçus -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">Liste des reçus</div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recus)): ?>
                            <div class="alert alert-info">Aucun reçu trouvé.</div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table id="datatable-table" class="display table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>N° reçu</th>
                                            <th>Étudiant</th>
                                            <th>Classe</th>
                                            <th>Montant</th>
                                            <th>Mode</th>
                                            <th>Expiration</th>
                                            <th>Statut</th>
                                            <th style="width: 15%;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recus as $index => $recu): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><strong><?= htmlspecialchars($recu['numero_recu']) ?></strong></td>
                                            <td><?= htmlspecialchars($recu['etudiant_nom'] . ' ' . $recu['etudiant_prenom']) ?>
                                            </td>
                                            <td><span
                                                    class="badge badge-primary"><?= htmlspecialchars($recu['niveau']) ?></span>
                                            </td>
                                            <td><?= number_format($recu['montant'], 0, ',', ' ') ?> FCFA</td>
                                            <td>
                                                <span
                                                    class="badge badge-<?= $recu['mode_paiement'] === 'BACI' ? 'primary' : 'info' ?>">
                                                    <?= htmlspecialchars($recu['mode_paiement']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($recu['date_expiration']): ?>
                                                <?php
                                                            $isExpired = strtotime($recu['date_expiration']) < time();
                                                            $expirationDate = date('d/m/Y', strtotime($recu['date_expiration']));
                                                            ?>
                                                <span class="badge badge-<?= $isExpired ? 'danger' : 'success' ?>">
                                                    <?= $expirationDate ?>
                                                    <?= $isExpired ? ' (Expiré)' : '' ?>
                                                </span>
                                                <?php else: ?>
                                                <span class="badge badge-secondary">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($recu['statut'] === 'généré'): ?>
                                                <span class="badge badge-success">Généré</span>
                                                <?php else: ?>
                                                <span class="badge badge-danger">Annulé</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="imprimer_recu.php?id=<?= $recu['id_recu'] ?>"
                                                    class="btn btn-sm btn-primary" title="Imprimer" target="_blank">
                                                    <i class="fa fa-print"></i>
                                                </a>
                                                <a href="../etudiants/voir_profil_etudiant.php?id=<?= $recu['etudiant_id'] ?>"
                                                    class="btn btn-sm btn-info" title="Voir/Modifier">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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