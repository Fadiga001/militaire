<?php

/**
 * Classe DetentionProvisoireManager
 * Gestion des détentions provisoires (avant jugement)
 * Séparation claire du flux: Détenu → Détention Provisoire → Condamnation
 */
class DetentionProvisoireManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Créer une nouvelle détention provisoire
     */
    public function create(array $data, int $userId): ?int
    {
        try {
            $sql = "INSERT INTO detentions_provisoires (
                numero_dossier, detenu_id, infraction_presume_id, infraction_details,
                date_faits, lieu_faits, date_arrestation, date_oip, date_mandat_depot,
                numero_mandat, autorite_mandante, motif_detention, date_debut_detention,
                lieu_detention_id, observations, created_by
            ) VALUES (
                :numero_dossier, :detenu_id, :infraction_presume_id, :infraction_details,
                :date_faits, :lieu_faits, :date_arrestation, :date_oip, :date_mandat_depot,
                :numero_mandat, :autorite_mandante, :motif_detention, :date_debut_detention,
                :lieu_detention_id, :observations, :created_by
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':numero_dossier' => $data['numero_dossier'],
                ':detenu_id' => $data['detenu_id'],
                ':infraction_presume_id' => $data['infraction_presume_id'],
                ':infraction_details' => $data['infraction_details'] ?? null,
                ':date_faits' => $data['date_faits'] ?? null,
                ':lieu_faits' => $data['lieu_faits'] ?? null,
                ':date_arrestation' => $data['date_arrestation'],
                ':date_oip' => $data['date_oip'] ?? null,
                ':date_mandat_depot' => $data['date_mandat_depot'] ?? null,
                ':numero_mandat' => $data['numero_mandat'] ?? null,
                ':autorite_mandante' => $data['autorite_mandante'] ?? null,
                ':motif_detention' => $data['motif_detention'] ?? null,
                ':date_debut_detention' => $data['date_debut_detention'] ?? $data['date_mandat_depot'] ?? $data['date_arrestation'],
                ':lieu_detention_id' => $data['lieu_detention_id'],
                ':observations' => $data['observations'] ?? null,
                ':created_by' => $userId
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur création détention provisoire: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupérer toutes les détentions provisoires
     */
    public function getAll(array $filters = []): array
    {
        $sql = "SELECT * FROM v_detentions_provisoires WHERE 1=1";
        $params = [];

        // Filtre par priorité/alerte
        if (!empty($filters['priorite'])) {
            switch ($filters['priorite']) {
                case 'DEPASSEE':
                    $sql .= " AND niveau_alerte = 'DEPASSEE'";
                    break;
                case 'CRITIQUE':
                    $sql .= " AND niveau_alerte = 'CRITIQUE'";
                    break;
                case 'URGENT':
                    $sql .= " AND niveau_alerte = 'URGENT'";
                    break;
                case 'ATTENTION':
                    $sql .= " AND niveau_alerte = 'ATTENTION'";
                    break;
            }
        }

        // Filtre par catégorie
        if (!empty($filters['categorie'])) {
            $sql .= " AND categorie_infraction = :categorie";
            $params[':categorie'] = $filters['categorie'];
        }

        // Filtre par lieu
        if (!empty($filters['lieu_id'])) {
            $sql .= " AND lieu_detention_id = :lieu_id";
            $params[':lieu_id'] = $filters['lieu_id'];
        }

        // Recherche
        if (!empty($filters['search'])) {
            $sql .= " AND (detenu LIKE :search OR numero_dossier LIKE :search OR matricule LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY date_limite_legale ASC";

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
     * Récupérer une détention provisoire par ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT * FROM v_detentions_provisoires WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Récupérer par détenu
     */
    public function getByDetenu(int $detenuId): array
    {
        $sql = "SELECT dp.*, i.libelle as infraction, l.nom as lieu
                FROM detentions_provisoires dp
                LEFT JOIN infractions i ON dp.infraction_presume_id = i.id
                LEFT JOIN lieux_detention l ON dp.lieu_detention_id = l.id
                WHERE dp.detenu_id = :detenu_id
                ORDER BY dp.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':detenu_id' => $detenuId]);
        return $stmt->fetchAll();
    }

    /**
     * Libérer un détenu en détention provisoire
     */
    public function liberer(int $detentionId, string $motif, int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("CALL sp_liberer_detention_provisoire(:id, :user_id, :motif)");
            $stmt->execute([
                ':id' => $detentionId,
                ':user_id' => $userId,
                ':motif' => $motif
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Erreur libération: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Convertir en condamnation (après jugement)
     */
    public function convertirEnCondamnation(int $detentionId, array $dataCondamnation, int $userId): ?int
    {
        try {
            $stmt = $this->pdo->prepare("
                CALL sp_convertir_en_condamnation(
                    :detention_id,
                    :date_jugement,
                    :numero_jugement,
                    :tribunal,
                    :peine_valeur,
                    :peine_unite,
                    :user_id
                )
            ");

            $stmt->execute([
                ':detention_id' => $detentionId,
                ':date_jugement' => $dataCondamnation['date_jugement'],
                ':numero_jugement' => $dataCondamnation['numero_jugement'] ?? null,
                ':tribunal' => $dataCondamnation['tribunal'] ?? null,
                ':peine_valeur' => $dataCondamnation['peine_valeur'],
                ':peine_unite' => $dataCondamnation['peine_unite'],
                ':user_id' => $userId
            ]);

            $result = $stmt->fetch();
            return (int)($result['condamnation_id'] ?? null);
        } catch (PDOException $e) {
            error_log("Erreur conversion: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtenir les statistiques
     */
    public function getStatistiques(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM v_stats_detentions_provisoires");
        return $stmt->fetch() ?: [];
    }

    /**
     * Récupérer les détentions dépassées
     */
    public function getDepassees(): array
    {
        return $this->getAll(['priorite' => 'DEPASSEE']);
    }

    /**
     * Récupérer les détentions critiques (≤ 3 jours)
     */
    public function getCritiques(): array
    {
        return $this->getAll(['priorite' => 'CRITIQUE']);
    }

    /**
     * Récupérer les détentions urgentes (≤ 7 jours)
     */
    public function getUrgentes(): array
    {
        return $this->getAll(['priorite' => 'URGENT']);
    }

    /**
     * Vérifier si une détention est dépassée
     */
    public function estDepassee(int $detentionId): bool
    {
        $detention = $this->getById($detentionId);
        return $detention && $detention['jours_restants'] < 0;
    }

    /**
     * Mettre à jour une détention provisoire
     */
    public function update(int $id, array $data, int $userId): bool
    {
        try {
            $sql = "UPDATE detentions_provisoires 
                    SET infraction_presume_id = :infraction_id,
                        infraction_details = :details,
                        date_oip = :date_oip,
                        date_mandat_depot = :date_mandat,
                        numero_mandat = :numero_mandat,
                        autorite_mandante = :autorite,
                        lieu_detention_id = :lieu_id,
                        observations = :observations,
                        updated_by = :user_id
                    WHERE id = :id AND statut = 'EN_COURS'";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':infraction_id' => $data['infraction_presume_id'],
                ':details' => $data['infraction_details'] ?? null,
                ':date_oip' => $data['date_oip'] ?? null,
                ':date_mandat' => $data['date_mandat_depot'] ?? null,
                ':numero_mandat' => $data['numero_mandat'] ?? null,
                ':autorite' => $data['autorite_mandante'] ?? null,
                ':lieu_id' => $data['lieu_detention_id'],
                ':observations' => $data['observations'] ?? null,
                ':user_id' => $userId,
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("Erreur mise à jour: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifier les alertes
     */
    public function verifierAlertes(): array
    {
        try {
            $stmt = $this->pdo->query("CALL sp_verifier_detentions_provisoires()");
            return $stmt->fetch() ?: [];
        } catch (PDOException $e) {
            error_log("Erreur vérification alertes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtenir les détentions par catégorie
     */
    public function getParCategorie(): array
    {
        $sql = "SELECT 
                categorie_infraction,
                COUNT(*) as nb,
                AVG(jours_detention_actuel) as duree_moyenne,
                SUM(CASE WHEN niveau_alerte = 'DEPASSEE' THEN 1 ELSE 0 END) as depassees
                FROM v_detentions_provisoires
                GROUP BY categorie_infraction
                ORDER BY nb DESC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Obtenir le tableau de bord complet
     */
    public function getDashboard(): array
    {
        return [
            'statistiques' => $this->getStatistiques(),
            'depassees' => $this->getDepassees(),
            'critiques' => $this->getCritiques(),
            'urgentes' => $this->getUrgentes(),
            'par_categorie' => $this->getParCategorie()
        ];
    }

    /**
     * Vérifier si un numéro de dossier existe
     */
    public function numeroDossierExists(string $numero, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as nb FROM detentions_provisoires WHERE numero_dossier = :numero";
        $params = [':numero' => $numero];

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetch()['nb'] > 0;
    }

    /**
     * Calculer la date limite théorique
     */
    public static function calculerDateLimite(string $categorie, string $dateDebut): string
    {
        $mois = match ($categorie) {
            'CRIME' => 24,
            'DELIT' => 18,
            default => 6
        };

        $date = new DateTime($dateDebut);
        $date->modify("+{$mois} months");
        return $date->format('Y-m-d');
    }

    /**
     * Obtenir l'historique d'un détenu en détention provisoire
     */
    public function getHistorique(int $detenuId): array
    {
        $sql = "SELECT dp.*,
                i.libelle as infraction,
                l.nom as lieu,
                DATEDIFF(COALESCE(dp.date_fin, CURDATE()), dp.date_debut_detention) as duree_totale,
                c.numero_jugement as numero_jugement_condamnation
                FROM detentions_provisoires dp
                LEFT JOIN infractions i ON dp.infraction_presume_id = i.id
                LEFT JOIN lieux_detention l ON dp.lieu_detention_id = l.id
                LEFT JOIN condamnations c ON dp.condamnation_id = c.id
                WHERE dp.detenu_id = :detenu_id
                ORDER BY dp.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':detenu_id' => $detenuId]);
        return $stmt->fetchAll();
    }

    /**
     * Générer un rapport
     */
    public function genererRapport(array $filters = []): array
    {
        $detentions = $this->getAll($filters);
        $stats = $this->getStatistiques();
        $parCategorie = $this->getParCategorie();

        return [
            'detentions' => $detentions,
            'statistiques' => $stats,
            'par_categorie' => $parCategorie,
            'date_generation' => date('Y-m-d H:i:s'),
            'total' => count($detentions)
        ];
    }
}