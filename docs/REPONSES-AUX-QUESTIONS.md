# Réponses aux questions du sujet

Les réponses renvoient au code du dépôt quand c'est utile. Elles suivent la numérotation
du sujet `3IRC ALW 1 - sujet v2026.1-7`.

---

## Partie 1 — Dynamique côté serveur

### Question 1 — Quels éléments de contexte peuvent rendre un site dynamique ?

Un site peut adapter son contenu selon :

- la **requête HTTP** : méthode, URL, paramètres d'URL, en-têtes, corps ;
- l'**utilisateur** : session, cookie, identifiant, préférences, langue ;
- le **temps** : date, heure, saison, expiration d'une offre ;
- l'**état applicatif** : base de données, fichiers, stocks, niveau du joueur ;
- l'**environnement** : adresse IP, appareil, navigateur, pays ;
- des **données externes** : météo, cours d'une monnaie, API tierce ;
- l'**historique** : page précédente, actions récentes, panier.

Dans notre jeu, le contexte principal est la session du joueur et sa sauvegarde JSON.

### Question 2 — Qu'y a-t-il d'intéressant dans `$_SERVER` ?

`$_SERVER` contient les métadonnées de la requête et du serveur :

- `REQUEST_METHOD` : `GET` pour l'affichage du formulaire, `POST` après soumission ;
- `REQUEST_URI`, `QUERY_STRING`, `HTTP_HOST` : l'URL demandée ;
- `HTTP_USER_AGENT`, `REMOTE_ADDR` : le client ;
- `SERVER_PROTOCOL`, `SERVER_SOFTWARE` : la couche serveur ;
- `HTTP_COOKIE` : les cookies, dont l'identifiant de session.

La valeur de `REQUEST_METHOD` change parce que le navigateur envoie le formulaire avec
la méthode déclarée dans `method="POST"`, alors que l'affichage initial est un `GET`.

### Question 3 — Page et URL, est-ce la même chose ?

Non. Une **URL** est un identifiant de ressource : elle désigne *où* et *quoi* demander.
Une **page** est le résultat rendu et affiché. Une même URL peut produire des pages
différentes selon la session, la méthode HTTP ou l'état du jeu. À l'inverse, plusieurs
URLs peuvent afficher la même page (alias, redirections, paramètres). Une URL n'est pas
un chemin de fichier.

### Question 4 — Session et authentification

L'**authentification** vérifie une identité (« es-tu bien bean ? »). La **session**
conserve un état entre plusieurs requêtes HTTP, qui sont sans état par nature.

Elles sont liées mais distinctes : après authentification, on place généralement
l'identité dans la session pour ne pas redemander le mot de passe à chaque requête.
Une session peut exister sans authentification (panier, préférences), et une
authentification peut utiliser un jeton plutôt qu'une session.

### Question 5 — Quel code HTTP pour un tableau de bord non authentifié ?

`401 Unauthorized` est le plus pertinent : l'utilisateur n'a pas fourni d'identité
valide. `403 Forbidden` signifierait « identifié mais pas autorisé ». Une redirection
`302` vers la page de connexion est agréable pour un navigateur, mais elle ne décrit
pas l'erreur : pour une API, `401` est plus explicite. C'est le choix de
`DashboardController::index()`.

### Question 6 — Que factoriser entre les deux pages ?

- la feuille de style (`Public/style.css`) ;
- `session_start()` et le bootstrap (autoloader, configuration) ;
- l'initialisation des dépôts et de Twig ;
- la vérification d'authentification ;
- la structure HTML : `head`, en-tête, navigation, pied de page, messages d'erreur ;
- les règles d'affichage des cartes de produits et de bâtiments.

### Question 7 — `include`, `include_once`, `require` ou `require_once` ?

Pour charger un script indispensable, `require_once` est le plus adapté :

- `require` provoque une erreur fatale si le fichier manque, au lieu d'un simple
  avertissement avec `include` ;
- `_once` évite de redéclarer des fonctions ou des classes déjà chargées.

