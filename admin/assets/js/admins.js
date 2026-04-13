(function () {
  const modalEl = () => document.getElementById("adminModal");
  const modal = () => bootstrap.Modal.getOrCreateInstance(modalEl());

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
        document.getElementById("adminRole").value = a.role || "";
        document.getElementById("adminPermissions").value = Array.isArray(a.permissions) ? a.permissions.join(",") : (a.permissions || "");
        document.getElementById("adminModalTitle").textContent = "Edit Admin";
        modal().show();
      }
      if (action === "delete") {
        if (!confirm("Delete this admin?")) return;
        try { await window.API.delete(`/api/admin/admins/${id}`); window.AdminUI.showToast("Admin deleted"); loadAdmins(); } catch (err) { window.AdminUI.showToast(err.message, "error"); }
      }
    });

    document.getElementById("adminForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = document.getElementById("adminId").value;
      const payload = {
        name: document.getElementById("adminName").value.trim(),
        email: document.getElementById("adminEmail").value.trim(),
        role: document.getElementById("adminRole").value.trim(),
        permissions: document.getElementById("adminPermissions").value.split(",").map((x) => x.trim()).filter(Boolean),
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

    loadAdmins();
  });
})();
