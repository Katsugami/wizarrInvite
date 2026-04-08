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
 * Parcourt tous les slots affichés, collecte leurs données et met à jour
 * le champ caché WIZARRINVITE-slots-config.
 * Valide les champs numériques avant l'écriture (max_users, server_count).
 */
function wizarrinviteSyncAllSlots() {
	var slots = [];
	$('.wizarr-slot-card').each(function () {
		var slotId = parseInt($(this).data('slot-id'), 10);
		if (!isNaN(slotId)) {
			var data = wizarrinviteCollectSlotData(slotId);

			// Validation : max_users et server_count doivent être des entiers positifs
			// En cas de valeur invalide on conserve la valeur par défaut pour éviter
			// de bloquer la sauvegarde ou de créer une config incohérente côté PHP.
			if (isNaN(parseInt(data.max_users, 10)) || parseInt(data.max_users, 10) < 1) {
				data.max_users = '100';
			}
			if (isNaN(parseInt(data.server_count, 10)) || parseInt(data.server_count, 10) < 1) {
				data.server_count = '1';
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

			// Options — toggles style Organizr
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
					'<span>Allow Mobile Uploads</span>' +
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
			var sd         = statsRes.response.data || {};
			var pct        = sd.max ? Math.min(100, Math.round(sd.count / sd.max * 100)) : 0;
			var limitColor = sd.limit_reached ? '#f87171' : pct >= 80 ? '#f59e0b' : '#22c55e';

			statsHtml += '<div style="background:rgba(255,255,255,.04); border-radius:8px; padding:6px 10px; margin-bottom:8px;">';
			statsHtml += '<div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">';
			statsHtml += '<span>👥 Utilisateurs (effectif)</span>';
			statsHtml += '<span style="color:' + limitColor + '; font-weight:700;">' + sd.count + ' / ' + sd.max + '</span>';
			statsHtml += '</div>';
			statsHtml += '<div style="height:5px; border-radius:999px; background:rgba(255,255,255,.1); overflow:hidden;">';
			statsHtml += '<div style="height:100%; width:' + pct + '%; background:' + limitColor + '; border-radius:999px;"></div>';
			statsHtml += '</div>';
			if (sd.server_count > 1) {
				statsHtml += '<div style="font-size:11px; opacity:.6; margin-top:3px;">total brut : ' + sd.raw_count + ' ÷ ' + sd.server_count + ' serveurs</div>';
			}
			if (sd.limit_reached) {
				statsHtml += '<div style="font-size:11px; color:#f87171; margin-top:3px;">⚠️ Limite atteinte — création d\'invitation bloquée</div>';
			}
			if (sd.next_expiry) {
				statsHtml += '<div style="font-size:11px; opacity:.6; margin-top:3px;">⏰ Prochaine expiration : ' + sd.next_expiry + '</div>';
			}
			statsHtml += '</div>';
		}

		// ── Bloc code d'invitation ────────────────────────────────────────────
		if (!currentRes || !currentRes.response) {
			$r.html(statsHtml + '<span style="color:red;">❌ Réponse invalide</span>');
			return;
		}
		if (currentRes.response.result === 'success') {
			var cd = currentRes.response.data || {};
			$r.html(
				statsHtml +
				'<div style="color:lime; margin-bottom:4px; font-size:12px;">✅ Slot #' + slotId + ' prêt</div>' +
				'<div style="font-size:12px;"><strong>Code :</strong> ' + (cd.code || '-') + '</div>' +
				'<div style="font-size:12px;"><strong>URL :</strong> '  + (cd.url  || '-') + '</div>'
			);
		} else {
			$r.html(statsHtml + '<span style="color:red;">❌ ' + (currentRes.response.message || 'Erreur') + '</span>');
		}

	}).fail(function () {
		// Au moins une des deux requêtes a échoué
		$r.html('<span style="color:red;">❌ Impossible de vérifier le slot #' + slotId + '</span>');
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
