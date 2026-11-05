/* Cookie notice. Only essential cookies are used today, so this is an acknowledgement.
   To add analytics later: load the script only after an explicit "accept" (store 'accepted'). */
(function () {
  var KEY = 'nx_cookie_notice', MAX = 365 * 864e5, b = document.getElementById('cookie-banner');
  function get() { try { var v = JSON.parse(localStorage.getItem(KEY)); return v && Date.now() - v.t < MAX ? v : null; } catch (e) { return null; } }
  function set() { try { localStorage.setItem(KEY, JSON.stringify({ t: Date.now(), v: 'essential' })); } catch (e) {} }
  if (!b) return;
  if (!get()) b.hidden = false;
  b.querySelector('[data-cookie-ok]').onclick = function () { set(); b.hidden = true; };
  document.querySelectorAll('[data-cookie-reset]').forEach(function (el) {
    el.onclick = function () { try { localStorage.removeItem(KEY); } catch (e) {} b.hidden = false; };
  });
})();
