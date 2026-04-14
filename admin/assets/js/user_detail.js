(function () {
  const toSafe = (v) => (v === null || v === undefined || v === "" ? "-" : String(v));
  let currentUserId = 0;
  let pendingDelete = null;

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
    currentUserId = getUserIdFromUrl();
    if (!currentUserId) {
      window.AdminUI.showToast("Invalid user id", "error");
      return;
    }

    try {
      const res = await window.API.get(`/api/admin/users/${currentUserId}/full`);
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
            <td class="text-end">
              <button class="btn btn-outline-primary btn-sm" data-kind="applied" data-action="edit" data-id="${j.id}">Edit</button>
              <button class="btn btn-outline-danger btn-sm" data-kind="applied" data-action="delete" data-id="${j.id}">Delete</button>
            </td>
          </tr>
        `).join("") : `<tr><td colspan="5" class="text-center text-secondary">No applied jobs</td></tr>`;
      }

      const createdEl = document.getElementById("udCreatedJobs");
      if (createdEl) {
        createdEl.innerHTML = createdJobs.length ? createdJobs.map((j) => `
          <tr>
            <td>${toSafe(j.title)}</td>
            <td>${toSafe(j.company)}</td>
            <td>${toSafe(j.applications_count)}</td>
            <td>${toSafe(j.created_at)}</td>
            <td class="text-end">
              <button class="btn btn-outline-primary btn-sm" data-kind="created" data-action="edit" data-id="${j.id}">Edit</button>
              <button class="btn btn-outline-danger btn-sm" data-kind="created" data-action="delete" data-id="${j.id}">Delete</button>
            </td>
          </tr>
        `).join("") : `<tr><td colspan="5" class="text-center text-secondary">No created jobs</td></tr>`;
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
            <td class="text-end">
              <button class="btn btn-outline-primary btn-sm" data-kind="activity" data-action="edit" data-id="${a.id}">Edit</button>
              <button class="btn btn-outline-danger btn-sm" data-kind="activity" data-action="delete" data-id="${a.id}">Delete</button>
            </td>
          </tr>
        `).join("") : `<tr><td colspan="6" class="text-center text-secondary">No activity found</td></tr>`;
      }
    } catch (err) {
      window.AdminUI.showToast(err.message, "error");
    }
  }

  function openDelete(kind, id, previewText) {
    pendingDelete = { kind, id };
    const p = document.getElementById("udDeletePreview");
    if (p) p.textContent = previewText || `${kind} #${id}`;
    bootstrap.Modal.getOrCreateInstance(document.getElementById("udDeleteModal")).show();
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (!window.Auth.requireAuth()) return;
    document.getElementById("udAppliedJobs")?.addEventListener("click", async (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;
      const id = btn.dataset.id;
      const action = btn.dataset.action;
      if (action === "edit") {
        try {
          const res = await window.API.get(`/api/admin/users/${currentUserId}/applied-jobs/${id}`);
          const d = res.data || {};
          document.getElementById("udAppliedEditId").value = d.id || id;
          document.getElementById("udAppliedStatus").value = d.status || "";
          document.getElementById("udAppliedFullName").value = d.full_name || "";
          document.getElementById("udAppliedEmail").value = d.email || "";
          document.getElementById("udAppliedPhone").value = d.phone || "";
          document.getElementById("udAppliedResume").value = d.resume_url || "";
          document.getElementById("udAppliedSalary").value = d.salary_expectation || "";
          document.getElementById("udAppliedCover").value = d.cover_letter || "";
          bootstrap.Modal.getOrCreateInstance(document.getElementById("udAppliedEditModal")).show();
        } catch (err) { window.AdminUI.showToast(err.message, "error"); }
      }
      if (action === "delete") openDelete("applied", id, `Applied Job #${id}`);
    });

    document.getElementById("udCreatedJobs")?.addEventListener("click", async (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;
      const id = btn.dataset.id;
      const action = btn.dataset.action;
      if (action === "edit") {
        try {
          const res = await window.API.get(`/api/admin/users/${currentUserId}/created-jobs/${id}`);
          const d = res.data || {};
          document.getElementById("udCreatedEditId").value = d.id || id;
          document.getElementById("udCreatedTitle").value = d.title || "";
          document.getElementById("udCreatedCompany").value = d.company || "";
          document.getElementById("udCreatedLocation").value = d.location || "";
          document.getElementById("udCreatedType").value = d.job_type || "";
          document.getElementById("udCreatedSalaryMin").value = d.salary_min || "";
          document.getElementById("udCreatedSalaryMax").value = d.salary_max || "";
          document.getElementById("udCreatedSkills").value = d.skills || "";
          document.getElementById("udCreatedDescription").value = d.description || "";
          bootstrap.Modal.getOrCreateInstance(document.getElementById("udCreatedEditModal")).show();
        } catch (err) { window.AdminUI.showToast(err.message, "error"); }
      }
      if (action === "delete") openDelete("created", id, `Created Job #${id}`);
    });

    document.getElementById("udActivity")?.addEventListener("click", async (e) => {
      const btn = e.target.closest("button[data-action]");
      if (!btn) return;
      const id = btn.dataset.id;
      const action = btn.dataset.action;
      if (action === "edit") {
        try {
          const res = await window.API.get(`/api/admin/users/${currentUserId}/activity/${id}`);
          const d = res.data || {};
          document.getElementById("udActivityEditId").value = d.id || id;
          document.getElementById("udActivityAction").value = d.type || d.action || "";
          document.getElementById("udActivityMessage").value = d.message || "";
          bootstrap.Modal.getOrCreateInstance(document.getElementById("udActivityEditModal")).show();
        } catch (err) { window.AdminUI.showToast(err.message, "error"); }
      }
      if (action === "delete") openDelete("activity", id, `Activity #${id}`);
    });

    document.getElementById("udAppliedEditForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = document.getElementById("udAppliedEditId").value;
      const payload = {
        status: document.getElementById("udAppliedStatus").value.trim(),
        full_name: document.getElementById("udAppliedFullName").value.trim(),
        email: document.getElementById("udAppliedEmail").value.trim(),
        phone: document.getElementById("udAppliedPhone").value.trim(),
        resume_url: document.getElementById("udAppliedResume").value.trim(),
        salary_expectation: document.getElementById("udAppliedSalary").value.trim(),
        cover_letter: document.getElementById("udAppliedCover").value.trim(),
      };
      try {
        await window.API.put(`/api/admin/users/${currentUserId}/applied-jobs/${id}`, payload);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("udAppliedEditModal")).hide();
        window.AdminUI.showToast("Applied job updated");
        loadUserFull();
      } catch (err) { window.AdminUI.showToast(err.message, "error"); }
    });

    document.getElementById("udCreatedEditForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = document.getElementById("udCreatedEditId").value;
      const payload = {
        title: document.getElementById("udCreatedTitle").value.trim(),
        company: document.getElementById("udCreatedCompany").value.trim(),
        location: document.getElementById("udCreatedLocation").value.trim(),
        job_type: document.getElementById("udCreatedType").value.trim(),
        salary_min: Number(document.getElementById("udCreatedSalaryMin").value || 0),
        salary_max: Number(document.getElementById("udCreatedSalaryMax").value || 0),
        skills: document.getElementById("udCreatedSkills").value.trim(),
        description: document.getElementById("udCreatedDescription").value.trim(),
      };
      try {
        await window.API.put(`/api/admin/users/${currentUserId}/created-jobs/${id}`, payload);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("udCreatedEditModal")).hide();
        window.AdminUI.showToast("Created job updated");
        loadUserFull();
      } catch (err) { window.AdminUI.showToast(err.message, "error"); }
    });

    document.getElementById("udActivityEditForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const id = document.getElementById("udActivityEditId").value;
      const payload = {
        action: document.getElementById("udActivityAction").value.trim(),
        message: document.getElementById("udActivityMessage").value.trim(),
      };
      try {
        await window.API.put(`/api/admin/users/${currentUserId}/activity/${id}`, payload);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("udActivityEditModal")).hide();
        window.AdminUI.showToast("Activity updated");
        loadUserFull();
      } catch (err) { window.AdminUI.showToast(err.message, "error"); }
    });

    document.getElementById("udConfirmDelete")?.addEventListener("click", async () => {
      if (!pendingDelete) return;
      const { kind, id } = pendingDelete;
      try {
        if (kind === "applied") await window.API.delete(`/api/admin/users/${currentUserId}/applied-jobs/${id}`);
        if (kind === "created") await window.API.delete(`/api/admin/users/${currentUserId}/created-jobs/${id}`);
        if (kind === "activity") await window.API.delete(`/api/admin/users/${currentUserId}/activity/${id}`);
        bootstrap.Modal.getOrCreateInstance(document.getElementById("udDeleteModal")).hide();
        window.AdminUI.showToast("Deleted successfully");
        pendingDelete = null;
        loadUserFull();
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      }
    });

    loadUserFull();
  });
})();
