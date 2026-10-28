# Cahier des charges — « Gérer ma ferme »

## 1. Objet

Ce document décrit les fonctionnalités déjà présentes dans le plugin WordPress
**Gérer ma ferme**. Il sert de référence pour son état actuel et pour préparer
son intégration au site Baykat.

Le plugin ajoute un espace de gestion agricole au site WordPress. Il réutilise
la base de données, les comptes et la session WordPress existants.

## 2. Accès à l’application

- L’application est rendue par le shortcode `[gerer_ma_ferme]`.
- L’ancien shortcode `[baykat_farm]` reste disponible pour compatibilité.
- Une page WordPress publiée contenant l’un de ces shortcodes affiche l’espace
de gestion.
- Un visiteur non connecté voit un lien de connexion WordPress qui revient à
cette page après connexion.
- À la connexion, les utilisateurs qui ne disposent pas de la capacité
`manage_options` sont redirigés vers la page publiée contenant le shortcode,
lorsqu’elle est trouvée.
- Un lien de déconnexion est disponible dans l’espace de gestion.

L’intégration du shortcode dans une page est déjà disponible. L’ajout d’un
bouton dans une page ou dans les onglets de paramètres du site Baykat reste à
faire lorsque l’accès à cette partie du site sera disponible.

## 3. Fonctionnalités implémentées

### 3.1 Tableau de bord

- Accueil personnalisé pour l’utilisateur connecté.
- Vue des fermes enregistrées et raccourcis vers les sections de gestion.
- Indicateurs : nombre de fermes, superficie totale, nombre de parcelles,
  cultures en cours, récoltes à venir, activités et transactions.
- Solde financier calculé à partir des revenus et dépenses.
- Affichage des prochaines récoltes et des dernières activités et transactions.
- Création, modification, suppression et ajout ou remplacement de photo d’une
  ferme.
- Affichage du détail statistique par ferme.

### 3.2 Fermes

Chaque ferme peut comporter un nom, une localisation, une superficie en
hectares et une photo. Les informations peuvent être modifiées et la ferme peut
être supprimée si elle ne possède pas de données liées qui empêcheraient cette
suppression.

### 3.3 Parcelles

- Création d’une parcelle rattachée à une ferme.
- Enregistrement du nom, de la superficie en mètres carrés, d’un statut et
  d’une photo.
- Modification, déplacement vers une autre ferme appartenant au même
  utilisateur, remplacement de la photo et suppression sous réserve des
  dépendances.
- Affichage des parcelles sous forme de fiches et possibilité de rechercher ou
  filtrer les enregistrements.

Les statuts proposés sont : planifiée, en cours, récoltée et en pause.

### 3.4 Cultures

- Création d’une culture associée à une parcelle.
- Enregistrement du nom, de la variété (facultative), des dates de plantation
  et de récolte prévue, d’un statut et d’une photo.
- Modification, remplacement de la photo et suppression sous réserve des
  dépendances.
- Recherche et filtrage par statut.
- Affichage des cultures à récolter prochainement sur le tableau de bord.
- La récolte s’enregistre depuis l’action dédiée sur la fiche de culture, avec
  quantité, unité, date et note facultative. Une même culture peut être
  récoltée plusieurs fois; les quantités sont cumulées et le journal conserve
  chaque récolte séparément, avec le compte qui l’a enregistrée.
- Le statut passe automatiquement à « Récoltée » à la première récolte. Une
  culture nouvellement créée ne peut pas être marquée récoltée sans enregistrer
  sa quantité.

Les statuts proposés sont : planifiée, en croissance, récoltée et en pause.

### 3.5 Activités agricoles

- Enregistrement d’une activité sur une parcelle, avec son type, son titre, sa
  date, des notes facultatives et une photo.
- Modification, remplacement de la photo et suppression sous réserve des
  dépendances.
- Types proposés : semis, arrosage, fertilisation, traitement, récolte et autre.
- Association facultative d’un revenu ou d’une dépense à l’activité au moment
  de sa création.
- Affichage des activités récentes sur le tableau de bord.

### 3.6 Finances

- Enregistrement de revenus et de dépenses en FCFA.
- Catégorisation des dépenses générales : autre dépense, main-d’œuvre,
  transport, matériel et équipement, entretien.
- Rattachement d’une transaction à une parcelle et association facultative à
  une activité, une culture ou une campagne réelle de cette parcelle.
- Saisie séparée de la date réelle de l’opération et de la date de saisie.
  Les anciennes transactions sans date métier restent explicitement marquées
  comme non renseignées plutôt que de reprendre leur date de création.
- Consultation des totaux de revenus, dépenses et solde.
- Consultation des transactions avec recherche et filtre par type.
- Sous-sections dédiées aux revenus, dépenses, intrants et ventes.

### 3.7 Intrants, ventes et stocks

