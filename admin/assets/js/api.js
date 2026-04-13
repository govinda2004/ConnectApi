(function () {
  const BASE = window.ADMIN_API_BASE || localStorage.getItem("ADMIN_API_BASE") || "";

  function query(params = {}) {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && String(v).trim() !== "") q.append(k, v);
    });
    const s = q.toString();
    return s ? `?${s}` : "";
  }

  async function request(path, options = {}, withAuth = true) {
    const token = localStorage.getItem("SUPER_ADMIN_TOKEN");
    const headers = new Headers(options.headers || {});
    if (!headers.has("Content-Type") && !(options.body instanceof FormData)) {
      headers.set("Content-Type", "application/json");
    }
    if (withAuth && token) headers.set("Authorization", `Bearer ${token}`);

    let res;
    try {
      res = await fetch(`${BASE}${path}`, { ...options, headers });
    } catch {
      throw new Error("Network error");
    }

    const txt = await res.text();
    let data = {};
    try { data = txt ? JSON.parse(txt) : {}; } catch { data = { message: txt || "Invalid response" }; }

    if (res.status === 401) {
      window.dispatchEvent(new CustomEvent("admin:unauthorized"));
      throw new Error(data.message || "Unauthorized");
    }
    if (!res.ok) throw new Error(data.message || `Request failed ${res.status}`);
    return data;
  }

  window.API = {
    toQuery: query,
    get: (p, a = {}, auth = true) => request(`${p}${query(a)}`, { method: "GET" }, auth),
    post: (p, b = {}, auth = true) => request(p, { method: "POST", body: b instanceof FormData ? b : JSON.stringify(b) }, auth),
    put: (p, b = {}, auth = true) => request(p, { method: "PUT", body: JSON.stringify(b) }, auth),
    patch: (p, b = {}, auth = true) => request(p, { method: "PATCH", body: JSON.stringify(b) }, auth),
    delete: (p, b = null, auth = true) => request(p, { method: "DELETE", body: b ? JSON.stringify(b) : undefined }, auth),
  };
})();
