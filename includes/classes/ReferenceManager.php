<?php

/**
 * Classe ReferenceManager
 * Gestion des données de référence (grades, unités, infractions, lieux)
 */
class ReferenceManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ========================================================================
    // GRADES
    // ========================================================================

    /**
     * Liste tous les grades actifs
     */
    public function getAllGrades(): array
    {
        $sql = "SELECT * FROM grades 
                WHERE is_active = TRUE 
                ORDER BY hierarchie ASC";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Récupérer un grade par ID
     */
    public function getGradeById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM grades WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Créer un grade
     */
    public function createGrade(array $data): ?int
    {
        try {
            $sql = "INSERT INTO grades (code, libelle, hierarchie) 
                    VALUES (:code, :libelle, :hierarchie)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':code' => $data['code'],
                ':libelle' => $data['libelle'],
                ':hierarchie' => $data['hierarchie']
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur création grade: " . $e->getMessage());
            return null;
        }
    }

    // ========================================================================
    // UNITÉS
    // ========================================================================

    /**
     * Liste toutes les unités actives
     */
    public function getAllUnites(string $type = null): array
    {
        $sql = "SELECT * FROM unites WHERE is_active = TRUE";

        if ($type) {
            $sql .= " AND type = :type";
        }

        $sql .= " ORDER BY nom ASC";

        $stmt = $this->pdo->prepare($sql);

        if ($type) {
            $stmt->execute([':type' => $type]);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll();
    }

    /**
     * Récupérer une unité par ID
     */
    public function getUniteById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM unites WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Créer une unité
     */
    public function createUnite(array $data): ?int
    {
        try {
            $sql = "INSERT INTO unites (code, nom, type, localisation) 
                    VALUES (:code, :nom, :type, :localisation)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':code' => $data['code'],
                ':nom' => $data['nom'],
                ':type' => $data['type'],
                ':localisation' => $data['localisation'] ?? null
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur création unité: " . $e->getMessage());
            return null;
        }
    }

    // ========================================================================
    // INFRACTIONS
    // ========================================================================

    /**
     * Liste toutes les infractions actives
     */
    public function getAllInfractions(string $categorie = null): array
    {
        $sql = "SELECT * FROM infractions WHERE is_active = TRUE";

        if ($categorie) {
            $sql .= " AND categorie = :categorie";
        }

        $sql .= " ORDER BY gravite DESC, libelle ASC";

        $stmt = $this->pdo->prepare($sql);

        if ($categorie) {
            $stmt->execute([':categorie' => $categorie]);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll();
    }

    /**
     * Récupérer une infraction par ID
     */
    public function getInfractionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM infractions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Créer une infraction
     */
    public function createInfraction(array $data): ?int
    {
        try {
            $sql = "INSERT INTO infractions (
                    code, libelle, categorie, gravite, 
                    duree_detention_provisoire_mois, description
                ) VALUES (
                    :code, :libelle, :categorie, :gravite,
                    :duree_dp, :description
                )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':code' => $data['code'],
                ':libelle' => $data['libelle'],
                ':categorie' => $data['categorie'],
                ':gravite' => $data['gravite'],
                ':duree_dp' => $data['duree_detention_provisoire_mois'] ?? null,
                ':description' => $data['description'] ?? null
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur création infraction: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mettre à jour une infraction
     */
    public function updateInfraction(int $id, array $data): bool
    {
        try {
            $sql = "UPDATE infractions SET
                    code = :code,
                    libelle = :libelle,
                    categorie = :categorie,
                    gravite = :gravite,
                    duree_detention_provisoire_mois = :duree_dp,
                    description = :description
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':code' => $data['code'],
                ':libelle' => $data['libelle'],
                ':categorie' => $data['categorie'],
                ':gravite' => $data['gravite'],
                ':duree_dp' => $data['duree_detention_provisoire_mois'] ?? null,
                ':description' => $data['description'] ?? null,
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("Erreur mise à jour infraction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprimer une infraction (désactivation)
     */
    public function deleteInfraction(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE infractions SET is_active = FALSE WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("Erreur suppression infraction: " . $e->getMessage());
            return false;
        }
    }

    // ========================================================================
    // LIEUX DE DÉTENTION
    // ========================================================================

    /**
     * Liste tous les lieux actifs
     */
    public function getAllLieuxDetention(string $type = null): array
    {
        $sql = "SELECT * FROM lieux_detention WHERE is_active = TRUE";

        if ($type) {
            $sql .= " AND type = :type";
        }

        $sql .= " ORDER BY nom ASC";

        $stmt = $this->pdo->prepare($sql);

        if ($type) {
            $stmt->execute([':type' => $type]);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll();
    }

    /**
     * Récupérer un lieu par ID
     */
    public function getLieuDetentionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lieux_detention WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Créer un lieu de détention
     */
    public function createLieuDetention(array $data): ?int
    {
        try {
            $sql = "INSERT INTO lieux_detention (
                    code, nom, type, capacite, adresse, ville, telephone
                ) VALUES (
                    :code, :nom, :type, :capacite, :adresse, :ville, :telephone
                )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':code' => $data['code'],
                ':nom' => $data['nom'],
                ':type' => $data['type'],
                ':capacite' => $data['capacite'] ?? null,
                ':adresse' => $data['adresse'] ?? null,
                ':ville' => $data['ville'] ?? null,
                ':telephone' => $data['telephone'] ?? null
            ]);

            return (int)$this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur création lieu: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mettre à jour un lieu de détention
     */
    public function updateLieuDetention(int $id, array $data): bool
    {
        try {
            $sql = "UPDATE lieux_detention SET
                    code = :code,
                    nom = :nom,
                    type = :type,
                    capacite = :capacite,
                    adresse = :adresse,
                    ville = :ville,
                    telephone = :telephone
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':code' => $data['code'],
                ':nom' => $data['nom'],
                ':type' => $data['type'],
                ':capacite' => $data['capacite'] ?? null,
                ':adresse' => $data['adresse'] ?? null,
                ':ville' => $data['ville'] ?? null,
                ':telephone' => $data['telephone'] ?? null,
                ':id' => $id
            ]);
        } catch (PDOException $e) {
            error_log("Erreur mise à jour lieu: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprimer un lieu (désactivation)
     */
    public function deleteLieuDetention(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE lieux_detention SET is_active = FALSE WHERE id = :id");
            return $stmt->execute([':id' => $id]);
        } catch (PDOException $e) {
            error_log("Erreur suppression lieu: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtenir la capacité disponible d'un lieu
     */
    public function getCapaciteDisponible(int $lieuId): array
    {
        // Capacité totale
        $lieu = $this->getLieuDetentionById($lieuId);
        $capaciteMax = $lieu['capacite'] ?? 0;

        // Détenus actuels
        $sql = "SELECT COUNT(DISTINCT p.detenu_id) as nb_detenus
                FROM periodes_detention p
                WHERE p.lieu_detention_id = :lieu_id
                AND p.date_fin IS NULL";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':lieu_id' => $lieuId]);
        $nbDetenus = (int)$stmt->fetch()['nb_detenus'];

        return [
            'capacite_max' => $capaciteMax,
            'nb_detenus' => $nbDetenus,
            'disponible' => $capaciteMax - $nbDetenus,
            'taux_occupation' => $capaciteMax > 0 ? round(($nbDetenus / $capaciteMax) * 100, 1) : 0
        ];
    }

    // ========================================================================
    // UTILITAIRES
    // ========================================================================

    /**
     * Initialiser les données de référence (à utiliser après installation)
     */
    public function initializeDefaultData(): void
    {
        // Grades de base
        $grades = [
            ['code' => 'SCH', 'libelle' => 'Soldat de 2ème Classe', 'hierarchie' => 1],
            ['code' => 'S1C', 'libelle' => 'Soldat de 1ère Classe', 'hierarchie' => 2],
            ['code' => 'CPL', 'libelle' => 'Caporal', 'hierarchie' => 3],
            ['code' => 'CCH', 'libelle' => 'Caporal-Chef', 'hierarchie' => 4],
            ['code' => 'SGT', 'libelle' => 'Sergent', 'hierarchie' => 5],
            ['code' => 'SCH', 'libelle' => 'Sergent-Chef', 'hierarchie' => 6],
            ['code' => 'ADJ', 'libelle' => 'Adjudant', 'hierarchie' => 7],
            ['code' => 'ADC', 'libelle' => 'Adjudant-Chef', 'hierarchie' => 8],
            ['code' => 'MDL', 'libelle' => 'Major', 'hierarchie' => 9],
            ['code' => 'ASP', 'libelle' => 'Aspirant', 'hierarchie' => 10],
            ['code' => 'SLT', 'libelle' => 'Sous-Lieutenant', 'hierarchie' => 11],
            ['code' => 'LTN', 'libelle' => 'Lieutenant', 'hierarchie' => 12],
            ['code' => 'CPT', 'libelle' => 'Capitaine', 'hierarchie' => 13],
            ['code' => 'CDT', 'libelle' => 'Commandant', 'hierarchie' => 14],
            ['code' => 'LCL', 'libelle' => 'Lieutenant-Colonel', 'hierarchie' => 15],
            ['code' => 'COL', 'libelle' => 'Colonel', 'hierarchie' => 16],
            ['code' => 'GBR', 'libelle' => 'Général de Brigade', 'hierarchie' => 17],
            ['code' => 'GDV', 'libelle' => 'Général de Division', 'hierarchie' => 18],
            ['code' => 'GCA', 'libelle' => 'Général de Corps d\'Armée', 'hierarchie' => 19],
            ['code' => 'GAR', 'libelle' => 'Général d\'Armée', 'hierarchie' => 20]
        ];

        foreach ($grades as $grade) {
            try {
                $this->createGrade($grade);
            } catch (Exception $e) {
                // Grade existe déjà
            }
        }

        // Infractions courantes
        $infractions = [
            ['code' => 'DESERTION', 'libelle' => 'Désertion', 'categorie' => 'CRIME', 'gravite' => 8],
            ['code' => 'VOL', 'libelle' => 'Vol', 'categorie' => 'DELIT', 'gravite' => 5],
            ['code' => 'INSUBORDINATION', 'libelle' => 'Insubordination', 'categorie' => 'DELIT', 'gravite' => 6],
            ['code' => 'ABSENCE_IRREGULIERE', 'libelle' => 'Absence irrégulière', 'categorie' => 'CONTRAVENTION', 'gravite' => 3],
            ['code' => 'COUPS_BLESSURES', 'libelle' => 'Coups et blessures', 'categorie' => 'DELIT', 'gravite' => 7],
            ['code' => 'TRAFIC_STUPEFIANTS', 'libelle' => 'Trafic de stupéfiants', 'categorie' => 'CRIME', 'gravite' => 9],
            ['code' => 'MUTINERIE', 'libelle' => 'Mutinerie', 'categorie' => 'CRIME', 'gravite' => 10]
        ];

        foreach ($infractions as $infraction) {
            try {
                $this->createInfraction($infraction);
            } catch (Exception $e) {
                // Infraction existe déjà
            }
        }
    }

    /**
     * Vérifier l'intégrité des données de référence
     */
    public function checkIntegrity(): array
    {
        $issues = [];

        // Vérifier les grades
        $stmt = $this->pdo->query("SELECT COUNT(*) as nb FROM grades WHERE is_active = TRUE");
        if ((int)$stmt->fetch()['nb'] === 0) {
            $issues[] = "Aucun grade actif dans le système";
        }

        // Vérifier les unités
        $stmt = $this->pdo->query("SELECT COUNT(*) as nb FROM unites WHERE is_active = TRUE");
        if ((int)$stmt->fetch()['nb'] === 0) {
            $issues[] = "Aucune unité active dans le système";
        }

        // Vérifier les infractions
        $stmt = $this->pdo->query("SELECT COUNT(*) as nb FROM infractions WHERE is_active = TRUE");
        if ((int)$stmt->fetch()['nb'] === 0) {
            $issues[] = "Aucune infraction définie dans le système";
        }

        // Vérifier les lieux
        $stmt = $this->pdo->query("SELECT COUNT(*) as nb FROM lieux_detention WHERE is_active = TRUE");
        if ((int)$stmt->fetch()['nb'] === 0) {
            $issues[] = "Aucun lieu de détention défini";
        }

        return $issues;
    }
}
