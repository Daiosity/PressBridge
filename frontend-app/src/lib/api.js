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

async function apiFetch(path) {
  let response;

  try {
    response = await fetch(`${API_BASE}${path}`, {
      headers: {
        Accept: "application/json"
      }
    });
  } catch (networkError) {
    throw new Error(
      "Unable to reach the PressBridge API. Confirm WordPress is running, the plugin is active, and the API base URL is correct."
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
    const error = new Error("The PressBridge API returned an invalid JSON response.");
    error.status = response.status;
    throw error;
  }
}

export function fetchSiteConfig() {
  return apiFetch("/site");
}

export function fetchMenus() {
  return apiFetch("/menus");
}

export function fetchPosts() {
  return apiFetch("/posts?per_page=10");
}

export function fetchPages() {
  return apiFetch("/pages?per_page=10");
}

export function resolveContent(pathname) {
  return apiFetch(`/resolve?path=${encodeURIComponent(pathname)}`);
}

export function fetchPreviewContent(token) {
  return apiFetch(`/preview/${encodeURIComponent(token)}`);
}
