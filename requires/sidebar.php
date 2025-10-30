<?php
// Déterminer la page actuelle
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function isActive($page, $dir = null)
{
    global $currentPage, $currentDir;
    if ($dir) {
        return ($currentDir === $dir) ? 'active' : '';
    }
    return ($currentPage === $page) ? 'active' : '';
}

function isSubmenuOpen($dir)
{
    global $currentDir;
    return ($currentDir === $dir) ? 'show' : '';
}
?>
<!-- Sidebar -->
<div class="sidebar" data-background-color="dark">
    <div class="sidebar-logo">
        <div class="logo-header" data-background-color="dark">
            <a href="../../pages/dash/dashboard.php" class="logo">
                <img src="../../assets/img/logo.png" alt="Logo" class="navbar-brand"
                    style="width:180px; height:140px; object-fit: contain;">
            </a>
            <div class="nav-toggle">
                <button class="btn btn-toggle toggle-sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <button class="btn btn-toggle sidenav-toggler">
                    <i class="fas fa-angle-double-left"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="sidebar-wrapper scrollbar scrollbar-inner">
        <div class="sidebar-content">
            <ul class="nav nav-secondary">

                <!-- DASHBOARD -->
                <li class="nav-item <?= isActive('dashboard.php') ?>">
                    <a href="../../pages/dash/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <p>Tableau de Bord</p>
                    </a>
                </li>

                <!-- SECTION: GESTION PRINCIPALE -->
                <li class="nav-section">
                    <span class="sidebar-mini-icon"><i class="fas fa-ellipsis-h"></i></span>
                    <h4 class="text-section">Gestion Principale</h4>
                </li>

                <!-- Détenus -->
                <li class="nav-item <?= isActive(null, 'detenus') ?>">
                    <a data-bs-toggle="collapse" href="#detenus" class="collapsed"
                        aria-expanded="<?= isSubmenuOpen('detenus') ? 'true' : 'false' ?>">
                        <i class="fas fa-users"></i>
                        <p>Détenus</p>
                        <span class="caret"></span>
                    </a>
                    <div class="collapse <?= isSubmenuOpen('detenus') ?>" id="detenus">
                        <ul class="nav nav-collapse">
                            <li class="<?= isActive('detenus.php', 'detenus') ?>">
                                <a href="../../pages/detenus/detenus.php">
                                    <span class="sub-item">Liste des Détenus</span>
                                </a>
                            </li>
                            <li class="<?= isActive('ajouter_detenu.php', 'detenus') ?>">
                                <a href="../../pages/detenus/ajouter_detenu.php">
                                    <span class="sub-item">Ajouter un Détenu</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- Condamnations -->
                <li class="nav-item <?= isActive(null, 'condamnations') ?>">
                    <a data-bs-toggle="collapse" href="#condamnations" class="collapsed"
                        aria-expanded="<?= isSubmenuOpen('condamnations') ? 'true' : 'false' ?>">
                        <i class="fas fa-gavel"></i>
                        <p>Condamnations</p>
                        <span class="caret"></span>
                    </a>
                    <div class="collapse <?= isSubmenuOpen('condamnations') ?>" id="condamnations">
                        <ul class="nav nav-collapse">
                            <li class="<?= isActive('condamnations.php', 'condamnations') ?>">
                                <a href="../../pages/condamnations/condamnations.php">
                                    <span class="sub-item">Liste des Condamnations</span>
                                </a>
                            </li>
                            <li class="<?= isActive('ajouter_condamnation.php', 'condamnations') ?>">
                                <a href="../../pages/condamnations/ajouter_condamnation.php">
                                    <span class="sub-item">Ajouter une Condamnation</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- SECTION: DONNÉES DE RÉFÉRENCE -->
                <li class="nav-section">
                    <span class="sidebar-mini-icon"><i class="fas fa-ellipsis-h"></i></span>
                    <h4 class="text-section">Données de Référence</h4>
                </li>

                <!-- Infractions -->
                <li class="nav-item <?= isActive(null, 'infractions') ?>">
                    <a data-bs-toggle="collapse" href="#infractions" class="collapsed"
                        aria-expanded="<?= isSubmenuOpen('infractions') ? 'true' : 'false' ?>">
                        <i class="fas fa-balance-scale"></i>
                        <p>Infractions</p>
                        <span class="caret"></span>
                    </a>
                    <div class="collapse <?= isSubmenuOpen('infractions') ?>" id="infractions">
                        <ul class="nav nav-collapse">
                            <li class="<?= isActive('infractions.php', 'infractions') ?>">
                                <a href="../../pages/infractions/infractions.php">
                                    <span class="sub-item">Liste des Infractions</span>
                                </a>
                            </li>
                            <li class="<?= isActive('ajouter_infraction.php', 'infractions') ?>">
                                <a href="../../pages/infractions/ajouter_infraction.php">
                                    <span class="sub-item">Ajouter une Infraction</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- Lieux de Détention -->
                <li class="nav-item <?= isActive(null, 'lieux_detention') ?>">
                    <a data-bs-toggle="collapse" href="#lieux" class="collapsed"
                        aria-expanded="<?= isSubmenuOpen('lieux_detention') ? 'true' : 'false' ?>">
                        <i class="fas fa-map-marker-alt"></i>
                        <p>Lieux de Détention</p>
                        <span class="caret"></span>
                    </a>
                    <div class="collapse <?= isSubmenuOpen('lieux_detention') ?>" id="lieux">
                        <ul class="nav nav-collapse">
                            <li class="<?= isActive('lieux_detention.php', 'lieux_detention') ?>">
                                <a href="../../pages/lieux_detention/lieux_detention.php">
                                    <span class="sub-item">Liste des Lieux</span>
                                </a>
                            </li>
                            <li class="<?= isActive('ajouter_lieu.php', 'lieux_detention') ?>">
                                <a href="../../pages/lieux_detention/ajouter_lieu.php">
                                    <span class="sub-item">Ajouter un Lieu</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- SECTION: RAPPORTS -->
                <li class="nav-section">
                    <span class="sidebar-mini-icon"><i class="fas fa-ellipsis-h"></i></span>
                    <h4 class="text-section">Rapports & Statistiques</h4>
                </li>

                <!-- Notifications -->
                <li class="nav-item <?= isActive('notifications.php', 'notifications') ?>">
                    <a href="../../pages/notifications/notifications.php">
                        <i class="fas fa-bell"></i>
                        <p>Notifications</p>
                        <?php
                        try {
                            $notifStmt = $pdo->query("
                                SELECT COUNT(*) as nb 
                                FROM notifications 
                                WHERE is_read = FALSE AND is_active = TRUE
                            ");
                            $nbNotif = (int)$notifStmt->fetch()['nb'];
                            if ($nbNotif > 0):
                        ?>
                                <span class="badge badge-danger"><?= $nbNotif ?></span>
                        <?php
                            endif;
                        } catch (Exception $e) {
                        }
                        ?>
                    </a>
                </li>

                <!-- SECTION: ADMINISTRATION (ADMIN UNIQUEMENT) -->
                <?php if ($_SESSION['user_role'] === 'ADMIN'): ?>
                    <li class="nav-section">
                        <span class="sidebar-mini-icon"><i class="fas fa-ellipsis-h"></i></span>
                        <h4 class="text-section">Administration</h4>
                    </li>

                    <!-- Utilisateurs -->
                    <li class="nav-item <?= isActive(null, 'utilisateurs') ?>">
                        <a data-bs-toggle="collapse" href="#utilisateurs" class="collapsed"
                            aria-expanded="<?= isSubmenuOpen('utilisateurs') ? 'true' : 'false' ?>">
                            <i class="fas fa-user-shield"></i>
                            <p>Utilisateurs</p>
                            <?php
                            // Badge: utilisateurs verrouillés
                            try {
                                $lockStmt = $pdo->query("
                                SELECT COUNT(*) as nb 
                                FROM users 
                                WHERE locked_until IS NOT NULL AND locked_until > NOW()
                            ");
                                $nbLocked = (int)$lockStmt->fetch()['nb'];
                                if ($nbLocked > 0):
                            ?>
                                    <span class="badge badge-warning"><?= $nbLocked ?></span>
                            <?php
                                endif;
                            } catch (Exception $e) {
                            }
                            ?>
                            <span class="caret"></span>
                        </a>
                        <div class="collapse <?= isSubmenuOpen('utilisateurs') ?>" id="utilisateurs">
                            <ul class="nav nav-collapse">
                                <li class="<?= isActive('utilisateurs.php', 'utilisateurs') ?>">
                                    <a href="../../pages/utilisateurs/utilisateurs.php">
                                        <span class="sub-item">Liste des Utilisateurs</span>
                                    </a>
                                </li>
                                <li class="<?= isActive('ajouter_utilisateur.php', 'utilisateurs') ?>">
                                    <a href="../../pages/utilisateurs/ajouter_utilisateur.php">
                                        <span class="sub-item">Ajouter un Utilisateur</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                <?php endif; ?>

                <!-- SECTION: COMPTE PERSONNEL (TOUS) -->
                <li class="nav-section">
                    <span class="sidebar-mini-icon"><i class="fas fa-ellipsis-h"></i></span>
                    <h4 class="text-section">Mon Compte</h4>
                </li>

                <!-- Mon Profil (disponible pour tous) -->
                <li class="nav-item <?= isActive('mon_profil.php', 'utilisateurs') ?>">
                    <a href="../../pages/utilisateurs/mon_profil.php">
                        <i class="fas fa-user-circle"></i>
                        <p>Mon Profil</p>
                    </a>
                </li>

                <!-- Déconnexion -->
                <li class="nav-item">
                    <a href="../../pages/dash/logout.php"
                        onclick="return confirm('Êtes-vous sûr de vouloir vous déconnecter ?')">
                        <i class="fas fa-sign-out-alt text-danger"></i>
                        <p>Déconnexion</p>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
<!-- End Sidebar -->

<style>
    .sidebar[data-background-color="dark"] {
        background: linear-gradient(180deg, #1a1f33 0%, #0d1117 100%);
    }

    .sidebar .nav>.nav-item.active>a {
        background: linear-gradient(90deg, rgba(23, 125, 255, 0.2) 0%, rgba(23, 125, 255, 0.05) 100%);
        border-left: 4px solid #177dff;
        color: #177dff !important;
    }

    .sidebar .nav>.nav-item.active>a i {
        color: #177dff;
    }

    .sidebar .nav-section .text-section {
        color: #6c757d;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 20px;
    }

    .sidebar .nav .nav-item a:hover {
        background: rgba(255, 255, 255, 0.05);
    }

    .sidebar .nav .nav-item a .badge {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
    }

    .sidebar .nav>.nav-item>a i {
        transition: transform 0.3s ease;
    }

    .sidebar .nav>.nav-item:hover>a i {
        transform: scale(1.1);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const collapseElements = document.querySelectorAll('.sidebar .collapse');
        collapseElements.forEach(function(element) {
            element.addEventListener('show.bs.collapse', function() {
                collapseElements.forEach(function(other) {
                    if (other !== element && other.classList.contains('show')) {
                        bootstrap.Collapse.getInstance(other)?.hide();
                    }
                });
            });
        });

        const activeSubmenu = document.querySelector('.sidebar .nav-collapse .active');
        if (activeSubmenu) {
            const parentCollapse = activeSubmenu.closest('.collapse');
            if (parentCollapse && !parentCollapse.classList.contains('show')) {
                new bootstrap.Collapse(parentCollapse, {
                    toggle: true
                });
            }
        }
    });
</script>