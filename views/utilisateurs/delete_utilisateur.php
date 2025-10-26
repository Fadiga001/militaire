<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../../includes/db.php';
require_once '../../includes/logs.php';

// Vérifier l'ID de l'utilisateur à supprimer
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: utilisateurs.php');
    exit();
}

$utilisateur_id = (int) $_GET['id'];

try {
    // Empêcher de se supprimer soi-même
    if ($utilisateur_id === (int)$_SESSION['user_id']) {
        $_SESSION['error'] = "Vous ne pouvez pas supprimer votre propre compte.";
        header('Location: utilisateurs.php');
        exit();
    }

    // Vérifier existence et rôle
    $stmt = $pdo->prepare('SELECT id_utilisateur, role, email FROM utilisateurs WHERE id_utilisateur = ?');
    $stmt->execute([$utilisateur_id]);
    $userToDelete = $stmt->fetch();
    if (!$userToDelete) {
        $_SESSION['error'] = "Utilisateur introuvable.";
        header('Location: utilisateurs.php');
        exit();
    }

    // Bloquer suppression des comptes privilégiés (admin/super_admin)
    if (in_array($userToDelete['role'], ['admin','super_admin'], true)) {
        $_SESSION['error'] = "Suppression interdite pour les comptes administrateurs.";
        header('Location: utilisateurs.php');
        exit();
    }

    // Vérifier les références (paiements, recus) liés à cet utilisateur
    $refChecks = [
        ['sql' => 'SELECT COUNT(*) AS nb FROM paiements WHERE id_utilisateur = ?', 'label' => 'paiements'],
        ['sql' => 'SELECT COUNT(*) AS nb FROM recus WHERE id_utilisateur = ?', 'label' => 'reçus'],
        ['sql' => 'SELECT COUNT(*) AS nb FROM logs_activites WHERE id_utilisateur = ?', 'label' => 'logs']
    ];
    $hasRefs = false;
    $refLabels = [];
    foreach ($refChecks as $check) {
        $s = $pdo->prepare($check['sql']);
        $s->execute([$utilisateur_id]);
        if (((int)$s->fetch()['nb']) > 0) {
            $hasRefs = true;
            $refLabels[] = $check['label'];
        }
    }

    if ($hasRefs) {
        $_SESSION['error'] = "Suppression impossible: des " . implode(', ', $refLabels) . " sont liés à cet utilisateur.";
        header('Location: utilisateurs.php');
        exit();
    }

    // Suppression
    $stmt = $pdo->prepare('DELETE FROM utilisateurs WHERE id_utilisateur = ?');
    $stmt->execute([$utilisateur_id]);
    $_SESSION['success'] = "Utilisateur supprimé avec succès.";
    log_activity($pdo, (int)$_SESSION['user_id'], 'Supprimer utilisateur', json_encode(['id'=>$utilisateur_id,'email'=>$userToDelete['email']]));
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur lors de la suppression : " . htmlspecialchars($e->getMessage());
}

header('Location: utilisateurs.php');
exit();
