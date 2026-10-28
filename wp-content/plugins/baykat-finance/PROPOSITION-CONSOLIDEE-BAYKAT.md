# BAYKAT — Gérer ma ferme

## Proposition consolidée de périmètre, lots, budget et modalités

**Document de travail pour la réunion du 23 octobre 2026**  
**Présenté par : Lamine**  
**Statut : proposition de discussion — chiffrage à confirmer après audit technique**

---

## 1. Ouverture de la réunion

> Merci pour votre note de revue et pour les tests réalisés sur la démonstration. J’ai repris les 21 demandes et les règles fonctionnelles de BAYKAT. Je propose de conserver le plugin existant comme base, de le faire évoluer en trois lots, et de reporter les fonctions P2 sans supprimer le code ni les données correspondants.
>
> Mon estimation actuelle pour la V1 est de **1 650 000 FCFA**, sur la base d’une évolution contrôlée du plugin. Je souhaite toutefois valider d’abord les dépendances techniques, en particulier l’intégration à l’espace client Listivo et le lien avec « Mes ventes ». À l’issue de cette vérification, nous pourrons confirmer le périmètre, le prix et le planning de chaque lot.

## 2. Compréhension du besoin et principes de réalisation

Je comprends que BAYKAT veut un outil de pilotage de la production agricole, et non un second outil de vente. La V1 couvrira les cultures uniquement.

Je retiens les principes suivants :

1. **Un espace client unique :** le module « Gérer ma production » doit s’intégrer à `/panel`, avec le compte BAYKAT existant et la charte de l’espace client.
2. **Une hiérarchie claire :** ferme → parcelle → campagne. Une campagne représente une culture donnée sur une période donnée, sur une parcelle.
3. **Un historique conservé :** les campagnes successives et leurs opérations ne doivent pas être écrasées par un simple renommage des cultures actuelles.
4. **Des opérations traçables :** la date réelle de l’opération est distincte de sa date de saisie.
5. **Une source unique pour chaque coût :** une dépense liée à un intrant ou à une activité ne doit pas être comptée une seconde fois.
6. **Pas de double circuit de vente :** les ventes BAYKAT proviennent de « Mes ventes » ; seules les ventes hors BAYKAT et les autres recettes agricoles sont saisies dans le module agricole.
7. **Des résultats clairement qualifiés :** les indicateurs constituent un résultat simplifié et ne remplacent pas une comptabilité.
8. **Une V1 maîtrisée :** partage de ferme, stock d’intrants avancé et carte ne sont pas des engagements de la V1, sauf décision expresse et faisabilité confirmée.

## 3. État de l’existant observé dans le plugin

J’ai commencé l’examen du plugin fourni. À ce stade, j’ai confirmé les éléments suivants dans son code :

- Les données sont déjà séparées entre fermes, parcelles, cultures, activités, transactions, journal des récoltes et mouvements de stock.
- Une culture est actuellement rattachée à une parcelle ; elle possède notamment un nom libre, une variété, des dates, un statut et des quantités récoltées.
- Les récoltes disposent déjà d’un journal distinct et le code d’enregistrement additionne les quantités du journal pour mettre à jour le total de la culture. Le constat de BAYKAT sur les deux récoltes de 10 kg reste à reproduire et à corriger dans la vue ou le calcul concerné.
- Les activités sont rattachées aux parcelles. Les transactions peuvent être reliées à une activité, une culture, une parcelle et une ferme.
- Les transactions enregistrent actuellement une date de création ; le schéma examiné ne comporte pas de date d’opération distincte.
- Le plugin contient déjà un export CSV, des écrans de suivi des ventes et des intrants, ainsi qu’un module de stock avancé.
- Les statuts de parcelle et de culture existent. Le partage de ferme avec les rôles Gestionnaire et Lecteur est également présent.
- Le schéma actuel ne contient pas d’entité campagne distincte. La base de données possède un mécanisme de versionnement et de mise à niveau, ce qui permet d’étudier une migration contrôlée, sans garantir avant audit qu’elle sera additive dans tous ses détails.

