<?php
/**
 * Classe DetenuManager
 * Gestion complète des détenus militaires
 */
class DetenuManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Créer un nouveau détenu
     */
    public function create(array $data, int $userId): ?int
    {
        try {
            $sql = "INSERT INTO detenus (
                nom, prenoms, date_naissance, lieu_naissance, nationalite, sexe,
                situation_matrimoniale, nombre_enfants, grade_id, unite_id, 
                matricule_militaire, date_incorporation, telephone, email, adresse,
                personne_contact_nom, personne_contact_telephone, personne_contact_relation,
                photo_path, statut_actuel, created_by
            ) VALUES (
                :nom, :prenoms, :date_naissance, :lieu_naissance, :nationalite, :sexe,
                :situation_matrimoniale, :nombre_enfants, :grade_id, :unite_id,
                :matricule_militaire, :date_incorporation, :telephone, :email, :adresse,
                :personne_contact_nom, :personne_contact_telephone, :personne_contact_relation,
                :photo_path, :statut_actuel, :created_by
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':nom' => $data['nom'],
                ':prenoms' => $data['prenoms'],
                ':date_naissance' => $data['date_naissance'] ?? null,
                ':lieu_naissance' => $data['lieu_naissance'] ?? null,
                ':nationalite' => $data['nationalite'] ?? 'Ivoirienne',
                ':sexe' => $data['sexe'],
                ':situation_matrimoniale' => $data['situation_matrimoniale'] ?? null,
                ':nombre_enfants' => $data['nombre_enfants'] ?? 0,
                ':grade_id' => $data['grade_id'],
                ':unite_id' => $data['unite_id'],
                ':matricule_militaire' => $data['matricule_militaire'] ?? null,
                ':date_incorporation' => $data['date_incorporation'] ?? null,
                ':telephone' => $data['telephone'] ?? null,
                ':email' => $data['email'] ?? null,
                ':adresse' => $data['adresse'] ?? null,
                ':personne_contact_nom' => $data['personne_contact_nom'] ?? null,
                ':personne_contact_telephone' => $data['personne_contact_telephone'] ?? null,
                ':personne_contact_relation' => $data['personne_contact_relation'] ?? null,
                ':photo_path' => $data['photo_path'] ?? null,
                ':statut_actuel' => $data['statut_actuel'] ?? 'DETENTION_PROVISOIRE',
                ':created_by' => $userId
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur création détenu: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Récupérer un détenu par ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT d.*, 
                g.libelle as grade_libelle, g.code as grade_code,
                u.nom as unite_nom, u.code as unite_code,
                TIMESTAMPDIFF(YEAR, d.date_naissance, NOW()) as age
                FROM detenus d
                LEFT JOIN grades g ON d.grade_id = g.id
                LEFT JOIN unites u ON d.unite_id = u.id
                WHERE d.id = :id AND d.is_deleted = FALSE";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Récupérer un détenu par matricule
     */
    public function getByMatricule(string $matricule): ?array
    {
        $sql = "SELECT d.*, 
                g.libelle as grade_libelle,
                u.nom as unite_nom
                FROM detenus d
                LEFT JOIN grades g ON d.grade_id = g.id
                LEFT JOIN unites u ON d.unite_id = u.id
                WHERE d.matricule = :matricule AND d.is_deleted = FALSE";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':matricule' => $matricule]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Liste tous les détenus avec filtres
     */
    public function getAll(array $filters = []): array
    {
        $sql = "SELECT d.id, d.matricule, d.nom_complet, d.date_naissance,
                TIMESTAMPDIFF(YEAR, d.date_naissance, NOW()) as age,
                d.sexe, d.statut_actuel, d.is_multrecidiviste, d.nombre_condamnations,
                g.libelle as grade, g.code as grade_code,
                u.nom as unite, u.code as unite_code,
                d.created_at
                FROM detenus d
                LEFT JOIN grades g ON d.grade_id = g.id
                LEFT JOIN unites u ON d.unite_id = u.id
                WHERE d.is_deleted = FALSE";

        $params = [];

        // Filtres
        if (!empty($filters['statut'])) {
            $sql .= " AND d.statut_actuel = :statut";
            $params[':statut'] = $filters['statut'];
        }

        if (!empty($filters['grade_id'])) {
            $sql .= " AND d.grade_id = :grade_id";
            $params[':grade_id'] = $filters['grade_id'];
        }

        if (!empty($filters['unite_id'])) {
            $sql .= " AND d.unite_id = :unite_id";
            $params[':unite_id'] = $filters['unite_id'];
        }

        if (!empty($filters['multrecidiviste'])) {
            $sql .= " AND d.is_multrecidiviste = TRUE";
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (d.nom_complet LIKE :search OR d.matricule LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY d.created_at DESC";

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
     * Mettre à jour un détenu
     */
    public function update(int $id, array $data, int $userId): bool
    {
        try {
            $sql = "UPDATE detenus SET
                nom = :nom,
                prenoms = :prenoms,
                date_naissance = :date_naissance,
                lieu_naissance = :lieu_naissance,
                nationalite = :nationalite,
                sexe = :sexe,
                situation_matrimoniale = :situation_matrimoniale,
                nombre_enfants = :nombre_enfants,
                grade_id = :grade_id,
                unite_id = :unite_id,
                matricule_militaire = :matricule_militaire,
                date_incorporation = :date_incorporation,
                telephone = :telephone,
                email = :email,
                adresse = :adresse,
                personne_contact_nom = :personne_contact_nom,
                personne_contact_telephone = :personne_contact_telephone,
                personne_contact_relation = :personne_contact_relation,
                updated_by = :updated_by
                WHERE id = :id AND is_deleted = FALSE";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':nom' => $data['nom'],
                ':prenoms' => $data['prenoms'],
                ':date_naissance' => $data['date_naissance'] ?? null,
                ':lieu_naissance' => $data['lieu_naissance'] ?? null,
                ':nationalite' => $data['nationalite'] ?? 'Ivoirienne',
                ':sexe' => $data['sexe'],
                ':situation_matrimoniale' => $data['situation_matrimoniale'] ?? null,
                ':nombre_enfants' => $data['nombre_enfants'] ?? 0,
                ':grade_id' => $data['grade_id'],
                ':unite_id' => $data['unite_id'],
                ':matricule_militaire' => $data['matricule_militaire'] ?? null,
                ':date_incorporation' => $data['date_incorporation'] ?? null,
                ':telephone' => $data['telephone'] ?? null,
                ':email' => $data['email'] ?? null,
                ':adresse' => $data['adresse'] ?? null,
                ':personne_contact_nom' => $data['personne_contact_nom'] ?? null,
                ':personne_contact_telephone' => $data['personne_contact_telephone'] ?? null,
                ':personne_contact_relation' => $data['personne_contact_relation'] ?? null,
                ':updated_by' => $userId,
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("Erreur mise à jour détenu: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprimer un détenu (soft delete)
     */
    public function delete(int $id, int $userId): bool
    {
        try {
            $sql = "UPDATE detenus SET 
                    is_deleted = TRUE,
                    deleted_at = NOW(),
                    deleted_by = :deleted_by
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':deleted_by' => $userId,
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("Erreur suppression détenu: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Changer le statut d'un détenu
     */
    public function changeStatut(int $id, string $newStatut, int $userId): bool
    {
        try {
            $sql = "UPDATE detenus SET
                    statut_actuel = :statut,
                    date_changement_statut = NOW(),
                    updated_by = :updated_by
                    WHERE id = :id AND is_deleted = FALSE";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':statut' => $newStatut,
                ':updated_by' => $userId,
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("Erreur changement statut: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload photo du détenu
     */
    public function uploadPhoto(int $id, array $file): ?string
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            return null;
        }

        if ($file['size'] > $maxSize) {
            return null;
        }

        $uploadDir = __DIR__ . '/../../uploads/photos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'detenu_' . $id . '_' . time() . '.' . $extension;
        $destination = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Mettre à jour le chemin dans la base
            $sql = "UPDATE detenus SET photo_path = :photo WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':photo' => 'uploads/photos/' . $filename,
                ':id' => $id
            ]);

            return 'uploads/photos/' . $filename;
        }

        return null;
    }

    /**
     * Statistiques des détenus
     */
    public function getStatistiques(): array
    {
        $stats = [];

        // Total détenus actifs
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM detenus WHERE is_deleted = FALSE");
        $stats['total'] = (int)$stmt->fetch()['total'];

        // Par statut
        $stmt = $this->pdo->query("
            SELECT statut_actuel, COUNT(*) as nb
            FROM detenus
            WHERE is_deleted = FALSE
            GROUP BY statut_actuel
        ");
        $stats['par_statut'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Multirécidivistes
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as total 
            FROM detenus 
            WHERE is_multrecidiviste = TRUE AND is_deleted = FALSE
        ");
        $stats['multrecidivistes'] = (int)$stmt->fetch()['total'];

        // Par grade
        $stmt = $this->pdo->query("
            SELECT g.libelle, COUNT(*) as nb
            FROM detenus d
            JOIN grades g ON d.grade_id = g.id
            WHERE d.is_deleted = FALSE
            GROUP BY g.libelle
            ORDER BY nb DESC
        ");
        $stats['par_grade'] = $stmt->fetchAll();

        // Par unité
        $stmt = $this->pdo->query("
            SELECT u.nom, COUNT(*) as nb
            FROM detenus d
            JOIN unites u ON d.unite_id = u.id
            WHERE d.is_deleted = FALSE
            GROUP BY u.nom
            ORDER BY nb DESC
            LIMIT 10
        ");
        $stats['par_unite'] = $stmt->fetchAll();

        return $stats;
    }

    /**
     * Recherche avancée
     */
    public function search(string $query): array
    {
        $sql = "SELECT d.id, d.matricule, d.nom_complet, d.statut_actuel,
                g.libelle as grade, u.nom as unite
                FROM detenus d
                LEFT JOIN grades g ON d.grade_id = g.id
                LEFT JOIN unites u ON d.unite_id = u.id
                WHERE d.is_deleted = FALSE
                AND (d.nom_complet LIKE :query 
                    OR d.matricule LIKE :query
                    OR d.matricule_militaire LIKE :query)
                ORDER BY d.nom_complet
                LIMIT 20";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':query' => '%' . $query . '%']);
        return $stmt->fetchAll();
    }

    /**
     * Vérifier si un matricule existe déjà
     */
    public function matriculeExists(string $matricule, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as nb FROM detenus 
                WHERE matricule = :matricule 
                AND is_deleted = FALSE";

        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
        }

        $stmt = $this->pdo->prepare($sql);
        $params = [':matricule' => $matricule];
        
        if ($excludeId) {
            $params[':exclude_id'] = $excludeId;
        }

        $stmt->execute($params);
        return (int)$stmt->fetch()['nb'] > 0;
    }

    /**
     * Obtenir l'historique complet d'un détenu
     */
    public function getHistorique(int $detenuId): array
    {
        $historique = [];

        // Condamnations
        $sql = "SELECT c.*, i.libelle as infraction,
                l.nom as lieu_detention
                FROM condamnations c
                LEFT JOIN infractions i ON c.infraction_id = i.id
                LEFT JOIN lieux_detention l ON c.lieu_detention_id = l.id
                WHERE c.detenu_id = :id AND c.is_deleted = FALSE
                ORDER BY c.date_jugement DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $detenuId]);
        $historique['condamnations'] = $stmt->fetchAll();

        // Périodes de détention
        $sql = "SELECT p.*, l.nom as lieu
                FROM periodes_detention p
                LEFT JOIN lieux_detention l ON p.lieu_detention_id = l.id
                WHERE p.detenu_id = :id
                ORDER BY p.date_debut DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $detenuId]);
        $historique['periodes'] = $stmt->fetchAll();

        return $historique;
    }
}