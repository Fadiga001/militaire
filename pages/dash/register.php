<?php
require_once '../../includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    // Vérification force du mot de passe
    $strong = strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^a-zA-Z0-9]/', $password);

    if ($nom === '' || $email === '' || $password === '' || $confirm === '') {
        $error = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Adresse email invalide.";
    } elseif ($password !== $confirm) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (!$strong) {
        $error = "Le mot de passe doit faire au moins 8 caractères, contenir une majuscule, une minuscule, un chiffre et un caractère spécial.";
    } else {
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare('SELECT id_utilisateur FROM utilisateurs WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Cet email est déjà utilisé.";
        } else {
            // Insérer l'utilisateur (par défaut admin, statut actif)
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO utilisateurs (nom, email, mot_de_passe, role, statut) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$nom, $email, $hash, 'admin', 'actif']);
            header('Location: ../../index.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Inscription Administrateur</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
</head>

<body class="login"
    style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
    <div class="container card p-4 rounded-4 d-flex flex-column justify-content-center align-items-center"
        style="max-width: 400px;">
        <h3 class="text-center mb-5 py-3 px-5 rounded-4">Inscription</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="" class="w-100">
            <div class="form-group mb-3">
                <label for="nom">Nom complet</label>
                <input type="text" name="nom" id="nom" class="form-control" placeholder="Entrer votre nom" required>
            </div>
            <div class="form-group mb-3">
                <label for="email">Adresse email</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="Entrer votre email"
                    required>
            </div>
            <div class="form-group mb-3">
                <label for="password">Mot de passe</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Mot de passe"
                    required>
            </div>
            <div class="form-group mb-4">
                <label for="confirm">Confirmer le mot de passe</label>
                <input type="password" name="confirm" id="confirm" class="form-control"
                    placeholder="Confirmer le mot de passe" required>
            </div>
            <button type="submit" class="btn btn-success w-100 p-3">Créer le compte</button>
            <a href="../../index.php" class="btn btn-link w-100 mt-2">Déjà un compte ? Connectez-vous</a>
        </form>
    </div>
</body>

</html>