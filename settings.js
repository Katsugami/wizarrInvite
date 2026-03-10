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
		html += '<div><div style="font-size:20px; font-weight:700;">' + (server.name || ('Server ' + sid)) + '</div><div style="opacity:.8;">' + (server.server_type || '') + '</div></div>';
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
	container.html('<div>Loading servers and libraries...</div>');

	$.when(
		$.ajax({ url: 'api/v2/plugins/wizarrinvite/servers', method: 'GET', dataType: 'json', cache: false }),
		$.ajax({ url: 'api/v2/plugins/wizarrinvite/libraries', method: 'GET', dataType: 'json', cache: false })
	).done(function (serversRes, libsRes) {
		const serversPayload = serversRes[0];
		const libsPayload = libsRes[0];

		if (!serversPayload || !serversPayload.response || serversPayload.response.result !== 'success') {
			container.html('<span style="color:red;">Unable to load servers</span>');
			return;
		}
		if (!libsPayload || !libsPayload.response || libsPayload.response.result !== 'success') {
			container.html('<span style="color:red;">Unable to load libraries</span>');
			return;
		}

		const servers = serversPayload.response.data && serversPayload.response.data.servers ? serversPayload.response.data.servers : [];
		const libraries = libsPayload.response.data && libsPayload.response.data.libraries ? libsPayload.response.data.libraries : [];

		wizarrinviteRenderSelector(prefix, servers, libraries);
	}).fail(function () {
		container.html('<span style="color:red;">Unable to load servers and libraries</span>');
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
			$r.html('<span style="color:red;">Invalid response</span>');
			return;
		}
		$r.html(
			res.response.result === 'success'
				? '<span style="color:lime;">' + res.response.message + '</span>'
				: '<span style="color:red;">' + res.response.message + '</span>'
		);
	}).fail(function () {
		$r.html('<span style="color:red;">Unable to start the test</span>');
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
	$r.html('Manual creation in progress...');

	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/create-manual',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).done(function (res) {
		if (!res || !res.response) {
			$r.html('<span style="color:red;">Invalid response</span>');
			return;
		}
		if (res.response.result === 'success') {
			const d = res.response.data || {};
			$r.html(
				'<div style="color:lime; margin-bottom:8px;">Manual invitation created</div>' +
				'<div><strong>Code:</strong> ' + (d.code || '-') + '</div>' +
				'<div><strong>URL:</strong> ' + (d.url || '-') + '</div>'
			);
		} else {
			$r.html('<span style="color:red;">' + (res.response.message || 'Error') + '</span>');
		}
	}).fail(function () {
		$r.html('<span style="color:red;">Unable to create the manual invitation</span>');
	});

	return false;
});

$(document).off('click.wizarrinvite', '#wizarrinvite-check-auto-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-check-auto-btn', function (e) {
	e.preventDefault();
	wizarrinviteSyncSelection('auto');

	const $r = $('#wizarrinvite-auto-result');
	$r.html('Checking automatic code...');

	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/current',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).done(function (res) {
		if (!res || !res.response) {
			$r.html('<span style="color:red;">Invalid response</span>');
			return;
		}
		if (res.response.result === 'success') {
			const d = res.response.data || {};
			$r.html(
				'<div style="color:lime; margin-bottom:8px;">Automatic code ready</div>' +
				'<div><strong>Code:</strong> ' + (d.code || '-') + '</div>' +
				'<div><strong>URL:</strong> ' + (d.url || '-') + '</div>'
			);
		} else {
			$r.html('<span style="color:red;">' + (res.response.message || 'Error') + '</span>');
		}
	}).fail(function () {
		$r.html('<span style="color:red;">Unable to check the automatic code</span>');
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

	const autoExpirationEl = $('#WIZARRINVITE-auto-expiration');
	if (autoExpirationEl.length && !autoExpirationEl.data('initialized')) {
		const autoExpiration = String(autoExpirationEl.attr('data-current') || autoExpirationEl.data('current') || autoExpirationEl.val() || '1');
		autoExpirationEl.val(autoExpiration);
		autoExpirationEl.data('initialized', true);
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

	if (!$('#wizarrinvite-auto-selector .wizarrinvite-server-checkbox').length) {
		const rawServers = $('#WIZARRINVITE-auto-server-ids').val() || '';
		const rawLibs = $('#WIZARRINVITE-auto-library-ids').val() || '';
		$('#wizarrinvite-auto-selected-servers').text(rawServers || '-');
		$('#wizarrinvite-auto-selected-libraries').text(rawLibs || '-');
	}
}

$(document).ready(function () {
	wizarrinviteInitState();
});

$(document).ajaxComplete(function () {
	wizarrinviteInitState();
});