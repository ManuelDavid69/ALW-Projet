# Architecture et parcours du code

Ce document décrit comment le projet est organisé, comment une requête traverse chaque
backend et quelles décisions techniques ont été prises. Il complète le
[`README`](../README.md) et les [réponses aux questions](REPONSES-AUX-QUESTIONS.md).

---

## 1. Vue d'ensemble

Le projet contient **deux applications PHP** et un service optionnel :

| Application | Port | Rôle | Point d'entrée |
|---|---|---|---|
| Site | 50180 | Pages HTML, session, tableau de bord | `src/site/index.php` |
| API | 50181 | Endpoints JSON, règles du jeu | `src/webservice/index.php` |
| WebSocket | 50200 | Diffusion temps réel (option) | `ws/server.php` |

Les deux applications PHP partagent le même framework et les mêmes classes métier,
mais **pas** le même code de présentation. Le site utilise Twig ; l'API ne produit que
du JSON.

Le dossier `src/Data/` est commun aux deux conteneurs. Docker le monte dans chacun
d'eux :

```yaml
volumes:
  - ./src/site:/var/www/html
  - ./src/Data:/var/www/html/Data
```

En local, `index.php` détecte le dossier `Data` à côté de lui ou à la racine de `src/`
et définit la constante `DATA_PATH`.

---

## 2. Parcours d'une requête côté site

### 2.1 Entrée unique

Apache réécrit toutes les URLs qui ne correspondent pas à un fichier existant vers
`index.php` (`.htaccess`). `index.php` :

1. active l'affichage des erreurs et le fuseau `Europe/Paris` ;
2. définit `DATA_PATH` ;
3. charge l'autoloader du framework ;
4. instancie `App\Application` et appelle `run()`.

`Application::run()` ouvre la session une seule fois, crée le routeur, déclare les
routes puis exécute le contrôleur correspondant.

### 2.2 Routage

```php
$router->map('GET',  '/login',     AuthController::class, 'showLogin');
$router->map('POST', '/login',     AuthController::class, 'authenticate');
$router->map('GET',  '/dashboard', DashboardController::class, 'index');
$router->map('POST', '/logout',    AuthController::class, 'logout');
```

Une route associe donc **une méthode HTTP**, **un motif d'URL** et **une action de
contrôleur**. Le routeur du cours gère les paramètres `{int:...}` et `{string:...}` ;
les paramètres `string` s'arrêtent au prochain `/` (correctif apporté au framework).

### 2.3 Connexion et session

`AuthController::authenticate()` :

1. lit `$_POST['username']` et `$_POST['password']` ;
2. récupère l'utilisateur via `UserRepository` ;
3. compare le mot de passe avec `password_verify()` ;
4. en cas d'erreur, stocke un message flash et redirige en **303** vers `/login` ;
5. en cas de succès, régénère l'identifiant de session, enregistre le joueur dans
   `$_SESSION['player']`, crée sa sauvegarde si besoin, puis redirige en **303** vers
   `/dashboard`.

`showLogin()` consomme le flash et l'efface : le message ne survit qu'à une seule
redirection. C'est le patron **Post/Redirect/Get**.

`DashboardController::index()` vérifie la présence de `$_SESSION['player']['login']`.
Sans session, il répond `401` et affiche `401.html.twig`.

### 2.4 Rendu Twig

`Application::twig()` initialise Twig une seule fois :

- loader `FilesystemLoader(__DIR__ . '/Templates')` ;
- cache désactivé en développement ;
- variable globale `player` issue de la session.

Les vues héritent toutes de `layout.html.twig`, qui contient l'en-tête, la navigation,
le bouton de déconnexion et les blocs `title`, `bodyClass`, `head` et `body`.

Le contrôleur transforme les données métier en « vues de bâtiment » prêtes à afficher :
niveau, rendement avec l'unité correcte, coût détaillé, indicateur de disponibilité.
Twig ne calcule donc pas les règles du jeu.

---

## 3. Parcours d'une requête côté API

### 3.1 Entrée et CORS

`src/webservice/index.php` définit `DATA_PATH`, charge l'autoloader, puis
`App\Application::run()` commence par `Cors::apply()`.

`Cors` :

- vérifie l'origine dans une liste blanche (`localhost:50180`, `127.0.0.1:50180`) ;
- envoie `Access-Control-Allow-Origin` sur les réponses autorisées ;
- pour `OPTIONS`, choisit les méthodes autorisées selon l'URL, envoie les en-têtes de
  préflight puis répond `204` ;
- renvoie `405` si la méthode demandée n'est pas autorisée et `404` si l'URL est
  inconnue.

### 3.2 Contrôleur

`ApiController` contient uniquement la traduction HTTP :

- lecture des paramètres d'URL (`player`, `product`, `building`, `generator`) ;
- lecture du corps JSON ou de formulaire ;
- appel de `FarmService` ;
- sérialisation JSON et choix du code de statut.

