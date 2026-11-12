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

  function showKeyNeeded(el) {
    if (!el) return;
    el.hidden = false;
    el.innerHTML = 'Нужен API-ключ. Нажми «Ключ» в шапке и вставь ключ с <a href="https://kinopoiskapiunofficial.tech" target="_blank" rel="noopener">kinopoiskapiunofficial.tech</a>';
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
      const pop = await KP.top('TOP_100_POPULAR_FILMS', 1);
      fillGrid(popularGrid, (pop.items || []).slice(0, 12));
      if (popularError) popularError.hidden = true;
    } catch (e) {
      popularGrid.innerHTML = '';
      if (e.code === 'NO_KEY' || e.code === 'BAD_KEY') showKeyNeeded(popularError);
      else if (popularError) {
        popularError.hidden = false;
        popularError.textContent = 'Не удалось загрузить популярное';
      }
    }

    try {
      const top = await KP.top('TOP_250_BEST_FILMS', 1);
      fillGrid(topGrid, (top.items || []).slice(0, 12));
      if (topError) topError.hidden = true;
    } catch (e) {
      topGrid.innerHTML = '';
      if (e.code === 'NO_KEY' || e.code === 'BAD_KEY') showKeyNeeded(topError);
      else if (topError) {
        topError.hidden = false;
        topError.textContent = 'Не удалось загрузить топ';
      }
    }
  }

  async function doSearch(query, mode) {
    searchResults.hidden = false;
    resultsEmpty.hidden = true;
    resultsError.hidden = true;
    resultsGrid.innerHTML = '<div class="skeleton-card"></div><div class="skeleton-card"></div><div class="skeleton-card"></div>';

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
      if (e.code === 'NO_KEY' || e.code === 'BAD_KEY') {
        resultsError.innerHTML = 'Нужен API-ключ. Нажми «Ключ» в шапке.';
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
      const mode = (document.querySelector('input[name="mode"]:checked') || {}).value || 'name';
      doSearch(q, mode);
    });
  }

  if (clearResults) {
    clearResults.addEventListener('click', () => {
      searchResults.hidden = true;
      resultsGrid.innerHTML = '';
    });
  }

  // ?q= from URL
  const params = new URLSearchParams(location.search);
  const q = params.get('q');
  if (q) {
    mainInput.value = q;
    doSearch(q, 'name');
  }

  loadCollections();
})();
