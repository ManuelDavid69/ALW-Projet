/**
 * Moteur visuel de la ferme.
 *
 * Le moteur ne décide pas des règles du jeu : il lit la configuration
 * (game_config.json), reçoit l'état du joueur depuis l'API et met à jour le
 * DOM. Les actions métier (récolter, améliorer) sont déléguées à main.js via
 * les callbacks onHarvest et onUpgrade.
 */
class FermeEngine {
    /**
     * @param {object} options
     * @param {string} options.apiBaseUrl URL de base du webservice
     * @param {string} options.configUrl URL du fichier de configuration
     * @param {string} options.player nom du joueur connecté
     */
    constructor(options = {}) {
        this.apiBaseUrl = options.apiBaseUrl || 'http://localhost:50181';
        this.configUrl = options.configUrl || '/Public/js/game_config.json';
        this.player = options.player || null;

        this.config = { products: {}, buildings: {} };
        this.bound = false;

        // Callbacks fournis par main.js.
        this.onLoadState = async () => {};
        this.onHarvest = async () => {};
        this.onUpgrade = async () => {};
    }

    async start() {
        await this.loadConfig();
        this.bindEvents();
        await this.refresh();
    }

    async loadConfig() {
        const response = await fetch(this.configUrl, { cache: 'no-cache' });
        if (!response.ok) {
            throw new Error(`Configuration inaccessible (${response.status}).`);
        }

        this.config = await response.json();
    }

    async refresh() {
        await this.onLoadState();
    }

    /**
     * Met à jour les quantités de l'inventaire dans les <output class="stock">.
     */
    renderInventory(inventory) {
        const root = document.querySelector('#inventory');
        if (!root) {
            return;
        }

        for (const [productId, product] of Object.entries(this.config.products)) {
            let card = document.getElementById(`product-${productId}`);

            if (!card) {
                card = this.createProductCard(productId, product);
                const grid = root.querySelector('.product-grid');
                if (grid) {
                    grid.append(card);
                }
            }

            const stock = card.querySelector('output.stock');
            if (stock) {
                stock.textContent = String(Number(inventory[productId] ?? 0));
            }
        }
    }

    /**
     * Met à jour les cartes des bâtiments : niveau, récolte, coût et boutons.
     *
     * @param {object} buildings niveaux possédés : { champ_ble: { level: 2 } }
     * @param {object} inventory stocks du joueur
     */
    renderBuildings(buildings, inventory) {
        const root = document.querySelector('#buildings');
        if (!root) {
            return;
        }

        const grid = root.querySelector('.building-grid');
        if (!grid) {
            return;
        }

        for (const [buildingId, building] of Object.entries(this.config.buildings)) {
            let card = document.getElementById(`building-${buildingId}`);
            if (!card) {
                card = this.createBuildingCard(buildingId, building);
                grid.append(card);
            }

            const level = Math.max(1, Number(buildings[buildingId]?.level ?? 1));
            const produced = this.config.products[building.produces] || { name: building.produces, unit: '' };
            const yieldPerHarvest = Math.max(1, Number(building.baseYield ?? 1) + (level - 1) * Number(building.yieldPerLevel ?? 0));
            const yieldUnit = yieldPerHarvest > 1
                ? (produced.unit || '')
                : (produced.unitSingular || produced.unit || '');
            const cost = this.upgradeCost(building, level);
            const canAfford = Object.entries(cost).every(([productId, quantity]) => {
                return Number(inventory[productId] ?? 0) >= quantity;
            });

            card.querySelector('output.level').textContent = String(level);
            card.querySelector('header p').textContent = `Produit : ${produced.name}`;
            card.querySelector('.description').textContent = building.description || '';

            const yieldElement = card.querySelector('.production strong');
            if (yieldElement) {
                yieldElement.textContent = `${yieldPerHarvest} ${yieldUnit}`.trim();
            }

            const costElement = card.querySelector('output.cost');
            if (costElement) {
                const parts = Object.entries(cost).map(([productId, quantity]) => {
                    const product = this.config.products[productId] || { icon: '' };
                    return `${product.icon} ${quantity}`.trim();
                });
                costElement.textContent = `Coût : ${parts.join(' · ')}`;
                costElement.classList.toggle('affordable', canAfford);
                costElement.classList.toggle('unaffordable', !canAfford);
            }

            const upgradeButton = card.querySelector('button.upgrade');
            if (upgradeButton) {
                upgradeButton.disabled = !canAfford;
            }

            const harvestButton = card.querySelector('button.harvest');
            if (harvestButton) {
                harvestButton.disabled = false;
            }
        }

        const totalLevels = Object.values(buildings).reduce((sum, building) => {
            return sum + Math.max(1, Number(building.level ?? 1));
        }, 0);
        const totalElement = document.getElementById('total-levels');
        if (totalElement) {
            totalElement.textContent = String(totalLevels);
        }
    }

