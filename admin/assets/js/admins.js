(function () {
  const modalEl = () => document.getElementById("adminModal");
  const modal = () => bootstrap.Modal.getOrCreateInstance(modalEl());
  let pendingDeleteAdminId = null;

  function getSelectedPermissions() {
    const sel = document.getElementById("adminPermissions");
    if (!sel) return [];
    return Array.from(sel.selectedOptions).map((o) => o.value).filter(Boolean);
  }

  function setSelectedPermissions(values) {
    const set = new Set(Array.isArray(values) ? values : []);
    const sel = document.getElementById("adminPermissions");
    if (!sel) return;
    Array.from(sel.options).forEach((o) => { o.selected = set.has(o.value); });
  }

  async function loadAdmins() {
    const ui = window.AdminUI;
    ui.setLoading("adminsLoading", true);
    try {
      const res = await window.API.get("/api/admin/admins");
      const rows = res.data?.items || res.data || [];
      const body = document.getElementById("adminsBody");
      body.innerHTML = "";
      rows.forEach((a) => {
        const perms = Array.isArray(a.permissions) ? a.permissions.join(", ") : (a.permissions || "-");
        const tr = document.createElement("tr");
        tr.dataset.admin = JSON.stringify(a);
        tr.innerHTML = `<td>${a.id}</td><td>${a.name || "-"}</td><td>${a.email || "-"}</td><td>${a.role || "-"}</td><td>${perms}</td><td class="text-end"><button class="btn btn-outline-primary" data-action="edit" data-id="${a.id}">Edit</button> <button class="btn btn-outline-danger" data-action="delete" data-id="${a.id}">Delete</button></td>`;
        body.appendChild(tr);
      });
      ui.setEmpty("adminsEmpty", rows.length === 0);
    } catch (e) {
      ui.showToast(e.message, "error");
    } finally {
      ui.setLoading("adminsLoading", false);
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (document.body.dataset.page !== "admins") return;
    document.getElementById("openAddAdminModal")?.addEventListener("click", () => {
      document.getElementById("adminForm").reset();
      document.getElementById("adminId").value = "";
      document.getElementById("adminModalTitle").textContent = "Add Admin";
      setSelectedPermissions([]);
      modal().show();
    });

    document.getElementById("adminsBody")?.addEventListener("click", async (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;
      const action = btn.dataset.action;
      const id = btn.dataset.id;
      if (action === "edit") {
        const a = JSON.parse(btn.closest("tr").dataset.admin || "{}");
        document.getElementById("adminId").value = a.id || "";
        document.getElementById("adminName").value = a.name || "";
        document.getElementById("adminEmail").value = a.email || "";
        document.getElementById("adminPassword").value = "";
        document.getElementById("adminRole").value = a.role || "";
        setSelectedPermissions(Array.isArray(a.permissions) ? a.permissions : []);
        document.getElementById("adminModalTitle").textContent = "Edit Admin";
        modal().show();
      }
      if (action === "delete") {
        pendingDeleteAdminId = id;
        const row = btn.closest("tr");
        const p = document.getElementById("adminDeletePreview");
        if (p) p.textContent = row ? row.innerText.replace(/\s+/g, " ").slice(0, 220) : `Admin #${id}`;
        bootstrap.Modal.getOrCreateInstance(document.getElementById("adminDeleteModal")).show();
      }
    });

    document.getElementById("adminForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = document.getElementById("adminId").value;
      const payload = {
        name: document.getElementById("adminName").value.trim(),
        email: document.getElementById("adminEmail").value.trim(),
        password: document.getElementById("adminPassword").value,
        role: document.getElementById("adminRole").value.trim(),
        permissions: getSelectedPermissions(),
      };
      try {
        if (id) await window.API.put(`/api/admin/admins/${id}`, payload);
        else await window.API.post("/api/admin/admins", payload);
        window.AdminUI.showToast(`Admin ${id ? "updated" : "added"}`);
        modal().hide();
        loadAdmins();
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      }
    });

    document.getElementById("confirmDeleteAdmin")?.addEventListener("click", async () => {
      if (!pendingDeleteAdminId) return;
      try {
        await window.API.delete(`/api/admin/admins/${pendingDeleteAdminId}`);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("adminDeleteModal")).hide();
        window.AdminUI.showToast("Admin deleted");
        pendingDeleteAdminId = null;
        loadAdmins();
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      }
    });

    loadAdmins();
  });
})();
