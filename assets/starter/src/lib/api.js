const runtimeConfig = (() => {
  try {
    return import.meta.glob("../config/*.json", { eager: true });
  } catch (error) {
    return {};
  }
})();

const loadedConfig = runtimeConfig["../config/wp-config.json"]?.default || {};
const API_BASE =
  (import.meta.env.VITE_WTR_API_BASE || loadedConfig.apiBase || "__WTR_API_BASE__").replace(/\/$/, "");

const CACHE_PREFIX = "Lenviqa:";
const memoryCache = new Map();
const SESSION_ROUTE_PATTERNS = [/^\/cart\/?$/i, /^\/checkout\/?$/i, /^\/my-account\/?$/i];

function buildCacheKey(key) {
  return `${CACHE_PREFIX}${key}`;
}

function readCache(key, maxAgeMs = 0) {
  const cacheKey = buildCacheKey(key);
  const inMemory = memoryCache.get(cacheKey);

  if (inMemory && (!maxAgeMs || Date.now() - inMemory.timestamp <= maxAgeMs)) {
    return inMemory.value;
  }

  try {
    const raw = window.sessionStorage.getItem(cacheKey);

    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw);

    if (maxAgeMs && Date.now() - parsed.timestamp > maxAgeMs) {
      window.sessionStorage.removeItem(cacheKey);
      return null;
    }

    memoryCache.set(cacheKey, parsed);
    return parsed.value;
  } catch (error) {
    return null;
  }
}

function writeCache(key, value) {
  const cacheKey = buildCacheKey(key);
  const entry = { value, timestamp: Date.now() };
  memoryCache.set(cacheKey, entry);

  try {
    window.sessionStorage.setItem(cacheKey, JSON.stringify(entry));
  } catch (error) {
    // Ignore storage failures and continue with in-memory cache.
  }
}

async function cachedApiFetch(path, { cacheKey, maxAgeMs = 0 } = {}) {
  if (cacheKey) {
    const cached = readCache(cacheKey, maxAgeMs);

    if (cached) {
      return cached;
    }
  }

  const data = await apiFetch(path);

  if (cacheKey) {
    writeCache(cacheKey, data);
  }

  return data;
}

export function getCachedBootData() {
  return {
    site: readCache("site", 1000 * 60 * 10),
    menus: readCache("menus", 1000 * 60 * 10),
    pages: readCache("pages", 1000 * 60 * 5),
    posts: readCache("posts", 1000 * 60 * 5)
  };
}

export function getCachedResolvedRoute(pathname) {
  if (isSessionSensitivePath(pathname)) {
    return null;
  }

  return readCache(`resolve:${pathname}`, 1000 * 60 * 5);
}

function isSessionSensitivePath(pathname = "") {
  return SESSION_ROUTE_PATTERNS.some((pattern) => pattern.test(pathname));
}

async function apiFetch(path) {
  let response;

  try {
    response = await fetch(`${API_BASE}${path}`, {
      credentials: "include",
      headers: {
        Accept: "application/json"
      }
    });
  } catch (networkError) {
    throw new Error(
      "Unable to reach the Lenviqa API. Confirm WordPress is running, the plugin is active, and the API base URL is correct."
    );
  }

  if (!response.ok) {
    let message = "Request failed.";

    try {
      const error = await response.json();
      message = error.message || message;
    } catch (parseError) {
      message = response.statusText || message;
    }

    const error = new Error(message);
    error.status = response.status;
    throw error;
  }

  try {
    return await response.json();
  } catch (parseError) {
    const error = new Error("The Lenviqa API returned an invalid JSON response.");
    error.status = response.status;
    throw error;
  }
}

export function fetchSiteConfig() {
  return cachedApiFetch("/site", { cacheKey: "site", maxAgeMs: 1000 * 60 * 10 });
}

export function fetchMenus() {
  return cachedApiFetch("/menus", { cacheKey: "menus", maxAgeMs: 1000 * 60 * 10 });
}

export function fetchPosts() {
  return cachedApiFetch("/posts?per_page=10", { cacheKey: "posts", maxAgeMs: 1000 * 60 * 5 });
}

export function fetchPages() {
  return cachedApiFetch("/pages?per_page=10", { cacheKey: "pages", maxAgeMs: 1000 * 60 * 5 });
}

export function fetchItems(type, options = {}) {
  const params = new URLSearchParams({
    type,
    per_page: String(options.perPage || 12)
  });

  if (options.page) {
    params.set("page", String(options.page));
  }

  if (options.search) {
    params.set("search", options.search);
  }

  return cachedApiFetch(`/items?${params.toString()}`, {
    cacheKey: `items:${params.toString()}`,
    maxAgeMs: 1000 * 60 * 5
  });
}

export function resolveContent(pathname) {
  if (isSessionSensitivePath(pathname)) {
    return apiFetch(`/resolve?path=${encodeURIComponent(pathname)}`);
  }

  return cachedApiFetch(`/resolve?path=${encodeURIComponent(pathname)}`, {
    cacheKey: `resolve:${pathname}`,
    maxAgeMs: 1000 * 60 * 5
  });
}

export function fetchPreviewContent(token) {
  return apiFetch(`/preview/${encodeURIComponent(token)}`);
}
