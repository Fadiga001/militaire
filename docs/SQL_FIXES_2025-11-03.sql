-- Fixes cohérence schéma / code (2025-11-03)

-- 1) Étendre notifications.entity_type pour supporter 'DETENTION_PROVISOIRE'
ALTER TABLE `notifications`
  MODIFY `entity_type` ENUM('DETENU','CONDAMNATION','DETENTION_PROVISOIRE')
  COLLATE utf8mb4_unicode_ci NOT NULL;

-- 2) Corriger la vue v_statistiques (alias dupliqué)
DROP VIEW IF EXISTS `v_statistiques`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_statistiques` AS
SELECT
  (SELECT COUNT(*) FROM `detenus` WHERE `is_deleted` = FALSE) AS `total_detenus`,
  (SELECT COUNT(*) FROM `detenus` WHERE `statut_actuel` = 'CONDAMNE' AND `is_deleted` = FALSE) AS `total_condamnes`,
  (SELECT COUNT(*) FROM `detenus` WHERE `statut_actuel` = 'DETENTION_PROVISOIRE' AND `is_deleted` = FALSE) AS `total_detention_provisoire`,
  (SELECT COUNT(*) FROM `detenus` WHERE `statut_actuel` = 'LIBRE' AND `is_deleted` = FALSE) AS `total_liberes`,
  (SELECT COUNT(*) FROM `detenus` WHERE `is_multrecidiviste` = TRUE AND `is_deleted` = FALSE) AS `total_multrecidivistes`,
  (SELECT COUNT(*) FROM `condamnations` WHERE `statut` = 'EN_COURS' AND `is_deleted` = FALSE) AS `condamnations_actives`;

-- 3) Recréer la vue v_detentions_provisoires avec l'ID du lieu pour permettre le filtre par lieu_detention_id
DROP VIEW IF EXISTS `v_detentions_provisoires`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_detentions_provisoires` AS
SELECT
  dp.id AS id,
  dp.numero_dossier AS numero_dossier,
  d.matricule AS matricule,
  d.nom_complet AS detenu,
  d.grade_id AS grade_id,
  g.code AS grade_code,
  g.libelle AS grade,
  u.nom AS unite,
  i.libelle AS infraction_presume,
  dp.categorie_infraction AS categorie_infraction,
  dp.date_debut_detention AS date_debut_detention,
  dp.date_limite_legale AS date_limite_legale,
  (TO_DAYS(dp.date_limite_legale) - TO_DAYS(CURDATE())) AS jours_restants,
  (TO_DAYS(CURDATE()) - TO_DAYS(dp.date_debut_detention)) AS jours_detention_actuel,
  ROUND(((TO_DAYS(CURDATE()) - TO_DAYS(dp.date_debut_detention)) / 30), 1) AS mois_detention_actuel,
  dp.duree_max_mois AS duree_max_mois,
  ROUND((((TO_DAYS(CURDATE()) - TO_DAYS(dp.date_debut_detention)) / (dp.duree_max_mois * 30)) * 100), 1) AS pourcentage_duree,
  CASE
    WHEN (TO_DAYS(dp.date_limite_legale) - TO_DAYS(CURDATE())) < 0 THEN 'DEPASSEE'
    WHEN (TO_DAYS(dp.date_limite_legale) - TO_DAYS(CURDATE())) <= 3 THEN 'CRITIQUE'
    WHEN (TO_DAYS(dp.date_limite_legale) - TO_DAYS(CURDATE())) <= 7 THEN 'URGENT'
    WHEN (TO_DAYS(dp.date_limite_legale) - TO_DAYS(CURDATE())) <= 30 THEN 'ATTENTION'
    ELSE 'NORMAL'
  END AS niveau_alerte,
  CASE
    WHEN (TO_DAYS(dp.date_limite_legale) - TO_DAYS(CURDATE())) < 0 THEN 'LIBÉRATION OBLIGATOIRE'
    WHEN (TO_DAYS(dp.date_limite_legale) - TO_DAYS(CURDATE())) <= 3 THEN 'TRÈS CRITIQUE'
    WHEN (TO_DAYS(dp.date_limite_legale) - TO_DAYS(CURDATE())) <= 7 THEN 'URGENT'
    WHEN (TO_DAYS(dp.date_limite_legale) - TO_DAYS(CURDATE())) <= 30 THEN 'À SURVEILLER'
    ELSE 'NORMAL'
  END AS priorite,
  l.nom AS lieu_detention,
  dp.lieu_detention_id AS lieu_detention_id,
  dp.statut AS statut,
  dp.date_oip AS date_oip,
  dp.date_mandat_depot AS date_mandat_depot,
  dp.observations AS observations,
  dp.created_at AS created_at,
  dp.updated_at AS updated_at
