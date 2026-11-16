/* КиноПлёнка home — XSS-hardened */
(function () {
  'use strict';

  const PROXY = '/api';
  let useProxy = null;

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function safeId(v) {
    const s = String(v == null ? '' : v).trim();
    return /^\d{1,12}$/.test(s) ? s : '';
  }

  function safeHttpUrl(u) {
    if (!u || typeof u !== 'string') return '';
    try {
      const url = new URL(u, location.origin);
      if (url.protocol !== 'https:' && url.protocol !== 'http:') return '';
      return url.href;
    } catch (_) {
      return '';
    }
  }

  function safeText(v, max) {
    const s = String(v == null ? '' : v).replace(/[\u0000-\u001F\u007F]/g, '');
    return max ? s.slice(0, max) : s;
  }

  async function probeProxy() {
    if (useProxy !== null) return useProxy;
    try {
      const r = await fetch(PROXY + '/health');
      if (r.ok) {
        const j = await r.json();
        useProxy = Boolean(j && j.hasKey);
      } else useProxy = false;
    } catch (_) {
      useProxy = false;
    }
    return useProxy;
  }

  async function request(path) {
    if (!(await probeProxy())) {
      const e = new Error('NO_KEY');
      e.code = 'NO_KEY';
      throw e;
    }
    const res = await fetch(PROXY + path);
    if (res.status === 404) {
      const e = new Error('NOT_FOUND');
      e.code = 'NOT_FOUND';
      throw e;
    }
    if (res.status === 503) {
      const e = new Error('NO_KEY');
      e.code = 'NO_KEY';
      throw e;
    }
    if (!res.ok) {
      const e = new Error('HTTP_' + res.status);
      e.code = 'HTTP';
      throw e;
    }
    return res.json();
  }

  const KP = {
    film: (id) => request('/films/' + safeId(id)),
    search: (q, p) =>
      request(
        '/films?keyword=' +
          encodeURIComponent(safeText(q, 200)) +
          '&page=' +
          (parseInt(p, 10) || 1)
      ),
    top: (t, p) =>
      request(
        '/films/collections?type=' +
          encodeURIComponent(String(t || 'TOP_POPULAR_ALL').replace(/[^A-Z0-9_]/g, '')) +
          '&page=' +
          (parseInt(p, 10) || 1)
      ),
    similars: (id) => request('/films/' + safeId(id) + '/similars'),
    videos: (id) => request('/films/' + safeId(id) + '/videos')
  };

  function renderFilmCard(film) {
    const id = safeId(film.kinopoiskId || film.filmId || film.id);
    if (!id) return '';
    const title = safeText(
      film.nameRu || film.nameOriginal || film.nameEn || 'Без названия',
      200
    );
    const year = safeText(film.year, 12).replace(/[^0-9\-–]/g, '');
    let rating = film.ratingKinopoisk || film.rating || '';
    if (rating !== '' && rating != null) {
      const n = Number(rating);
      rating = Number.isFinite(n) ? String(n) : '';
    } else rating = '';
    const poster = safeHttpUrl(film.posterUrlPreview || film.posterUrl || '');

    const ratingHtml = rating
      ? '<span class="card__rating">' + escapeHtml(rating) + '</span>'
      : '';
    const img = poster
      ? '<img src="' + escapeHtml(poster) + '" alt="" loading="lazy" referrerpolicy="no-referrer">'
      : '<div class="card__no-poster">Нет постера</div>';

    return (
      '<a class="card" href="film.html?id=' +
      encodeURIComponent(id) +
      '">' +
      '<div class="card__poster">' +
      img +
      ratingHtml +
      '</div>' +
      '<div class="card__body">' +
      '<div class="card__title">' +
      escapeHtml(title) +
      '</div>' +
      '<div class="card__year">' +
      escapeHtml(year) +
      '</div>' +
      '</div></a>'
    );
  }

  const hf = document.getElementById('headerSearch');
  const hi = document.getElementById('headerInput');
  if (hf && hi) {
    hf.addEventListener('submit', function (e) {
      e.preventDefault();
      const q = hi.value.trim().slice(0, 200);
      if (!q) return;
      if (/^\d{1,12}$/.test(q)) location.href = 'film.html?id=' + encodeURIComponent(q);
      else location.href = './?q=' + encodeURIComponent(q);
    });
  }

  const popularGrid = document.getElementById('popularGrid');
  const topGrid = document.getElementById('topGrid');
  const popularError = document.getElementById('popularError');
  const topError = document.getElementById('topError');
  const searchResults = document.getElementById('searchResults');
  const resultsGrid = document.getElementById('resultsGrid');
  const resultsTitle = document.getElementById('resultsTitle');
  const resultsEmpty = document.getElementById('resultsEmpty');
  const resultsError = document.getElementById('resultsError');
  const clearResults = document.getElementById('clearResults');
  const mainSearch = document.getElementById('mainSearch');
  const mainInput = document.getElementById('mainInput');

  function showApiError(el, msg) {
    if (!el) return;
    el.hidden = false;
    el.textContent = msg || 'Не удалось загрузить данные';
  }

  function fillGrid(grid, items) {
    if (!grid) return;
    grid.innerHTML =
      !items || !items.length ? '' : items.map(renderFilmCard).filter(Boolean).join('');
  }

  async function loadCollections() {
    try {
      const pop = await KP.top('TOP_POPULAR_ALL', 1);
      fillGrid(popularGrid, (pop.items || []).slice(0, 12));
      if (popularError) popularError.hidden = true;
    } catch (e) {
      if (popularGrid) popularGrid.innerHTML = '';
      showApiError(
        popularError,
        e.code === 'NO_KEY' ? 'API не настроен' : 'Не удалось загрузить популярное'
      );
    }
    try {
      const top = await KP.top('TOP_250_MOVIES', 1);
      fillGrid(topGrid, (top.items || []).slice(0, 12));
      if (topError) topError.hidden = true;
    } catch (e) {
      if (topGrid) topGrid.innerHTML = '';
      showApiError(
        topError,
        e.code === 'NO_KEY' ? 'API не настроен' : 'Не удалось загрузить топ'
      );
    }
  }

  async function doSearch(query, mode) {
    const q = safeText(query, 200);
    searchResults.hidden = false;
    resultsEmpty.hidden = true;
    resultsError.hidden = true;
    resultsGrid.innerHTML =
      '<div class="skeleton-card"></div><div class="skeleton-card"></div><div class="skeleton-card"></div>';

    if (mode === 'id' || /^\d{1,12}$/.test(q)) {
      location.href = 'film.html?id=' + encodeURIComponent(q.replace(/\D/g, '').slice(0, 12));
      return;
    }

    resultsTitle.textContent = 'Результаты: «' + q + '»';

    try {
      const data = await KP.search(q, 1);
      const items = data.films || data.items || [];
      if (!items.length) {
        resultsGrid.innerHTML = '';
        resultsEmpty.hidden = false;
        return;
      }
      fillGrid(resultsGrid, items.slice(0, 24));
    } catch (e) {
      resultsGrid.innerHTML = '';
      resultsError.hidden = false;
      resultsError.textContent =
        e.code === 'NO_KEY' ? 'API не настроен' : 'Ошибка поиска';
    }
  }

  if (mainSearch) {
    mainSearch.addEventListener('submit', function (e) {
      e.preventDefault();
      const q = mainInput.value.trim().slice(0, 200);
      if (!q) return;
      const mode =
        (document.querySelector('input[name="mode"]:checked') || {}).value || 'name';
      doSearch(q, mode);
    });
  }

  if (clearResults) {
    clearResults.addEventListener('click', function () {
      searchResults.hidden = true;
      resultsGrid.innerHTML = '';
    });
  }

  const qParam = new URLSearchParams(location.search).get('q');
  if (qParam && mainInput) {
    mainInput.value = safeText(qParam, 200);
    doSearch(qParam, 'name');
  }

  loadCollections();

  var box = document.getElementById('liveinternet-counter');
  if (box) {
    box.innerHTML =
      '<a href="https://www.liveinternet.ru/click" target="_blank" rel="noopener noreferrer">' +
      '<img id="licnt044E" width="88" height="31" style="border:0;vertical-align:middle" ' +
      'title="LiveInternet" alt="" ' +
      'src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAEALAAAAAABAAEAAAIBTAA7"/></a>';
    var img = document.getElementById('licnt044E');
    if (img) {
      img.src =
        'https://counter.yadro.ru/hit?t52.6;r' +
        encodeURIComponent(document.referrer || '') +
        ';u' +
        encodeURIComponent(location.href) +
        ';h' +
        encodeURIComponent((document.title || '').slice(0, 150)) +
        ';' +
        Math.random();
    }
  }
})();
