function wizarrinviteParseCsvInts(raw) {
	return String(raw || '')
		.split(',')
		.map(v => parseInt(v.trim(), 10))
		.filter(v => !isNaN(v));
}

function wizarrinviteCleanLibraries(libraries) {
	const knownExternalIds = new Set();

	libraries.forEach(function (lib) {
		if (lib && lib.external_id && lib.server_id !== null && lib.server_name && lib.server_name !== 'Unknown') {
			knownExternalIds.add(String(lib.external_id));
		}
	});

	return libraries.filter(function (lib) {
		if (!lib) return false;
		const isUnknown = (lib.server_id === null || lib.server_name === 'Unknown');
		const hasKnownTwin = lib.external_id && knownExternalIds.has(String(lib.external_id));
		return !(isUnknown && hasKnownTwin);
	});
}

function wizarrinviteRenderSelector(prefix, servers, libraries) {
	const serverField = $('#WIZARRINVITE-' + prefix + '-server-ids');
	const libraryField = $('#WIZARRINVITE-' + prefix + '-library-ids');
	const selectedServerIds = wizarrinviteParseCsvInts(serverField.val());
	const selectedLibraryIds = wizarrinviteParseCsvInts(libraryField.val());

	const cleanedLibraries = wizarrinviteCleanLibraries(libraries || []);
	const librariesByServer = {};

	cleanedLibraries.forEach(function (lib) {
		if (lib.server_id === null || !lib.server_name || lib.server_name === 'Unknown') return;
		if (!librariesByServer[lib.server_id]) librariesByServer[lib.server_id] = [];
		librariesByServer[lib.server_id].push(lib);
	});

	Object.keys(librariesByServer).forEach(function (sid) {
		librariesByServer[sid].sort(function (a, b) {
			return (a.name || '').localeCompare((b.name || ''), 'fr', { sensitivity: 'base' });
		});
	});

	let html = '<div style="display:flex; flex-direction:column; gap:16px;">';

	(servers || []).forEach(function (server) {
		const sid = parseInt(server.id, 10);
		const checkedServer = selectedServerIds.indexOf(sid) !== -1 ? 'checked' : '';
		const libs = librariesByServer[sid] || [];

		html += '<div style="border:1px solid rgba(255,255,255,0.15); border-radius:12px; padding:16px;">';
		html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">';
		html += '<div><div style="font-size:20px; font-weight:700;">' + (server.name || ('Server ' + sid)) + ' <span style="opacity:.5; font-size:14px; font-weight:400;">(ID = ' + sid + ')</span></div><div style="opacity:.8;">' + (server.server_type || '') + '</div></div>';
		html += '<label style="display:flex; align-items:center; gap:8px; margin:0;">';
		html += '<input class="wizarrinvite-server-checkbox" data-prefix="' + prefix + '" type="checkbox" value="' + sid + '" data-server-name="' + (server.name || ('Server ' + sid)) + '" ' + checkedServer + '>';
		html += '<span>Use this server</span></label></div>';

		if (!libs.length) {
			html += '<div style="opacity:.8;">No libraries found for this server.</div>';
		} else {
			html += '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:8px;">';
			libs.forEach(function (lib) {
				const checkedLib = selectedLibraryIds.indexOf(parseInt(lib.id, 10)) !== -1 ? 'checked' : '';
				html += '<label style="display:flex; align-items:center; gap:8px; border:1px solid rgba(255,255,255,0.08); border-radius:8px; padding:8px 10px; margin:0;">';
				html += '<input class="wizarrinvite-library-checkbox" data-prefix="' + prefix + '" type="checkbox" data-server-id="' + sid + '" data-library-name="' + lib.name + '" value="' + lib.id + '" ' + checkedLib + '>';
				html += '<span>' + lib.name + '</span></label>';
			});
			html += '</div>';
		}

		html += '</div>';
	});

	html += '</div>';
	$('#wizarrinvite-' + prefix + '-selector').html(html);
	wizarrinviteSyncSelection(prefix);
}

