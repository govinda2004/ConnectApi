(function () {
  const state = { page: 1, limit: 20, search: "", status: "" };
  const toSafe = (v) => (v === null || v === undefined || v === "" ? "-" : String(v));

  function renderUserDetailCard(user) {
    const status = (user.status || "unknown").toLowerCase();
    const statusClass = status === "active" ? "text-bg-success" : "text-bg-secondary";
    const name = toSafe(user.name);
    const email = toSafe(user.email);
    const avatar = (name && name !== "-" ? name[0] : "U").toUpperCase();

    const avatarEl = document.getElementById("userDetailAvatar");
    const nameEl = document.getElementById("userDetailName");
    const emailEl = document.getElementById("userDetailEmail");
    const statusEl = document.getElementById("userDetailStatus");
    const contentEl = document.getElementById("userDetailContent");

    if (avatarEl) avatarEl.textContent = avatar;
    if (nameEl) nameEl.textContent = name;
    if (emailEl) emailEl.textContent = email;
    if (statusEl) {
      statusEl.className = `badge ${statusClass}`;
      statusEl.textContent = status;
    }

    const fields = [
      ["User ID", user.id],
      ["Name", user.name],
      ["Email", user.email],
      ["Phone", user.phone],
      ["Account Type", user.account_type],
      ["Created At", user.created_at],
      ["Status", user.status],
      ["is_active", user.is_active],
    ];
    if (contentEl) {
      contentEl.innerHTML = fields.map(([k, v]) => `
        <div class="d-flex justify-content-between border-bottom py-2 gap-3">
          <div class="text-secondary">${k}</div>
          <div class="text-end fw-semibold">${toSafe(v)}</div>
        </div>
      `).join("");
    }
  }

  async function loadUsers() {
    const ui = window.AdminUI;
    ui.setLoading("usersLoading", true);
    try {
      const res = await window.API.get("/api/admin/users", state);
      const payload = res.data || res;
      const rows = payload.items || payload.users || [];
      const total = payload.total || rows.length;
      const body = document.getElementById("usersBody");
      body.innerHTML = "";
      rows.forEach((u) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `<td>${u.id}</td><td>${u.name || "-"}</td><td>${u.email || "-"}</td><td><span class="badge ${u.status === "active" ? "text-bg-success" : "text-bg-secondary"}">${u.status || "unknown"}</span></td><td>${u.created_at || "-"}</td><td class="text-end"><button class="btn btn-outline-primary" data-action="view" data-id="${u.id}">View</button> <button class="btn btn-outline-info" data-action="activity" data-id="${u.id}">Activity</button> <button class="btn btn-outline-warning" data-action="toggle" data-id="${u.id}" data-status="${u.status || ""}">${u.status === "active" ? "Deactivate" : "Activate"}</button> <button class="btn btn-outline-danger" data-action="delete" data-id="${u.id}">Delete</button></td>`;
        body.appendChild(tr);
      });
      ui.setEmpty("usersEmpty", rows.length === 0);
      document.getElementById("usersPageInfo").textContent = `Page ${state.page} · Total ${total}`;
      document.getElementById("usersPrev").disabled = state.page <= 1;
      document.getElementById("usersNext").disabled = rows.length < state.limit;
    } catch (e) {
      ui.showToast(e.message, "error");
    } finally {
      ui.setLoading("usersLoading", false);
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (document.body.dataset.page !== "users") return;
    document.getElementById("userFilterBtn")?.addEventListener("click", () => {
      state.page = 1;
      state.search = document.getElementById("userSearch").value.trim();
      state.status = document.getElementById("userStatusFilter").value;
      loadUsers();
    });
    document.getElementById("userResetBtn")?.addEventListener("click", () => {
      document.getElementById("userSearch").value = "";
      document.getElementById("userStatusFilter").value = "";
      state.page = 1; state.search = ""; state.status = "";
      loadUsers();
    });
    document.getElementById("usersPrev")?.addEventListener("click", () => { if (state.page > 1) state.page -= 1; loadUsers(); });
    document.getElementById("usersNext")?.addEventListener("click", () => { state.page += 1; loadUsers(); });
    document.getElementById("exportUsersCsv")?.addEventListener("click", () => window.AdminUI.exportTableToCsv("usersTable", "users.csv"));
    document.getElementById("usersBody")?.addEventListener("click", async (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;
      const action = btn.dataset.action;
      const id = btn.dataset.id;
      try {
        if (action === "view") {
          const detail = await window.API.get(`/api/admin/users/${id}`);
          renderUserDetailCard(detail.data || detail || {});
          bootstrap.Modal.getOrCreateInstance(document.getElementById("userDetailModal")).show();
        }
        if (action === "activity") {
          const activity = await window.API.get(`/api/admin/users/${id}/activity`);
          const items = activity.data?.items || activity.data || [];
          document.getElementById("userActivityList").innerHTML = items.map((x) => `
            <li class="list-group-item">
              <div class="small text-secondary">${toSafe(x.created_at)}</div>
              <div class="fw-semibold">${toSafe(x.action || x.event)}</div>
              <div class="small">${toSafe(x.message || x.event)}</div>
            </li>
          `).join("");
          bootstrap.Modal.getOrCreateInstance(document.getElementById("userActivityModal")).show();
        }
        if (action === "toggle") {
          await window.API.patch(`/api/admin/users/${id}/status`, { status: btn.dataset.status === "active" ? "inactive" : "active" });
          window.AdminUI.showToast("User status updated");
          loadUsers();
        }
        if (action === "delete") {
          if (!confirm("Delete this user?")) return;
          await window.API.delete(`/api/admin/users/${id}`);
          window.AdminUI.showToast("User deleted");
          loadUsers();
        }
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      }
    });
    loadUsers();
  });
})();
