(function () {
  'use strict';

  const params = new URLSearchParams(location.search);
  const id = params.get('id');

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
  const playerPlaceholder = document.getElementById('playerPlaceholder');

  function showError(msg) {
    loading.hidden = true;
    content.hidden = true;
    errorEl.hidden = false;
    errorEl.innerHTML = msg;
  }

  if (!id) {
    showError('Не указан ID фильма. <a href="./">На главную</a>');
    return;
  }

  async function load() {
    try {
      const data = await KP.film(id);

      const title = data.nameRu || data.nameOriginal || data.nameEn || 'Без названия';
      document.title = title + ' — Kino';

      titleEl.textContent = title;

      if (data.nameOriginal && data.nameOriginal !== title) {
        originalEl.textContent = data.nameOriginal;
        originalEl.hidden = false;
      } else {
        originalEl.hidden = true;
      }

      // Meta: year · type · length
      const bits = [];
      if (data.year) bits.push(data.year);
      if (data.type === 'TV_SERIES' || data.type === 'MINI_SERIES') bits.push('сериал');
      else if (data.type === 'FILM') bits.push('фильм');
      if (data.filmLength) bits.push(data.filmLength + ' мин');
      metaEl.textContent = bits.join(' · ');

      // Rating
      if (data.ratingKinopoisk) {
        ratingEl.textContent = '★ ' + data.ratingKinopoisk + (data.ratingKinopoiskVoteCount ? ' (' + data.ratingKinopoiskVoteCount + ')' : '');
        ratingEl.hidden = false;
      } else if (data.ratingImdb) {
        ratingEl.textContent = 'IMDb ' + data.ratingImdb;
        ratingEl.hidden = false;
      }

      // Genres + countries
      const genres = (data.genres || []).map((g) => g.genre).join(', ');
      const countries = (data.countries || []).map((c) => c.country).join(', ');
      genresEl.textContent = [genres, countries].filter(Boolean).join(' · ');

      // Description
      descEl.textContent = data.description || data.shortDescription || '';

      // Poster
      const posterUrl = data.posterUrl || data.posterUrlPreview;
      if (posterUrl) {
        poster.src = posterUrl;
        poster.alt = title;
      }

      // Kinopoisk link
      kpBtn.href = 'https://www.kinopoisk.ru/film/' + id + '/';

      // Trailer
      try {
        const vids = await KP.videos(id);
        const items = vids.items || [];
        const yt = items.find((v) => v.site === 'YOUTUBE' && v.url);
        if (yt) {
          let url = yt.url;
          if (url.includes('youtu.be/')) {
            url = 'https://www.youtube.com/watch?v=' + url.split('youtu.be/')[1];
          }
          trailerBtn.href = url;
          trailerBtn.hidden = false;
        }
      } catch (_) {}

      // Player slot: expose film id for external player scripts
      player.dataset.kinopoiskId = id;
      player.dataset.title = title;
      // Example hook for your player:
      // window.initPlayer && window.initPlayer({ id, title, playerEl: player });

      loading.hidden = true;
      content.hidden = false;

      // Similars
      try {
        const sim = await KP.similars(id);
        const list = (sim.items || []).slice(0, 12);
        if (list.length) {
          similarGrid.innerHTML = list.map(renderFilmCard).join('');
          similarSection.hidden = false;
        }
      } catch (_) {}
    } catch (e) {
      if (e.code === 'NO_KEY' || e.code === 'BAD_KEY') {
        showError('Нужен API-ключ. Нажми «Ключ» в шапке и вставь ключ с <a href="https://kinopoiskapiunofficial.tech" target="_blank" rel="noopener">kinopoiskapiunofficial.tech</a>');
      } else if (e.code === 'NOT_FOUND') {
        showError('Фильм не найден. <a href="./">На главную</a>');
      } else {
        showError('Ошибка загрузки. <a href="./">На главную</a>');
      }
    }
  }

  load();
})();
