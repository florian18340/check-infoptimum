# Vérificateur de Stock Infoptimum

Ce projet permet de surveiller le stock des ventes privées sur le site Infoptimum.

## Prérequis

*   Un serveur web (Apache/Nginx) avec PHP installé (ex: XAMPP, WAMP, MAMP, ou Docker).
*   Une base de données MySQL.

## Installation

1.  **Base de données** :
    *   Créez une base de données nommée `infoptimum_stock` dans votre gestionnaire MySQL (phpMyAdmin, etc.).
    *   Si vous utilisez un nom différent, mettez à jour le fichier `api.php`.

2.  **Configuration** :
    *   Ouvrez le fichier `api.php`.
    *   Modifiez les variables `$host`, `$dbname`, `$username`, et `$password` au début du fichier pour correspondre à votre configuration MySQL locale.

    ```php
    $host = 'localhost';
    $dbname = 'infoptimum_stock';
    $username = 'root';
    $password = ''; // Mettez votre mot de passe ici si nécessaire
    ```

3.  **Lancement** :
    *   Placez le dossier du projet dans le répertoire racine de votre serveur web (ex: `htdocs` ou `www`).
    *   Accédez à `http://localhost/check-infoptimum/index.html` via votre navigateur.

## Fonctionnement

*   Ajoutez l'URL d'une vente privée Infoptimum via le formulaire.
*   Cliquez sur "Vérifier tout le stock maintenant" pour lancer la vérification.
*   Le statut (En Stock / Épuisé) s'affichera dans le tableau.

## Note technique

Le script `api.php` analyse le code HTML de la page cible pour déterminer si le produit est en stock. Il cherche des mots clés comme "Ajouter au panier" ou "Epuisé". Si le site Infoptimum change sa structure, il faudra peut-être adapter la fonction `checkStock` dans `api.php`.
