# demo

Ce dépôt contient le code personnalisé du site WordPress développé en local
avec Local. WordPress Core, la configuration de l'environnement, les médias
et la base de données ne sont pas versionnés.

## Code personnalisé inclus

- `wp-content/plugins/baykat-finance/` : plugin Baykat Finance, son interface
  de gestion agricole, ses modules, ses feuilles de style et son JavaScript.

Aucun thème personnalisé n'a été trouvé dans l'installation source. Les thèmes
présents (`Twenty Twenty-Three`, `Twenty Twenty-Four` et `Twenty Twenty-Five`)
sont les thèmes WordPress standard et ne sont pas copiés dans ce dépôt. Le
dossier `wp-content/plugins/Mamari` ne contient aucun fichier.

## Installation dans WordPress

1. Installer une version compatible de WordPress et PHP dans l'environnement
   cible.
2. Copier `wp-content/plugins/baykat-finance/` dans le dossier
   `wp-content/plugins/` de cette installation.
3. Activer **Baykat Finance** dans l'administration WordPress.
4. Configurer séparément la page WordPress qui utilise le shortcode
   `[baykat_farm]`.

Le plugin s'appuie sur les fonctions WordPress et crée/met à niveau ses tables
applicatives à l'activation ou lors de ses vérifications de schéma. Aucune
base de données ni donnée utilisateur n'est fournie par ce dépôt.

## Environnement

La configuration locale (dont `wp-config.php`), les secrets, les fichiers
Local, les journaux, les fichiers téléversés et WordPress Core doivent rester
propres à chaque installation et ne doivent pas être commités.

Le `.gitignore` utilise une liste blanche : seuls ce README et le plugin
personnalisé identifié sont suivis. Lorsqu'un autre thème ou plugin
personnalisé est ajouté au template, il faudra explicitement l'autoriser dans
`.gitignore` et le documenter ici.