`include_once` conviendrait pour un élément optionnel. Dans le framework, le chargement
des contrôleurs et des vues est essentiel : une erreur doit arrêter l'application.

### Question 8 — Avantages d'un point d'entrée unique

- un seul endroit pour démarrer la session, charger la configuration et les classes ;
- un routage centralisé et des URLs propres ;
- des contrôles communs (authentification, CORS, journalisation) ;
- une gestion d'erreurs homogène ;
- moins de duplication entre les pages ;
- facilité de test et de maintenance.

Contreparties : un peu plus d'indirection, un point de défaillance unique et toutes les
requêtes passent par PHP.

### Question 9 — Qu'est-ce qu'une route ?

Une route associe :

- une **méthode HTTP** (`GET`, `POST`, etc.) ;
- un **motif d'URL** (`/dashboard`, `/api/players/{string:player}/inventory`) ;
- une **action** : contrôleur et méthode ;
- éventuellement des **paramètres** et des contraintes.

Ce n'est donc pas juste une URL : la même URL en `GET` ou en `POST` peut déclencher deux
actions différentes.

### Question 10 — Sans SEP, qui joue le rôle de routeur ?

Le serveur web (Apache) et les scripts eux-mêmes. Chaque URL pointe vers un fichier
(`login.php`, `dashboard.php`), et chaque fichier doit refaire son bootstrap : session,
includes, vérification d'accès, etc. Le « routage » est alors une correspondance
URL → fichier, avec `.htaccess` pour les URLs propres.

### Question 11 — Pourquoi la soumission du formulaire échoue-t-elle avec deux routes ?

Parce que le formulaire envoie un `POST` et que seules des routes `GET` existent. Le
routeur ne trouve aucune route pour `POST /login` et renvoie la page 404. Il faut
ajouter une route `POST /login` vers la même action (ou une action dédiée), ce qui est
fait dans `Application::run()`.

### Question 12 — Où placer `session_start()` ?

Dans le point d'entrée unique, avant le routage : `Application::run()` (ou `index.php`).
Ainsi, toutes les pages partagent la même session, sans répéter l'appel et sans risque
de démarrer la session après un envoi de contenu.

### Question 13 — Pourquoi séparer métier et affichage ?

Mélanger les deux :

- couple fortement la logique aux détails HTML ;
- rend les tests difficiles (il faut simuler une page) ;
- favorise la duplication et les incohérences ;
- augmente le risque de fautes d'échappement (XSS) ;
- empêche de réutiliser la logique pour une API ou un autre front ;
- rend la collaboration développeur / intégrateur plus compliquée.

### Question 14 — Peut-on encore écrire du code métier dans les vues ?

Techniquement oui : un template PHP reste du PHP exécuté. Twig limite la casse avec une
syntaxe restreinte, mais on peut toujours appeler des méthodes ou des fonctions
exposées. La séparation repose donc aussi sur la discipline et les conventions. Un
sandbox Twig peut restreindre davantage les possibilités.

### Question 15 — Pourquoi `.html.twig` plutôt que `.tpl` ?

La double extension indique à la fois le **format de sortie** (HTML) et le **moteur**
(Twig). Les éditeurs et outils reconnaissent mieux le fichier, le linting et la
coloration sont plus précis, et le nom reste explicite même si Twig accepte n'importe
quelle extension.

### Question 16 — Quelles parties HTML factoriser dans les vues ?

- le doctype, `head`, `meta`, le titre et les feuilles de style ;
- l'en-tête, la navigation et le bouton de déconnexion ;
- le pied de page ;
- les blocs d'erreur et de message ;
- les cartes répétitives (produits, bâtiments) via des macros ou des includes ;
- la structure de page commune via l'héritage Twig.

### Question 17 — Inconvénients d'`include` pour la structure

- il faut répéter les includes dans chaque page ;
- un oubli casse silencieusement la structure ;
- pas de contrat clair sur les blocs à remplir ;
- les variables et le contexte peuvent fuir entre fichiers ;
- difficile de remplacer un bloc ou d'ajouter du contenu à un endroit précis ;
- l'ordre des includes devient important.

