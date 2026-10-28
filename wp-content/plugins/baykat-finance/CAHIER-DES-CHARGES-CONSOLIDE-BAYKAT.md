# Cahier des charges consolidé — « Gérer ma ferme »

**Version :** 1.0 — document de travail pour validation  
**Date :** 22 octobre 2026  
**Réunion de revue :** 23 octobre 2026 à 10 h 00, heure de Dakar  
**Émetteur :** Lamine, prestataire technique  
**Destinataire :** BAYKAT — Mbaye  
**Références :** note de revue BAYKAT du 22 octobre 2026 ; cahier des charges fonctionnel BAYKAT V3 du 8 octobre 2026  
**Statut :** proposition consolidée ; le périmètre, les estimations et les dépendances restent à confirmer conjointement après audit technique.

---

## 1. Objet du document

Ce document répond au registre des demandes R01 à R21 de BAYKAT et propose une base commune pour valider le périmètre fonctionnel, les choix de réalisation, les charges, les lots, les critères de recette et les dépendances.

Les estimations ci-dessous sont indicatives. Elles supposent la réutilisation du plugin agricole existant et ne valent pas engagement définitif avant l’audit du thème Listivo, de l’espace `/panel`, du module « Mes ventes », de l’hébergement et des données à migrer.

## 2. Compréhension du besoin

Je comprends que BAYKAT souhaite un outil de **suivi et de pilotage de la production agricole**, intégré à l’espace client existant. Il ne s’agit pas de créer un deuxième outil de vente.

La V1 couvre les cultures. L’élevage reste hors périmètre.

### Principes retenus

1. **Un parcours client unique :** le producteur se connecte avec le parcours BAYKAT existant, puis ouvre « Gérer ma production » dans `/panel`. Le plugin agricole ne remplace ni la page d’accueil ni la page de connexion BAYKAT et ne détourne pas la redirection après connexion.
2. **Une hiérarchie métier :** ferme → parcelle → campagne. Le catalogue de cultures est un référentiel de types (par exemple, tomate), pas une plantation. Une campagne est une production datée d’une culture sur une parcelle et une superficie déterminées ; la même culture peut donc avoir plusieurs campagnes successives.
3. **Un historique conservé :** les campagnes successives et leurs opérations restent consultables ; une nouvelle campagne ne remplace pas l’historique de la précédente. Les anciennes récoltes et opérations ne sont rattachées automatiquement que lorsque leur correspondance avec une campagne est unique.
4. **Des opérations datées :** la date de l’opération est distincte de la date de création ou de saisie.
5. **Un coût compté une seule fois :** une dépense liée à un intrant ou à une activité n’est pas comptabilisée une seconde fois.
6. **Pas de doublon de ventes :** les ventes BAYKAT sont reprises de « Mes ventes » si l’intégration est confirmée ; les ventes hors BAYKAT et les autres recettes sont saisies dans le module agricole.
7. **Des résultats qualifiés :** le résultat affiché est un indicateur de gestion simplifié, pas un bénéfice comptable.
8. **Une V1 resserrée :** le stock d’intrants avancé, le partage de ferme et la carte sont reportés ou masqués selon les décisions de BAYKAT, sans suppression des données ni du code existant.

## 3. État de l’existant et constats de la démonstration

L’examen du plugin fourni montre que plusieurs briques peuvent être réutilisées :

- tables et écrans de fermes, parcelles, cultures, activités et transactions ;
- journal des récoltes ;
- rattachements des transactions à des fermes, parcelles, activités et cultures ;
- mouvements de stock d’intrants et rôles de partage ;
- export CSV et mécanisme de mise à niveau du schéma.

