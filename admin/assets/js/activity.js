(function () {
  const state = { page: 1, limit: 25, user: "", action: "", from: "", to: "" };

  async function loadActivity() {
    const ui = window.AdminUI;
    ui.setLoading("activityLoading", true);
    try {
      const res = await window.API.get("/api/admin/activity/logs", state);
      const payload = res.data || res;
      const rows = payload.items || payload.logs || [];
      const total = payload.total || rows.length;
      const body = document.getElementById("activityBody");
      body.innerHTML = "";
      rows.forEach((x) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `<td>${x.created_at || "-"}</td><td>${x.actor_name || x.user_name || "System"}</td><td>${x.actor_type || "-"}</td><td>${x.action || x.event || "-"}</td><td><small>${JSON.stringify(x.meta || x.data || {})}</small></td>`;
        body.appendChild(tr);
      });
      ui.setEmpty("activityEmpty", rows.length === 0);
      document.getElementById("activityPageInfo").textContent = `Page ${state.page} · Total ${total}`;
      document.getElementById("activityPrev").disabled = state.page <= 1;
      document.getElementById("activityNext").disabled = rows.length < state.limit;
    } catch (e) {
      ui.showToast(e.message, "error");
    } finally {
      ui.setLoading("activityLoading", false);
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (document.body.dataset.page !== "activity") return;
    document.getElementById("activityFilterBtn")?.addEventListener("click", () => {
      state.page = 1;
      state.user = document.getElementById("activityUser").value.trim();
      state.action = document.getElementById("activityAction").value.trim();
      state.from = document.getElementById("activityFrom").value;
      state.to = document.getElementById("activityTo").value;
      loadActivity();
    });
    document.getElementById("activityPrev")?.addEventListener("click", () => { if (state.page > 1) state.page -= 1; loadActivity(); });
    document.getElementById("activityNext")?.addEventListener("click", () => { state.page += 1; loadActivity(); });
    loadActivity();
  });
})();
