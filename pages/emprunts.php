<?php
require_once __DIR__ . '/../config/Database.php';

$pdo    = Database::getInstance()->getPdo();
$erreur = null;

// --------------------------------------------------------
// Traitement POST
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Création d'un emprunt ---
    if ($action === 'creer') {
        $idLivre    = (int) ($_POST['id_livre']    ?? 0);
        $idAdherent = (int) ($_POST['id_adherent'] ?? 0);

        // Vérification du stock avant INSERT
        $stmtStock = $pdo->prepare('SELECT stock_disponible FROM livre WHERE id_livre = :id');
        $stmtStock->execute([':id' => $idLivre]);
        $stock = (int) $stmtStock->fetchColumn();

        if ($stock <= 0) {
            $erreur = "Aucun exemplaire disponible pour ce livre.";
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO emprunt (id_livre, id_adherent, date_emprunt, date_retour_prevue)
                 VALUES (:id_livre, :id_adherent, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY))'
            );
            $stmt->execute([
                ':id_livre'    => $idLivre,
                ':id_adherent' => $idAdherent,
            ]);
            // Le trigger after_emprunt_insert décrémente stock_disponible
            header('Location: emprunts.php');
            exit;
        }
    }

    // --- Retour d'un emprunt ---
    if ($action === 'retour') {
        $idEmprunt = (int) ($_POST['id_emprunt'] ?? 0);

        $stmt = $pdo->prepare(
            'UPDATE emprunt
             SET date_retour_effective = CURDATE()
             WHERE id_emprunt = :id AND date_retour_effective IS NULL'
        );
        $stmt->execute([':id' => $idEmprunt]);
        // Le trigger before_retour_update passe statut à 'rendu'
        // Le trigger after_retour_update incrémente stock_disponible
        header('Location: emprunts.php');
        exit;
    }
}

// --------------------------------------------------------
// Emprunts en cours (JOIN livre + adherent)
// --------------------------------------------------------
$emprunts = $pdo->query(
    "SELECT e.id_emprunt,
            e.date_emprunt,
            e.date_retour_prevue,
            l.titre,
            l.auteur,
            a.nom,
            a.prenom,
            CASE WHEN e.date_retour_prevue < CURDATE() THEN 1 ELSE 0 END AS en_retard
     FROM emprunt e
     JOIN livre    l ON l.id_livre    = e.id_livre
     JOIN adherent a ON a.id_adherent = e.id_adherent
     WHERE e.statut = 'en_cours'
     ORDER BY e.date_retour_prevue ASC"
)->fetchAll();

// --------------------------------------------------------
// Listes pour le formulaire de création
// --------------------------------------------------------
$adherents = $pdo->query(
    'SELECT id_adherent, nom, prenom FROM adherent WHERE actif = 1 ORDER BY nom, prenom'
)->fetchAll();

$livresDisponibles = $pdo->query(
    'SELECT id_livre, titre, auteur FROM livre WHERE stock_disponible > 0 ORDER BY titre'
)->fetchAll();

// Helper d'échappement
$e = fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des emprunts</title>
    <style>
        body  { font-family: Arial, sans-serif; max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
        th, td { border: 1px solid #ccc; padding: .5rem .75rem; text-align: left; }
        th { background: #f0f0f0; }
        tr.en-retard { background: #fee; color: #900; }
        tr.en-retard:hover { background: #fdd; }
        tr:not(.en-retard):hover { background: #fafafa; }
        .erreur { color: #c00; background: #fee; border: 1px solid #c00; padding: .5rem; margin-bottom: 1rem; }
        fieldset { padding: 1rem; max-width: 480px; }
        label { display: inline-block; width: 160px; }
        select { width: 280px; padding: .25rem; }
        button { margin-top: .5rem; padding: .35rem .9rem; }
        .btn-retour { font-size: .85rem; padding: .2rem .6rem; }
    </style>
</head>
<body>

<h1>Gestion des emprunts</h1>
<p><a href="../index.php">← Accueil</a></p>

<?php if ($erreur): ?>
    <p class="erreur"><?= $e($erreur) ?></p>
<?php endif ?>

<!-- -------------------------------------------------------- -->
<!-- Tableau des emprunts en cours                           -->
<!-- -------------------------------------------------------- -->
<h2>Emprunts en cours</h2>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Adhérent</th>
            <th>Livre</th>
            <th>Date d'emprunt</th>
            <th>Retour prévu</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($emprunts)): ?>
        <tr><td colspan="6">Aucun emprunt en cours.</td></tr>
    <?php else: ?>
        <?php foreach ($emprunts as $emprunt): ?>
        <tr<?= $emprunt['en_retard'] ? ' class="en-retard"' : '' ?>>
            <td><?= $e($emprunt['id_emprunt']) ?></td>
            <td><?= $e($emprunt['nom']) ?> <?= $e($emprunt['prenom']) ?></td>
            <td><?= $e($emprunt['titre']) ?> <small>(<?= $e($emprunt['auteur']) ?>)</small></td>
            <td><?= $e($emprunt['date_emprunt']) ?></td>
            <td><?= $e($emprunt['date_retour_prevue']) ?><?= $emprunt['en_retard'] ? ' ⚠ retard' : '' ?></td>
            <td>
                <form method="post" action="emprunts.php">
                    <input type="hidden" name="action"     value="retour">
                    <input type="hidden" name="id_emprunt" value="<?= $e($emprunt['id_emprunt']) ?>">
                    <button type="submit" class="btn-retour">Retour rendu</button>
                </form>
            </td>
        </tr>
        <?php endforeach ?>
    <?php endif ?>
    </tbody>
</table>

<!-- -------------------------------------------------------- -->
<!-- Formulaire de création d'emprunt                        -->
<!-- -------------------------------------------------------- -->
<h2>Nouvel emprunt</h2>

<fieldset>
    <legend>Créer un emprunt</legend>

    <form method="post" action="emprunts.php">
        <input type="hidden" name="action" value="creer">

        <p>
            <label for="id_adherent">Adhérent *</label>
            <select id="id_adherent" name="id_adherent" required>
                <option value="">— Choisir —</option>
                <?php foreach ($adherents as $a): ?>
                    <option value="<?= $e($a['id_adherent']) ?>">
                        <?= $e($a['nom']) ?> <?= $e($a['prenom']) ?>
                    </option>
                <?php endforeach ?>
            </select>
        </p>

        <p>
            <label for="id_livre">Livre *</label>
            <select id="id_livre" name="id_livre" required>
                <option value="">— Choisir —</option>
                <?php foreach ($livresDisponibles as $l): ?>
                    <option value="<?= $e($l['id_livre']) ?>">
                        <?= $e($l['titre']) ?> (<?= $e($l['auteur']) ?>)
                    </option>
                <?php endforeach ?>
            </select>
        </p>

        <button type="submit">Enregistrer l'emprunt</button>
    </form>
</fieldset>

</body>
</html>