- Enregistrement d’un achat d’intrant avec nom, quantité, unité, coût, parcelle
  et association facultative à une activité.
- Enregistrement d’une vente avec produit, quantité, unité, montant, parcelle
  et association facultative à une activité.
- Une vente est toujours rattachée à une culture au statut « Récoltée » avec
  quantité et unité renseignées. Le produit, l’unité et la parcelle sont repris
  de cette culture; les ventes cumulées ne peuvent pas dépasser la récolte
  disponible. Les quantités vendues sont déduites de la récolte restante, sans
  modifier l’historique des ventes.
- Une vente peut être annulée par un propriétaire ou gestionnaire de la ferme.
  L’annulation est conservée dans le journal avec sa date et son auteur, mais
  elle est exclue des totaux financiers, des rapports et des quantités vendues;
  sa quantité redevient disponible.
- Les formulaires de vente affichent uniquement les récoltes encore vendables,
  leur quantité disponible et bloquent la saisie d’une quantité supérieure.
- Les formulaires de ferme, parcelle, culture, activité et finance présentent
  des consignes courtes et des exemples pour clarifier les champs attendus.
- Consultation des enregistrements d’intrants et de ventes, avec recherche.
- Enregistrement des entrées, sorties, transferts, ajustements positifs ou
  négatifs et retours d’intrants.
- Le formulaire guide la saisie selon le type de mouvement, masque les champs
  qui ne s’appliquent pas et filtre les parcelles, cultures et activités selon
  la ferme sélectionnée.
- Les stocks existants peuvent préremplir le nom et l’unité. Le solde disponible
  est affiché et la quantité demandée est contrôlée avant l’envoi. Pour les
  retours, le choix d’une sortie encore retournable préremplit aussi l’activité
  d’origine et la quantité maximale.
- Les transferts vers le même emplacement sont signalés; le coût est demandé
  uniquement pour un approvisionnement. Les contrôles serveur restent actifs.
- Déduction des sorties et transferts du stock disponible; une sortie ne peut
  pas dépasser le stock enregistré.
- Association possible d’une sortie à une activité ou une culture. Un retour
  associé à une activité ne peut pas dépasser la quantité sortie pour celle-ci.
- Les achats d’intrants sont également inscrits en dépenses financières. Les
  achats déjà enregistrés sont repris dans le calcul du stock et sont
  automatiquement rattachés à la campagne de leur activité ou culture, le cas
  échéant.
- Le stock est suivi par ferme, parcelle, intrant et unité. Le coût affiché
  correspond au cumul des achats, et non à la valorisation comptable du stock
  restant.

### 3.8 Rapports et exports

- Téléchargement d’un fichier CSV compatible avec les tableurs.
- Le fichier comprend les transactions financières accessibles et un résumé
  par culture des quantités récoltées, revenus, dépenses directement associées
  et marge calculée.
- Les transactions non associées à une culture ne sont pas imputées à la marge
  d’une culture particulière.

### 3.9 Campagnes et simulations — première version

