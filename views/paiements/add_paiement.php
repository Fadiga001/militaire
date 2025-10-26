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

// Récupère l'année académique en cours
$stmt = $pdo->query("SELECT id_annee, libelle FROM annees_academiques WHERE statut = 'en cours' ORDER BY date_debut DESC LIMIT 1");
$annee_courante = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupère l'étudiant lié à l'id_paiement si présent
$etudiant = null;
$paiements_passes = [];
$reste_a_payer = 0;
$scolarite_totale = 0;
$etat_scolarite = '';
$default_montant = '';
if (isset($_GET['id_paiement']) && is_numeric($_GET['id_paiement']) && $annee_courante) {
    $id_paiement = (int)$_GET['id_paiement'];
    // Récupère l'étudiant
    $stmt = $pdo->prepare('
        SELECT e.*, f.nom_filiere
        FROM paiements p
        JOIN etudiants e ON p.id_etudiant = e.id_etudiant
        LEFT JOIN filieres f ON e.id_filiere = f.id_filiere
        WHERE p.id_paiement = ?
        LIMIT 1
    ');
    $stmt->execute([$id_paiement]);
    $etudiant = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($etudiant) {
        // Montant scolarité brute et nette (avec reduction étudiante)
        $feeStmt = $pdo->prepare('SELECT montant_total FROM filiere_frais WHERE id_filiere = ? AND niveau = ? AND id_annee = ? LIMIT 1');
        $feeStmt->execute([$etudiant['id_filiere'], $etudiant['niveau'], $annee_courante['id_annee']]);
        $fee = $feeStmt->fetch(PDO::FETCH_ASSOC);
        $scolarite_totale = $fee ? (float)$fee['montant_total'] : 0;
        $reduction = isset($etudiant['reduction']) ? (float)$etudiant['reduction'] : 0.0;
        $scolarite_nette = max(0, $scolarite_totale - $reduction);

        // Paiements passés pour l'année en cours
        $payStmt = $pdo->prepare('SELECT montant, date_paiement, mode_paiement, ref_transaction FROM paiements WHERE id_etudiant = ? AND id_annee = ? ORDER BY date_paiement ASC');
        $payStmt->execute([$etudiant['id_etudiant'], $annee_courante['id_annee']]);
        $paiements_passes = $payStmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcul du reste à payer sur la scolarité nette
        $total_paye = 0;
        foreach ($paiements_passes as $p) {
            $total_paye += $p['montant'];
        }
        $reste_a_payer = max(0, $scolarite_nette - $total_paye);
        $etat_scolarite = ($reste_a_payer <= 0) ? 'À jour' : 'Reste à payer : ' . number_format($reste_a_payer, 0, ',', ' ') . ' FCFA (après réduction)';

        // Pré-remplir montant par défaut
        $default_montant = $reste_a_payer > 0 ? $reste_a_payer : '';
    }
}

// Traitement du formulaire
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_etudiant_post = (int)($_POST['id_etudiant'] ?? 0);
    $montant = (float)($_POST['montant'] ?? 0);
    $mode_paiement = $_POST['mode_paiement'] ?? '';
    // Normaliser la référence: trim + uppercase pour éviter les doublons cachés
    $ref_transaction = strtoupper(trim($_POST['ref_transaction'] ?? ''));
    $date_paiement = $_POST['date_paiement'] ?? date('Y-m-d H:i:s');
    $date_expiration_input = $_POST['date_expiration'] ?? '';

    // Test unitaire : le montant ne doit pas dépasser le reste à payer
    if ($montant > $reste_a_payer) {
        $error = "Le montant saisi dépasse le reste à payer (" . number_format($reste_a_payer, 0, ',', ' ') . " FCFA).";
    } elseif ($id_etudiant_post <= 0) {
        $error = "Veuillez sélectionner un étudiant.";
    } elseif ($montant <= 0) {
        $error = "Le montant doit être supérieur à 0.";
    } elseif (!in_array($mode_paiement, ['BACI', 'CELPAID'], true)) {
        $error = "Mode de paiement invalide.";
    } elseif (empty($ref_transaction)) {
        $error = "Veuillez saisir la référence de transaction.";
    } elseif (!$annee_courante) {
        $error = "Aucune année académique en cours trouvée.";
    } else {
        // Vérifier l'unicité de la référence de transaction
        $stmt = $pdo->prepare("SELECT id_paiement FROM paiements WHERE ref_transaction = ?");
        $stmt->execute([$ref_transaction]);
        if ($stmt->fetch()) {
            $error = "Cette référence de transaction est déjà utilisée.";
        } else {
            try {
                // Déterminer la date d'expiration
                $baseTs = strtotime($date_paiement ?: 'now');
                $date_exp_ts = $date_expiration_input ? strtotime($date_expiration_input) : false;
                // Si fournie et valide, utiliser la date fournie; sinon J+30 à partir de la date de paiement
                $date_expiration = $date_exp_ts ? date('Y-m-d', $date_exp_ts) : date('Y-m-d', strtotime('+30 days', $baseTs));
                // Si expiration < date paiement (jour), réaligner à J+30
                if ($date_exp_ts && strtotime(date('Y-m-d', $date_exp_ts)) < strtotime(date('Y-m-d', $baseTs))) {
                    $date_expiration = date('Y-m-d', strtotime('+30 days', $baseTs));
                }
                $stmt = $pdo->prepare("INSERT INTO paiements (id_etudiant, montant, mode_paiement, ref_transaction, date_paiement, date_expiration, id_annee, id_utilisateur) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id_etudiant_post, $montant, $mode_paiement, $ref_transaction, $date_paiement, $date_expiration, $annee_courante['id_annee'], $_SESSION['user_id']]);
                $id_paiement_new = $pdo->lastInsertId();

                // Générer le reçu
                $stmt = $pdo->query("SELECT COUNT(*) FROM recus WHERE YEAR(date_generation) = YEAR(NOW())");
                $nb_recus = $stmt->fetchColumn() + 1;
                $numero_recu = 'AGI-' . date('Y') . '-' . str_pad($nb_recus, 4, '0', STR_PAD_LEFT);

                // Générer le reçu avec un numéro aléatoire et unique
                do {
                    $numero_recu = 'AGI-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM recus WHERE numero_recu = ?");
                    $stmt->execute([$numero_recu]);
                    $exists = $stmt->fetchColumn();
                } while ($exists > 0);

                // Insérer le reçu avec date d'expiration (30 jours)
                $stmt = $pdo->prepare("INSERT INTO recus (id_paiement, numero_recu, id_annee, id_utilisateur) VALUES (?, ?, ?, ?)");
                $stmt->execute([$id_paiement_new, $numero_recu, $annee_courante['id_annee'], $_SESSION['user_id']]);

                // Récupérer l'id du reçu
                $id_recu = $pdo->lastInsertId();

                // Rediriger vers la page d’impression du reçu
                header("Location: imprimer_recu.php?id=$id_recu");
                exit();
                $success = true;
                log_activity($pdo, (int)$_SESSION['user_id'], 'Créer paiement', json_encode(['ref' => $ref_transaction, 'montant' => $montant]));
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
    <title>Ajouter un paiement</title>
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
                    <div class="page-header text-center">
                        <h3 class="fw-bold mb-3">Ajouter un paiement</h3>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <div class="card-title">Formulaire d'ajout</div>
                                </div>
                                <div class="card-body">
                                    <?php if ($success): ?>
                                        <div class="alert alert-success">Paiement ajouté avec succès.</div>
                                    <?php elseif ($error): ?>
                                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                                    <?php endif; ?>

                                    <?php if ($etudiant): ?>
                                        <div class="alert alert-info">
                                            Paiement pour :
                                            <strong><?= htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']) ?></strong><br>
                                            Filière : <?= htmlspecialchars($etudiant['nom_filiere']) ?> | Niveau :
                                            <?= htmlspecialchars($etudiant['niveau']) ?><br>
                                            Scolarité totale : <?= number_format($scolarite_totale, 0, ',', ' ') ?> FCFA<br>
                                            <strong><?= $etat_scolarite ?></strong>
                                        </div>
                                        <?php if ($paiements_passes): ?>
                                            <div class="mb-3">
                                                <h5>Paiements déjà effectués :</h5>
                                                <ul>
                                                    <?php foreach ($paiements_passes as $p): ?>
                                                        <li>
                                                            <?= date('d/m/Y', strtotime($p['date_paiement'])) ?> :
                                                            <?= number_format($p['montant'], 0, ',', ' ') ?> FCFA
                                                            (<?= htmlspecialchars($p['mode_paiement']) ?>, Réf :
                                                            <?= htmlspecialchars($p['ref_transaction']) ?>)
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <form method="POST" action="">
                                        <input type="hidden" name="id_etudiant"
                                            value="<?= $etudiant ? $etudiant['id_etudiant'] : '' ?>">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label>Étudiant</label>
                                                    <div class="form-control-plaintext bg-light p-2 rounded">
                                                        <?= $etudiant ? htmlspecialchars($etudiant['matricule'] . ' - ' . $etudiant['nom'] . ' ' . $etudiant['prenom']) : 'Aucun étudiant sélectionné' ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label>Année académique</label>
                                                    <div class="form-control-plaintext bg-light p-2 rounded">
                                                        <strong><?= $annee_courante ? htmlspecialchars($annee_courante['libelle']) : 'Aucune année en cours' ?></strong>
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
                                                        value="<?= isset($_POST['montant']) ? htmlspecialchars($_POST['montant']) : ($default_montant !== '' ? htmlspecialchars($default_montant) : '') ?>"
                                                        max="<?= $reste_a_payer > 0 ? $reste_a_payer : '' ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="mode_paiement">Mode de paiement <span
                                                            class="text-danger">*</span></label>
                                                    <select id="mode_paiement" name="mode_paiement" class="form-control"
                                                        required>
                                                        <option value="">-- Sélectionner --</option>
                                                        <option value="BACI"
                                                            <?= (isset($_POST['mode_paiement']) && $_POST['mode_paiement'] === 'BACI') ? 'selected' : '' ?>>
                                                            BACI</option>
                                                        <option value="CELPAID"
                                                            <?= (isset($_POST['mode_paiement']) && $_POST['mode_paiement'] === 'CELPAID') ? 'selected' : '' ?>>
                                                            CELPAID</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="form-group mb-3">
                                                    <label for="ref_transaction">Référence de transaction <span
                                                            class="text-danger">*</span></label>
                                                    <input type="text" id="ref_transaction" name="ref_transaction"
                                                        class="form-control" required
                                                        placeholder="Référence unique de la transaction"
                                                        value="<?= isset($_POST['ref_transaction']) ? htmlspecialchars($_POST['ref_transaction']) : '' ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="date_expiration">Date d'expiration</label>
                                                    <input type="datetime-local" id="date_expiration"
                                                        name="date_expiration" class="form-control"
                                                        value="<?= isset($_POST['date_expiration']) ? htmlspecialchars($_POST['date_expiration']) : date('Y-m-d\TH:i') ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group mb-3">
                                                    <label for="date_paiement">Date de paiement</label>
                                                    <input type="datetime-local" id="date_paiement" name="date_paiement"
                                                        class="form-control"
                                                        value="<?= isset($_POST['date_paiement']) ? htmlspecialchars($_POST['date_paiement']) : date('Y-m-d\TH:i') ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-success mt-3">Ajouter</button>
                                        <a href="paiements.php" class="btn btn-secondary mt-3">Retour</a>
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