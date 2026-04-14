(function () {
  let statsChart;
  let activityChart;
  let pendingDeleteActivityId = null;
  const toSafe = (v) => (v === null || v === undefined || v === "" ? "-" : String(v));

  function renderCharts(stats, analytics) {
    const barCtx = document.getElementById("statsBarChart");
    const donutCtx = document.getElementById("activityDonutChart");
    if (!barCtx || !donutCtx || typeof Chart === "undefined") return;

    if (statsChart) statsChart.destroy();
    if (activityChart) activityChart.destroy();

    const growth = analytics?.growth_points || [];
    const growthLabels = growth.map((p) => p.date?.slice(5) || "-");
    const growthValues = growth.map((p) => Number(p.total || 0));

    statsChart = new Chart(barCtx, {
      type: "line",
      data: {
        labels: growthLabels.length ? growthLabels : ["No Data"],
        datasets: [{
          label: "Daily Total Activity (All Tables)",
          data: growthValues.length ? growthValues : [0],
          borderColor: "#2563eb",
          backgroundColor: "rgba(37,99,235,.18)",
          pointBackgroundColor: "#1d4ed8",
          fill: true,
          tension: .35,
        }],
      },
      options: {
        responsive: true,
        plugins: { legend: { display: true } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
      },
    });

    const mix = analytics?.activity_mix || [];
    const labels = mix.map((m) => m.type || "other");
    const values = mix.map((m) => Number(m.total || 0));
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
      const [statsRes, analyticsRes, activityRes] = await Promise.all([
        window.API.get("/api/admin/dashboard/stats"),
        window.API.get("/api/admin/dashboard/analytics", { days: 14 }),
        window.API.get("/api/admin/activity/logs", { page: 1, limit: 12 }),
      ]);
      const stats = statsRes.data || statsRes;
      const analytics = analyticsRes.data || analyticsRes;
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
      renderCharts(stats, analytics);
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
          const d = res.data || {};
          const badgeClass = actionBadge(d.type || d.action);
          const fields = [
            ["Activity ID", d.id],
            ["Time", d.created_at],
            ["Actor Name", d.actor_name],
            ["Actor Email", d.actor_email],
            ["Receiver Name", d.user_name],
            ["Receiver Email", d.user_email],
            ["Actor ID", d.actor_id],
            ["User ID", d.user_id],
            ["Action Type", d.type || d.action],
            ["Target ID", d.target_id],
            ["Message", d.message],
            ["Read", d.is_read],
          ];
          const content = document.getElementById("activityViewContent");
          if (content) {
            content.innerHTML = `
              <div class="mb-3">
                <span class="badge ${badgeClass}">${toSafe(d.type || d.action)}</span>
              </div>
              ${fields.map(([k, v]) => `
              <div class="d-flex justify-content-between border-bottom py-2 gap-3">
                <div class="text-secondary">${k}</div>
                <div class="text-end fw-semibold">${toSafe(v)}</div>
              </div>
              `).join("")}
            `;
          }
          bootstrap.Modal.getOrCreateInstance(document.getElementById("activityViewModal")).show();
        }
        if (action === "edit") {
          const res = await window.API.get(`/api/admin/activity/${id}`);
          const d = res.data || {};
          document.getElementById("editActivityId").value = d.id || id;
          document.getElementById("editActivityAction").value = d.type || d.action || "";
          document.getElementById("editActivityMessage").value = d.message || "";
          const details = document.getElementById("editActivityDetails");
          if (details) {
            const fields = [
              ["Activity ID", d.id],
              ["Created At", d.created_at],
              ["Actor", d.actor_name],
              ["Actor Email", d.actor_email],
              ["Receiver", d.user_name],
              ["Receiver Email", d.user_email],
              ["Target ID", d.target_id],
              ["Read", d.is_read],
            ];
            details.innerHTML = fields.map(([k, v]) => `
              <div class="d-flex justify-content-between border-bottom py-2 gap-3">
                <div class="text-secondary">${k}</div>
                <div class="text-end fw-semibold">${toSafe(v)}</div>
              </div>
            `).join("");
          }
          bootstrap.Modal.getOrCreateInstance(document.getElementById("activityEditModal")).show();
        }
        if (action === "delete") {
          pendingDeleteActivityId = id;
          const row = btn.closest("tr");
          const preview = document.getElementById("deleteActivityPreview");
          if (preview) {
            preview.textContent = row ? row.innerText.replace(/\s+/g, " ").slice(0, 220) : `Activity #${id}`;
          }
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

    document.getElementById("confirmDeleteActivity")?.addEventListener("click", async () => {
      if (!pendingDeleteActivityId) return;
      try {
        await window.API.delete(`/api/admin/activity/${pendingDeleteActivityId}`);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("activityDeleteModal")).hide();
        window.AdminUI.showToast("Activity deleted");
        pendingDeleteActivityId = null;
        loadDashboard();
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      }
    });

    loadDashboard();
  });
})();
