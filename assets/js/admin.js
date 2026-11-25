function editJob(btn) {
    document.getElementById('job_id').value       = btn.dataset.id;
    document.getElementById('f_title').value      = btn.dataset.title;
    document.getElementById('f_branch').value     = btn.dataset.branch;
    document.getElementById('f_tags').value       = btn.dataset.tags;
    document.getElementById('f_hours').value      = btn.dataset.hours;
    document.getElementById('f_distance').value   = btn.dataset.distance;
    document.getElementById('f_match').value      = btn.dataset.match;
    document.getElementById('action_field').value = 'edit';
    document.getElementById('job-form').scrollIntoView({ behavior: 'smooth' });
}
