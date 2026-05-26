(function () {
  const TOKEN_KEY = "SUPER_ADMIN_TOKEN";
  const ADMIN_KEY = "SUPER_ADMIN_PROFILE";

  const getToken = () => localStorage.getItem(TOKEN_KEY);
  const isAuthenticated = () => !!getToken();

  function getAdmin() {
    try { return JSON.parse(localStorage.getItem(ADMIN_KEY) || "null"); } catch { return null; }
  }

  function setSession(token, admin) {
    localStorage.setItem(TOKEN_KEY, token);
    if (admin) localStorage.setItem(ADMIN_KEY, JSON.stringify(admin));
  }

  function clearSession() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(ADMIN_KEY);
  }

  async function login(credentials) {
    const res = await window.API.post("/api/admin/login", credentials, false);
    const token = res?.token || res?.data?.token;
    const admin = res?.admin || res?.data?.admin || null;
    if (!token) {
      console.error("Admin login: token missing in response", res);
      const detail = res?.message || res?.data?.message || (typeof res === "string" ? res : JSON.stringify(res));
      throw new Error(`Token missing. API response: ${detail || "empty response"}`);
    }
    setSession(token, admin);
    return { token, admin };
  }

  async function bootstrapSuperAdmin(payload) {
    const res = await window.API.post("/api/admin/bootstrap", payload, false);
    const token = res?.token || res?.data?.token;
    const admin = res?.admin || res?.data?.admin || null;
    if (!token) {
      console.error("Admin bootstrap: token missing in response", res);
      const detail = res?.message || res?.data?.message || (typeof res === "string" ? res : JSON.stringify(res));
      throw new Error(`Token missing. API response: ${detail || "empty response"}`);
    }


    setSession(token, admin);
    return { token, admin };
  }

  async function bootstrapSuperAdmin(payload) {
    const res = await window.API.post("/api/admin/bootstrap", payload, false);
    const token = res?.token || res?.data?.token;
    const admin = res?.admin || res?.data?.admin || null;
    if (!token) throw new Error("Token missing");
    setSession(token, admin);
    return { token, admin };
  }

  function logout() {
    clearSession();
    if (!location.pathname.endsWith("index.html")) location.href = "index.html";
  }

  function requireAuth() {
    if (!isAuthenticated()) {
      location.href = "index.html";
      return false;
    }
    return true;
  }

  window.addEventListener("admin:unauthorized", logout);
  window.Auth = { getToken, isAuthenticated, getAdmin, login, bootstrapSuperAdmin, logout, requireAuth };
})();
