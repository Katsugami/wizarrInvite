// ─────────────────────────────────────────────────────────────────────────────
// Cache Status — local time ticker + smart auto-refresh table
// Loaded dynamically by settings.js when the Cache Status section is present.
// ─────────────────────────────────────────────────────────────────────────────

var _wizarrinviteLocalTimeTimer    = null;
var _wizarrinviteCacheStatusTimer  = null;
var _wizarrinviteLastKnownInterval = null; // seconds, from last /cache-status response

// ── Auto-refresh state (localStorage) ────────────────────────────────────────
function wizarrinviteIsAutoRefreshOn() {
	return localStorage.getItem('wizarrinvite_autorefresh') !== 'off';
}

/**
 * Syncs the checkbox and localStorage state.
 * @param {boolean} on
 * @param {boolean} [skipStorage]  true = only update UI, don't write localStorage
 */
function wizarrinviteSetAutoRefresh(on, skipStorage) {
	if (!skipStorage) {
		localStorage.setItem('wizarrinvite_autorefresh', on ? 'on' : 'off');
	}
	$('#wizarrinvite-autorefresh-cb').prop('checked', on);
}

// ── wz-toggle handler ─────────────────────────────────────────────────────────
$(document).off('change.wizarrinvite', '#wizarrinvite-autorefresh-cb');
$(document).on('change.wizarrinvite', '#wizarrinvite-autorefresh-cb', function () {
	var on = $(this).prop('checked');
	localStorage.setItem('wizarrinvite_autorefresh', on ? 'on' : 'off');
	if (on) {
		// Immediately fetch status + check if a refresh is due
		wizarrinviteAutoCheckIfDue(true);
		wizarrinviteStartAutoRefreshTimer();
	} else {
		wizarrinviteStopAutoRefreshTimer();
	}
});

// ── Helpers ───────────────────────────────────────────────────────────────────
function wizarrinviteFormatDuration(diffSec, past) {
	var abs = Math.abs(diffSec);
	var s = abs % 60;
	var m = Math.floor(abs / 60) % 60;
	var h = Math.floor(abs / 3600);
	var str = '';
	if (h > 0)  str += h + 'h ';
	if (m > 0)  str += m + 'min ';
	str += s + 's';
	return past ? str + ' ago' : 'in ' + str;
}

var _wizarrinviteTriggerLabels = {
	'manual-button': 'manual button',
	'display':       'display page',
	'auto':          'auto refresh',
	'api':           'Wizarr API',
};

function wizarrinviteFormatTrigger(trigger) {
	return _wizarrinviteTriggerLabels[trigger] || (trigger || 'unknown');
}

// ── Fetch & render (passive — no Wizarr API call) ─────────────────────────────
/**
 * Reads /cache-status and updates the table + last-check line.
 * @param {function} [onDone]  Called with the response data object, or null on failure.
 */
function wizarrinviteFetchCacheStatus(onDone) {
	var $tbody = $('#wizarrinvite-cache-status-tbody');
	if (!$tbody.length) {
		if (onDone) onDone(null);
		return;
	}

	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/cache-status',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).done(function (res) {
		if (!res || !res.response || res.response.result !== 'success') {
			if (onDone) onDone(null);
			return;
		}
		var d     = res.response.data || {};
		var slots = d.slots || [];
		var now   = Math.floor(Date.now() / 1000);

		// Update interval display
		var intervalSec = ((d.hours || 0) * 3600) + ((d.minutes || 0) * 60);
		if (intervalSec < 60) intervalSec = 60;
		$('#wizarrinvite-cache-interval').text((d.hours || 0) + 'h ' + (d.minutes || 0) + 'min');

		// Restart timer if interval changed
		if (_wizarrinviteLastKnownInterval !== null && _wizarrinviteLastKnownInterval !== intervalSec) {
			wizarrinviteRestartAutoRefreshTimer(intervalSec);
		}
		_wizarrinviteLastKnownInterval = intervalSec;

		// ── Last user check info ──────────────────────────────────────────────
		var $lastCheck = $('#wizarrinvite-last-user-check');
		if ($lastCheck.length) {
			if (d.last_user_check_at) {
				var ago = now - d.last_user_check_at;
				var agoStr = wizarrinviteFormatDuration(ago, true);
				var triggerLabel = wizarrinviteFormatTrigger(d.last_user_trigger);
				$lastCheck.html('📋 Last user check: <strong>' + agoStr + '</strong> — <em>' + triggerLabel + '</em>');

				// Also update the User Check section last-check-info if available
				if (typeof wizarrinviteUpdateLastCheckInfo === 'function') {
					wizarrinviteUpdateLastCheckInfo({
						last_check_at: d.last_user_check_at,
						last_trigger:  d.last_user_trigger,
					});
				}
			} else {
				$lastCheck.html('<span style="opacity:.4;">No user check recorded yet</span>');
			}
		}

		// ── Slot table — Slot + Cached count only ─────────────────────────────
		if (!slots.length) {
			$tbody.html('<tr><td colspan="2" style="opacity:.5; padding:4px 6px;">No deferred slots configured.</td></tr>');
			if (onDone) onDone(d);
			return;
		}

		var rows = '';
		slots.forEach(function (slot) {
			var label = $('<div>').text(slot.label).html();
			if (!slot.deferred) {
				rows += '<tr>' +
					'<td style="padding:4px 6px;">' + label + '</td>' +
					'<td style="padding:4px 6px; opacity:.4; font-size:10px;">live (deferred off)</td>' +
					'</tr>';
				return;
			}
			var countHtml = slot.count !== null
				? '<span style="color:#a78bfa; font-weight:700;">' + slot.count + '</span>'
				: '<span style="color:#fbbf24; font-size:10px;">⏳ pending</span>';

			rows += '<tr>' +
				'<td style="padding:4px 6px;">' + label + '</td>' +
				'<td style="padding:4px 6px;">' + countHtml + '</td>' +
				'</tr>';
		});
		$tbody.html(rows);

		if (onDone) onDone(d);

	}).fail(function () {
		$('#wizarrinvite-cache-status-tbody').html(
			'<tr><td colspan="2" style="opacity:.5; padding:4px 6px;">⚠ Unable to load cache status</td></tr>'
		);
		if (onDone) onDone(null);
	});
}

