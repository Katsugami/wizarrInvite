// ─────────────────────────────────────────────────────────────────────────────
// Live User Check handlers
// Loaded dynamically by settings.js when the User Check section is present.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Renders user stats data into the result container.
 * Used by both Check Users (live) and Show Cache (cached).
 *
 * @param {object}  d          API response data object
 * @param {jQuery}  $r         Target container
 * @param {number}  cacheAgeS  If >= 0, prepend a "from cache (Xs ago)" banner
 */
function wizarrinviteRenderUserStats(d, $r, cacheAgeS) {
	var rawCount      = d.count        || 0;
	var uniqueCount   = d.unique_count;   // null if fallback /status
	var groups        = d.groups      || [];
	var ambiguous     = d.ambiguous   || [];
	var perServer     = d.per_server      || {};
	var usersByServer = d.users_by_server || {};
	var perServerKeys = Object.keys(perServer);
	var isFallback    = !!d.fallback;

	var html = '';

	// ── Cache banner ──────────────────────────────────────────────────────────
	if (cacheAgeS >= 0) {
		var ageMin = Math.floor(cacheAgeS / 60);
		var ageSec = cacheAgeS % 60;
		var ageStr = (ageMin > 0 ? ageMin + 'min ' : '') + ageSec + 's ago';
		html += '<div style="background:rgba(167,139,250,.1); border:1px solid rgba(167,139,250,.25); border-radius:8px; padding:6px 12px; margin-bottom:10px; font-size:12px;">';
		html += '📦 <strong style="color:#a78bfa;">Showing cached data</strong> <span style="opacity:.6;">(' + ageStr + ')</span>';
		html += '</div>';
	} else {
		html += '<div style="color:lime; margin-bottom:10px;"><strong>✅ Users loaded</strong></div>';
	}

	// ── Fallback: /users failed ───────────────────────────────────────────────
	if (isFallback) {
		html += '<div style="background:rgba(251,191,36,.1); border:1px solid rgba(251,191,36,.3); border-radius:8px; padding:10px 12px; margin-bottom:10px;">';
		html += '<div style="font-weight:700; color:#fbbf24; margin-bottom:4px;">⚠️ Per-server data unavailable</div>';
		html += '<div style="font-size:11px; opacity:.8;">The <code>/api/users</code> endpoint did not respond correctly. ';
		html += 'Only the raw total (' + rawCount + ') is available via <code>/api/status</code>.<br>';
		html += 'Check the Debug logs for the cause (SSL, timeout, URL).</div>';
		html += '</div>';
		$r.html(html);
		return;
	}

	// ── Per-server counters — main display ────────────────────────────────────
	if (perServerKeys.length > 0) {
		html += '<div style="display:flex; gap:10px; margin-bottom:12px; flex-wrap:wrap;">';
		perServerKeys.forEach(function (serverName) {
			var cnt = perServer[serverName];
			html += '<div style="background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:10px; padding:10px 16px; text-align:center; min-width:90px;">';
			html += '<div style="font-size:24px; font-weight:800; color:#e2e8f0;">' + cnt + '</div>';
			html += '<div style="font-size:11px; opacity:.6; margin-top:3px;">' + serverName + '</div>';
			html += '</div>';
		});
		html += '</div>';
	}

	// ── Deduplication summary ─────────────────────────────────────────────────
	if (uniqueCount != null) {
		html += '<div style="display:flex; gap:10px; margin-bottom:10px; flex-wrap:wrap; align-items:center;">';
		html += '<div style="background:rgba(167,139,250,.12); border-radius:8px; padding:6px 12px; text-align:center;">';
		html += '<div style="font-size:18px; font-weight:700; color:#a78bfa;">' + uniqueCount + '</div>';
		html += '<div style="font-size:10px; opacity:.7;">Unique persons</div></div>';
		html += '<div style="background:rgba(255,255,255,.04); border-radius:8px; padding:6px 12px; text-align:center;">';
		html += '<div style="font-size:18px; font-weight:700;">' + rawCount + '</div>';
		html += '<div style="font-size:10px; opacity:.7;">Records</div></div>';
		if (rawCount !== uniqueCount) {
			html += '<div style="background:rgba(52,211,153,.1); border-radius:8px; padding:6px 12px; text-align:center;">';
			html += '<div style="font-size:18px; font-weight:700; color:#34d399;">' + (rawCount - uniqueCount) + '</div>';
			html += '<div style="font-size:10px; opacity:.7;">Duplicates removed</div></div>';
		}
		html += '</div>';
	}

	if (d.next_expiry) {
		html += '<div style="font-size:12px; margin-bottom:8px; color:#94a3b8;">⏰ Next expiry: <strong style="color:#f8fafc;">' + d.next_expiry + '</strong></div>';
	}

	// ── Ambiguous matches ─────────────────────────────────────────────────────
	if (ambiguous.length > 0) {
		html += '<div style="background:rgba(251,191,36,.1); border:1px solid rgba(251,191,36,.3); border-radius:8px; padding:10px 12px; margin-bottom:10px;">';
		html += '<div style="font-weight:700; color:#fbbf24; margin-bottom:6px;">⚠️ ' + ambiguous.length + ' ambiguous match' + (ambiguous.length > 1 ? 'es' : '') + ' detected</div>';
		html += '<div style="font-size:11px; opacity:.8; margin-bottom:8px;">Same username found with different emails across servers — counted as distinct persons. Check manually.</div>';
		ambiguous.forEach(function (a) {
			html += '<div style="font-size:11px; background:rgba(255,255,255,.04); border-radius:6px; padding:6px 8px; margin-bottom:4px;">';
			html += '<strong>' + (a.username || '?') + '</strong>';
			html += ' — <span style="color:#fbbf24;">IDs [' + a.existing_ids.join(',') + '] email: ' + (a.existing_email || 'empty') + '</span>';
			html += ' vs <span style="color:#fb923c;">ID ' + a.new_id + ' email: ' + (a.new_email || 'empty') + '</span>';
			html += ' on <em>' + a.new_server + '</em>';
			html += '</div>';
		});
		html += '</div>';
	}

	// ── Users on multiple servers ─────────────────────────────────────────────
	var multiServerGroups = groups.filter(function(g){ return g.servers && g.servers.length > 1; });
	if (multiServerGroups.length > 0) {
		var uid0 = 'wz-multi-srv-list';
		html += '<div style="background:rgba(255,255,255,.04); border-radius:8px; padding:8px 12px; margin-bottom:10px;">';
		html += '<div class="wizarrinvite-srv-toggle" data-target="' + uid0 + '" style="display:flex; justify-content:space-between; align-items:center; cursor:pointer;">';
		html += '<span style="font-weight:600;">👥 Users on multiple servers (' + multiServerGroups.length + ')</span><span style="font-size:12px; color:#34d399;">▾</span>';
		html += '</div>';
		html += '<div id="' + uid0 + '" style="display:none; margin-top:8px;">';
		multiServerGroups.forEach(function(g) {
			html += '<div style="font-size:11px; padding:4px 6px; border-radius:4px; background:rgba(255,255,255,.03); margin-bottom:3px;">';
			html += '<strong>' + (g.username || '?') + '</strong> — ' + g.servers.join(', ');
			if (g.emails && g.emails.length) html += ' <span style="opacity:.5;">(' + g.emails[0] + ')</span>';
			html += '</div>';
		});
		html += '</div></div>';
	}

	// ── Detailed per-server lists (collapsible) ───────────────────────────────
	if (perServerKeys.length > 0) {
		html += '<div style="display:flex; flex-direction:column; gap:6px; margin-top:4px;">';
		perServerKeys.forEach(function (serverName) {
			var count = perServer[serverName];
			var users = usersByServer[serverName] || [];
			var uid   = 'wizarrinvite-srv-' + serverName.replace(/[^a-z0-9]/gi, '_');

			html += '<div style="background:rgba(255,255,255,.03); border-radius:8px; padding:6px 10px;">';
			html += '<div class="wizarrinvite-srv-toggle" data-target="' + uid + '" ' +
				'style="display:flex; justify-content:space-between; align-items:center; cursor:pointer;">';
			html += '<span style="font-size:12px;">🖥️ ' + serverName + '</span>';
			html += '<span style="font-size:11px; color:#a78bfa; font-weight:700;">' + count + ' record' + (count > 1 ? 's' : '') + ' ▾</span>';
			html += '</div>';
			html += '<div id="' + uid + '" style="display:none; margin-top:6px;">';
			if (users.length > 0) {
				html += '<div style="display:flex; flex-direction:column; gap:2px;">';
				users.forEach(function (u) {
					var label   = u.username || u.email || '?';
					var expires = u.expires ? ' <span style="color:#94a3b8; font-size:10px;">exp: ' + u.expires + '</span>' : '';
					html += '<div style="font-size:11px; padding:3px 8px; border-radius:4px; background:rgba(255,255,255,.03); display:flex; justify-content:space-between; align-items:center;">';
					html += '<span>👤 ' + label + '</span>' + expires;
					html += '</div>';
				});
				html += '</div>';
			} else {
				html += '<div style="font-size:11px; opacity:.5;">No details available.</div>';
			}
			html += '</div></div>';
		});
		html += '</div>';
	}

	$r.html(html);
}