function wizarrinviteSyncSelection(prefix) {
	const selectedServers = [];
	const selectedLibraries = [];
	const serverNames = [];
	const libraryNames = [];

	$('.wizarrinvite-server-checkbox[data-prefix="' + prefix + '"]:checked').each(function () {
		selectedServers.push(parseInt($(this).val(), 10));
		serverNames.push($(this).data('server-name'));
	});

	$('.wizarrinvite-library-checkbox[data-prefix="' + prefix + '"]:checked').each(function () {
		selectedLibraries.push(parseInt($(this).val(), 10));
		libraryNames.push($(this).data('library-name'));
	});

	selectedServers.sort((a, b) => a - b);
	selectedLibraries.sort((a, b) => a - b);

	$('#WIZARRINVITE-' + prefix + '-server-ids').val(selectedServers.join(','));
	$('#WIZARRINVITE-' + prefix + '-library-ids').val(selectedLibraries.join(','));

	$('#wizarrinvite-' + prefix + '-selected-servers').text(serverNames.length ? serverNames.join(', ') : '-');
	$('#wizarrinvite-' + prefix + '-selected-libraries').text(libraryNames.length ? libraryNames.join(', ') : '-');
}

function wizarrinviteLoadSelector(prefix) {
	const container = $('#wizarrinvite-' + prefix + '-selector');
	container.html('<div>⏳ Loading servers and libraries...</div>');

	$.when(
		$.ajax({ url: 'api/v2/plugins/wizarrinvite/servers', method: 'GET', dataType: 'json', cache: false }),
		$.ajax({ url: 'api/v2/plugins/wizarrinvite/libraries', method: 'GET', dataType: 'json', cache: false })
	).done(function (serversRes, libsRes) {
		const serversPayload = serversRes[0];
		const libsPayload = libsRes[0];

		if (!serversPayload || !serversPayload.response || serversPayload.response.result !== 'success') {
			container.html('<span style="color:red;">❌ Unable to load servers</span>');
			return;
		}
		if (!libsPayload || !libsPayload.response || libsPayload.response.result !== 'success') {
			container.html('<span style="color:red;">❌ Unable to load libraries</span>');
			return;
		}

		const servers = serversPayload.response.data && serversPayload.response.data.servers ? serversPayload.response.data.servers : [];
		const libraries = libsPayload.response.data && libsPayload.response.data.libraries ? libsPayload.response.data.libraries : [];

		wizarrinviteRenderSelector(prefix, servers, libraries);
	}).fail(function () {
		container.html('<span style="color:red;">❌ Unable to load servers and libraries</span>');
	});
}

$(document).off('click.wizarrinvite', '#wizarrinvite-test-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-test-btn', function (e) {
	e.preventDefault();
	const $r = $('#wizarrinvite-test-result');
	$r.html('Test in progress...');

	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/test',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).done(function (res) {
		if (!res || !res.response) {
			$r.html('<span style="color:red;">❌ Invalid response</span>');
			return;
		}
		$r.html(
			res.response.result === 'success'
				? '<span style="color:lime;">✅ ' + res.response.message + '</span>'
				: '<span style="color:red;">❌ ' + res.response.message + '</span>'
		);
	}).fail(function () {
		$r.html('<span style="color:red;">❌ Unable to start the test</span>');
	});

	return false;
});

