<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<div class="sidebar" data-background-color="dark">
    <div class="sidebar-logo">
        <div class="logo-header" data-background-color="dark">
            <a href="../../pages/dash/dashboard.php" class="logo">
                <img src="../../assets/img/logo.png" alt="logo" class="navbar-brand" style="width:180px; height:140px;">
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
                <li
                    class="nav-item <?= $currentPage === 'dashboard.php' || $currentPage === 'dashbord.php' ? 'active' : '' ?>">
                    <a href="../../pages/dash/dashboard.php" class="collapsed" aria-expanded="false">
                        <i class="fas fa-home"></i>
                        <p>Tableau de bord</p>
                    </a>
                </li>
                <li class="nav-section">
                    <span class="sidebar-mini-icon">
                        <i class="fas fa-ellipsis-h"></i>
                    </span>
                    <h4 class="text-section">Gestion</h4>
                </li>
                <li class="nav-item <?= $currentPage === 'filieres.php' ? 'active' : '' ?>">
                    <a href="../../pages/filieres/filieres.php">
                        <i class="fas fa-graduation-cap"></i>
                        <p>Filières</p>
                    </a>
                </li>
                <li class="nav-item <?= $currentPage === 'etudiants.php' ? 'active' : '' ?>">
                    <a href="../../pages/etudiants/etudiants.php">
                        <i class="fas fa-user-graduate"></i>
                        <p>Module Étudiants</p>
                    </a>
                </li>
                <li class="nav-item <?= $currentPage === 'paiements.php' ? 'active' : '' ?>">
                    <a href="../../pages/paiements/paiements.php">
                        <i class="fas fa-credit-card"></i>
                        <p>Module Paiements</p>
                    </a>
                </li>
                <li class="nav-item <?= $currentPage === 'recus.php' ? 'active' : '' ?>">
                    <a href="../../pages/paiements/recus.php">
                        <i class="fas fa-receipt"></i>
                        <p>Module Reçus</p>
                    </a>
                </li>
                <li class="nav-item <?= $currentPage === 'annees-academik.php' ? 'active' : '' ?>">
                    <a href="../../pages/annees-academiques/annees-academik.php">
                        <i class="fas fa-calendar-alt"></i>
                        <p>Années Académiques</p>
                    </a>
                </li>
                <?php if ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin'): ?>
                    <li class="nav-item <?= $currentPage === 'utilisateurs.php' ? 'active' : '' ?>">
                        <a href="../../pages/utilisateurs/utilisateurs.php">
                            <i class="fas fa-users"></i>
                            <p>Utilisateurs</p>
                        </a>
                    </li>
                    <li class="nav-item <?= $currentPage === 'logs.php' ? 'active' : '' ?>">
                        <a href="../../pages/logs/logs.php">
                            <i class="fas fa-history"></i>
                            <p>Monitoring</p>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="nav-item <?= $currentPage === 'logout.php' ? 'active' : '' ?>">
                    <a href="../../pages/dash/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <p>Déconnexion</p>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
<!-- End Sidebar -->