L'héritage (`extends` + `block`) répond à ces problèmes.

### Question 18 — Ce qui change entre SSR et CSR

En **SSR**, le serveur construit le HTML complet ; le navigateur affiche un document
prêt. En **CSR**, le serveur envoie une coquille et des données (souvent JSON), puis le
JavaScript construit le DOM, gère la navigation et les interactions.

La responsabilité du rendu et de l'état d'interface passe donc du serveur au client.
Cela découple les clients (web, mobile) et allège les réponses HTML, mais augmente la
quantité de JavaScript, les requêtes réseau et la complexité côté navigateur.

---

## Partie 2 — Dynamique côté client

### Question 19 — Pourquoi utiliser REST ?

REST fournit un cadre d'architecture :

- **séparation client/serveur** : le front peut évoluer indépendamment ;
- **sans état** : chaque requête contient tout ce qu'il faut pour être comprise ;
- **interface uniforme** : mêmes méthodes, mêmes conventions, URLs prévisibles ;
- **cache** et **scalabilité** facilités ;
- **interopérabilité** : plusieurs clients peuvent consommer la même API.

On n'« architecture » pas seulement des URLs : on définit des ressources, leurs
représentations (JSON), les méthodes autorisées et les transitions entre états.

### Question 20 — Concilier stateless, authentification et données

Le **sans état** concerne l'état de conversation : le serveur ne garde pas en mémoire
« où en est le client ». Il peut néanmoins conserver des **ressources** (sauvegardes,
base de données, fichiers).

Pour l'authentification, le client renvoie à chaque requête une preuve d'identité :
cookie de session, jeton JWT, clé d'API. Le serveur valide cette preuve et reconstruit
le contexte. La session PHP est un stockage serveur identifié par un cookie ; un jeton
signé est une autre solution, plus adaptée à une API multi-clients.

### Question 21 — Pourquoi cinq méthodes HTTP ?

`GET`, `POST`, `PUT`, `PATCH` et `DELETE` couvrent les opérations courantes sur une
ressource : lire, créer, remplacer, modifier partiellement, supprimer.

Les autres méthodes ont d'autres rôles :

- `HEAD` : mêmes en-têtes que `GET`, sans corps ;
- `OPTIONS` : capacités et préflight CORS ;
- `TRACE` : diagnostic ;
- `CONNECT` : établissement de tunnel (proxy).

Ce ne sont pas des opérations métier sur une ressource. La liste de cinq est donc une
convention, pas une limite du protocole.

### Question 22 — Quelles classes pour les entités avancées ?

Une modélisation possible :

- `Product` : identifiant, nom, icône, unité, stock ;
- `Generator` : identifiant, nom, produit, niveau, rendement, coût ;
- `Requirement` : condition de déblocage (niveau, produit, quantité) ;
- `ProductRepository`, `GeneratorRepository`, `RequirementRepository` : accès aux JSON ;
- `FarmService` : récolte, amélioration, déblocage ;
- `Inventory` : collection de stocks avec ajout/retrait ;
- `Cost` : calcul et vérification d'un coût ;
- `ApiException` : erreurs métier avec statut HTTP.

Le projet simplifié utilise des tableaux JSON et `GameConfigRepository`, mais la
séparation service / dépôt / modèle reste la même.

### Question 23 — Pourquoi décoder puis ré-encoder le JSON ?

Parce que le fichier de stockage et la réponse HTTP n'ont pas le même contrat :

- on valide et filtre les champs exposés ;
- on ne transmet qu'une partie de la ressource (inventaire, pas toute la sauvegarde) ;
- on calcule des valeurs (rendement, coût) ;
- on normalise les types (entiers, booléens) ;
- on évite de coupler le format interne au format public ;
- on empêche la fuite de données internes.

Un passage direct est possible pour un endpoint strictement read-only dont le fichier
est déjà la représentation exacte, mais c'est fragile et peu évolutif.

