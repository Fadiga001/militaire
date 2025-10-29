# 🛡️ Système de Gestion des Détenus Militaires

## 📋 Description du Projet

Système complet de gestion des détenus militaires développé en **PHP pur** avec base de données **MySQL**.
Ce système permet la gestion complète des détenus, condamnations, infractions et lieux de détention avec calcul automatique des dates de libération.

## ✨ Fonctionnalités Principales

### 🎯 Modules Opérationnels (100%)

#### 1. **Dashboard** ✅

- Statistiques en temps réel
- Graphiques interactifs (Chart.js)
- Alertes libérations imminentes (30 jours)
- Répartition par grade, unité, infraction
- Évolution mensuelle des détenus

#### 2. **Module Détenus** ✅

- ✅ Liste avec filtres avancés (DataTables)
- ✅ Ajout complet (avec upload photo)
- ✅ Profil détaillé + timeline condamnations
- ✅ Modification complète
- ✅ Suppression (soft delete)
- ✅ Recherche multi-critères
- ✅ Badge multirécidiviste

#### 3. **Module Condamnations** ✅

- ✅ Liste avec code couleur alertes
- ✅ Ajout avec calculs automatiques
- ✅ Détails complets + calculs
- ✅ Modification + recalculs
- ✅ Remises de peine
- ✅ Libération condamné
- ✅ Timeline remises

#### 4. **Sidebar & Navigation** ✅

- Menu hiérarchique avec submenus
- Indicateur page active
- Badge notifications (temps réel)
- Responsive (mobile/tablette)
- Design militaire moderne

### 🔢 Calculs Automatiques

Le système effectue automatiquement :

```
✓ Peine totale en jours
✓ Jours de détention provisoire (OIP + Mandat)
✓ Date de libération théorique
✓ Date de libération effective (avec déductions)
✓ Jours restants avant libération
✓ Niveau d'alerte (CRITIQUE, URGENT, ATTENTION, etc.)
✓ Recalcul après remises de peine
```

## 🏗️ Architecture du Projet

```
detenusMilitaires/
├── index.php                          # Page de connexion
├── .env                               # Configuration DB
├── composer.json                      # Dépendances (dotenv)
│
├── assets/
│   ├── css/
│   │   ├── bootstrap.min.css
│   │   ├── kaiadmin.min.css
│   │   └── recu-style.css
│   ├── js/
│   │   ├── core/
│   │   ├── plugin/
│   │   └── kaiadmin.min.js
│   └── img/
│       └── logo.png
│
├── includes/
│   ├── db.php                         # Connexion PDO + dotenv
│   ├── logs.php                       # Fonction log_activity()
│   └── classes/
│       ├── autoload.php               # Chargeur automatique
│       ├── DetenuManager.php          # Gestion détenus
│       ├── CondamnationManager.php    # Gestion condamnations
│       └── ReferenceManager.php       # Données référence
│
├── pages/
│   ├── dash/
│   │   ├── dashboard.php              # Tableau de bord
│   │   ├── register.php               # Inscription admin
│   │   └── logout.php                 # Déconnexion
│   │
│   ├── detenus/
│   │   ├── detenus.php                # Liste détenus
│   │   ├── ajouter_detenu.php         # Ajout
│   │   ├── voir_detenu.php            # Détails
│   │   └── modifier_detenu.php        # Modification
│   │
│   ├── condamnations/
│   │   ├── condamnations.php          # Liste
│   │   ├── ajouter_condamnation.php   # Ajout
│   │   ├── voir_condamnation.php      # Détails
│   │   ├── modifier_condamnation.php  # Modification
│   │   └── ajouter_remise.php         # Remise peine
│   │
│   ├── infractions/                   # À créer
│   ├── lieux-detention/               # À créer
│   ├── references/                    # À créer
│   ├── rapports/                      # À créer
│   ├── notifications/                 # À créer
│   ├── utilisateurs/                  # À créer
│   └── logs/                          # À créer
│
├── requires/
│   ├── link.php                       # Liens CSS/JS
│   ├── sidebar.php                    # Menu navigation
│   ├── main-header.php                # Header page
│   └── script.php                     # Scripts JS
│
├── uploads/
│   └── photos/                        # Photos détenus
│
├── docs/
│   └── base_de_donnees.sql            # Structure DB
│
└── vendor/                            # Dépendances Composer
```

## 🗄️ Base de Données

### Tables Principales

| Table                  | Description             | Statut |
| ---------------------- | ----------------------- | ------ |
| **users**              | Utilisateurs du système | ✅     |
| **detenus**            | Informations détenus    | ✅     |
| **condamnations**      | Condamnations           | ✅     |
| **grades**             | Grades militaires       | ✅     |
| **unites**             | Unités militaires       | ✅     |
| **infractions**        | Types d'infractions     | ✅     |
| **lieux_detention**    | Lieux de détention      | ✅     |
| **remises_peine**      | Remises de peine        | ✅     |
| **periodes_detention** | Historique détention    | ✅     |
| **documents**          | Documents attachés      | ✅     |
| **notifications**      | Alertes système         | ✅     |
| **audit_logs**         | Logs d'activité         | ✅     |

