(function () {
  'use strict';

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
    if (!items || !items.length) {
      grid.innerHTML = '';
      return;
    }
    grid.innerHTML = items.map(renderFilmCard).join('');
  }

  async function loadCollections() {
    try {
      const pop = await KP.top('TOP_POPULAR_ALL', 1);
      fillGrid(popularGrid, (pop.items || []).slice(0, 12));
      if (popularError) popularError.hidden = true;
    } catch (e) {
      popularGrid.innerHTML = '';
      if (e.code === 'NO_KEY') {
        showApiError(popularError, 'API не настроен. Нужен KP_API_KEY в Cloudflare Worker.');
      } else {
        showApiError(popularError, 'Не удалось загрузить популярное');
      }
    }

    try {
      const top = await KP.top('TOP_250_MOVIES', 1);
      fillGrid(topGrid, (top.items || []).slice(0, 12));
      if (topError) topError.hidden = true;
    } catch (e) {
      topGrid.innerHTML = '';
      if (e.code === 'NO_KEY') {
        showApiError(topError, 'API не настроен. Нужен KP_API_KEY в Cloudflare Worker.');
      } else {
        showApiError(topError, 'Не удалось загрузить топ');
      }
    }
  }

  async function doSearch(query, mode) {
    searchResults.hidden = false;
    resultsEmpty.hidden = true;
    resultsError.hidden = true;
    resultsGrid.innerHTML =
      '<div class="skeleton-card"></div><div class="skeleton-card"></div><div class="skeleton-card"></div>';

    if (mode === 'id' || /^\d+$/.test(query)) {
      location.href = 'film.html?id=' + query.replace(/\D/g, '');
      return;
    }

    resultsTitle.textContent = 'Результаты: «' + query + '»';

    try {
      const data = await KP.search(query, 1);
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
      if (e.code === 'NO_KEY') {
        resultsError.textContent =
          'API не настроен. Нужен KP_API_KEY в Cloudflare Worker.';
      } else {
        resultsError.textContent = 'Ошибка поиска. Попробуй позже.';
      }
    }
  }

  if (mainSearch) {
    mainSearch.addEventListener('submit', (e) => {
      e.preventDefault();
      const q = mainInput.value.trim();
      if (!q) return;
      const mode =
        (document.querySelector('input[name="mode"]:checked') || {}).value ||
        'name';
      doSearch(q, mode);
    });
  }

  if (clearResults) {
    clearResults.addEventListener('click', () => {
      searchResults.hidden = true;
      resultsGrid.innerHTML = '';
    });
  }

  const params = new URLSearchParams(location.search);
  const q = params.get('q');
  if (q) {
    mainInput.value = q;
    doSearch(q, 'name');
  }

  loadCollections();
})();