### Question 24 — Pourquoi deux `.then()` avec `fetch()` ?

Parce que `fetch()` résout une promesse dès que les **en-têtes** de la réponse sont
disponibles, pas quand le corps est lu. Le premier `.then()` reçoit un objet `Response`.
`response.json()` lit le corps de façon asynchrone et renvoie une nouvelle promesse ;
le second `.then()` reçoit les données réellement parsées.

### Question 25 — Quels en-têtes l'API transmet-elle ?

Selon la réponse, on observe notamment :

- `Content-Type: application/json; charset=utf-8` ;
- `Access-Control-Allow-Origin` et `Vary: Origin` ;
- `Content-Length` ;
- `Date`, `Server`, `X-Powered-By` ;
- `Connection` ;
- pour le préflight : `Access-Control-Allow-Methods`,
  `Access-Control-Allow-Headers`, `Access-Control-Max-Age`.

### Question 26 — Avantages et inconvénients du CRUD mécanique

Avantages : URLs et méthodes prévisibles, API uniforme, outils génériques, courbe
d'apprentissage faible.

Inconvénients : toutes les opérations métier ne sont pas des CRUD ; le client enchaîne
les appels, calcule des valeurs et peut laisser l'état incohérent ; les règles métier
fuient côté client ; les opérations ne sont pas atomiques ; l'API devient bavarde et
sujette aux conditions de course.

### Question 27 — « Améliorer » avec seulement le CRUD

Le client devrait :

1. `GET` le niveau du générateur ;
2. `GET` l'inventaire ;
3. calculer le coût ;
4. `PUT` le stock de chaque produit ;
5. `PUT` le niveau du générateur.

Risques :

- **condition de course** : deux clients lisent le même stock et le dépensent deux fois ;
- **triche** : le client peut envoyer un niveau ou un stock arbitraire ;
- **mise à jour partielle** : si le dernier `PUT` échoue, les produits sont débités mais
  le niveau n'a pas augmenté ;
- **incohérence** : aucune transaction ne couvre l'ensemble.

C'est pourquoi le projet expose `POST .../upgrade`, qui vérifie et modifie tout dans une
seule opération verrouillée.

---

## Partie 3 — Approfondissement

### Question 28 — Comment résoudre une erreur CORS ?

- configurer correctement les en-têtes CORS côté serveur (origine, méthodes, en-têtes) ;
- passer par un **proxy same-origin** (reverse proxy) qui sert le front et l'API sous le
  même domaine ;
- faire l'appel côté serveur (PHP, Node) plutôt que dans le navigateur ;
- utiliser JSONP pour de vieux navigateurs et des `GET` uniquement ;
- désactiver la sécurité du navigateur : à ne jamais faire, ce n'est pas une solution ;
- pour du temps réel, WebSocket n'est pas soumis à la même politique d'origine, mais
  des contrôles d'origine restent nécessaires.

### Question 29 — Pourquoi `Access-Control-Allow-Origin: *` est-il risqué ?

Parce que n'importe quel site peut lire les réponses de l'API depuis le navigateur d'un
utilisateur. Si l'API expose des données sensibles ou accepte des cookies, cela ouvre la
porte à l'exfiltration et à des actions non désirées. Une liste blanche d'origines
précises, avec `Vary: Origin`, limite l'accès aux fronts légitimes.

### Question 30 — Pourquoi le message d'erreur disparaît-il après redirection ?

Le serveur répond au `POST` par une redirection. Le navigateur abandonne le corps de la
réponse précédente et effectue un nouveau `GET` : le message, qui n'existait que dans la
réponse POST, est perdu.

Solutions :

- **flash en session** : le message est stocké côté serveur et lu au prochain `GET`
  (solution du projet) ;
- paramètre d'URL (`?error=...`), à éviter pour des messages sensibles ou longs ;
- réafficher directement la page en réponse au `POST`, mais on perd l'intérêt du PRG et
  le rechargement rejoue le formulaire ;
- cookie temporaire, équivalent au flash mais moins pratique.

