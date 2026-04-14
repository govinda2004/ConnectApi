(function () {
  let resource = "users";
  const USERS_HIDDEN_FIELDS = ["password", "firebase_uid", "device_token", "fcm_token"];

  function formatValue(value) {
    if (value === null || value === undefined) return "";
    if (typeof value === "object") return JSON.stringify(value);
    return String(value);
  }

  function getVisibleKeys(rows) {
    if (!rows.length) return [];
    const keys = Object.keys(rows[0]);
    if (resource !== "users") return keys;
    return keys.filter((k) => !USERS_HIDDEN_FIELDS.includes(k));
  }

  function renderUserDetails(details) {
    const box = document.getElementById("dataViewDetails");
    if (!box) return;
    const entries = Object.entries(details || {});
    box.innerHTML = entries.map(([k, v]) => `
      <div class="d-flex justify-content-between border-bottom py-2 gap-3">
        <div class="text-secondary">${k}</div>
        <div class="text-end fw-semibold">${formatValue(v) || "-"}</div>
      </div>
    `).join("");
  }

  function renderUserActivity(items) {
    const list = document.getElementById("dataViewActivity");
    if (!list) return;
    if (!items.length) {
      list.innerHTML = `<li class="list-group-item text-secondary">No recent activity found.</li>`;
      return;
    }
    list.innerHTML = items.map((x) => `
      <li class="list-group-item">
        <div class="small text-secondary">${x.created_at || "-"}</div>
        <div class="fw-semibold">${x.action || x.event || "-"}</div>
        <div class="small">${x.event || x.message || ""}</div>
      </li>
    `).join("");
  }

  async function openUserView(userId) {
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById("dataViewModal"));
    document.getElementById("dataViewLoading")?.classList.remove("d-none");
    document.getElementById("dataViewDetails").innerHTML = "";
    document.getElementById("dataViewActivity").innerHTML = "";
    modal.show();
    try {
      const [detailRes, activityRes] = await Promise.all([
        window.API.get(`/api/admin/users/${userId}`),
        window.API.get(`/api/admin/users/${userId}/activity`),
      ]);
      renderUserDetails(detailRes.data || detailRes || {});
      const items = activityRes.data?.items || activityRes.data || [];
      renderUserActivity(Array.isArray(items) ? items : []);
    } catch (err) {
      window.AdminUI.showToast(err.message, "error");
    } finally {
      document.getElementById("dataViewLoading")?.classList.add("d-none");
    }
  }

  function buildTable(rows) {
    const head = document.getElementById("dataHead");
    const body = document.getElementById("dataBody");
    head.innerHTML = "";
    body.innerHTML = "";
    if (!rows.length) return;

    const keys = getVisibleKeys(rows);
    head.innerHTML = `<tr>${keys.map((k) => `<th>${k}</th>`).join("")}<th class="text-end">Actions</th></tr>`;
    rows.forEach((r) => {
      const id = r.id ?? r._id ?? "";
      const tr = document.createElement("tr");
      tr.dataset.row = JSON.stringify(r);
      const viewBtn = resource === "users" ? `<button class="btn btn-outline-info" data-action="view" data-id="${id}">View</button> ` : "";
      tr.innerHTML = `${keys.map((k) => `<td>${formatValue(r[k])}</td>`).join("")}<td class="text-end">${viewBtn}<button class="btn btn-outline-primary" data-action="edit" data-id="${id}">Edit</button> <button class="btn btn-outline-danger" data-action="delete" data-id="${id}">Delete</button></td>`;
      body.appendChild(tr);
    });
  }

  async function loadData() {
    const ui = window.AdminUI;
    ui.setLoading("dataLoading", true);
    try {
      resource = document.getElementById("dataResource").value;
      const q = document.getElementById("dataSearch").value.trim();
      const res = await window.API.get(`/api/admin/data/${resource}`, { q, limit: 100 });
      const rows = res.data?.items || res.data || [];
      buildTable(Array.isArray(rows) ? rows : []);
      ui.setEmpty("dataEmpty", !Array.isArray(rows) || rows.length === 0);
    } catch (e) {
      ui.showToast(e.message, "error");
    } finally {
      ui.setLoading("dataLoading", false);
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (document.body.dataset.page !== "data") return;
    document.getElementById("dataLoadBtn")?.addEventListener("click", loadData);
    document.getElementById("exportDataCsv")?.addEventListener("click", () => window.AdminUI.exportTableToCsv("dynamicDataTable", `${resource}.csv`));
    document.getElementById("dataBody")?.addEventListener("click", async (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;
      const action = btn.dataset.action;
      const id = btn.dataset.id;
      const row = JSON.parse(btn.closest("tr").dataset.row || "{}");
      if (action === "view" && resource === "users") {
        openUserView(id);
        return;
      }
      if (action === "edit") {
        document.getElementById("dataRecordId").value = row.id ?? row._id ?? "";
        document.getElementById("dataRecordJson").value = JSON.stringify(row, null, 2);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("dataEditModal")).show();
      }
      if (action === "delete") {
        if (!confirm("Delete this record?")) return;
        try { await window.API.delete(`/api/admin/data/${resource}/${id}`); window.AdminUI.showToast("Record deleted"); loadData(); } catch (err) { window.AdminUI.showToast(err.message, "error"); }
      }
    });
    document.getElementById("saveDataRecord")?.addEventListener("click", async () => {
      const id = document.getElementById("dataRecordId").value;
      try {
        const payload = JSON.parse(document.getElementById("dataRecordJson").value);
        await window.API.put(`/api/admin/data/${resource}/${id}`, payload);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("dataEditModal")).hide();
        window.AdminUI.showToast("Record updated");
        loadData();
      } catch (err) {
        window.AdminUI.showToast(err.message || "Invalid JSON", "error");
      }
    });
    loadData();
  });
})();
