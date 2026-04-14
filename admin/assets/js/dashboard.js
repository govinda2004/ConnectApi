(function () {
  let statsChart;
  let activityChart;

  function renderCharts(stats, rows) {
    const barCtx = document.getElementById("statsBarChart");
    const donutCtx = document.getElementById("activityDonutChart");
    if (!barCtx || !donutCtx || typeof Chart === "undefined") return;

    if (statsChart) statsChart.destroy();
    if (activityChart) activityChart.destroy();

    statsChart = new Chart(barCtx, {
      type: "bar",
      data: {
        labels: ["Users", "Admins", "Active Users", "Records"],
        datasets: [{
          label: "Count",
          data: [
            Number(stats.total_users || 0),
            Number(stats.total_admins || 0),
            Number(stats.active_users || 0),
            Number(stats.total_records || 0),
          ],
          backgroundColor: ["#3b82f6", "#8b5cf6", "#10b981", "#f59e0b"],
          borderRadius: 8,
        }],
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
      },
    });

    const actionCount = {};
    rows.forEach((r) => {
      const a = r.action || "other";
      actionCount[a] = (actionCount[a] || 0) + 1;
    });
    const labels = Object.keys(actionCount).slice(0, 6);
    const values = labels.map((l) => actionCount[l]);
    if (!labels.length) {
      labels.push("No Activity");
      values.push(1);
    }

    activityChart = new Chart(donutCtx, {
      type: "doughnut",
      data: {
        labels,
        datasets: [{
          data: values,
          backgroundColor: ["#2563eb", "#7c3aed", "#059669", "#dc2626", "#ea580c", "#0891b2"],
        }],
      },
      options: {
        responsive: true,
        plugins: { legend: { position: "bottom" } },
      },
    });
  }

  function actionBadge(action) {
    const a = String(action || "").toLowerCase();
    if (a.includes("like")) return "text-bg-primary";
    if (a.includes("comment")) return "text-bg-info";
    if (a.includes("connection")) return "text-bg-success";
    if (a.includes("job")) return "text-bg-warning";
    return "text-bg-secondary";
  }

  async function loadDashboard() {
    const ui = window.AdminUI;
    ui.setLoading("dashboardLoading", true);
    try {
      const [statsRes, activityRes] = await Promise.all([
        window.API.get("/api/admin/dashboard/stats"),
        window.API.get("/api/admin/activity/logs", { page: 1, limit: 12 }),
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
        tr.innerHTML = `
          <td>#${r.id ?? "-"}</td>
          <td>${r.created_at || "-"}</td>
          <td>${r.actor_name || r.user_name || "System"}</td>
          <td><span class="badge ${actionBadge(r.action)}">${r.action || r.event || "-"}</span></td>
          <td>${(r.message || "").toString().slice(0, 80)}</td>
          <td class="text-end">
            <button class="btn btn-outline-info btn-sm" data-action="view" data-id="${r.id}">View</button>
            <button class="btn btn-outline-primary btn-sm" data-action="edit" data-id="${r.id}">Edit</button>
            <button class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${r.id}">Delete</button>
          </td>
        `;
        body.appendChild(tr);
      });
      ui.setEmpty("dashboardEmpty", rows.length === 0);
      renderCharts(stats, rows);
    } catch (e) {
      ui.showToast(e.message, "error");
    } finally {
      ui.setLoading("dashboardLoading", false);
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (document.body.dataset.page !== "dashboard") return;
    document.getElementById("refreshDashboard")?.addEventListener("click", loadDashboard);

    document.getElementById("recentActivityBody")?.addEventListener("click", async (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;
      const action = btn.dataset.action;
      const id = btn.dataset.id;
      if (!id) return;

      try {
        if (action === "view") {
          const res = await window.API.get(`/api/admin/activity/${id}`);
          document.getElementById("activityViewJson").textContent = JSON.stringify(res.data || res, null, 2);
          bootstrap.Modal.getOrCreateInstance(document.getElementById("activityViewModal")).show();
        }
        if (action === "edit") {
          const res = await window.API.get(`/api/admin/activity/${id}`);
          const d = res.data || {};
          document.getElementById("editActivityId").value = d.id || id;
          document.getElementById("editActivityAction").value = d.type || d.action || "";
          document.getElementById("editActivityMessage").value = d.message || "";
          bootstrap.Modal.getOrCreateInstance(document.getElementById("activityEditModal")).show();
        }
        if (action === "delete") {
          if (!confirm("Delete this activity log?")) return;
          await window.API.delete(`/api/admin/activity/${id}`);
          window.AdminUI.showToast("Activity deleted");
          loadDashboard();
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
      };
      try {
        await window.API.put(`/api/admin/activity/${id}`, payload);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("activityEditModal")).hide();
        window.AdminUI.showToast("Activity updated");
        loadDashboard();
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      }
    });

    loadDashboard();
  });
})();
