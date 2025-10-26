<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/logs.php';

// Vérifier l'ID du paiement à supprimer
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: paiements.php');
    exit();
}

$paiement_id = (int) $_GET['id'];

try {
    // Vérifier existence du paiement
    $stmt = $pdo->prepare('SELECT id_paiement, ref_transaction, montant FROM paiements WHERE id_paiement = ?');
    $stmt->execute([$paiement_id]);
    $paiementToDelete = $stmt->fetch();
    if (!$paiementToDelete) {
        $_SESSION['error'] = "Paiement introuvable.";
        header('Location: paiements.php');
        exit();
    }

    // Vérifier si le paiement a un reçu (est validé)
    $stmt = $pdo->prepare('SELECT COUNT(*) as nb FROM recus WHERE id_paiement = ?');
    $stmt->execute([$paiement_id]);
    $hasRecu = (int)$stmt->fetch()['nb'] > 0;

    if ($hasRecu) {
        $_SESSION['error'] = "Suppression impossible: ce paiement a déjà été validé (reçu généré).";
        header('Location: paiements.php');
        exit();
    }

    // Suppression
    $stmt = $pdo->prepare('DELETE FROM paiements WHERE id_paiement = ?');
    $stmt->execute([$paiement_id]);
    $_SESSION['success'] = "Paiement supprimé avec succès.";
    log_activity($pdo, (int)$_SESSION['user_id'], 'Supprimer paiement', json_encode(['id'=>$paiement_id,'ref'=>$paiementToDelete['ref_transaction'],'montant'=>$paiementToDelete['montant']]));
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de la suppression : " . htmlspecialchars($e->getMessage());
}

header('Location: paiements.php');
exit();

