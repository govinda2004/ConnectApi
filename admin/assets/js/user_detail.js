(function () {
  const toSafe = (v) => (v === null || v === undefined || v === "" ? "-" : String(v));

  function getUserIdFromUrl() {
    const p = new URLSearchParams(window.location.search);
    return Number(p.get("id") || 0);
  }

  function row(label, value) {
    return `
      <div class="d-flex justify-content-between border-bottom py-2 gap-3">
        <div class="text-secondary">${label}</div>
        <div class="text-end fw-semibold">${toSafe(value)}</div>
      </div>
    `;
  }

  async function loadUserFull() {
    const id = getUserIdFromUrl();
    if (!id) {
      window.AdminUI.showToast("Invalid user id", "error");
      return;
    }

    try {
      const res = await window.API.get(`/api/admin/users/${id}/full`);
      const data = res.data || {};
      const profile = data.profile || {};
      const summary = data.summary || {};
      const activity = data.activity || [];
      const appliedJobs = data.applied_jobs || [];
      const createdJobs = data.created_jobs || [];

      const summaryEl = document.getElementById("udProfileSummary");
      if (summaryEl) {
        const name = toSafe(profile.name);
        const email = toSafe(profile.email);
        const avatar = (name !== "-" ? name[0] : "U").toUpperCase();
        summaryEl.innerHTML = `
          <div class="text-center">
            <div class="mx-auto mb-2 rounded-circle d-flex align-items-center justify-content-center" style="width:72px;height:72px;background:#dbeafe;font-weight:700;color:#1d4ed8;">${avatar}</div>
            <h5 class="mb-1">${name}</h5>
            <div class="small text-secondary mb-2">${email}</div>
            <span class="badge ${profile.status === "active" ? "text-bg-success" : "text-bg-secondary"}">${toSafe(profile.status)}</span>
          </div>
        `;
      }

      const detailEl = document.getElementById("udProfileDetails");
      if (detailEl) {
        detailEl.innerHTML = [
          row("User ID", profile.id),
          row("Name", profile.name),
          row("Email", profile.email),
          row("Phone", profile.contact_no),
          row("Account Type", profile.account_type),
          row("Login Type", profile.login_type),
          row("Headline", profile.headline),
          row("Location", profile.location),
          row("About", profile.about),
          row("Created", profile.created_at),
          row("Updated", profile.updated_at),
        ].join("");
      }

      document.getElementById("udActivityCount").textContent = Number(summary.activity_count || 0);
      document.getElementById("udAppliedCount").textContent = Number(summary.applied_jobs_count || 0);
      document.getElementById("udCreatedCount").textContent = Number(summary.created_jobs_count || 0);

      const appliedEl = document.getElementById("udAppliedJobs");
      if (appliedEl) {
        appliedEl.innerHTML = appliedJobs.length ? appliedJobs.map((j) => `
          <tr>
            <td>${toSafe(j.title)}</td>
            <td>${toSafe(j.company)}</td>
            <td><span class="badge text-bg-info">${toSafe(j.status)}</span></td>
            <td>${toSafe(j.applied_at)}</td>
          </tr>
        `).join("") : `<tr><td colspan="4" class="text-center text-secondary">No applied jobs</td></tr>`;
      }

      const createdEl = document.getElementById("udCreatedJobs");
      if (createdEl) {
        createdEl.innerHTML = createdJobs.length ? createdJobs.map((j) => `
          <tr>
            <td>${toSafe(j.title)}</td>
            <td>${toSafe(j.company)}</td>
            <td>${toSafe(j.applications_count)}</td>
            <td>${toSafe(j.created_at)}</td>
          </tr>
        `).join("") : `<tr><td colspan="4" class="text-center text-secondary">No created jobs</td></tr>`;
      }

      const actEl = document.getElementById("udActivity");
      if (actEl) {
        actEl.innerHTML = activity.length ? activity.map((a) => `
          <tr>
            <td>${toSafe(a.created_at)}</td>
            <td><span class="badge text-bg-secondary">${toSafe(a.action)}</span></td>
            <td>${toSafe(a.actor_name)}</td>
            <td>${toSafe(a.receiver_name)}</td>
            <td>${toSafe(a.message)}</td>
          </tr>
        `).join("") : `<tr><td colspan="5" class="text-center text-secondary">No activity found</td></tr>`;
      }
    } catch (err) {
      window.AdminUI.showToast(err.message, "error");
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (!window.Auth.requireAuth()) return;
    loadUserFull();
  });
})();
