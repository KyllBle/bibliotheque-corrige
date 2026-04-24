<?php

require_once __DIR__ . '/../config/Database.php';

class Livre
{
    private ?int    $id_livre;
    private string  $titre;
    private string  $auteur;
    private ?string $isbn;
    private int     $stock_total;
    private int     $stock_disponible;

    public function __construct(array $data)
    {
        $this->id_livre         = isset($data['id_livre']) ? (int) $data['id_livre'] : null;
        $this->titre            = $data['titre'];
        $this->auteur           = $data['auteur'];
        $this->isbn             = $data['isbn'] ?? null;
        $this->stock_total      = (int) $data['stock_total'];
        $this->stock_disponible = (int) $data['stock_disponible'];
    }

    // --------------------------------------------------------
    // Getters
    // --------------------------------------------------------

    public function getIdLivre(): ?int    { return $this->id_livre; }
    public function getTitre(): string    { return $this->titre; }
    public function getAuteur(): string   { return $this->auteur; }
    public function getIsbn(): ?string    { return $this->isbn; }
    public function getStockTotal(): int  { return $this->stock_total; }
    public function getStockDisponible(): int { return $this->stock_disponible; }

    // --------------------------------------------------------
    // Méthodes statiques de lecture
    // --------------------------------------------------------

    /**
     * Retourne tous les livres triés par titre.
     *
     * @return Livre[]
     */
    public static function getAll(): array
    {
        $pdo  = Database::getInstance()->getPdo();
        $stmt = $pdo->query('SELECT * FROM livre ORDER BY titre ASC');

        return array_map(fn(array $row) => new self($row), $stmt->fetchAll());
    }

    /**
     * Retourne un livre par son identifiant.
     *
     * @throws RuntimeException si le livre n'existe pas
     */
    public static function getById(int $id): self
    {
        $pdo  = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare('SELECT * FROM livre WHERE id_livre = :id');
        $stmt->execute([':id' => $id]);

        $data = $stmt->fetch();

        if ($data === false) {
            throw new RuntimeException("Livre introuvable (id = $id).");
        }

        return new self($data);
    }

    /**
     * Recherche les livres dont le titre ou l'auteur contient $terme.
     *
     * @return Livre[]
     */
    public static function rechercher(string $terme): array
    {
        $pdo  = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM livre
             WHERE titre LIKE :terme OR auteur LIKE :terme
             ORDER BY titre ASC'
        );
        $stmt->execute([':terme' => '%' . $terme . '%']);

        return array_map(fn(array $row) => new self($row), $stmt->fetchAll());
    }

    // --------------------------------------------------------
    // Persistance
    // --------------------------------------------------------

    /**
     * INSERT si id_livre est null, UPDATE sinon.
     *
     * @throws RuntimeException en cas d'échec SQL
     */
    public function save(): void
    {
        $pdo = Database::getInstance()->getPdo();

        try {
            if ($this->id_livre === null) {
                $stmt = $pdo->prepare(
                    'INSERT INTO livre (titre, auteur, isbn, stock_total, stock_disponible)
                     VALUES (:titre, :auteur, :isbn, :stock_total, :stock_disponible)'
                );
                $stmt->execute([
                    ':titre'            => $this->titre,
                    ':auteur'           => $this->auteur,
                    ':isbn'             => $this->isbn,
                    ':stock_total'      => $this->stock_total,
                    ':stock_disponible' => $this->stock_disponible,
                ]);
                $this->id_livre = (int) $pdo->lastInsertId();
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE livre
                     SET titre            = :titre,
                         auteur           = :auteur,
                         isbn             = :isbn,
                         stock_total      = :stock_total,
                         stock_disponible = :stock_disponible
                     WHERE id_livre = :id_livre'
                );
                $stmt->execute([
                    ':titre'            => $this->titre,
                    ':auteur'           => $this->auteur,
                    ':isbn'             => $this->isbn,
                    ':stock_total'      => $this->stock_total,
                    ':stock_disponible' => $this->stock_disponible,
                    ':id_livre'         => $this->id_livre,
                ]);
            }
        } catch (PDOException $e) {
            throw new RuntimeException("Erreur lors de la sauvegarde du livre : " . $e->getMessage());
        }
    }

    /**
     * Supprime un livre uniquement si aucun exemplaire n'est actuellement emprunté.
     *
     * @throws RuntimeException si des exemplaires sont en cours d'emprunt
     */
    public static function delete(int $id): void
    {
        $livre = self::getById($id);

        if ($livre->getStockDisponible() !== $livre->getStockTotal()) {
            throw new RuntimeException(
                "Impossible de supprimer le livre \"{$livre->getTitre()}\" : "
                . ($livre->getStockTotal() - $livre->getStockDisponible())
                . " exemplaire(s) actuellement emprunté(s)."
            );
        }

        $pdo  = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare('DELETE FROM livre WHERE id_livre = :id');
        $stmt->execute([':id' => $id]);
    }
}
