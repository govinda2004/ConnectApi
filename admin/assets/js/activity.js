(function () {
  const state = { page: 1, limit: 25, user: "", action: "", from: "", to: "" };
  const formatMeta = (meta) => {
    if (!meta || typeof meta !== "object") return "-";
    const keys = Object.keys(meta);
    if (!keys.length) return "-";
    return keys.slice(0, 2).map((k) => `${k}: ${meta[k]}`).join(" | ");
  };

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
        tr.innerHTML = `<td>${x.created_at || "-"}</td><td>${x.actor_name || x.user_name || "System"}</td><td>${x.actor_type || "-"}</td><td>${x.action || x.event || "-"}</td><td><small>${formatMeta(x.meta || x.data || {})}</small></td>`;
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

    const modeEl = document.getElementById("notifyMode");
    const userWrap = document.getElementById("notifyUserWrap");
    const userSelect = document.getElementById("notifyUserId");
    const form = document.getElementById("notifyForm");

    const toggleMode = () => {
      const mode = modeEl?.value || "single";
      userWrap?.classList.toggle("d-none", mode === "all");
    };

    modeEl?.addEventListener("change", toggleMode);
    toggleMode();

    window.API.get("/api/admin/users", { page: 1, limit: 200 }).then((res) => {
      const users = res.data?.items || [];
      users.forEach((u) => {
        const opt = document.createElement("option");
        opt.value = u.id;
        opt.textContent = `${u.name || "User"} (${u.email || "-"})`;
        userSelect?.appendChild(opt);
      });
    }).catch(() => {});

    form?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const sendBtn = document.getElementById("notifySendBtn");
      if (sendBtn) sendBtn.disabled = true;
      const mode = modeEl?.value || "single";
      const payload = {
        mode,
        title: (document.getElementById("notifyTitle")?.value || "Admin Notice").trim(),
        message: (document.getElementById("notifyMessage")?.value || "").trim(),
      };
      if (mode === "single") payload.user_id = Number(userSelect?.value || 0);
      try {
        const res = await window.API.post("/api/admin/notifications/send", payload);
        window.AdminUI.showToast(res.message || "Notification sent");
        const d = res.data || {};
        if ((d.push_failed || 0) > 0 || (d.push_skipped || 0) > 0) {
          window.AdminUI.showToast(`Push issue: failed=${d.push_failed || 0}, skipped=${d.push_skipped || 0}`, "warning");
        }
        document.getElementById("notifyMessage").value = "";
        loadActivity();
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      } finally {
        if (sendBtn) sendBtn.disabled = false;
      }
    });

    loadActivity();
  });
})();
