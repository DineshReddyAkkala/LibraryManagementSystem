/* LMS Enterprise - Client-side Interactions */

function toggleSidebar() {
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebar-overlay');
  if (sidebar) sidebar.classList.toggle('open');
  if (overlay) overlay.classList.toggle('show');
}

document.addEventListener('DOMContentLoaded', function() {
  var overlay = document.getElementById('sidebar-overlay');
  if (overlay) {
    overlay.addEventListener('click', function() {
      toggleSidebar();
    });
  }

  // Auto-dismiss alerts after 5 seconds
  document.querySelectorAll('.alert').forEach(function(el) {
    setTimeout(function() {
      el.style.transition = 'opacity 400ms ease, transform 400ms ease';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-10px)';
      setTimeout(function() { el.remove(); }, 400);
    }, 5000);
  });

  // Confirmation for destructive actions
  document.querySelectorAll('form[data-confirm]').forEach(function(form) {
    form.addEventListener('submit', function(e) {
      if (!confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });
});

function goTo(path) {
  window.location.href = path;
}

function confirmAction(msg) {
  return confirm(msg || 'Are you sure?');
}