$(document).off('click.wizarrinvite', '#wizarrinvite-check-users-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-check-users-btn', function (e) {
	e.preventDefault();
	const $r = $('#wizarrinvite-users-result');
	$r.html('⏳ Fetching user stats...');

	$.when(
		$.ajax({ url: 'api/v2/plugins/wizarrinvite/user-stats', method: 'GET', dataType: 'json', cache: false }),
		$.ajax({ url: 'api/v2/plugins/wizarrinvite/servers',    method: 'GET', dataType: 'json', cache: false })
	).done(function (statsRes, serversRes) {
		const statsPayload   = statsRes[0];
		const serversPayload = serversRes[0];

		if (!statsPayload || !statsPayload.response || statsPayload.response.result !== 'success') {
			$r.html('<span style="color:red;">❌ ' + ((statsPayload && statsPayload.response && statsPayload.response.message) || 'Error') + '</span>');
			return;
		}

		const d = statsPayload.response.data || {};

		let html = '<div style="color:lime; margin-bottom:8px;"><strong>✅ Users loaded successfully</strong></div>';
		html += '<div style="margin-bottom:8px;"><strong>👥 Total users:</strong> ' + (d.count || 0) + '</div>';

		if (d.next_expiry) {
			html += '<div style="font-size:12px; margin-bottom:8px; color:#94a3b8;">⏰ Next expiry: <strong style="color:#f8fafc;">' + d.next_expiry + '</strong></div>';
		}

		// Décompte + liste détaillée par serveur (User.server)
		const perServer     = d.per_server      || {};
		const usersByServer = d.users_by_server || {};
		const perServerKeys = Object.keys(perServer);

		if (perServerKeys.length > 0) {
			html += '<div style="margin-top:10px; display:flex; flex-direction:column; gap:8px;">';
			perServerKeys.forEach(function (serverName) {
				var count = perServer[serverName];
				var users = usersByServer[serverName] || [];
				var uid   = 'wizarrinvite-srv-' + serverName.replace(/[^a-z0-9]/gi, '_');

				html += '<div style="background:rgba(255,255,255,.04); border-radius:8px; padding:8px 12px;">';

				// En-tête du serveur — cliquable pour déplier la liste
				html += '<div style="display:flex; justify-content:space-between; align-items:center; cursor:pointer;" onclick="var l=document.getElementById(\'' + uid + '\');l.style.display=l.style.display===\'none\'?\'block\':\'none\'">';
				html += '<span style="font-weight:600;">🖥️ ' + serverName + '</span>';
				html += '<span style="font-size:12px; color:#a78bfa; font-weight:700;">' + count + ' user' + (count > 1 ? 's' : '') + ' ▾</span>';
				html += '</div>';

				// Liste des utilisateurs (masquée par défaut)
				html += '<div id="' + uid + '" style="display:none; margin-top:8px; display:none;">';
				if (users.length > 0) {
					html += '<div style="display:flex; flex-direction:column; gap:3px;">';
					users.forEach(function (u) {
						var label   = u.username || u.email || '?';
						var expires = u.expires ? ' <span style="color:#94a3b8; font-size:10px;">exp: ' + u.expires + '</span>' : '';
						html += '<div style="font-size:11px; padding:3px 8px; border-radius:4px; background:rgba(255,255,255,.03); display:flex; justify-content:space-between; align-items:center;">';
						html += '<span>👤 ' + label + '</span>';
						html += expires;
						html += '</div>';
					});
					html += '</div>';
				} else {
					html += '<div style="font-size:11px; opacity:.5;">No user detail available.</div>';
				}
				html += '</div>';

				html += '</div>';
			});
			html += '</div>';
		} else {
			// Fallback : liste des serveurs sans comptage (repli /status utilisé)
			const srvList = (serversPayload && serversPayload.response && serversPayload.response.data && serversPayload.response.data.servers) || [];
			if (srvList.length > 0) {
				html += '<div style="margin-top:8px; font-size:12px; color:#94a3b8;">🖥️ Configured servers: ';
				html += srvList.map(function(s){ return (s.name || 'Server ' + s.id); }).join(', ');
				html += '</div>';
			}
		}

		$r.html(html);
	}).fail(function () {
		$r.html('<span style="color:red;">❌ Unable to fetch user stats</span>');
	});

	return false;
});