### Vues SQL

- `v_detenus_complets` : Détenus avec grade/unité
- `v_condamnations_actives` : Condamnations en cours
- `v_statistiques` : Stats globales

### Triggers

- `trg_detenu_before_insert` : Génération matricule auto
- `trg_condamnation_after_insert` : Mise à jour nb condamnations
- `trg_condamnation_before_update` : Calcul dates libération
- `trg_periode_detention_before_*` : Calcul durées

### Procédures Stockées

- `sp_liberer_condamne()` : Procédure complète de libération
- `sp_generer_notifications()` : Génération notifications auto

## 🚀 Installation

### Prérequis

```bash
PHP >= 7.4
MySQL >= 5.7
Composer
Apache/Nginx
```

### Étapes d'Installation

1. **Cloner le projet**

```bash
git clone [url-projet]
cd detenusMilitaires
```

2. **Installer les dépendances**

```bash
composer install
```

3. **Configurer la base de données**

```bash
# Créer la base
mysql -u root -p < docs/base_de_donnees.sql

# Configurer .env
cp .env.example .env
# Éditer .env avec vos paramètres
```

4. **Créer les dossiers uploads**

```bash
mkdir -p uploads/photos
chmod 755 uploads/photos
```

5. **Créer un utilisateur admin**

```bash
# Accéder à : http://localhost/detenusMilitaires/pages/dash/register.php
```

6. **Se connecter**

```bash
# Accéder à : http://localhost/detenusMilitaires/
```

## 🔐 Sécurité

### Fonctionnalités Implémentées

- ✅ Authentification sessions PHP
- ✅ Hashage passwords (password_hash)
- ✅ Protection XSS (htmlspecialchars)
- ✅ Requêtes préparées PDO (SQL injection)
- ✅ Verrouillage compte (5 tentatives)
- ✅ Logs d'activité complets
- ✅ Soft delete (récupérable)
- ✅ Validation côté serveur
- ✅ Variables d'environnement (.env)

### Rôles & Permissions

```php
ADMIN
├─ Toutes les permissions
├─ Supprimer détenus
├─ Libérer condamnés
├─ Gérer utilisateurs
└─ Accès logs audit

USER
├─ Voir détenus/condamnations
├─ Ajouter détenus/condamnations
├─ Modifier détenus/condamnations
└─ Ajouter remises peine

READONLY
├─ Voir détenus
└─ Voir condamnations
```

## 📊 Code Couleur & Statuts

### Statuts Détenus

- 🔴 **CONDAMNE** → Rouge
- 🟡 **DETENTION_PROVISOIRE** → Jaune
- 🟢 **LIBRE** → Vert
- ⚫ **EVADE** → Noir
- ⚪ **DECEDE** → Gris

### Alertes Libération

- ⚫ **LIBERABLE** → Date dépassée
- 🔴 **CRITIQUE** → ≤ 1 jour
- 🟠 **URGENT** → ≤ 7 jours
- 🟡 **ATTENTION** → ≤ 14 jours
- 🔵 **A_SUIVRE** → ≤ 30 jours
- 🟢 **NORMAL** → > 30 jours

### Catégories Infractions

- 🔴 **CRIME** → Rouge
- 🟡 **DELIT** → Jaune
- 🔵 **CONTRAVENTION** → Bleu

## 📈 Statistiques Disponibles

### Dashboard

- Total détenus actifs
- Condamnés vs Détention provisoire
- Libérations critiques (7 jours)
- Multirécidivistes
- Évolution mensuelle (6 mois)
- Top 5 grades
- Top 5 infractions
- Répartition par catégorie

### Module Détenus

- Total par statut
- Par grade/unité
- Âge moyen
- Taux multirécidivisme

### Module Condamnations

- Condamnations actives
- Libérations imminentes
- Par infraction
- Durée moyenne peines

## 🛠️ Technologies Utilisées

### Backend

- **PHP 7.4+** (pur, sans framework)
- **MySQL 5.7+**
- **PDO** (requêtes préparées)
- **Composer** (gestion dépendances)

### Frontend

- **Bootstrap 5** (design responsive)
- **jQuery 3.7** (manipulation DOM)
- **Chart.js** (graphiques)
- **DataTables** (tableaux)
- **Font Awesome 6** (icônes)

### Librairies

- **vlucas/phpdotenv** (variables environnement)

## 📝 API Classes PHP

### DetenuManager

