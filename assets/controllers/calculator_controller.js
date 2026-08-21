import { Controller } from '@hotwired/stimulus';

/**
 * The damage calculator, entirely client-side: the server hands over the
 * Pokémon list, item catalogue and (on request) one Pokémon's full
 * stats/movepool once; every stat/nature/toggle change recalculates and
 * re-renders instantly here, no page reload.
 *
 * Formulas mirror src/Service/DamageCalculator.php and TypeChart.php exactly
 * (level 50 always, "points" system instead of classic IV/EV) — keep both in
 * sync if the rules ever change.
 */

const STAGE_MULTIPLIERS = {
    '-6': 2 / 8, '-5': 2 / 7, '-4': 2 / 6, '-3': 2 / 5, '-2': 2 / 4, '-1': 2 / 3,
    0: 1,
    1: 3 / 2, 2: 4 / 2, 3: 5 / 2, 4: 6 / 2, 5: 7 / 2, 6: 8 / 2,
};

const TYPE_CHART = {
    normal: { rock: 0.5, ghost: 0, steel: 0.5 },
    fire: { fire: 0.5, water: 0.5, grass: 2, ice: 2, bug: 2, rock: 0.5, dragon: 0.5, steel: 2 },
    water: { fire: 2, water: 0.5, grass: 0.5, ground: 2, rock: 2, dragon: 0.5 },
    electric: { water: 2, electric: 0.5, grass: 0.5, ground: 0, flying: 2, dragon: 0.5 },
    grass: { fire: 0.5, water: 2, grass: 0.5, poison: 0.5, ground: 2, flying: 0.5, bug: 0.5, rock: 2, dragon: 0.5, steel: 0.5 },
    ice: { fire: 0.5, water: 0.5, grass: 2, ice: 0.5, ground: 2, flying: 2, dragon: 2, steel: 0.5 },
    fighting: { normal: 2, ice: 2, poison: 0.5, flying: 0.5, psychic: 0.5, bug: 0.5, rock: 2, ghost: 0, dark: 2, steel: 2, fairy: 0.5 },
    poison: { grass: 2, poison: 0.5, ground: 0.5, rock: 0.5, ghost: 0.5, steel: 0, fairy: 2 },
    ground: { fire: 2, electric: 2, grass: 0.5, poison: 2, flying: 0, bug: 0.5, rock: 2, steel: 2 },
    flying: { electric: 0.5, grass: 2, fighting: 2, bug: 2, rock: 0.5, steel: 0.5 },
    psychic: { fighting: 2, poison: 2, psychic: 0.5, dark: 0, steel: 0.5 },
    bug: { fire: 0.5, grass: 2, fighting: 0.5, poison: 0.5, flying: 0.5, psychic: 2, ghost: 0.5, dark: 2, steel: 0.5, fairy: 0.5 },
    rock: { fire: 2, ice: 2, fighting: 0.5, ground: 0.5, flying: 2, bug: 2, steel: 0.5 },
    ghost: { normal: 0, psychic: 2, ghost: 2, dark: 0.5 },
    dragon: { dragon: 2, steel: 0.5, fairy: 0 },
    dark: { fighting: 0.5, psychic: 2, ghost: 2, dark: 0.5, fairy: 0.5 },
    steel: { fire: 0.5, water: 0.5, electric: 0.5, ice: 2, rock: 2, steel: 0.5, fairy: 2 },
    fairy: { fire: 0.5, fighting: 2, poison: 0.5, dragon: 2, dark: 2, steel: 0.5 },
};

const TYPE_BOOST_ITEMS = {
    charcoal: 'fire', 'mystic-water': 'water', 'miracle-seed': 'grass',
    'never-melt-ice': 'ice', 'black-belt': 'fighting', 'poison-barb': 'poison',
    'soft-sand': 'ground', 'sharp-beak': 'flying', 'twisted-spoon': 'psychic',
    'silver-powder': 'bug', 'hard-stone': 'rock', 'spell-tag': 'ghost',
    'dragon-fang': 'dragon', 'silk-scarf': 'normal', 'metal-coat': 'steel',
    magnet: 'electric', 'black-glasses': 'dark', 'fairy-feather': 'fairy',
};

const STAT_ROWS = [
    { label: 'PV', key: 'pv', point: 'pv', stage: null },
    { label: 'Attaque', key: 'attaque', point: 'atq', stage: 'stAtq' },
    { label: 'Défense', key: 'defense', point: 'def', stage: 'stDef' },
    { label: 'Atq. Spé.', key: 'atqSpe', point: 'atqSpe', stage: 'stAtqSpe' },
    { label: 'Déf. Spé.', key: 'defSpe', point: 'defSpe', stage: 'stDefSpe' },
    { label: 'Vitesse', key: 'vitesse', point: 'vitesse', stage: 'stVitesse' },
];

// Same 5 stats NatureCatalog::STATS uses, in the same order — the grid's rows/columns.
const NATURE_STATS = { attaque: 'Attaque', defense: 'Défense', atqSpe: 'Atq. Spé.', defSpe: 'Déf. Spé.', vitesse: 'Vitesse' };

const NEUTRAL_ROW_FOR = { serieux: 'attaque', docile: 'defense', farceur: 'atqSpe', bizarre: 'defSpe', hardi: 'vitesse' };

/** Mirrors NatureCatalog::grid() — [rowStat][colStat] => natureKey (row = stat up, column = stat down). */
function buildNatureGrid(natures) {
    const grid = {};
    for (const [key, nature] of Object.entries(natures)) {
        const row = nature.up || NEUTRAL_ROW_FOR[key] || 'attaque';
        const col = nature.down || row;
        grid[row] = grid[row] || {};
        grid[row][col] = key;
    }
    return grid;
}