FROM `detentions_provisoires` dp
JOIN `detenus` d ON dp.detenu_id = d.id
LEFT JOIN `grades` g ON d.grade_id = g.id
LEFT JOIN `unites` u ON d.unite_id = u.id
LEFT JOIN `infractions` i ON dp.infraction_presume_id = i.id
LEFT JOIN `lieux_detention` l ON dp.lieu_detention_id = l.id
WHERE dp.statut = 'EN_COURS' AND d.is_deleted = FALSE
ORDER BY dp.date_limite_legale ASC;

-- 4) Corriger la vue v_stats_detentions_provisoires (nom de source en FROM)
DROP VIEW IF EXISTS `v_stats_detentions_provisoires`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_stats_detentions_provisoires` AS
SELECT
  COUNT(*) AS total,
  SUM(CASE WHEN v.niveau_alerte = 'DEPASSEE' THEN 1 ELSE 0 END) AS depassees,
  SUM(CASE WHEN v.niveau_alerte = 'CRITIQUE' THEN 1 ELSE 0 END) AS critiques,
  SUM(CASE WHEN v.niveau_alerte = 'URGENT' THEN 1 ELSE 0 END) AS urgentes,
  SUM(CASE WHEN v.niveau_alerte = 'ATTENTION' THEN 1 ELSE 0 END) AS attention,
  SUM(CASE WHEN v.categorie_infraction = 'CRIME' THEN 1 ELSE 0 END) AS crimes,
  SUM(CASE WHEN v.categorie_infraction = 'DELIT' THEN 1 ELSE 0 END) AS delits,
  ROUND(AVG(v.jours_detention_actuel), 0) AS duree_moyenne_jours,
  ROUND(AVG(v.pourcentage_duree), 1) AS pourcentage_moyen_duree,
  MAX(v.jours_detention_actuel) AS duree_max_jours,
  MIN(v.jours_restants) AS jours_min_restants
FROM `v_detentions_provisoires` v;

-- 5) Calcul des dates de libération à l'insertion des condamnations (même logique que BEFORE UPDATE)
DROP TRIGGER IF EXISTS `trg_condamnation_before_insert_compute`;
DELIMITER $$
CREATE TRIGGER `trg_condamnation_before_insert_compute`
BEFORE INSERT ON `condamnations`
FOR EACH ROW
BEGIN
  IF NEW.date_jugement IS NOT NULL AND NEW.peine_valeur > 0 THEN
    SET NEW.date_liberation_theorique = DATE_ADD(
      NEW.date_jugement,
      INTERVAL (
        CASE NEW.peine_unite
          WHEN 'JOUR' THEN NEW.peine_valeur
          WHEN 'MOIS' THEN NEW.peine_valeur * 30
          WHEN 'ANNEE' THEN NEW.peine_valeur * 365
        END
      ) DAY
    );

    SET NEW.jours_detention_provisoire_oip = COALESCE(DATEDIFF(NEW.date_omlp, NEW.date_oip), 0);

    IF NEW.date_mandat_depot IS NOT NULL THEN
      SET NEW.jours_detention_provisoire_mandat = DATEDIFF(COALESCE(NEW.date_liberation_mandat, NEW.date_jugement), NEW.date_mandat_depot);
    ELSE
      SET NEW.jours_detention_provisoire_mandat = 0;
    END IF;

    SET NEW.date_liberation_effective = DATE_SUB(
      NEW.date_liberation_theorique,
      INTERVAL (NEW.jours_detention_provisoire_oip + NEW.jours_detention_provisoire_mandat) DAY
    );
  END IF;
END$$
DELIMITER ;
