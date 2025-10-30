<?php

/**
 * Classe CondamnationManager
 * Gestion complète des condamnations
 */
class CondamnationManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Créer une nouvelle condamnation
     */
    public function create(array $data, int $userId): ?int
    {
        try {
            $this->pdo->beginTransaction();

            $sql = "INSERT INTO condamnations (
                numero_dossier, detenu_id, infraction_id, infraction_details,
                date_infraction, lieu_infraction, date_oip, date_omlp,
                date_jugement, numero_jugement, tribunal, date_mandat_depot,
                date_liberation_mandat, peine_valeur, peine_unite,
                lieu_detention_id, date_debut_execution, observations,
                statut, is_principale, created_by
            ) VALUES (
                :numero_dossier, :detenu_id, :infraction_id, :infraction_details,
                :date_infraction, :lieu_infraction, :date_oip, :date_omlp,
                :date_jugement, :numero_jugement, :tribunal, :date_mandat_depot,
                :date_liberation_mandat, :peine_valeur, :peine_unite,
                :lieu_detention_id, :date_debut_execution, :observations,
                :statut, :is_principale, :created_by
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':numero_dossier' => $data['numero_dossier'],
                ':detenu_id' => $data['detenu_id'],
                ':infraction_id' => $data['infraction_id'],
                ':infraction_details' => $data['infraction_details'] ?? null,
                ':date_infraction' => $data['date_infraction'] ?? null,
                ':lieu_infraction' => $data['lieu_infraction'] ?? null,
                ':date_oip' => $data['date_oip'] ?? null,
                ':date_omlp' => $data['date_omlp'] ?? null,
                ':date_jugement' => $data['date_jugement'],
                ':numero_jugement' => $data['numero_jugement'] ?? null,
                ':tribunal' => $data['tribunal'] ?? null,
                ':date_mandat_depot' => $data['date_mandat_depot'] ?? null,
                ':date_liberation_mandat' => $data['date_liberation_mandat'] ?? null,
                ':peine_valeur' => $data['peine_valeur'],
                ':peine_unite' => $data['peine_unite'],
                ':lieu_detention_id' => $data['lieu_detention_id'] ?? null,
                ':date_debut_execution' => $data['date_debut_execution'] ?? null,
                ':observations' => $data['observations'] ?? null,
                ':statut' => $data['statut'] ?? 'EN_COURS',
                ':is_principale' => $data['is_principale'] ?? true,
                ':created_by' => $userId
            ]);

            $condamnationId = (int)$this->pdo->lastInsertId();

            // Créer une période de détention si lieu spécifié
            if (!empty($data['lieu_detention_id']) && !empty($data['date_debut_execution'])) {
                $this->createPeriodeDetention(
                    $data['detenu_id'],
                    $condamnationId,
                    $data['lieu_detention_id'],
                    $data['date_debut_execution'],
                    $userId
                );
            }

            $this->pdo->commit();
            return $condamnationId;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Erreur création condamnation: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupérer une condamnation par ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT c.*,
                d.nom_complet as detenu_nom, d.matricule as detenu_matricule,
                i.libelle as infraction_libelle, i.categorie as infraction_categorie,
                l.nom as lieu_detention_nom,
                DATEDIFF(c.date_liberation_effective, NOW()) as jours_restants,
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
                d.nom_complet as detenu, d.matricule,
                i.libelle as infraction, i.categorie,
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
            $sql = "UPDATE condamnations SET
                numero_dossier = :numero_dossier,
                infraction_id = :infraction_id,
                infraction_details = :infraction_details,
                date_infraction = :date_infraction,
                lieu_infraction = :lieu_infraction,
                date_oip = :date_oip,
                date_omlp = :date_omlp,
                date_jugement = :date_jugement,
                numero_jugement = :numero_jugement,
                tribunal = :tribunal,
                date_mandat_depot = :date_mandat_depot,
                date_liberation_mandat = :date_liberation_mandat,
                peine_valeur = :peine_valeur,
                peine_unite = :peine_unite,
                lieu_detention_id = :lieu_detention_id,
                observations = :observations,
                updated_by = :updated_by
                WHERE id = :id AND is_deleted = FALSE";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':numero_dossier' => $data['numero_dossier'],
                ':infraction_id' => $data['infraction_id'],
                ':infraction_details' => $data['infraction_details'] ?? null,
                ':date_infraction' => $data['date_infraction'] ?? null,
                ':lieu_infraction' => $data['lieu_infraction'] ?? null,
                ':date_oip' => $data['date_oip'] ?? null,
                ':date_omlp' => $data['date_omlp'] ?? null,
                ':date_jugement' => $data['date_jugement'],
                ':numero_jugement' => $data['numero_jugement'] ?? null,
                ':tribunal' => $data['tribunal'] ?? null,
                ':date_mandat_depot' => $data['date_mandat_depot'] ?? null,
                ':date_liberation_mandat' => $data['date_liberation_mandat'] ?? null,
                ':peine_valeur' => $data['peine_valeur'],
                ':peine_unite' => $data['peine_unite'],
                ':lieu_detention_id' => $data['lieu_detention_id'] ?? null,
                ':observations' => $data['observations'] ?? null,
                ':updated_by' => $userId,
                ':id' => $id
            ]);
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

            // Appeler la procédure stockée
            $stmt = $this->pdo->prepare("CALL sp_liberer_condamne(:id, :user_id, :motif)");
            $stmt->execute([
                ':id' => $id,
                ':user_id' => $userId,
                ':motif' => $motif
            ]);

            // Fermer les périodes de détention
            $condamnation = $this->getById($id);
            if ($condamnation) {
                $sql = "UPDATE periodes_detention 
                        SET date_fin = NOW() 
                        WHERE condamnation_id = :id AND date_fin IS NULL";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':id' => $id]);
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
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
            $this->recalculerDateLiberation($condamnationId);

            return $remiseId;
        } catch (PDOException $e) {
            error_log("Erreur ajout remise: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Recalculer la date de libération avec remises
     */
    private function recalculerDateLiberation(int $condamnationId): void
    {
        // Récupérer le total des remises
        $sql = "SELECT COALESCE(SUM(jours_remis), 0) as total_remises
                FROM remises_peine
                WHERE condamnation_id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $condamnationId]);
        $totalRemises = (int)$stmt->fetch()['total_remises'];

        // Mettre à jour la date de libération
        $sql = "UPDATE condamnations
                SET date_liberation_effective = DATE_SUB(
                    DATE_SUB(date_liberation_theorique, INTERVAL jours_detention_provisoire_total DAY),
                    INTERVAL :remises DAY
                )
                WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':remises' => $totalRemises,
            ':id' => $condamnationId
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
            date_debut, lieu_detention_id, created_by
        ) VALUES (
            :detenu_id, :condamnation_id, 'EXECUTION_PEINE', 'Exécution de peine',
            :date_debut, :lieu_id, :created_by
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

        // Par infraction
        $stmt = $this->pdo->query("
            SELECT i.libelle, COUNT(*) as nb
            FROM condamnations c
            JOIN infractions i ON c.infraction_id = i.id
            WHERE c.statut = 'EN_COURS' AND c.is_deleted = FALSE
            GROUP BY i.libelle
            ORDER BY nb DESC
            LIMIT 10
        ");
        $stats['par_infraction'] = $stmt->fetchAll();

        return $stats;
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
