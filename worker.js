/**
 * Cloudflare Worker: static site + Kinopoisk API proxy with cache
 *
 * Secrets (wrangler secret put KP_API_KEY):
 *   KP_API_KEY — ключ с kinopoiskapiunofficial.tech
 *
 * Deploy:
 *   npx wrangler deploy
 *   npx wrangler secret put KP_API_KEY
 */

const KP_BASE = 'https://kinopoiskapiunofficial.tech/api/v2.2';

// Cache TTL (seconds)
const TTL = {
  collections: 6 * 3600, // популярное / топ — редко меняются
  film: 24 * 3600,
  search: 30 * 60,
  videos: 24 * 3600,
  similars: 12 * 3600,
  default: 3600
};

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);

    // API proxy
    if (url.pathname.startsWith('/api/')) {
      return handleApi(request, env, ctx, url);
    }

    // Static assets — if using Workers Assets / Sites, platform serves them.
    // Fallback message when only the worker script is deployed alone:
    return new Response(
      'Kino worker is running. Mount static assets (Pages or Workers Assets) or open /api/health',
      { status: 200, headers: { 'content-type': 'text/plain; charset=utf-8' } }
    );
  }
};

async function handleApi(request, env, ctx, url) {
  if (request.method === 'OPTIONS') {
    return new Response(null, { headers: corsHeaders() });
  }

  if (url.pathname === '/api/health') {
    return json({ ok: true, hasKey: Boolean(env.KP_API_KEY) });
  }

  const key = env.KP_API_KEY;
  if (!key) {
    return json({ error: 'KP_API_KEY not configured' }, 503);
  }

  // /api/films/... → KP /films/...
  const kpPath = url.pathname.replace(/^\/api/, '') + url.search;

  // Only allow films endpoints
  if (!kpPath.startsWith('/films')) {
    return json({ error: 'forbidden' }, 403);
  }

  const cacheKey = new Request('https://kp-cache.internal' + kpPath, request);
  const cache = caches.default;

  let cached = await cache.match(cacheKey);
  if (cached) {
    const hit = new Response(cached.body, cached);
    hit.headers.set('X-Cache', 'HIT');
    hit.headers.set('Access-Control-Allow-Origin', '*');
    return hit;
  }

  const upstream = await fetch(KP_BASE + kpPath, {
    headers: {
      'X-API-KEY': key,
      'Content-Type': 'application/json',
      Accept: 'application/json'
    }
  });

  const body = await upstream.text();
  const ttl = pickTtl(kpPath);

  const response = new Response(body, {
    status: upstream.status,
    headers: {
      'Content-Type': 'application/json; charset=utf-8',
      'Cache-Control': 'public, max-age=' + ttl,
      'X-Cache': 'MISS',
      ...corsHeaders()
    }
  });

  if (upstream.ok) {
    ctx.waitUntil(cache.put(cacheKey, response.clone()));
  }

  return response;
}

function pickTtl(path) {
  if (path.includes('/collections')) return TTL.collections;
  if (path.includes('/videos')) return TTL.videos;
  if (path.includes('/similars')) return TTL.similars;
  if (path.includes('keyword=')) return TTL.search;
  if (/\/films\/\d+/.test(path)) return TTL.film;
  return TTL.default;
}

function corsHeaders() {
  return {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type'
  };
}

function json(obj, status = 200) {
  return new Response(JSON.stringify(obj), {
    status,
    headers: { 'Content-Type': 'application/json', ...corsHeaders() }
  });
}
