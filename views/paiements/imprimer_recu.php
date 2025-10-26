<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../../includes/db.php';

// 🔹 Récupère le nom de l'utilisateur connecté
$stmt = $pdo->prepare('SELECT nom FROM utilisateurs WHERE id_utilisateur = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$name = $user ? htmlspecialchars($user['nom']) : 'Utilisateur';

//Récupération de l'ID du reçu


// 🔹 Vérifie la présence d’un ID de reçu
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: paiements.php');
    exit();
}

$recu_id = (int) $_GET['id'];

// 🔹 Récupération complète du reçu
$stmt = $pdo->prepare("
    SELECT r.*, 
           p.montant, p.mode_paiement, p.ref_transaction, p.date_paiement, p.date_expiration, p.id_annee,
           e.matricule, e.id_etudiant as etudiant_id, e.nom as etudiant_nom, e.prenom as etudiant_prenom, e.id_filiere as filiere, e.contact, e.niveau,
           a.libelle as annee_libelle,
           u.nom as utilisateur_nom
    FROM recus r
    INNER JOIN paiements p ON p.id_paiement = r.id_paiement
    INNER JOIN etudiants e ON e.id_etudiant = p.id_etudiant
    INNER JOIN annees_academiques a ON a.id_annee = p.id_annee
    INNER JOIN utilisateurs u ON u.id_utilisateur = p.id_utilisateur
    WHERE r.id_recu = ?
");
$stmt->execute([$recu_id]);
$recu = $stmt->fetch();

if (!$recu || empty($recu['numero_recu'])) {
    echo "<h3 style='color:red;text-align:center;'>Reçu introuvable ou invalide.</h3>";
    exit();
}

// 🔹 Calcul du reste à payer
$scolarite_totale = 0;
$reduction = 0;
$scolarite_nette = 0;
$reste_a_payer = 0;
$etat_scolarite = '';

$feeStmt = $pdo->prepare('SELECT montant_total FROM filiere_frais WHERE id_filiere = ? AND niveau = ? AND id_annee = ? LIMIT 1');
$feeStmt->execute([$recu['filiere'], $recu['niveau'], $recu['id_annee']]);
$fee = $feeStmt->fetch(PDO::FETCH_ASSOC);

if ($fee) {
    $scolarite_totale = (float)$fee['montant_total'];
    // récupérer la réduction de l'étudiant
    $redStmt = $pdo->prepare('SELECT reduction FROM etudiants WHERE id_etudiant = ? LIMIT 1');
    $redStmt->execute([$recu['etudiant_id']]);
    $reduction = (float)$redStmt->fetchColumn();
    $scolarite_nette = max(0, $scolarite_totale - $reduction);

    $payStmt = $pdo->prepare('SELECT SUM(montant) as total_paye FROM paiements WHERE id_etudiant = ? AND id_annee = ?');
    $payStmt->execute([$recu['etudiant_id'], $recu['id_annee']]);
    $total_paye = (float)$payStmt->fetchColumn();
    $reste_a_payer = max(0, $scolarite_nette - $total_paye);
    $etat_scolarite = ($reste_a_payer <= 0)
        ? '✅ À jour'
        : '⚠️ Reste à payer : ' . number_format($reste_a_payer, 0, ',', ' ') . ' FCFA (après réduction)';
}

// 🔒 Génération QR code sécurisé
$secret_key = 'MaCleSecreteUltraSecurisee2025';
$signature = hash_hmac('sha256', $recu['numero_recu'], $secret_key);
$qr_url = 'https://agitel-formation.net/site/verif_recu.php?num=' . urlencode($recu['numero_recu']) . '&sign=' . urlencode($signature);
$qr_img = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . urlencode($qr_url);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu - <?= htmlspecialchars($recu['numero_recu']) ?></title>
    <style>
        @media print {
            body {
                margin: 10mm;
            }

            .no-print {
                display: none !important;
            }

            .recu-container {
                margin: 0;
                box-shadow: none;
            }
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }

        .recu-container {
            background: white;
            border: 2px solid #333;
            padding: 40px;
            max-width: 800px;
            margin: auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .recu-header {
            text-align: center;
            border-bottom: 3px solid #333;
            padding-bottom: 25px;
            margin-bottom: 30px;
            position: relative;
        }

        .logo-agitel {
            position: absolute;
            left: 0;
            top: 10px;
            height: 70px;
        }

        .qr-code {
            position: absolute;
            right: 0;
            top: 10px;
            height: 70px;
        }

        .etat-scolarite {
            background: #f8f9fa;
            color: #155724;
            font-weight: bold;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }

        .label {
            font-weight: bold;
            width: 35%;
            background-color: #f8f9fa;
        }

        .montant-total {
            background-color: #e9ecef;
            font-weight: bold;
            font-size: 18px;
        }

        .signature-zone {
            margin-top: 40px;
            text-align: right;
        }

        .signature-line {
            border-bottom: 2px solid #333;
            width: 220px;
            margin: 0 0 10px auto;
            height: 40px;
        }

        .cachet {
            font-weight: bold;
            color: #2c3e50;
        }

        .recu-footer {
            border-top: 2px solid #333;
            padding-top: 5px;
            margin-top: 40px;
            text-align: center;
            font-size: 14px;
        }

        .validity {
            margin-top: 10px;
            padding: 10px;
            border-radius: 6px;
            background: #eef7ff;
            color: #0c5460;
            border: 1px solid #b8daff;
            font-weight: bold;
        }

        .validity.expired {
            background: #fff3f3;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .back-button,
        .print-button {
            position: fixed;
            top: 20px;
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
            color: white;
            cursor: pointer;
            z-index: 1000;
        }

        .back-button {
            left: 20px;
            background: #6c757d;
        }

        .print-button {
            right: 20px;
            background: #007bff;
        }

        .back-button:hover {
            background: #495057;
        }

        .print-button:hover {
            background: #0056b3;
        }
    </style>
</head>

<body>
    <button class="back-button no-print" onclick="window.location.href='paiements.php'">⬅️ Retour</button>
    <button class="print-button no-print" onclick="window.print()">🖨️ Imprimer</button>

    <div class="recu-container">
        <div class="recu-header">
            <img src="../../assets/img/logo.png" alt="Logo AGITEL" class="logo-agitel">
            <img src="<?= $qr_img ?>" alt="QR Code" class="qr-code">
            <h1>REÇU DE PAIEMENT</h1>
            <h2>AGITEL - École Supérieure</h2>
            <p>Année académique : <?= htmlspecialchars($recu['annee_libelle']) ?></p>
        </div>

        <?php if ($etat_scolarite): ?>
            <div class="etat-scolarite"><?= $etat_scolarite ?></div>
        <?php endif; ?>

        <table>
            <tr>
                <td class="label">Numéro de reçu :</td>
                <td><?= htmlspecialchars($recu['numero_recu']) ?></td>
            </tr>
            <tr>
                <td class="label">Date de paiement :</td>
                <td><?= date('d/m/Y à H:i', strtotime($recu['date_paiement'])) ?></td>
            </tr>
            <tr>
                <td class="label">Étudiant :</td>
                <td><?= htmlspecialchars($recu['etudiant_nom'] . ' ' . $recu['etudiant_prenom']) ?></td>
            </tr>
            <tr>
                <td class="label">Matricule :</td>
                <td><?= htmlspecialchars($recu['matricule']) ?></td>
            </tr>
            <tr>
                <td class="label">Classe :</td>
                <td><?= htmlspecialchars($recu['niveau']) ?></td>
            </tr>
            <tr>
                <td class="label">Contact :</td>
                <td><?= htmlspecialchars($recu['contact'] ?? 'Non renseigné') ?></td>
            </tr>
            <tr>
                <td class="label">Mode de paiement :</td>
                <td><?= htmlspecialchars($recu['mode_paiement']) ?></td>
            </tr>
            <tr>
                <td class="label">Référence transaction :</td>
                <td><?= htmlspecialchars($recu['ref_transaction']) ?></td>
            </tr>
            <tr class="montant-total">
                <td class="label">Montant payé :</td>
                <td><?= number_format($recu['montant'], 0, ',', ' ') ?> FCFA</td>
            </tr>
        </table>

        <?php
        // Afficher la validité depuis la DB paiements.date_expiration
        $dateGeneration = new DateTime($recu['date_generation']);
        $dateExpiration = !empty($recu['date_expiration'])
            ? new DateTime($recu['date_expiration'])
            : (clone $dateGeneration)->modify('+30 days');
        $now = new DateTime();
        $isExpired = $now > $dateExpiration;
        ?>
        <div class="validity <?= $isExpired ? 'expired' : '' ?>">
            Validité du reçu : du <?= $dateGeneration->format('d/m/Y') ?> au <?= $dateExpiration->format('d/m/Y') ?>
            <?= $isExpired ? '— Expiré' : '' ?>
        </div>

        <div class="signature-zone">
            <div class="signature-line"></div>
            <div class="cachet">Signature & Cachet</div>
        </div>

        <div class="recu-footer">
            <p><strong>Reçu généré par :</strong> <?= htmlspecialchars($recu['utilisateur_nom']) ?></p>
            <p><strong>Date de génération :</strong> <?= date('d/m/Y à H:i', strtotime($recu['date_generation'])) ?></p>

        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 1000);
        });
    </script>
</body>

</html>