Lors de l’audit initial, le plugin ne comportait pas d’entité distincte de campagne agricole. Le code local a depuis évolué : il dispose d’une entité de campagne, d’un référentiel administrable des cultures, de campagnes de simulation indépendantes d’une ferme, et d’un journal de récoltes rattaché directement à la campagne. Les associations historiques ambiguës restent volontairement non attribuées. Le catalogue initial est alimenté à partir des 19 produits agricoles présentés par la [Bourse des Prix BayKat](https://baykat.sn/bourse-des-prix/), complétés par l’arachide, le mil et le blé; l’huile est exclue car ce n’est pas une culture à semer. Les campagnes ne proposent pas de saisie libre; une culture manquante doit d’abord être ajoutée au référentiel par un administrateur. Les ajouts bloquent les doublons après normalisation des majuscules, accents, espaces, ponctuation, pluriels simples et noms très proches.

La navigation de production est désormais centrée sur les campagnes ; les anciennes plantations restent conservées comme données historiques et ne sont pas supprimées. Le plugin local inclut maintenant un registre de stock récolté par lot, avec réservations, libérations, pertes et autres sorties physiques. Une réservation ne diminue pas le stock physique et n’est pas une vente. Les saisies manuelles de nouvelles ventes sont bloquées ; les anciennes transactions et leur logique d’annulation sont conservées. Les ventes BAYKAT, remboursements et retours ne sont pas connectés faute d’accès au code et aux statuts officiels de « Mes ventes ». Le code local n’a pas encore été déployé ni validé sur une base de préproduction.

Les interfaces et les règles de « Mes ventes » ainsi que les hooks d’intégration à Listivo restent à auditer. La stratégie de migration ne remplace pas les dates manquantes par des dates supposées et ne rattache pas automatiquement les données historiques lorsqu’il existe plusieurs campagnes possibles.

La note BAYKAT rapporte notamment deux entrées de récolte de 10 kg pour les tomates avec un total affiché de 10 kg. Une correction locale a été préparée pour calculer le cumul à partir du journal et fiabiliser le contrôle des quantités disponibles ; elle devra être vérifiée avec le jeu de recette BAYKAT avant tout déploiement.

La note rapporte également une unité « Kk », des nombres affichés avec des décimales non nécessaires, l’absence de bilan PDF et un chargement de carte défaillant lors du test. La carte devra être reproduite en préproduction : je ne considère pas encore sa cause comme établie.

**Aucune modification des données de production de BAYKAT n’est incluse dans cette proposition.** Toute migration fera l’objet d’une sauvegarde, d’une vérification en préproduction et d’une validation préalable.

## 4. Réponses au registre R01–R21

Les charges sont en **jours ouvrés estimatifs par demande**. Elles se chevauchent parfois avec les travaux transversaux d’un lot ; elles ne doivent donc pas être additionnées isolément pour obtenir le prix. Les montants forfaitaires proposés sont donnés à la section 6.

| N° / priorité | Réponse de Lamine — faisabilité et traitement proposé | Existant réutilisé / adaptation ou développement | Charge indicative |
|---|---|---|---:|
| **R01 — Intégration / P1** | **Faisable sous réserve d’audit Listivo.** Le producteur utilise la page d’accueil, la connexion et le compte BAYKAT existants ; après connexion, il trouve « Gérer ma production » dans `/panel`, entre « Mes annonces » et « Favoris ». Le plugin ne crée pas de parcours d’accueil ou de connexion concurrent et masque la barre d’administration WordPress sur l’application agricole. Le parcours principal sera vérifié à 360 px. | Plugin agricole, authentification et espace client BAYKAT existants. Adaptation de la navigation `/panel` et de l’interface ; l’accès au code et à la préproduction Listivo est requis. | **1 à 1,5 j** |
| **R02 — Campagnes / P1** | **Faisable, avec décision de modèle à confirmer à l’audit.** Je transforme le suivi actuel en campagnes liées à une parcelle, avec culture de référence, variété, surface, dates, statut et clôture. Plusieurs campagnes successives doivent rester consultables. Je ne renommerai pas simplement les cultures si cela détruit leur historique. | Réutilisation des cultures, parcelles, récoltes et relations existantes ; adaptation du schéma et migration contrôlée. Le choix entre faire évoluer les cultures et créer une table de campagnes dépend de la cartographie et du volume historique. | **1,5 à 2,5 j** |
| **R03 — Activités / P1** | **Faisable.** Je rattache les activités à la campagne et conserve un coût facultatif de main-d’œuvre ou de prestation. Je retire la possibilité d’enregistrer un revenu directement sur une activité. | Écrans d’activités et transactions. Adaptation des relations, formulaires, requêtes et règles de calcul ; reprise des anciennes activités à valider. | **0,75 à 1 j** |
| **R04 — Dates / P1** | **Faisable.** J’ajoute une date d’opération obligatoire et modifiable pour les dépenses, les intrants et les ventes hors BAYKAT, tout en conservant la date de saisie. La règle de reprise des anciennes opérations sera validée par BAYKAT. | Dates d’activités et de récoltes déjà présentes ; date de création des transactions. Nouveau champ date métier, migration et adaptation des formulaires, filtres et exports. | **1 à 1,5 j** |
| **R05 — Dépenses / P1** | **Faisable.** Toute dépense est rattachée à une campagne ou à la ferme si elle est commune. J’ajoute « Commercialisation ». En V1, les charges communes restent au niveau ferme et ne sont pas réparties sur les campagnes. | Transactions, catégories et rattachements existants. Adaptation des validations et calculs ; la catégorie de commercialisation est exclue du coût de production par kg, mais incluse au résultat selon les règles validées. | **0,5 à 0,75 j** |
| **R06 — Intrants / P1** | **Faisable.** Je structure les intrants par catégorie, produit de référence et choix « Autre », avec quantité, unité, date et coût. Le rattachement à une activité reste facultatif et ne crée jamais un second coût. | Mouvements de stock et formulaires existants. Adaptation pour le suivi des intrants de campagne ; listes initiales et unités à fournir ou valider par BAYKAT. | **0,75 à 1,25 j** |
| **R07 — Récoltes / P1** | **Faisable et prioritaire.** Je conserve le journal et corrige le cumul ainsi que les contrôles qui en dépendent. Le cas de recette inclura deux entrées de 10 kg, un total de 20 kg et une vente de 1 kg laissant 19 kg disponibles. | Journal des récoltes et transactions existants. Correction préparée dans le plugin local ; vérification des unités, affichages, CSV et disponibilités à effectuer en préproduction. | **0,5 à 1 j** |
| **R08 — Indicateurs / P1** | **Faisable après validation des règles de calcul.** J’affiche par campagne, parcelle et ferme le coût total, les ventes/recettes, le résultat simplifié, le rendement, le coût par kg et la marge. Un indicateur impossible à calculer est affiché « Non disponible », et non comme zéro. | Agrégations de transactions et export CSV existants. Calculs communs à créer ou adapter pour l’écran et les exports. Les charges communes restent au niveau ferme en V1. | **2,5 à 3 j** |
| **R09 — Bilan PDF / P1** | **Faisable sous réserve de compatibilité avec l’hébergement.** Je produis un bilan PDF par campagne et par ferme avec identité, période, travaux, dépenses, récoltes, recettes, indicateurs et mention « résultat simplifié ». Le CSV existant est conservé. | Données et export CSV existants. Nouveau rendu PDF et tests de cohérence avec l’écran ; bibliothèque, polices et limites d’hébergement à auditer. | **2 à 3 j** |
| **R10 — Ventes BAYKAT / P1** | **Faisable sous réserve d’accès et de données exploitables dans « Mes ventes ».** Une vente confirmée devra alimenter une seule fois la campagne liée, sans ressaisie. Les saisies manuelles de nouvelles ventes sont suspendues dans le plugin local ; l’historique existant et l’annulation des transactions sont conservés. Une annonce publiée ou une réservation ne constitue pas une recette. | Le registre local sait distinguer les ventes historiques des sorties de stock idempotentes, mais aucun connecteur BAYKAT n’est actif. Il faut les identifiants d’annonce et de vente ainsi que les statuts officiels ; annulations, remboursements, retours et ventes partielles restent à définir avec BAYKAT. | **2 à 3 j** |
| **R11 — Mettre en vente / P1** | **Faisable sous réserve des interfaces de « Mes ventes ».** Le plugin local permet déjà de réserver une quantité récoltée à la commercialisation ou à une autre destination, puis de libérer la réservation ou de déclarer une perte/sortie. Cette réservation ne crée ni annonce ni vente ; la récolte ne crée jamais automatiquement une annonce. | Registre de stock par campagne et par lot ajouté localement. Le préremplissage du parcours d’annonce et la synchronisation avec le stock de « Mes ventes » attendent l’accès au code ou à une interface documentée. | **1 à 2 j** |
| **R12 — Listes de référence / P1** | **Faisable.** Je remplace les principaux champs libres par des listes administrables par BAYKAT : cultures, types d’activité, catégories et produits d’intrants, dépenses et unités, avec « Autre » quand pertinent. | Formulaires et champs actuels. Ajout/paramétrage des référentiels, interface d’administration et règles d’évolution. BAYKAT fournit ou valide le contenu initial. | **0,75 à 1 j** |
| **R13 — Affichage des nombres / P1** | **Faisable.** J’affiche les quantités et surfaces sans décimales inutiles, conserve une précision suffisante pour les calculs et formate les montants en FCFA avec séparateur de milliers. Exemple : « 10 kg » et « 40 ha ». | Helpers et affichages existants ; certains formats peuvent être harmonisés. Vérification des écrans, du CSV et du PDF. | **0,25 à 0,5 j** |
| **R14 — Archivage / P1** | **Faisable sous réserve de cartographie des dépendances.** Les fermes, parcelles et campagnes portant des données sont archivées par défaut. La suppression définitive est réservée aux objets vides, avec confirmation et contrôle des relations. | Statuts, relations et données existants. Adaptation des écrans, règles de suppression et migrations ; les données historiques ne sont pas effacées. | **0,75 à 1 j** |
| **R15 — Statut de parcelle / P1** | **Faisable.** Je retire le statut de suivi propre à la parcelle de l’interface V1 et porte le suivi opérationnel par la campagne. Les anciennes valeurs sont traitées par migration contrôlée. | Statuts actuels des parcelles et cultures. Adaptation des formulaires, vues et migration ; mapping des statuts historiques à valider. | **0,25 à 0,5 j** |
| **R16 — Stock d’intrants / P2** | **Faisable à chiffrer séparément.** Je masque en V1 les écrans de stock avancé et conserve le code et les données pour une réactivation ultérieure. Je vérifierai que cela ne masque pas le suivi des intrants nécessaire à la V1. | Module existant de mouvements, transferts, inventaire et retours. Masquage des accès et contrôle des dépendances. | **1 à 2 j** — option |
| **R17 — Partage de ferme / P2** | **Faisable à chiffrer séparément.** Je désactive en V1 la gestion des rôles Gestionnaire et Lecteur, sans supprimer les données de partage. Le traitement des accès préexistants et le maintien des droits du propriétaire seront validés. | Table, rôles et contrôles d’accès existants. Désactivation des écrans et vérification des permissions. | **0,5 à 1 j** — option |
| **R18 — Carte et tracé / P2** | **Diagnostic d’abord.** BAYKAT a constaté un défaut de chargement le 22 octobre. Je reproduis le cas en préproduction, identifie la cause et ne propose une correction que si elle n’entraîne pas de coût récurrent ou de dépendance externe non approuvée. | Coordonnées, tracés et affichage cartographique présents dans le plugin. Diagnostic et correction éventuelle ; aucune cause n’est présumée avant reproduction. | **1 à 3 j** — option, à confirmer après diagnostic |
| **R19 — Pertes / P2** | **Nouveau développement, faisable à chiffrer séparément.** Une perte datée, quantifiée et motivée diminue la quantité disponible, sans effacer la récolte initiale. Les corrections et annulations restent traçables. | Journal de récoltes et calcul de disponibilité existants. Nouveau type d’événement et adaptation des écrans et indicateurs ; motifs et unités à définir. | **2 à 4 j** — option |
| **R20 — Charges communes / P2** | **Nouveau développement, faisable à chiffrer séparément.** Je peux étudier une répartition au prorata des surfaces cultivées après validation des périodes, surfaces de référence et règles d’arrondi. En V1, ces charges restent au niveau ferme. | Dépenses de ferme et surfaces existantes. Nouveau calcul avec traçabilité ; aucune répartition par campagne dans le périmètre P1. | **2 à 4 j** — option |
| **R21 — Indicateurs avancés / P2** | **Nouveau développement, faisable à chiffrer séparément après stabilisation de la V1.** Comparaison de campagnes et tendances pluriannuelles, sous réserve d’un historique suffisamment homogène. | Historique de campagnes et indicateurs V1 à construire. Nouvelles vues, règles de comparaison et vérification de la qualité des données. | **3 à 5 j** — option |

## 5. Règles fonctionnelles consolidées

### 5.1 Campagnes et historique

- Une campagne est rattachée à une parcelle et correspond à une culture sur une période définie.
- Une campagne porte au minimum la culture, la variété, la surface cultivée, les dates et le statut.
- Plusieurs campagnes successives sont possibles sur une même parcelle.
- Les activités, intrants, dépenses, récoltes et recettes sont rattachés à une campagne, sauf les charges communes rattachées à la ferme.
- La clôture d’une campagne conserve les données et l’historique ; toute réouverture ou correction suit une règle à valider par BAYKAT.

### 5.2 « Mes ventes » et disponibilité

1. Une annonce BAYKAT peut être liée à une campagne et à une seule campagne à la fois.
2. Une vente confirmée sur cette annonce devient une recette de la campagne liée, une seule fois, au moment où elle est confirmée selon les statuts convenus.
3. Une annonce publiée ou une réservation non confirmée ne crée pas de recette.
4. Une récolte ne devient jamais automatiquement du stock mis en vente ; le producteur décide de créer ou non une annonce.
5. La quantité disponible indicative est calculée ainsi :

   **Récoltes enregistrées − ventes confirmées reprises de « Mes ventes » − ventes hors BAYKAT − pertes validées**

   Les pertes ne sont pas incluses au calcul P1 ; elles relèvent de R19 en P2.
6. Les ventes annulées ou remboursées, ventes partielles, réservations et changements de lien annonce-campagne doivent être définis avec BAYKAT avant l’intégration.

### 5.3 Coûts, résultats et indicateurs

| Indicateur | Règle de calcul proposée | Règle d’affichage |
|---|---|---|
| Coût total de campagne | Activités + intrants + dépenses affectés à la campagne, chaque coût compté une seule fois. | Montant en FCFA, séparateur de milliers. |
| Recettes de campagne | Ventes confirmées de « Mes ventes » liées à la campagne + ventes hors BAYKAT et autres recettes saisies. | Une publication ou réservation ne vaut pas recette. |
| Coût de production par kg | Coûts de campagne hors commercialisation ÷ récolte exprimée en kg. | « Non disponible » sans récolte valide ou conversion fiable. |
| Rendement | Récolte exprimée en kg ÷ surface cultivée en hectares. | « Non disponible » sans quantité, surface ou conversion valide. |
| Résultat simplifié | Recettes attribuées − charges affectées selon les règles V1. | Mention visible : « Résultat simplifié — ce n’est pas un bénéfice comptable. » |
| Marge sur coûts suivis | Résultat simplifié ÷ recettes × 100. | « Non disponible » sans recette. |

**Charges communes :** en V1, elles restent rattachées à la ferme et entrent dans le résultat de la ferme ; elles ne sont pas réparties entre campagnes.  
**Commercialisation :** cette catégorie entre dans le résultat, mais pas dans le coût de production par kg.  
**Unités :** une conversion n’est appliquée que si elle est explicitement fiable et validée ; sinon l’indicateur concerné est « Non disponible ».

## 6. Lots, budget et délais indicatifs

| Lot | Demandes incluses | Forfait estimatif | Charge estimative | Critères de recette proposés |
|---|---|---:|---:|---|
| **1 — Structure** | R01, R02, R12, R13, R14, R15 | **450 000 FCFA** | **5 à 7 jours ouvrés** | Parcours principal à 360 px ; deux comptes producteurs avec données isolées ; campagnes successives sur une parcelle ; historique conservé ; listes, archivage et statuts conformes aux règles validées. |
| **2 — Pilotage** | R03 à R08 | **650 000 FCFA** | **7 à 9 jours ouvrés** | Jeu de 20 opérations datées fourni par BAYKAT ; résultats attendus identiques ; récolte de 10 + 10 = 20 kg ; ventes et quantités disponibles cohérentes ; aucun coût compté deux fois ; mêmes données dans les écrans et le CSV. |
| **3 — Intégration BAYKAT** | R09, R10, R11 | **550 000 FCFA** | **6 à 8 jours ouvrés** | Vente confirmée de « Mes ventes » reprise une seule fois dans la campagne liée ; aucune recette à la publication d’une annonce ; quantité disponible selon les règles validées ; PDF par campagne et par ferme cohérent avec l’écran et qualifiant le résultat de simplifié. |
| **Total V1 — P1** | R01 à R15 selon le découpage convenu | **1 650 000 FCFA** | **18 à 24 jours ouvrés** | Recette et validation écrites lot par lot. |

Le délai calendaire estimatif est de **4 à 5 semaines**, hors audit et hors périodes d’attente d’accès, de données, de décisions ou de validation.

Les prix sont des **estimations indicatives à confirmer après audit**. Ils supposent que les évolutions peuvent être réalisées sans refonte imprévue, que le code et les environnements nécessaires sont accessibles, et que les règles de gestion sont validées avant réalisation.

### Options P2 (non comprises dans le total V1)

| Demande | Estimation indicative | Charge indicative |
|---|---:|---:|
| R16 — Masquage/réactivation du stock avancé | **75 000 à 125 000 FCFA** | 1 à 2 jours |
| R17 — Désactivation des rôles de partage | **50 000 à 100 000 FCFA** | 0,5 à 1 jour |
| R18 — Diagnostic/correction de la carte | **100 000 à 200 000 FCFA** | 1 à 3 jours, sous réserve de diagnostic |
| R19 — Déclaration et déduction des pertes | **150 000 à 250 000 FCFA** | 2 à 4 jours |
| R20 — Répartition des charges communes | **150 000 à 250 000 FCFA** | 2 à 4 jours |
| R21 — Comparaison et tendances | **200 000 à 350 000 FCFA** | 3 à 5 jours |

Ces fourchettes ne constituent pas un engagement de réalisation ; elles seront confirmées après définition et validation du besoin. Hébergement, licences, API et services tiers payants sont exclus et requièrent l’accord préalable de BAYKAT.

## 7. Audit technique préalable et dépendances

Je propose un audit technique préalable de **1 à 2 jours ouvrés**, dont le coût est **distinct du total V1 de 1 650 000 FCFA**, à chiffrer et à convenir séparément. Nous pouvons convenir que son coût soit déduit du lot 1 si BAYKAT lance ensuite la suite du projet. Aucun travail au-delà de l’audit ne sera engagé sans validation écrite du périmètre et du budget.

L’audit couvre :

- la cartographie des données et des relations à migrer vers les campagnes ;
- la stratégie de conservation de l’historique et de reprise des anciennes dates ;
- la reproduction du cas des récoltes de tomates ;
- les hooks, gabarits et contraintes du thème Listivo et de `/panel` ;
- les identifiants, statuts, événements et interfaces de « Mes ventes » ;
- la génération PDF, les bibliothèques compatibles et les limites d’hébergement ;
- la préproduction, les sauvegardes, le plan de retour arrière et les jeux de recette ;
- les règles exactes de ventes confirmées, remboursements, annulations, unités et disponibilité.

### Réserve spécifique au lot 3

Le forfait du lot 3 suppose que BAYKAT fournisse l’accès au code ou à une interface documentée de « Mes ventes », que les identifiants annonce/vente et les statuts nécessaires soient accessibles, et que le parcours d’annonce accepte les données de campagne et de quantité. Si une hypothèse n’est pas vérifiée, je propose une solution de remplacement et chiffre tout écart avant de poursuivre.

## 8. Planning indicatif

1. **Audit : 1 à 2 jours ouvrés** — accès, cartographie, décisions fonctionnelles et devis final.
2. **Lot 1 : 5 à 7 jours ouvrés** — intégration, campagnes, listes, archivage et statuts.
3. **Lot 2 : 7 à 9 jours ouvrés** — activités, dates, dépenses, intrants, récoltes et indicateurs.
4. **Lot 3 : 6 à 8 jours ouvrés** — bilan PDF et intégration à « Mes ventes », selon faisabilité confirmée.
5. **Mise en production** — après recette validée, sauvegarde vérifiée et accord de BAYKAT.

Le planning est recalé après l’audit et dépend de la disponibilité des accès, des interlocuteurs, des listes de référence, du jeu de test et des validations.

## 9. Recette et modalités de collaboration

- Le développement et la recette se déroulent en **préproduction**, jamais directement en production.
- BAYKAT fournit une sauvegarde exploitable, les accès requis, les listes de référence et le jeu de 20 opérations avec résultats attendus.
- Chaque lot est livré et soumis à une validation écrite sur les critères définis dans ce document.
- **Proposition de paiement par lot : 40 % au démarrage de chaque lot, puis 60 % après livraison et recette validée de ce même lot.** Le lot suivant démarre selon le calendrier convenu, après accord sur son périmètre.

| Lot | Forfait estimatif | 40 % au démarrage du lot | 60 % après recette validée du lot |
|---|---:|---:|---:|
| Lot 1 — Structure | 450 000 FCFA | 180 000 FCFA | 270 000 FCFA |
| Lot 2 — Pilotage | 650 000 FCFA | 260 000 FCFA | 390 000 FCFA |
| Lot 3 — Intégration BAYKAT | 550 000 FCFA | 220 000 FCFA | 330 000 FCFA |
| **Total V1** | **1 650 000 FCFA** | **660 000 FCFA** | **990 000 FCFA** |

- Cette modalité est une proposition à valider par les deux parties et à inscrire au devis ou au contrat. Le délai de recette de chaque lot, la forme de validation et les modalités de facturation seront précisés dans ces documents. Le solde de 60 % est dû après validation écrite du lot concerné selon ses critères de recette.
- Une garantie corrective de **30 jours après recette de chaque lot** est proposée pour les anomalies reproductibles du périmètre livré. Les évolutions et changements de règles ne relèvent pas de cette garantie.
- Le code source des développements spécifiques sera remis à BAYKAT. La propriété des développements spécifiques sera transférée à BAYKAT après paiement intégral du lot concerné, selon des conditions à formaliser au contrat. Le plugin préexistant, les composants tiers et leurs licences restent distingués et soumis à leurs conditions respectives.
- Les données des producteurs sont confidentielles ; les accès sont limités aux besoins convenus.
- Toute demande supplémentaire, adaptation majeure de Listivo, API ou service payant fait l’objet d’un chiffrage et d’un accord préalable. Aucun dépassement n’est engagé sans accord écrit.

## 10. Éléments attendus de BAYKAT

Pour confirmer les hypothèses et démarrer :

- un environnement de préproduction et une sauvegarde exploitable du code et de la base ;
- l’accès au thème Listivo, à l’espace `/panel` et au module « Mes ventes », ou une documentation technique équivalente ;
- un interlocuteur technique pour chaque module ;
- les listes de cultures, variétés, types d’activité, intrants, catégories de dépenses et unités ;
- un jeu de 20 opérations datées avec les résultats attendus ;
- les statuts de campagne et les règles de clôture ;
- la définition des ventes confirmées, annulations, remboursements, ventes partielles et réservations ;
- la règle à appliquer aux anciennes transactions dont la date réelle n’est pas connue ;
- la validation des critères de recette, du délai de réponse et des modalités de paiement ;
- la confirmation des fonctions P2 à masquer pendant la V1.

## 11. Décisions à obtenir en réunion

1. Valider le principe de campagne et la stratégie de migration des cultures historiques.
2. Confirmer la règle de date pour les anciennes opérations.
3. Définir les statuts de « Mes ventes » retenus comme ventes confirmées.
4. Valider la gestion des annulations, remboursements, ventes partielles et annonces liées.
5. Confirmer les formules de coût de production, rendement, résultat simplifié et marge.
6. Confirmer que les charges communes restent au niveau ferme en V1.
7. Confirmer les listes de référence et qui les fournira.
8. Valider les règles d’archivage et de suppression.
9. Décider du périmètre et du coût de l’audit, ainsi que de sa déduction éventuelle du lot 1.
10. Valider la proposition de paiement à 40 % au démarrage et 60 % après recette de chaque lot.
11. Confirmer l’ordre des lots, les critères de recette, le délai de validation et le calendrier.
12. Confirmer que stock avancé, partage et carte sont traités comme options P2.

## 12. Formulation de clôture proposée

> Merci pour votre note et pour les tests réalisés. Je vous propose de conserver le plugin existant comme base, puis de le faire évoluer autour des campagnes agricoles, de dates d’opération fiables et d’indicateurs vérifiables.  
>
> Mon estimation indicative pour les trois lots V1 est de 1 650 000 FCFA, à confirmer après audit technique, notamment pour Listivo, « Mes ventes » et la migration de l’historique. Je propose de travailler par lots, avec une recette en préproduction et une validation écrite de chaque étape.  
>
> Pour chaque lot, je vous propose un paiement de 40 % au démarrage, puis 60 % après livraison et recette validée de ce lot. Le coût de l’audit reste à convenir séparément ; nous pouvons décider s’il est déduit du lot 1.  
>
> Si cette approche vous convient, nous pouvons d’abord confirmer les accès, les règles métier et le coût de l’audit. Je vous remettrai ensuite le devis final et le planning consolidé avant tout développement au-delà de l’audit.

---

## Validation du cadrage

Cette validation confirme le périmètre fonctionnel et les hypothèses de travail. Elle ne remplace pas le devis ou le contrat final.

| Pour BAYKAT | Pour Lamine |
|---|---|
| Nom : ______________________________ | Nom : Lamine |
| Fonction : _________________________ | Fonction : Prestataire technique |
| Date : _____________________________ | Date : _____________________________ |
| Signature : ________________________ | Signature : ________________________ |