Toute erreur métier est une `ApiException` avec un statut HTTP. `respond()` la traduit
en `{"error": "..."}` ; les erreurs inattendues sont journalisées et renvoient `500`
sans dévoiler la pile.

### 3.3 Service métier

`FarmService` ne connaît ni HTTP ni Twig. Il reçoit :

- `GameConfigRepository` pour les règles ;
- `SaveRepository` pour les sauvegardes ;
- `WeatherService` pour le modificateur de production ;
- `EventRepository` pour publier les événements.

Ses opérations principales :

| Méthode | Vérifications | Écritures |
|---|---|---|
| `harvest` | bâtiment connu, joueur connu | ajoute le rendement, publie un événement |
| `upgrade` | bâtiment connu, coût disponible | débite les produits, incrémente le niveau, publie un événement |
| `readProduct` / `updateProductStock` | produit connu, stock entier positif | met à jour l'inventaire |
| `discardProduct` | produit connu | remet le stock à zéro |
| `readGenerator` / `updateGeneratorLevel` | bâtiment connu, niveau ≥ 1 | met à jour le niveau |

La quantité récoltée vaut :

```text
max(1, arrondi((baseYield + (niveau - 1) × yieldPerLevel) × modificateurMétéo))
```

Le coût d'amélioration vaut, pour chaque produit :

```text
arrondiSupérieur(coûtDeBase × costGrowth^(niveau - 1))
```

Le calcul est **dupliqué volontairement** dans `FermeEngine.upgradeCost()` pour
l'affichage, mais le serveur reste la seule autorité : il recalcule le coût et vérifie
les stocks avant de débiter.

### 3.4 Persistance et concurrence

`SaveRepository` écrit un fichier par joueur. Pour les mises à jour, il utilise
`mutate()` :

```text
ouverture c+
  → flock(LOCK_EX)
  → lecture du JSON
  → fonction de modification
  → troncature + réécriture
  → flock(LOCK_UN)
  → fermeture
```

Ce verrou couvre la lecture et l'écriture. Sans lui, deux requêtes simultanées
pourraient lire le même stock puis le réécrire chacune avec une valeur différente,
ce qui perdrait une récolte ou permettrait une double amélioration.

Le nom du joueur est validé par `isValidLogin()` (`[a-zA-Z0-9_-]{1,32}`) avant toute
construction de chemin : cela empêche `../../` et les accès hors de `Data/Saves`.

---

## 4. Modèle de données

### 4.1 Configuration

```json
{
  "products": {
    "ble": { "name": "Blé", "icon": "🌾", "unit": "gerbes", "unitSingular": "gerbe" }
  },
  "buildings": {
    "champ_ble": {
      "name": "Champ de blé",
      "produces": "ble",
      "baseYield": 3,
      "yieldPerLevel": 1,
      "upgradeCost": { "ble": 10 },
      "costGrowth": 1.6
    }
  }
}
```

### 4.2 Sauvegarde

```json
{
  "login": "bean",
  "inventory": { "ble": 47, "lait": 21, "oeuf": 12, "laine": 5 },
  "buildings": {
    "champ_ble": { "level": 3 },
    "vache": { "level": 2 },
    "poulailler": { "level": 2 },
    "bergerie": { "level": 1 }
  },
  "createdAt": "2026-11-03T00:50:00+00:00",
  "updatedAt": "2026-11-03T00:57:21+00:00",
  "lastHarvest": {
    "building": "champ_ble",
    "product": "ble",
    "quantity": 5,
    "at": "2026-11-03T00:57:21+00:00"
  }
}
```

### 4.3 Journal d'événements

```json
{
  "nextId": 5,
  "events": [
    {
      "type": "harvest",
      "player": "bean",
      "building": "champ_ble",
      "product": "ble",
      "quantity": 5,
      "message": "bean a récolté 5 gerbes au bâtiment Champ de blé.",
      "id": 1,
      "timestamp": "2026-11-03T00:58:02+00:00"
    }
  ]
}
```

Le fichier est tronqué aux 100 derniers événements. `nextId` continue d'augmenter,
même après la suppression des plus anciens.

---

## 5. Le front JavaScript

### 5.1 Cycle de vie

`FermeEngine` :

1. charge `Public/js/game_config.json` ;
2. installe un écouteur d'événements délégué sur `#buildings` ;
3. appelle `onLoadState()` fourni par `main.js`.

`main.js` implémente les trois callbacks attendus :

```js
engine.onLoadState = async () => { /* GET inventory + buildings, puis rendu */ };
engine.onHarvest   = (id) => { /* POST .../harvest, retourne la promesse */ };
engine.onUpgrade   = (id) => { /* POST .../upgrade, retourne la promesse */ };
```

Après une action, le moteur attend la promesse puis appelle `engine.refresh()`, qui
relance `onLoadState()`. L'interface n'est jamais modifiée de façon optimiste : elle
reflète l'état renvoyé par le serveur.

### 5.2 Rendu

- `renderInventory()` met à jour les `<output class="stock">` et crée les cartes
  manquantes si le HTML serveur n'en contient pas.
