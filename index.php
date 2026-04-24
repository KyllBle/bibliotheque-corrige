<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bibliothèque</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .carte {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 2.5rem 3rem;
            text-align: center;
            width: 360px;
        }

        h1 {
            font-size: 1.6rem;
            margin-bottom: .4rem;
        }

        p.sous-titre {
            color: #666;
            font-size: .9rem;
            margin-bottom: 2rem;
        }

        nav {
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }

        nav a {
            display: block;
            padding: .75rem 1rem;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 1rem;
        }

        nav a:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>

<div class="carte">
    <h1>Bibliothèque</h1>
    <p class="sous-titre">Gestion des livres et des emprunts</p>

    <nav>
        <a href="pages/livres.php">Livres</a>
        <a href="pages/emprunts.php">Emprunts</a>
    </nav>
</div>

</body>
</html>