/**
 * Updates the last-check info line with trigger type and time.
 * Reads last_trigger + last_check_at from the API response data.
 */
function wizarrinviteFormatAgo(ts) {
	var abs = Math.max(0, Math.floor(Date.now() / 1000) - ts);
	var s = abs % 60;
	var m = Math.floor(abs / 60) % 60;
	var h = Math.floor(abs / 3600);
	var str = '';
	if (h > 0) str += h + 'h ';
	if (m > 0) str += m + 'min ';
	str += s + 's';
	return str + ' ago';
}

function wizarrinviteUpdateLastCheckInfo(data) {
	var $info = $('#wizarrinvite-last-check-info');
	if (!$info.length) return;

	var ts      = parseInt(data.last_check_at || 0, 10);
	var trigger = data.last_trigger || '';
	if (!ts) { $info.html(''); return; }

	var triggerLabels = {
		'manual-button': 'manual button',
		'display':       'display page',
		'auto':          'auto refresh',
		'api':           'Wizarr API',
	};
	var triggerLabel = triggerLabels[trigger] || (trigger || 'unknown');

	function render() {
		$info.html('📋 Last check: <strong>' + wizarrinviteFormatAgo(ts) + '</strong> — <em>' + triggerLabel + '</em>');
	}
	render();
	if ($info.data('ticker')) clearInterval($info.data('ticker'));
	$info.data('ticker', setInterval(function () {
		if (!$('#wizarrinvite-last-check-info').length) return;
		render();
	}, 1000));
}