$(document).off('click.wizarrinvite', '#wizarrinvite-load-manual-selector-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-load-manual-selector-btn', function (e) {
	e.preventDefault();
	wizarrinviteLoadSelector('manual');
	return false;
});

$(document).off('click.wizarrinvite', '#wizarrinvite-load-auto-selector-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-load-auto-selector-btn', function (e) {
	e.preventDefault();
	wizarrinviteLoadSelector('auto');
	return false;
});

$(document).off('change.wizarrinvite', '.wizarrinvite-server-checkbox');
$(document).on('change.wizarrinvite', '.wizarrinvite-server-checkbox', function () {
	const prefix = $(this).data('prefix');
	const serverId = $(this).val();
	const checked = $(this).is(':checked');

	$('.wizarrinvite-library-checkbox[data-prefix="' + prefix + '"][data-server-id="' + serverId + '"]').prop('checked', checked);
	wizarrinviteSyncSelection(prefix);
});

$(document).off('change.wizarrinvite', '.wizarrinvite-library-checkbox');
$(document).on('change.wizarrinvite', '.wizarrinvite-library-checkbox', function () {
	const prefix = $(this).data('prefix');
	const serverId = $(this).data('server-id');
	const serverCheckbox = $('.wizarrinvite-server-checkbox[data-prefix="' + prefix + '"][value="' + serverId + '"]');
	const checkedLibraries = $('.wizarrinvite-library-checkbox[data-prefix="' + prefix + '"][data-server-id="' + serverId + '"]:checked');

	serverCheckbox.prop('checked', checkedLibraries.length > 0);
	wizarrinviteSyncSelection(prefix);
});

$(document).off('click.wizarrinvite', '#wizarrinvite-create-manual-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-create-manual-btn', function (e) {
	e.preventDefault();
	wizarrinviteSyncSelection('manual');

	const $r = $('#wizarrinvite-manual-result');
	$r.html('⏳ Manual creation in progress...');

	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/create-manual',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).done(function (res) {
		if (!res || !res.response) {
			$r.html('<span style="color:red;">❌ Invalid response</span>');
			return;
		}
		if (res.response.result === 'success') {
			const d = res.response.data || {};
			$r.html(
				'<div style="color:lime; margin-bottom:8px;">✅ Manual invitation created</div>' +
				'<div><strong>Code:</strong> ' + (d.code || '-') + '</div>' +
				'<div><strong>URL:</strong> ' + (d.url || '-') + '</div>'
			);
		} else {
			$r.html('<span style="color:red;">❌ ' + (res.response.message || 'Error') + '</span>');
		}
	}).fail(function () {
		$r.html('<span style="color:red;">❌ Unable to create the manual invitation</span>');
	});

	return false;
});

$(document).off('click.wizarrinvite', '#wizarrinvite-check-auto-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-check-auto-btn', function (e) {
	e.preventDefault();
	wizarrinviteSyncSelection('auto');

	const $r = $('#wizarrinvite-auto-result');
	$r.html('⏳ Checking automatic code...');

	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/current',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).done(function (res) {
		if (!res || !res.response) {
			$r.html('<span style="color:red;">❌ Invalid response</span>');
			return;
		}
		if (res.response.result === 'success') {
			const d = res.response.data || {};
			$r.html(
				'<div style="color:lime; margin-bottom:8px;">✅ Automatic code ready</div>' +
				'<div><strong>Code:</strong> ' + (d.code || '-') + '</div>' +
				'<div><strong>URL:</strong> ' + (d.url || '-') + '</div>'
			);
		} else {
			$r.html('<span style="color:red;">❌ ' + (res.response.message || 'Error') + '</span>');
		}
	}).fail(function () {
		$r.html('<span style="color:red;">❌ Unable to check the automatic code</span>');
	});

	return false;
});

