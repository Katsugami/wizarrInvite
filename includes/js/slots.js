// ══════════════════════════════════════════════════════════════════════════════
// Gestionnaire de slots automatiques — chargé dynamiquement par settings.js
// Chaque slot a ses propres paramètres et son propre code d'invitation permanent.
// La configuration de tous les slots est sérialisée en JSON dans un seul champ
// caché : #WIZARRINVITE-slots-config
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Lit la config des slots depuis le champ caché Organizr.
 */
function wizarrinviteGetSlotsConfig() {
	var raw = $('#WIZARRINVITE-slots-config').val() || '[]';
	try { return JSON.parse(raw); } catch (e) { return []; }
}

/**
 * Sérialise les slots dans le champ caché pour qu'Organizr les sauvegarde.
 */
function wizarrinviteSaveSlotsConfig(slots) {
	$('#WIZARRINVITE-slots-config').val(JSON.stringify(slots));
}

/**
 * Retourne le prochain ID disponible (max existant + 1).
 */
function wizarrinviteNextSlotId(slots) {
	var max = 0;
	slots.forEach(function (s) { if ((s.id || 0) > max) max = s.id; });
	return max + 1;
}

/**
 * Lit les valeurs du formulaire d'un slot et retourne l'objet slot à jour.
 */
function wizarrinviteCollectSlotData(slotId) {
	return {
		id:                   slotId,
		label:                $('#wizarr-slot-' + slotId + '-label').val()            || ('Slot ' + slotId),
		min_group:            $('#wizarr-slot-' + slotId + '-min-group').val()         || '5',
		expiration:           $('#wizarr-slot-' + slotId + '-expiration').val()        || '1',
		access_days:          $('#wizarr-slot-' + slotId + '-access-days').val()       || '7',
		max_users:            $('#wizarr-slot-' + slotId + '-max-users').val()          || '100',
		server_count:         $('#wizarr-slot-' + slotId + '-server-count').val()       || '1',
		bundle_id:            $('#wizarr-slot-' + slotId + '-bundle-id').val()            || '',
		server_ids:           $('#WIZARRINVITE-slot-' + slotId + '-server-ids').val()  || '',
		library_ids:          $('#WIZARRINVITE-slot-' + slotId + '-library-ids').val() || '',
		allow_downloads:      $('#wizarr-slot-' + slotId + '-allow-downloads').is(':checked'),
		allow_live_tv:        $('#wizarr-slot-' + slotId + '-allow-live-tv').is(':checked'),
		allow_mobile_uploads: $('#wizarr-slot-' + slotId + '-allow-mobile-uploads').is(':checked'),
	};
}

/**
 * Parcourt tous les slots affichés et met à jour le champ caché.
 */
function wizarrinviteSyncAllSlots() {
	var slots = [];
	$('.wizarr-slot-card').each(function () {
		var slotId = parseInt($(this).data('slot-id'), 10);
		if (!isNaN(slotId)) {
			slots.push(wizarrinviteCollectSlotData(slotId));
		}
	});
	wizarrinviteSaveSlotsConfig(slots);
}

/**
 * Génère le HTML d'un slot (carte de configuration).
 */
