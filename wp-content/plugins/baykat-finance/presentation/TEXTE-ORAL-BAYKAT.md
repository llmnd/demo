# Texte oral — présentation BAYKAT

**Guide de répétition pour Lamine — 19 diapositives**

Lisez les passages entre guillemets comme votre texte de présentation. Les notes **[Transition]** sont des repères pour passer à la diapositive suivante ; elles ne sont pas à lire.

Durée indicative : **8 à 11 minutes**, sans compter les réponses aux questions et la discussion sur le budget.

---

## Diapositive 1 — Une récolte, deux entrées, un total

> Bonjour Mbaye, merci de prendre le temps d’échanger avec moi. Je vais vous présenter comment je propose de faire évoluer le module « Gérer ma ferme », en partant de vos retours et en gardant le plugin existant comme base. Je voudrais surtout que nous arrivions à un périmètre clair, avec des résultats que vous pourrez vérifier.

**[Transition]** Je commence par l’anomalie de récolte qui illustre l’importance de la fiabilité des données.

## Diapositive 2 — Avant / après : les récoltes

> Dans votre test, deux récoltes de 10 kilos étaient enregistrées pour les tomates, alors que le total affiché était de 10 kilos. Le résultat attendu est bien 20 kilos. J’ai préparé un correctif dans le code local pour calculer le cumul à partir du journal ; il n’est pas encore déployé sur votre démonstration. Après validation, nous pourrons refaire ce test ensemble : avec une vente de 1 kilo, il devra rester 19 kilos disponibles. J’ai également harmonisé des unités comme « Kk » en « kg » et clarifié certains affichages de quantités.

**[Transition]** Au-delà de cette correction, voici le parcours complet que je vous propose.

## Diapositive 3 — De la parcelle au bilan

> L’idée est de suivre toute la production dans un seul parcours. On part de la ferme et de la parcelle, on crée une campagne, on enregistre les travaux, les dépenses et les récoltes, puis on consulte les résultats. Si le produit est vendu sur BAYKAT, l’objectif est de reprendre la vente confirmée sans la saisir une deuxième fois.

**[Transition]** Pour y arriver, je ne repars pas de zéro : je m’appuie sur les éléments déjà présents.

## Diapositive 4 — Consolider le socle existant

> Le plugin possède déjà les fermes, les parcelles, les cultures, les activités, les transactions, le journal de récoltes et un export CSV. Je propose de conserver ces briques et de les faire évoluer. Avant de confirmer le devis, je dois vérifier avec vous l’intégration à Listivo, l’accès à « Mes ventes » et les conditions techniques pour produire le PDF.

**[Transition]** La principale évolution du modèle est de suivre les cultures par campagne.

## Diapositive 5 — La campagne agricole

> Une campagne correspond à une culture sur une parcelle et une période données. Par exemple, une parcelle peut avoir une campagne d’oignon en 2025, puis une campagne de tomate en 2026. Chaque campagne garde ses propres travaux, ses coûts, ses récoltes et ses résultats. Je confirmerai la meilleure manière de migrer vos données après avoir étudié leur structure, afin de ne pas perdre l’historique.

**[Transition]** Ensuite, chaque opération doit être enregistrée avec les bonnes informations.

## Diapositive 6 — Dates, rattachements et coûts

> Pour une dépense ou un intrant, on doit pouvoir enregistrer la date réelle de l’opération, même si la saisie est faite plus tard. La date de saisie sera conservée séparément. Chaque coût sera rattaché à la bonne campagne, ou à la ferme s’il s’agit d’une charge commune. Et un même coût ne sera compté qu’une fois, même si un intrant est lié à une activité.

**[Transition]** Avec des opérations fiables, nous pourrons calculer des indicateurs utiles.

## Diapositive 7 — Des chiffres pour décider

> L’objectif est d’afficher les coûts suivis, les récoltes, le rendement, le coût de production par kilo, les recettes et un résultat simplifié. Les chiffres de cette maquette sont des exemples pour montrer le calcul ; ce ne sont pas vos résultats réels. Les mêmes règles devront être utilisées à l’écran et dans les exports. Et lorsqu’un calcul n’est pas fiable, par exemple si l’unité ou la surface manque, nous afficherons « Non disponible » au lieu d’inventer un chiffre.

**[Transition]** Pour les ventes BAYKAT, nous voulons relier la production à « Mes ventes », sans créer de doublon.

## Diapositive 8 — Relier la production et « Mes ventes »

> « Mes ventes » reste l’endroit où l’annonce et la vente sont gérées. Le module agricole suit la campagne et reprend la vente seulement lorsqu’elle est confirmée. Une récolte ne devient pas automatiquement une annonce : le producteur choisit ce qu’il met en vente. Il faudra vérifier ensemble les statuts, annulations et remboursements que le module « Mes ventes » permet de reconnaître.

**[Transition]** Je propose de réaliser le projet en trois lots successifs.

## Diapositive 9 — Trois lots, trois étapes

