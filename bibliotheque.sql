-- ============================================================
-- bibliotheque.sql
-- Requiert MySQL 8.0.13+ (DEFAULT (expression) sur les dates)
-- ============================================================

CREATE DATABASE IF NOT EXISTS bibliotheque
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bibliotheque;

-- --------------------------------------------------------
-- Table livre
-- --------------------------------------------------------
CREATE TABLE livre (
    id_livre         INT          AUTO_INCREMENT PRIMARY KEY,
    titre            VARCHAR(255) NOT NULL,
    auteur           VARCHAR(255) NOT NULL,
    isbn             VARCHAR(13)  UNIQUE,
    stock_total      INT          NOT NULL DEFAULT 1,
    stock_disponible INT          NOT NULL DEFAULT 1,
    CONSTRAINT chk_stock CHECK (stock_disponible >= 0 AND stock_disponible <= stock_total)
);

-- --------------------------------------------------------
-- Table adherent
-- --------------------------------------------------------
CREATE TABLE adherent (
    id_adherent      INT          AUTO_INCREMENT PRIMARY KEY,
    nom              VARCHAR(100) NOT NULL,
    prenom           VARCHAR(100) NOT NULL,
    email            VARCHAR(255) NOT NULL UNIQUE,
    date_inscription DATE         NOT NULL DEFAULT (CURRENT_DATE),
    actif            TINYINT(1)   NOT NULL DEFAULT 1
);

-- --------------------------------------------------------
-- Table emprunt
-- --------------------------------------------------------
CREATE TABLE emprunt (
    id_emprunt            INT  AUTO_INCREMENT PRIMARY KEY,
    id_livre              INT  NOT NULL,
    id_adherent           INT  NOT NULL,
    date_emprunt          DATE NOT NULL DEFAULT (CURRENT_DATE),
    date_retour_prevue    DATE NOT NULL,
    date_retour_effective DATE DEFAULT NULL,
    statut                ENUM('en_cours', 'rendu', 'en_retard') NOT NULL DEFAULT 'en_cours',
    CONSTRAINT fk_emprunt_livre    FOREIGN KEY (id_livre)    REFERENCES livre(id_livre)       ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_emprunt_adherent FOREIGN KEY (id_adherent) REFERENCES adherent(id_adherent) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- --------------------------------------------------------
-- Données de test — adhérents
-- --------------------------------------------------------
INSERT INTO adherent (nom, prenom, email, date_inscription) VALUES
    ('Dupont',   'Martin', 'martin.dupont@example.fr',   '2024-09-01'),
    ('Lefebvre', 'Sophie', 'sophie.lefebvre@example.fr', '2024-09-15'),
    ('Moreau',   'Pierre', 'pierre.moreau@example.fr',   '2024-10-03'),
    ('Bernard',  'Marie',  'marie.bernard@example.fr',   '2025-01-10'),
    ('Petit',    'Lucas',  'lucas.petit@example.fr',     '2025-02-20');

-- --------------------------------------------------------
-- Données de test — livres
-- --------------------------------------------------------
INSERT INTO livre (titre, auteur, isbn, stock_total, stock_disponible) VALUES
    ('Le Petit Prince',          'Antoine de Saint-Exupéry', '9782070612758', 3, 3),
    ('L\'Étranger',              'Albert Camus',             '9782070360024', 2, 2),
    ('Les Misérables',           'Victor Hugo',              '9782253096344', 2, 2),
    ('1984',                     'George Orwell',            '9782070368228', 3, 3),
    ('Le Comte de Monte-Cristo', 'Alexandre Dumas',          '9782253098584', 1, 1);

-- --------------------------------------------------------
-- Trigger : décrémente stock_disponible après un emprunt
-- --------------------------------------------------------
DELIMITER //
CREATE TRIGGER after_emprunt_insert
AFTER INSERT ON emprunt
FOR EACH ROW
BEGIN
    UPDATE livre
    SET stock_disponible = stock_disponible - 1
    WHERE id_livre = NEW.id_livre;
END //
DELIMITER ;

-- --------------------------------------------------------
-- Trigger BEFORE : passe statut à 'rendu' lors du retour
-- BEFORE est nécessaire ici : seul ce timing permet de
-- modifier NEW.statut avant que la ligne soit écrite.
-- --------------------------------------------------------
DELIMITER //
CREATE TRIGGER before_retour_update
BEFORE UPDATE ON emprunt
FOR EACH ROW
BEGIN
    IF OLD.date_retour_effective IS NULL AND NEW.date_retour_effective IS NOT NULL THEN
        SET NEW.statut = 'rendu';
    END IF;
END //
DELIMITER ;

-- --------------------------------------------------------
-- Trigger AFTER : incrémente stock_disponible lors du retour
-- --------------------------------------------------------
DELIMITER //
CREATE TRIGGER after_retour_update
AFTER UPDATE ON emprunt
FOR EACH ROW
BEGIN
    IF OLD.date_retour_effective IS NULL AND NEW.date_retour_effective IS NOT NULL THEN
        UPDATE livre
        SET stock_disponible = stock_disponible + 1
        WHERE id_livre = NEW.id_livre;
    END IF;
END //
DELIMITER ;

-- --------------------------------------------------------
-- Procédure : stats_livre(p_id_livre)
-- Retourne titre, auteur, nb total d'emprunts, nb en cours
-- --------------------------------------------------------
DELIMITER //
CREATE PROCEDURE stats_livre(IN p_id_livre INT)
BEGIN
    SELECT
        l.titre,
        l.auteur,
        COUNT(e.id_emprunt)                                          AS nb_emprunts_total,
        SUM(CASE WHEN e.statut = 'en_cours' THEN 1 ELSE 0 END)      AS nb_emprunts_en_cours
    FROM  livre l
    LEFT JOIN emprunt e ON l.id_livre = e.id_livre
    WHERE l.id_livre = p_id_livre
    GROUP BY l.id_livre, l.titre, l.auteur;
END //
DELIMITER ;
