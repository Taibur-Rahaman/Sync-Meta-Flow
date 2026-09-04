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
  function eventId(name) {
    if (window.crypto && window.crypto.randomUUID) return 'smf-' + name + '-' + window.crypto.randomUUID();
    return 'smf-' + name + '-' + Date.now() + '-' + Math.random().toString(36).slice(2);
  }
  function send(eventName, payload) {
    if (!data.ajaxUrl || !data.nonce) return;
    var body = new URLSearchParams();
    body.set('action', 'smf_track_event'); body.set('nonce', data.nonce); body.set('event', eventName);
    body.set('payload', JSON.stringify(payload || {}));
    if (navigator.sendBeacon) navigator.sendBeacon(data.ajaxUrl, body);
    else if (window.fetch) fetch(data.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true });
  }
  function metaTrack(name, payload, id) {
    if (typeof window.fbq !== 'function' || !data.metaPixelId) return;
    var params = Object.assign({}, payload || {});
    delete params.session_key;
    delete params.page_url;
    delete params.event_id;
    window.fbq('track', name, params, id ? { eventID: id } : undefined);
  }
  function track(eventName, payload, metaName) {
    payload = payload || {};
    payload.page_url = window.location.href;
    payload.session_key = data.sessionKey || '';
    payload.event_id = payload.event_id || eventId(eventName);
    send(eventName, payload);
    if (metaName) metaTrack(metaName, payload, payload.event_id);
    return payload.event_id;
  }

  if (Object.keys(attribution).length) {
    var existing = {};
    try { existing = JSON.parse(readCookie('smf_attribution') || '{}'); } catch (e) {}
    keys.forEach(function (key) { if (attribution[key]) existing[key] = attribution[key]; });
    writeCookie('smf_attribution', JSON.stringify(existing), 30);
  }

  window.SMF = window.SMF || {};
  window.SMF.track = track;
  track('page_view', {}, 'PageView');

  if (data.isProduct) track('view_content', { product_id: data.productId || 0, product_name: data.productName || '' }, 'ViewContent');

  if (window.jQuery) {
    jQuery(document.body).on('added_to_cart', function (event, fragments, cartHash, button) {
      var productId = button && button.data ? button.data('product_id') : data.productId;
      track('add_to_cart', { product_id: productId || 0 }, 'AddToCart');
    });
    jQuery(document.body).on('checkout_error', function () { track('checkout_error'); });
  }
  if (data.isCheckout) track('initiate_checkout', {}, 'InitiateCheckout');

  // Purchase is fired on the WooCommerce order-received page with the same event ID
  // saved on the order. That ID can be reused by server-side CAPI for deduplication.
  if (data.isOrderReceived && data.purchaseEventId) {
    var purchasePayload = {
      value: Number(data.orderTotal || 0),
      currency: data.currency || '',
      order_id: String(data.orderId || '')
    };
    track('purchase', purchasePayload, 'Purchase');
  }
})();
