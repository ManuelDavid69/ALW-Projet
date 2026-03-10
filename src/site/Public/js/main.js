/*
 * Point d'entrée du front : le moteur visuel est branché sur l'API REST.
 *
 * Les trois callbacks demandés par le sujet sont définis ici :
 *   - engine.onLoadState : recharge l'état complet de la ferme ;
 *   - engine.onHarvest   : demande une récolte au serveur ;
 *   - engine.onUpgrade   : demande une amélioration au serveur.
 */
(() => {
    const apiBaseUrl = window.FERME_API_URL
        || `${window.location.protocol}//${window.location.hostname}:50181`;

    const engine = new FermeEngine({
        apiBaseUrl,
        configUrl: '/Public/js/game_config.json',
        player: window.FERME_PLAYER,
    });

    async function requestJson(url, options = {}) {
        const response = await fetch(url, options);
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.error || `Erreur HTTP ${response.status}`);
        }

        return data;
    }

    function playerUrl(suffix = '') {
        return `${apiBaseUrl}/api/players/${encodeURIComponent(engine.player)}${suffix}`;
    }

    /**
     * Recharge l'inventaire et les bâtiments affichés.
     */
    engine.onLoadState = async () => {
        const [inventory, buildings] = await Promise.all([
            requestJson(playerUrl('/inventory')),
            requestJson(playerUrl('/buildings')),
        ]);

        engine.renderInventory(inventory);
        engine.renderBuildings(buildings, inventory);
    };

    /**
     * Récolte un bâtiment. La quantité ajoutée est décidée par le serveur.
     * On retourne la promesse du fetch pour que le moteur rafraîchisse
     * l'interface une fois l'opération terminée.
     */
    engine.onHarvest = async (buildingId) => {
        return requestJson(playerUrl(`/buildings/${encodeURIComponent(buildingId)}/harvest`), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
        });
    };

    /**
     * Améliore un bâtiment. Le serveur vérifie le coût et débite l'inventaire.
     */
    engine.onUpgrade = async (buildingId) => {
        return requestJson(playerUrl(`/buildings/${encodeURIComponent(buildingId)}/upgrade`), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
        });
    };

    /**
     * Affiche la météo de Lyon, récupérée par le webservice auprès
     * d'Open-Meteo (le serveur PHP agit ici comme client d'API).
     */
    async function loadWeather() {
        try {
            const weather = await requestJson(`${apiBaseUrl}/api/weather`);

            document.querySelector('#weather-icon').textContent = weather.icon || '🌡️';
            document.querySelector('#weather-condition').textContent = weather.condition || 'Météo variable';
            document.querySelector('#weather-temperature').textContent = weather.temperature === null
                ? '--'
                : Number(weather.temperature).toFixed(1).replace('.', ',');
            document.querySelector('#weather-wind').textContent = weather.windSpeed === null
                ? '--'
                : Number(weather.windSpeed).toFixed(1).replace('.', ',');
            document.querySelector('#weather-bonus').textContent =
                `×${Number(weather.productionModifier ?? 1).toFixed(2).replace('.', ',')}`;
            document.querySelector('#weather-advice').textContent =
                `${weather.advice || ''}${weather.stale ? ' (données en cache)' : ''}`.trim();
        } catch (error) {
            console.error(error);
            document.querySelector('#weather-condition').textContent = 'Météo indisponible';
            document.querySelector('#weather-advice').textContent = 'La production reste nominale.';
        }
    }

    /**
     * Ajoute un événement en tête du fil et limite la liste aux 20 derniers.
     */
    function appendLiveEvent(event) {
        const feed = document.querySelector('#live-feed');
        if (!feed) {
            return;
        }

        const item = document.createElement('li');
        item.className = 'live-event';
        item.dataset.type = event.type || 'world';

        const time = document.createElement('span');
        time.className = 'event-time';
        const date = event.timestamp ? new Date(event.timestamp) : new Date();
        time.textContent = Number.isNaN(date.getTime())
            ? '--:--:--'
            : date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

        const type = document.createElement('span');
        type.className = 'event-type';
        type.textContent = event.type || 'info';

        const message = document.createElement('span');
        message.className = 'event-message';
        message.textContent = event.message || '';

        item.append(time, type, message);
        feed.prepend(item);

        const empty = feed.querySelector('.live-empty');
        if (empty) {
            empty.remove();
        }

        while (feed.children.length > 20) {
            feed.lastElementChild.remove();
        }
    }

    function setLiveStatus(online, label) {
        const status = document.querySelector('#live-status');
        if (!status) {
            return;
        }

        status.classList.toggle('online', online === true);
        status.classList.toggle('offline', online === false);
        const labelElement = status.querySelector('.live-label');
        if (labelElement) {
            labelElement.textContent = label;
        }
    }

    /**
     * Ouvre un flux WebSocket (Workerman) à la place du flux SSE.
     * Activation : ?live=ws ou window.FERME_WS_URL = 'ws://...'.
     */
    function connectWebSocket() {
        const wsUrl = window.FERME_WS_URL || `ws://${window.location.hostname}:50200`;
        let socket;

        try {
            socket = new WebSocket(wsUrl);
        } catch (error) {
            console.error(error);
            setLiveStatus(false, 'WebSocket indisponible');
            return;
        }

        socket.addEventListener('open', () => setLiveStatus(true, 'WebSocket'));
        socket.addEventListener('message', (message) => {
            try {
                const event = JSON.parse(message.data);
                if (event.type === 'welcome') {
                    setLiveStatus(true, 'WebSocket');
                    return;
                }

                appendLiveEvent(event);
                engine.refresh().catch((error) => console.error(error));
            } catch (error) {
                console.error('Message WebSocket illisible', error);
            }
        });
        socket.addEventListener('close', () => setLiveStatus(false, 'WebSocket fermé'));
        socket.addEventListener('error', () => setLiveStatus(false, 'WebSocket indisponible'));

        window.addEventListener('beforeunload', () => socket.close());
    }

    /**
     * Ouvre le flux SSE du webservice. Chaque événement rafraîchit les stocks,
     * ce qui donne un suivi de production en direct.
     */
    function connectLiveFeed() {
        const feed = document.querySelector('#live-feed');
        if (!feed) {
            return;
        }

        const mode = new URLSearchParams(window.location.search).get('live');
        if (mode === '0') {
            setLiveStatus(null, 'Flux désactivé');
            return;
        }

        if (mode === 'ws' || window.FERME_WS_URL) {
            connectWebSocket();
            return;
        }

        const source = new EventSource(`${apiBaseUrl}/api/events/stream?after=0`);

        source.addEventListener('open', () => setLiveStatus(true, 'En direct'));
        source.addEventListener('farm', (message) => {
            try {
                const event = JSON.parse(message.data);
                appendLiveEvent(event);

                // Le flux est la source de vérité : on relit l'état complet
                // pour refléter aussi les actions des autres joueurs.
                engine.refresh().catch((error) => console.error(error));
            } catch (error) {
                console.error('Événement illisible', error);
            }
        });
        source.onerror = () => setLiveStatus(false, 'Reconnexion…');

        window.addEventListener('beforeunload', () => source.close());
    }

    // Exposé pour le débogage dans la console du navigateur.
    window.fermeEngine = engine;

    engine.start()
        .then(async () => {
            // On charge la météo avant d'ouvrir le flux SSE : avec le serveur
            // intégré de PHP (mono-processus), une connexion SSE bloque les
            // autres requêtes.
            await loadWeather();
            connectLiveFeed();
        })
        .catch((error) => {
            console.error(error);
            engine.setStatus(error.message || 'Impossible de charger la ferme.', true);
        });

    window.setInterval(loadWeather, 10 * 60 * 1000);
})();
