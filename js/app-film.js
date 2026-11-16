/* КиноПлёнка film page — XSS-hardened + multi-player */
(function () {
  'use strict';

  const PROXY = '/api';
  let useProxy = null;

  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&')
      .replace(/</g, '<')
      .replace(/>/g, '>')
      .replace(/"/g, '"')
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

  function safeYoutubeUrl(u) {
    const href = safeHttpUrl(u);
    if (!href) return '';
    try {
      const url = new URL(href);
      const host = url.hostname.replace(/^www\./, '');
      if (
        host === 'youtube.com' ||
        host === 'm.youtube.com' ||
        host === 'youtu.be'
      ) {
        return href;
      }
    } catch (_) {}
    return '';
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
      '"><div class="card__poster">' +
      img +
      ratingHtml +
      '</div><div class="card__body"><div class="card__title">' +
      escapeHtml(title) +
      '</div><div class="card__year">' +
      escapeHtml(year) +
      '</div></div></a>'
    );
  }

  /* ── Player registry ───────────────────────────────────────────────
     Чтобы добавить новый плеер — просто добавь объект в массив PLAYERS.
     id        — уникальный ключ (сохраняется в localStorage)
     name      — подпись на вкладке
     render(container, kpId) — функция, которая вставляет плеер
  ─────────────────────────────────────────────────────────────────── */
  const PLAYERS = [
    {
      id: 'vibix',
      name: 'Vibix',
      render: function (container, kpId) {
        container.innerHTML = '';
        const ins = document.createElement('ins');
        ins.setAttribute('data-publisher-id', '679962206');
        ins.setAttribute('data-type', 'kp');
        ins.setAttribute('data-id', kpId);
        ins.setAttribute('data-design', '4');
        ins.setAttribute('data-color1', '#FF0000');
        ins.setAttribute('data-color2', '#CC0000');
        ins.setAttribute('data-color3', '#282828');
        ins.setAttribute('data-color4', '#FF3333');
        ins.setAttribute('data-color5', '#FFFFFF');
        container.appendChild(ins);
      }
    }
    // Пример будущего плеера:
    // {
    //   id: 'another',
    //   name: 'Другой',
    //   render: function (container, kpId) {
    //     container.innerHTML = '...';
    //   }
    // }
  ];

  const STORAGE_KEY = 'kino_player';

  function getSavedPlayerId() {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      if (saved && PLAYERS.some(function (p) { return p.id === saved; })) {
        return saved;
      }
    } catch (_) {}
    return PLAYERS[0] ? PLAYERS[0].id : null;
  }

  function savePlayerId(id) {
    try {
      localStorage.setItem(STORAGE_KEY, id);
    } catch (_) {}
  }

  function renderPlayerTabs(activeId, onSelect) {
    const tabsEl = document.getElementById('playerTabs');
    if (!tabsEl) return;

    tabsEl.innerHTML = '';

    // Если плеер один — вкладки можно скрыть, но оставляем видимыми
    // чтобы пользователь видел, что выбор есть и его можно расширить.
    PLAYERS.forEach(function (p) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'player-tab' + (p.id === activeId ? ' is-active' : '');
      btn.setAttribute('role', 'tab');
      btn.setAttribute('aria-selected', p.id === activeId ? 'true' : 'false');
      btn.dataset.playerId = p.id;
      btn.textContent = p.name;
      btn.addEventListener('click', function () {
        if (p.id === activeId) return;
        onSelect(p.id);
      });
      tabsEl.appendChild(btn);
    });
  }

  function loadPlayer(playerId, kpId) {
    const player = document.getElementById('player');
    if (!player) return;

    const entry = PLAYERS.find(function (p) { return p.id === playerId; }) || PLAYERS[0];
    if (!entry) return;

    entry.render(player, kpId);
    player.dataset.kinopoiskId = kpId;
    player.dataset.player = entry.id;
  }

  /* ── Page logic ─────────────────────────────────────────────────── */

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

  const rawId = new URLSearchParams(location.search).get('id');
  const id = safeId(rawId);

  const loading = document.getElementById('filmLoading');
  const content = document.getElementById('filmContent');
  const errorEl = document.getElementById('filmError');
  const poster = document.getElementById('filmPoster');
  const titleEl = document.getElementById('filmTitle');
  const originalEl = document.getElementById('filmOriginal');
  const metaEl = document.getElementById('filmMeta');
  const ratingEl = document.getElementById('filmRating');
  const genresEl = document.getElementById('filmGenres');
  const descEl = document.getElementById('filmDescription');
  const trailerBtn = document.getElementById('filmTrailer');
  const kpBtn = document.getElementById('filmKp');
  const similarSection = document.getElementById('similarSection');
  const similarGrid = document.getElementById('similarGrid');
  const player = document.getElementById('player');

  function showError(msg) {
    loading.hidden = true;
    content.hidden = true;
    errorEl.hidden = false;
    errorEl.textContent = '';
    const p = document.createElement('span');
    p.textContent = msg + ' ';
    const a = document.createElement('a');
    a.href = './';
    a.textContent = 'На главную';
    errorEl.appendChild(p);
    errorEl.appendChild(a);
  }

  if (!id) {
    showError('Не указан или неверный ID фильма.');
    return;
  }

  async function load() {
    try {
      const data = await KP.film(id);
      const title = safeText(
        data.nameRu || data.nameOriginal || data.nameEn || 'Без названия',
        300
      );
      document.title = title + ' — КиноПлёнка';
      titleEl.textContent = title;

      if (data.nameOriginal && data.nameOriginal !== title) {
        originalEl.textContent = safeText(data.nameOriginal, 300);
        originalEl.hidden = false;
      } else originalEl.hidden = true;

      const bits = [];
      if (data.year) bits.push(safeText(data.year, 12));
      if (data.type === 'TV_SERIES' || data.type === 'MINI_SERIES') bits.push('сериал');
      else if (data.type === 'FILM') bits.push('фильм');
      if (data.filmLength) bits.push(String(parseInt(data.filmLength, 10) || '') + ' мин');
      metaEl.textContent = bits.filter(Boolean).join(' · ');

      if (data.ratingKinopoisk != null && Number.isFinite(Number(data.ratingKinopoisk))) {
        ratingEl.textContent =
          '★ ' +
          data.ratingKinopoisk +
          (data.ratingKinopoiskVoteCount
            ? ' (' + parseInt(data.ratingKinopoiskVoteCount, 10) + ')'
            : '');
        ratingEl.hidden = false;
      } else if (data.ratingImdb != null && Number.isFinite(Number(data.ratingImdb))) {
        ratingEl.textContent = 'IMDb ' + data.ratingImdb;
        ratingEl.hidden = false;
      }

      const genres = (data.genres || [])
        .map(function (g) {
          return safeText(g.genre, 40);
        })
        .filter(Boolean)
        .join(', ');
      const countries = (data.countries || [])
        .map(function (c) {
          return safeText(c.country, 40);
        })
        .filter(Boolean)
        .join(', ');
      genresEl.textContent = [genres, countries].filter(Boolean).join(' · ');

      descEl.textContent = safeText(data.description || data.shortDescription || '', 4000);

      const posterUrl = safeHttpUrl(data.posterUrl || data.posterUrlPreview);
      if (posterUrl) {
        poster.src = posterUrl;
        poster.alt = title;
        poster.referrerPolicy = 'no-referrer';
        poster.onerror = function () {
          this.removeAttribute('src');
          this.style.display = 'none';
        };
      }

      kpBtn.href = 'https://www.kinopoisk.ru/film/' + encodeURIComponent(id) + '/';
      kpBtn.rel = 'noopener noreferrer';

      try {
        const vids = await KP.videos(id);
        const yt = (vids.items || []).find(function (v) {
          return v.site === 'YOUTUBE' && v.url;
        });
        if (yt) {
          let u = yt.url;
          if (String(u).indexOf('youtu.be/') !== -1) {
            u =
              'https://www.youtube.com/watch?v=' +
              String(u).split('youtu.be/')[1].split(/[?&#]/)[0];
          }
          const safe = safeYoutubeUrl(u);
          if (safe) {
            trailerBtn.href = safe;
            trailerBtn.rel = 'noopener noreferrer';
            trailerBtn.hidden = false;
          }
        }
      } catch (_) {}

      // Player switcher
      let currentPlayerId = getSavedPlayerId();

      function selectPlayer(playerId) {
        currentPlayerId = playerId;
        savePlayerId(playerId);
        renderPlayerTabs(playerId, selectPlayer);
        loadPlayer(playerId, id);
      }

      renderPlayerTabs(currentPlayerId, selectPlayer);
      loadPlayer(currentPlayerId, id);

      if (player) {
        player.dataset.title = title;
      }

      loading.hidden = true;
      content.hidden = false;

      try {
        const sim = await KP.similars(id);
        const list = (sim.items || []).slice(0, 12);
        if (list.length && similarGrid) {
          similarGrid.innerHTML = list.map(renderFilmCard).filter(Boolean).join('');
          similarSection.hidden = false;
        }
      } catch (_) {}
    } catch (e) {
      if (e.code === 'NO_KEY') showError('API не настроен.');
      else if (e.code === 'NOT_FOUND') showError('Фильм не найден.');
      else showError('Ошибка загрузки.');
    }
  }

  load();

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
