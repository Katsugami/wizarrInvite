<?php

$GLOBALS['plugins']['WizarrInvite'] = [
	'name' => 'Wizarr Invite',
	'author' => 'Katsugami',
	'category' => 'Management',
	'link' => '',
	'license' => 'personal',
	'idPrefix' => 'WIZARRINVITE',
	'configPrefix' => 'WIZARRINVITE',
	'version' => '2.0.0',
	'image' => file_exists(dirname(__DIR__, 3) . '/data/plugins/' . basename(__DIR__) . '/wizarr.png')
		? '/data/plugins/' . basename(__DIR__) . '/wizarr.png'
		: '/api/plugins/' . basename(__DIR__) . '/wizarr.png',
	'settings' => true,
	'bind' => true,
	'api' => 'api/v2/plugins/wizarrinvite/settings',
	'homepage' => false
];

class WizarrInvite extends Organizr
{
	public function wizarrInviteGetSettings()
	{
		$manualServerIds  = $this->config['WIZARRINVITE-manual-server-ids']  ?? '';
		$manualLibraryIds = $this->config['WIZARRINVITE-manual-library-ids'] ?? '';
		$manualBundleId   = htmlspecialchars($this->config['WIZARRINVITE-manual-bundle-id'] ?? '', ENT_QUOTES, 'UTF-8');

		// Compatibilité : lecture du nouvel emplacement, fallback sur l'ancien
		$minGroup = (string)($this->config['WIZARRINVITE-manual-min-group'] ?? $this->config['WIZARRINVITE-min-group'] ?? '2');

		// ── Données pour l'onglet User Count & Cache ──────────────────────────
		$cacheHours  = max(0, (int)($this->config['WIZARRINVITE-count-cache-hours']   ?? 24));
		$cacheMins   = max(0, (int)($this->config['WIZARRINVITE-count-cache-minutes'] ?? 0));

		return [
			'Wizarr Connection' => [
				[
					'type' => 'text',
					'name' => 'WIZARRINVITE-url',
					'label' => 'Internal Wizarr URL',
					'value' => $this->config['WIZARRINVITE-url'] ?? '',
					'placeholder' => 'http://127.0.0.1:5690'
				],
				[
					'type' => 'password',
					'name' => 'WIZARRINVITE-api-key',
					'label' => 'Wizarr API Key',
					'value' => $this->config['WIZARRINVITE-api-key'] ?? ''
				],
				[
					'type' => 'text',
					'name' => 'WIZARRINVITE-public-url',
					'label' => 'Public URL',
					'value' => $this->config['WIZARRINVITE-public-url'] ?? '',
					'placeholder' => 'https://invite.yourdomain.com'
				],
				[
					'type'        => 'text',
					'name'        => 'WIZARRINVITE-timezone',
					'label'       => 'Timezone',
					'value'       => $this->config['WIZARRINVITE-timezone'] ?? '',
					'placeholder' => 'Europe/Paris',
				],
				[
					'type' => 'html',
					'label' => 'Connection Test',
					'html' => '
						<button type="button" id="wizarrinvite-test-btn" class="btn btn-primary">Test Wizarr Connection</button>
						<div id="wizarrinvite-test-result" style="margin-top:10px;"></div>
					'
				],
			],

			'Manual Invitation' => [
				// ── Ligne 1 gauche : 3 paramètres d'accès groupés ────────────────
				[
					'type' => 'html',
					'label' => 'Access Settings',
					'html' => '
						<div style="display:flex; flex-direction:column; gap:10px; max-width:280px;">
							<div>
								<div style="font-size:12px; font-weight:600; margin-bottom:5px;">Minimum Organizr Group</div>
								<select id="WIZARRINVITE-manual-min-group" name="WIZARRINVITE-manual-min-group" class="form-control">
									<option value="1"' . ($minGroup === '1' ? ' selected' : '') . '>1 – Admin</option>
									<option value="2"' . ($minGroup === '2' ? ' selected' : '') . '>2 – Co-Admin</option>
									<option value="3"' . ($minGroup === '3' ? ' selected' : '') . '>3 – Super User</option>
									<option value="4"' . ($minGroup === '4' ? ' selected' : '') . '>4 – Power User</option>
									<option value="5"' . ($minGroup === '5' ? ' selected' : '') . '>5 – User</option>
									<option value="6"' . ($minGroup === '6' ? ' selected' : '') . '>6 – Guest</option>
								</select>
								<div style="font-size:11px; opacity:.6; margin-top:3px;">Minimum group required to create invitations.</div>
							</div>
							<div>
								<div style="font-size:12px; font-weight:600; margin-bottom:5px;">Wizarr Expiration</div>
								<select id="WIZARRINVITE-manual-expiration" name="WIZARRINVITE-manual-expiration" class="form-control"
										data-current="' . htmlspecialchars($this->config['WIZARRINVITE-manual-expiration'] ?? '1', ENT_QUOTES, 'UTF-8') . '">
									<option value="1">1 day</option>
									<option value="7">7 days</option>
									<option value="30">30 days</option>
									<option value="never">Never</option>
								</select>
							</div>
							<div>
								<div style="font-size:12px; font-weight:600; margin-bottom:5px;">Access Duration (days)</div>
								<input type="text" id="WIZARRINVITE-manual-access-days" name="WIZARRINVITE-manual-access-days"
									   class="form-control"
									   value="' . htmlspecialchars($this->config['WIZARRINVITE-manual-access-days'] ?? '7', ENT_QUOTES, 'UTF-8') . '">
							</div>
						</div>
					'
				],
				// ── Ligne 1 droite : Bundle ───────────────────────────────────────
				[
					'type' => 'html',
					'label' => 'Bundle',
					'html' => '
						<div style="max-width:280px;">
							<input type="text" id="WIZARRINVITE-manual-bundle-id" name="WIZARRINVITE-manual-bundle-id"
								   class="form-control" placeholder="Empty = default bundle"
								   value="' . $manualBundleId . '">
							<div style="margin-top:6px; font-size:11px; opacity:.6; line-height:1.5;">
								Leave empty to use the default bundle.<br>
								<strong>1</strong> = first bundle, <strong>2</strong> = second, etc.
							</div>
						</div>
					'
				],
				// ── Ligne 2 gauche : Sélecteur serveurs/bibliothèques ────────────
				// Placé avant les Permissions pour apparaître dans la colonne gauche.
				[
					'type' => 'html',
					'label' => 'Servers and Libraries',
					'html' => '
						<input type="hidden" id="WIZARRINVITE-manual-server-ids" name="WIZARRINVITE-manual-server-ids" value="' . htmlspecialchars($manualServerIds, ENT_QUOTES, 'UTF-8') . '">
						<input type="hidden" id="WIZARRINVITE-manual-library-ids" name="WIZARRINVITE-manual-library-ids" value="' . htmlspecialchars($manualLibraryIds, ENT_QUOTES, 'UTF-8') . '">
						<button type="button" id="wizarrinvite-load-manual-selector-btn" class="btn btn-info">Load Servers and Libraries</button>
						<div id="wizarrinvite-manual-selector" style="margin-top:12px;"></div>
						<div style="margin-top:8px; font-size:12px; opacity:.8;">
							<strong>Servers:</strong> <span id="wizarrinvite-manual-selected-servers">-</span><br>
							<strong>Libraries:</strong> <span id="wizarrinvite-manual-selected-libraries">-</span>
						</div>
					'
				],
				// ── Ligne 2 droite : Permissions + Action ────────────────────────
				// Pattern hidden+checkbox : le champ caché garantit une valeur vide
				// lorsque la case est décochée (un checkbox non coché n'est pas envoyé).
				[
					'type' => 'html',
					'label' => 'Permissions',
					'html' => '
						<div style="display:flex; flex-direction:column; gap:14px;">
							<div style="display:flex; flex-wrap:wrap; gap:18px;">
								<label class="wz-toggle">
									<input type="hidden"   name="WIZARRINVITE-manual-allow-downloads" value="">
									<input type="checkbox" name="WIZARRINVITE-manual-allow-downloads" value="1"'
										. (!empty($this->config['WIZARRINVITE-manual-allow-downloads']) ? ' checked' : '') . '>
									<span class="wz-toggle-track"></span>
									<span>Allow Downloads</span>
								</label>
								<label class="wz-toggle">
									<input type="hidden"   name="WIZARRINVITE-manual-allow-live-tv" value="">
									<input type="checkbox" name="WIZARRINVITE-manual-allow-live-tv" value="1"'
										. (!empty($this->config['WIZARRINVITE-manual-allow-live-tv']) ? ' checked' : '') . '>
									<span class="wz-toggle-track"></span>
									<span>Allow Live TV</span>
								</label>
								<label class="wz-toggle">
									<input type="hidden"   name="WIZARRINVITE-manual-allow-mobile-uploads" value="">
									<input type="checkbox" name="WIZARRINVITE-manual-allow-mobile-uploads" value="1"'
										. (!empty($this->config['WIZARRINVITE-manual-allow-mobile-uploads']) ? ' checked' : '') . '>
									<span class="wz-toggle-track"></span>
									<span>Allow Mobile Uploads <span style="opacity:.5; font-size:10px;">(Plex only)</span></span>
								</label>
								<label class="wz-toggle">
									<input type="hidden"   name="WIZARRINVITE-manual-invite-to-plex-home" value="">
									<input type="checkbox" name="WIZARRINVITE-manual-invite-to-plex-home" value="1"'
										. (!empty($this->config['WIZARRINVITE-manual-invite-to-plex-home']) ? ' checked' : '') . '>
									<span class="wz-toggle-track"></span>
									<span>Invite to Plex Home <span style="opacity:.5; font-size:10px;">(Plex only)</span></span>
								</label>
							</div>
							<div>
								<button type="button" id="wizarrinvite-create-manual-btn" class="btn btn-success">Create Manual Invitation</button>
								<div id="wizarrinvite-manual-result" style="margin-top:10px;"></div>
							</div>
						</div>
					'
				]
			],

			'Automatic Slots' => [
				[
					'type' => 'html',
					'label' => 'Slot Manager',
					'html' => '
						<div id="wizarrinvite-slots-fullwidth">
						<input type="hidden"
							id="WIZARRINVITE-slots-config"
							name="WIZARRINVITE-slots-config"
							value="' . htmlspecialchars($this->config['WIZARRINVITE-slots-config'] ?? '[]', ENT_QUOTES, 'UTF-8') . '">

						<div id="wizarrinvite-slots-container" style="margin-bottom:12px;"></div>

						<button type="button" id="wizarrinvite-add-slot-btn" class="btn btn-success">+ Add Slot</button>

						<div style="margin-top:10px; opacity:.8; font-size:13px; line-height:1.6;">
							Each slot has its own permanent invitation code.<br>
							Display URL pattern: <code>/api/v2/plugins/wizarrinvite/display/{id}</code>
						</div>
						</div>
					'
				]
			],


			'Display Language' => [
				[
					'type' => 'text',
					'name' => 'WIZARRINVITE-display-custom-file',
					'label' => 'Display Used',
					'value' => $this->config['WIZARRINVITE-display-custom-file'] ?? 'display/default/display-en',
					'placeholder' => 'display/default/display-en'
				],
				[
					'type' => 'html',
					'label' => 'Information',
					'html' => '
						<div style="line-height:1.6; opacity:.95;">
							<p>Enter the relative path (without <code>.php</code>) of the display file to load.</p>
							<p>📁 Built-in displays (in <code>display/default/</code>):</p>
							<ul style="margin:4px 0 8px 16px;">
								<li>🇫🇷 <code>display/default/display-fr</code> — French</li>
								<li>🇬🇧 <code>display/default/display-en</code> — English</li>
								<li>🇪🇸 <code>display/default/display-es</code> — Spanish</li>
							</ul>
							<p>🛠️ To use a custom display, place your file anywhere inside the plugin folder and enter its relative path here.</p>
						</div>
					'
				]
			],

			'User Check & Count Cache' => [

				// ── Col 1 : Live User Check ───────────────────────────────────
				[
					'type'  => 'html',
					'label' => 'Live User Check',
					'html'  => '
						<div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; opacity:.45; margin-bottom:8px;">Live User Check</div>
						<p style="font-size:12px; opacity:.7; margin-bottom:8px;">
							Queries Wizarr <code>/api/users</code> — returns the list of users Wizarr already manages.<br>
							The result is automatically cached to speed up slots (deferred mode).
						</p>
						<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:4px;">
							<button type="button" id="wizarrinvite-check-users-btn" class="btn btn-info">Check Users</button>
							<button type="button" id="wizarrinvite-show-cache-btn" class="btn btn-default" style="font-size:12px;">Show Cache</button>
							<button type="button" id="wizarrinvite-clear-users-cache-btn" class="btn btn-default" style="font-size:12px;">Clear users cache</button>
						</div>
						<div id="wizarrinvite-last-check-info" style="margin-top:4px; font-size:11px; opacity:.6; min-height:16px;"></div>
						<div id="wizarrinvite-users-result" style="margin-top:8px; font-size:13px; line-height:1.6;"></div>
					',
				],

				// ── Col 2 : Plex Home Users ───────────────────────────────────
				[
					'type'  => 'html',
					'label' => 'Plex Home Users',
					'html'  => '
						<details id="wizarrinvite-plex-home-details" style="background:rgba(255,255,255,.03); border-radius:8px; padding:10px 14px;">
							<summary style="font-size:12px; font-weight:700; cursor:pointer; user-select:none; list-style:none; display:flex; justify-content:space-between; align-items:center;">
								<span>Plex Home Users <span style="font-size:10px; opacity:.5; font-weight:400;">(manually declared — Plex only)</span></span>
								<span style="font-size:11px; opacity:.5;">▾</span>
							</summary>
							<div style="margin-top:10px; font-size:12px; opacity:.7; margin-bottom:8px;">
								Declare Plex Home / local accounts that Wizarr cannot see via API.<br>
								These are counted alongside API users — stored separately (not erased by "Clear users cache").
							</div>
							<div id="wizarrinvite-plex-home-list" style="display:flex; flex-direction:column; gap:6px; margin-bottom:8px;">
								<div style="opacity:.5; font-size:12px;">⏳ Loading…</div>
							</div>
							<button type="button" id="wizarrinvite-plex-home-add-btn" class="btn btn-success btn-sm" style="margin-right:6px;">+ Add User</button>
							<button type="button" id="wizarrinvite-plex-home-save-btn" class="btn btn-primary btn-sm">Save</button>
							<span id="wizarrinvite-plex-home-result" style="margin-left:8px; font-size:12px; opacity:.8;"></span>
						</details>
					',
				],

				// ── Col 1 : Deferred Count Cache description ──────────────────
				[
					'type'  => 'html',
					'label' => 'Cache Interval',
					'html'  => '
						<div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; opacity:.45; margin-bottom:8px;">Deferred Count Cache</div>
						<p style="font-size:12px; opacity:.7; margin-bottom:6px;">
							When <strong>Deferred Count Check</strong> is enabled on a slot, the user count is cached
							and only refreshed after this interval. Set both to <strong>0</strong> for the 1-minute minimum.
						</p>
						<p style="font-size:11px; color:#f87171; margin-bottom:0;">
							⚠️ Values under 2 minutes will slow down Organizr — each check queries the Wizarr API.
						</p>
					',
				],

				// ── Col 2 : Smart Check ───────────────────────────────────────
				[
					'type'  => 'html',
					'label' => 'Smart Check',
					'html'  => '
						<div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; opacity:.45; margin-bottom:8px;">Smart Check</div>
						<div>
							<!-- Hidden input ensures a value is always submitted even when unchecked -->
							<input type="hidden" id="WIZARRINVITE-smart-check-enabled" name="WIZARRINVITE-smart-check-enabled"
							       value="' . (empty($this->config['WIZARRINVITE-smart-check-enabled']) ? '0' : '1') . '">
							<label class="wz-toggle">
								<input type="checkbox" id="wizarrinvite-smart-check-toggle"
								       ' . (!empty($this->config['WIZARRINVITE-smart-check-enabled']) ? 'checked' : '') . '>
								<span class="wz-toggle-track"></span>
								<span>Enable Smart Check</span>
							</label>
							<div style="margin-top:5px; font-size:11px; opacity:.6;">
								When deferred mode is active, forces a live check if the cached count is within
								<em>threshold</em> spots of the limit — prevents creating an invite when the server is full.
							</div>
						</div>
					',
				],

				// ── Col 1 : Cache Hours ───────────────────────────────────────
				[
					'type'        => 'text',
					'name'        => 'WIZARRINVITE-count-cache-hours',
					'label'       => 'Cache Hours',
					'value'       => $this->config['WIZARRINVITE-count-cache-hours'] ?? '0',
					'placeholder' => '0',
				],

				// ── Col 2 : Smart Check Threshold ────────────────────────────
				[
					'type'        => 'text',
					'name'        => 'WIZARRINVITE-smart-check-threshold',
					'label'       => 'Smart Check Threshold',
					'value'       => $this->config['WIZARRINVITE-smart-check-threshold'] ?? '5',
					'placeholder' => '5',
				],

				// ── Col 1 : Cache Minutes ─────────────────────────────────────
				[
					'type'        => 'text',
					'name'        => 'WIZARRINVITE-count-cache-minutes',
					'label'       => 'Cache Minutes',
					'value'       => $this->config['WIZARRINVITE-count-cache-minutes'] ?? '15',
					'placeholder' => '15',
				],

				// ── Col 2 : Cache Status ──────────────────────────────────────
				[
					'type'  => 'html',
					'label' => 'Cache Status',
					'html'  => '
						<div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; opacity:.45; margin-bottom:8px;">Cache Status</div>
						<p style="font-size:11px; opacity:.7; margin-bottom:6px; display:flex; align-items:center; flex-wrap:wrap; gap:8px;">
							<span>🕐 <strong id="wizarrinvite-local-time">—</strong></span>
							<span style="opacity:.4;">|</span>
							<span>Interval: <strong id="wizarrinvite-cache-interval">' . $cacheHours . 'h ' . $cacheMins . 'min</strong></span>
							<span style="opacity:.4;">|</span>
							<label class="wz-toggle" style="vertical-align:middle; margin:0;">
								<input type="checkbox" id="wizarrinvite-autorefresh-cb">
								<span class="wz-toggle-track"></span>
								<span style="font-size:11px;">Auto-refresh</span>
							</label>
							<span id="wizarrinvite-cache-status-refresh" style="font-size:16px; opacity:.45; cursor:pointer; line-height:1;" title="Force refresh now">⟳</span>
						</p>
						<div id="wizarrinvite-last-user-check" style="font-size:11px; opacity:.6; margin-bottom:6px; min-height:15px;"></div>
						<div id="wizarrinvite-cache-status-table">
							<table style="width:100%; font-size:11px; border-collapse:collapse;">
								<thead>
									<tr style="opacity:.5; text-align:left;">
										<th style="padding:3px 6px;">Slot</th>
										<th style="padding:3px 6px;">Cached count</th>
									</tr>
								</thead>
								<tbody id="wizarrinvite-cache-status-tbody">
									<tr><td colspan="2" style="opacity:.5; padding:4px 6px;">Loading…</td></tr>
								</tbody>
							</table>
						</div>
						<div style="margin-top:8px; display:flex; gap:8px; align-items:center;">
							<button type="button" id="wizarrinvite-clear-count-cache-btn" class="btn btn-danger btn-sm">
								Clear Count Cache
							</button>
							<span id="wizarrinvite-clear-count-cache-result" style="font-size:12px; opacity:.8;"></span>
						</div>
					',
				],
			],

			'Info' => [
				[
					'type' => 'html',
					'label' => 'How to use slots',
					'html' => '
						<div style="line-height:1.8; font-size:13px;">
							<p>Each <strong>Automatic Slot</strong> has a unique ID (1, 2, 3…).<br>
							Each slot gets its own invitation page URL using this pattern:</p>

							<p style="font-size:14px; font-weight:600; background:rgba(255,255,255,.06); border-radius:6px; padding:8px 12px; margin:8px 0;">
								https://yourdomain.com/api/v2/plugins/wizarrinvite/display/<strong style="color:#a78bfa;">[ID]</strong>
							</p>

							<p>Replace <strong style="color:#a78bfa;">[ID]</strong> with the number shown on the slot card:</p>
							<ul style="margin:4px 0 8px 20px;">
								<li>Slot #1 &nbsp;→&nbsp; <code>/api/v2/plugins/wizarrinvite/display/1</code></li>
								<li>Slot #2 &nbsp;→&nbsp; <code>/api/v2/plugins/wizarrinvite/display/2</code></li>
								<li>Slot #3 &nbsp;→&nbsp; <code>/api/v2/plugins/wizarrinvite/display/3</code></li>
							</ul>

							<p style="opacity:.7; font-size:12px;">
								💡 This is the URL you put in Organizr as the homepage for that slot.<br>
								Each slot has a separate user limit, servers, and libraries.
							</p>
						</div>
					'
				],
				[
					'type' => 'html',
					'label' => 'Display language',
					'html' => '
						<div style="line-height:1.8; font-size:13px;">
							<p>The page shown to users is controlled by <strong>Display Language → Display Used</strong>.<br>
							This setting applies to <em>all</em> slots at once.</p>

							<p>Included languages:</p>
							<ul style="margin:4px 0 8px 20px;">
								<li><code>display/default/display-fr</code> — French 🇫🇷</li>
								<li><code>display/default/display-en</code> — English 🇬🇧</li>
								<li><code>display/default/display-es</code> — Spanish 🇪🇸</li>
							</ul>

							<p style="opacity:.7; font-size:12px;">
								You can create your own display file and put its relative path here.<br>
								The <code>.php</code> extension is added automatically.
							</p>
						</div>
					'
				],
				[
					'type' => 'html',
					'label' => 'Cache & logs',
					'html' => '
						<div style="line-height:1.8; font-size:13px;">
							<p>All files are saved in:</p>
							<p style="font-family:monospace; font-size:12px; background:rgba(255,255,255,.06); border-radius:6px; padding:6px 10px;">
								/config/www/organizr/data/cache/
							</p>
							<ul style="margin:4px 0 8px 20px; font-family:monospace; font-size:12px;">
								<li>wizarrinvite_slot_1.json &nbsp;— slot #1 invite code cache</li>
								<li>wizarrinvite_slot_2.json &nbsp;— slot #2 invite code cache</li>
								<li>wizarrinvite_debug.log &nbsp;&nbsp;— plugin activity log</li>
							</ul>
							<p style="opacity:.7; font-size:12px;">
								To force a new invite code for a slot, delete its <code>.json</code> cache file<br>
								or click <strong>Check / Recreate</strong> on the slot card.
							</p>
						</div>
					'
				]
			],

			'Debug' => [
				[
					'type' => 'html',
					'label' => 'Plugin Logs',
					'html' => '
						<div style="display:flex; gap:8px; margin-bottom:8px; align-items:center; flex-wrap:wrap;">
							<button type="button" id="wizarrinvite-debug-refresh-btn" class="btn btn-info btn-sm">Refresh</button>
							<button type="button" id="wizarrinvite-debug-clear-btn" class="btn btn-danger btn-sm">Clear Logs</button>
							<span id="wizarrinvite-debug-status" style="font-size:12px; opacity:.7;"></span>
						</div>
						<div style="font-size:11px; opacity:.5; margin-bottom:6px;">
							Deferred count live checks appear as: <code>Deferred count live check — slot #N (reason): X users</code>
						</div>
						<div id="wizarrinvite-debug-log" style="
							font-family:monospace; font-size:11px; line-height:1.6;
							background:rgba(0,0,0,.4); border-radius:6px; padding:10px;
							height:280px; overflow-y:auto; white-space:pre-wrap; color:#e2e8f0;
							text-align:left; display:block;">📋 Click Refresh to load logs.</div>
					'
				]
			],

			'API Tester' => [
				[
					'type' => 'html',
					'label' => 'Wizarr API Docs',
					'html' => '
						<div style="line-height:1.8; font-size:13px;">
							<p>Wizarr includes an interactive API documentation page (Swagger / OpenAPI).<br>
							Use it to browse all available endpoints and send test requests directly.</p>

							<div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;">
								' . (($this->config['WIZARRINVITE-url'] ?? '') !== '' ? '
								<a href="' . rtrim($this->config['WIZARRINVITE-url'], '/') . '/api/docs/"
								   target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">
									Internal (LAN)
								</a>
								' : '') . '
								' . (!empty($this->config['WIZARRINVITE-public-url']) ? '
								<a href="' . rtrim($this->config['WIZARRINVITE-public-url'], '/') . '/api/docs/"
								   target="_blank" rel="noopener noreferrer" class="btn btn-info btn-sm">
									External (WAN)
								</a>
								' : '') . '
							</div>

							<p style="opacity:.7; font-size:12px; margin-top:12px;">
								The API key configured above is required to authenticate on that page.<br>
								All Wizarr endpoints (users, invitations, bundles, servers…) are documented there.
							</p>
						</div>
					'
				]
			]
		];
	}

}