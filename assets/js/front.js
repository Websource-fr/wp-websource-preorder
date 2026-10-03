/* Websource Précommandes - compte à rebours, bandeau, libellé du bouton des variations. */
(function () {
	'use strict';
	var cfg = window.WSPO || { serverNow: 0, units: {}, done: '' };
	var offset = cfg.serverNow ? cfg.serverNow * 1000 - Date.now() : 0;

	function unitLabel(u, n) {
		var l = (cfg.units && cfg.units[u]) || [u, u];
		return n === 1 ? l[0] : l[1];
	}
	function pad(n) { return n < 10 ? '0' + n : '' + n; }

	function layout(mode, secs) {
		if (mode === 'd') { return ['d']; }
		if (mode === 'dh') { return ['d', 'h']; }
		if (mode === 'dhm') { return ['d', 'h', 'm']; }
		if (mode === 'dhms') { return ['d', 'h', 'm', 's']; }
		// auto : unités adaptées au temps restant.
		if (secs >= 86400) { return ['d', 'h', 'm']; }
		if (secs >= 3600) { return ['h', 'm', 's']; }
		return ['m', 's'];
	}

	function tick(el) {
		var release = parseInt(el.getAttribute('data-release'), 10) * 1000;
		var secs = Math.floor((release - (Date.now() + offset)) / 1000);
		if (secs <= 0) {
			el.className = 'wspo-countdown wspo-countdown--done';
			el.textContent = cfg.done;
			el.removeAttribute('data-wspo-live');
			return false;
		}
		var units = layout(el.getAttribute('data-mode') || 'auto', secs);
		var d = Math.floor(secs / 86400), h = Math.floor((secs % 86400) / 3600), m = Math.floor((secs % 3600) / 60), s = secs % 60;
		// Si les jours ne sont pas affichés, ils sont reportés sur les heures.
		if (units.indexOf('d') === -1) { h += d * 24; d = 0; }
		if (units.indexOf('h') === -1) { m += h * 60; h = 0; }
		if (units.indexOf('m') === -1) { s += m * 60; m = 0; }
		var vals = { d: d, h: h, m: m, s: s };
		var html = '';
		for (var i = 0; i < units.length; i++) {
			var u = units[i], v = vals[u];
			html += '<span class="wspo-cd-u" data-u="' + u + '"><b>' + (u === 'd' ? v : pad(v)) + '</b><small>' + unitLabel(u, v) + '</small></span>';
		}
		el.innerHTML = html;
		return true;
	}

	function initCountdowns() {
		var list = document.querySelectorAll('.wspo-countdown[data-release]');
		var live = [];
		for (var i = 0; i < list.length; i++) {
			list[i].setAttribute('data-wspo-live', '1');
			if (tick(list[i])) { live.push(list[i]); }
		}
		if (live.length) {
			setInterval(function () {
				for (var j = 0; j < live.length; j++) {
					if (live[j].getAttribute('data-wspo-live')) { tick(live[j]); }
				}
			}, 1000);
		}
	}

	function initTickers() {
		var tickers = document.querySelectorAll('.wspo-ticker');
		for (var i = 0; i < tickers.length; i++) {
			var track = tickers[i].querySelector('.wspo-ticker__track');
			var group = tickers[i].querySelector('.wspo-ticker__group');
			if (!track || !group) { continue; }
			var w = group.offsetWidth || 600;
			track.style.setProperty('--wspo-duration', Math.max(8, w / (cfg.speed || 60)) + 's');
		}
	}

	function initVariations() {
		var forms = document.querySelectorAll('form.variations_form');
		forms.forEach(function (form) {
			var btn = form.querySelector('.single_add_to_cart_button');
			if (!btn) { return; }
			var original = btn.textContent;
			var jq = window.jQuery;
			if (!jq) { return; }
			jq(form).on('found_variation', function (e, v) {
				if (v && v.wspo_btn) { btn.textContent = v.wspo_btn; } else { btn.textContent = original; }
			}).on('reset_data', function () { btn.textContent = original; });
		});
	}

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
	}
	ready(function () { initCountdowns(); initTickers(); initVariations(); });
	window.addEventListener('load', initTickers);
})();
