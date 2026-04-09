// ══════════════════════════════════════════════════════════════════════════════
// Gestionnaire de slots automatiques — chargé dynamiquement par settings.js
// Chaque slot a ses propres paramètres et son propre code d'invitation permanent.
// La configuration de tous les slots est sérialisée en JSON dans un seul champ
// caché : #WIZARRINVITE-slots-config
// ══════════════════════════════════════════════════════════════════════════════

// Note : les styles CSS des toggles (.wz-toggle / .wz-toggle-track) sont injectés
// par settings.js au démarrage. Ce fichier réutilise ces classes partagées.

/**
 * Lit la config des slots depuis le champ caché Organizr.
 */
function wizarrinviteGetSlotsConfig() {
	var raw = $('#WIZARRINVITE-slots-config').val() || '[]';
	try { return JSON.parse(raw); } catch (e) { return []; }
}

/**
 * Sérialise les slots dans le champ caché pour qu'Organizr les sauvegarde.
 * Le .trigger('change') déclenche l'événement natif qu'Organizr écoute
 * pour afficher le bouton Save — sans ça, les clics Add/Remove passaient
 * inaperçus car .val() ne génère pas d'événement change automatiquement.
 */
function wizarrinviteSaveSlotsConfig(slots) {
	$('#WIZARRINVITE-slots-config').val(JSON.stringify(slots)).trigger('change');
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
 *
 * Note sur les préfixes d'ID :
 *  - "wizarr-slot-{id}-*"      → champs visibles du formulaire (label, select, input, checkbox)
 *  - "WIZARRINVITE-slot-{id}-*" → champs cachés gérés par Organizr (server-ids, library-ids)
 *    Le préfixe majuscule est nécessaire pour que la valeur soit incluse dans la sauvegarde Organizr.
 */
function wizarrinviteCollectSlotData(slotId) {
	return {
		id:                   slotId,
		label:                $('#wizarr-slot-' + slotId + '-label').val()            || ('Slot ' + slotId),
		min_group:            $('#wizarr-slot-' + slotId + '-min-group').val()         || '5',
		expiration:           $('#wizarr-slot-' + slotId + '-expiration').val()        || '1',
		access_days:          $('#wizarr-slot-' + slotId + '-access-days').val()       || '7',
		max_users:            $('#wizarr-slot-' + slotId + '-max-users').val(),
		bundle_id:            $('#wizarr-slot-' + slotId + '-bundle-id').val()            || '',
		server_ids:           $('#WIZARRINVITE-slot-' + slotId + '-server-ids').val()  || '',
		library_ids:          $('#WIZARRINVITE-slot-' + slotId + '-library-ids').val() || '',
		allow_downloads:      $('#wizarr-slot-' + slotId + '-allow-downloads').is(':checked'),
		allow_live_tv:        $('#wizarr-slot-' + slotId + '-allow-live-tv').is(':checked'),
		allow_mobile_uploads: $('#wizarr-slot-' + slotId + '-allow-mobile-uploads').is(':checked'),
		show_user_count:      $('#wizarr-slot-' + slotId + '-show-user-count').is(':checked'),
		deferred_count:       $('#wizarr-slot-' + slotId + '-deferred-count').is(':checked'),
		common_servers:       $('#wizarr-slot-' + slotId + '-common-servers').is(':checked'),
	};
}

/**
 * Parcourt tous les slots affichés, collecte leurs données et met à jour
 * le champ caché WIZARRINVITE-slots-config.
 * Valide les champs numériques avant l'écriture (max_users).
 */
function wizarrinviteSyncAllSlots() {
	var slots = [];
	$('.wizarr-slot-card').each(function () {
		var slotId = parseInt($(this).data('slot-id'), 10);
		if (!isNaN(slotId)) {
			var data = wizarrinviteCollectSlotData(slotId);

			// max_users : chaîne vide = infini (pas de limite).
			// On n'accepte que les entiers positifs ou la chaîne vide.
			var parsedMax = parseInt(data.max_users, 10);
			if (data.max_users !== '' && (isNaN(parsedMax) || parsedMax < 1)) {
				data.max_users = '';
			}

			slots.push(data);
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

	// max_users vide = infini (∞) — pas de limite d'utilisateurs
	var maxUsers      = (slot.max_users !== undefined && slot.max_users !== null) ? String(slot.max_users) : '';
	var bundleId      = String(slot.bundle_id    || '');
	// show_user_count absent sur les anciens slots → true (rétrocompatibilité)
	var showUserCount  = (slot.show_user_count  !== undefined) ? !!slot.show_user_count  : true;
	// deferred_count absent sur les anciens slots → false
	var deferredCount  = (slot.deferred_count   !== undefined) ? !!slot.deferred_count   : false;
	// common_servers absent sur les anciens slots → false
	var commonServers  = (slot.common_servers   !== undefined) ? !!slot.common_servers   : false;

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

	// URL relative : s'adapte automatiquement à l'adresse d'Organizr (LAN ou WAN)
	var displayPath = '/api/v2/plugins/wizarrinvite/display/' + id;

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

			// Max users
			'<div style="margin-bottom:10px;">' +
				'<label style="display:block; margin-bottom:4px;">Max Users</label>' +
				'<input type="text" id="wizarr-slot-' + id + '-max-users" ' +
					'class="form-control wizarr-slot-field" data-slot-id="' + id + '" ' +
					'style="max-width:180px;" ' +
					'value="' + maxUsers + '" placeholder="∞ = no limit">' +
				'<div style="margin-top:3px; font-size:11px; opacity:.6;">Empty = no limit (∞). Requires Show User Count. User count is deduplicated across servers automatically.</div>' +
			'</div>' +

			// Show User Count (standalone row, above Bundle)
			'<div style="margin-bottom:10px;">' +
				'<label class="wz-toggle">' +
					'<input type="checkbox" id="wizarr-slot-' + id + '-show-user-count" ' +
						'class="wizarr-slot-field" data-slot-id="' + id + '" ' +
						(showUserCount ? 'checked' : '') + '>' +
					'<span class="wz-toggle-track"></span>' +
					'<span>Show User Count</span>' +
				'</label>' +
				'<div style="margin-top:3px; font-size:11px; opacity:.6;">Display user count on the invite page and enforce the max limit.</div>' +
			'</div>' +

			// Deferred Count Check (standalone row, below Show User Count)
			'<div style="margin-bottom:10px;">' +
				'<label class="wz-toggle">' +
					'<input type="checkbox" id="wizarr-slot-' + id + '-deferred-count" ' +
						'class="wizarr-slot-field" data-slot-id="' + id + '" ' +
						(deferredCount ? 'checked' : '') + '>' +
					'<span class="wz-toggle-track"></span>' +
					'<span>Deferred Count Check</span>' +
				'</label>' +
				'<div style="margin-top:3px; font-size:11px; opacity:.6;">Use cached user count (interval set in Count Cache tab). Fast invite creation.</div>' +
			'</div>' +

			// Common Servers (standalone row, below Deferred Count)
			'<div style="margin-bottom:10px;">' +
				'<label class="wz-toggle">' +
					'<input type="checkbox" id="wizarr-slot-' + id + '-common-servers" ' +
						'class="wizarr-slot-field" data-slot-id="' + id + '" ' +
						(commonServers ? 'checked' : '') + '>' +
					'<span class="wz-toggle-track"></span>' +
					'<span>Common Servers</span>' +
				'</label>' +
				'<div style="margin-top:3px; font-size:11px; opacity:.6;">Selected servers share the same Plex account (same users). Deduplication is applied across servers. If off, each server is counted independently and the highest count is used.</div>' +
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

			// Options — toggles
			'<div style="display:flex; gap:18px; margin-bottom:12px; flex-wrap:wrap;">' +
				'<label class="wz-toggle">' +
					'<input type="checkbox" id="wizarr-slot-' + id + '-allow-downloads" ' +
						'class="wizarr-slot-field" data-slot-id="' + id + '" ' +
						(slot.allow_downloads ? 'checked' : '') + '>' +
					'<span class="wz-toggle-track"></span>' +
					'<span>Allow Downloads</span>' +
				'</label>' +
				'<label class="wz-toggle">' +
					'<input type="checkbox" id="wizarr-slot-' + id + '-allow-live-tv" ' +
						'class="wizarr-slot-field" data-slot-id="' + id + '" ' +
						(slot.allow_live_tv ? 'checked' : '') + '>' +
					'<span class="wz-toggle-track"></span>' +
					'<span>Allow Live TV</span>' +
				'</label>' +
				'<label class="wz-toggle">' +
					'<input type="checkbox" id="wizarr-slot-' + id + '-allow-mobile-uploads" ' +
						'class="wizarr-slot-field" data-slot-id="' + id + '" ' +
						(slot.allow_mobile_uploads ? 'checked' : '') + '>' +
					'<span class="wz-toggle-track"></span>' +
					'<span>Allow Mobile Uploads <span style="font-size:10px; opacity:.55;">(Plex only)</span></span>' +
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

			// Check / Recreate + accès direct à la page display.
			// URL relative : le navigateur complète avec l'adresse courante d'Organizr,
			// ce qui fonctionne automatiquement en LAN comme en WAN.
			'<div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">' +
				'<button type="button" class="btn btn-success wizarr-check-slot-btn" ' +
					'data-slot-id="' + id + '" style="font-size:12px; padding:5px 10px;">' +
					'Check / Recreate' +
				'</button>' +
				'<a href="' + displayPath + '" target="_blank" class="btn btn-default" ' +
					'style="font-size:12px; padding:5px 10px;">🖥️ Open Display</a>' +
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

	container.css({ display: 'block', 'grid-template-columns': '', gap: '' });

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
		max_users:            '',       // vide = infini (∞)
		bundle_id:            '',
		server_ids:           '',
		library_ids:          '',
		show_user_count:      true,
		deferred_count:       false,
		common_servers:       false,
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

// ── Extension de wizarrinviteSyncSelection pour les slots ────────────────────
// wizarrinviteSyncSelection (définie dans settings.js) synchronise les champs
// cachés server-ids / library-ids après chaque changement de case à cocher.
// On surcharge ici la fonction pour déclencher aussi wizarrinviteSyncAllSlots()
// quand le préfixe correspond à un slot (ex : 'slot-3').
//
// EXCEPTION : si _wizarrinviteRenderingSelector est vrai, l'appel vient du rendu
// initial du sélecteur (bouton "Load Servers"), pas d'un vrai changement utilisateur.
// Dans ce cas on NE déclenche PAS le bouton Save d'Organizr.
var _origSyncSelection = wizarrinviteSyncSelection;
wizarrinviteSyncSelection = function (prefix) {
	_origSyncSelection(prefix);
	if (prefix.indexOf('slot-') === 0 && !_wizarrinviteRenderingSelector) {
		wizarrinviteSyncAllSlots();
	}
};

// ── Check / Recreate un slot ──────────────────────────────────────────────────
// Les deux appels API (statistiques utilisateurs + code d'invitation) sont lancés
// en parallèle via $.when() pour réduire le temps d'attente total.
$(document).off('click.wizarrinvite', '.wizarr-check-slot-btn');
$(document).on('click.wizarrinvite', '.wizarr-check-slot-btn', function (e) {
	e.preventDefault();
	// Pas de wizarrinviteSyncAllSlots() ici : la config est déjà à jour grâce
	// aux handlers sur .wizarr-slot-field. L'appeler ici déclencherait le
	// bouton Save d'Organizr sans qu'aucune modification réelle n'ait été faite.

	var slotId = parseInt($(this).data('slot-id'), 10);
	var $r     = $('#wizarr-slot-' + slotId + '-result');
	$r.html('⏳ Checking slot #' + slotId + '...');

	// Lancement parallèle des deux requêtes
	$.when(
		$.ajax({ url: 'api/v2/plugins/wizarrinvite/slot-user-stats/' + slotId, method: 'GET', dataType: 'json', cache: false }),
		$.ajax({ url: 'api/v2/plugins/wizarrinvite/current/'           + slotId, method: 'GET', dataType: 'json', cache: false })
	).done(function (statsResult, currentResult) {
		var statsRes   = statsResult[0];
		var currentRes = currentResult[0];
		var statsHtml  = '';

		// ── Bloc statistiques utilisateurs ───────────────────────────────────
		if (statsRes && statsRes.response && statsRes.response.result === 'success') {
			var sd             = statsRes.response.data || {};
			var perSrv         = sd.per_server_counts  || {};
			var srvNames       = sd.server_names       || [];
			var commonSrv      = !!sd.common_servers;
			var perSrvKeys     = Object.keys(perSrv);
			var limitColor     = sd.limit_reached ? '#f87171' : '#22c55e';

			statsHtml += '<div style="background:rgba(255,255,255,.04); border-radius:8px; padding:6px 10px; margin-bottom:8px;">';

			if (perSrvKeys.length > 0) {
				if (commonSrv) {
					// Serveurs partagés → afficher "SRV1, SRV2 : X / max"
					var pct = sd.max ? Math.min(100, Math.round(sd.count / sd.max * 100)) : 0;
					limitColor = sd.limit_reached ? '#f87171' : pct >= 80 ? '#f59e0b' : '#22c55e';
					statsHtml += '<div style="font-size:11px; opacity:.6; margin-bottom:4px;">' + srvNames.join(', ') + ' (shared)</div>';
					statsHtml += '<div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">';
					statsHtml += '<span>👥 Members</span>';
					statsHtml += '<span style="color:' + limitColor + '; font-weight:700;">' + sd.count + (sd.max ? ' / ' + sd.max : '') + '</span>';
					statsHtml += '</div>';
					if (sd.max) {
						statsHtml += '<div style="height:5px; border-radius:999px; background:rgba(255,255,255,.1); overflow:hidden; margin-bottom:4px;">';
						statsHtml += '<div style="height:100%; width:' + pct + '%; background:' + limitColor + '; border-radius:999px;"></div>';
						statsHtml += '</div>';
					}
					// Per-server breakdown (small text)
					perSrvKeys.forEach(function(name) {
						statsHtml += '<div style="font-size:11px; opacity:.6;">' + name + ': ' + perSrv[name] + ' records</div>';
					});
				} else {
					// Serveurs indépendants → afficher chaque serveur séparément
					statsHtml += '<div style="font-size:12px; font-weight:600; margin-bottom:6px;">👥 Members per server</div>';
					perSrvKeys.forEach(function(name) {
						var c    = perSrv[name];
						var pct2 = sd.max ? Math.min(100, Math.round(c / sd.max * 100)) : 0;
						var col2 = (sd.max && c >= sd.max) ? '#f87171' : (pct2 >= 80 ? '#f59e0b' : '#22c55e');
						statsHtml += '<div style="margin-bottom:5px;">';
						statsHtml += '<div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:2px;">';
						statsHtml += '<span>' + name + '</span>';
						statsHtml += '<span style="color:' + col2 + '; font-weight:700;">' + c + (sd.max ? ' / ' + sd.max : '') + '</span>';
						statsHtml += '</div>';
						if (sd.max) {
							statsHtml += '<div style="height:4px; border-radius:999px; background:rgba(255,255,255,.1); overflow:hidden;">';
							statsHtml += '<div style="height:100%; width:' + pct2 + '%; background:' + col2 + '; border-radius:999px;"></div>';
							statsHtml += '</div>';
						}
						statsHtml += '</div>';
					});
				}
			} else {
				// Pas de filtre serveur — afficher le total
				var pct0 = sd.max ? Math.min(100, Math.round(sd.count / sd.max * 100)) : 0;
				limitColor = sd.limit_reached ? '#f87171' : pct0 >= 80 ? '#f59e0b' : '#22c55e';
				statsHtml += '<div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">';
				statsHtml += '<span>👥 Users (deduplicated)</span>';
				statsHtml += '<span style="color:' + limitColor + '; font-weight:700;">' + sd.count + (sd.max ? ' / ' + sd.max : '') + '</span>';
				statsHtml += '</div>';
				if (sd.max) {
					statsHtml += '<div style="height:5px; border-radius:999px; background:rgba(255,255,255,.1); overflow:hidden; margin-bottom:4px;">';
					statsHtml += '<div style="height:100%; width:' + pct0 + '%; background:' + limitColor + '; border-radius:999px;"></div>';
					statsHtml += '</div>';
				}
			}

			if (sd.limit_reached) {
				statsHtml += '<div style="font-size:11px; color:#f87171; margin-top:4px;">⚠️ User limit reached — invite creation blocked</div>';
			}
			if (sd.next_expiry) {
				statsHtml += '<div style="font-size:11px; opacity:.6; margin-top:3px;">⏰ Next expiry: ' + sd.next_expiry + '</div>';
			}
			if (sd.deferred) {
				statsHtml += '<div style="font-size:11px; opacity:.5; margin-top:3px;">📦 Deferred (cached' + (sd.cache_age_s != null ? ', ' + Math.round(sd.cache_age_s / 60) + ' min ago' : '') + ')</div>';
			}
			statsHtml += '</div>';
		}

		// ── Bloc code d'invitation ────────────────────────────────────────────
		if (!currentRes || !currentRes.response) {
			$r.html(statsHtml + '<span style="color:red;">❌ Invalid response</span>');
			return;
		}
		if (currentRes.response.result === 'success') {
			var cd = currentRes.response.data || {};
			$r.html(
				statsHtml +
				'<div style="color:lime; margin-bottom:4px; font-size:12px;">✅ Slot #' + slotId + ' ready</div>' +
				'<div style="font-size:12px;"><strong>Code:</strong> ' + (cd.code || '-') + '</div>' +
				'<div style="font-size:12px;"><strong>URL:</strong> '  + (cd.url  || '-') + '</div>'
			);
		} else {
			$r.html(statsHtml + '<span style="color:red;">❌ ' + (currentRes.response.message || 'Error') + '</span>');
		}

	}).fail(function () {
		// Au moins une des deux requêtes a échoué
		$r.html('<span style="color:red;">❌ Unable to check slot #' + slotId + '</span>');
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
