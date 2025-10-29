# 🚀 Guide d'Installation & Déploiement

## 📋 Table des Matières

1. [Prérequis](#prérequis)
2. [Installation Locale](#installation-locale)
3. [Configuration](#configuration)
4. [Initialisation Base de Données](#initialisation-base-de-données)
5. [Premier Utilisateur](#premier-utilisateur)
6. [Vérification](#vérification)
7. [Déploiement Production](#déploiement-production)
8. [Troubleshooting](#troubleshooting)

---

## 🔧 Prérequis

### Serveur

```bash
✓ PHP >= 7.4
✓ MySQL >= 5.7 ou MariaDB >= 10.3
✓ Apache ou Nginx
✓ Composer (gestionnaire dépendances PHP)
✓ Git (optionnel)
```

### Extensions PHP Requises

```bash
php -m | grep -E "pdo|pdo_mysql|mbstring|json|openssl"
```

Vérifier que ces extensions sont installées :

- `pdo`
- `pdo_mysql`
- `mbstring`
- `json`
- `openssl`
- `fileinfo`
- `gd` (pour manipulation images)

### Installation Extensions (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install php-mysql php-mbstring php-gd php-xml php-curl
sudo systemctl restart apache2
```

---

## 💻 Installation Locale

### 1. Télécharger le Projet

**Option A : Via Git**

```bash
cd /var/www/html
git clone https://github.com/votre-repo/detenusMilitaires.git
cd detenusMilitaires
```

**Option B : Via Archive ZIP**

```bash
cd /var/www/html
unzip detenusMilitaires.zip
cd detenusMilitaires
```

### 2. Installer Composer

Si Composer n'est pas installé :

```bash
# Télécharger Composer
curl -sS https://getcomposer.org/installer | php

# Installer globalement
sudo mv composer.phar /usr/local/bin/composer

# Vérifier
composer --version
```

### 3. Installer les Dépendances

```bash
cd /var/www/html/detenusMilitaires
composer install
```

Si erreur "composer command not found" :

```bash
php composer.phar install
```

### 4. Permissions des Dossiers

```bash
# Créer le dossier uploads si nécessaire
mkdir -p uploads/photos

# Définir les permissions
chmod 755 uploads
chmod 755 uploads/photos

# Propriétaire web server
sudo chown -R www-data:www-data uploads/

# Si Apache/Nginx
sudo chown -R www-data:www-data /var/www/html/detenusMilitaires
```

---

## ⚙️ Configuration

### 1. Fichier .env

Créer le fichier `.env` à la racine du projet :

```bash
cp .env.example .env
nano .env
```

Contenu du `.env` :

```env
DB_HOST=127.0.0.1
DB_NAME=detenusMilitaires
DB_USER=root
DB_PASS=votre_mot_de_passe
```

**⚠️ Important :**

- Ne JAMAIS commiter `.env` dans Git
- Utiliser un mot de passe fort en production

### 2. Configuration Apache (Virtual Host)

Créer le fichier `/etc/apache2/sites-available/detenus.conf` :

```apache
<VirtualHost *:80>
    ServerName detenus.local
    ServerAdmin admin@detenus.local
    DocumentRoot /var/www/html/detenusMilitaires

    <Directory /var/www/html/detenusMilitaires>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/detenus_error.log
    CustomLog ${APACHE_LOG_DIR}/detenus_access.log combined
</VirtualHost>
```

Activer le site :

```bash
sudo a2ensite detenus.conf
sudo systemctl reload apache2
```

Ajouter dans `/etc/hosts` :

```
127.0.0.1   detenus.local
```

### 3. Configuration Nginx (Alternative)

Fichier `/etc/nginx/sites-available/detenus` :

```nginx
server {
    listen 80;
    server_name detenus.local;
    root /var/www/html/detenusMilitaires;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Activer :

```bash
sudo ln -s /etc/nginx/sites-available/detenus /etc/nginx/sites-enabled/
sudo systemctl reload nginx
```

---

## 🗄️ Initialisation Base de Données

### 1. Créer la Base de Données

**Via ligne de commande :**

```bash
mysql -u root -p
```

```sql
CREATE DATABASE detenusMilitaires
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

-- Créer un utilisateur dédié (recommandé en production)
CREATE USER 'detenus_user'@'localhost' IDENTIFIED BY 'mot_de_passe_fort';
GRANT ALL PRIVILEGES ON detenusMilitaires.* TO 'detenus_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**Via phpMyAdmin :**

1. Accéder à phpMyAdmin
2. Cliquer sur "Nouvelle base de données"
3. Nom : `detenusMilitaires`
4. Interclassement : `utf8mb4_unicode_ci`
5. Créer

### 2. Importer la Structure

**Via ligne de commande :**

```bash
mysql -u root -p detenusMilitaires < docs/base_de_donnees.sql
```

**Via phpMyAdmin :**

1. Sélectionner la base `detenusMilitaires`
2. Onglet "Importer"
3. Choisir le fichier `docs/base_de_donnees.sql`
4. Cliquer sur "Exécuter"

### 3. Vérifier l'Installation

```sql
mysql -u root -p detenusMilitaires

SHOW TABLES;
-- Devrait afficher 12 tables

DESCRIBE users;
DESCRIBE detenus;
DESCRIBE condamnations;

EXIT;
```

### 4. Initialiser les Données de Référence

```sql
USE detenusMilitaires;

-- Insérer les grades de base
INSERT INTO grades (code, libelle, hierarchie) VALUES
('S2C', 'Soldat de 2ème Classe', 1),
('S1C', 'Soldat de 1ère Classe', 2),
('CPL', 'Caporal', 3),
('CCH', 'Caporal-Chef', 4),
('SGT', 'Sergent', 5),
('SCH', 'Sergent-Chef', 6),
('ADJ', 'Adjudant', 7),
('ADC', 'Adjudant-Chef', 8),
('MDL', 'Major', 9),
('ASP', 'Aspirant', 10),
('SLT', 'Sous-Lieutenant', 11),
('LTN', 'Lieutenant', 12),
('CPT', 'Capitaine', 13),
('CDT', 'Commandant', 14),
('LCL', 'Lieutenant-Colonel', 15),
('COL', 'Colonel', 16);

-- Insérer quelques unités
INSERT INTO unites (code, nom, type, localisation) VALUES
('BASA', 'Base Aérienne Sud', 'ARMEE', 'Abidjan'),
('43BIM', '43ème Bataillon d\'Infanterie', 'ARMEE', 'Abidjan'),
('CLDO', 'Commandement de la Légion', 'GENDARMERIE', 'Abidjan');

-- Insérer quelques infractions
INSERT INTO infractions (code, libelle, categorie, gravite) VALUES
('DESERTION', 'Désertion', 'CRIME', 8),
('VOL', 'Vol', 'DELIT', 5),
('INSUBORDINATION', 'Insubordination', 'DELIT', 6),
('ABSENCE', 'Absence irrégulière', 'CONTRAVENTION', 3),
('COUPS', 'Coups et blessures', 'DELIT', 7);

-- Insérer quelques lieux
INSERT INTO lieux_detention (code, nom, type, capacite, ville) VALUES
('MACA', 'MACA - Maison d\'Arrêt et de Correction d\'Abidjan', 'PRISON_MILITAIRE', 500, 'Abidjan'),
('CAP', 'Camp Pénal d\'Akouédo', 'PRISON_MILITAIRE', 300, 'Abidjan');
```

---

## 👤 Premier Utilisateur

### Option 1 : Via Interface Web (Recommandé)

1. Accéder à : `http://detenus.local/pages/dash/register.php`

2. Remplir le formulaire :

   - Nom : Votre nom
   - Email : admin@detenus.ci
   - Mot de passe : Minimum 8 caractères avec majuscule, minuscule, chiffre et caractère spécial
   - Confirmer le mot de passe

3. Cliquer sur "Créer le compte"

4. Vous êtes redirigé vers la page de connexion

### Option 2 : Via SQL

```sql
USE detenusMilitaires;

-- Générer un hash de mot de passe (remplacer 'VotreMotDePasse')
-- Utiliser : https://bcrypt-generator.com/

INSERT INTO users (
    username, email, password_hash,
    nom, prenom, role, is_active
) VALUES (
    'admin',
    'admin@detenus.ci',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password
    'Administrateur',
    'Système',
    'ADMIN',
    TRUE
);
```

### Option 3 : Script PHP

Créer `scripts/create_admin.php` :

```php
<?php
require_once __DIR__ . '/../includes/db.php';

$username = 'admin';
$email = 'admin@detenus.ci';
$password = 'Admin@123'; // Changer !
$nom = 'Administrateur';
$prenom = 'Système';

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('
    INSERT INTO users (username, email, password_hash, nom, prenom, role, is_active)
    VALUES (?, ?, ?, ?, ?, ?, ?)
');

$stmt->execute([$username, $email, $hash, $nom, $prenom, 'ADMIN', true]);

echo "Utilisateur admin créé avec succès!\n";
echo "Username: $username\n";
echo "Password: $password\n";
```

Exécuter :

```bash
php scripts/create_admin.php
```

---

## ✅ Vérification

### 1. Tester la Connexion

```
URL: http://detenus.local
Username: admin
Password: [votre mot de passe]
```

### 2. Vérifier les Modules

- ✅ Dashboard s'affiche correctement
- ✅ Menu de navigation fonctionne
- ✅ Page "Liste des Détenus" accessible
- ✅ Page "Liste des Condamnations" accessible

### 3. Tester l'Upload

```bash
# Vérifier permissions
ls -la uploads/photos/

# Devrait afficher : drwxr-xr-x www-data www-data
```

Tester l'upload d'une photo dans "Ajouter un Détenu"

### 4. Vérifier les Logs

```php
// Dans includes/logs.php, tester :
log_activity($pdo, 1, 'Test connexion', 'Installation réussie');
```

Vérifier dans la table `audit_logs` :

```sql
SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10;
```

---

## 🌐 Déploiement Production

### 1. Sécurité

**A. Fichier .env**

```env
DB_HOST=localhost
DB_NAME=detenus_prod
DB_USER=detenus_user
DB_PASS=mot_de_passe_tres_fort_et_long
```

**B. Permissions strictes**

```bash
chmod 600 .env
chown www-data:www-data .env

chmod 644 includes/db.php
chmod 755 pages/
```

**C. Désactiver les erreurs PHP**

Dans `php.ini` :

```ini
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log
```

**D. HTTPS obligatoire**

```bash
# Installer Certbot
sudo apt install certbot python3-certbot-apache

# Obtenir certificat SSL
sudo certbot --apache -d detenus.votredomaine.ci
```

### 2. Optimisations

**A. Cache PHP (OPcache)**

Dans `php.ini` :

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

**B. MySQL**

```sql
-- Indexer les champs fréquemment utilisés
ALTER TABLE detenus ADD INDEX idx_created_at (created_at);
ALTER TABLE condamnations ADD INDEX idx_dates (date_jugement, date_liberation_effective);

-- Analyser les tables
ANALYZE TABLE detenus, condamnations, infractions;
```

**C. Apache**

```apache
# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>

# Cache navigateur
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

### 3. Backup Automatique

**Script backup :**

```bash
#!/bin/bash
# /home/backup/backup_detenus.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/backup/detenus"
DB_NAME="detenusMilitaires"
DB_USER="root"
DB_PASS="votre_password"

# Backup base de données
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup fichiers
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/html/detenusMilitaires/uploads

# Garder seulement les 30 derniers jours
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
```

**Crontab :**

```bash
# Backup quotidien à 2h du matin
0 2 * * * /home/backup/backup_detenus.sh >> /var/log/backup_detenus.log 2>&1
```

### 4. Monitoring

**Script de monitoring :**

```bash
#!/bin/bash
# /home/scripts/check_detenus.sh

URL="https://detenus.votredomaine.ci"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" $URL)

if [ $RESPONSE -ne 200 ]; then
    echo "Site DOWN! HTTP $RESPONSE" | mail -s "Alert: Site Detenus DOWN" admin@votredomaine.ci
fi
```

**Crontab :**

```bash
# Vérifier toutes les 5 minutes
*/5 * * * * /home/scripts/check_detenus.sh
```

---

## 🐛 Troubleshooting

### Erreur : "Connection refused"

**Cause :** MySQL n'est pas démarré

**Solution :**

```bash
sudo systemctl start mysql
sudo systemctl enable mysql
```

### Erreur : "Access denied for user"

**Cause :** Mauvais identifiants dans `.env`

**Solution :**

1. Vérifier le fichier `.env`
2. Tester la connexion :

```bash
mysql -u votre_user -p votre_database
```

### Erreur : "Call to undefined function password_hash()"

**Cause :** PHP < 5.5

**Solution :**

```bash
php -v
sudo apt upgrade php
```

### Erreur : "Upload failed"

**Cause :** Permissions insuffisantes

**Solution :**

```bash
sudo chown -R www-data:www-data uploads/
chmod 755 uploads/photos/
```

### Erreur : "Class 'Dotenv\Dotenv' not found"

**Cause :** Dépendances Composer non installées

**Solution :**

```bash
composer install
```

### Erreur 500 : "Internal Server Error"

**Cause :** Erreur PHP

**Solution :**

```bash
# Activer temporairement les erreurs
# Dans index.php, ajouter en haut :
error_reporting(E_ALL);
ini_set('display_errors', 1);

# Consulter les logs
tail -f /var/log/apache2/error.log
```

### Sidebar ne s'affiche pas

**Cause :** Fichier manquant ou erreur PHP

**Solution :**

1. Vérifier que `requires/sidebar.php` existe
2. Vérifier les includes dans les pages
3. Consulter la console navigateur (F12)

### Graphiques ne s'affichent pas

**Cause :** Chart.js non chargé

**Solution :**

1. Vérifier dans `requires/link.php`
2. Vérifier la console navigateur
3. Tester la connexion CDN

---

## 📞 Support

Si vous rencontrez des problèmes non résolus :

1. **Vérifier les logs :**

   - `/var/log/apache2/error.log`
   - `/var/log/mysql/error.log`
   - Console navigateur (F12)

2. **Vérifier les permissions :**

   ```bash
   ls -la /var/www/html/detenusMilitaires/
   ```

3. **Tester la connexion DB :**

   ```bash
   php -r "
   \$pdo = new PDO('mysql:host=localhost;dbname=detenusMilitaires', 'root', '');
   echo 'Connexion OK';
   "
   ```

4. **Contacter le support**

---

## ✅ Checklist de Déploiement

```
□ PHP >= 7.4 installé
□ MySQL >= 5.7 installé
□ Extensions PHP installées
□ Composer installé
□ Projet téléchargé
□ Dépendances installées (composer install)
□ Fichier .env configuré
□ Base de données créée
□ Structure SQL importée
□ Données de référence initialisées
□ Dossier uploads créé avec bonnes permissions
□ Premier utilisateur admin créé
□ Connexion testée
□ Upload photo testé
□ HTTPS configuré (production)
□ Backup automatique configuré (production)
□ Monitoring configuré (production)
```

---

🎉 **Félicitations !** Votre système est maintenant opérationnel !