function typeMultiplier(atkType, defTypes) {
    atkType = atkType.toLowerCase();
    let m = 1;
    for (const d of defTypes) {
        const row = TYPE_CHART[atkType];
        const v = row ? row[d.toLowerCase()] : undefined;
        m *= v === undefined ? 1 : v;
    }
    return m;
}

function natureMultiplier(natures, natureKey, statKey) {
    const n = natures[natureKey];
    if (!n) {
        return 1;
    }
    if (n.up === statKey) {
        return 1.1;
    }
    if (n.down === statKey) {
        return 0.9;
    }
    return 1;
}

function statAtLevel50(natures, base, points, natureKey, statKey) {
    if (statKey === 'pv') {
        return base + points + 75;
    }
    return Math.floor((base + points + 20) * natureMultiplier(natures, natureKey, statKey));
}

function stageMultiplier(stage) {
    stage = Math.max(-6, Math.min(6, stage));
    return STAGE_MULTIPLIERS[stage];
}

function stageAdjustedStat(finalStat, stage, ignoreNegative, ignorePositive) {
    if (ignoreNegative && stage < 0) {
        stage = 0;
    }
    if (ignorePositive && stage > 0) {
        stage = 0;
    }
    return Math.floor(finalStat * stageMultiplier(stage));
}

function weatherMultiplier(weather, moveType) {
    moveType = moveType.toLowerCase();
    if (weather === 'pluie') {
        if (moveType === 'water') return 1.5;
        if (moveType === 'fire') return 0.5;
    }
    if (weather === 'soleil') {
        if (moveType === 'fire') return 1.5;
        if (moveType === 'water') return 0.5;
    }
    return 1;
}

function terrainMultiplier(terrain, moveType, attackerGrounded, defenderGrounded) {
    moveType = moveType.toLowerCase();
    if (terrain === 'brumeux' && defenderGrounded && moveType === 'dragon') {
        return 0.5;
    }
    if (!attackerGrounded || !terrain) {
        return 1;
    }
    if (terrain === 'electrique' && moveType === 'electric') return 1.3;
    if (terrain === 'herbu' && moveType === 'grass') return 1.3;
    if (terrain === 'psychique' && moveType === 'psychic') return 1.3;
    return 1;
}

function itemMultiplier(item, move, effectiveness) {
    if (!item) {
        return 1;
    }
    let m = 1;
    if (item === 'life-orb') m *= 1.3;
    if (item === 'expert-belt' && effectiveness > 1) m *= 1.2;
    if (item === 'muscle-band' && move.isPhysical) m *= 1.1;
    if (item === 'wise-glasses' && !move.isPhysical) m *= 1.1;
    if (TYPE_BOOST_ITEMS[item] === move.type.toLowerCase()) m *= 1.2;
    return m;
}

/**
 * Doubles-only quirk (matches the actual games, verified against Pikalytics'
 * live Champions calculator): Light Screen/Reflect only cut damage by 1/3
 * (2732/4096) in doubles, not the usual 1/2 from singles.
 */
const DOUBLES_SCREEN_FRACTION = 2732 / 4096;

function computeDamage(attacker, defender, move, context, randomRoll, effectiveness) {
    if (context.protection) {
        return 0;
    }
    const isCrit = context.isCritical;
    const atk = stageAdjustedStat(attacker.atk, attacker.atkStage, isCrit, false);
    const def = Math.max(1, stageAdjustedStat(defender.def, defender.defStage, false, isCrit));

    // Spread (multi-target), weather and critical all combine into ONE multiplier
    // applied before the base formula's single floor+2 — verified directly against
    // the live @smogon/calc-based Pikalytics Champions calculator; they are NOT
    // separate late floor() steps like the modern-gen textbook formula suggests.
    let earlyMod = 1;
    if (move.isSpread) earlyMod *= 0.75;
    earlyMod *= weatherMultiplier(context.weather, move.type);
    earlyMod *= terrainMultiplier(context.terrain, move.type, attacker.grounded, defender.grounded);
    if (isCrit) earlyMod *= 1.5;

    let damage = Math.floor(((22 * move.power * atk / def) / 50) * earlyMod) + 2;

    damage = Math.floor(damage * randomRoll / 100);

    if (attacker.types.map((t) => t.toLowerCase()).includes(move.type.toLowerCase())) {
        damage = Math.floor(damage * 1.5);
    }
    damage = Math.floor(damage * effectiveness);

    if (attacker.status === 'brulure' && move.isPhysical) {
        damage = Math.floor(damage * 0.5);
    }

    // Items/screens/Helping Hand chain together and ROUND (not floor) — also
    // verified against the live calculator; floor() undershoots here.
    let other = 1;
    if (context.helpingHand) other *= 1.5;
    if (context.lightScreen && !move.isPhysical && !isCrit) other *= DOUBLES_SCREEN_FRACTION;
    other *= itemMultiplier(attacker.item, move, effectiveness);
    damage = Math.round(damage * other);

    return Math.max(0, damage);
}

function koAnalysis(damages, defenderHp) {
    if (damages.reduce((a, b) => a + b, 0) === 0) {
        return { hits: null, percent: 0, guaranteed: false };
    }

    let distribution = new Map([[0, 1]]);
    for (let hits = 1; hits <= 6; hits++) {
        const next = new Map();
        for (const [sum, ways] of distribution) {
            for (const d of damages) {
                const s = sum + d;
                next.set(s, (next.get(s) || 0) + ways);
            }
        }
        distribution = next;

        let total = 0;
        let koWays = 0;
        for (const [sum, ways] of distribution) {
            total += ways;
            if (sum >= defenderHp) koWays += ways;
        }

        if (koWays > 0) {
            return { hits, percent: (koWays / total) * 100, guaranteed: koWays === total };
        }
    }
    return { hits: null, percent: 0, guaranteed: false };
}

