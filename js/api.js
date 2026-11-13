/**
 * Kinopoisk data layer
 * Prefer same-origin /api/* (Cloudflare Worker + secret + cache)
 * Fallback: direct API only if KP_API_KEY was set in env somehow — no UI key
 */
const KP = (function () {
  const DIRECT = 'https://kinopoiskapiunofficial.tech/api/v2.2';
  const PROXY = '/api';

  let useProxy = null;

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

    // No proxy — fail clearly (key is only on Worker)
    const err = new Error('NO_KEY');
    err.code = 'NO_KEY';
    throw err;
  }

  return {
    film(id) {
      return request('/films/' + id);
    },
    search(keyword, page = 1) {
      return request(
        '/films?keyword=' + encodeURIComponent(keyword) + '&page=' + page
      );
    },
    /**
     * Collections — valid types:
     * TOP_POPULAR_ALL | TOP_POPULAR_MOVIES | TOP_250_MOVIES |
     * TOP_250_TV_SHOWS | POPULAR_SERIES | CLOSES_RELEASES | FAMILY | ...
     */
    top(type = 'TOP_POPULAR_ALL', page = 1) {
      return request(
        '/films/collections?type=' + encodeURIComponent(type) + '&page=' + page
      );
    },
    similars(id) {
      return request('/films/' + id + '/similars');
    },
    videos(id) {
      return request('/films/' + id + '/videos');
    }
  };
})();
