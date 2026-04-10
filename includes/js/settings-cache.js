// ─────────────────────────────────────────────────────────────────────────────
// Cache Status — local time ticker + interval auto-check
// Loaded dynamically by settings.js when the Cache Status section is present.
// ─────────────────────────────────────────────────────────────────────────────

var _wizarrinviteLocalTimeTimer    = null;
var _wizarrinviteCacheStatusTimer  = null;  // setTimeout handle (single-shot, rescheduled)
var _wizarrinviteLastKnownInterval = null;  // seconds — set from /cache-status response
var _wizarrinviteLastCheckAt       = 0;     // Unix timestamp of last user check

// ── Auto-refresh state (localStorage) ────────────────────────────────────────
function wizarrinviteIsAutoRefreshOn() {
	return localStorage.getItem('wizarrinvite_autorefresh') !== 'off';
}

function wizarrinviteSetAutoRefresh(on, skipStorage) {
	if (!skipStorage) localStorage.setItem('wizarrinvite_autorefresh', on ? 'on' : 'off');
	$('#wizarrinvite-autorefresh-cb').prop('checked', on);
}

// ── Helper : set a "running" state on the last-check-info line ───────────────
function wizarrinviteSetCheckingStatus(msg) {
	var $info = $('#wizarrinvite-last-check-info');
	if (!$info.length) return;
	if ($info.data('ticker')) { clearInterval($info.data('ticker')); $info.removeData('ticker'); }
	$info.html(msg || '⏳ <em>Auto check running…</em>');
}

// ── wz-toggle handler ─────────────────────────────────────────────────────────
$(document).off('change.wizarrinvite', '#wizarrinvite-autorefresh-cb');
$(document).on('change.wizarrinvite', '#wizarrinvite-autorefresh-cb', function () {
	var on = $(this).prop('checked');
	localStorage.setItem('wizarrinvite_autorefresh', on ? 'on' : 'off');
	if (on) {
		wizarrinviteSetCheckingStatus('⏳ <em>Auto check running…</em>');
		// Run an immediate full check first.
		// wizarrinviteRunFullCheck ends with wizarrinviteFetchCacheStatus, which sets
		// _wizarrinviteLastKnownInterval and _wizarrinviteLastCheckAt.
		// Only THEN schedule the next check with the correct interval.
		wizarrinviteRunFullCheck(function () {
			wizarrinviteScheduleNextCheck();
		});
	} else {
		wizarrinviteStopAutoRefreshTimer();
		wizarrinviteFetchCacheStatus();
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
 * Reads /cache-status, updates the display, and stores the interval + last-check
 * timestamp in globals so the scheduler can use them.
 *
 * @param {function} [onDone]  Called with the data object, or null on failure.
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

		// ── Store interval & last-check timestamp (used by scheduler) ─────────
		var intervalSec = ((d.hours || 0) * 3600) + ((d.minutes || 0) * 60);
		if (intervalSec < 60) intervalSec = 60;
		_wizarrinviteLastKnownInterval = intervalSec;

		if (d.last_user_check_at) {
			_wizarrinviteLastCheckAt = d.last_user_check_at;
		}

		// ── Update display ────────────────────────────────────────────────────
		$('#wizarrinvite-cache-interval').text((d.hours || 0) + 'h ' + (d.minutes || 0) + 'min');

		// ── Last user check info ─────────────────────────────────────────────
		// Always update #wizarrinvite-last-check-info (Live User Check section)
		// regardless of whether the legacy #wizarrinvite-last-user-check exists.
		if (typeof wizarrinviteUpdateLastCheckInfo === 'function') {
			wizarrinviteUpdateLastCheckInfo({
				last_check_at: d.last_user_check_at || 0,
				last_trigger:  d.last_user_trigger  || '',
			});
		}

		// ── Slot table ────────────────────────────────────────────────────────
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

// ── Full user check + slot cache refresh ─────────────────────────────────────
/**
 * Step 1: /user-stats?trigger=auto  — fetches fresh data from Wizarr.
 * Step 2: /refresh-deferred-counts  — refreshes slot count caches.
 * Step 3: wizarrinviteFetchCacheStatus — updates display + globals.
 */
function wizarrinviteRunFullCheck(onDone) {
	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/user-stats?trigger=auto',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).always(function () {
		$.ajax({
			url: 'api/v2/plugins/wizarrinvite/refresh-deferred-counts',
			method: 'GET',
			dataType: 'json',
			cache: false
		}).always(function () {
			wizarrinviteFetchCacheStatus(onDone || null);
		});
	});
}

// ── Scheduler (setTimeout-based — one shot, rescheduled after each check) ────
function wizarrinviteStopAutoRefreshTimer() {
	if (_wizarrinviteCacheStatusTimer) {
		clearTimeout(_wizarrinviteCacheStatusTimer);
		_wizarrinviteCacheStatusTimer = null;
	}
}

/**
 * Schedules the NEXT auto-check by comparing the last check timestamp with the
 * configured interval. Uses setTimeout (not setInterval) so each check starts
 * a fresh countdown from the actual completion time of the previous check.
 *
 * Behaviour:
 *  - Never checked yet, or check is overdue  → fires in 5 seconds
 *  - Partially elapsed (e.g. 30 s of 120 s)  → fires in 90 seconds
 *  - Just checked (elapsed ≈ 0)              → fires in intervalSec seconds
 */
function wizarrinviteScheduleNextCheck() {
	wizarrinviteStopAutoRefreshTimer();
	if (!wizarrinviteIsAutoRefreshOn()) return;

	// Fall back to 60 s if interval somehow still unknown
	var intervalSec = _wizarrinviteLastKnownInterval || 60;
	var now         = Math.floor(Date.now() / 1000);
	var elapsed     = now - (_wizarrinviteLastCheckAt || 0);
	// If overdue or never checked → fire soon; otherwise wait the remainder
	var waitSec     = (elapsed >= intervalSec) ? 5 : Math.max(5, intervalSec - elapsed);

	_wizarrinviteCacheStatusTimer = setTimeout(function () {
		_wizarrinviteCacheStatusTimer = null;
		if (!wizarrinviteIsAutoRefreshOn()) return;
		// Show "running" status, then run the full check
		wizarrinviteSetCheckingStatus('⏳ <em>Auto check running…</em>');
		wizarrinviteRunFullCheck(function () {
			// Schedule the next one after this check completes
			wizarrinviteScheduleNextCheck();
		});
	}, waitSec * 1000);
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
	wizarrinviteSetCheckingStatus('⏳ <em>Check running…</em>');
	// After the manual check, reset the auto-check countdown from now
	wizarrinviteRunFullCheck(function () {
		if (wizarrinviteIsAutoRefreshOn()) {
			wizarrinviteScheduleNextCheck();
		}
	});
});

// ── Init ──────────────────────────────────────────────────────────────────────
function wizarrinviteInitCacheStatus() {
	wizarrinviteSetAutoRefresh(wizarrinviteIsAutoRefreshOn(), true);
	wizarrinviteStartLocalTimeTicker();

	// Fetch cache status to populate _wizarrinviteLastKnownInterval and
	// _wizarrinviteLastCheckAt, then let the scheduler decide when to fire.
	wizarrinviteFetchCacheStatus(function (d) {
		if (!wizarrinviteIsAutoRefreshOn()) return;
		// wizarrinviteScheduleNextCheck uses the globals set above:
		//  - overdue or never checked → fires in 5 s
		//  - partially elapsed        → fires at the right remaining time
		wizarrinviteScheduleNextCheck();
	});
}

if ($('#wizarrinvite-local-time').length) {
	wizarrinviteInitCacheStatus();
}