- Une campagne réelle est rattachée à une ferme, une parcelle et une culture.
- Les campagnes réelles et les simulations utilisent exclusivement une culture
  active du référentiel BAYKAT. Le producteur ne peut pas saisir un nom libre;
  une culture absente doit d’abord être ajoutée et validée dans le référentiel
  par un administrateur. Les ajouts bloquent les doublons après normalisation
  des majuscules, accents, espaces, ponctuation, pluriels simples en « s » ou
  « x » et noms très proches. Le catalogue initial reprend les 19 produits
  agricoles affichés par la [Bourse des Prix BayKat](https://baykat.sn/bourse-des-prix/),
  complétés par l’arachide, le mil et le blé, qui sont aussi des cultures;
  l’huile est exclue car ce n’est pas une culture à semer. Les campagnes
  existantes restent inchangées.
- Une campagne réelle expose deux phases indépendantes : production et
  commercialisation. La commercialisation ne peut démarrer qu’après la fin de
  la production.
- Les activités peuvent être rattachées à une campagne. Les transactions
  rattachées à une activité reprennent cette campagne; celles liées à la culture
  de la campagne y sont également associées automatiquement. Une transaction
  peut aussi être reliée directement à une campagne de la même parcelle.
- Le tableau de bord réel affiche la superficie suivie, les récoltes, le
  rendement lorsque l’unité est le kilogramme, les dépenses et revenus
  explicitement rattachés, le résultat enregistré, la marge sur revenus et le
  nombre d’activités et d’opérations. Les anciennes transactions reliées à une
  activité ou à une culture de campagne sont associées lors de la mise à niveau.
- Les montants du tableau de bord sont des totaux des opérations enregistrées
  et rattachées; ils ne prétendent pas inclure les coûts qui n’ont pas été
  saisis ou associés à la campagne.
- Une simulation peut être créée sans ferme ni parcelle. La superficie, le
  rendement attendu, les coûts prévus et le prix espéré sont saisis par
  l’utilisateur; le plugin ne préremplit pas les hypothèses.
- Avant son enregistrement, la simulation prévisualise la récolte (surface ×
  rendement), les recettes (récolte × prix), le résultat simplifié (recettes −
  coûts) et le financement à mobiliser (coûts − fonds disponibles, avec un
  plancher à zéro). Elle calcule aussi les seuils de prix et de rendement qui
  couvriraient les coûts, toutes les autres hypothèses restant inchangées.
- La simulation compare les outils et intrants déclarés nécessaires à ceux
  déclarés disponibles, par correspondance de nom sans tenir compte de la
  casse, des accents ni des espaces répétés. Elle ne recommande pas
  automatiquement de matériel.
- Ces calculs sont déterministes et n’utilisent que les entrées de l’utilisateur.
  Aucun rendement-type, coût-type, calendrier ou conseil agronomique n’est
  déduit du seul nom de culture. Le résultat ne tient compte que des coûts
  saisis et n’est pas un bénéfice comptable garanti.
- Une simulation enregistrée n’est pas incluse dans les rapports ou chiffres
  réels.
- Une simulation peut être convertie explicitement en campagne réelle après le
  choix d’une ferme et d’une parcelle. La culture, la variété, la superficie,
  les dates prévues et les hypothèses de devis sont reprises comme prévisions
  liées à la campagne; elles ne créent ni récolte, ni dépense, ni revenu réel.
- Les données de campagne sont stockées dans une table dédiée et la mise à
  niveau du schéma est automatique.
- Le plugin local comprend un registre du stock récolté par campagne et par
  lot, avec réservation, libération, pertes et autres sorties. Les réservations
  ne diminuent pas le stock physique et ne constituent pas des ventes.
- Les saisies manuelles de nouvelles ventes sont bloquées, tout en conservant
  l’historique et la logique d’annulation existants. L’intégration aux ventes,
  commandes et retours officiels de « Mes ventes » reste à réaliser après
  réception du code source et validation des statuts BAYKAT.
- L’export PDF et les tests de migration sur une base de préproduction restent
  à réaliser.

## 4. Données et comptes

- Les données sont stockées dans les tables WordPress du site courant, créées
  avec le préfixe configuré pour l’installation (`$wpdb->prefix`).
- Les tables gérées par le plugin couvrent les fermes, parcelles, cultures,
  campagnes, activités, transactions, partages de fermes et mouvements de
  stock.
- Par défaut, les données appartiennent au compte WordPress propriétaire.
  Celui-ci peut partager une ferme avec un compte existant comme gestionnaire
  (consultation et modification) ou lecteur (consultation seule).
- L’accès est vérifié au niveau de chaque ferme et de ses données liées.
- Les lecteurs peuvent consulter les informations sans voir les formulaires ni
  les actions de modification.
- Les formulaires de modification utilisent des nonces WordPress.
- Les photos sont enregistrées dans la médiathèque WordPress et leur
  identifiant est associé à l’enregistrement agricole.
- Le schéma de base de données évolue automatiquement lors d’une requête
  WordPress après mise à jour du plugin.

## 5. Limites et éléments non encore réalisés

- Le partage est accordé ferme par ferme et nécessite que la personne invitée
  possède déjà un compte WordPress; les invitations par courriel ne sont pas
  encore proposées.
- Le bouton « Gérer ma ferme » dans les onglets « Réglages » de l’espace
  utilisateur Baykat n’est pas encore intégré.
- L’emplacement final du bouton et le lien vers la page dédiée restent à
  configurer dans le site Baykat.
- La connexion et l’authentification reposent sur WordPress ; le plugin ne
  fournit pas de système de comptes séparé.

## 6. Mise en place de la page de gestion

1. Activer le plugin dans **Extensions → Extensions installées**.
2. Créer ou réutiliser une page WordPress publiée, par exemple
   **Gestion de ferme**.
3. Insérer `[gerer_ma_ferme]` dans le contenu de cette page, puis la publier.
4. Vérifier avec un utilisateur connecté que la page affiche l’application.
5. Une fois l’accès au site Baykat disponible, ajouter dans l’espace utilisateur
   le bouton **Gérer ma ferme**, pointant vers l’URL de cette page.

## 7. Principaux modules

| Module | Fonction principale |
|---|---|
| Vue d’ensemble | Indicateurs, calendrier des récoltes, activités, finances et gestion des fermes |
| Parcelles | Parcelles rattachées aux fermes |
| Cultures | Plantations, variétés, statuts et dates |
| Campagnes | Deux phases de suivi, planification et simulation prévisionnelle |
| Activités | Journal des opérations agricoles |
| Finances | Revenus, dépenses, intrants et ventes |
| Stocks | Mouvements d’intrants et quantités disponibles |
| Rapports | Export CSV des finances et de la rentabilité par culture |