> Le premier lot porte sur la structure, les campagnes et l’intégration dans l’espace client : il est estimé à 450 000 FCFA. Le deuxième porte sur les opérations, les coûts et les indicateurs : 650 000 FCFA. Le troisième porte sur le bilan PDF et l’intégration aux ventes BAYKAT : 550 000 FCFA, sous réserve de faisabilité technique. Le total estimatif est de 1 650 000 FCFA, sur 18 à 24 jours ouvrés. Ce chiffrage reste à confirmer après l’audit.

**[Transition]** Chaque lot aura des critères concrets de vérification.

## Diapositive 10 — La recette

> Je propose que nous testions chaque lot en préproduction, pas directement sur le site en production. Pour le premier lot, nous vérifierons notamment deux comptes distincts et l’historique des campagnes. Pour le deuxième, nous utiliserons votre jeu de 20 opérations, dont le test des deux récoltes de 10 kilos. Pour le troisième, nous vérifierons qu’une vente confirmée apparaît une seule fois et que le PDF reprend les mêmes chiffres que l’écran.

**[Transition]** Nous pouvons aussi garder une première version simple en reportant certaines fonctions.

## Diapositive 11 — Périmètre et options

> Dans la V1, je propose de concentrer le travail sur les campagnes, les opérations datées, les coûts, les récoltes, les indicateurs, les exports et l’intégration prévue à BAYKAT. Le stock avancé, le partage de ferme, la carte, les pertes, la répartition des charges communes et les comparaisons avancées sont présentés séparément comme options P2. Ainsi, nous ne les supprimons pas : nous décidons ensemble quand les traiter et à quel prix.

**[Transition]** Pour protéger le projet, il faut aussi se mettre d’accord sur la façon de travailler et de payer.

## Diapositive 12 — Nos engagements

> Je propose de travailler lot par lot, avec une recette et une validation pour chaque étape. Pour chaque lot, je vous propose 40 % au démarrage puis 60 % après livraison et recette validée de ce même lot. Par exemple, pour le lot 1, cela représente 180 000 FCFA au démarrage et 270 000 FCFA après validation. Cette proposition sera inscrite au devis ou au contrat si nous sommes d’accord. Je propose également 30 jours de correction pour les anomalies du périmètre livré.

**[Transition]** Je voudrais maintenant expliquer ce que couvre l’estimation globale.

## Diapositive 13 — Ce que couvre le prix

> Les 1 650 000 FCFA correspondent aux trois lots et au travail nécessaire pour protéger l’historique, structurer les campagnes, fiabiliser les calculs, préparer les intégrations et tester les livrables. Ce n’est pas seulement la création de quelques écrans. Chaque lot a son propre périmètre et ses critères de recette. Je confirmerai le devis après l’audit ; je m’engage sur des livrables vérifiables, pas sur un rendement commercial garanti.

**[Transition]** Si ce montant dépasse votre enveloppe, nous pouvons regarder ensemble plusieurs possibilités.

## Diapositive 14 — Si le budget dépasse votre enveloppe

> Si le budget complet est trop élevé, nous pouvons commencer par le lot 1, estimé à 450 000 FCFA, puis décider de la suite après sa recette. Ce lot seul ne comprend pas la V1 complète. Nous pouvons aussi revoir les priorités, reporter certaines fonctions et établir un devis différent. Dans ce cas, j’aimerais savoir quelle enveloppe vous souhaitez respecter et quelles fonctions doivent absolument être présentes dans la première version.

**[Transition]** J’ai également préparé des réponses aux questions qui peuvent se poser sur le prix, le paiement et les résultats.

## Diapositive 15 — Vos questions, mes réponses

> Si vous me dites que le prix est trop élevé, je vous réponds que je comprends et que nous pouvons revoir ensemble les priorités. Si vous me demandez de réaliser toute la V1 pour un million, je peux étudier un périmètre réduit, mais je ne veux pas vous présenter ce périmètre comme la V1 complète. Si vous préférez payer après livraison, nous pouvons discuter d’un paiement après recette de chaque lot, avec des critères et un délai écrits. Enfin, je peux m’engager sur les livrables et corriger les anomalies prévues, mais pas garantir vos ventes ou votre rendement.

**[Transition]** Mon objectif est de rester ouvert à vos priorités tout en gardant un cadre clair.

## Diapositive 16 — Un cadre clair pour avancer

> Nous pouvons adapter l’ordre des lots, le calendrier, les fonctions reportées et les modalités de paiement. En revanche, je souhaite que chaque prix corresponde à un périmètre écrit, que les travaux supplémentaires soient approuvés avant d’être engagés, et que nous testions les changements en préproduction avec une sauvegarde. Cela nous protège tous les deux et évite les malentendus.

**[Transition]** J’ai bien repris les questions de votre note. Je vous propose de les parcourir une à une.

## Diapositive 17 — Vos questions sur le modèle et le pilotage

> Voici les quatre premières questions de votre note. Je vais y répondre dans l’ordre.

**[Repère]** Les questions apparaissent seules à l’écran. Je garde les réponses détaillées dans la section « Réponses aux questions techniques » plus bas dans ce guide, que je peux consulter sur mon téléphone.

**[Transition]** Je passe maintenant aux trois dernières questions.

