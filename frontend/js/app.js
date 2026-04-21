/**
 * Pokédex — JavaScript + Bootstrap + API PHP local
 */
(function () {
  'use strict';

  const API_BASE = (function () {
    const path = window.location.pathname || '/';
    const idx = path.indexOf('/frontend');
    if (idx === -1) {
      return 'api/';
    }
    return path.slice(0, idx) + '/api/';
  })();

  const state = {
    page: 1,
    perPage: 20,
    totalPages: 1,
    total: 0,
    loading: false,
    lastListMeta: null,
    searchActive: false,
    searchResponse: null,
    /** @type {Set<number>} */
    favoritePokemonIds: new Set(),
  };

  let searchDebounce = null;
  let paginationInputDebounce = null;
  /** Invalida respostas antigas quando uma nova lista ou busca é disparada. */
  let navToken = 0;

  const els = {
    grid: document.getElementById('pokemonGrid'),
    search: document.getElementById('searchInput'),
    regionFilter: document.getElementById('regionFilter'),
    typeFilter: document.getElementById('typeFilter'),
    paginationNav: document.getElementById('paginationNav'),
    listMeta: document.getElementById('listMeta'),
    loader: document.getElementById('globalLoader'),
    modalEl: document.getElementById('pokemonModal'),
    modalBody: document.getElementById('pokemonModalBody'),
    toastEl: document.getElementById('appToast'),
    favoritesList: document.getElementById('favoritesList'),
    historyList: document.getElementById('historyList'),
    recentList: document.getElementById('recentList'),
    achievementsList: document.getElementById('achievementsList'),
    btnFav: document.getElementById('btnFavorite'),
    btnTheme: document.getElementById('btnTheme'),
    btnSound: document.getElementById('btnSound'),
    btnRandom: document.getElementById('btnRandom'),
    btnSharePokemon: document.getElementById('btnSharePokemon'),
    btnCompare: document.getElementById('btnCompare'),
    btnExportFav: document.getElementById('btnExportFav'),
    importFavFile: document.getElementById('importFavFile'),
    compareModal: document.getElementById('compareModal'),
    compareA: document.getElementById('compareA'),
    compareB: document.getElementById('compareB'),
    btnRunCompare: document.getElementById('btnRunCompare'),
    compareResult: document.getElementById('compareResult'),
  };

  const modal = els.modalEl ? new bootstrap.Modal(els.modalEl) : null;
  const compareModalBootstrap = els.compareModal ? new bootstrap.Modal(els.compareModal) : null;
  const toast = els.toastEl ? new bootstrap.Toast(els.toastEl, { delay: 3200 }) : null;

  let currentDetail = null;

  const LS_THEME = 'pokedex_theme';
  const LS_SOUND = 'pokedex_sound';
  const LS_RECENT = 'pokedex_recent_v1';
  const LS_STATS = 'pokedex_stats_v1';

  function skeletonGridHtml() {
    const n = Math.min(20, Math.max(1, state.perPage || 20));
    let html = '';
    for (let i = 0; i < n; i++) {
      html += `<div class="col"><div class="card pokemon-skeleton h-100 shadow-sm border-0"><div class="skeleton-img"></div><div class="card-body py-2 px-2"><div class="skeleton-line skeleton-num"></div><div class="skeleton-line skeleton-name"></div></div></div></div>`;
    }
    return html;
  }

  function syncTypeFilterEnabled() {
    const tf = els.typeFilter;
    if (!tf) return;
    const reg = els.regionFilter && els.regionFilter.value;
    tf.disabled = !!reg;
    if (reg) tf.value = '';
  }

  function applyTheme(dark) {
    const root = document.documentElement;
    if (dark) {
      root.setAttribute('data-theme', 'dark');
      try {
        localStorage.setItem(LS_THEME, 'dark');
      } catch (e) {}
    } else {
      root.removeAttribute('data-theme');
      try {
        localStorage.setItem(LS_THEME, 'light');
      } catch (e) {}
    }
    if (els.btnTheme) {
      const i = els.btnTheme.querySelector('i');
      if (i) i.className = dark ? 'bi bi-sun-fill' : 'bi bi-moon-stars';
    }
  }

  function initThemeToggle() {
    if (!els.btnTheme) return;
    const syncIcon = () => {
      const dark = document.documentElement.getAttribute('data-theme') === 'dark';
      const i = els.btnTheme.querySelector('i');
      if (i) i.className = dark ? 'bi bi-sun-fill' : 'bi bi-moon-stars';
    };
    syncIcon();
    els.btnTheme.addEventListener('click', () => {
      applyTheme(document.documentElement.getAttribute('data-theme') !== 'dark');
    });
  }

  function initSoundToggle() {
    if (!els.btnSound) return;
    let on = false;
    try {
      on = localStorage.getItem(LS_SOUND) === '1';
    } catch (e) {}
    const syncIcon = () => {
      const i = els.btnSound.querySelector('i');
      if (i) i.className = on ? 'bi bi-volume-up-fill' : 'bi bi-volume-mute-fill';
    };
    syncIcon();
    els.btnSound.addEventListener('click', () => {
      on = !on;
      try {
        localStorage.setItem(LS_SOUND, on ? '1' : '0');
      } catch (e) {}
      syncIcon();
      showToast(on ? 'Som ativado' : 'Som desativado');
    });
  }

  function playFavoriteBlip() {
    try {
      if (localStorage.getItem(LS_SOUND) !== '1') return;
    } catch (e) {
      return;
    }
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const o = ctx.createOscillator();
      const g = ctx.createGain();
      o.type = 'sine';
      o.frequency.value = 880;
      g.gain.value = 0.04;
      o.connect(g);
      g.connect(ctx.destination);
      o.start();
      setTimeout(() => {
        o.stop();
        ctx.close();
      }, 90);
    } catch (e) {}
  }

  function readStats() {
    try {
      const raw = localStorage.getItem(LS_STATS);
      const o = raw ? JSON.parse(raw) : {};
      return {
        views: parseInt(String(o.views || '0'), 10) || 0,
        favAdds: parseInt(String(o.favAdds || '0'), 10) || 0,
      };
    } catch (e) {
      return { views: 0, favAdds: 0 };
    }
  }

  function writeStats(s) {
    try {
      localStorage.setItem(LS_STATS, JSON.stringify(s));
    } catch (e) {}
  }

  function bumpAchievement(kind) {
    const s = readStats();
    if (kind === 'views') s.views += 1;
    if (kind === 'favadd') s.favAdds += 1;
    writeStats(s);
    renderAchievements();
  }

  function renderAchievements() {
    if (!els.achievementsList) return;
    const s = readStats();
    const milestones = [
      { k: 'views', n: 1, label: 'Ver 1 detalhe' },
      { k: 'views', n: 5, label: 'Ver 5 detalhes' },
      { k: 'views', n: 25, label: 'Ver 25 detalhes' },
      { k: 'favadd', n: 1, label: '1 favorito adicionado' },
      { k: 'favadd', n: 5, label: '5 favoritos' },
    ];
    const lines = milestones.map((m) => {
      const v = m.k === 'views' ? s.views : s.favAdds;
      const ok = v >= m.n;
      return `<li class="list-group-item py-2 d-flex align-items-center gap-2"><span class="${ok ? 'text-success' : 'text-muted'}">${ok ? '✓' : '○'}</span><span>${escapeHtml(m.label)}</span></li>`;
    });
    els.achievementsList.innerHTML = lines.join('');
  }

  function readRecent() {
    try {
      const raw = localStorage.getItem(LS_RECENT);
      const arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr) ? arr : [];
    } catch (e) {
      return [];
    }
  }

  function writeRecent(arr) {
    try {
      localStorage.setItem(LS_RECENT, JSON.stringify(arr.slice(0, 12)));
    } catch (e) {}
  }

  function recordRecentView(pokemon) {
    if (!pokemon || !pokemon.id) return;
    const id = parseInt(String(pokemon.id), 10);
    const name = String(pokemon.name_display || pokemon.name || '').trim() || String(pokemon.name);
    let list = readRecent().filter((x) => x && x.id !== id);
    list.unshift({ id, name });
    writeRecent(list);
    renderRecentList();
  }

  function renderRecentList() {
    if (!els.recentList) return;
    const list = readRecent();
    if (!list.length) {
      els.recentList.innerHTML = '<li class="list-group-item small text-muted">Abra um Pokémon para preencher.</li>';
      return;
    }
    els.recentList.innerHTML = list
      .map(
        (r) => `
      <li class="list-group-item py-2">
        <a href="#" class="small history-chip text-capitalize" data-open-recent="${escapeHtml(String(r.name))}">#${r.id} ${escapeHtml(String(r.name))}</a>
      </li>`
      )
      .join('');
    els.recentList.querySelectorAll('[data-open-recent]').forEach((a) => {
      a.addEventListener('click', (ev) => {
        ev.preventDefault();
        openPokemon(a.getAttribute('data-open-recent'));
      });
    });
  }

  function syncPokemonUrlQuery(identifier) {
    try {
      const u = new URL(window.location.href);
      u.searchParams.set('pokemon', String(identifier).toLowerCase().trim());
      history.replaceState({}, '', u.pathname + (u.search ? u.search : ''));
    } catch (e) {}
  }

  function clearPokemonUrlQuery() {
    try {
      const u = new URL(window.location.href);
      u.searchParams.delete('pokemon');
      const q = u.searchParams.toString();
      history.replaceState({}, '', u.pathname + (q ? '?' + q : ''));
    } catch (e) {}
  }

  async function openRandomPokemon() {
    let max = state.total;
    if (!max) {
      try {
        const j = await fetchJson(API_BASE + 'list.php?page=1&limit=1');
        max = parseInt(String((j.data || {}).total || '0'), 10) || 1025;
      } catch (e) {
        max = 1025;
      }
    }
    const id = 1 + Math.floor(Math.random() * Math.max(1, max));
    openPokemon(String(id));
  }

  async function exportFavoritesJson() {
    try {
      const json = await fetchJson(API_BASE + 'favorites.php');
      if (json.db === false) {
        showToast('Configure o banco para usar favoritos.', true);
        return;
      }
      const blob = new Blob([JSON.stringify(json.data || [], null, 2)], { type: 'application/json' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'pokedex-favoritos.json';
      a.click();
      URL.revokeObjectURL(a.href);
      showToast('Exportação concluída.');
    } catch (e) {
      showToast(e.message || 'Falha ao exportar', true);
    }
  }

  async function importFavoritesFromFile(file) {
    const text = await file.text();
    const rows = JSON.parse(text);
    if (!Array.isArray(rows)) throw new Error('JSON inválido');
    let ok = 0;
    for (const r of rows) {
      const id = r.pokemon_id != null ? parseInt(String(r.pokemon_id), 10) : parseInt(String(r.id || '0'), 10);
      const nome = (r.nome != null ? String(r.nome) : r.name != null ? String(r.name) : '').trim();
      if (!id || !nome) continue;
      try {
        await fetchJson(API_BASE + 'favorites.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ pokemon_id: id, nome }),
        });
        ok++;
      } catch (e) {}
    }
    await refreshFavorites();
    showToast(ok ? `Importados ${ok} favorito(s).` : 'Nenhum favorito novo importado.');
  }

  function statValueNum(v) {
    if (typeof v === 'number' && Number.isFinite(v)) return v;
    const n = parseInt(String(v), 10);
    return Number.isFinite(n) ? n : null;
  }

  /** Classes por célula: [classeA, classeB] para destacar vencedor da stat. */
  function compareStatHighlightClasses(va, vb) {
    const na = statValueNum(va);
    const nb = statValueNum(vb);
    if (na === null || nb === null) return ['compare-stat-na', 'compare-stat-na'];
    if (na > nb) return ['compare-stat-winner', 'compare-stat-loser'];
    if (nb > na) return ['compare-stat-loser', 'compare-stat-winner'];
    return ['compare-stat-tie', 'compare-stat-tie'];
  }

  function renderCompareBoard(pA, pB) {
    const labels = ['PS', 'Ataque', 'Defesa', 'At. Esp.', 'Def. Esp.', 'Velocidade'];
    const ids = ['hp', 'attack', 'defense', 'special-attack', 'special-defense', 'speed'];
    const byId = (stats, id) => {
      const row = (stats || []).find((s) => s.id === id);
      return row ? Number(row.base) : '—';
    };
    const na = escapeHtml(pA.name_display || pA.name);
    const nb = escapeHtml(pB.name_display || pB.name);
    const imgA = escapeHtml(pA.image || '');
    const imgB = escapeHtml(pB.image || '');
    let winsA = 0;
    let winsB = 0;
    let ties = 0;
    const cells = [];
    for (let i = 0; i < ids.length; i++) {
      const va = byId(pA.stats, ids[i]);
      const vb = byId(pB.stats, ids[i]);
      const [cA, cB] = compareStatHighlightClasses(va, vb);
      const nA = statValueNum(va);
      const nB = statValueNum(vb);
      if (nA !== null && nB !== null) {
        if (nA > nB) winsA += 1;
        else if (nB > nA) winsB += 1;
        else ties += 1;
      }
      const ariaA =
        nA !== null && nB !== null
          ? nA > nB
            ? 'Maior que o oponente nesta stat'
            : nA < nB
              ? 'Menor que o oponente nesta stat'
              : 'Empate com o oponente nesta stat'
          : 'Valor da stat';
      const ariaB =
        nA !== null && nB !== null
          ? nB > nA
            ? 'Maior que o oponente nesta stat'
            : nB < nA
              ? 'Menor que o oponente nesta stat'
              : 'Empate com o oponente nesta stat'
          : 'Valor da stat';
      cells.push(`
        <div class="compare-stat-name">${escapeHtml(labels[i])}</div>
        <div class="compare-stat-val ${cA}" aria-label="${ariaA}">${va}</div>
        <div class="compare-stat-val ${cB}" aria-label="${ariaB}">${vb}</div>`);
    }
    const shortA = escapeHtml((pA.name_display || pA.name || 'A').split(' ')[0]);
    const shortB = escapeHtml((pB.name_display || pB.name || 'B').split(' ')[0]);
    const summaryParts = [];
    if (winsA || winsB || ties) {
      summaryParts.push(
        `<strong class="compare-summary-name">${shortA}</strong> <span class="text-muted">lidera em</span> <strong class="compare-summary-name">${winsA}</strong> <span class="text-muted">stat(s)</span>`
      );
      summaryParts.push(
        `<strong class="compare-summary-name">${shortB}</strong> <span class="text-muted">lidera em</span> <strong class="compare-summary-name">${winsB}</strong> <span class="text-muted">stat(s)</span>`
      );
      if (ties) summaryParts.push(`<span class="text-muted">empates:</span> <strong class="compare-summary-name">${ties}</strong>`);
    }
    const summary =
      summaryParts.length > 0
        ? `<p class="compare-summary mb-0 mt-3"><span class="compare-summary-label">Resumo</span> · ${summaryParts.join(' · ')}. <span class="compare-summary-hint">Verde = maior na linha · Vermelho = menor.</span></p>`
        : '';

    return `
      <div class="compare-board" role="table" aria-label="Comparação de stats base">
        <div class="compare-board-corner" aria-hidden="true"></div>
        <div class="compare-board-poke">
          <img src="${imgA}" class="compare-head-img mb-2" alt="" onerror="this.onerror=null;this.src='${pokemonSpriteUrl(pA.id)}'">
          <div class="compare-poke-name">${na} <span class="text-muted fw-normal">#${String(pA.id).padStart(4, '0')}</span></div>
          <div class="compare-poke-types mt-1">${renderTypeBadges(pA.types)}</div>
        </div>
        <div class="compare-board-poke">
          <img src="${imgB}" class="compare-head-img mb-2" alt="" onerror="this.onerror=null;this.src='${pokemonSpriteUrl(pB.id)}'">
          <div class="compare-poke-name">${nb} <span class="text-muted fw-normal">#${String(pB.id).padStart(4, '0')}</span></div>
          <div class="compare-poke-types mt-1">${renderTypeBadges(pB.types)}</div>
        </div>
        ${cells.join('')}
      </div>
      ${summary}`;
  }

  async function runCompare() {
    const a = (els.compareA && els.compareA.value.trim()) || '';
    const b = (els.compareB && els.compareB.value.trim()) || '';
    if (!a || !b) {
      showToast('Informe os dois Pokémon.', true);
      return;
    }
    if (!els.compareResult) return;
    els.compareResult.innerHTML = '<p class="text-muted mb-0">Carregando…</p>';
    try {
      const qa = /^\d+$/.test(a) ? 'id=' + encodeURIComponent(a) : 'name=' + encodeURIComponent(a.toLowerCase());
      const qb = /^\d+$/.test(b) ? 'id=' + encodeURIComponent(b) : 'name=' + encodeURIComponent(b.toLowerCase());
      const [ja, jb] = await Promise.all([
        fetchJson(API_BASE + 'pokemon.php?' + qa),
        fetchJson(API_BASE + 'pokemon.php?' + qb),
      ]);
      const pa = ja.data && ja.data.pokemon ? ja.data.pokemon : null;
      const pb = jb.data && jb.data.pokemon ? jb.data.pokemon : null;
      if (!pa || !pb) throw new Error('Resposta incompleta');
      els.compareResult.innerHTML = renderCompareBoard(pa, pb);
    } catch (e) {
      els.compareResult.innerHTML = `<p class="text-danger mb-0">${escapeHtml(e.message || 'Erro')}</p>`;
    }
  }

  function registerServiceWorkerSafe() {
    if (!('serviceWorker' in navigator)) return;
    navigator.serviceWorker.register('sw.js').catch(() => {});
  }

  function bindGlobalShortcuts() {
    document.addEventListener('keydown', (e) => {
      const tag = (e.target && e.target.tagName) || '';
      const inField = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
      if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
        if (inField && e.target !== els.search) return;
        if (e.target === els.search) return;
        e.preventDefault();
        els.search && els.search.focus();
      }
      if (e.key === 'Escape') {
        if (els.modalEl && els.modalEl.classList.contains('show') && modal) modal.hide();
        if (els.compareModal && els.compareModal.classList.contains('show') && compareModalBootstrap) {
          compareModalBootstrap.hide();
        }
      }
    });
  }

  function showLoader(show) {
    if (!els.loader) return;
    els.loader.classList.toggle('d-none', !show);
    els.loader.classList.toggle('d-flex', !!show);
  }

  function showToast(message, isError) {
    if (!els.toastEl || !toast) {
      alert(message);
      return;
    }
    const t = els.toastEl;
    t.classList.toggle('text-bg-danger', !!isError);
    t.classList.toggle('text-bg-dark', !isError);
    t.querySelector('.toast-body').textContent = message;
    toast.show();
  }

  async function fetchJson(url, options = {}) {
    const res = await fetch(url, {
      ...options,
      headers: {
        Accept: 'application/json',
        ...(options.headers || {}),
      },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.success === false) {
      const err = data.error || res.statusText || 'Erro na requisição';
      throw new Error(err);
    }
    return data;
  }

  /** Classes Bootstrap 5 (text-bg-*) para tipos. */
  function typeBadgeClass(type) {
    const map = {
      normal: 'text-bg-secondary',
      fire: 'text-bg-danger',
      water: 'text-bg-primary',
      grass: 'text-bg-success',
      electric: 'text-bg-warning',
      ice: 'text-bg-info',
      fighting: 'text-bg-dark',
      poison: 'text-bg-dark',
      ground: 'text-bg-warning',
      flying: 'text-bg-info',
      psychic: 'text-bg-info',
      bug: 'text-bg-success',
      rock: 'text-bg-secondary',
      ghost: 'text-bg-dark',
      dragon: 'text-bg-danger',
      dark: 'text-bg-dark',
      steel: 'text-bg-secondary',
      fairy: 'text-bg-danger',
    };
    return map[type] || 'text-bg-secondary';
  }

  function renderTypeBadges(types) {
    return (types || [])
      .map((t) => {
        const slug = typeof t === 'string' ? t : t.slug || '';
        const label = typeof t === 'string' ? t : t.label || slug;
        return `<span class="badge type-badge ${typeBadgeClass(slug)} me-1">${escapeHtml(label)}</span>`;
      })
      .join('');
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function pokemonSpriteUrl(id) {
    const n = parseInt(String(id), 10);
    return (
      'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/' +
      (Number.isFinite(n) ? n : 0) +
      '.png'
    );
  }

  function cardHtml(item) {
    const name = escapeHtml(item.name);
    const num = String(item.id).padStart(4, '0');
    return `
      <div class="col">
        <div class="card pokemon-card h-100 shadow-sm" data-name="${name}" data-id="${item.id}" role="button" tabindex="0" aria-label="Ver detalhes de ${name}">
          <img src="${escapeHtml(item.image)}" class="card-img-top" alt="Arte de ${name}" loading="lazy"
            onerror="this.onerror=null;this.src='https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${item.id}.png'">
          <div class="card-body py-2 px-2 text-center">
            <div class="poke-number">#${num}</div>
            <div class="fw-semibold text-capitalize small">${name}</div>
          </div>
        </div>
      </div>`;
  }

  function updateListMeta(data) {
    if (!els.listMeta) return;
    if (state.searchActive && state.searchResponse) {
      const s = state.searchResponse;
      const more =
        s.total > s.itemsShown
          ? ` · mostrando ${s.itemsShown} de ${s.total} (limite da busca)`
          : '';
      els.listMeta.innerHTML = `<i class="bi bi-search" aria-hidden="true"></i> Busca “${escapeHtml(
        String(s.query)
      )}” · ${s.total} resultado(s)${more} · ${escapeHtml(String(s.scope_label))}`;
      return;
    }
    const d = data || state.lastListMeta || {};
    state.lastListMeta = d;
    const regLabel = d.region_label ? String(d.region_label) : 'Pokédex Nacional';
    const t = state.total > 0 ? `${state.total} Pokémon` : 'Lista';
    const typeExtra = d.type_label ? ` · Tipo: ${escapeHtml(String(d.type_label))}` : '';
    els.listMeta.innerHTML = `<i class="bi bi-bookmarks-fill" aria-hidden="true"></i> Pág. ${state.page}/${
      state.totalPages
    } · ${escapeHtml(regLabel)} · ${escapeHtml(t)}${typeExtra}`;
  }

  function applyPaginationPageInput(pageInput, totalPages, currentPage) {
    let n = parseInt(String(pageInput.value).trim(), 10);
    if (!Number.isFinite(n)) {
      pageInput.value = String(currentPage);
      return;
    }
    n = Math.min(Math.max(1, n), totalPages);
    pageInput.value = String(n);
    if (n !== currentPage) {
      loadListPage(n);
    }
  }

  function renderPagination() {
    if (!els.paginationNav) return;
    const totalPages = Math.max(1, state.totalPages);
    const page = Math.min(Math.max(1, state.page), totalPages);
    state.page = page;

    const firstDisabled = page <= 1 ? 'disabled' : '';
    const prevDisabled = page <= 1 ? 'disabled' : '';
    const nextDisabled = page >= totalPages ? 'disabled' : '';
    const lastDisabled = page >= totalPages ? 'disabled' : '';
    const prevPage = page - 1;
    const nextPage = page + 1;

    const html = `
      <ul class="pagination pagination-pokedex flex-wrap justify-content-center align-items-center gap-1 mb-0" role="navigation" aria-label="Paginação do catálogo">
        <li class="page-item ${firstDisabled}">
          <a class="page-link d-inline-flex align-items-center justify-content-center px-2" href="#" data-nav-page="1" title="Primeira página" aria-label="Primeira página" ${firstDisabled ? 'tabindex="-1" aria-disabled="true"' : ''}><i class="bi bi-chevron-bar-left" aria-hidden="true"></i></a>
        </li>
        <li class="page-item ${prevDisabled}">
          <a class="page-link d-inline-flex align-items-center gap-1" href="#" data-nav-page="${prevPage}" aria-label="Página anterior" ${prevDisabled ? 'tabindex="-1" aria-disabled="true"' : ''}><i class="bi bi-chevron-left" aria-hidden="true"></i> Anterior</a>
        </li>
        <li class="page-item pagination-page-go">
          <div class="page-link border-0 bg-transparent d-flex align-items-center justify-content-center gap-1 py-1 px-2">
            <label for="paginationPageInput" class="visually-hidden">Ir para a página</label>
            <input type="number" inputmode="numeric" min="1" max="${totalPages}" class="form-control form-control-sm pagination-page-input" id="paginationPageInput" value="${page}" autocomplete="off" aria-label="Número da página" />
            <span class="text-secondary fw-semibold small text-nowrap" aria-hidden="true">/ ${totalPages}</span>
          </div>
        </li>
        <li class="page-item ${nextDisabled}">
          <a class="page-link d-inline-flex align-items-center gap-1" href="#" data-nav-page="${nextPage}" aria-label="Próxima página" ${nextDisabled ? 'tabindex="-1" aria-disabled="true"' : ''}>Próximo <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
        </li>
        <li class="page-item ${lastDisabled}">
          <a class="page-link d-inline-flex align-items-center justify-content-center px-2" href="#" data-nav-page="${totalPages}" title="Última página" aria-label="Última página" ${lastDisabled ? 'tabindex="-1" aria-disabled="true"' : ''}><i class="bi bi-chevron-bar-right" aria-hidden="true"></i></a>
        </li>
      </ul>`;
    els.paginationNav.innerHTML = html;

    els.paginationNav.querySelectorAll('[data-nav-page]').forEach((a) => {
      a.addEventListener('click', (e) => {
        e.preventDefault();
        const li = a.closest('.page-item');
        if (li && li.classList.contains('disabled')) return;
        const p = parseInt(a.getAttribute('data-nav-page'), 10);
        if (p >= 1 && p <= totalPages) loadListPage(p);
      });
    });

    const pageInput = document.getElementById('paginationPageInput');
    if (pageInput) {
      const apply = () => applyPaginationPageInput(pageInput, totalPages, page);
      pageInput.addEventListener('change', () => {
        clearTimeout(paginationInputDebounce);
        paginationInputDebounce = null;
        apply();
      });
      pageInput.addEventListener('input', () => {
        clearTimeout(paginationInputDebounce);
        paginationInputDebounce = setTimeout(() => {
          paginationInputDebounce = null;
          apply();
        }, 380);
      });
      pageInput.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        clearTimeout(paginationInputDebounce);
        paginationInputDebounce = null;
        apply();
      });
    }
  }

  function renderSearchToolbar() {
    if (!els.paginationNav) return;
    els.paginationNav.innerHTML = `
      <div class="text-center py-1">
        <button type="button" class="btn btn-sm btn-outline-light rounded-pill" id="btnClearSearch">
          <i class="bi bi-x-lg me-1" aria-hidden="true"></i>Limpar busca
        </button>
      </div>`;
    const btn = document.getElementById('btnClearSearch');
    if (btn) {
      btn.addEventListener('click', () => {
        if (els.search) els.search.value = '';
        clearSearchAndReload();
      });
    }
  }

  async function clearSearchAndReload() {
    clearTimeout(searchDebounce);
    await loadListPage(1);
  }

  async function runGlobalSearch(rawQuery) {
    const q = (rawQuery || '').trim();
    if (!q) {
      await clearSearchAndReload();
      return;
    }
    if (q.length < 2 && !/^\d+$/.test(q)) {
      return;
    }
    const token = ++navToken;
    state.loading = true;
    showLoader(true);
    try {
      let url = API_BASE + 'search.php?q=' + encodeURIComponent(q) + '&limit=80';
      const region = els.regionFilter && els.regionFilter.value ? els.regionFilter.value.trim() : '';
      if (region) url += '&region=' + encodeURIComponent(region);
      const json = await fetchJson(url);
      if (token !== navToken) return;
      const d = json.data || {};
      const items = d.items || [];
      state.searchActive = true;
      const total = d.total != null ? parseInt(String(d.total), 10) : 0;
      state.searchResponse = {
        query: d.query != null ? String(d.query) : q,
        total,
        scope_label: d.scope_label != null ? String(d.scope_label) : '',
        itemsShown: items.length,
      };
      els.grid.innerHTML = items.length
        ? items.map(cardHtml).join('')
        : `<div class="col-12 py-5 text-center">
            <div class="fs-1 text-body-secondary mb-2" aria-hidden="true"><i class="bi bi-search"></i></div>
            <p class="text-secondary mb-0 fw-medium">Nenhum Pokémon encontrado para esse termo.</p>
            <p class="small text-muted mt-1 mb-0">Tente outro nome, ID ou limpe a busca.</p>
          </div>`;
      updateListMeta();
      renderSearchToolbar();
      window.scrollTo({ top: els.grid.offsetTop ? els.grid.offsetTop - 24 : 0, behavior: 'smooth' });
    } catch (e) {
      if (token === navToken) {
        showToast(e.message || 'Falha na busca', true);
      }
    } finally {
      if (token === navToken) {
        state.loading = false;
        showLoader(false);
      }
    }
  }

  async function loadListPage(page) {
    const token = ++navToken;
    state.searchActive = false;
    state.searchResponse = null;
    state.loading = true;
    showLoader(true);
    if (els.grid) els.grid.innerHTML = skeletonGridHtml();
    try {
      let url =
        API_BASE +
        'list.php?page=' +
        encodeURIComponent(String(page)) +
        '&limit=' +
        encodeURIComponent(String(state.perPage));
      const region = els.regionFilter && els.regionFilter.value ? els.regionFilter.value.trim() : '';
      if (region) {
        url += '&region=' + encodeURIComponent(region);
      }
      const typeSlug = els.typeFilter && !els.typeFilter.disabled && els.typeFilter.value ? els.typeFilter.value.trim() : '';
      if (typeSlug) {
        url += '&type=' + encodeURIComponent(typeSlug);
      }
      const json = await fetchJson(url);
      if (token !== navToken) return;
      const d = json.data || {};
      const items = d.items || [];
      state.page = d.page != null ? parseInt(String(d.page), 10) : page;
      state.perPage = d.per_page != null ? parseInt(String(d.per_page), 10) : state.perPage;
      state.total = d.total != null ? parseInt(String(d.total), 10) : 0;
      state.totalPages = d.total_pages != null ? Math.max(1, parseInt(String(d.total_pages), 10)) : 1;

      els.grid.innerHTML = items.length
        ? items.map(cardHtml).join('')
        : `<div class="col-12 py-5 text-center">
            <div class="fs-1 text-body-secondary mb-2" aria-hidden="true"><i class="bi bi-inbox"></i></div>
            <p class="text-secondary mb-0 fw-medium">Nenhum Pokémon nesta página.</p>
            <p class="small text-muted mt-1 mb-0">Tente outra página ou outro filtro de região.</p>
          </div>`;

      updateListMeta(d);
      renderPagination();
      window.scrollTo({ top: els.grid.offsetTop ? els.grid.offsetTop - 24 : 0, behavior: 'smooth' });
    } catch (e) {
      if (token === navToken) {
        showToast(e.message || 'Falha ao carregar lista', true);
      }
    } finally {
      if (token === navToken) {
        state.loading = false;
        showLoader(false);
      }
    }
  }

  async function openPokemon(identifier) {
    showLoader(true);
    try {
      const q = /^\d+$/.test(String(identifier).trim())
        ? 'id=' + encodeURIComponent(String(identifier).trim())
        : 'name=' + encodeURIComponent(String(identifier).trim().toLowerCase());
      const json = await fetchJson(API_BASE + 'pokemon.php?' + q);
      currentDetail = json.data;
      els.modalBody.innerHTML = renderDetail(currentDetail);
      wireModalInteractions();
      await syncFavoriteIdsFromApi();
      updateFavoriteButton();
      if (currentDetail && currentDetail.pokemon) {
        recordRecentView(currentDetail.pokemon);
        syncPokemonUrlQuery(currentDetail.pokemon.name || String(currentDetail.pokemon.id));
        bumpAchievement('views');
      }
      if (els.btnSharePokemon) els.btnSharePokemon.classList.remove('d-none');
      modal.show();
      refreshHistory();
    } catch (e) {
      showToast(e.message || 'Pokémon não encontrado', true);
    } finally {
      showLoader(false);
    }
  }

  function renderEvolutionStages(stages) {
    if (!stages || !stages.length) {
      return '<p class="text-muted small mb-0">Este Pokémon não possui evoluções registradas na cadeia padrão.</p>';
    }
    const parts = [];
    for (let gi = 0; gi < stages.length; gi++) {
      const group = stages[gi];
      const cards = group
        .map((spec) => {
          const sid = spec.species_id || 0;
          const img =
            'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/' +
            sid +
            '.png';
          return `
            <div class="card evolution-card" data-open-name="${escapeHtml(spec.name)}">
              <img src="${img}" class="card-img-top" alt="${escapeHtml(spec.name)}" loading="lazy"
                onerror="this.onerror=null;this.src='https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${sid}.png'">
              <div class="card-body p-2 text-center">
                <span class="small fw-semibold">${escapeHtml(spec.display_name || spec.name)}</span>
              </div>
            </div>`;
        })
        .join('');
      parts.push(`<div class="evolution-stage-row">${cards}</div>`);
      if (gi < stages.length - 1) {
        parts.push(
          '<div class="evolution-arrow" role="presentation" aria-hidden="true"><i class="bi bi-chevron-down"></i></div>'
        );
      }
    }
    return `<div class="evolution-flow evolution-flow-vertical">${parts.join('')}</div>`;
  }

  function renderDetail(data) {
    const p = data.pokemon;
    const h = p.height / 10;
    const w = p.weight / 10;
    const titleName = p.name_display || p.name;
    const abilities = (p.abilities || [])
      .map((a) => {
        const label = typeof a === 'object' && a !== null ? a.label || a.slug || a.name || '' : String(a);
        const hidden = a.is_hidden ? ' <span class="badge bg-secondary">Oculta</span>' : '';
        return `<li>${escapeHtml(String(label))}${hidden}</li>`;
      })
      .join('');

    const genusHtml = p.genus
      ? `<p class="text-muted small mb-2">${escapeHtml(p.genus)}</p>`
      : '';

    let flavorHtml = '';
    if (p.flavor_text) {
      const langNote =
        p.flavor_language && !String(p.flavor_language).startsWith('pt')
          ? `<span class="text-muted small"> (texto da Pokédex: ${escapeHtml(String(p.flavor_language))})</span>`
          : '';
      flavorHtml = `<h6 class="mt-3">Pokédex</h6><p class="small fst-italic border-start border-3 ps-2 mb-1">${escapeHtml(
        p.flavor_text
      )}</p>${langNote}`;
    }

    const statsRows = (p.stats || [])
      .map(
        (s) =>
          `<tr><td>${escapeHtml(s.label)}</td><td class="text-end fw-semibold">${Number(s.base)}</td></tr>`
      )
      .join('');
    const statsHtml =
      statsRows !== ''
        ? `<h6 class="mt-3">Status base</h6><div class="table-responsive"><table class="table table-sm table-borderless mb-0"><tbody>${statsRows}</tbody></table></div>`
        : '';

    let triviaHtml = '';
    const bits = [];
    if (p.habitat_label) bits.push(`Habitat típico: <strong>${escapeHtml(String(p.habitat_label))}</strong>`);
    if (p.capture_rate != null && p.capture_rate !== '') bits.push(`Taxa de captura: <strong>${escapeHtml(String(p.capture_rate))}</strong> (255 = mais difícil)`);
    if (p.base_happiness != null && p.base_happiness !== '') bits.push(`Felicidade base: <strong>${escapeHtml(String(p.base_happiness))}</strong>`);
    if (p.is_baby) bits.push('<span class="badge bg-info text-dark">Bebé</span>');
    if (p.is_legendary) bits.push('<span class="badge bg-warning text-dark">Lendário</span>');
    if (p.is_mythical) bits.push('<span class="badge bg-danger">Mítico</span>');
    if (bits.length) {
      triviaHtml = `<h6 class="mt-3">Curiosidades</h6><p class="small trivia-box mb-0">${bits.join(' · ')}</p>`;
    }

    return `
      <div class="row g-3">
        <div class="col-md-5 text-center">
          <img src="${escapeHtml(p.image)}" class="img-fluid modal-pokemon-img mb-2" alt="${escapeHtml(titleName)}"
            onerror="this.onerror=null;this.src='https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/${p.id}.png'">
          <div>${renderTypeBadges(p.types)}</div>
        </div>
        <div class="col-md-7">
          <h4 class="mb-1">${escapeHtml(titleName)} <span class="text-muted fs-6">#${String(p.id).padStart(4, '0')}</span></h4>
          ${genusHtml}
          <p class="mb-2"><strong>Altura:</strong> ${h} m &nbsp;|&nbsp; <strong>Peso:</strong> ${w} kg</p>
          ${triviaHtml}
          <h6 class="mt-3">Habilidades</h6>
          <ul class="mb-3">${abilities || '<li class="text-muted">—</li>'}</ul>
          ${statsHtml}
          ${flavorHtml}
          <h6 class="mt-3">Evoluções</h6>
          ${renderEvolutionStages(data.evolution_stages)}
        </div>
      </div>`;
  }

  function wireModalInteractions() {
    els.modalBody.querySelectorAll('[data-open-name]').forEach((node) => {
      node.addEventListener('click', () => {
        const n = node.getAttribute('data-open-name');
        if (n) openPokemon(n);
      });
    });
  }

  function onGridClick(e) {
    const card = e.target.closest('.pokemon-card');
    if (!card) return;
    const name = card.getAttribute('data-name');
    if (name) openPokemon(name);
  }

  function onGridKeydown(e) {
    const card = e.target.closest('.pokemon-card');
    if (!card) return;
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      const name = card.getAttribute('data-name');
      if (name) openPokemon(name);
    }
  }

  /** Busca global na API (debounce). */
  function onSearchInput() {
    if (!els.search) return;
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
      const raw = (els.search.value || '').trim();
      if (!raw) {
        clearSearchAndReload();
        return;
      }
      runGlobalSearch(raw);
    }, 420);
  }

  function onSearchKeydown(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      const raw = (els.search.value || '').trim();
      if (raw.length) openPokemon(raw);
    }
  }

  async function populateRegionFilter() {
    const sel = els.regionFilter;
    if (!sel) return;
    try {
      const json = await fetchJson(API_BASE + 'regions.php');
      const rows = json.data || [];
      sel.innerHTML = '<option value="">Todas — Pokédex Nacional</option>';
      rows.forEach((r) => {
        const opt = document.createElement('option');
        opt.value = r.name;
        opt.textContent = r.label || r.name;
        sel.appendChild(opt);
      });
    } catch (e) {
      showToast(e.message || 'Não foi possível carregar regiões.', true);
    }
  }

  async function syncFavoriteIdsFromApi() {
    try {
      const json = await fetchJson(API_BASE + 'favorites.php');
      if (json.db === false) {
        state.favoritePokemonIds = new Set();
        return;
      }
      const rows = json.data || [];
      state.favoritePokemonIds = new Set(rows.map((r) => parseInt(String(r.pokemon_id), 10)));
    } catch {
      /* mantém o Set anterior */
    }
  }

  async function refreshFavorites() {
    if (!els.favoritesList) return;
    try {
      const json = await fetchJson(API_BASE + 'favorites.php');
      if (json.db === false) {
        state.favoritePokemonIds = new Set();
        els.favoritesList.innerHTML =
          '<li class="list-group-item small text-muted">Configure o banco para favoritos.</li>';
        syncFavoriteButtonIfModalOpen();
        return;
      }
      const rows = json.data || [];
      state.favoritePokemonIds = new Set(rows.map((r) => parseInt(String(r.pokemon_id), 10)));
      if (!rows.length) {
        els.favoritesList.innerHTML = '<li class="list-group-item small text-muted">Nenhum favorito.</li>';
        syncFavoriteButtonIfModalOpen();
        return;
      }
      els.favoritesList.innerHTML = rows
        .map((r) => {
          const pid = parseInt(String(r.pokemon_id), 10);
          const thumb = pokemonSpriteUrl(pid);
          return `
        <li class="list-group-item favorite-row d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2 favorite-link-wrap flex-grow-1">
            <img class="favorite-thumb" src="${escapeHtml(thumb)}" width="40" height="40" alt="" loading="lazy"
              onerror="this.onerror=null;this.src='https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/0.png'">
            <a href="#" class="text-capitalize history-chip text-truncate" data-open="${escapeHtml(r.nome)}">#${r.pokemon_id} ${escapeHtml(r.nome)}</a>
          </div>
          <button type="button" class="btn btn-sm btn-outline-danger flex-shrink-0" data-del-fav="${r.id}" aria-label="Remover favorito">&times;</button>
        </li>`;
        })
        .join('');
      els.favoritesList.querySelectorAll('[data-open]').forEach((a) => {
        a.addEventListener('click', (ev) => {
          ev.preventDefault();
          openPokemon(a.getAttribute('data-open'));
        });
      });
      els.favoritesList.querySelectorAll('[data-del-fav]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const id = btn.getAttribute('data-del-fav');
          try {
            await fetchJson(API_BASE + 'favorites.php?id=' + encodeURIComponent(id), { method: 'DELETE' });
            await refreshFavorites();
          } catch (e) {
            showToast(e.message, true);
          }
        });
      });
      syncFavoriteButtonIfModalOpen();
    } catch (e) {
      els.favoritesList.innerHTML =
        '<li class="list-group-item small text-danger">Erro ao carregar favoritos.</li>';
    }
  }

  function syncFavoriteButtonIfModalOpen() {
    if (els.modalEl && els.modalEl.classList.contains('show')) {
      updateFavoriteButton();
    }
  }

  async function refreshHistory() {
    if (!els.historyList) return;
    try {
      const json = await fetchJson(API_BASE + 'history.php?limit=12');
      if (json.db === false) {
        els.historyList.innerHTML =
          '<li class="list-group-item small text-muted">Histórico requer banco configurado.</li>';
        return;
      }
      const rows = json.data || [];
      if (!rows.length) {
        els.historyList.innerHTML = '<li class="list-group-item small text-muted">Sem buscas ainda.</li>';
        return;
      }
      els.historyList.innerHTML = rows
        .map(
          (r) => `
        <li class="list-group-item py-2">
          <a href="#" class="small history-chip" data-open="${escapeHtml(r.termo)}">${escapeHtml(r.termo)}</a>
        </li>`
        )
        .join('');
      els.historyList.querySelectorAll('[data-open]').forEach((a) => {
        a.addEventListener('click', (ev) => {
          ev.preventDefault();
          els.search.value = a.getAttribute('data-open');
          openPokemon(a.getAttribute('data-open'));
        });
      });
    } catch {
      /* silencioso */
    }
  }

  function updateFavoriteButton() {
    if (!els.btnFav || !currentDetail) return;
    const id = currentDetail.pokemon.id;
    els.btnFav.dataset.pokemonId = String(id);
    els.btnFav.dataset.nome = currentDetail.pokemon.name;
    const isFav = state.favoritePokemonIds.has(id);
    els.btnFav.classList.toggle('is-favorited', isFav);
    const icon = els.btnFav.querySelector('i');
    if (icon) {
      icon.className = isFav ? 'bi bi-heart-fill' : 'bi bi-heart';
    }
    const label = isFav ? 'Remover dos favoritos' : 'Adicionar aos favoritos';
    els.btnFav.setAttribute('title', label);
    els.btnFav.setAttribute('aria-label', label);
    els.btnFav.setAttribute('aria-pressed', isFav ? 'true' : 'false');
    const textSpan = els.btnFav.querySelector('.btn-favorite-label');
    if (textSpan) {
      textSpan.textContent = isFav ? 'Remover' : 'Favoritar';
    }
  }

  async function toggleFavorite() {
    if (!els.btnFav || !currentDetail) return;
    const pokemonId = parseInt(els.btnFav.dataset.pokemonId, 10);
    const nome = els.btnFav.dataset.nome || '';
    const isFav = state.favoritePokemonIds.has(pokemonId);
    try {
      if (isFav) {
        await fetchJson(API_BASE + 'favorites.php?pokemon_id=' + encodeURIComponent(String(pokemonId)), {
          method: 'DELETE',
        });
        showToast('Removido dos favoritos.');
      } else {
        await fetchJson(API_BASE + 'favorites.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ pokemon_id: pokemonId, nome: nome }),
        });
        showToast('Adicionado aos favoritos!');
        playFavoriteBlip();
        bumpAchievement('favadd');
      }
      await refreshFavorites();
      updateFavoriteButton();
    } catch (e) {
      showToast(e.message || 'Não foi possível atualizar favoritos', true);
    }
  }

  async function init() {
    initThemeToggle();
    initSoundToggle();
    renderRecentList();
    renderAchievements();
    registerServiceWorkerSafe();
    bindGlobalShortcuts();

    if (els.modalEl) {
      els.modalEl.addEventListener('hidden.bs.modal', () => {
        clearPokemonUrlQuery();
        if (els.btnSharePokemon) els.btnSharePokemon.classList.add('d-none');
      });
    }

    els.grid.addEventListener('click', onGridClick);
    els.grid.addEventListener('keydown', onGridKeydown);
    if (els.search) {
      els.search.addEventListener('input', onSearchInput);
      els.search.addEventListener('keydown', onSearchKeydown);
    }
    if (els.regionFilter) {
      els.regionFilter.addEventListener('change', () => {
        syncTypeFilterEnabled();
        const raw = (els.search && els.search.value ? els.search.value : '').trim();
        if (raw.length >= 2 || /^\d+$/.test(raw)) {
          runGlobalSearch(raw);
        } else {
          loadListPage(1);
        }
      });
    }
    if (els.typeFilter) {
      els.typeFilter.addEventListener('change', () => {
        loadListPage(1);
      });
    }
    if (els.btnFav) {
      els.btnFav.addEventListener('click', toggleFavorite);
    }
    if (els.btnRandom) {
      els.btnRandom.addEventListener('click', () => openRandomPokemon());
    }
    if (els.btnSharePokemon) {
      els.btnSharePokemon.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(window.location.href);
          showToast('Link copiado para a área de transferência.');
        } catch (e) {
          showToast('Não foi possível copiar o link.', true);
        }
      });
    }
    if (els.btnCompare) {
      els.btnCompare.addEventListener('click', () => {
        if (compareModalBootstrap) compareModalBootstrap.show();
      });
    }
    if (els.btnRunCompare) {
      els.btnRunCompare.addEventListener('click', () => runCompare());
    }
    if (els.btnExportFav) {
      els.btnExportFav.addEventListener('click', () => exportFavoritesJson());
    }
    if (els.importFavFile) {
      els.importFavFile.addEventListener('change', async (ev) => {
        const f = ev.target.files && ev.target.files[0];
        ev.target.value = '';
        if (!f) return;
        try {
          await importFavoritesFromFile(f);
        } catch (e) {
          showToast(e.message || 'Importação inválida', true);
        }
      });
    }

    await populateRegionFilter();
    syncTypeFilterEnabled();
    await loadListPage(1);
    refreshFavorites();
    refreshHistory();

    try {
      const u = new URL(window.location.href);
      const qp = (u.searchParams.get('pokemon') || '').trim();
      if (qp) openPokemon(qp);
    } catch (e) {}
  }

  document.addEventListener('DOMContentLoaded', init);
})();
