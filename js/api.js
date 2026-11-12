/**
 * Kinopoisk Unofficial API helper
 * Key: localStorage 'kp_api_key' or set via UI
 */
const KP = (function () {
  const BASE = 'https://kinopoiskapiunofficial.tech/api/v2.2';

  function getKey() {
    return localStorage.getItem('kp_api_key') || '';
  }

  function setKey(key) {
    if (key) localStorage.setItem('kp_api_key', key.trim());
    else localStorage.removeItem('kp_api_key');
  }

  async function request(path) {
    const key = getKey();
    if (!key) {
      const err = new Error('NO_KEY');
      err.code = 'NO_KEY';
      throw err;
    }

    const res = await fetch(BASE + path, {
      headers: {
        'X-API-KEY': key,
        'Content-Type': 'application/json'
      }
    });

    if (res.status === 401 || res.status === 403) {
      const err = new Error('BAD_KEY');
      err.code = 'BAD_KEY';
      throw err;
    }
    if (res.status === 404) {
      const err = new Error('NOT_FOUND');
      err.code = 'NOT_FOUND';
      throw err;
    }
    if (!res.ok) {
      const err = new Error('HTTP_' + res.status);
      err.code = 'HTTP';
      err.status = res.status;
      throw err;
    }
    return res.json();
  }

  return {
    getKey,
    setKey,
    /** Film by Kinopoisk ID */
    film(id) {
      return request('/films/' + id);
    },
    /** Search by keyword */
    search(keyword, page = 1) {
      return request('/films?keyword=' + encodeURIComponent(keyword) + '&page=' + page);
    },
    /** Top: TOP_250_BEST_FILMS | TOP_100_POPULAR_FILMS | TOP_AWAIT_FILMS */
    top(type = 'TOP_100_POPULAR_FILMS', page = 1) {
      return request('/films/collections?type=' + type + '&page=' + page);
    },
    /** Similar films */
    similars(id) {
      return request('/films/' + id + '/similars');
    },
    /** Videos / trailers */
    videos(id) {
      return request('/films/' + id + '/videos');
    }
  };
})();
