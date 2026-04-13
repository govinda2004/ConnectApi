(function () {
  async function loadDashboard() {
    const ui = window.AdminUI;
    ui.setLoading("dashboardLoading", true);
    try {
      const [statsRes, activityRes] = await Promise.all([
        window.API.get("/api/admin/dashboard/stats"),
        window.API.get("/api/admin/activity/recent", { limit: 8 }),
      ]);
      const stats = statsRes.data || statsRes;
      document.getElementById("statUsers").textContent = stats.total_users ?? 0;
      document.getElementById("statAdmins").textContent = stats.total_admins ?? 0;
      document.getElementById("statActive").textContent = stats.active_users ?? 0;
      document.getElementById("statRecords").textContent = stats.total_records ?? 0;

      const rows = activityRes.data?.items || activityRes.data || [];
      const body = document.getElementById("recentActivityBody");
      body.innerHTML = "";
      rows.forEach((r) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `<td>${r.created_at || "-"}</td><td>${r.actor_name || r.user_name || "System"}</td><td>${r.action || r.event || "-"}</td><td>${r.target || r.target_type || "-"}</td>`;
        body.appendChild(tr);
      });
      ui.setEmpty("dashboardEmpty", rows.length === 0);
    } catch (e) {
      ui.showToast(e.message, "error");
    } finally {
      ui.setLoading("dashboardLoading", false);
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (document.body.dataset.page !== "dashboard") return;
    document.getElementById("refreshDashboard")?.addEventListener("click", loadDashboard);
    loadDashboard();
  });
})();
