<?php

/**
 * Script de génération automatique des notifications
 * À exécuter via CRON quotidiennement
 * 
 * Crontab example:
 * 0 6 * * * php /var/www/html/detenusMilitaires/pages/notifications/generer_notifications_cron.php
 * (Tous les jours à 6h du matin)
 */

// Désactiver le timeout
set_time_limit(0);

// Chemin vers les includes
require_once __DIR__ . '/../../includes/db.php';

// Log de début
$logFile = __DIR__ . '/../../logs/notifications.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message)
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== DÉBUT GÉNÉRATION NOTIFICATIONS ===");

try {
    // 1. Nettoyer les anciennes notifications (> 30 jours)
    $stmt = $pdo->prepare("
        DELETE FROM notifications 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    logMessage("Notifications supprimées (> 30j): $deleted");

    // 2. Désactiver les notifications obsolètes
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_active = FALSE 
        WHERE date_evenement < CURDATE() 
        AND type = 'LIBERATION_IMMINENTE'
    ");
    $stmt->execute();
    $deactivated = $stmt->rowCount();
    logMessage("Notifications désactivées (dates passées): $deactivated");

    // 3. LIBÉRATIONS IMMINENTES (0-30 jours)
    $stmt = $pdo->query("
        SELECT 
            c.id as condamnation_id,
            c.numero_dossier,
            c.date_liberation_effective,
            DATEDIFF(c.date_liberation_effective, NOW()) as jours_restants,
            d.id as detenu_id,
            d.nom_complet,
            d.matricule,
            CASE 
                WHEN DATEDIFF(c.date_liberation_effective, NOW()) < 0 THEN 'LIBERABLE'
                WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 1 THEN 'CRITICAL'
                WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 7 THEN 'HIGH'
                WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 14 THEN 'MEDIUM'
                ELSE 'LOW'
            END as urgence
        FROM condamnations c
        INNER JOIN detenus d ON c.detenu_id = d.id
        WHERE c.statut = 'EN_COURS'
        AND c.is_deleted = FALSE
        AND d.is_deleted = FALSE
        AND DATEDIFF(c.date_liberation_effective, NOW()) BETWEEN -1 AND 30
    ");

    $liberations = $stmt->fetchAll();
    $created = 0;

    foreach ($liberations as $lib) {
        // Vérifier si notification existe déjà pour aujourd'hui
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as nb 
            FROM notifications 
            WHERE entity_type = 'CONDAMNATION' 
            AND entity_id = ? 
            AND type = 'LIBERATION_IMMINENTE'
            AND DATE(created_at) = CURDATE()
        ");
        $checkStmt->execute([$lib['condamnation_id']]);

        if ((int)$checkStmt->fetch()['nb'] === 0) {
            $jours = (int)$lib['jours_restants'];

            if ($jours < 0) {
                $titre = "LIBÉRATION DÉPASSÉE - " . $lib['nom_complet'];
                $message = "La date de libération est dépassée de " . abs($jours) . " jour(s).\n";
                $message .= "Date prévue: " . date('d/m/Y', strtotime($lib['date_liberation_effective'])) . "\n";
                $message .= "Dossier: " . $lib['numero_dossier'];
            } else {
                $titre = "Libération dans $jours jour(s) - " . $lib['nom_complet'];
                $message = "Date de libération prévue: " . date('d/m/Y', strtotime($lib['date_liberation_effective'])) . "\n";
                $message .= "Matricule: " . $lib['matricule'] . "\n";
                $message .= "Dossier: " . $lib['numero_dossier'];
            }

            $insertStmt = $pdo->prepare("
                INSERT INTO notifications (
                    type, urgence, entity_type, entity_id,
                    titre, message, date_evenement, jours_avant, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)
            ");

            $insertStmt->execute([
                'LIBERATION_IMMINENTE',
                $lib['urgence'],
                'CONDAMNATION',
                $lib['condamnation_id'],
                $titre,
                $message,
                $lib['date_liberation_effective'],
                max(0, $jours)
            ]);

            $created++;
        }
    }

    logMessage("Notifications libérations créées: $created");

    // 4. DÉTENTION PROVISOIRE DÉPASSÉE
    $stmt = $pdo->query("
        SELECT 
            c.id as condamnation_id,
            c.numero_dossier,
            c.date_oip,
            c.date_mandat_depot,
            DATEDIFF(NOW(), c.date_oip) as jours_depuis_oip,
            DATEDIFF(NOW(), c.date_mandat_depot) as jours_depuis_mandat,
            d.id as detenu_id,
            d.nom_complet,
            d.matricule,
            i.duree_detention_provisoire_mois
        FROM condamnations c
        INNER JOIN detenus d ON c.detenu_id = d.id
        INNER JOIN infractions i ON c.infraction_id = i.id
        WHERE c.statut = 'EN_COURS'
        AND d.statut_actuel = 'DETENTION_PROVISOIRE'
        AND c.is_deleted = FALSE
        AND d.is_deleted = FALSE
        AND i.duree_detention_provisoire_mois IS NOT NULL
        AND (
            DATEDIFF(NOW(), c.date_oip) > (i.duree_detention_provisoire_mois * 30)
            OR DATEDIFF(NOW(), c.date_mandat_depot) > (i.duree_detention_provisoire_mois * 30)
        )
    ");

    $detentions = $stmt->fetchAll();
    $dpCreated = 0;

    foreach ($detentions as $dp) {
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as nb 
            FROM notifications 
            WHERE entity_type = 'DETENU' 
            AND entity_id = ? 
            AND type = 'FIN_DETENTION_PROVISOIRE'
            AND DATE(created_at) = CURDATE()
        ");
        $checkStmt->execute([$dp['detenu_id']]);

        if ((int)$checkStmt->fetch()['nb'] === 0) {
            $titre = "Détention provisoire dépassée - " . $dp['nom_complet'];
            $message = "La durée maximale de détention provisoire est dépassée.\n";
            $message .= "Durée max autorisée: " . $dp['duree_detention_provisoire_mois'] . " mois\n";

            if ($dp['date_oip']) {
                $message .= "Jours depuis OIP: " . $dp['jours_depuis_oip'] . " jours\n";
            }
            if ($dp['date_mandat_depot']) {
                $message .= "Jours depuis mandat: " . $dp['jours_depuis_mandat'] . " jours\n";
            }

            $message .= "Dossier: " . $dp['numero_dossier'];

            $insertStmt = $pdo->prepare("
                INSERT INTO notifications (
                    type, urgence, entity_type, entity_id,
                    titre, message, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, TRUE)
            ");

            $insertStmt->execute([
                'FIN_DETENTION_PROVISOIRE',
                'HIGH',
                'DETENU',
                $dp['detenu_id'],
                $titre,
                $message
            ]);

            $dpCreated++;
        }
    }

    logMessage("Notifications détention provisoire créées: $dpCreated");

    // 5. DOCUMENTS MANQUANTS (condamnations sans lieu ou dates)
    $stmt = $pdo->query("
        SELECT 
            c.id as condamnation_id,
            c.numero_dossier,
            d.id as detenu_id,
            d.nom_complet,
            d.matricule,
            CASE 
                WHEN c.lieu_detention_id IS NULL THEN 'Lieu de détention'
                WHEN c.date_debut_execution IS NULL THEN 'Date début exécution'
                WHEN c.numero_jugement IS NULL THEN 'Numéro de jugement'
                ELSE NULL
            END as document_manquant
        FROM condamnations c
        INNER JOIN detenus d ON c.detenu_id = d.id
        WHERE c.statut = 'EN_COURS'
        AND c.is_deleted = FALSE
        AND d.is_deleted = FALSE
        AND (
            c.lieu_detention_id IS NULL 
            OR c.date_debut_execution IS NULL
            OR c.numero_jugement IS NULL
        )
    ");

    $documents = $stmt->fetchAll();
    $docCreated = 0;

    foreach ($documents as $doc) {
        if (!$doc['document_manquant']) continue;

        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as nb 
            FROM notifications 
            WHERE entity_type = 'CONDAMNATION' 
            AND entity_id = ? 
            AND type = 'DOCUMENT_MANQUANT'
            AND message LIKE ?
            AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $checkStmt->execute([
            $doc['condamnation_id'],
            '%' . $doc['document_manquant'] . '%'
        ]);

        if ((int)$checkStmt->fetch()['nb'] === 0) {
            $titre = "Document manquant - " . $doc['nom_complet'];
            $message = "Information manquante: " . $doc['document_manquant'] . "\n";
            $message .= "Matricule: " . $doc['matricule'] . "\n";
            $message .= "Dossier: " . $doc['numero_dossier'];

            $insertStmt = $pdo->prepare("
                INSERT INTO notifications (
                    type, urgence, entity_type, entity_id,
                    titre, message, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, TRUE)
            ");

            $insertStmt->execute([
                'DOCUMENT_MANQUANT',
                'MEDIUM',
                'CONDAMNATION',
                $doc['condamnation_id'],
                $titre,
                $message
            ]);

            $docCreated++;
        }
    }

    logMessage("Notifications documents manquants créées: $docCreated");

    // 6. Statistiques finales
    $totalCreated = $created + $dpCreated + $docCreated;

    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as non_lues,
            SUM(CASE WHEN urgence = 'CRITICAL' THEN 1 ELSE 0 END) as critiques
        FROM notifications
        WHERE is_active = TRUE
    ");
    $stats = $statsStmt->fetch();

    logMessage("Total notifications créées: $totalCreated");
    logMessage("Total notifications actives: " . $stats['total']);
    logMessage("Notifications non lues: " . $stats['non_lues']);
    logMessage("Notifications critiques: " . $stats['critiques']);
    logMessage("=== FIN GÉNÉRATION NOTIFICATIONS - SUCCÈS ===");

    // Afficher résultat si exécution manuelle
    if (php_sapi_name() === 'cli') {
        echo "\n✅ GÉNÉRATION TERMINÉE\n";
        echo "─────────────────────\n";
        echo "• Notifications créées: $totalCreated\n";
        echo "  - Libérations: $created\n";
        echo "  - Détention provisoire: $dpCreated\n";
        echo "  - Documents manquants: $docCreated\n";
        echo "• Anciennes supprimées: $deleted\n";
        echo "• Désactivées: $deactivated\n";
        echo "• Total actives: " . $stats['total'] . "\n";
        echo "• Non lues: " . $stats['non_lues'] . "\n";
        echo "• Critiques: " . $stats['critiques'] . "\n\n";
    }
} catch (Exception $e) {
    logMessage("ERREUR: " . $e->getMessage());
    logMessage("=== FIN GÉNÉRATION NOTIFICATIONS - ÉCHEC ===");

    if (php_sapi_name() === 'cli') {
        echo "\n❌ ERREUR: " . $e->getMessage() . "\n\n";
    }

    exit(1);
}

exit(0);
