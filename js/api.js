/**
 * Kinopoisk data layer
 * 1) Prefer same-origin /api/* (Cloudflare Worker + secret + cache)
 * 2) Fallback: direct unofficial API with localStorage key
 */
const KP = (function () {
  const DIRECT = 'https://kinopoiskapiunofficial.tech/api/v2.2';
  const PROXY = '/api';

  let useProxy = null; // null = unknown, true/false after probe

  function getKey() {
    return localStorage.getItem('kp_api_key') || '';
  }

  function setKey(key) {
    if (key) localStorage.setItem('kp_api_key', key.trim());
    else localStorage.removeItem('kp_api_key');
  }

  async function probeProxy() {
    if (useProxy !== null) return useProxy;
    try {
      const r = await fetch(PROXY + '/health', { method: 'GET' });
      if (r.ok) {
        const j = await r.json();
        useProxy = Boolean(j && j.hasKey);
      } else {
        useProxy = false;
      }
    } catch (_) {
      useProxy = false;
    }
    return useProxy;
  }

  async function request(path) {
    const proxyOk = await probeProxy();

    if (proxyOk) {
      const res = await fetch(PROXY + path);
      if (res.status === 404) {
        const err = new Error('NOT_FOUND');
        err.code = 'NOT_FOUND';
        throw err;
      }
      if (res.status === 503) {
        const err = new Error('NO_KEY');
        err.code = 'NO_KEY';
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

    // Direct fallback (dev / without worker)
    const key = getKey();
    if (!key) {
      const err = new Error('NO_KEY');
      err.code = 'NO_KEY';
      throw err;
    }

    const res = await fetch(DIRECT + path, {
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
    film(id) {
      return request('/films/' + id);
    },
    search(keyword, page = 1) {
      return request('/films?keyword=' + encodeURIComponent(keyword) + '&page=' + page);
    },
    top(type = 'TOP_100_POPULAR_FILMS', page = 1) {
      return request('/films/collections?type=' + type + '&page=' + page);
    },
    similars(id) {
      return request('/films/' + id + '/similars');
    },
    videos(id) {
      return request('/films/' + id + '/videos');
    }
  };
})();
