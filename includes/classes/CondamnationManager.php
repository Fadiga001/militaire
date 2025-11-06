<?php

/**
 * Classe CondamnationManager - CONDAMNATIONS DIRECTES UNIQUEMENT
 * Gestion des condamnations sans détention provisoire préalable
 * 
 * RÈGLES MÉTIER:
 * - Aucun champ DP (OIP, OMLP, mandat) n'est utilisé
 * - Date début exécution = date jugement
 * - Aucune déduction de jours
 * - Détenu peut être LIBRE, EVADE, ou tout statut sauf DETENTION_PROVISOIRE
 */
class CondamnationManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Créer une condamnation DIRECTE (sans DP)
     */
    public function create(array $data, int $userId): ?int
    {
        try {
            $this->pdo->beginTransaction();

            // VALIDATION: Vérifier que le détenu n'a PAS de DP en cours
            $checkDP = $this->pdo->prepare("
                SELECT COUNT(*) as nb 
                FROM detentions_provisoires 
                WHERE detenu_id = ? AND statut = 'EN_COURS'
            ");
            $checkDP->execute([$data['detenu_id']]);
            if ($checkDP->fetch()['nb'] > 0) {
                throw new Exception(
                    "Ce détenu a une détention provisoire en cours. " .
                        "Utilisez le module 'Conversion DP → Condamnation' à la place."
                );
            }

            // Vérifier unicité numéro dossier
            $checkDossier = $this->pdo->prepare("
                SELECT COUNT(*) FROM condamnations 
                WHERE numero_dossier = ? AND is_deleted = FALSE
            ");
            $checkDossier->execute([$data['numero_dossier']]);
            if ($checkDossier->fetchColumn() > 0) {
                throw new Exception("Ce numéro de dossier existe déjà.");
            }

            // INSERTION - Aucun champ DP n'est rempli
            $sql = "INSERT INTO condamnations (
                numero_dossier, 
                detenu_id, 
                infraction_id, 
                infraction_details,
                date_infraction, 
                lieu_infraction, 
                date_jugement, 
                numero_jugement, 
                tribunal,
                peine_valeur, 
                peine_unite,
                lieu_detention_id, 
                date_debut_execution,
                observations,
                statut, 
                is_principale, 
                created_by,
                -- FORCER les champs DP à NULL
                date_oip,
                date_omlp,
                date_mandat_depot,
                date_liberation_mandat,
                jours_detention_provisoire_oip,
                jours_detention_provisoire_mandat
            ) VALUES (
                :numero_dossier, 
                :detenu_id, 
                :infraction_id, 
                :infraction_details,
                :date_infraction, 
                :lieu_infraction, 
                :date_jugement, 
                :numero_jugement, 
                :tribunal,
                :peine_valeur, 
                :peine_unite,
                :lieu_detention_id, 
                :date_jugement, -- date_debut_execution = date_jugement
                :observations,
                'EN_COURS', 
                :is_principale, 
                :created_by,
                NULL, NULL, NULL, NULL, 0, 0 -- Tous les champs DP à NULL/0
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':numero_dossier' => $data['numero_dossier'],
                ':detenu_id' => $data['detenu_id'],
                ':infraction_id' => $data['infraction_id'],
                ':infraction_details' => $data['infraction_details'] ?? null,
                ':date_infraction' => $data['date_infraction'] ?? null,
                ':lieu_infraction' => $data['lieu_infraction'] ?? null,
                ':date_jugement' => $data['date_jugement'],
                ':numero_jugement' => $data['numero_jugement'] ?? null,
                ':tribunal' => $data['tribunal'] ?? null,
                ':peine_valeur' => $data['peine_valeur'],
                ':peine_unite' => $data['peine_unite'],
                ':lieu_detention_id' => $data['lieu_detention_id'],
                ':observations' => $data['observations'] ?? 'CONDAMNATION DIRECTE - Sans détention provisoire préalable',
                ':is_principale' => $data['is_principale'] ?? true,
                ':created_by' => $userId
            ]);

            $condamnationId = (int)$this->pdo->lastInsertId();

            // Mettre à jour le statut du détenu
            $this->updateDetenuStatut($data['detenu_id'], $userId);

            // Créer la période d'exécution
            $this->createPeriodeDetention(
                $data['detenu_id'],
                $condamnationId,
                $data['lieu_detention_id'],
                $data['date_jugement'],
                $userId
            );

            // Audit log
            $this->logAudit($userId, 'CONDAMNATION_DIRECTE', $condamnationId, [
                'numero_dossier' => $data['numero_dossier'],
                'detenu_id' => $data['detenu_id'],
                'peine' => $data['peine_valeur'] . ' ' . $data['peine_unite'],
                'detention_provisoire' => 'NON',
                'jours_deduits' => 0
            ]);

            $this->pdo->commit();
            return $condamnationId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erreur création condamnation directe: " . $e->getMessage());
            throw $e; // Relancer pour gestion dans le contrôleur
        }
    }

    /**
     * Récupérer une condamnation par ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT c.*,
                d.nom_complet as detenu_nom, 
                d.matricule as detenu_matricule,
                d.statut_actuel as detenu_statut,
                g.libelle as detenu_grade,
                u.nom as detenu_unite,
                i.libelle as infraction_libelle, 
                i.categorie as infraction_categorie,
                l.nom as lieu_detention_nom,
                l.ville as lieu_detention_ville,
                -- Jours restants (sans déduction DP car = 0)
                DATEDIFF(c.date_liberation_effective, NOW()) as jours_restants,
                -- Niveau d'alerte
                CASE 
                    WHEN c.date_liberation_effective < NOW() THEN 'LIBERABLE'
                    WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 1 THEN 'CRITIQUE'
                    WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 7 THEN 'URGENT'
                    WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 14 THEN 'ATTENTION'
                    WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 30 THEN 'A_SUIVRE'
                    ELSE 'NORMAL'
                END as alerte_niveau,
                -- Progression (%)
                ROUND(
                    (DATEDIFF(NOW(), c.date_debut_execution) / 
                     DATEDIFF(c.date_liberation_effective, c.date_debut_execution)) * 100, 1
                ) as progression_pourcent
                FROM condamnations c
                INNER JOIN detenus d ON c.detenu_id = d.id
                LEFT JOIN grades g ON d.grade_id = g.id
                LEFT JOIN unites u ON d.unite_id = u.id
                LEFT JOIN infractions i ON c.infraction_id = i.id
                LEFT JOIN lieux_detention l ON c.lieu_detention_id = l.id
                WHERE c.id = :id AND c.is_deleted = FALSE";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Liste toutes les condamnations avec filtres
     */
    public function getAll(array $filters = []): array
    {
        $sql = "SELECT c.id, c.numero_dossier, c.date_jugement, c.statut,
                d.nom_complet as detenu, 
                d.matricule,
                g.libelle as grade,
                i.libelle as infraction, 
                i.categorie,
                CONCAT(c.peine_valeur, ' ', c.peine_unite) as peine,
                c.date_liberation_effective,
                DATEDIFF(c.date_liberation_effective, NOW()) as jours_restants,
                l.nom as lieu_detention,
                CASE 
                    WHEN c.date_liberation_effective < NOW() THEN 'LIBERABLE'
                    WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 1 THEN 'CRITIQUE'
                    WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 7 THEN 'URGENT'
                    WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 14 THEN 'ATTENTION'
                    WHEN DATEDIFF(c.date_liberation_effective, NOW()) <= 30 THEN 'A_SUIVRE'
                    ELSE 'NORMAL'
                END as alerte_niveau
                FROM condamnations c
                INNER JOIN detenus d ON c.detenu_id = d.id
                LEFT JOIN grades g ON d.grade_id = g.id
                LEFT JOIN infractions i ON c.infraction_id = i.id
                LEFT JOIN lieux_detention l ON c.lieu_detention_id = l.id
                WHERE c.is_deleted = FALSE";

        $params = [];

        // Filtres
        if (!empty($filters['statut'])) {
            $sql .= " AND c.statut = :statut";
            $params[':statut'] = $filters['statut'];
        }

        if (!empty($filters['detenu_id'])) {
            $sql .= " AND c.detenu_id = :detenu_id";
            $params[':detenu_id'] = $filters['detenu_id'];
        }

        if (!empty($filters['infraction_id'])) {
            $sql .= " AND c.infraction_id = :infraction_id";
            $params[':infraction_id'] = $filters['infraction_id'];
        }

        if (!empty($filters['lieu_id'])) {
            $sql .= " AND c.lieu_detention_id = :lieu_id";
            $params[':lieu_id'] = $filters['lieu_id'];
        }

        if (!empty($filters['alerte'])) {
            $sql .= " AND c.statut = 'EN_COURS' AND DATEDIFF(c.date_liberation_effective, NOW()) <= 30";
        }

        $sql .= " ORDER BY c.date_jugement DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT :limit";
        }

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if (!empty($filters['limit'])) {
            $stmt->bindValue(':limit', (int)$filters['limit'], PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Mettre à jour une condamnation
     */
    public function update(int $id, array $data, int $userId): bool
    {
        try {
            // FORCER les champs DP à NULL/0 lors de la mise à jour
            $sql = "UPDATE condamnations SET
                numero_dossier = :numero_dossier,
                infraction_id = :infraction_id,
                infraction_details = :infraction_details,
                date_infraction = :date_infraction,
                lieu_infraction = :lieu_infraction,
                date_jugement = :date_jugement,
                numero_jugement = :numero_jugement,
                tribunal = :tribunal,
                peine_valeur = :peine_valeur,
                peine_unite = :peine_unite,
                lieu_detention_id = :lieu_detention_id,
                observations = :observations,
                updated_by = :updated_by,
                -- FORCER DP à NULL
                date_oip = NULL,
                date_omlp = NULL,
                date_mandat_depot = NULL,
                date_liberation_mandat = NULL,
                jours_detention_provisoire_oip = 0,
                jours_detention_provisoire_mandat = 0
                WHERE id = :id AND is_deleted = FALSE";

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                ':numero_dossier' => $data['numero_dossier'],
                ':infraction_id' => $data['infraction_id'],
                ':infraction_details' => $data['infraction_details'] ?? null,
                ':date_infraction' => $data['date_infraction'] ?? null,
                ':lieu_infraction' => $data['lieu_infraction'] ?? null,
                ':date_jugement' => $data['date_jugement'],
                ':numero_jugement' => $data['numero_jugement'] ?? null,
                ':tribunal' => $data['tribunal'] ?? null,
                ':peine_valeur' => $data['peine_valeur'],
                ':peine_unite' => $data['peine_unite'],
                ':lieu_detention_id' => $data['lieu_detention_id'] ?? null,
                ':observations' => $data['observations'] ?? null,
                ':updated_by' => $userId,
                ':id' => $id
            ]);

            // Les triggers recalculeront automatiquement les dates
            return $result;
        } catch (PDOException $e) {
            error_log("Erreur mise à jour condamnation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Libérer un condamné
     */
    public function liberer(int $id, string $motif, int $userId): bool
    {
        try {
            $this->pdo->beginTransaction();

            $condamnation = $this->getById($id);
            if (!$condamnation) {
                throw new Exception("Condamnation introuvable");
            }

            // Mettre à jour la condamnation
            $stmt = $this->pdo->prepare("
                UPDATE condamnations
                SET statut = 'TERMINEE',
                    date_liberation_reelle = NOW(),
                    motif_liberation = :motif,
                    updated_by = :user_id
                WHERE id = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':user_id' => $userId,
                ':motif' => $motif
            ]);

            // Mettre à jour le détenu SI c'est sa dernière condamnation
            $checkAutres = $this->pdo->prepare("
                SELECT COUNT(*) FROM condamnations 
                WHERE detenu_id = :detenu_id 
                  AND statut = 'EN_COURS'
                  AND id != :id
                  AND is_deleted = FALSE
            ");
            $checkAutres->execute([
                ':detenu_id' => $condamnation['detenu_id'],
                ':id' => $id
            ]);

            if ($checkAutres->fetchColumn() == 0) {
                // Aucune autre condamnation active → LIBRE
                $this->pdo->prepare("
                    UPDATE detenus
                    SET statut_actuel = 'LIBRE',
                        date_changement_statut = NOW(),
                        updated_by = :user_id
                    WHERE id = :detenu_id
                ")->execute([
                    ':detenu_id' => $condamnation['detenu_id'],
                    ':user_id' => $userId
                ]);
            }

            // Fermer les périodes de détention
            $this->pdo->prepare("
                UPDATE periodes_detention
                SET date_fin = NOW()
                WHERE condamnation_id = :id AND date_fin IS NULL
            ")->execute([':id' => $id]);

            // Audit
            $this->logAudit($userId, 'LIBERATION_CONDAMNE', $id, [
                'motif' => $motif,
                'date_liberation' => date('Y-m-d H:i:s')
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erreur libération: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ajouter une remise de peine
     */
    public function addRemise(int $condamnationId, array $data, int $userId): ?int
    {
        try {
            $this->pdo->beginTransaction();

            $condamnation = $this->getById($condamnationId);
            if (!$condamnation || $condamnation['statut'] !== 'EN_COURS') {
                throw new Exception("Condamnation invalide ou terminée");
            }

            // Calculer les remises existantes
            $totalRemises = $this->getTotalRemises($condamnationId);
            $peineNette = (int)$condamnation['peine_jours_total']; // Pas de DP à déduire

            // Vérifier que total remises ≤ peine totale
            if (($totalRemises + $data['jours_remis']) > $peineNette) {
                throw new Exception(
                    "Le total des remises ({$totalRemises} + {$data['jours_remis']}) " .
                        "ne peut pas excéder la peine totale ({$peineNette} jours)."
                );
            }

            // Vérifier date cohérente
            if ($data['date_decision'] < $condamnation['date_jugement']) {
                throw new Exception(
                    "La date de décision ne peut pas précéder la date de jugement."
                );
            }

            // Insertion
            $sql = "INSERT INTO remises_peine (
                condamnation_id, type, motif, jours_remis,
                date_decision, reference_decision, autorite_decision,
                created_by
            ) VALUES (
                :condamnation_id, :type, :motif, :jours_remis,
                :date_decision, :reference_decision, :autorite_decision,
                :created_by
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':condamnation_id' => $condamnationId,
                ':type' => $data['type'],
                ':motif' => $data['motif'],
                ':jours_remis' => $data['jours_remis'],
                ':date_decision' => $data['date_decision'],
                ':reference_decision' => $data['reference_decision'] ?? null,
                ':autorite_decision' => $data['autorite_decision'] ?? null,
                ':created_by' => $userId
            ]);

            $remiseId = (int)$this->pdo->lastInsertId();

            // Recalculer la date de libération
            $nouveauTotal = $totalRemises + $data['jours_remis'];
            $this->pdo->prepare("
                UPDATE condamnations
                SET date_liberation_effective = DATE_SUB(
                    date_liberation_theorique,
                    INTERVAL :remises DAY
                )
                WHERE id = :id
            ")->execute([
                ':remises' => $nouveauTotal,
                ':id' => $condamnationId
            ]);

            // Audit
            $this->logAudit($userId, 'AJOUT_REMISE', $condamnationId, [
                'remise_id' => $remiseId,
                'jours_remis' => $data['jours_remis'],
                'type' => $data['type'],
                'total_remises' => $nouveauTotal,
                'nouvelle_date_liberation' => $this->getById($condamnationId)['date_liberation_effective']
            ]);

            $this->pdo->commit();
            return $remiseId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erreur ajout remise: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtenir les remises de peine d'une condamnation
     */
    public function getRemises(int $condamnationId): array
    {
        $sql = "SELECT * FROM remises_peine
                WHERE condamnation_id = :id
                ORDER BY date_decision DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $condamnationId]);
        return $stmt->fetchAll();
    }

    /**
     * Obtenir le total des jours de remises
     */
    private function getTotalRemises(int $condamnationId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(jours_remis), 0) 
            FROM remises_peine
            WHERE condamnation_id = ?
        ");
        $stmt->execute([$condamnationId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Statistiques des condamnations
     */
    public function getStatistiques(): array
    {
        $stats = [];

        // Total condamnations actives
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as total 
            FROM condamnations 
            WHERE statut = 'EN_COURS' AND is_deleted = FALSE
        ");
        $stats['actives'] = (int)$stmt->fetch()['total'];

        // Libérations imminentes (30 jours)
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as total 
            FROM condamnations 
            WHERE statut = 'EN_COURS' 
            AND DATEDIFF(date_liberation_effective, NOW()) BETWEEN 0 AND 30
            AND is_deleted = FALSE
        ");
        $stats['liberations_imminentes'] = (int)$stmt->fetch()['total'];

        // Dépassées (à libérer)
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as total 
            FROM condamnations 
            WHERE statut = 'EN_COURS' 
            AND date_liberation_effective < NOW()
            AND is_deleted = FALSE
        ");
        $stats['a_liberer'] = (int)$stmt->fetch()['total'];

        // Par infraction
        $stmt = $this->pdo->query("
            SELECT i.libelle, i.categorie, COUNT(*) as nb
            FROM condamnations c
            JOIN infractions i ON c.infraction_id = i.id
            WHERE c.statut = 'EN_COURS' AND c.is_deleted = FALSE
            GROUP BY i.id, i.libelle, i.categorie
            ORDER BY nb DESC
            LIMIT 10
        ");
        $stats['par_infraction'] = $stmt->fetchAll();

        // Par lieu
        $stmt = $this->pdo->query("
            SELECT l.nom, COUNT(*) as nb
            FROM condamnations c
            JOIN lieux_detention l ON c.lieu_detention_id = l.id
            WHERE c.statut = 'EN_COURS' AND c.is_deleted = FALSE
            GROUP BY l.id, l.nom
            ORDER BY nb DESC
        ");
        $stats['par_lieu'] = $stmt->fetchAll();

        return $stats;
    }

    /**
     * Mettre à jour le statut du détenu
     */
    private function updateDetenuStatut(int $detenuId, int $userId): void
    {
        $this->pdo->prepare("
            UPDATE detenus
            SET statut_actuel = 'CONDAMNE',
                nombre_condamnations = nombre_condamnations + 1,
                is_multrecidiviste = (nombre_condamnations + 1 > 1),
                date_changement_statut = NOW(),
                updated_by = :user_id
            WHERE id = :detenu_id
        ")->execute([
            ':detenu_id' => $detenuId,
            ':user_id' => $userId
        ]);
    }

    /**
     * Créer une période de détention
     */
    private function createPeriodeDetention(
        int $detenuId,
        int $condamnationId,
        int $lieuId,
        string $dateDebut,
        int $userId
    ): void {
        $sql = "INSERT INTO periodes_detention (
            detenu_id, condamnation_id, type, motif,
            date_debut, lieu_detention_id, observations, created_by
        ) VALUES (
            :detenu_id, :condamnation_id, 'EXECUTION_PEINE', 'Condamnation directe - Exécution de peine',
            :date_debut, :lieu_id, 'Aucune détention provisoire préalable', :created_by
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':detenu_id' => $detenuId,
            ':condamnation_id' => $condamnationId,
            ':date_debut' => $dateDebut,
            ':lieu_id' => $lieuId,
            ':created_by' => $userId
        ]);
    }

    /**
     * Logger une action dans l'audit
     */
    private function logAudit(int $userId, string $action, int $entityId, array $data): void
    {
        $this->pdo->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values)
            VALUES (:user_id, :action, 'CONDAMNATION', :entity_id, :payload)
        ")->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':entity_id' => $entityId,
            ':payload' => json_encode($data, JSON_UNESCAPED_UNICODE)
        ]);
    }

    /**
     * Vérifier si un numéro de dossier existe
     */
    public function numeroDossierExists(string $numero, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as nb FROM condamnations 
                WHERE numero_dossier = :numero 
                AND is_deleted = FALSE";

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
        }

        $stmt = $this->pdo->prepare($sql);
        $params = [':numero' => $numero];

        if ($excludeId) {
            $params[':exclude_id'] = $excludeId;
        }

        $stmt->execute($params);
        return (int)$stmt->fetch()['nb'] > 0;
    }
}
