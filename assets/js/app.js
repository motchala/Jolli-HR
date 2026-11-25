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
    i.type = show ? 'text' : 'password'; b.textContent = show ? 'Hide' : 'Show';
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