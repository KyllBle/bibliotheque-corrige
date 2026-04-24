<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Livre.php';

$erreur  = null;
$message = null;

// --------------------------------------------------------
// Traitement POST : ajout ou modification
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'titre'            => trim($_POST['titre']          ?? ''),
        'auteur'           => trim($_POST['auteur']         ?? ''),
        'isbn'             => trim($_POST['isbn']           ?? '') ?: null,
        'stock_total'      => (int) ($_POST['stock_total']  ?? 1),
        'stock_disponible' => (int) ($_POST['stock_total']  ?? 1),
    ];

    $idPost = isset($_POST['id_livre']) && $_POST['id_livre'] !== ''
        ? (int) $_POST['id_livre']
        : null;

    if ($idPost !== null) {
        // Modification : on conserve le stock_disponible existant
        $existant = Livre::getById($idPost);
        $data['id_livre']         = $idPost;
        $data['stock_disponible'] = $existant->getStockDisponible();
    }

    $livre = new Livre($data);

    try {
        $livre->save();
        header('Location: livres.php');
        exit;
    } catch (RuntimeException $e) {
        $erreur = $e->getMessage();
    }
}

// --------------------------------------------------------
// Traitement GET : suppression
// --------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        Livre::delete((int) $_GET['id']);
        header('Location: livres.php');
        exit;
    } catch (RuntimeException $e) {
        $erreur = $e->getMessage();
    }
}

// --------------------------------------------------------
// Récupération des livres (recherche ou liste complète)
// --------------------------------------------------------
$terme  = trim($_GET['terme'] ?? '');
$livres = $terme !== ''
    ? Livre::rechercher($terme)
    : Livre::getAll();

// --------------------------------------------------------
// Formulaire pré-rempli pour modification
// --------------------------------------------------------
$livreEdit = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    try {
        $livreEdit = Livre::getById((int) $_GET['id']);
    } catch (RuntimeException $e) {
        $erreur = $e->getMessage();
    }
}

// Helper d'échappement
$e = fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des livres</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 960px; margin: 2rem auto; padding: 0 1rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
        th, td { border: 1px solid #ccc; padding: .5rem .75rem; text-align: left; }
        th { background: #f0f0f0; }
        tr:hover { background: #fafafa; }
        .erreur  { color: #c00; background: #fee; border: 1px solid #c00; padding: .5rem; margin-bottom: 1rem; }
        .actions a { margin-right: .5rem; }
        fieldset { margin-bottom: 1.5rem; padding: 1rem; }
        label { display: inline-block; width: 140px; }
        input[type="text"], input[type="number"] { width: 260px; padding: .25rem; }
        button { margin-top: .5rem; padding: .35rem .9rem; }
        .lien-suppr { color: #c00; }
    </style>
</head>
<body>

<h1>Gestion des livres</h1>
<p><a href="../index.php">← Accueil</a></p>

<?php if ($erreur): ?>
    <p class="erreur"><?= $e($erreur) ?></p>
<?php endif ?>

<!-- -------------------------------------------------------- -->
<!-- Formulaire de recherche                                  -->
<!-- -------------------------------------------------------- -->
<form method="get" action="livres.php">
    <label for="terme">Rechercher :</label>
    <input type="text" id="terme" name="terme" value="<?= $e($terme) ?>" placeholder="Titre ou auteur">
    <button type="submit">Rechercher</button>
    <?php if ($terme !== ''): ?>
        <a href="livres.php">Réinitialiser</a>
    <?php endif ?>
</form>

<!-- -------------------------------------------------------- -->
<!-- Tableau des livres                                       -->
<!-- -------------------------------------------------------- -->
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Titre</th>
            <th>Auteur</th>
            <th>ISBN</th>
            <th>Stock total</th>
            <th>Disponible</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($livres)): ?>
        <tr><td colspan="7">Aucun livre trouvé.</td></tr>
    <?php else: ?>
        <?php foreach ($livres as $livre): ?>
        <tr>
            <td><?= $e($livre->getIdLivre()) ?></td>
            <td><?= $e($livre->getTitre()) ?></td>
            <td><?= $e($livre->getAuteur()) ?></td>
            <td><?= $e($livre->getIsbn() ?? '—') ?></td>
            <td><?= $e($livre->getStockTotal()) ?></td>
            <td><?= $e($livre->getStockDisponible()) ?></td>
            <td class="actions">
                <a href="livres.php?action=edit&id=<?= $e($livre->getIdLivre()) ?>">Modifier</a>
                <a href="livres.php?action=delete&id=<?= $e($livre->getIdLivre()) ?>"
                   class="lien-suppr"
                   onclick="return confirm('Supprimer « <?= $e($livre->getTitre()) ?> » ?')">Supprimer</a>
            </td>
        </tr>
        <?php endforeach ?>
    <?php endif ?>
    </tbody>
</table>

<!-- -------------------------------------------------------- -->
<!-- Formulaire ajout / modification                         -->
<!-- -------------------------------------------------------- -->
<fieldset>
    <legend><?= $livreEdit ? 'Modifier un livre' : 'Ajouter un livre' ?></legend>

    <form method="post" action="livres.php">
        <?php if ($livreEdit): ?>
            <input type="hidden" name="id_livre" value="<?= $e($livreEdit->getIdLivre()) ?>">
        <?php endif ?>

        <p>
            <label for="titre">Titre *</label>
            <input type="text" id="titre" name="titre" required
                   value="<?= $e($livreEdit?->getTitre() ?? '') ?>">
        </p>
        <p>
            <label for="auteur">Auteur *</label>
            <input type="text" id="auteur" name="auteur" required
                   value="<?= $e($livreEdit?->getAuteur() ?? '') ?>">
        </p>
        <p>
            <label for="isbn">ISBN</label>
            <input type="text" id="isbn" name="isbn" maxlength="13"
                   value="<?= $e($livreEdit?->getIsbn() ?? '') ?>">
        </p>
        <p>
            <label for="stock_total">Stock total *</label>
            <input type="number" id="stock_total" name="stock_total" min="1" required
                   value="<?= $e($livreEdit?->getStockTotal() ?? 1) ?>">
        </p>

        <button type="submit"><?= $livreEdit ? 'Enregistrer les modifications' : 'Ajouter le livre' ?></button>
        <?php if ($livreEdit): ?>
            <a href="livres.php">Annuler</a>
        <?php endif ?>
    </form>
</fieldset>

</body>
</html>