function wizarrinviteRenderSlot(slot) {
	var id         = slot.id;
	var label      = slot.label      || ('Slot ' + id);
	var minGroup   = String(slot.min_group   || '5');
	var expiration = String(slot.expiration  || '1');
	var accessDays = String(slot.access_days || '7');
	var serverIds  = slot.server_ids  || '';
	var libraryIds = slot.library_ids || '';

	var maxUsers    = String(slot.max_users    || '100');
	var serverCount = String(slot.server_count || '1');
	var bundleId    = String(slot.bundle_id    || '');

	var expirationOptions = [
		['1',     '1 day'],
		['7',     '7 days'],
		['30',    '30 days'],
		['never', 'Never'],
	].map(function (o) {
		return '<option value="' + o[0] + '"' + (expiration === o[0] ? ' selected' : '') + '>' + o[1] + '</option>';
	}).join('');

	var minGroupOptions = [
		['1', '1 - Admin'],
		['2', '2 - Co-Admin'],
		['3', '3 - Super User'],
		['4', '4 - Power User'],
		['5', '5 - User'],
		['6', '6 - Guest'],
	].map(function (o) {
		return '<option value="' + o[0] + '"' + (minGroup === o[0] ? ' selected' : '') + '>' + o[1] + '</option>';
	}).join('');

	var displayUrl = '/api/v2/plugins/wizarrinvite/display/' + id;

	return (
		'<div class="wizarr-slot-card" data-slot-id="' + id + '" style="' +
			'border:1px solid rgba(255,255,255,.15); border-radius:12px; padding:16px; margin-bottom:14px;">' +

			// En-tête : numéro du slot + bouton supprimer
			'<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">' +
				'<div style="font-size:16px; font-weight:700;">Slot #' + id + '</div>' +
				'<button type="button" class="btn btn-danger wizarr-remove-slot-btn" data-slot-id="' + id + '" ' +
					'style="padding:4px 10px; font-size:12px;">Remove</button>' +
			'</div>' +

			// Label + groupe Organizr
			'<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">' +
				'<div>' +
					'<label style="display:block; margin-bottom:4px;">Label</label>' +
					'<input type="text" id="wizarr-slot-' + id + '-label" ' +
						'class="form-control wizarr-slot-field" data-slot-id="' + id + '" ' +
						'value="' + label.replace(/"/g, '&quot;') + '" placeholder="VIP Access">' +
				'</div>' +
				'<div>' +
					'<label style="display:block; margin-bottom:4px;">Min Organizr Group</label>' +
					'<select id="wizarr-slot-' + id + '-min-group" ' +
						'class="form-control wizarr-slot-field" data-slot-id="' + id + '">' +
						minGroupOptions +
					'</select>' +
				'</div>' +
			'</div>' +

			// Expiration + durée d'accès
			'<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">' +
				'<div>' +
					'<label style="display:block; margin-bottom:4px;">Wizarr Expiration</label>' +
					'<select id="wizarr-slot-' + id + '-expiration" ' +
						'class="form-control wizarr-slot-field" data-slot-id="' + id + '">' +
						expirationOptions +
					'</select>' +
				'</div>' +
				'<div>' +
					'<label style="display:block; margin-bottom:4px;">Access Duration (days)</label>' +
					'<input type="text" id="wizarr-slot-' + id + '-access-days" ' +
						'class="form-control wizarr-slot-field" data-slot-id="' + id + '" ' +
						'value="' + accessDays + '" placeholder="7">' +
				'</div>' +
			'</div>' +

			// Max users + Server count
			'<div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">' +
				'<div>' +
					'<label style="display:block; margin-bottom:4px;">Max Users</label>' +
					'<input type="text" id="wizarr-slot-' + id + '-max-users" ' +
						'class="form-control wizarr-slot-field" data-slot-id="' + id + '" ' +
						'value="' + maxUsers + '" placeholder="100">' +
					'<div style="margin-top:3px; font-size:11px; opacity:.6;">Total limit (all servers combined).</div>' +
				'</div>' +
				'<div>' +
					'<label style="display:block; margin-bottom:4px;">Server Count</label>' +
					'<input type="text" id="wizarr-slot-' + id + '-server-count" ' +
						'class="form-control wizarr-slot-field" data-slot-id="' + id + '" ' +
						'value="' + serverCount + '" placeholder="1">' +
					'<div style="margin-top:3px; font-size:11px; opacity:.6;">Servers sharing one account (effective = total ÷ count).</div>' +
				'</div>' +
			'</div>' +

			// Bundle
			'<div style="margin-bottom:10px;">' +
				'<label style="display:block; margin-bottom:4px;">Bundle</label>' +
				'<input type="text" id="wizarr-slot-' + id + '-bundle-id" ' +
					'class="form-control wizarr-slot-field" data-slot-id="' + id + '" ' +
					'style="max-width:180px;" placeholder="Empty = default" ' +
					'value="' + bundleId.replace(/"/g, '&quot;') + '">' +
				'<div style="margin-top:3px; font-size:11px; opacity:.6;">Empty = default bundle. 1 = first bundle, 2 = second, etc.</div>' +
			'</div>' +

			// Options (checkboxes)
			'<div style="display:flex; gap:20px; margin-bottom:12px; flex-wrap:wrap;">' +
				'<label style="display:flex; align-items:center; gap:6px; margin:0;">' +
					'<input type="checkbox" id="wizarr-slot-' + id + '-allow-downloads" ' +
						'class="wizarr-slot-field" data-slot-id="' + id + '" ' +
						(slot.allow_downloads ? 'checked' : '') + '> Allow Downloads' +
				'</label>' +
				'<label style="display:flex; align-items:center; gap:6px; margin:0;">' +
					'<input type="checkbox" id="wizarr-slot-' + id + '-allow-live-tv" ' +
						'class="wizarr-slot-field" data-slot-id="' + id + '" ' +
						(slot.allow_live_tv ? 'checked' : '') + '> Allow Live TV' +
				'</label>' +
				'<label style="display:flex; align-items:center; gap:6px; margin:0;">' +
					'<input type="checkbox" id="wizarr-slot-' + id + '-allow-mobile-uploads" ' +
						'class="wizarr-slot-field" data-slot-id="' + id + '" ' +
						(slot.allow_mobile_uploads ? 'checked' : '') + '> Allow Mobile Uploads' +
				'</label>' +
			'</div>' +

			// Sélecteur serveurs / bibliothèques
			'<div style="margin-bottom:12px;">' +
				'<input type="hidden" id="WIZARRINVITE-slot-' + id + '-server-ids" value="' + serverIds + '">' +
				'<input type="hidden" id="WIZARRINVITE-slot-' + id + '-library-ids" value="' + libraryIds + '">' +
				'<button type="button" class="btn btn-info wizarr-load-selector-btn" ' +
					'data-slot-id="' + id + '" style="font-size:12px; padding:5px 10px;">' +
					'Load Servers &amp; Libraries' +
				'</button>' +
				'<div id="wizarrinvite-slot-' + id + '-selector" style="margin-top:8px;"></div>' +
				'<div style="margin-top:6px; font-size:12px;">' +
					'<strong>Servers:</strong> <span id="wizarrinvite-slot-' + id + '-selected-servers">-</span><br>' +
					'<strong>Libraries:</strong> <span id="wizarrinvite-slot-' + id + '-selected-libraries">-</span>' +
				'</div>' +
			'</div>' +

			// Bouton Check / Recreate + URL d'affichage
			'<div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">' +
				'<button type="button" class="btn btn-success wizarr-check-slot-btn" ' +
					'data-slot-id="' + id + '" style="font-size:12px; padding:5px 10px;">' +
					'Check / Recreate' +
				'</button>' +
				'<span style="font-size:12px; opacity:.7;">Display URL: <code>' + displayUrl + '</code></span>' +
			'</div>' +
			'<div id="wizarr-slot-' + id + '-result" style="margin-top:8px; font-size:13px;"></div>' +

		'</div>'
	);
}

/**
 * Relit la config depuis le champ caché et réaffiche tous les slots.
 */
function wizarrinviteRenderAllSlots() {
	var slots     = wizarrinviteGetSlotsConfig();
	var container = $('#wizarrinvite-slots-container');
	if (!container.length) return;

	if (!slots.length) {
		container.html('<div style="opacity:.7; margin-bottom:8px;">No slots configured. Click "+ Add Slot" to create one.</div>');
		return;
	}

	var html = '';
	slots.forEach(function (slot) { html += wizarrinviteRenderSlot(slot); });
	container.html(html);

	slots.forEach(function (slot) {
		var id = slot.id;
		if (!$('#wizarrinvite-slot-' + id + '-selector .wizarrinvite-server-checkbox').length) {
			$('#wizarrinvite-slot-' + id + '-selected-servers').text(slot.server_ids  || '-');
			$('#wizarrinvite-slot-' + id + '-selected-libraries').text(slot.library_ids || '-');
		}
	});
}

// ── Ajouter un slot ───────────────────────────────────────────────────────────
$(document).off('click.wizarrinvite', '#wizarrinvite-add-slot-btn');
$(document).on('click.wizarrinvite', '#wizarrinvite-add-slot-btn', function (e) {
	e.preventDefault();
	var slots = wizarrinviteGetSlotsConfig();
	var newId = wizarrinviteNextSlotId(slots);
	slots.push({
		id:                   newId,
		label:                'Slot ' + newId,
		min_group:            '5',
		expiration:           '1',
		access_days:          '7',
		max_users:            '100',
		server_count:         '1',
		bundle_id:            '',
		server_ids:           '',
		library_ids:          '',
		allow_downloads:      false,
		allow_live_tv:        false,
		allow_mobile_uploads: false,
	});
	wizarrinviteSaveSlotsConfig(slots);
	wizarrinviteRenderAllSlots();
	return false;
});

// ── Supprimer un slot ─────────────────────────────────────────────────────────
$(document).off('click.wizarrinvite', '.wizarr-remove-slot-btn');
$(document).on('click.wizarrinvite', '.wizarr-remove-slot-btn', function (e) {
	e.preventDefault();
	var slotId = parseInt($(this).data('slot-id'), 10);
	if (!confirm('Remove slot #' + slotId + '? The cached invite code will no longer be served.')) return false;
	var slots = wizarrinviteGetSlotsConfig().filter(function (s) { return s.id !== slotId; });
	wizarrinviteSaveSlotsConfig(slots);
	wizarrinviteRenderAllSlots();
	return false;
});

// ── Synchroniser le champ caché quand un champ de slot change ─────────────────
$(document).off('change.wizarrinvite input.wizarrinvite-slot', '.wizarr-slot-field');
$(document).on('change.wizarrinvite input.wizarrinvite-slot', '.wizarr-slot-field', function () {
	wizarrinviteSyncAllSlots();
});

// ── Charger le sélecteur serveurs/bibliothèques d'un slot ─────────────────────
$(document).off('click.wizarrinvite', '.wizarr-load-selector-btn');
$(document).on('click.wizarrinvite', '.wizarr-load-selector-btn', function (e) {
	e.preventDefault();
	var slotId = $(this).data('slot-id');
	wizarrinviteLoadSelector('slot-' + slotId);
	return false;
});

// ── Override de wizarrinviteSyncSelection pour les slots ──────────────────────
var _origSyncSelection = wizarrinviteSyncSelection;
wizarrinviteSyncSelection = function (prefix) {
	_origSyncSelection(prefix);
	if (prefix.indexOf('slot-') === 0) {
		wizarrinviteSyncAllSlots();
	}
};

// ── Check / Recreate un slot ──────────────────────────────────────────────────
$(document).off('click.wizarrinvite', '.wizarr-check-slot-btn');
$(document).on('click.wizarrinvite', '.wizarr-check-slot-btn', function (e) {
	e.preventDefault();
	wizarrinviteSyncAllSlots();

	var slotId = parseInt($(this).data('slot-id'), 10);
	var $r     = $('#wizarr-slot-' + slotId + '-result');
	$r.html('Checking slot #' + slotId + '...');

	$.ajax({
		url:      'api/v2/plugins/wizarrinvite/slot-user-stats/' + slotId,
		method:   'GET',
		dataType: 'json',
		cache:    false,
	}).done(function (statsRes) {
		var statsHtml = '';

		if (statsRes && statsRes.response && statsRes.response.result === 'success') {
			var d = statsRes.response.data || {};
			var pct        = d.max ? Math.min(100, Math.round(d.count / d.max * 100)) : 0;
			var limitColor = d.limit_reached ? '#f87171' : pct >= 80 ? '#f59e0b' : '#22c55e';
			statsHtml += '<div style="background:rgba(255,255,255,.04); border-radius:8px; padding:6px 10px; margin-bottom:8px;">';
			statsHtml += '<div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">';
			statsHtml += '<span>Users (effective)</span>';
			statsHtml += '<span style="color:' + limitColor + '; font-weight:700;">' + d.count + ' / ' + d.max + '</span>';
			statsHtml += '</div>';
			statsHtml += '<div style="height:5px; border-radius:999px; background:rgba(255,255,255,.1); overflow:hidden;">';
			statsHtml += '<div style="height:100%; width:' + pct + '%; background:' + limitColor + '; border-radius:999px;"></div>';
			statsHtml += '</div>';
			if (d.server_count > 1) {
				statsHtml += '<div style="font-size:11px; opacity:.6; margin-top:3px;">raw total: ' + d.raw_count + ' ÷ ' + d.server_count + ' servers</div>';
			}
			if (d.limit_reached) {
				statsHtml += '<div style="font-size:11px; color:#f87171; margin-top:3px;">Limit reached — invite creation blocked</div>';
			}
			if (d.next_expiry) {
				statsHtml += '<div style="font-size:11px; opacity:.6; margin-top:3px;">Next expiry: ' + d.next_expiry + '</div>';
			}
			statsHtml += '</div>';
		}

		$.ajax({
			url:      'api/v2/plugins/wizarrinvite/current/' + slotId,
			method:   'GET',
			dataType: 'json',
			cache:    false,
		}).done(function (res) {
			if (!res || !res.response) {
				$r.html(statsHtml + '<span style="color:red;">Invalid response</span>');
				return;
			}
			if (res.response.result === 'success') {
				var d = res.response.data || {};
				$r.html(
					statsHtml +
					'<div style="color:lime; margin-bottom:4px; font-size:12px;">Slot #' + slotId + ' code ready</div>' +
					'<div style="font-size:12px;"><strong>Code:</strong> ' + (d.code || '-') + '</div>' +
					'<div style="font-size:12px;"><strong>URL:</strong> ' + (d.url  || '-') + '</div>'
				);
			} else {
				$r.html(statsHtml + '<span style="color:red;">' + (res.response.message || 'Error') + '</span>');
			}
		}).fail(function () {
			$r.html(statsHtml + '<span style="color:red;">Unable to check slot #' + slotId + '</span>');
		});

	}).fail(function () {
		$.ajax({
			url:      'api/v2/plugins/wizarrinvite/current/' + slotId,
			method:   'GET',
			dataType: 'json',
			cache:    false,
		}).done(function (res) {
			if (!res || !res.response) {
				$r.html('<span style="color:red;">Invalid response</span>');
				return;
			}
			if (res.response.result === 'success') {
				var d = res.response.data || {};
				$r.html(
					'<div style="color:lime; margin-bottom:4px;">Slot #' + slotId + ' code ready</div>' +
					'<div><strong>Code:</strong> ' + (d.code || '-') + '</div>' +
					'<div><strong>URL:</strong> ' + (d.url  || '-') + '</div>'
				);
			} else {
				$r.html('<span style="color:red;">' + (res.response.message || 'Error') + '</span>');
			}
		}).fail(function () {
			$r.html('<span style="color:red;">Unable to check slot #' + slotId + '</span>');
		});
	});

	return false;
});

// ── Auto-initialisation dès que ce script est chargé ─────────────────────────
(function () {
	if ($('#WIZARRINVITE-slots-config').length && !$('#wizarrinvite-slots-container').data('slots-initialized')) {
		wizarrinviteRenderAllSlots();
		$('#wizarrinvite-slots-container').data('slots-initialized', true);
	}
}());
