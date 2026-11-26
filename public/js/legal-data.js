/*
 * Datos del titular en las páginas legales. No van en el HTML: los robots que recogen datos
 * leen el código sin esperar, así que se escriben a los 4 segundos de cargar y, hasta entonces,
 * se ve un aviso. Van al revés y en base64 para que tampoco salgan buscando en este fichero.
 */
(function () {
  'use strict';
  var DELAY_MS = 4000;
  var DATA = {
    name: 'emVkbsOhbnJlRiB6ZWhjbsOhUyBvaW5vdG5B',
    nif: 'TTc5NTk5NDMz',
    address_en: 'bmlhcFMgLClldG5hY2lsQSggZWhjbEUgMTAyMzAgLHF6SSBvamFCICwwMyB6ZWRuw6FycmVGIGHDrWNyYUcgYcOtcmFNIC9D',
    address_es: 'KWV0bmFjaWxBKCBlaGNsRSAxMDIzMCAscXpJIG9qYUIgLDAzIHplZG7DoXJyZUYgYcOtY3JhRyBhw61yYU0gL0M='
  };

  function decode(value) {
    var bytes = atob(value);
    var text = decodeURIComponent(Array.prototype.map.call(bytes, function (c) {
      return '%' + ('0' + c.charCodeAt(0).toString(16)).slice(-2);
    }).join(''));
    return Array.from(text).reverse().join('');
  }

  function reveal() {
    var slots = document.querySelectorAll('[data-legal]');
    Array.prototype.forEach.call(slots, function (el) {
      var key = el.getAttribute('data-legal');
      if (!Object.prototype.hasOwnProperty.call(DATA, key)) return;
      el.textContent = decode(DATA[key]);
      el.removeAttribute('style');
    });
  }

  window.setTimeout(reveal, DELAY_MS);
})();