**Ce que je n’ai pas encore pu vérifier :** le code et les interfaces de Listivo dans l’environnement cible, le code et les données de « Mes ventes », les identifiants et statuts de vente disponibles, ainsi que les contraintes de préproduction et d’hébergement. Ces éléments conditionnent la confirmation du lot 3 et de certains points du lot 1.

## 4. Réponses et périmètre des demandes R01 à R21

Les estimations ci-dessous sont incluses dans les lots P1 ou indiquées comme options P2. Elles sont des estimations de travail, et non des engagements indépendants du périmètre et des hypothèses de ce document.

| N° | Réponse et réalisation proposée | Existant réutilisé / dépendance | Estimation |
|---|---|---|---|
| **R01 — Intégration** | Intégrer « Gérer ma production » dans `/panel`, à la position demandée, harmoniser la navigation et les styles, masquer la barre WordPress pour les clients et vérifier le parcours à 360 px. | Plugin actuel et authentification WordPress. Dépend de Listivo, des hooks disponibles et de la préproduction BAYKAT. | Lot 1 |
| **R02 — Campagnes** | Faire évoluer le modèle vers des campagnes rattachées à une parcelle, avec culture issue d’une liste, variété, surface, dates, statut et clôture. Permettre plusieurs campagnes successives et conserver les historiques. | Cultures et récoltes actuelles réutilisables après cartographie des données. Il faut décider après audit entre évolution du modèle actuel et nouvelle entité campagne ; je ne propose pas de renommer une culture si cela fait perdre son historique. | Lot 1 |
| **R03 — Activités** | Rattacher chaque activité à une campagne. Conserver un coût facultatif pour la main-d’œuvre ou une prestation ; retirer le revenu porté directement par une activité. | Écrans d’activités et transactions existants. Dépend du modèle de campagne et d’une règle claire sur la source du coût. | Lot 2 |
| **R04 — Dates réelles** | Ajouter une date d’opération obligatoire et modifiable pour les dépenses, intrants et ventes hors BAYKAT, en conservant séparément la date de création/saisie. | Les tables existantes ont des dates de création ; les activités et récoltes ont déjà des dates métier. Les anciennes transactions devront recevoir une valeur historique identifiée comme reprise, à valider par BAYKAT. | Lot 2 |
| **R05 — Dépenses** | Exiger un rattachement à une campagne ou à la ferme pour une charge commune. Ajouter la catégorie « Commercialisation ». Les charges communes restent au niveau de la ferme en V1. | Transactions existantes. Les charges communes ne seront pas réparties entre campagnes en V1. | Lot 2 |
| **R06 — Intrants** | Ajouter catégorie, produit de référence, option « Autre », quantité, unité, date et coût. Le lien à une activité reste facultatif et ne crée pas un second coût. | Écrans et mouvements existants à examiner. La liste de référence des produits et unités dépend de R12 et des données fournies par BAYKAT. | Lot 2 |
| **R07 — Récoltes** | Conserver le journal, reproduire le cas des deux entrées de 10 kg et corriger le total affiché et les agrégations concernées. | Journal de récoltes existant ; l’enregistrement cumule déjà les quantités du journal dans le code examiné. La recette vérifiera aussi les unités et la cohérence du total entre écran, CSV et PDF. | Lot 2 |
| **R08 — Indicateurs** | Afficher coûts, ventes/recettes, résultat simplifié, rendement, coût de production par kg et marge par campagne, parcelle et ferme, avec « Non disponible » quand le calcul n’a pas de dénominateur valide. | Export CSV existant, à auditer. Une règle de calcul commune devra alimenter les écrans et les exports. Les formules proposées figurent en section 5. | Lot 2 |
| **R09 — Bilan PDF** | Générer un bilan PDF par campagne et par ferme, avec identité, période, travaux, dépenses, récoltes, recettes et indicateurs. Conserver le CSV et afficher l’avertissement sur le résultat simplifié. | Le CSV existe ; aucun PDF n’a été confirmé dans le plugin examiné. Dépend de la bibliothèque PDF compatible avec l’hébergement. | Lot 3 |
| **R10 — Ventes BAYKAT** | Retirer la ressaisie des ventes réalisées sur BAYKAT et reprendre les ventes confirmées de « Mes ventes » via le lien annonce-campagne. Garder la saisie datée des ventes hors BAYKAT et autres recettes agricoles. | L’écran et les transactions de vente existent dans le plugin agricole, mais le code de « Mes ventes » et ses interfaces ne sont pas encore vérifiés. L’annonce ne pourra être liée qu’à une seule campagne à la fois. | Lot 3, sous réserve d’accès et d’interface exploitable |
| **R11 — Mettre en vente** | Ajouter un accès au parcours de « Mes ventes », prérempli avec la culture et la quantité disponible, si le module expose un mécanisme compatible. La récolte ne crée jamais automatiquement une annonce ou un stock mis en vente. | Dépend entièrement du formulaire, des champs et des hooks de « Mes ventes ». L’interface doit éviter de proposer deux fois la même quantité disponible. | Lot 3, sous réserve d’accès et d’interface exploitable |
| **R12 — Listes de référence** | Remplacer les champs concernés par des listes administrables : cultures, activités, catégories/produits d’intrants, catégories de dépenses et unités. Ajouter « Autre » lorsque pertinent. | Certains champs sont actuellement libres. Le contenu initial et les règles de gestion des listes doivent être fournis ou validés par BAYKAT. | Lot 1 |
| **R13 — Affichage des nombres** | Formater les quantités et surfaces sans décimales inutiles, avec unités cohérentes, et les montants en FCFA avec séparateur de milliers. Préserver une précision interne adaptée aux calculs. | Corrections d’affichage dans les écrans, CSV et PDF, selon les formats réellement utilisés par le plugin. | Lot 1 |
| **R14 — Archivage** | Archiver par défaut les fermes, parcelles et campagnes qui portent des données. N’autoriser la suppression définitive que pour les objets vides, après confirmation et contrôle des dépendances. | Les objets et statuts actuels devront être migrés avec précaution ; l’archivage n’est pas uniforme dans le schéma examiné. Les données historiques resteront consultables. | Lot 1 |
| **R15 — Statut de parcelle** | Retirer le statut de suivi propre à la parcelle de l’interface V1 et porter le suivi par la campagne. Conserver et traiter les valeurs historiques par migration contrôlée. | Le schéma actuel possède un statut de parcelle et un statut de culture. La règle de migration sera confirmée lors de l’audit. | Lot 1 |
| **R16 — Stock avancé** | Masquer en V1 les écrans de stock avancé, sans supprimer leur code ni leurs données. Prévoir une réactivation ultérieure. | Le module de mouvements de stock est présent. Vérifier que son masquage ne retire pas des fonctions nécessaires aux intrants de campagne. | P2 — option |
| **R17 — Partage de ferme** | Désactiver en V1 l’ajout et la gestion des rôles Gestionnaire et Lecteur, sans effacer les données ni le code de partage. | Le partage et les contrôles de rôle existent dans le plugin. Confirmer la stratégie de traitement des accès déjà créés et le maintien des accès du propriétaire. | P2 — option |
| **R18 — Carte et tracé** | Ne pas inclure une correction de carte dans le périmètre P1. Réaliser un diagnostic P2 ; ne proposer une correction que si elle n’ajoute pas de coût récurrent ni de dépendance externe non approuvée. | Le plugin contient des champs de coordonnées et de tracé ; la cause du chargement défaillant et les services utilisés restent à diagnostiquer. | P2 — diagnostic et correction éventuelle |
| **R19 — Pertes** | Ajouter ultérieurement une déclaration de perte datée, quantifiée et motivée, qui diminue la quantité disponible sans modifier l’historique des récoltes initiales. | Nouveau développement ; à préciser avec BAYKAT (motifs, corrections, annulations, unités). | P2 — option |
| **R20 — Charges communes** | En V1, conserver les charges communes au niveau ferme, sans répartition par campagne. Étudier en P2 une répartition au prorata des surfaces après validation des périodes et des surfaces de référence. | Nouveau calcul. Ne pas intégrer aux résultats de campagne en V1. | P2 — option |
| **R21 — Indicateurs avancés** | Étudier en P2 la comparaison de campagnes et les tendances pluriannuelles après stabilisation des données et des indicateurs V1. | Nouveau tableau de comparaison, dépendant de l’historique et de données suffisamment homogènes. | P2 — option |

