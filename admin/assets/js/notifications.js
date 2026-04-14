(function () {
  const toSafe = (v) => (v === null || v === undefined || v === "" ? "-" : String(v));
  const state = { page: 1, limit: 20, user: "", action: "", search: "", from: "", to: "" };
  let pendingDeleteId = null;

  async function loadAdminNotices() {
    const ui = window.AdminUI;
    ui.setLoading("notificationsLoading", true);
    try {
      const res = await window.API.get("/api/admin/notifications/logs", state);
      const payload = res.data || res;
      const rows = payload.items || [];
      const total = Number(payload.total || rows.length);
      const body = document.getElementById("adminNoticesBody");
      if (!body) return;
      body.innerHTML = rows.length ? rows.map((x) => `
        <tr>
          <td>#${toSafe(x.id)}</td>
          <td>${toSafe(x.created_at)}</td>
          <td>${toSafe(x.receiver_name)}<div class="small text-secondary">${toSafe(x.receiver_email)}</div></td>
          <td><span class="badge text-bg-primary">${toSafe(x.type)}</span></td>
          <td>${toSafe(x.message)}</td>
          <td>${x.image_url ? `<a href="${x.image_url}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>` : '<span class="text-secondary small">No image</span>'}</td>
          <td class="text-end">
            <button class="btn btn-outline-primary btn-sm" data-action="edit" data-id="${x.id}">Edit</button>
            <button class="btn btn-outline-warning btn-sm" data-action="resend" data-id="${x.id}">Resend</button>
            <button class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${x.id}">Delete</button>
          </td>
        </tr>
      `).join("") : `<tr><td colspan="7" class="text-center text-secondary">No admin notifications found.</td></tr>`;

      ui.setEmpty("notificationsEmpty", rows.length === 0);
      document.getElementById("notificationsPageInfo").textContent = `Page ${state.page} · Total ${total}`;
      document.getElementById("notificationsPrev").disabled = state.page <= 1;
      document.getElementById("notificationsNext").disabled = rows.length < state.limit;
    } catch (err) {
      window.AdminUI.showToast(err.message, "error");
    } finally {
      ui.setLoading("notificationsLoading", false);
    }
  }

  function bindFilters() {
    document.getElementById("notifApplyBtn")?.addEventListener("click", () => {
      state.page = 1;
      state.user = (document.getElementById("notifFilterUser")?.value || "").trim();
      state.action = (document.getElementById("notifFilterAction")?.value || "").trim();
      state.search = (document.getElementById("notifFilterSearch")?.value || "").trim();
      state.from = document.getElementById("notifFilterFrom")?.value || "";
      state.to = document.getElementById("notifFilterTo")?.value || "";
      loadAdminNotices();
    });
    document.getElementById("notifResetBtn")?.addEventListener("click", () => {
      ["notifFilterUser", "notifFilterAction", "notifFilterSearch", "notifFilterFrom", "notifFilterTo"].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.value = "";
      });
      state.page = 1;
      state.user = "";
      state.action = "";
      state.search = "";
      state.from = "";
      state.to = "";
      loadAdminNotices();
    });
    document.getElementById("notificationsPrev")?.addEventListener("click", () => { if (state.page > 1) state.page -= 1; loadAdminNotices(); });
    document.getElementById("notificationsNext")?.addEventListener("click", () => { state.page += 1; loadAdminNotices(); });
  }

  function bindSendModal() {
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

    document.getElementById("openSendNotificationBtn")?.addEventListener("click", () => {
      bootstrap.Modal.getOrCreateInstance(document.getElementById("notifyModal")).show();
    });

    form?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const sendBtn = document.getElementById("notifySendBtn");
      if (sendBtn) sendBtn.disabled = true;
      const mode = modeEl?.value || "single";
      const fd = new FormData();
      fd.append("mode", mode);
      fd.append("title", (document.getElementById("notifyTitle")?.value || "Admin Notice").trim());
      fd.append("message", (document.getElementById("notifyMessage")?.value || "").trim());
      if (mode === "single") fd.append("user_id", String(Number(userSelect?.value || 0)));
      const imageInput = document.getElementById("notifyImage");
      const file = imageInput?.files?.[0];
      if (file) fd.append("image", file);

      try {
        const res = await window.API.post("/api/admin/notifications/send", fd);
        window.AdminUI.showToast(res.message || "Notification sent");
        const d = res.data || {};
        if ((d.push_failed || 0) > 0 || (d.push_skipped || 0) > 0) {
          window.AdminUI.showToast(`Push issue: failed=${d.push_failed || 0}, skipped=${d.push_skipped || 0}`, "warning");
        }
        document.getElementById("notifyMessage").value = "";
        if (imageInput) imageInput.value = "";
        bootstrap.Modal.getOrCreateInstance(document.getElementById("notifyModal")).hide();
        loadAdminNotices();
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      } finally {
        if (sendBtn) sendBtn.disabled = false;
      }
    });
  }

  function bindActions() {
    document.getElementById("adminNoticesBody")?.addEventListener("click", async (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;
      const action = btn.dataset.action;
      const id = btn.dataset.id;
      if (!id) return;
      try {
        if (action === "edit") {
          const res = await window.API.get(`/api/admin/notifications/${id}`);
          const d = res.data || {};
          document.getElementById("notifEditId").value = d.id || id;
          document.getElementById("notifEditType").value = d.type || "admin_notice";
          document.getElementById("notifEditMessage").value = d.message || "";
          document.getElementById("notifEditImageUrl").value = d.image_url || "";
          bootstrap.Modal.getOrCreateInstance(document.getElementById("notifEditModal")).show();
        }
        if (action === "resend") {
          const res = await window.API.post(`/api/admin/notifications/${id}/resend`, {});
          window.AdminUI.showToast(res.message || "Notification resent");
          loadAdminNotices();
        }
        if (action === "delete") {
          pendingDeleteId = id;
          const row = btn.closest("tr");
          const preview = document.getElementById("notifDeletePreview");
          if (preview) preview.textContent = row ? row.innerText.replace(/\s+/g, " ").slice(0, 220) : `Notification #${id}`;
          bootstrap.Modal.getOrCreateInstance(document.getElementById("notifDeleteModal")).show();
        }
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      }
    });

    document.getElementById("notifEditForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = document.getElementById("notifEditId").value;
      const payload = {
        type: document.getElementById("notifEditType").value.trim(),
        message: document.getElementById("notifEditMessage").value.trim(),
        image_url: document.getElementById("notifEditImageUrl").value.trim(),
      };
      try {
        await window.API.put(`/api/admin/notifications/${id}`, payload);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("notifEditModal")).hide();
        window.AdminUI.showToast("Notification updated");
        loadAdminNotices();
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      }
    });

    document.getElementById("confirmNotifDelete")?.addEventListener("click", async () => {
      if (!pendingDeleteId) return;
      try {
        await window.API.delete(`/api/admin/notifications/${pendingDeleteId}`);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("notifDeleteModal")).hide();
        pendingDeleteId = null;
        window.AdminUI.showToast("Notification deleted");
        loadAdminNotices();
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      }
    });

    document.getElementById("refreshAdminNotices")?.addEventListener("click", loadAdminNotices);
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (document.body.dataset.page !== "notifications") return;
    bindFilters();
    bindSendModal();
    bindActions();
    loadAdminNotices();
  });
})();
