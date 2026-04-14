(function () {
  const THEME_KEY = "SUPER_ADMIN_THEME";
  const SIDEBAR_KEY = "SUPER_ADMIN_SIDEBAR";
  const MOBILE_BP = 992;

  function showToast(message, type = "success") {
    let container = document.getElementById("globalToastContainer");
    if (!container) {
      container = document.createElement("div");
      container.id = "globalToastContainer";
      container.className = "toast-container position-fixed top-0 end-0 p-3";
      document.body.appendChild(container);
    }
    const id = `toast-${Date.now()}`;
    const cls = type === "error" ? "text-bg-danger" : (type === "warning" ? "text-bg-warning" : "text-bg-success");
    container.insertAdjacentHTML("beforeend", `<div id="${id}" class="toast ${cls} border-0"><div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`);
    const el = document.getElementById(id);
    new bootstrap.Toast(el, { delay: 2500 }).show();
    el.addEventListener("hidden.bs.toast", () => el.remove());
  }

  const setLoading = (id, v) => { const el = document.getElementById(id); if (el) el.classList.toggle("d-none", !v); };
  const setEmpty = (id, v) => { const el = document.getElementById(id); if (el) el.classList.toggle("d-none", !v); };

  async function loadComponent(targetId, filePath) {
    const target = document.getElementById(targetId);
    if (!target) return;
    const res = await fetch(filePath);
    target.innerHTML = await res.text();
  }

  function applyTheme() { document.body.classList.toggle("theme-dark", (localStorage.getItem(THEME_KEY) || "light") === "dark"); }
  function toggleTheme() { const dark = document.body.classList.toggle("theme-dark"); localStorage.setItem(THEME_KEY, dark ? "dark" : "light"); }
  function isMobile() { return window.innerWidth < MOBILE_BP; }
  function applySidebar() {
    const saved = localStorage.getItem(SIDEBAR_KEY);
    const collapsed = saved ? saved === "collapsed" : true;
    document.body.classList.toggle("sidebar-collapsed", collapsed);
    if (isMobile()) document.body.classList.remove("mobile-sidebar-open");
  }
  function toggleSidebar() {
    if (isMobile()) {
      document.body.classList.toggle("mobile-sidebar-open");
      return;
    }
    const c = document.body.classList.toggle("sidebar-collapsed");
    localStorage.setItem(SIDEBAR_KEY, c ? "collapsed" : "expanded");
  }

  function setActiveNav() {
    const page = document.body.dataset.page;
    document.querySelectorAll("#sidebarNav .nav-link").forEach((a) => a.classList.toggle("active", a.dataset.page === page));
  }

  function initHeader() {
    document.getElementById("logoutBtn")?.addEventListener("click", window.Auth.logout);
    document.getElementById("darkModeToggle")?.addEventListener("click", toggleTheme);
    document.getElementById("sidebarToggle")?.addEventListener("click", toggleSidebar);
    const admin = window.Auth.getAdmin();
    const label = document.getElementById("headerAdminName");
    if (label) label.textContent = admin?.name || admin?.email || "Super Admin";
  }

  function initMobileSidebar() {
    let backdrop = document.getElementById("sidebarBackdrop");
    if (!backdrop) {
      backdrop = document.createElement("div");
      backdrop.id = "sidebarBackdrop";
      backdrop.className = "sidebar-backdrop";
      document.body.appendChild(backdrop);
    }
    backdrop.addEventListener("click", () => document.body.classList.remove("mobile-sidebar-open"));
    document.querySelectorAll("#sidebarNav .nav-link").forEach((a) => {
      a.addEventListener("click", () => {
        if (isMobile()) document.body.classList.remove("mobile-sidebar-open");
      });
    });
  }

  function exportTableToCsv(tableId, fileName = "export.csv") {
    const table = document.getElementById(tableId);
    if (!table) return;
    const rows = [...table.querySelectorAll("tr")].map((tr) => [...tr.querySelectorAll("th,td")].map((td) => `"${(td.innerText || "").replace(/"/g, '""')}"`).join(","));
    const blob = new Blob([rows.join("\\n")], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url; a.download = fileName; a.click();
    URL.revokeObjectURL(url);
  }

  async function initSettingsPage() {
    if (document.body.dataset.page !== "settings") return;
    try {
      const profile = (await window.API.get("/api/admin/profile")).data || {};
      document.getElementById("profileName").value = profile.name || "";
      document.getElementById("profileEmail").value = profile.email || "";

      // Load editable HTML content for app settings pages.
      const contentRes = await window.API.get("/api/admin/data/app_contents", { limit: 100 });
      const items = contentRes.data?.items || [];
      const byKey = {};
      (Array.isArray(items) ? items : []).forEach((x) => {
        if (x && x.content_key) byKey[String(x.content_key)] = x;
      });
      if (byKey.terms_conditions) {
        document.getElementById("termsTitle").value = byKey.terms_conditions.title || "Terms & Conditions";
        document.getElementById("termsHtml").value = byKey.terms_conditions.html_content || "";
      }
      if (byKey.about) {
        document.getElementById("aboutTitle").value = byKey.about.title || "About ConnectIn";
        document.getElementById("aboutHtml").value = byKey.about.html_content || "";
      }
      if (byKey.help_support) {
        document.getElementById("helpTitle").value = byKey.help_support.title || "Help & Support";
        document.getElementById("helpHtml").value = byKey.help_support.html_content || "";
      }
    } catch (e) { showToast(e.message, "error"); }

    document.getElementById("profileForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        await window.API.put("/api/admin/profile", { name: document.getElementById("profileName").value.trim(), email: document.getElementById("profileEmail").value.trim() });
        showToast("Profile updated");
      } catch (err) { showToast(err.message, "error"); }
    });

    document.getElementById("passwordForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        await window.API.post("/api/admin/change-password", { current_password: document.getElementById("currentPassword").value, new_password: document.getElementById("newPassword").value });
        showToast("Password changed");
      } catch (err) { showToast(err.message, "error"); }
    });

    document.getElementById("appSettingsForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        await window.API.put("/api/admin/settings", {
          maintenance_mode: document.getElementById("maintenanceMode").value === "true",
          registration_enabled: document.getElementById("registrationEnabled").value === "true",
          default_page_size: Number(document.getElementById("defaultPageSize").value || 20),
        });
        showToast("Settings updated");
      } catch (err) { showToast(err.message, "error"); }
    });

    document.getElementById("appContentForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        await window.API.put("/api/admin/settings", {
          app_contents: {
            terms_conditions: {
              title: document.getElementById("termsTitle").value.trim(),
              html_content: document.getElementById("termsHtml").value,
            },
            about: {
              title: document.getElementById("aboutTitle").value.trim(),
              html_content: document.getElementById("aboutHtml").value,
            },
            help_support: {
              title: document.getElementById("helpTitle").value.trim(),
              html_content: document.getElementById("helpHtml").value,
            },
          },
        });
        showToast("App HTML content updated");
      } catch (err) {
        showToast(err.message, "error");
      }
    });
  }

  async function boot() {
    if (location.pathname.endsWith("index.html")) return;
    if (!window.Auth.requireAuth()) return;
    applyTheme();
    applySidebar();
    await loadComponent("sidebarContainer", "components/sidebar.html");
    await loadComponent("headerContainer", "components/header.html");
    setActiveNav();
    initHeader();
    initMobileSidebar();
    await initSettingsPage();
    window.addEventListener("resize", () => {
      if (!isMobile()) document.body.classList.remove("mobile-sidebar-open");
    });
  }

  document.addEventListener("DOMContentLoaded", boot);
  window.AdminUI = { showToast, setLoading, setEmpty, exportTableToCsv };
})();