// ── Clear users cache ─────────────────────────────────────────────────────────
$(document).off('click.wizarrinvite', '#wizarrinvite-clear-users-cache-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-clear-users-cache-btn', function (e) {
	e.preventDefault();
	$(this).text('...');
	$.get('api/v2/plugins/wizarrinvite/clear-users-cache').always(function () {
		$('#wizarrinvite-clear-users-cache-btn').text('Clear users cache');
		$('#wizarrinvite-users-result').html('<span style="color:#34d399;">✅ Users cache cleared</span>');
		// Clear last-check info too since the cache is gone
		$('#wizarrinvite-last-check-info').html('');
	});
	return false;
});

// ── Show Cache (display stored data only — no live API call) ──────────────────
$(document).off('click.wizarrinvite', '#wizarrinvite-show-cache-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-show-cache-btn', function (e) {
	e.preventDefault();
	var $r = $('#wizarrinvite-users-result');
	$r.html('⏳ Loading from cache…');

	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/cached-user-stats',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).done(function (res) {
		if (!res || !res.response) {
			$r.html('<span style="color:red;">❌ Unexpected response</span>');
			return;
		}
		if (res.response.result !== 'success') {
			// No cache — just tell the user. Do NOT auto-trigger a live check.
			$r.html('<div style="background:rgba(255,255,255,.04); border-radius:8px; padding:10px 12px; font-size:12px; opacity:.8;">📭 No cache yet — press <strong>Check Users</strong> to fetch live data.</div>');
			return;
		}
		var d = res.response.data || {};
		wizarrinviteRenderUserStats(d, $r, d.cache_age_s !== undefined ? d.cache_age_s : 0);
		wizarrinviteUpdateLastCheckInfo(d);
	}).fail(function () {
		$r.html('<span style="color:red;">❌ Unable to load cache</span>');
	});
	return false;
});

// ── Check Users (live API call) ───────────────────────────────────────────────
$(document).off('click.wizarrinvite', '#wizarrinvite-check-users-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-check-users-btn', function (e) {
	e.preventDefault();
	var $r = $('#wizarrinvite-users-result');
	$r.html('⏳ Fetching users from Wizarr… (may take a moment)');
	$('#wizarrinvite-last-check-info').html('🔄 Check in progress…');

	$.ajax({
		// Pass trigger type so it's recorded in the cache and debug log
		url: 'api/v2/plugins/wizarrinvite/user-stats?trigger=manual-button',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).done(function (res) {
		if (!res || !res.response || res.response.result !== 'success') {
			$r.html('<span style="color:red;">❌ ' + ((res && res.response && res.response.message) || 'Error') + '</span>');
			$('#wizarrinvite-last-check-info').html('');
			return;
		}
		var d = res.response.data || {};
		wizarrinviteRenderUserStats(d, $r, -1);
		wizarrinviteUpdateLastCheckInfo(d);
	}).fail(function () {
		$r.html('<span style="color:red;">❌ Unable to fetch user stats</span>');
		$('#wizarrinvite-last-check-info').html('');
	});

	return false;
});

// ── Collapsible toggle for per-server lists ───────────────────────────────────
$(document).off('click.wizarrinvite', '.wizarrinvite-srv-toggle');
$(document).on('click.wizarrinvite', '.wizarrinvite-srv-toggle', function () {
	var target = $(this).data('target');
	$('#' + target).slideToggle(150);
});