## 5. Règles de calcul proposées pour la V1

Je propose de centraliser ces règles pour que les valeurs affichées dans l’interface, le CSV et le PDF soient identiques.

| Indicateur | Règle proposée | Affichage / limite |
|---|---|---|
| **Coût total de campagne** | Somme des coûts d’activités, intrants et dépenses affectés à la campagne, chaque coût compté une seule fois. | Les charges communes au niveau ferme ne sont pas réparties en campagne en V1. |
| **Ventes / recettes de campagne** | Recettes agricoles saisies hors BAYKAT, plus ventes confirmées du module « Mes ventes » associées à l’annonce liée à la campagne. | Une annonce publiée ou une réservation non confirmée ne constitue pas une recette. |
| **Coût de production par kg** | Coûts de campagne hors commercialisation ÷ quantité récoltée, sous réserve d’une unité compatible en kilogrammes. | « Non disponible » si aucune récolte valide ou si l’unité ne permet pas une conversion fiable. |
| **Rendement** | Quantité récoltée en kg ÷ surface cultivée en hectares. | « Non disponible » sans récolte, surface valide ou conversion d’unité confirmée. |
| **Résultat simplifié** | Recettes attribuées − charges affectées selon les règles de la V1. | Afficher clairement : « Résultat simplifié — ce n’est pas un bénéfice comptable. » |
| **Marge sur coûts suivis** | Résultat simplifié ÷ recettes × 100. | « Non disponible » sans recette. |
| **Quantité disponible indicative** | Récoltes enregistrées − ventes confirmées reprises de « Mes ventes » − ventes hors BAYKAT. | Une récolte n’est jamais transformée automatiquement en stock ou en annonce. La gestion des annonces actives, réservations et ventes partiellement annulées doit être confirmée avant l’intégration. |