function evaluateMove(attacker, defender, move, context) {
    if (!move.power) {
        return {
            minDamage: 0, maxDamage: 0, minPercent: 0, maxPercent: 0,
            effectiveness: typeMultiplier(move.type, defender.types),
            koHits: null, koPercent: 0, koGuaranteed: false,
        };
    }

    const effectiveness = typeMultiplier(move.type, defender.types);
    const damages = [];
    for (let roll = 85; roll <= 100; roll++) {
        damages.push(computeDamage(attacker, defender, move, context, roll, effectiveness));
    }

    const min = damages[0];
    const max = damages[damages.length - 1];
    const maxHp = Math.max(1, defender.maxHp);
    const ko = koAnalysis(damages, maxHp);

    return {
        minDamage: min, maxDamage: max,
        minPercent: (min / maxHp) * 100, maxPercent: (max / maxHp) * 100,
        effectiveness,
        koHits: ko.hits, koPercent: ko.percent, koGuaranteed: ko.guaranteed,
    };
}

function emptySide() {
    return {
        pokemon: '', forme: 'base', nature: '', objet: '', talent: '', statut: '',
        pv: 0, atq: 0, def: 0, atqSpe: 0, defSpe: 0, vitesse: 0,
        stAtq: 0, stDef: 0, stAtqSpe: 0, stDefSpe: 0, stVitesse: 0,
        m1: '', m2: '', m3: '', m4: '',
        c1: false, c2: false, c3: false, c4: false,
        protection: false, lightScreen: false, helpingHand: false,
    };
}