- `renderBuildings()` met à jour le niveau, le rendement, le coût, l'état
  « abordable » et l'état `disabled` des boutons.
- `appendLiveEvent()` ajoute un événement en tête du fil et limite la liste à 20
  entrées.
- `setLiveStatus()` affiche l'état du flux (direct, reconnexion, désactivé).

### 5.3 Météo

`loadWeather()` est appelé après le chargement de l'état, puis toutes les dix minutes.
Il interroge `/api/weather` et met à jour l'icône, la température, le vent, le
modificateur et le conseil. Un échec réseau laisse la production nominale.

### 5.4 Direct

Par défaut, `connectLiveFeed()` ouvre un `EventSource` vers
`/api/events/stream?after=0`. Chaque message `farm` est ajouté au fil et déclenche
`engine.refresh()`.

Avec `?live=0`, le flux est désactivé : les événements déjà rendus par le serveur
restent visibles.

Avec `?live=ws` (ou `window.FERME_WS_URL`), le client ouvre un WebSocket vers
`ws://<hôte>:50200`.

---

## 6. Météo côté serveur

`WeatherService` :

- interroge Open-Meteo pour Lyon (latitude 45,764, longitude 4,8357) ;
- lit `temperature_2m`, `weather_code` et `wind_speed_10m` ;
- traduit le code WMO en condition, icône, conseil et modificateur ;
- met la réponse en cache dix minutes dans `Data/State/weather.json` ;
- en cas d'échec, réutilise le cache ou retourne une météo neutre (`×1,00`).

Le modificateur est appliqué dans `FarmService::harvest()`, pas dans le navigateur.
Le client ne peut donc pas choisir une météo favorable.

---

## 7. Temps réel

### 7.1 SSE

`GET /api/events/stream` :

- envoie `Content-Type: text/event-stream`, `Cache-Control: no-cache`,
  `Connection: keep-alive` et `Content-Encoding: identity` ;
- écrit un commentaire d'ouverture puis les événements disponibles ;
- surveille `filemtime()` du journal toutes les 500 ms ;
- envoie `id:`, `event: farm` et `data: {...}` pour chaque nouvel événement ;
- envoie un commentaire `: ping` toutes les dix secondes ;
- s'arrête quand `connection_aborted()` détecte la fermeture du client.

Le client peut reprendre avec `?after=<dernierId>`.

### 7.2 WebSocket

`ws/server.php` utilise Workerman :

- serveur `websocket://0.0.0.0:50200` ;
- au démarrage, mémorise le dernier identifiant du journal ;
- toutes les 500 ms, lit le journal et diffuse les nouveaux événements à tous les
  clients ;
- accepte un message texte d'un client, l'enregistre dans le journal puis le diffuse.

Le service Docker est optionnel (`--profile websocket`) pour ne pas alourdir le
démarrage par défaut.

---

## 8. Décisions et alternatives

### Pourquoi deux backends ?

Le sujet l'impose et cela illustre une architecture réaliste : le site pourrait être
remplacé par une application mobile ou un autre front sans toucher à l'API. Les
responsabilités sont claires : présentation d'un côté, règles et données de l'autre.

### Pourquoi Twig installé à la main ?

Le sujet demande explicitement d'éviter Composer dans les conteneurs. Twig est donc
copié dans `src/site/Twig`, et l'autoloader du framework reconnaît le préfixe `Twig\`.
La fonction `trigger_deprecation()` est fournie comme un no-op pour les dépréciations
internes de Twig.

### Pourquoi dupliquer les classes métier ?

Le sujet autorise la duplication du code métier entre les deux backends pour rester
simple. Les **données** ne sont pas dupliquées : elles pointent vers `src/Data`. Une
évolution naturelle serait un package partagé ou un microservice unique.

### Pourquoi le client ne calcule-t-il pas les coûts ?

Parce qu'un client est toujours modifiable. Le serveur recalcule tout à partir de son
propre état. Le calcul côté JS ne sert qu'à l'affichage et à désactiver les boutons.

### Comment sécuriser davantage l'API ?

Dans une vraie application :

1. le site authentifierait le joueur puis émettrait un jeton signé (JWT) ou
   utiliserait un cookie `HttpOnly` partagé sur le même domaine ;
2. l'API vérifierait la signature et l'identité à chaque requête ;
3. les noms de joueurs dans l'URL seraient remplacés par l'identité du jeton ;
4. les écritures passeraient par une base transactionnelle plutôt que des fichiers.

---

## 9. Pistes d'évolution

- Remplacer les fichiers JSON par SQLite ou PostgreSQL.
- Ajouter un middleware d'authentification et de journalisation.
- Générer les types TypeScript de l'API à partir d'un schéma OpenAPI.
- Découper le front en modules ES et ajouter un bundler.
- Remplacer SSE par WebSocket pour tous les clients.
- Ajouter des tests unitaires PHP (PHPUnit) pour `FarmService` et `GameConfigRepository`.
- Ajouter un rate limiting sur `/api/events` et les actions de jeu.
