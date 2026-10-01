/* ============================================================
   Assignment Portal — app.js
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {

  /* ── File Upload Zone ─────────────────────────────────────── */
  const zone    = document.querySelector('.upload-zone');
  const fileIn  = document.querySelector('.upload-zone input[type=file]');
  const preview = document.querySelector('.upload-preview');
  const prevName= document.querySelector('.upload-preview-name');
  const prevSize= document.querySelector('.upload-preview-size');

  if (zone && fileIn) {
    // Drag-and-drop events
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.classList.remove('drag-over');
      if (e.dataTransfer.files.length) {
        fileIn.files = e.dataTransfer.files;
        updateFilePreview(fileIn.files[0]);
      }
    });

    fileIn.addEventListener('change', () => {
      if (fileIn.files.length) updateFilePreview(fileIn.files[0]);
    });
  }

  function updateFilePreview(file) {
    if (!preview) return;
    const allowed = ['application/pdf','application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/zip','application/x-zip-compressed'];

    // Basic client-side type hint (server validates definitively)
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['pdf','doc','docx','zip'].includes(ext)) {
      showClientError('Only PDF, DOC, DOCX, and ZIP files are allowed.');
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      showClientError('File size exceeds the 10 MB limit.');
      return;
    }

    prevName.textContent = file.name;
    prevSize.textContent = humanSize(file.size);
    preview.classList.add('show');

    // Set icon based on extension
    const icon = preview.querySelector('.file-icon');
    if (icon) {
      icon.className = `file-icon fa fa-file-${ext === 'zip' ? 'zipper' : ext === 'pdf' ? 'pdf' : 'word'} ${ext}`;
    }
  }

  /* ── Upload Progress Simulation ──────────────────────────── */
  const submitBtn  = document.querySelector('[data-submit]');
  const progressWrap = document.querySelector('.progress-wrap');
  const progressBar  = document.querySelector('.progress-bar');

  if (submitBtn && progressWrap) {
    submitBtn.closest('form')?.addEventListener('submit', () => {
      progressWrap.classList.add('show');
      let pct = 0;
      const tick = setInterval(() => {
        pct += Math.random() * 15;
        if (pct >= 90) { clearInterval(tick); pct = 90; }
        progressBar.style.width = pct + '%';
      }, 150);
      // Mark 100% on load (actual redirect happens server-side)
      window.addEventListener('beforeunload', () => {
        clearInterval(tick);
        progressBar.style.width = '100%';
      });
    });
  }

  /* ── Auto-dismiss alerts ─────────────────────────────────── */
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => el.style.opacity = '0', 4000);
    setTimeout(() => el.remove(), 4500);
    el.style.transition = 'opacity .5s';
  });

  /* ── Sidebar active link ─────────────────────────────────── */
  const currentPath = window.location.pathname;
  document.querySelectorAll('.sidebar-nav a').forEach(a => {
    if (a.getAttribute('href') && currentPath.endsWith(a.getAttribute('href').split('/').pop())) {
      a.classList.add('active');
    }
  });

  /* ── Confirm dangerous actions ───────────────────────────── */
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });

  /* ── Grade inline form live score update ─────────────────── */
  document.querySelectorAll('.grade-input').forEach(input => {
    input.addEventListener('input', () => {
      const max   = parseFloat(input.dataset.max) || 100;
      const val   = parseFloat(input.value);
      const pct   = Math.min(100, Math.round((val / max) * 100));
      const label = input.closest('tr')?.querySelector('.score-pct');
      if (label) label.textContent = isNaN(pct) ? '' : `(${pct}%)`;
    });
  });

  /* ── Client-side deadline countdown ─────────────────────── */
  document.querySelectorAll('[data-deadline]').forEach(el => {
    const dl = new Date(el.dataset.deadline).getTime();
    function tick() {
      const diff = dl - Date.now();
      if (diff <= 0) { el.textContent = 'Deadline passed'; return; }
      const d = Math.floor(diff / 86400000);
      const h = Math.floor((diff % 86400000) / 3600000);
      const m = Math.floor((diff % 3600000)  / 60000);
      el.textContent = d > 0 ? `${d}d ${h}h ${m}m` : h > 0 ? `${h}h ${m}m` : `${m}m`;
      setTimeout(tick, 30000);
    }
    tick();
  });

  /* ── Mobile Hamburger Menu ───────────────────────────── */
  const hamburgerMenu = document.getElementById('hamburger-menu');
  const sidebar = document.getElementById('sidebar');
  const mobileOverlay = document.getElementById('mobile-menu-overlay');

  if (hamburgerMenu && sidebar && mobileOverlay) {
    // Toggle menu function
    function toggleMobileMenu() {
      hamburgerMenu.classList.toggle('active');
      sidebar.classList.toggle('mobile-open');
      mobileOverlay.classList.toggle('active');
      document.body.classList.toggle('mobile-menu-open');
    }

    // Hamburger menu click
    hamburgerMenu.addEventListener('click', function(e) {
      e.preventDefault();
      toggleMobileMenu();
    });

    // Overlay click to close
    mobileOverlay.addEventListener('click', toggleMobileMenu);

    // Close menu when clicking a link (mobile)
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth <= 900) {
          toggleMobileMenu();
        }
      });
    });

    // Close menu on window resize if desktop
    window.addEventListener('resize', () => {
      if (window.innerWidth > 900) {
        hamburgerMenu.classList.remove('active');
        sidebar.classList.remove('mobile-open');
        mobileOverlay.classList.remove('active');
        document.body.classList.remove('mobile-menu-open');
      }
    });
  }

  /* ── Helpers ─────────────────────────────────────────────── */
  function humanSize(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
    if (bytes >= 1024)    return (bytes / 1024).toFixed(0)    + ' KB';
    return bytes + ' B';
  }

  function showClientError(msg) {
    const existing = document.querySelector('.client-error');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.className = 'alert alert-error client-error';
    div.innerHTML = `<i class="fa fa-circle-exclamation"></i> ${msg}`;
    document.querySelector('.upload-zone')?.insertAdjacentElement('afterend', div);
    setTimeout(() => div.remove(), 4000);
  }
});