// ── Smart interval check ──────────────────────────────────────────────────────
/**
 * Fetches cache status. If the configured interval has elapsed since the last
 * user check, triggers /refresh-deferred-counts and updates the display again.
 *
 * @param {boolean} [immediate]  true = called on toggle-ON; false = timer tick
 */
function wizarrinviteAutoCheckIfDue(immediate) {
	wizarrinviteFetchCacheStatus(function (d) {
		if (!d) return;

		var now         = Math.floor(Date.now() / 1000);
		var lastCheckAt = d.last_user_check_at || 0;
		var intervalSec = _wizarrinviteLastKnownInterval || 900; // default 15min

		var overdue = (lastCheckAt === 0) || ((now - lastCheckAt) >= intervalSec);

		if (overdue) {
			// Interval has elapsed — trigger a real Wizarr API check
			$.ajax({
				url: 'api/v2/plugins/wizarrinvite/refresh-deferred-counts',
				method: 'GET',
				dataType: 'json',
				cache: false
			}).always(function () {
				wizarrinviteFetchCacheStatus(); // update display after refresh
			});
		}
	});
}

// ── Manual force-refresh (⟳ button) ──────────────────────────────────────────
/**
 * Always triggers /refresh-deferred-counts regardless of interval.
 * Only called by the manual ⟳ button.
 */
function wizarrinviteRefreshCacheStatus() {
	wizarrinviteFetchCacheStatus();
	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/refresh-deferred-counts',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).always(function () {
		wizarrinviteFetchCacheStatus();
	});
}

// ── Timer management ──────────────────────────────────────────────────────────
function wizarrinviteStopAutoRefreshTimer() {
	if (_wizarrinviteCacheStatusTimer) {
		clearInterval(_wizarrinviteCacheStatusTimer);
		_wizarrinviteCacheStatusTimer = null;
	}
}

function wizarrinviteStartAutoRefreshTimer() {
	if (_wizarrinviteCacheStatusTimer) return;
	if (!wizarrinviteIsAutoRefreshOn()) return;

	// Poll every min(intervalSec/2, 60s) — but at least 30s, at most 5min
	var pollMs = _wizarrinviteLastKnownInterval !== null
		? Math.min(Math.max(Math.floor(_wizarrinviteLastKnownInterval / 2) * 1000, 30000), 300000)
		: 60000;

	_wizarrinviteCacheStatusTimer = setInterval(function () {
		if (!$('#wizarrinvite-cache-status-tbody').length) {
			wizarrinviteStopAutoRefreshTimer();
			return;
		}
		if (!wizarrinviteIsAutoRefreshOn()) {
			wizarrinviteStopAutoRefreshTimer();
			return;
		}
		// Smart check: only calls Wizarr API if the interval has elapsed
		wizarrinviteAutoCheckIfDue(false);
	}, pollMs);
}

function wizarrinviteRestartAutoRefreshTimer(newIntervalSec) {
	wizarrinviteStopAutoRefreshTimer();
	_wizarrinviteLastKnownInterval = newIntervalSec;
	if (wizarrinviteIsAutoRefreshOn()) {
		wizarrinviteStartAutoRefreshTimer();
	}
}

// ── Local time ticker ─────────────────────────────────────────────────────────
function wizarrinviteStartLocalTimeTicker() {
	if (_wizarrinviteLocalTimeTimer) return;
	_wizarrinviteLocalTimeTimer = setInterval(function () {
		var $el = $('#wizarrinvite-local-time');
		if (!$el.length) {
			clearInterval(_wizarrinviteLocalTimeTimer);
			_wizarrinviteLocalTimeTimer = null;
			return;
		}
		var now = new Date();
		var pad = function (n) { return n < 10 ? '0' + n : n; };
		$el.text(
			now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) +
			' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds())
		);
	}, 1000);
}

// ── Manual ⟳ button ───────────────────────────────────────────────────────────
$(document).off('click.wizarrinvite', '#wizarrinvite-cache-status-refresh');
$(document).on('click.wizarrinvite', '#wizarrinvite-cache-status-refresh', function () {
	wizarrinviteRefreshCacheStatus();
});

// ── Init ──────────────────────────────────────────────────────────────────────
function wizarrinviteInitCacheStatus() {
	// Sync checkbox with stored state (no localStorage write)
	wizarrinviteSetAutoRefresh(wizarrinviteIsAutoRefreshOn(), true);

	wizarrinviteStartLocalTimeTicker();

	// Passive first load — only reads /cache-status
	wizarrinviteFetchCacheStatus();

	if (wizarrinviteIsAutoRefreshOn()) {
		wizarrinviteStartAutoRefreshTimer();
	}
}

if ($('#wizarrinvite-local-time').length) {
	wizarrinviteInitCacheStatus();
}
