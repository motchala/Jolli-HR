// Tabs / segmented controls
document.querySelectorAll('[data-tabs]').forEach(function (g) {
  var panels = g.parentNode.querySelectorAll('[data-panel]');
  g.querySelectorAll('[data-tab]').forEach(function (b) {
    b.addEventListener('click', function () {
      g.querySelectorAll('[data-tab]').forEach(function (x) { x.classList.toggle('on', x === b); });
      panels.forEach(function (p) { p.hidden = p.dataset.panel !== b.dataset.tab; });
    });
  });
});
// Password show/hide
document.querySelectorAll('[data-pw]').forEach(function (b) {
  b.addEventListener('click', function () {
    var i = b.parentNode.querySelector('input'), show = i.type === 'password';
    i.type = show ? 'text' : 'password';
    b.setAttribute('aria-pressed', show ? 'true' : 'false');
    b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
});
// Toggle buttons (Apply / Select / Swap)
document.querySelectorAll('[data-toggle]').forEach(function (b) {
  var a = b.textContent, c = b.dataset.toggle;
  b.addEventListener('click', function () {
    b.textContent = b.classList.toggle('done') ? c : a;
  });
});
// Job filters + search
var jobs = document.querySelectorAll('.job'), q = document.getElementById('q'), f = 'all';
function filterJobs() {
  var t = q ? q.value.toLowerCase() : '';
  jobs.forEach(function (j) {
    j.hidden = !((f === 'all' || j.dataset.tags.split(' ').indexOf(f) > -1) && j.dataset.text.indexOf(t) > -1);
  });
}
if (q) q.addEventListener('input', filterJobs);
document.querySelectorAll('[data-chips] button').forEach(function (b) {
  b.addEventListener('click', function () {
    document.querySelectorAll('[data-chips] button').forEach(function (x) { x.classList.toggle('on', x === b); });
    f = b.dataset.f; filterJobs();
  });
});
// Mood check-in
document.querySelectorAll('[data-mood] button').forEach(function (b) {
  b.addEventListener('click', function () {
    b.parentNode.querySelectorAll('button').forEach(function (x) { x.classList.toggle('on', x === b); });
  });
});
// Signup: confirm-password match check (live)
document.querySelectorAll('form[data-validate]').forEach(function (f) {
  var p = f.querySelector('[name=password]'), c = f.querySelector('[name=confirm]');
  if (!p || !c) return;
  function chk() { c.setCustomValidity(c.value && c.value !== p.value ? 'Passwords do not match.' : ''); }
  p.addEventListener('input', chk); c.addEventListener('input', chk);
});

// ── Notifications ────────────────────────────────────────────────────────────
(function () {
  var bell = document.querySelector('a.bell');
  if (!bell) return; // not on a page that has the topbar

  // Build the dropdown panel once and append it inside .bell so it anchors correctly
  var panel = document.createElement('div');
  panel.className = 'notif-panel';
  panel.setAttribute('hidden', '');
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-label', 'Notifications');
  panel.innerHTML =
    '<div class="notif-head">' +
      '<b>Notifications</b>' +
      '<button type="button" id="notif-mark-all">Mark all read</button>' +
    '</div>' +
    '<ul class="notif-list" id="notif-list"></ul>';
  bell.appendChild(panel);

  var list   = panel.querySelector('#notif-list');
  var markBtn = panel.querySelector('#notif-mark-all');

  // Format a UTC timestamp into a short relative string ("2 h ago", "just now", etc.)
  function relativeTime(iso) {
    var diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (diff < 60)  return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' h ago';
    return Math.floor(diff / 86400) + ' d ago';
  }

  // Render items into the list
  function renderItems(items) {
    list.innerHTML = '';
    if (!items.length) {
      list.innerHTML = '<li class="notif-empty">You\'re all caught up!</li>';
      return;
    }
    items.forEach(function (n) {
      var li = document.createElement('li');
      li.className = 'notif-item';
      li.innerHTML =
        '<span>' + n.message.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>' +
        '<time class="notif-time">' + relativeTime(n.time) + '</time>';
      list.appendChild(li);
    });
  }

  // Update the bell badge
  function setBadge(count) {
    var existing = bell.querySelector('.bell-count');
    // Remove the original static dot when we take over
    var dot = bell.querySelector('i:not(.bell-count)');
    if (dot) dot.remove();

    if (count > 0) {
      if (!existing) {
        existing = document.createElement('span');
        existing.className = 'bell-count';
        bell.appendChild(existing);
      }
      existing.textContent = count > 99 ? '99+' : count;
      existing.removeAttribute('hidden');
    } else {
      if (existing) existing.setAttribute('hidden', '');
    }
  }

  var cachedItems = [];

  // Fetch unread notifications from the server
  function loadNotifications() {
    fetch('notifications.php?action=list', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (items) {
        cachedItems = items;
        setBadge(items.length);
        if (!panel.hasAttribute('hidden')) renderItems(items);
      })
      .catch(function () {}); // silent fail — notifications are non-critical
  }

  // Toggle open/close
  bell.addEventListener('click', function (e) {
    e.preventDefault();
    var isOpen = !panel.hasAttribute('hidden');
    if (isOpen) {
      panel.setAttribute('hidden', '');
    } else {
      renderItems(cachedItems);
      panel.removeAttribute('hidden');
    }
  });

  // Mark all read
  markBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    fetch('notifications.php?action=mark_read', {
      method: 'POST',
      credentials: 'same-origin'
    })
      .then(function () {
        cachedItems = [];
        setBadge(0);
        renderItems([]);
      })
      .catch(function () {});
  });

  // Close when clicking outside the panel
  document.addEventListener('click', function (e) {
    if (!panel.hasAttribute('hidden') && !bell.contains(e.target)) {
      panel.setAttribute('hidden', '');
    }
  });

  // Load immediately, then poll every 60 s
  loadNotifications();
  setInterval(loadNotifications, 60000);
}());
