(function () {
  const toSafe = (v) => (v === null || v === undefined || v === "" ? "-" : String(v));

  async function loadAdminNotices() {
    try {
      const res = await window.API.get("/api/admin/notifications/logs", { page: 1, limit: 50 });
      const rows = res.data?.items || [];
      const body = document.getElementById("adminNoticesBody");
      if (!body) return;
      body.innerHTML = rows.length ? rows.map((x) => `
        <tr>
          <td>#${toSafe(x.id)}</td>
          <td>${toSafe(x.created_at)}</td>
          <td>${toSafe(x.receiver_name)}<div class="small text-secondary">${toSafe(x.receiver_email)}</div></td>
          <td><span class="badge text-bg-primary">${toSafe(x.type)}</span></td>
          <td>${toSafe(x.message)}</td>
          <td>${x.image_url ? `<a href="${x.image_url}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>` : '<span class="text-secondary small">No image</span>'}</td>
        </tr>
      `).join("") : `<tr><td colspan="6" class="text-center text-secondary">No admin notifications sent yet.</td></tr>`;
    } catch (err) {
      window.AdminUI.showToast(err.message, "error");
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    if (document.body.dataset.page !== "notifications") return;

    const modeEl = document.getElementById("notifyMode");
    const userWrap = document.getElementById("notifyUserWrap");
    const userSelect = document.getElementById("notifyUserId");
    const form = document.getElementById("notifyForm");

    const toggleMode = () => {
      const mode = modeEl?.value || "single";
      userWrap?.classList.toggle("d-none", mode === "all");
    };
    modeEl?.addEventListener("change", toggleMode);
    toggleMode();

    window.API.get("/api/admin/users", { page: 1, limit: 200 }).then((res) => {
      const users = res.data?.items || [];
      users.forEach((u) => {
        const opt = document.createElement("option");
        opt.value = u.id;
        opt.textContent = `${u.name || "User"} (${u.email || "-"})`;
        userSelect?.appendChild(opt);
      });
    }).catch(() => {});

    form?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const sendBtn = document.getElementById("notifySendBtn");
      if (sendBtn) sendBtn.disabled = true;

      const mode = modeEl?.value || "single";
      const fd = new FormData();
      fd.append("mode", mode);
      fd.append("title", (document.getElementById("notifyTitle")?.value || "Admin Notice").trim());
      fd.append("message", (document.getElementById("notifyMessage")?.value || "").trim());
      if (mode === "single") fd.append("user_id", String(Number(userSelect?.value || 0)));
      const imageInput = document.getElementById("notifyImage");
      const file = imageInput?.files?.[0];
      if (file) fd.append("image", file);

      try {
        const res = await window.API.post("/api/admin/notifications/send", fd);
        window.AdminUI.showToast(res.message || "Notification sent");
        const d = res.data || {};
        if ((d.push_failed || 0) > 0 || (d.push_skipped || 0) > 0) {
          window.AdminUI.showToast(`Push issue: failed=${d.push_failed || 0}, skipped=${d.push_skipped || 0}`, "warning");
        }
        document.getElementById("notifyMessage").value = "";
        if (imageInput) imageInput.value = "";
        loadAdminNotices();
      } catch (err) {
        window.AdminUI.showToast(err.message, "error");
      } finally {
        if (sendBtn) sendBtn.disabled = false;
      }
    });

    document.getElementById("refreshAdminNotices")?.addEventListener("click", loadAdminNotices);
    loadAdminNotices();
  });
})();
