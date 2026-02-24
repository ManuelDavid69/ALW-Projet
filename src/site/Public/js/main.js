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

    // Exposé pour le débogage dans la console du navigateur.
    window.fermeEngine = engine;

    engine.start().catch((error) => {
        console.error(error);
        engine.setStatus(error.message || 'Impossible de charger la ferme.', true);
    });
})();
