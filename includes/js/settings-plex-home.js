// ─────────────────────────────────────────────────────────────────────────────
// Plex Home users — settings CRUD
// Loaded dynamically when the Plex Home section is present.
// ─────────────────────────────────────────────────────────────────────────────

var _wizarrinvitePlexHomeUsers = [];
var _wizarrinvitePlexHomeServers = [];

/**
 * Loads the current Plex Home users from the API and renders them.
 */
function wizarrinviteLoadPlexHomeUsers() {
	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/plex-home-users',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).done(function (res) {
		if (res && res.response && res.response.result === 'success') {
			_wizarrinvitePlexHomeUsers = (res.response.data && res.response.data.users) || [];
		} else {
			_wizarrinvitePlexHomeUsers = [];
		}
		wizarrinviteRenderPlexHomeUsers();
	}).fail(function () {
		$('#wizarrinvite-plex-home-list').html('<div style="color:red; font-size:12px;">❌ Unable to load</div>');
	});
}

/**
 * Loads server names from the API (for the server checkboxes).
 */
function wizarrinviteLoadPlexHomeServers(callback) {
	if (_wizarrinvitePlexHomeServers.length > 0) {
		if (callback) callback();
		return;
	}
	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/servers',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).done(function (res) {
		var servers = (res && res.response && res.response.data && res.response.data.servers) || [];
		// Keep only Plex servers (PH users are Plex-only).
		// Fall back to all servers if the server_type field is absent in this Wizarr version.
		var plexServers = servers.filter(function (s) {
			var t = (s.server_type || s.type || '').toLowerCase();
			return t === '' || t === 'plex';
		});
		if (plexServers.length === 0) plexServers = servers; // safety fallback
		_wizarrinvitePlexHomeServers = plexServers.map(function (s) { return s.name || ('Server ' + s.id); });
		if (callback) callback();
	}).fail(function () {
		if (callback) callback();
	});
}

/**
 * Renders the Plex Home user list into #wizarrinvite-plex-home-list.
 */
function wizarrinviteRenderPlexHomeUsers() {
	var $list = $('#wizarrinvite-plex-home-list');
	if (!$list.length) return;

	if (!_wizarrinvitePlexHomeUsers.length) {
		$list.html('<div style="opacity:.5; font-size:12px;">No Plex Home users declared yet.</div>');
		return;
	}

	var html = '';
	_wizarrinvitePlexHomeUsers.forEach(function (u, idx) {
		html += '<div class="wz-ph-row" data-idx="' + idx + '" style="background:rgba(255,255,255,.04); border-radius:8px; padding:8px 10px;">';
		html += '<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">';
		html += '<span style="font-size:11px; opacity:.5; min-width:30px; font-weight:700;">' + (u.id || ('PH' + (idx + 1))) + '</span>';
		html += '<input type="text" class="wz-ph-name form-control" data-idx="' + idx + '" value="' + $('<div>').text(u.name || '').html() + '" placeholder="Display name" style="flex:1; min-width:120px; font-size:12px;">';
		html += '<button type="button" class="wz-ph-remove-btn btn btn-danger btn-sm" data-idx="' + idx + '" style="flex-shrink:0;">✕</button>';
		html += '</div>';
		// Server checkboxes
		if (_wizarrinvitePlexHomeServers.length > 0) {
			html += '<div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">';
			_wizarrinvitePlexHomeServers.forEach(function (srvName) {
				var checked = (u.servers || []).indexOf(srvName) !== -1 ? 'checked' : '';
				html += '<label class="wz-toggle" style="font-size:11px;">';
				html += '<input type="checkbox" class="wz-ph-srv-cb" data-idx="' + idx + '" data-server="' + srvName + '" ' + checked + '>';
				html += '<span class="wz-toggle-track" style="width:32px; height:18px;"></span>';
				html += '<span>' + srvName + '</span></label>';
			});
			html += '</div>';
		} else {
			// Manual server text input fallback
			var srvVal = (u.servers || []).join(', ');
			html += '<input type="text" class="wz-ph-servers-text form-control" data-idx="' + idx + '" value="' + $('<div>').text(srvVal).html() + '" placeholder="Server names (comma-separated)" style="margin-top:6px; font-size:11px;">';
		}
		html += '</div>';
	});
	$list.html(html);
}

/**
 * Reads current state from the DOM back into _wizarrinvitePlexHomeUsers.
 */
function wizarrinviteCollectPlexHomeUsers() {
	_wizarrinvitePlexHomeUsers.forEach(function (u, idx) {
		var $row = $('.wz-ph-row[data-idx="' + idx + '"]');
		u.name = $row.find('.wz-ph-name').val() || '';

		if (_wizarrinvitePlexHomeServers.length > 0) {
			var servers = [];
			$row.find('.wz-ph-srv-cb:checked').each(function () {
				servers.push($(this).data('server'));
			});
			u.servers = servers;
		} else {
			var raw = $row.find('.wz-ph-servers-text').val() || '';
			u.servers = raw.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
		}
	});
}

// ── Add user ──────────────────────────────────────────────────────────────────
$(document).off('click.wizarrinvite', '#wizarrinvite-plex-home-add-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-plex-home-add-btn', function (e) {
	e.preventDefault();
	wizarrinviteCollectPlexHomeUsers();
	var n = _wizarrinvitePlexHomeUsers.length + 1;
	_wizarrinvitePlexHomeUsers.push({ id: 'PH' + n, name: '', servers: [] });

	wizarrinviteLoadPlexHomeServers(function () {
		wizarrinviteRenderPlexHomeUsers();
	});
	return false;
});

// ── Remove user ───────────────────────────────────────────────────────────────
$(document).off('click.wizarrinvite', '.wz-ph-remove-btn');
$(document).on('click.wizarrinvite', '.wz-ph-remove-btn', function (e) {
	e.preventDefault();
	wizarrinviteCollectPlexHomeUsers();
	var idx = parseInt($(this).data('idx'), 10);
	_wizarrinvitePlexHomeUsers.splice(idx, 1);
	// Re-assign IDs
	_wizarrinvitePlexHomeUsers.forEach(function (u, i) { u.id = 'PH' + (i + 1); });
	wizarrinviteRenderPlexHomeUsers();
	return false;
});

// ── Save ──────────────────────────────────────────────────────────────────────
$(document).off('click.wizarrinvite', '#wizarrinvite-plex-home-save-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-plex-home-save-btn', function (e) {
	e.preventDefault();
	wizarrinviteCollectPlexHomeUsers();

	var $result = $('#wizarrinvite-plex-home-result');
	$result.text('Saving…');

	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/save-plex-home-users',
		method: 'GET',
		data: { users: JSON.stringify(_wizarrinvitePlexHomeUsers) },
		dataType: 'json',
		cache: false
	}).done(function (res) {
		if (res && res.response && res.response.result === 'success') {
			_wizarrinvitePlexHomeUsers = (res.response.data && res.response.data.users) || [];
			wizarrinviteRenderPlexHomeUsers();
			$result.html('<span style="color:#34d399;">✅ Saved</span>');
		} else {
			$result.html('<span style="color:red;">❌ ' + ((res && res.response && res.response.message) || 'Error') + '</span>');
		}
	}).fail(function () {
		$result.html('<span style="color:red;">❌ Save failed</span>');
	});

	return false;
});

// ── Auto-load on init ─────────────────────────────────────────────────────────
wizarrinviteLoadPlexHomeServers(function () {
	wizarrinviteLoadPlexHomeUsers();
});
