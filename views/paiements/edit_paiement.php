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

// Vérification de l'ID du paiement
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: paiements.php');
    exit();
}

$paiement_id = (int) $_GET['id'];
$message = '';
$success = false;

// Récupération des données actuelles du paiement avec informations étudiant
$stmt = $pdo->prepare("
    SELECT p.*, 
           e.matricule, e.nom as etudiant_nom, e.prenom as etudiant_prenom, e.id_filiere as filiere,
           a.libelle as annee_libelle
    FROM paiements p
    INNER JOIN etudiants e ON e.id_etudiant = p.id_etudiant
    INNER JOIN annees_academiques a ON a.id_annee = p.id_annee
    WHERE p.id_paiement = ?
");
$stmt->execute([$paiement_id]);
$paiement = $stmt->fetch();

if (!$paiement) {
    $message = "Paiement introuvable.";
}

// Récupère les étudiants de la même année académique
$stmt = $pdo->prepare("SELECT id_etudiant, matricule, nom, prenom, id_filiere FROM etudiants WHERE id_annee = ? ORDER BY nom, prenom");
$stmt->execute([$paiement['id_annee'] ?? 0]);
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_etudiant = (int)($_POST['id_etudiant'] ?? 0);
    $montant = (float)($_POST['montant'] ?? 0);
    $mode_paiement = $_POST['mode_paiement'] ?? '';
    $ref_transaction = trim($_POST['ref_transaction'] ?? '');
    $date_paiement = $_POST['date_paiement'] ?? '';
    $date_expiration = $_POST['date_expiration'] ?? '';

    if ($id_etudiant <= 0) {
        $message = "Veuillez sélectionner un étudiant.";
    } elseif ($montant <= 0) {
        $message = "Le montant doit être supérieur à 0.";
    } elseif (!in_array($mode_paiement, ['BACI', 'CELPAID'], true)) {
        $message = "Mode de paiement invalide.";
    } elseif (empty($ref_transaction)) {
        $message = "Veuillez saisir la référence de transaction.";
    } else {
        // Vérifier l'unicité de la référence de transaction (hors paiement courant)
        $stmt = $pdo->prepare("SELECT id_paiement FROM paiements WHERE ref_transaction = ? AND id_paiement != ?");
        $stmt->execute([$ref_transaction, $paiement_id]);
        if ($stmt->fetch()) {
            $message = "Cette référence de transaction est déjà utilisée par un autre paiement.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE paiements SET id_etudiant = ?, montant = ?, mode_paiement = ?, ref_transaction = ?, date_paiement = ?, date_expiration = ? WHERE id_paiement = ?");
                $stmt->execute([$id_etudiant, $montant, $mode_paiement, $ref_transaction, $date_paiement, $date_expiration, $paiement_id]);
                $success = true;
                $message = "Paiement modifié avec succès.";
                log_activity($pdo, (int)$_SESSION['user_id'], 'Modifier paiement', json_encode(['id' => $paiement_id, 'ref' => $ref_transaction, 'montant' => $montant]));
                // Recharge les données après modification
                $stmt = $pdo->prepare("
                    SELECT p.*, 
                           e.matricule, e.nom as etudiant_nom, e.prenom as etudiant_prenom, e.id_filiere as filiere,
                           a.libelle as annee_libelle
                    FROM paiements p
                    INNER JOIN etudiants e ON e.id_etudiant = p.id_etudiant
                    INNER JOIN annees_academiques a ON a.id_annee = p.id_annee
                    WHERE p.id_paiement = ?
                ");
                $stmt->execute([$paiement_id]);
                $paiement = $stmt->fetch();
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
    <title>Modifier un paiement</title>
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
                        <h3 class="fw-bold mb-3">Modifier un paiement</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <?php if ($message): ?>
                            <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?>">
                                <?= htmlspecialchars($message) ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($paiement): ?>
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title">Formulaire de modification</div>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="id_etudiant">Étudiant <span
                                                            class="text-danger">*</span></label>
                                                    <select id="id_etudiant" name="id_etudiant" class="form-control"
                                                        required>
                                                        <option value="">-- Sélectionner un étudiant --</option>
                                                        <?php foreach ($etudiants as $etudiant): ?>
                                                        <option value="<?= $etudiant['id_etudiant'] ?>"
                                                            <?= (isset($_POST['id_etudiant']) && $_POST['id_etudiant'] == $etudiant['id_etudiant']) || (!isset($_POST['id_etudiant']) && $paiement['id_etudiant'] == $etudiant['id_etudiant']) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($etudiant['matricule'] . ' - ' . $etudiant['nom'] . ' ' . $etudiant['prenom']) ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label>Année académique</label>
                                                    <div class="form-control-plaintext bg-light p-2 rounded">
                                                        <strong><?= htmlspecialchars($paiement['annee_libelle']) ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="montant">Montant (FCFA) <span
                                                            class="text-danger">*</span></label>
                                                    <input type="number" id="montant" name="montant"
                                                        class="form-control" step="0.01" min="0" required
                                                        value="<?= htmlspecialchars($_POST['montant'] ?? $paiement['montant']) ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="mode_paiement">Mode de paiement <span
                                                            class="text-danger">*</span></label>
                                                    <select id="mode_paiement" name="mode_paiement" class="form-control"
                                                        required>
                                                        <option value="">-- Sélectionner --</option>
                                                        <?php $modeSel = $_POST['mode_paiement'] ?? $paiement['mode_paiement']; ?>
                                                        <option value="BACI"
                                                            <?= $modeSel === 'BACI' ? 'selected' : '' ?>>BACI</option>
                                                        <option value="CELPAID"
                                                            <?= $modeSel === 'CELPAID' ? 'selected' : '' ?>>CELPAID
                                                        </option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="ref_transaction">Référence de transaction <span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" id="ref_transaction" name="ref_transaction"
                                                        class="form-control" required
                                                        value="<?= htmlspecialchars($_POST['ref_transaction'] ?? $paiement['ref_transaction']) ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="date_paiement">Date de paiement</label>
                                                    <input type="datetime-local" id="date_paiement" name="date_paiement"
                                                        class="form-control"
                                                        value="<?= htmlspecialchars($_POST['date_paiement'] ?? date('Y-m-d\TH:i', strtotime($paiement['date_paiement']))) ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="date_expiration">Date d'expiration</label>
                                                    <input type="date" id="date_expiration" name="date_expiration"
                                                        class="form-control"
                                                        value="<?= htmlspecialchars($_POST['date_expiration'] ?? ($paiement['date_expiration'] ? date('Y-m-d', strtotime($paiement['date_expiration'])) : '')) ?>">
                                                    <small class="text-muted">Date d'expiration du paiement
                                                        (optionnel)</small>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-success mt-3">Enregistrer</button>
                                        <a href="paiements.php" class="btn btn-secondary mt-3">Annuler</a>
                                    </form>
                                </div>
                            </div>
                            <?php else: ?>
                            <a href="paiements.php" class="btn btn-secondary">Retour à la liste</a>
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