function esc(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

export default class extends Controller {
    static targets = ['panelA', 'panelB', 'results', 'vs', 'weather', 'terrain', 'backdrop', 'modalContent'];
    static values = { bootstrap: Object };

    connect() {
        this.pokemonList = this.bootstrapValue.pokemonList || [];
        this.itemCatalog = this.bootstrapValue.itemCatalog || [];
        this.natures = this.bootstrapValue.natures || {};
        this.weatherOptions = this.bootstrapValue.weatherOptions || {};
        this.terrainOptions = this.bootstrapValue.terrainOptions || {};
        this.typeIcons = this.bootstrapValue.typeIcons || {};
        this.typeNamesFr = this.bootstrapValue.typeNamesFr || {};

        this.pokemonCache = {};
        this.natureGrid = buildNatureGrid(this.natures);
        this.state = { a: emptySide(), b: emptySide(), weather: '', terrain: '', active: 'a1' };

        this.renderGlobalControls();
        this.render();
    }

    // ---- global field controls (weather/terrain) ----

    renderGlobalControls() {
        const buildOptions = (options, selected) => `<option value="">Aucun</option>` + Object.entries(options)
            .map(([key, label]) => `<option value="${key}"${key === selected ? ' selected' : ''}>${esc(label)}</option>`)
            .join('');
        this.weatherTarget.innerHTML = buildOptions(this.weatherOptions, this.state.weather);
        this.terrainTarget.innerHTML = buildOptions(this.terrainOptions, this.state.terrain);
    }

    onGlobalChange(event) {
        const field = event.currentTarget === this.weatherTarget ? 'weather' : 'terrain';
        this.state[field] = event.currentTarget.value;
        this.renderResultsAndVs();
    }

    // ---- rendering ----

    render() {
        this.renderPanel('a');
        this.renderPanel('b');
        this.renderResultsAndVs();
    }

    /** Merges the picked Pokémon's cached data with whichever forme (base/méga) is selected for this side. */
    resolveForme(raw, data) {
        if (!data) {
            return null;
        }
        if (raw.forme && raw.forme !== 'base') {
            const form = (data.forms || []).find((f) => f.slug === raw.forme);
            if (form) {
                return { slug: data.slug, name: form.label, sprite: form.sprite, types: form.types, baseStats: form.baseStats, abilities: form.abilities, moves: data.moves };
            }
        }
        return data;
    }

    renderPanel(side) {
        const target = side === 'a' ? this.panelATarget : this.panelBTarget;
        const raw = this.state[side];
        const data = raw.pokemon ? this.pokemonCache[raw.pokemon] : null;
        const effective = this.resolveForme(raw, data);
        const label = side === 'a' ? 'Mon côté' : 'Côté adverse';

        let html = `<a href="#" class="dropdown-btn calc-panel__pokemon-picker" data-action="click->calculator#openPokemonPicker" data-side="${side}">
            <span class="dropdown-btn__left">${effective ? esc(effective.name) : 'Choisir un Pokémon'}</span>
            <span>▾</span>
        </a>`;

        if (data) {
            html += this.renderPanelBody(side, raw, effective, label, data);
        }

        target.innerHTML = html;
    }

    renderPanelBody(side, raw, data, label, rawData) {
        const finalStats = this.finalStats(raw, data);

        const typePills = data.types.map((t) => `<span class="type-pill"><img src="${this.typeIcons[t] || ''}" alt="">${esc((this.typeNamesFr[t] || t).toUpperCase())}</span>`).join('');

        const statRows = STAT_ROWS.map((row) => {
            const stageCell = row.stage
                ? `<select class="calc-stage-select" data-action="change->calculator#onStageChange" data-side="${side}" data-stage="${row.stage}">
                    ${[-6, -5, -4, -3, -2, -1, 0, 1, 2, 3, 4, 5, 6].map((s) => `<option value="${s}"${raw[row.stage] === s ? ' selected' : ''}>${s > 0 ? '+' + s : s}</option>`).join('')}
                </select>`
                : '';
            return `<tr>
                <td class="calc-stats-table__label">${row.label}</td>
                <td><input type="number" min="0" max="32" class="calc-stat-input" value="${raw[row.point]}" data-action="input->calculator#onPointChange" data-side="${side}" data-point="${row.point}"></td>
                <td>${stageCell}</td>
                <td class="calc-stats-table__value">${finalStats[row.key]}</td>
            </tr>`;
        }).join('');

        const talentOptions = `<option value="">—</option>` + data.abilities
            .map((a) => `<option value="${esc(a.name)}"${raw.talent === a.name ? ' selected' : ''}>${esc(a.name)}${a.isHidden ? ' (caché)' : ''}</option>`).join('');

        const item = raw.objet ? this.itemCatalog.find((i) => i.slug === raw.objet) : null;

        const formeSelect = (rawData.forms && rawData.forms.length)
            ? `<label class="calc-select-label">Forme :
                <select class="dropdown-btn calc-select" data-action="change->calculator#onFormeChange" data-side="${side}">
                    <option value="base"${!raw.forme || raw.forme === 'base' ? ' selected' : ''}>Forme de base</option>
                    ${rawData.forms.map((f) => `<option value="${f.slug}"${raw.forme === f.slug ? ' selected' : ''}>${esc(f.label)}</option>`).join('')}
                </select>
            </label>`
            : '';

        const movesHtml = [1, 2, 3, 4].map((i) => {
            const slug = raw['m' + i];
            const move = slug ? data.moves.find((m) => m.slug === slug) : null;
            return `<div class="calc-move-row">
                <a href="#" class="dropdown-btn calc-move-row__picker" data-action="click->calculator#openMovePicker" data-side="${side}" data-slot="${i}">
                    ${move ? `<img class="dropdown-btn__left-icon" src="${this.typeIcons[capitalize(move.type)] || ''}" alt="">` : ''}
                    <span class="dropdown-btn__left">${move ? esc(move.name) : 'Capacité ' + i}</span>
                    <span>▾</span>
                </a>
                <span class="calc-move-row__power">${move ? (move.power ?? '-') : ''}</span>
                <label class="calc-crit-toggle${raw['c' + i] ? ' is-active' : ''}">
                    <input type="checkbox" data-action="change->calculator#onToggleChange" data-side="${side}" data-field="c${i}" ${raw['c' + i] ? 'checked' : ''}>
                    Critique
                </label>
            </div>`;
        }).join('');

        return `
            <div class="calc-panel__identity">
                <img class="calc-panel__sprite" src="${data.sprite}" alt="${esc(data.name)}">
                <h3 class="calc-panel__name">${esc(data.name)}</h3>
                <div class="type-pills" style="justify-content:center;">${typePills}</div>
            </div>

            <table class="calc-stats-table">
                <tr><th></th><th>Points</th><th>Mod.</th><th></th></tr>
                ${statRows}
            </table>

            <div class="calc-panel__selects">
                ${formeSelect}
                <a href="#" class="dropdown-btn" data-action="click->calculator#openNaturePicker" data-side="${side}">
                    <span class="dropdown-btn__left">${raw.nature && this.natures[raw.nature] ? esc(this.natures[raw.nature].name) : 'Choisir une nature'}</span>
                    <span>▾</span>
                </a>
                <label class="calc-select-label">Talent :
                    <select class="dropdown-btn calc-select" data-action="change->calculator#onSelectChange" data-side="${side}" data-field="talent">${talentOptions}</select>
                </label>
                <a href="#" class="dropdown-btn" data-action="click->calculator#openItemPicker" data-side="${side}">
                    <span class="dropdown-btn__left">${item ? esc(item.name) : 'Objet'}</span>
                    <span>▾</span>
                </a>
                <label class="calc-select-label">Statut :
                    <select class="dropdown-btn calc-select" data-action="change->calculator#onSelectChange" data-side="${side}" data-field="statut">
                        <option value=""${raw.statut === '' ? ' selected' : ''}>Aucun</option>
                        <option value="brulure"${raw.statut === 'brulure' ? ' selected' : ''}>Brûlure</option>
                        <option value="paralysie"${raw.statut === 'paralysie' ? ' selected' : ''}>Paralysie</option>
                    </select>
                </label>
            </div>

            <div class="field-group">
                <div class="field-group__label">Capacités :</div>
                ${movesHtml}
            </div>

            <div class="field-group">
                <div class="field-group__label">${label} :</div>
                <div class="calc-field-toggles">
                    <label class="calc-toggle-btn${raw.protection ? ' is-active' : ''}">
                        <input type="checkbox" data-action="change->calculator#onToggleChange" data-side="${side}" data-field="protection" ${raw.protection ? 'checked' : ''}>
                        Protection
                    </label>
                    <label class="calc-toggle-btn${raw.lightScreen ? ' is-active' : ''}">
                        <input type="checkbox" data-action="change->calculator#onToggleChange" data-side="${side}" data-field="lightScreen" ${raw.lightScreen ? 'checked' : ''}>
                        Mur Lumière
                    </label>
                    <label class="calc-toggle-btn${raw.helpingHand ? ' is-active' : ''}">
                        <input type="checkbox" data-action="change->calculator#onToggleChange" data-side="${side}" data-field="helpingHand" ${raw.helpingHand ? 'checked' : ''}>
                        Coup de main
                    </label>
                </div>
            </div>
        `;
    }

    finalStats(raw, data) {
        const stats = {
            pv: statAtLevel50(this.natures, data.baseStats.hp, raw.pv, null, 'pv'),
            attaque: statAtLevel50(this.natures, data.baseStats.attack, raw.atq, raw.nature || null, 'attaque'),
            defense: statAtLevel50(this.natures, data.baseStats.defense, raw.def, raw.nature || null, 'defense'),
            atqSpe: statAtLevel50(this.natures, data.baseStats.sp_attack, raw.atqSpe, raw.nature || null, 'atqSpe'),
            defSpe: statAtLevel50(this.natures, data.baseStats.sp_defense, raw.defSpe, raw.nature || null, 'defSpe'),
            vitesse: statAtLevel50(this.natures, data.baseStats.speed, raw.vitesse, raw.nature || null, 'vitesse'),
        };
        if (raw.statut === 'paralysie') {
            stats.vitesse = Math.floor(stats.vitesse / 2);
        }
        return stats;
    }

    sideProfile(side) {
        const raw = this.state[side];
        const data = this.resolveForme(raw, raw.pokemon ? this.pokemonCache[raw.pokemon] : null);
        if (!data) {
            return null;
        }
        return {
            raw,
            data,
            finalStats: this.finalStats(raw, data),
            types: data.types,
            grounded: !data.types.includes('Flying'),
        };
    }

    evaluateSide(attackerSide, defenderSide) {
        const attacker = this.sideProfile(attackerSide);
        const defender = this.sideProfile(defenderSide);
        const results = { 1: null, 2: null, 3: null, 4: null };
        if (!attacker || !defender) {
            return results;
        }

        for (const i of [1, 2, 3, 4]) {
            const slug = attacker.raw['m' + i];
            const move = slug ? attacker.data.moves.find((m) => m.slug === slug) : null;
            if (!move) {
                continue;
            }

            const isPhysical = move.damageClass === 'physical';
            const attackProfile = {
                atk: isPhysical ? attacker.finalStats.attaque : attacker.finalStats.atqSpe,
                atkStage: isPhysical ? attacker.raw.stAtq : attacker.raw.stAtqSpe,
                types: attacker.types,
                item: attacker.raw.objet || null,
                status: attacker.raw.statut || null,
                grounded: attacker.grounded,
            };
            const defenseProfile = {
                def: isPhysical ? defender.finalStats.defense : defender.finalStats.defSpe,
                defStage: isPhysical ? defender.raw.stDef : defender.raw.stDefSpe,
                maxHp: defender.finalStats.pv,
                types: defender.types,
                grounded: defender.grounded,
            };
            const context = {
                isCritical: !!attacker.raw['c' + i],
                weather: this.state.weather || null,
                terrain: this.state.terrain || null,
                helpingHand: !!attacker.raw.helpingHand,
                lightScreen: !!defender.raw.lightScreen,
                protection: !!defender.raw.protection,
            };

            const result = evaluateMove(attackProfile, defenseProfile, {
                power: move.power, type: move.type, isPhysical, isSpread: !!move.isSpread,
            }, context);
            result.move = move;
            results[i] = result;
        }

        return results;
    }

    renderResultsAndVs() {
        const aToB = this.evaluateSide('a', 'b');
        const bToA = this.evaluateSide('b', 'a');
        this.lastResults = { aToB, bToA };

        const dataA = this.resolveForme(this.state.a, this.state.a.pokemon ? this.pokemonCache[this.state.a.pokemon] : null);
        const dataB = this.resolveForme(this.state.b, this.state.b.pokemon ? this.pokemonCache[this.state.b.pokemon] : null);

        const col = (title, results, side) => {
            const rows = [1, 2, 3, 4].map((i) => {
                const r = results[i];
                if (!r) return '';
                const isActive = this.state.active === side + i;
                return `<button type="button" class="calc-result-row${isActive ? ' is-active' : ''}" data-action="click->calculator#setActive" data-active="${side}${i}">
                    <span>${esc(r.move.name)}</span>
                    <span>${r.minPercent.toFixed(1)} % - ${r.maxPercent.toFixed(1)} %</span>
                </button>`;
            }).join('');
            return `<div class="calc-results__col"><h3 class="calc-results__title">${title}</h3>${rows}</div>`;
        };

        this.resultsTarget.innerHTML = col('Dégâts de mon Pokémon :', aToB, 'a') + col('Dégâts du Pokémon adverse :', bToA, 'b');

        const activeSide = this.state.active.startsWith('b') ? 'b' : 'a';
        const activeIndex = Math.max(1, Math.min(4, parseInt(this.state.active.slice(1), 10) || 1));
        const sideResults = activeSide === 'a' ? aToB : bToA;
        const res = sideResults[activeIndex];
        const attackerData = activeSide === 'a' ? dataA : dataB;
        const defenderData = activeSide === 'a' ? dataB : dataA;

        if (res && attackerData && defenderData) {
            let effBadge = '';
            if (res.effectiveness === 0) effBadge = '<span class="calc-eff calc-eff--zero">Immunité</span>';
            else if (res.effectiveness > 1) effBadge = '<span class="calc-eff calc-eff--up">Super efficace</span>';
            else if (res.effectiveness < 1) effBadge = '<span class="calc-eff calc-eff--down">Peu efficace</span>';

            let koLine;
            if (res.koHits === null) {
                koLine = 'Aucun KO en 6 coups ou moins';
            } else if (res.koGuaranteed) {
                koLine = `${res.koHits}HKO${res.koHits === 1 ? ' (OHKO)' : ''} Garantie`;
            } else {
                koLine = `${res.koPercent.toFixed(1)} % de chance de ${res.koHits}HKO${res.koHits === 1 ? ' (OHKO)' : ''}`;
            }

            this.vsTarget.innerHTML = `
                <div class="calc-vs">
                    <div class="calc-vs__mon"><img src="${attackerData.sprite}" alt=""><span>${esc(attackerData.name)}</span></div>
                    <span class="calc-vs__label">VS</span>
                    <div class="calc-vs__mon"><img src="${defenderData.sprite}" alt=""><span>${esc(defenderData.name)}</span></div>
                </div>
                <div class="calc-vs__detail">
                    <p><strong>Attaque :</strong> ${esc(res.move.name)} ${effBadge}</p>
                    <p><strong>Dégâts :</strong> ${res.minDamage} - ${res.maxDamage} <span class="calc-vs__percent">(${res.minPercent.toFixed(1)} % - ${res.maxPercent.toFixed(1)} %)</span></p>
                    <p><strong>Chance de KO :</strong> ${koLine}</p>
                </div>
            `;
        } else {
            this.vsTarget.innerHTML = '<p class="calc-vs__empty">Choisissez un Pokémon et une capacité de chaque côté pour voir le résultat.</p>';
        }
    }

    // ---- field events ----

    onPointChange(event) {
        const { side, point } = event.currentTarget.dataset;
        const value = Math.max(0, Math.min(32, parseInt(event.currentTarget.value, 10) || 0));
        this.state[side][point] = value;
        this.renderPanel(side);
        this.renderResultsAndVs();
    }

    onStageChange(event) {
        const { side, stage } = event.currentTarget.dataset;
        this.state[side][stage] = parseInt(event.currentTarget.value, 10) || 0;
        this.renderResultsAndVs();
    }

    onFormeChange(event) {
        const side = event.currentTarget.dataset.side;
        this.state[side].forme = event.currentTarget.value;
        this.state[side].talent = ''; // abilities differ per forme
        this.renderPanel(side);
        this.renderResultsAndVs();
    }

    onSelectChange(event) {
        const { side, field } = event.currentTarget.dataset;
        this.state[side][field] = event.currentTarget.value;
        if (field === 'statut') {
            this.renderPanel(side);
        }
        this.renderResultsAndVs();
    }

    onToggleChange(event) {
        const { side, field } = event.currentTarget.dataset;
        this.state[side][field] = event.currentTarget.checked;
        this.renderPanel(side);
        this.renderResultsAndVs();
    }

    setActive(event) {
        this.state.active = event.currentTarget.dataset.active;
        this.renderResultsAndVs();
    }

    // ---- pokemon / move / item pickers ----

    openPokemonPicker(event) {
        event.preventDefault();
        const side = event.currentTarget.dataset.side;
        this.showModal(this.pokemonPickerHtml(side, ''));
    }

    pokemonPickerHtml(side, query) {
        const needle = query.trim().toLowerCase();
        const list = needle ? this.pokemonList.filter((p) => p.name.toLowerCase().includes(needle)) : this.pokemonList;

        const rows = list.map((p) => `
            <div class="data-table__row is-pokemon" data-action="click->calculator#pickPokemon" data-side="${side}" data-slug="${p.slug}">
                <img class="data-table__sprite" src="${p.sprite}" alt="" loading="lazy">
                <div>
                    <div class="data-table__name">${esc(p.name)}</div>
                    <div class="data-table__meta">${p.types.map((t) => `<span class="type-pill"><img src="${this.typeIcons[t] || ''}" alt="">${esc((this.typeNamesFr[t] || t).toUpperCase())}</span>`).join('')}</div>
                </div>
                <button type="button" class="btn btn--primary">Ajouter</button>
            </div>
        `).join('') || `<p style="padding:24px; text-align:center; color:var(--color-text-muted);">Aucun Pokémon ne correspond à « ${esc(query)} ».</p>`;

        return `
            <div class="modal-card">
                <a href="#" class="modal-card__close" data-action="click->calculator#closeModal">✕</a>
                <h1 class="modal-card__title">Choix du Pokémon</h1>
                <p class="modal-card__subtitle">Veuillez choisir le Pokémon</p>
                <div class="modal-filters">
                    <div class="modal-search">
                        <input type="search" placeholder="Nom du Pokémon" value="${esc(query)}" data-action="input->calculator#onPokemonSearch" data-side="${side}">
                        <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="8" cy="8" r="6.5" stroke="white" stroke-width="1.5"/><path d="M17 17L13 13" stroke="white" stroke-width="1.5" stroke-linecap="round"/></svg>
                    </div>
                </div>
                <div class="data-table"><div class="data-table__scroll">${rows}</div></div>
            </div>
        `;
    }

    onPokemonSearch(event) {
        const side = event.currentTarget.dataset.side;
        this.modalContentTarget.innerHTML = this.pokemonPickerHtml(side, event.currentTarget.value);
        this.modalContentTarget.querySelector('input[type="search"]').focus();
        const input = this.modalContentTarget.querySelector('input[type="search"]');
        input.setSelectionRange(input.value.length, input.value.length);
    }

    async pickPokemon(event) {
        const { side, slug } = event.currentTarget.dataset;
        this.showLoading();
        try {
            const data = await this.fetchPokemon(slug);
            this.state[side] = { ...emptySide(), pokemon: slug };
            this.pokemonCache[slug] = data;
            this.hideModal();
            this.render();
        } catch {
            this.modalContentTarget.innerHTML = '<div class="modal-card"><p style="padding:40px; text-align:center;">Une erreur est survenue. <a href="#" data-action="click->calculator#closeModal">Fermer</a></p></div>';
        }
    }

    async fetchPokemon(slug) {
        if (this.pokemonCache[slug]) {
            return this.pokemonCache[slug];
        }
        const response = await fetch(`/calculateur-de-degats/api/pokemon/${slug}`);
        if (!response.ok) {
            throw new Error('fetch failed');
        }
        return response.json();
    }

    openMovePicker(event) {
        event.preventDefault();
        const { side, slot } = event.currentTarget.dataset;
        this.showModal(this.movePickerHtml(side, slot, '', ''));
    }

    movePickerHtml(side, slot, query, selectedType) {
        const data = this.pokemonCache[this.state[side].pokemon];
        const moves = data ? data.moves : [];
        const availableTypes = [...new Set(moves.map((m) => m.type))].sort();

        let filtered = moves;
        if (query.trim()) {
            const needle = query.trim().toLowerCase();
            filtered = filtered.filter((m) => m.name.toLowerCase().includes(needle));
        }
        if (selectedType) {
            filtered = filtered.filter((m) => m.type === selectedType);
        }

        const categoryFr = { physical: 'Physique', special: 'Spécial', status: 'Statut' };

        const rows = filtered.map((m) => `
            <div class="data-table__row is-move" data-action="click->calculator#pickMove" data-side="${side}" data-slot="${slot}" data-slug="${m.slug}">
                <img class="data-table__icon" src="${this.typeIcons[capitalize(m.type)] || ''}" alt="${m.type}">
                <span class="data-table__name">${esc(m.name)}</span>
                <span class="data-table__cell--dim">${categoryFr[m.damageClass] || m.damageClass}</span>
                <span class="data-table__cell--dim">${m.power ?? '-'}</span>
                <span class="data-table__cell--dim">${m.pp ?? '-'}</span>
                <span class="data-table__cell--dim">${m.accuracy ? m.accuracy + ' %' : '-'}</span>
                <button type="button" class="btn btn--primary">Ajouter</button>
            </div>
        `).join('') || `<p style="padding:24px; text-align:center; color:var(--color-text-muted);">Aucune capacité ne correspond à ces critères.</p>`;

        const typeOptions = `<option value="">Tous les types</option>` + availableTypes
            .map((t) => `<option value="${t}"${t === selectedType ? ' selected' : ''}>${esc(this.typeNamesFr[capitalize(t)] || t)}</option>`).join('');

        return `
            <div class="modal-card">
                <a href="#" class="modal-card__close" data-action="click->calculator#closeModal">✕</a>
                <h1 class="modal-card__title">Choix de la capacité</h1>
                <p class="modal-card__subtitle">Veuillez choisir la capacité que le Pokémon doit apprendre</p>
                <div class="modal-filters">
                    <div class="modal-search">
                        <input type="search" placeholder="Nom de la capacité" value="${esc(query)}" data-action="input->calculator#onMoveSearch" data-side="${side}" data-slot="${slot}">
                        <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="8" cy="8" r="6.5" stroke="white" stroke-width="1.5"/><path d="M17 17L13 13" stroke="white" stroke-width="1.5" stroke-linecap="round"/></svg>
                    </div>
                    <select class="modal-select" data-action="change->calculator#onMoveTypeFilter" data-side="${side}" data-slot="${slot}">${typeOptions}</select>
                </div>
                <div class="data-table">
                    <div class="data-table__scroll">
                        <div class="data-table__head is-move">
                            <span>Type</span><span>Nom</span><span>Catégorie</span><span>Puissance</span><span>PP</span><span>Précision</span><span></span>
                        </div>
                        ${rows}
                    </div>
                </div>
            </div>
        `;
    }

    onMoveSearch(event) {
        const { side, slot } = event.currentTarget.dataset;
        const typeSelect = this.modalContentTarget.querySelector('.modal-select');
        this.modalContentTarget.innerHTML = this.movePickerHtml(side, slot, event.currentTarget.value, typeSelect ? typeSelect.value : '');
        const input = this.modalContentTarget.querySelector('input[type="search"]');
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
    }

    onMoveTypeFilter(event) {
        const { side, slot } = event.currentTarget.dataset;
        const searchInput = this.modalContentTarget.querySelector('input[type="search"]');
        this.modalContentTarget.innerHTML = this.movePickerHtml(side, slot, searchInput ? searchInput.value : '', event.currentTarget.value);
    }

    pickMove(event) {
        const { side, slot, slug } = event.currentTarget.dataset;
        this.state[side]['m' + slot] = slug;
        this.hideModal();
        this.renderPanel(side);
        this.renderResultsAndVs();
    }

    openNaturePicker(event) {
        event.preventDefault();
        const side = event.currentTarget.dataset.side;
        this.showModal(this.natureModalHtml(side));
    }

    natureModalHtml(side) {
        const statKeys = Object.keys(NATURE_STATS);

        const headCells = statKeys
            .map((s) => `<th class="is-down-header"><span class="stat-arrow stat-arrow--down">▼</span> ${NATURE_STATS[s]}</th>`)
            .join('');

        const bodyRows = statKeys.map((rowKey) => {
            const cells = statKeys.map((colKey) => {
                const natureKey = this.natureGrid[rowKey] ? this.natureGrid[rowKey][colKey] : null;
                if (!natureKey) {
                    return '<td></td>';
                }
                return `<td>
                    <button type="button" data-action="click->calculator#pickNature" data-side="${side}" data-nature="${natureKey}">${esc(this.natures[natureKey].name)}</button>
                </td>`;
            }).join('');
            return `<tr><th class="is-up-header"><span class="stat-arrow stat-arrow--up">▲</span> ${NATURE_STATS[rowKey]}</th>${cells}</tr>`;
        }).join('');

        return `
            <div class="modal-card">
                <a href="#" class="modal-card__close" data-action="click->calculator#closeModal">✕</a>
                <h1 class="modal-card__title">Choix de la nature</h1>
                <p class="modal-card__subtitle">Veuillez choisir la nature du Pokémon</p>
                <div class="nature-grid-wrap">
                    <div>
                        <p class="nature-grid__legend">
                            <span class="stat-arrow stat-arrow--up">▲</span> Statistique augmentée (ligne)
                            &nbsp;·&nbsp;
                            <span class="stat-arrow stat-arrow--down">▼</span> Statistique diminuée (colonne)
                        </p>
                        <table class="nature-grid">
                            <tr><th style="background:transparent;"></th>${headCells}</tr>
                            ${bodyRows}
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    pickNature(event) {
        const { side, nature } = event.currentTarget.dataset;
        this.state[side].nature = nature;
        this.hideModal();
        this.renderPanel(side);
        this.renderResultsAndVs();
    }

    openItemPicker(event) {
        event.preventDefault();
        const side = event.currentTarget.dataset.side;
        this.showModal(this.itemPickerHtml(side, ''));
    }

    itemPickerHtml(side, query) {
        const needle = query.trim().toLowerCase();
        const list = needle ? this.itemCatalog.filter((i) => i.name.toLowerCase().includes(needle)) : this.itemCatalog;

        const noneRow = `
            <div class="data-table__row is-item" data-action="click->calculator#pickItem" data-side="${side}" data-slug="">
                <span class="data-table__cell--dim">•</span>
                <span class="data-table__name">Aucun objet</span>
                <span class="data-table__effect"></span>
                <button type="button" class="btn btn--primary">Retirer</button>
            </div>`;

        const rows = list.map((item) => `
            <div class="data-table__row is-item" data-action="click->calculator#pickItem" data-side="${side}" data-slug="${item.slug}">
                ${item.sprite ? `<img class="data-table__icon" src="${item.sprite}" alt="" loading="lazy">` : '<span class="data-table__cell--dim">•</span>'}
                <span class="data-table__name">${esc(item.name)}</span>
                <span class="data-table__effect">${esc(item.effect)}</span>
                <button type="button" class="btn btn--primary">Ajouter</button>
            </div>
        `).join('') || `<p style="padding:24px; text-align:center; color:var(--color-text-muted);">Aucun objet ne correspond à « ${esc(query)} ».</p>`;

        return `
            <div class="modal-card">
                <a href="#" class="modal-card__close" data-action="click->calculator#closeModal">✕</a>
                <h1 class="modal-card__title">Choix de l'objet</h1>
                <p class="modal-card__subtitle">Veuillez choisir l'objet que le Pokémon doit porter</p>
                <div class="modal-filters">
                    <div class="modal-search">
                        <input type="search" placeholder="Nom de l'objet" value="${esc(query)}" data-action="input->calculator#onItemSearch" data-side="${side}">
                        <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="8" cy="8" r="6.5" stroke="white" stroke-width="1.5"/><path d="M17 17L13 13" stroke="white" stroke-width="1.5" stroke-linecap="round"/></svg>
                    </div>
                </div>
                <div class="data-table">
                    <div class="data-table__scroll">
                        <div class="data-table__head is-item"><span></span><span>Objets</span><span>Effet</span><span></span></div>
                        ${noneRow}${rows}
                    </div>
                </div>
            </div>
        `;
    }

    onItemSearch(event) {
        const side = event.currentTarget.dataset.side;
        this.modalContentTarget.innerHTML = this.itemPickerHtml(side, event.currentTarget.value);
        const input = this.modalContentTarget.querySelector('input[type="search"]');
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);
    }

    pickItem(event) {
        const { side, slug } = event.currentTarget.dataset;
        this.state[side].objet = slug;
        this.hideModal();
        this.renderPanel(side);
        this.renderResultsAndVs();
    }

    // ---- modal plumbing ----

    showModal(html) {
        this.modalContentTarget.innerHTML = html;
        this.backdropTarget.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        const input = this.modalContentTarget.querySelector('input[type="search"]');
        if (input) {
            input.focus();
        }
    }

    showLoading() {
        this.modalContentTarget.innerHTML = `
            <div class="modal-loading">
                <div class="modal-loading__bounce">
                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" class="modal-loading__pokeball" aria-hidden="true">
                        <circle cx="50" cy="50" r="45" fill="#F4F4F8" stroke="#16121F" stroke-width="5" />
                        <path d="M6 50 A44 44 0 0 1 94 50 Z" fill="#FF3D71" stroke="#16121F" stroke-width="5" stroke-linejoin="round" />
                        <rect x="5" y="46" width="90" height="8" fill="#16121F" />
                        <circle cx="50" cy="50" r="15" fill="#16121F" />
                        <circle cx="50" cy="50" r="9" fill="#F4F4F8" stroke="#16121F" stroke-width="4" />
                    </svg>
                </div>
                <p class="modal-loading__text">Chargement…</p>
            </div>
        `;
    }

    hideModal() {
        this.backdropTarget.classList.remove('is-open');
        document.body.style.overflow = '';
        this.modalContentTarget.innerHTML = '';
    }

    closeModal(event) {
        if (event) {
            event.preventDefault();
        }
        this.hideModal();
    }

    onBackdropClick(event) {
        if (event.target === this.backdropTarget) {
            this.hideModal();
        }
    }
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
