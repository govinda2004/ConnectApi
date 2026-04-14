(function () {
  const state = { page: 1, limit: 25, user: "", action: "", from: "", to: "" };
  let pendingDeleteActivityId = null;
  const formatMeta = (meta) => {
    if (!meta || typeof meta !== "object") return "-";
    const keys = Object.keys(meta);
    if (!keys.length) return "-";
    return keys.slice(0, 2).map((k) => `${k}: ${meta[k]}`).join(" | ");
  };
  const toSafe = (v) => (v === null || v === undefined || v === "" ? "-" : String(v));
  const actionBadge = (action) => {
    const a = String(action || "").toLowerCase();
    if (a.includes("admin_notice")) return "text-bg-primary";
    if (a.includes("connection")) return "text-bg-success";
    if (a.includes("comment")) return "text-bg-info";
    if (a.includes("like")) return "text-bg-warning";
    return "text-bg-secondary";
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
        tr.innerHTML = `
          <td>#${toSafe(x.id)}</td>
          <td>${toSafe(x.created_at)}</td>
          <td>${toSafe(x.actor_name)}<div class="small text-secondary">${toSafe(x.actor_email)}</div></td>
          <td>${toSafe(x.receiver_name)}<div class="small text-secondary">${toSafe(x.receiver_email)}</div></td>
          <td><span class="badge ${actionBadge(x.action)}">${toSafe(x.action || x.event)}</span></td>
          <td><small>${formatMeta(x.meta || x.data || {})}</small></td>
          <td class="text-end">
            <button class="btn btn-outline-info btn-sm" data-action="view" data-id="${x.id}">View</button>
            <button class="btn btn-outline-primary btn-sm" data-action="edit" data-id="${x.id}">Edit</button>
            <button class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${x.id}">Delete</button>
          </td>
        `;
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

    document.getElementById("activityBody")?.addEventListener("click", async (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;
      const action = btn.dataset.action;
      const id = btn.dataset.id;
      if (!id) return;
      try {
        if (action === "view") {
          const res = await window.API.get(`/api/admin/activity/${id}`);
          const d = res.data || {};
          const fields = [
            ["ID", d.id],
            ["Time", d.created_at],
            ["Action", d.type || d.action],
            ["Actor", d.actor_name],
            ["Actor Email", d.actor_email],
            ["Receiver", d.user_name],
            ["Receiver Email", d.user_email],
            ["Message", d.message],
            ["Image URL", d.image_url],
            ["Target ID", d.target_id],
            ["Read", d.is_read],
          ];
          const content = document.getElementById("activityViewContent");
          if (content) {
            content.innerHTML = fields.map(([k, v]) => `
              <div class="d-flex justify-content-between border-bottom py-2 gap-3">
                <div class="text-secondary">${k}</div>
                <div class="text-end fw-semibold">${toSafe(v)}</div>
              </div>
            `).join("");
          }
          bootstrap.Modal.getOrCreateInstance(document.getElementById("activityViewModal")).show();
        }
        if (action === "edit") {
          const res = await window.API.get(`/api/admin/activity/${id}`);
          const d = res.data || {};
          document.getElementById("editActivityId").value = d.id || id;
          document.getElementById("editActivityAction").value = d.type || d.action || "";
          document.getElementById("editActivityMessage").value = d.message || "";
          document.getElementById("editActivityImageUrl").value = d.image_url || "";
          bootstrap.Modal.getOrCreateInstance(document.getElementById("activityEditModal")).show();
        }
        if (action === "delete") {
          pendingDeleteActivityId = id;
          const row = btn.closest("tr");
          const p = document.getElementById("activityDeletePreview");
          if (p) p.textContent = row ? row.innerText.replace(/\s+/g, " ").slice(0, 220) : `Activity #${id}`;
          bootstrap.Modal.getOrCreateInstance(document.getElementById("activityDeleteModal")).show();
        }
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      }
    });

    document.getElementById("activityEditForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = document.getElementById("editActivityId").value;
      const payload = {
        action: document.getElementById("editActivityAction").value.trim(),
        message: document.getElementById("editActivityMessage").value.trim(),
        image_url: document.getElementById("editActivityImageUrl").value.trim(),
      };
      try {
        await window.API.put(`/api/admin/activity/${id}`, payload);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("activityEditModal")).hide();
        window.AdminUI.showToast("Activity updated");
        loadActivity();
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      }
    });

    document.getElementById("confirmDeleteActivity")?.addEventListener("click", async () => {
      if (!pendingDeleteActivityId) return;
      try {
        await window.API.delete(`/api/admin/activity/${pendingDeleteActivityId}`);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("activityDeleteModal")).hide();
        pendingDeleteActivityId = null;
        window.AdminUI.showToast("Activity deleted");
        loadActivity();
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      }
    });

    loadActivity();
  });
})();