    /**
     * Coût d'amélioration, identique au calcul du serveur.
     */
    upgradeCost(building, level) {
        const growth = Number(building.costGrowth ?? 1);
        const factor = Math.pow(growth, Math.max(0, level - 1));
        const cost = {};

        for (const [productId, baseQuantity] of Object.entries(building.upgradeCost || {})) {
            cost[productId] = Math.ceil(Number(baseQuantity) * factor);
        }

        return cost;
    }

    bindEvents() {
        const root = document.querySelector('#buildings');
        if (!root || this.bound) {
            return;
        }

        this.bound = true;
        root.addEventListener('click', async (event) => {
            const button = event.target.closest('button.harvest, button.upgrade');
            if (!button || button.disabled) {
                return;
            }

            const article = button.closest('article');
            const buildingId = button.dataset.building || (article ? article.id.replace('building-', '') : null);
            if (!buildingId) {
                return;
            }

            const isHarvest = button.classList.contains('harvest');
            button.disabled = true;
            this.setStatus(isHarvest ? 'Récolte en cours…' : 'Amélioration en cours…');

            try {
                if (isHarvest) {
                    await this.onHarvest(buildingId);
                } else {
                    await this.onUpgrade(buildingId);
                }

                await this.refresh();
                this.setStatus('');
            } catch (error) {
                console.error(error);
                this.setStatus(error.message || 'Une erreur est survenue.', true);
                button.disabled = false;
            }
        });
    }

    createProductCard(productId, product) {
        const card = document.createElement('article');
        card.id = `product-${productId}`;
        card.className = 'product-card';
        card.innerHTML = `
            <span class="icon" aria-hidden="true"></span>
            <div>
                <h3></h3>
                <p></p>
            </div>
            <output class="stock" data-product="${productId}">0</output>
        `;
        card.querySelector('.icon').textContent = product.icon || '';
        card.querySelector('h3').textContent = product.name || productId;
        card.querySelector('p').textContent = product.unit || '';

        return card;
    }

    createBuildingCard(buildingId, building) {
        const card = document.createElement('article');
        card.id = `building-${buildingId}`;
        card.className = 'building-card';
        card.innerHTML = `
            <header>
                <span class="icon" aria-hidden="true"></span>
                <div>
                    <h3></h3>
                    <p></p>
                </div>
                <span class="level-badge">Niv. <output class="level">1</output></span>
            </header>
            <p class="description"></p>
            <div class="production">Récolte : <strong></strong></div>
            <div class="actions">
                <button class="harvest" type="button" data-building="${buildingId}">Produire</button>
                <button class="upgrade" type="button" data-building="${buildingId}">Améliorer</button>
            </div>
            <output class="cost"></output>
        `;
        card.querySelector('.icon').textContent = building.icon || '';
        card.querySelector('h3').textContent = building.name || buildingId;

        return card;
    }

    setStatus(message, isError = false) {
        const status = document.getElementById('farm-status');
        if (!status) {
            return;
        }

        status.textContent = message;
        status.classList.toggle('error', Boolean(isError));
    }
}

window.FermeEngine = FermeEngine;