```php
// Création
create(array $data, int $userId): ?int

// Lecture
getById(int $id): ?array
getByMatricule(string $matricule): ?array
getAll(array $filters = []): array

// Mise à jour
update(int $id, array $data, int $userId): bool
changeStatut(int $id, string $statut, int $userId): bool

// Suppression
delete(int $id, int $userId): bool

// Utilitaires
uploadPhoto(int $id, array $file): ?string
getStatistiques(): array
getHistorique(int $detenuId): array
search(string $query): array
matriculeExists(string $matricule, ?int $excludeId): bool
```

### CondamnationManager

```php
// Création
create(array $data, int $userId): ?int

// Lecture
getById(int $id): ?array
getAll(array $filters = []): array

// Mise à jour
update(int $id, array $data, int $userId): bool

// Libération
liberer(int $id, string $motif, int $userId): bool

// Remises
addRemise(int $condamnationId, array $data, int $userId): ?int
getRemises(int $condamnationId): array

// Utilitaires
getStatistiques(): array
numeroDossierExists(string $numero, ?int $excludeId): bool
```

### ReferenceManager

```php
// Grades
getAllGrades(): array
getGradeById(int $id): ?array
createGrade(array $data): ?int

// Unités
getAllUnites(string $type = null): array
getUniteById(int $id): ?array
createUnite(array $data): ?int

// Infractions
getAllInfractions(string $categorie = null): array
getInfractionById(int $id): ?array
createInfraction(array $data): ?int
updateInfraction(int $id, array $data): bool
deleteInfraction(int $id): bool

// Lieux
getAllLieuxDetention(string $type = null): array
getLieuDetentionById(int $id): ?array
createLieuDetention(array $data): ?int
updateLieuDetention(int $id, array $data): bool
deleteLieuDetention(int $id): bool
getCapaciteDisponible(int $lieuId): array

// Utilitaires
initializeDefaultData(): void
checkIntegrity(): array
```

## 🔄 Workflow Typique

### Ajout d'un Nouveau Détenu

```
1. Accéder à "Détenus > Ajouter un Détenu"
2. Remplir les informations :
   ├─ Personnelles (nom, prénoms, date naissance, etc.)
   ├─ Militaires (grade, unité, matricule militaire)
   ├─ Contact (téléphone, email, adresse)
   └─ Upload photo (optionnel)
3. Validation automatique
4. Génération matricule détenu automatique
5. Création dans la base
6. Log activité
7. Redirection vers profil détenu
```

### Ajout d'une Condamnation

```
1. Accéder à "Condamnations > Ajouter une Condamnation"
2. Remplir les informations :
   ├─ Dossier (N°, détenu, infraction)
   ├─ Procédure (OIP, OMLP, mandats)
   ├─ Jugement (date, peine)
   └─ Exécution (lieu, observations)
3. Validation automatique
4. Calculs automatiques :
   ├─ Peine en jours
   ├─ Jours détention provisoire
   ├─ Date libération théorique
   └─ Date libération effective
5. Création dans la base
6. Création période détention
7. Mise à jour statut détenu
8. Log activité
9. Redirection vers détails condamnation
```

### Ajout d'une Remise de Peine

```
1. Depuis détails condamnation > "Ajouter Remise"
2. Remplir :
   ├─ Type (gracieuse, bonne conduite, amnistie)
   ├─ Motif
   ├─ Jours remis (max calculé)
   ├─ Date décision
   └─ Référence/Autorité
3. Validation (≤ peine nette)
4. Création remise
5. Recalcul automatique date libération effective
6. Log activité
7. Retour détails condamnation
```

## 📞 Support & Contact

Pour toute question ou problème :

- 📧 Email: support@detenusmilitaires.ci
- 📱 Téléphone: +225 XX XX XX XX XX
- 📍 Adresse: Abidjan, Côte d'Ivoire

## 📄 Licence

Ce projet est sous licence propriétaire. Tous droits réservés.

## 🎯 Roadmap

### ✅ Phase 1 - Complétée

- [x] Dashboard
- [x] Module Détenus
- [x] Module Condamnations
- [x] Sidebar & Navigation

### 🔄 Phase 2 - En cours

- [ ] Module Infractions
- [ ] Module Lieux de Détention
- [ ] Module Grades & Unités
- [ ] Système Notifications

### 📅 Phase 3 - Planifiée

- [ ] Module Rapports
- [ ] Module Utilisateurs
- [ ] Logs d'Audit
- [ ] Paramètres Système

### 🚀 Phase 4 - Future

- [ ] API REST
- [ ] Export PDF avancé
- [ ] Envoi email automatique
- [ ] Application mobile
- [ ] Multi-langue

## 🏆 Crédits

Développé avec ❤️ pour la gestion des détenus militaires

**Version:** 1.0.0  
**Date:** Octobre 2025  
**Statut:** En production

---

© 2025 Système de Gestion des Détenus Militaires. Tous droits réservés.
