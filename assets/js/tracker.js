(function () {
  'use strict';
  var data = window.SMF_DATA || {};
  var attribution = window.SMF_ATTRIBUTION || {};
  var keys = ['fbclid','utm_source','utm_medium','utm_campaign','utm_content','utm_term'];

  function readCookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()\[\]\\/+^]/g, '\\$&') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
  }
  function writeCookie(name, value, days) {
    var expires = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax';
  }

  if (Object.keys(attribution).length) {
    var existing = {};
    try { existing = JSON.parse(readCookie('smf_attribution') || '{}'); } catch (e) {}
    keys.forEach(function (key) { if (attribution[key]) existing[key] = attribution[key]; });
    writeCookie('smf_attribution', JSON.stringify(existing), 30);
  }

  window.SMF = window.SMF || {};
  window.SMF.track = function (eventName, payload) {
    if (!data.ajaxUrl || !data.nonce) return;
    var body = new URLSearchParams();
    body.set('action', 'smf_track_event');
    body.set('nonce', data.nonce);
    body.set('event', eventName);
    body.set('payload', JSON.stringify(payload || {}));
    if (navigator.sendBeacon) navigator.sendBeacon(data.ajaxUrl, body);
  };
})();
