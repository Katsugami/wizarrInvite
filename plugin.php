<?php

$GLOBALS['plugins']['WizarrInvite'] = [
	'name' => 'Wizarr Invite',
	'author' => 'Katsugami',
	'category' => 'Management',
	'link' => '',
	'license' => 'personal',
	'idPrefix' => 'WIZARRINVITE',
	'configPrefix' => 'WIZARRINVITE',
	'version' => '1.0.0',
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
					'type' => 'html',
					'label' => 'Connection Test',
					'html' => '
						<button type="button" id="wizarrinvite-test-btn" class="btn btn-primary">Test Wizarr Connection</button>
						<div id="wizarrinvite-test-result" style="margin-top:10px;"></div>
					'
				],
				[
					'type' => 'html',
					'label' => 'Users Check',
					'html' => '
						<button type="button" id="wizarrinvite-check-users-btn" class="btn btn-info">Check Users</button>
						<div id="wizarrinvite-users-result" style="margin-top:10px; font-size:13px; line-height:1.6;"></div>
					'
				]
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
				// ── Ligne 2 : Permissions (toggles natifs, 2 par ligne) ───────────
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-manual-allow-downloads',
					'label' => 'Allow Downloads',
					'value' => !empty($this->config['WIZARRINVITE-manual-allow-downloads'])
				],
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-manual-allow-live-tv',
					'label' => 'Allow Live TV',
					'value' => !empty($this->config['WIZARRINVITE-manual-allow-live-tv'])
				],
				// ── Ligne 3 : Suite des permissions ──────────────────────────────
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-manual-allow-mobile-uploads',
					'label' => 'Allow Mobile Uploads',
					'value' => !empty($this->config['WIZARRINVITE-manual-allow-mobile-uploads'])
				],
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-manual-invite-to-plex-home',
					'label' => 'Invite to Plex Home',
					'value' => !empty($this->config['WIZARRINVITE-manual-invite-to-plex-home'])
				],
				// ── Ligne 4 gauche : Sélecteur serveurs/bibliothèques ─────────────
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
				// ── Ligne 4 droite : Action ───────────────────────────────────────
				[
					'type' => 'html',
					'label' => 'Action',
					'html' => '
						<button type="button" id="wizarrinvite-create-manual-btn" class="btn btn-success">Create Manual Invitation</button>
						<div id="wizarrinvite-manual-result" style="margin-top:10px;"></div>
					'
				]
			],

			'Automatic Slots' => [
				[
					'type' => 'html',
					'label' => 'Slot Manager',
					'html' => '
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

							<div style="display:flex; flex-direction:column; gap:10px; margin-top:10px;">
								<div>
									<div style="font-size:12px; font-weight:600; margin-bottom:5px;">Internal access (LAN)</div>
									<a href="' . rtrim($this->config['WIZARRINVITE-url'] ?? '', '/') . '/api/docs/"
									   target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">
										Open Wizarr API Docs (internal)
									</a>
									<div style="margin-top:4px; font-family:monospace; font-size:11px; opacity:.6;">
										' . rtrim($this->config['WIZARRINVITE-url'] ?? '', '/') . '/api/docs/
									</div>
								</div>
								' . (!empty($this->config['WIZARRINVITE-public-url']) ? '
								<div>
									<div style="font-size:12px; font-weight:600; margin-bottom:5px;">External access (domain)</div>
									<a href="' . rtrim($this->config['WIZARRINVITE-public-url'], '/') . '/api/docs/"
									   target="_blank" rel="noopener noreferrer" class="btn btn-info btn-sm">
										Open Wizarr API Docs (external)
									</a>
									<div style="margin-top:4px; font-family:monospace; font-size:11px; opacity:.6;">
										' . rtrim($this->config['WIZARRINVITE-public-url'], '/') . '/api/docs/
									</div>
								</div>
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