Avant le développement, BAYKAT devra confirmer le traitement des remboursements, annulations, ventes partielles, unités, conversions en kilogrammes et annonces actives. Les montants sont exprimés en FCFA. Les indicateurs sont des indicateurs de gestion et ne constituent pas un résultat comptable certifié.

## 6. Lots, budget et délais

Les montants suivants sont mon **estimation indicative de V1**, fondée sur la réutilisation du plugin et les hypothèses de ce document.

| Lot | Périmètre | Estimation | Délai estimé |
|---|---|---:|---:|
| **Lot 1 — Structure** | R01, R02, R12, R13, R14, R15 | **450 000 FCFA** | **5 à 7 jours ouvrés** |
| **Lot 2 — Pilotage** | R03 à R08 | **650 000 FCFA** | **7 à 9 jours ouvrés** |
| **Lot 3 — Intégration BAYKAT** | R09, R10, R11 | **550 000 FCFA** | **6 à 8 jours ouvrés** |
| **Total V1 P1** | Trois lots | **1 650 000 FCFA** | **18 à 24 jours ouvrés** |

Le délai calendaire prévisionnel est de **4 à 5 semaines**, sous réserve de la disponibilité des accès, des données de référence, des jeux de test et des validations de BAYKAT. Le délai d’audit préalable et les périodes d’attente de validation ne sont pas inclus dans les jours ouvrés de réalisation indiqués ci-dessus.

### Audit technique préalable

Je propose une étape d’audit de **1 à 2 jours ouvrés** avant de figer le forfait. Elle couvrira :

- la cartographie des tables et des relations entre fermes, parcelles, cultures, activités, transactions et récoltes ;
- la stratégie de migration vers les campagnes et de conservation de l’historique ;
- la reproduction du problème de total des récoltes ;
- les interfaces et statuts disponibles dans « Mes ventes » ;
- les points d’intégration de Listivo et les contraintes de préproduction ;
- la disponibilité d’une solution PDF compatible avec l’hébergement ;
- les risques, hypothèses, critères de recette et chiffrage final par lot.

Le coût de cette étape est **à convenir séparément**. Nous pouvons convenir qu’il soit déduit du lot 1 si BAYKAT lance ensuite le projet. Aucun travail dépassant l’audit ne sera engagé sans validation du périmètre et du budget.

### Réserve sur le lot 3

