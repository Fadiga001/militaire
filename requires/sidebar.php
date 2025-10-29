<?php
// Déterminer la page actuelle
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Fonction pour déterminer si un menu est actif
function isActive($page, $dir = null)
{
    global $currentPage, $currentDir;
    if ($dir) {
        return ($currentDir === $dir) ? 'active' : '';
    }
    return ($currentPage === $page) ? 'active' : '';
}

// Fonction pour déterminer si un submenu doit être ouvert
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
            <button class="topbar-toggler more">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>
    </div>

    <div class="sidebar-wrapper scrollbar scrollbar-inner">
        <div class="sidebar-content">
            <ul class="nav nav-secondary">
                <!-- Dashboard -->
                <li class="nav-item <?= isActive('dashboard.php') ?>">
                    <a href="../../pages/dash/dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <p>Tableau de Bord</p>
                    </a>
                </li>

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fas fa-ellipsis-h"></i>
                    </span>
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

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fas fa-ellipsis-h"></i>
                    </span>
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
                <li class="nav-item <?= isActive(null, 'lieux-detention') ?>">
                    <a data-bs-toggle="collapse" href="#lieux" class="collapsed"
                        aria-expanded="<?= isSubmenuOpen('lieux-detention') ? 'true' : 'false' ?>">
                        <i class="fas fa-map-marker-alt"></i>
                        <p>Lieux de Détention</p>
                        <span class="caret"></span>
                    </a>
                    <div class="collapse <?= isSubmenuOpen('lieux-detention') ?>" id="lieux">
                        <ul class="nav nav-collapse">
                            <li class="<?= isActive('lieux_detention.php', 'lieux-detention') ?>">
                                <a href="../../pages/lieux-detention/lieux_detention.php">
                                    <span class="sub-item">Liste des Lieux</span>
                                </a>
                            </li>
                            <li class="<?= isActive('ajouter_lieu.php', 'lieux-detention') ?>">
                                <a href="../../pages/lieux-detention/ajouter_lieu.php">
                                    <span class="sub-item">Ajouter un Lieu</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- Grades & Unités -->
                <li class="nav-item <?= isActive(null, 'references') ?>">
                    <a data-bs-toggle="collapse" href="#references" class="collapsed"
                        aria-expanded="<?= isSubmenuOpen('references') ? 'true' : 'false' ?>">
                        <i class="fas fa-star"></i>
                        <p>Grades & Unités</p>
                        <span class="caret"></span>
                    </a>
                    <div class="collapse <?= isSubmenuOpen('references') ?>" id="references">
                        <ul class="nav nav-collapse">
                            <li class="<?= isActive('grades.php', 'references') ?>">
                                <a href="../../pages/references/grades.php">
                                    <span class="sub-item">Grades Militaires</span>
                                </a>
                            </li>
                            <li class="<?= isActive('unites.php', 'references') ?>">
                                <a href="../../pages/references/unites.php">
                                    <span class="sub-item">Unités Militaires</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fas fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Rapports & Statistiques</h4>
                </li>

                <!-- Rapports -->
                <li class="nav-item <?= isActive(null, 'rapports') ?>">
                    <a data-bs-toggle="collapse" href="#rapports" class="collapsed"
                        aria-expanded="<?= isSubmenuOpen('rapports') ? 'true' : 'false' ?>">
                        <i class="fas fa-chart-bar"></i>
                        <p>Rapports</p>
                        <span class="caret"></span>
                    </a>
                    <div class="collapse <?= isSubmenuOpen('rapports') ?>" id="rapports">
                        <ul class="nav nav-collapse">
                            <li class="<?= isActive('rapport_general.php', 'rapports') ?>">
                                <a href="../../pages/rapports/rapport_general.php">
                                    <span class="sub-item">Rapport Général</span>
                                </a>
                            </li>
                            <li class="<?= isActive('rapport_liberations.php', 'rapports') ?>">
                                <a href="../../pages/rapports/rapport_liberations.php">
                                    <span class="sub-item">Libérations Prévues</span>
                                </a>
                            </li>
                            <li class="<?= isActive('rapport_statistiques.php', 'rapports') ?>">
                                <a href="../../pages/rapports/rapport_statistiques.php">
                                    <span class="sub-item">Statistiques Détaillées</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- Notifications -->
                <li class="nav-item <?= isActive('notifications.php', 'notifications') ?>">
                    <a href="../../pages/notifications/notifications.php">
                        <i class="fas fa-bell"></i>
                        <p>Notifications</p>
                        <?php
                        // Compter les notifications non lues
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
                            // Ignorer les erreurs
                        }
                        ?>
                    </a>
                </li>

                <?php if ($_SESSION['user_role'] === 'ADMIN'): ?>
                    <li class="nav-section">
                        <span class="sidebar-mini-icon">
                            <i class="fas fa-ellipsis-h"></i>
                        </span>
                        <h4 class="text-section">Administration</h4>
                    </li>

                    <!-- Utilisateurs -->
                    <li class="nav-item <?= isActive(null, 'utilisateurs') ?>">
                        <a data-bs-toggle="collapse" href="#utilisateurs" class="collapsed"
                            aria-expanded="<?= isSubmenuOpen('utilisateurs') ? 'true' : 'false' ?>">
                            <i class="fas fa-user-shield"></i>
                            <p>Utilisateurs</p>
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

                    <!-- Logs d'Audit -->
                    <li class="nav-item <?= isActive('logs.php', 'logs') ?>">
                        <a href="../../pages/logs/logs.php">
                            <i class="fas fa-history"></i>
                            <p>Logs d'Audit</p>
                        </a>
                    </li>

                    <!-- Paramètres -->
                    <li class="nav-item <?= isActive('parametres.php', 'parametres') ?>">
                        <a href="../../pages/parametres/parametres.php">
                            <i class="fas fa-cog"></i>
                            <p>Paramètres</p>
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fas fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Système</h4>
                </li>

                <!-- Aide -->
                <li class="nav-item <?= isActive('aide.php', 'aide') ?>">
                    <a href="../../pages/aide/aide.php">
                        <i class="fas fa-question-circle"></i>
                        <p>Aide & Documentation</p>
                    </a>
                </li>

                <!-- Déconnexion -->
                <li class="nav-item <?= isActive('logout.php', 'dash') ?>">
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
    /* Personnalisation sidebar militaire */
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

    .sidebar .nav .nav-collapse .nav-item a:hover {
        background: rgba(23, 125, 255, 0.1);
        padding-left: 65px;
    }

    .sidebar .nav .nav-item a .badge {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
    }

    /* Animation pour les icônes */
    .sidebar .nav>.nav-item>a i {
        transition: transform 0.3s ease;
    }

    .sidebar .nav>.nav-item:hover>a i {
        transform: scale(1.1);
    }

    /* Scrollbar personnalisé */
    .sidebar-wrapper::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar-wrapper::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
    }

    .sidebar-wrapper::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 10px;
    }

    .sidebar-wrapper::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    /* Logo */
    .sidebar .logo-header {
        padding: 20px 15px;
        text-align: center;
    }

    .sidebar .logo-header .logo img {
        max-width: 100%;
        height: auto;
    }

    /* Submenu */
    .sidebar .nav-collapse {
        background: rgba(0, 0, 0, 0.2);
    }

    .sidebar .nav-collapse .nav-item.active a {
        background: rgba(23, 125, 255, 0.2);
        border-left: 3px solid #177dff;
        color: #177dff !important;
    }

    /* Responsive */
    @media (max-width: 991px) {
        .sidebar {
            margin-left: -275px;
        }

        .sidebar.show {
            margin-left: 0;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fermer automatiquement les autres submenus quand on en ouvre un
        const collapseElements = document.querySelectorAll('.sidebar .collapse');
        collapseElements.forEach(function(element) {
            element.addEventListener('show.bs.collapse', function() {
                collapseElements.forEach(function(other) {
                    if (other !== element && other.classList.contains('show')) {
                        bootstrap.Collapse.getInstance(other).hide();
                    }
                });
            });
        });

        // Ouvrir automatiquement le submenu actif
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