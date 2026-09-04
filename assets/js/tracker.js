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
  function uuid() {
    return 'smf-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
  }

  var existing = {};
  try { existing = JSON.parse(readCookie('smf_attribution') || '{}'); } catch (e) {}
  keys.forEach(function (key) { if (attribution[key]) existing[key] = attribution[key]; });
  if (Object.keys(existing).length) writeCookie('smf_attribution', JSON.stringify(existing), 30);

  var session = readCookie('smf_session');
  if (!session) {
    session = uuid();
    writeCookie('smf_session', session, 30);
  }

  window.SMF = window.SMF || {};
  window.SMF.track = function (eventName, payload) {
    if (!data.ajaxUrl || !data.nonce) return;
    var body = new URLSearchParams();
    body.set('action', 'smf_track_event');
    body.set('nonce', data.nonce);
    body.set('event', eventName);
    body.set('payload', JSON.stringify(Object.assign({event_id: uuid(), page_url: window.location.href, session_key: session}, payload || {})));
    if (navigator.sendBeacon) navigator.sendBeacon(data.ajaxUrl, body);
    else fetch(data.ajaxUrl, {method: 'POST', body: body, credentials: 'same-origin', keepalive: true});
  };

  window.SMF.track('page_view');
  if (data.isProduct) window.SMF.track('view_content', {product_id: data.productId});
  if (data.isCheckout) window.SMF.track('initiate_checkout');

  if (window.jQuery) {
    jQuery(document.body).on('added_to_cart', function (event, fragments, cartHash, button) {
      var productId = button && button.data ? button.data('product_id') : 0;
      window.SMF.track('add_to_cart', {product_id: productId || 0});
    });
  }
})();