Le prix de 550 000 FCFA suppose que BAYKAT donne accès au code ou à une interface documentée de « Mes ventes », que les identifiants annonce/vente et statuts nécessaires soient accessibles, et que le parcours de création d’annonce puisse recevoir les données de culture et de quantité. Si l’une de ces conditions n’est pas remplie, je présenterai une solution de remplacement et un éventuel avenant chiffré **avant** tout développement additionnel.

## 7. Options P2 — estimations indicatives séparées

Ces options ne sont pas incluses dans le total V1.

| Option | Estimation indicative |
|---|---:|
| R16 — Masquage/réactivation du stock avancé | 75 000 à 125 000 FCFA |
| R17 — Désactivation des rôles de partage | 50 000 à 100 000 FCFA |
| R18 — Diagnostic et correction de la carte si solution sans coût externe | 100 000 à 200 000 FCFA |
| R19 — Déclaration et déduction des pertes | 150 000 à 250 000 FCFA |
| R20 — Répartition des charges communes au prorata des surfaces | 150 000 à 250 000 FCFA |
| R21 — Comparaison de campagnes et tendances pluriannuelles | 200 000 à 350 000 FCFA |

Ces fourchettes sont des ordres de grandeur à confirmer après définition du besoin et vérification technique. Tout hébergement, licence, API ou service externe payant est exclu et devra être approuvé par BAYKAT avant engagement.

## 8. Recette proposée

Les développements et la recette se dérouleront sur une préproduction, jamais directement en production. BAYKAT fournira les accès et les données de test nécessaires.

| Lot | Critères de recette proposés |
|---|---|
| **Lot 1** | Parcours principal vérifié à 360 px ; tests avec deux comptes producteurs dont les données sont isolées ; création et clôture de campagnes successives sur une même parcelle ; historique conservé ; listes et statuts conformes aux règles validées. |
| **Lot 2** | Résultats conformes au jeu de 20 opérations datées fourni par BAYKAT ; deux entrées de récolte de 10 kg affichent 20 kg au total ; dates d’opération et dates de saisie distinctes ; coûts non comptés deux fois ; mêmes résultats dans les écrans et le CSV. |
| **Lot 3** | Une vente confirmée de « Mes ventes » apparaît une seule fois dans la campagne liée ; une annonce publiée ne génère aucune recette ; la quantité disponible suit les règles approuvées ; PDF par campagne et par ferme cohérent avec les écrans et comportant l’avertissement demandé. |

Je propose que chaque lot fasse l’objet d’une validation écrite. Nous devons convenir ensemble d’un délai de recette, par exemple cinq jours ouvrés après livraison, et du nombre de cycles de corrections inclus. Toute demande nouvelle ou modification de règle après validation du périmètre sera estimée séparément avant réalisation.

## 9. Planning indicatif

1. **Audit technique — 1 à 2 jours ouvrés :** accès plugin, base, Listivo, « Mes ventes » et préproduction ; décisions sur les hypothèses.
2. **Lot 1 — 5 à 7 jours ouvrés :** structure, intégration, campagnes, listes et archivage.
3. **Lot 2 — 7 à 9 jours ouvrés :** activités, opérations datées, dépenses, intrants, récoltes et indicateurs.
4. **Lot 3 — 6 à 8 jours ouvrés :** PDF et intégration aux ventes, sous réserve de faisabilité confirmée.
5. **Mise en production :** uniquement après recette validée, sauvegarde exploitable et accord de BAYKAT.

Le planning sera recalé après l’audit et pourra être suspendu en cas d’accès, de données de recette ou de validations manquants.

## 10. Éléments attendus de BAYKAT

Pour confirmer le périmètre et démarrer dans de bonnes conditions, j’aurai besoin de :

- un site de préproduction et une sauvegarde exploitable de la base et du code ;
- l’accès aux éléments nécessaires de Listivo et au code ou à la documentation de « Mes ventes » ;
- un interlocuteur technique pour les deux modules et l’espace `/panel` ;
- les listes de cultures, variétés, types d’activité, catégories/produits d’intrants, dépenses et unités ;
- un jeu de 20 opérations datées avec les résultats attendus ;
- la définition des statuts de campagne, des règles de clôture et des unités/surfaces ;
- les statuts de « Mes ventes » considérés comme confirmés et les règles relatives aux annulations, remboursements, ventes partielles, réservations et annonces actives ;
- la confirmation du comportement attendu quand une annonce est liée à une campagne puis modifiée ou clôturée.