## Diapositive 18 — Vos questions sur le PDF et l’intégration

> Voici les trois dernières questions. Je réponds aussi à celles-ci, puis nous pourrons valider ensemble les prochaines étapes.

**[Repère]** Là encore, l’écran n’affiche que les questions. Je consulte mes réponses sur mon téléphone.

**[Transition]** Une fois vos questions traitées, je vous propose de décider comment avancer.

## Diapositive 19 — La première étape

> Pour avancer, je vous propose de confirmer les accès à la préproduction et aux modules, les règles de vente et les données de test. Nous fixerons ensuite les critères de recette, le coût de l’audit et les modalités de paiement. Après l’audit, je vous remettrai le devis et le planning définitifs. Est-ce que cette première étape vous convient ?

---

## Repères rapides si la discussion s’interrompt

- **Si on demande une baisse de prix :** « Je peux revoir le périmètre ou le phasage ; je préfère ne pas vous promettre la même V1 complète à un prix réduit sans recalcul. »
- **Si on demande un prix ferme tout de suite :** « Je peux confirmer le forfait après vérification de Listivo, de “Mes ventes”, de la migration et de l’hébergement PDF. »
- **Si on demande de commencer immédiatement en production :** « Je préfère d’abord une préproduction et une sauvegarde vérifiée, puis une mise en production après recette. »
- **Si on questionne le bug des récoltes :** « Le correctif est préparé localement ; le site de démonstration n’a pas encore été mis à jour. Je vous propose de refaire le test ensemble après déploiement autorisé. »
- **Si une demande nouvelle apparaît :** « Je la note, j’en évalue l’impact, puis je vous soumets le coût et le délai avant de commencer. »

## Réponses aux questions techniques — à utiliser pendant l’échange

Ces réponses sont des repères à lire si la question est posée. Elles ne sont pas affichées dans le diaporama. Les charges et les montants restent des estimations à confirmer après l’audit.

### 1. Le modèle actuel peut-il devenir des campagnes sans refonte ?

> Je peux réutiliser les fermes, les parcelles, les cultures et les journaux déjà présents. En revanche, je ne peux pas garantir qu’aucune adaptation de la base ne sera nécessaire avant d’avoir cartographié l’historique. Mon objectif est de faire une migration contrôlée, sans repartir de zéro. À ce stade, j’estime la création des campagnes à un jour et demi à deux jours et demi, puis l’adaptation des activités à trois quarts de journée à une journée.

### 2. Quel effort pour ajouter la date réelle des opérations ?

> J’estime à un jour ou un jour et demi l’ajout d’une date d’opération distincte de la date de saisie, migration comprise. Pour les anciennes lignes dont la date réelle est inconnue, je ne veux pas inventer de date : nous déciderons ensemble de la règle à appliquer.

### 3. Peut-on relier sûrement annonce, vente et campagne ?

> Je dois d’abord vérifier les identifiants et les statuts disponibles dans « Mes ventes ». S’ils permettent un rapprochement fiable, une annonce sera liée à une seule campagne et seule une vente confirmée sera reprise, sans doublon. Nous définirons ensemble le traitement des annulations et des remboursements. L’estimation actuelle est de deux à trois jours pour les ventes, et d’un à deux jours pour la fonction « Mettre en vente ».

### 4. La marge du CSV peut-elle être affichée à l’écran ?

> Oui, c’est l’objectif. Je propose de reprendre la formule déjà utilisée dans le CSV, de la valider avec vous et d’appliquer la même règle à l’écran et dans les exports. Si les données ne permettent pas un calcul fiable, le résultat indiquera « Non disponible ». L’ensemble des indicateurs est estimé à deux jours et demi à trois jours.

### 5. Quelle charge pour le bilan PDF ?

> J’estime le bilan PDF à deux ou trois jours. Il reprendra les informations de la ferme et de la campagne, les opérations et les indicateurs, avec des chiffres cohérents avec l’écran. Je dois vérifier la bibliothèque PDF et les limites de l’hébergement avant de confirmer la solution.

### 6. Peut-on masquer le stock, le partage et la carte ?

> Pour le stock et le partage, je vérifierai les menus, les droits et les dépendances avant de les masquer, sans supprimer le code ni les données. J’estime le stock à un ou deux jours, et le partage à une demi-journée ou une journée. Pour la carte, je ne veux pas promettre une simple désactivation ou une correction avant d’avoir reproduit et diagnostiqué la panne ; ce diagnostic est estimé à un à trois jours. Ces fonctions restent des options P2.

### 7. Quelles adaptations pour `/panel` et la charte ?

> Je propose d’intégrer le module dans l’espace client existant, entre « Mes annonces » et « Favoris », en reprenant la charte et le compte BAYKAT. J’estime ce travail à un jour ou un jour et demi, sous réserve de vérifier Listivo en préproduction. Je testerai également le parcours sur téléphone, à 360 pixels.

> Après ces réponses, je vous propose que nous validions ensemble les règles et les priorités de la V1, les accès et les interlocuteurs techniques, puis le périmètre et le coût de l’audit. Nous pourrons aussi confirmer les critères de recette et discuter des modalités de paiement.
