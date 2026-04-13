(function () {
  let resource = "users";

  function buildTable(rows) {
    const head = document.getElementById("dataHead");
    const body = document.getElementById("dataBody");
    head.innerHTML = "";
    body.innerHTML = "";
    if (!rows.length) return;

    const keys = Object.keys(rows[0]);
    head.innerHTML = `<tr>${keys.map((k) => `<th>${k}</th>`).join("")}<th class="text-end">Actions</th></tr>`;
    rows.forEach((r) => {
      const id = r.id ?? r._id ?? "";
      const tr = document.createElement("tr");
      tr.dataset.row = JSON.stringify(r);
      tr.innerHTML = `${keys.map((k) => `<td>${typeof r[k] === "object" ? JSON.stringify(r[k]) : (r[k] ?? "")}</td>`).join("")}<td class="text-end"><button class="btn btn-outline-primary" data-action="edit" data-id="${id}">Edit</button> <button class="btn btn-outline-danger" data-action="delete" data-id="${id}">Delete</button></td>`;
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
