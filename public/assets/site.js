(function () {
  var form = document.getElementById('contact-form');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var m = document.getElementById('form-msg'), b = form.querySelector('button');
    b.disabled = true;
    fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { m.textContent = d.message; m.hidden = false; if (d.ok) form.reset(); })
      .catch(function () { m.textContent = 'Something went wrong. Please email us directly.'; m.hidden = false; })
      .then(function () { b.disabled = false; });
  });
})();