## 11. Modalités contractuelles à discuter

### Paiement

La note BAYKAT prévoit un paiement par lot après recette validée. De mon côté, je propose la modalité suivante, **à discuter et à inscrire au devis ou au contrat si elle est acceptée** :

| Lot | Forfait estimatif | 40 % au lancement | 60 % après recette |
|---|---:|---:|---:|
| Lot 1 | 450 000 FCFA | 180 000 FCFA | 270 000 FCFA |
| Lot 2 | 650 000 FCFA | 260 000 FCFA | 390 000 FCFA |
| Lot 3 | 550 000 FCFA | 220 000 FCFA | 330 000 FCFA |
| **Total** | **1 650 000 FCFA** | **660 000 FCFA** | **990 000 FCFA** |

Si BAYKAT souhaite conserver un paiement intégral après recette, nous devrons convenir de critères de recette objectifs, d’un délai de validation et d’une procédure en cas de retour ou de demande hors périmètre.

### Livraison, code et garantie

- Une garantie corrective de **30 jours après la recette de chaque lot** est proposée pour les anomalies reproductibles relevant du périmètre livré. Les évolutions et changements de règles ne sont pas des corrections de garantie.
- Le code source des développements spécifiques destinés à BAYKAT sera livré selon les conditions de règlement du lot concerné. Le contrat distinguera ces développements du plugin préexistant, des composants tiers et de leurs licences respectives.
- Les données des producteurs seront traitées de manière confidentielle. Les accès de développement seront limités aux besoins du projet.
- Les sauvegardes, la migration, le plan de retour arrière et la mise en production seront validés avant intervention sur la production.
- Les taxes éventuelles, modalités de facturation et toute dépense de service externe restent à préciser au devis ou au contrat.

## 12. Points à décider pendant la réunion

Je propose que nous sortions de la réunion avec des décisions ou responsables identifiés sur les points suivants :

1. L’accès et la date de disponibilité de la préproduction, du code Listivo et de « Mes ventes ».
2. Le principe d’un audit de 1 à 2 jours, son coût ou sa déduction éventuelle du lot 1.
3. La règle de migration des cultures existantes vers les campagnes, notamment les données historiques.
4. La date à appliquer aux anciennes opérations et la manière de signaler les dates reprises de la saisie.
5. Les règles exactes de coût, vente confirmée, annulation, remboursement, unité et quantité disponible.
6. La liste des données de référence que BAYKAT fournira et la personne habilitée à les valider.
7. Les critères de recette, le délai de validation et le nombre de cycles de corrections inclus.
8. La modalité de paiement par lot et le traitement de la propriété du code spécifique.
9. La confirmation que la carte, le partage et le stock avancé restent hors V1.
10. La date de démarrage et le calendrier de validation de chaque lot.

## 13. Formulation de clôture à dire en réunion

> Pour résumer, je confirme que le plugin existant peut servir de base et qu’il contient déjà plusieurs éléments utiles : fermes, parcelles, cultures, activités, journal des récoltes, transactions, stock et export CSV. Il faut toutefois faire évoluer le modèle pour introduire les campagnes, distinguer les dates d’opération des dates de saisie et fiabiliser les calculs.
>
> Mon estimation indicative de la V1 est de 1 650 000 FCFA sur 18 à 24 jours ouvrés de réalisation, répartis entre les trois lots proposés. Je ne souhaite pas vous confirmer un forfait ferme avant d’avoir vérifié les dépendances à Listivo et à « Mes ventes ». Je propose donc un audit court, puis un devis consolidé avec critères de recette et planning définitifs.
>
> Les fonctions P2 restent séparées. Toute dépendance externe payante ou tout développement supplémentaire de « Mes ventes » fera l’objet d’un accord préalable. Une fois ces hypothèses validées ensemble, nous pourrons lancer le premier lot en préproduction.

---

**Fin du document — proposition de discussion, à consolider après la réunion et l’audit technique.**