// ── Debug : affichage des logs ────────────────────────────────────────────────
function wizarrinviteRefreshLogs() {
	var $log    = $('#wizarrinvite-debug-log');
	var $status = $('#wizarrinvite-debug-status');
	$status.text('⏳ Loading...');

	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/debug/logs',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).done(function (res) {
		if (!res || !res.response || res.response.result !== 'success') {
			$status.text('❌ Error loading logs.');
			return;
		}
		var lines = (res.response.data && res.response.data.lines) || [];
		var path  = (res.response.data && res.response.data.path)  || '';
		if (!lines.length) {
			$log.text('📭 No log entries yet.');
		} else {
			$log.text(lines.join(''));
			$log.scrollTop(0); // newest first
		}
		$status.text('📋 ' + lines.length + ' line(s)' + (path ? ' — ' + path : ''));
	}).fail(function () {
		$status.text('❌ Unable to load logs.');
	});
}

$(document).off('click.wizarrinvite', '#wizarrinvite-debug-refresh-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-debug-refresh-btn', function (e) {
	e.preventDefault();
	wizarrinviteRefreshLogs();
	return false;
});

$(document).off('click.wizarrinvite', '#wizarrinvite-debug-clear-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-debug-clear-btn', function (e) {
	e.preventDefault();
	var $status = $('#wizarrinvite-debug-status');
	$status.text('Clearing...');
	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/debug/clear',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).done(function () {
		$('#wizarrinvite-debug-log').text('(logs cleared)');
		$status.text('Cleared.');
	}).fail(function () {
		$status.text('Error.');
	});
	return false;
});


function wizarrinviteInitState() {
	const manualExpirationEl = $('#WIZARRINVITE-manual-expiration');
	if (manualExpirationEl.length && !manualExpirationEl.data('initialized')) {
		const manualExpiration = String(manualExpirationEl.attr('data-current') || manualExpirationEl.data('current') || manualExpirationEl.val() || '1');
		manualExpirationEl.val(manualExpiration);
		manualExpirationEl.data('initialized', true);
	}

	const minGroupEl = $('#WIZARRINVITE-min-group');
	if (minGroupEl.length && !minGroupEl.data('initialized')) {
		const minGroup = String(minGroupEl.attr('data-current') || minGroupEl.data('current') || minGroupEl.val() || '4');
		minGroupEl.val(minGroup);
		minGroupEl.data('initialized', true);
	}

	if (!$('#wizarrinvite-manual-selector .wizarrinvite-server-checkbox').length) {
		const rawServers = $('#WIZARRINVITE-manual-server-ids').val() || '';
		const rawLibs = $('#WIZARRINVITE-manual-library-ids').val() || '';
		$('#wizarrinvite-manual-selected-servers').text(rawServers || '-');
		$('#wizarrinvite-manual-selected-libraries').text(rawLibs || '-');
	}

	// Initialise le gestionnaire de slots si disponible
	if (typeof wizarrinviteRenderAllSlots === 'function') {
		if ($('#WIZARRINVITE-slots-config').length && !$('#wizarrinvite-slots-container').data('slots-initialized')) {
			wizarrinviteRenderAllSlots();
			$('#wizarrinvite-slots-container').data('slots-initialized', true);
		}
	}
}

$(document).ready(function () {
	wizarrinviteInitState();
});

// Charge slots.js dès que la page de configuration des slots est présente dans le DOM
var _wizarrinviteSlotsLoading = false;
$(document).ajaxComplete(function () {
	if ($('#WIZARRINVITE-slots-config').length
		&& typeof wizarrinviteRenderAllSlots === 'undefined'
		&& !_wizarrinviteSlotsLoading) {
		_wizarrinviteSlotsLoading = true;
		$.getScript('api/v2/plugins/wizarrinvite/js/slots').fail(function () {
			_wizarrinviteSlotsLoading = false;
		});
	}
	wizarrinviteInitState();
});

// ══════════════════════════════════════════════════════════════════════════════
// FIN — le gestionnaire de slots est dans includes/js/slots.js
// ══════════════════════════════════════════════════════════════════════════════
