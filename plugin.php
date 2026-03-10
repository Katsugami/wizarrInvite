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
		$manualServerIds = $this->config['WIZARRINVITE-manual-server-ids'] ?? '1,2';
		$manualLibraryIds = $this->config['WIZARRINVITE-manual-library-ids'] ?? '4,5,7,9,10';

		$autoServerIds = $this->config['WIZARRINVITE-auto-server-ids'] ?? '1,2';
		$autoLibraryIds = $this->config['WIZARRINVITE-auto-library-ids'] ?? '4,5,7,9,10';

		$minGroup = (string)($this->config['WIZARRINVITE-min-group'] ?? '5');

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
				]
			],

			'Manual Invitation' => [
				[
					'type' => 'html',
					'label' => 'Wizarr Expiration',
					'html' => '
						<select id="WIZARRINVITE-manual-expiration" name="WIZARRINVITE-manual-expiration" class="form-control" data-current="' . htmlspecialchars($this->config['WIZARRINVITE-manual-expiration'] ?? '1', ENT_QUOTES, 'UTF-8') . '">
							<option value="1">1 day</option>
							<option value="7">7 days</option>
							<option value="30">30 days</option>
							<option value="never">Never</option>
						</select>
					'
				],
				[
					'type' => 'text',
					'name' => 'WIZARRINVITE-manual-access-days',
					'label' => 'Access Duration (days)',
					'value' => $this->config['WIZARRINVITE-manual-access-days'] ?? '7'
				],
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
				[
					'type' => 'html',
					'label' => 'Servers and Libraries',
					'html' => '
						<input type="hidden" id="WIZARRINVITE-manual-server-ids" name="WIZARRINVITE-manual-server-ids" value="' . htmlspecialchars($manualServerIds, ENT_QUOTES, 'UTF-8') . '">
						<input type="hidden" id="WIZARRINVITE-manual-library-ids" name="WIZARRINVITE-manual-library-ids" value="' . htmlspecialchars($manualLibraryIds, ENT_QUOTES, 'UTF-8') . '">
						<button type="button" id="wizarrinvite-load-manual-selector-btn" class="btn btn-info">Load Servers and Libraries</button>
						<div id="wizarrinvite-manual-selector" style="margin-top:12px;"></div>
						<div style="margin-top:12px;">
							<div><strong>Selected Servers:</strong> <span id="wizarrinvite-manual-selected-servers">-</span></div>
							<div><strong>Selected Libraries:</strong> <span id="wizarrinvite-manual-selected-libraries">-</span></div>
						</div>
					'
				],
				[
					'type' => 'html',
					'label' => 'Action',
					'html' => '
						<button type="button" id="wizarrinvite-create-manual-btn" class="btn btn-success">Create Manual Invitation</button>
						<div id="wizarrinvite-manual-result" style="margin-top:10px;"></div>
					'
				]
			],

			'Automatic Invitation' => [
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-auto-enabled',
					'label' => 'Enable Automatic Mode',
					'value' => !empty($this->config['WIZARRINVITE-auto-enabled'])
				],
				[
					'type' => 'html',
					'label' => 'Wizarr Expiration',
					'html' => '
						<select id="WIZARRINVITE-auto-expiration" name="WIZARRINVITE-auto-expiration" class="form-control" data-current="' . htmlspecialchars($this->config['WIZARRINVITE-auto-expiration'] ?? '1', ENT_QUOTES, 'UTF-8') . '">
							<option value="1">1 day</option>
							<option value="7">7 days</option>
							<option value="30">30 days</option>
							<option value="never">Never</option>
						</select>
					'
				],
				[
					'type' => 'text',
					'name' => 'WIZARRINVITE-auto-access-days',
					'label' => 'Access Duration (days)',
					'value' => $this->config['WIZARRINVITE-auto-access-days'] ?? '7'
				],
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-auto-allow-downloads',
					'label' => 'Allow Downloads',
					'value' => !empty($this->config['WIZARRINVITE-auto-allow-downloads'])
				],
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-auto-allow-live-tv',
					'label' => 'Allow Live TV',
					'value' => !empty($this->config['WIZARRINVITE-auto-allow-live-tv'])
				],
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-auto-allow-mobile-uploads',
					'label' => 'Allow Mobile Uploads',
					'value' => !empty($this->config['WIZARRINVITE-auto-allow-mobile-uploads'])
				],
				[
					'type' => 'html',
					'label' => 'Servers and Libraries',
					'html' => '
						<input type="hidden" id="WIZARRINVITE-auto-server-ids" name="WIZARRINVITE-auto-server-ids" value="' . htmlspecialchars($autoServerIds, ENT_QUOTES, 'UTF-8') . '">
						<input type="hidden" id="WIZARRINVITE-auto-library-ids" name="WIZARRINVITE-auto-library-ids" value="' . htmlspecialchars($autoLibraryIds, ENT_QUOTES, 'UTF-8') . '">
						<button type="button" id="wizarrinvite-load-auto-selector-btn" class="btn btn-info">Load Servers and Libraries</button>
						<div id="wizarrinvite-auto-selector" style="margin-top:12px;"></div>
						<div style="margin-top:12px;">
							<div><strong>Selected Servers:</strong> <span id="wizarrinvite-auto-selected-servers">-</span></div>
							<div><strong>Selected Libraries:</strong> <span id="wizarrinvite-auto-selected-libraries">-</span></div>
						</div>
					'
				],
				[
					'type' => 'html',
					'label' => 'Action',
					'html' => '
						<button type="button" id="wizarrinvite-check-auto-btn" class="btn btn-success">Check / Recreate Automatic Code</button>
						<div id="wizarrinvite-auto-result" style="margin-top:10px;"></div>
					'
				]
			],

			'Organizr' => [
				[
					'type' => 'html',
					'label' => 'Minimum Organizr Group',
					'html' => '
						<select id="WIZARRINVITE-min-group" name="WIZARRINVITE-min-group" class="form-control" data-current="' . htmlspecialchars($minGroup, ENT_QUOTES, 'UTF-8') . '">
							<option value="1"' . ($minGroup === '1' ? ' selected' : '') . '>1 - Admin</option>
							<option value="2"' . ($minGroup === '2' ? ' selected' : '') . '>2 - Co-Admin</option>
							<option value="3"' . ($minGroup === '3' ? ' selected' : '') . '>3 - Super User</option>
							<option value="4"' . ($minGroup === '4' ? ' selected' : '') . '>4 - Power User</option>
							<option value="5"' . ($minGroup === '5' ? ' selected' : '') . '>5 - User</option>
							<option value="6"' . ($minGroup === '6' ? ' selected' : '') . '>6 - Guest</option>
						</select>
						<div style="margin-top:8px; opacity:.9; line-height:1.6;">
							Choose here the minimum authorized level required to display the plugin page.
						</div>
					'
				]
			],

			'Display Language' => [
				[
					'type' => 'text',
					'name' => 'WIZARRINVITE-display-custom-file',
					'label' => 'Display Used',
					'value' => $this->config['WIZARRINVITE-display-custom-file'] ?? 'display-fr',
					'placeholder' => 'display-fr'
				],
				[
					'type' => 'html',
					'label' => 'Information',
					'html' => '
						<div style="line-height:1.6; opacity:.95;">
							<p>Enter only the name of the display file to load.</p>
							<p>Examples: <code>display-fr</code>, <code>display-en</code>, <code>display-es</code>.</p>
							<p>The plugin will automatically add <code>.php</code> if needed.</p>
							<p>This field is only used to choose which display file will be loaded by the plugin\'s single URL.</p>
						</div>
					'
				]
			],

			'Info' => [
				[
					'type' => 'html',
					'label' => 'Usage',
					'html' => '
						<div style="line-height:1.7;">
							<p><strong>Important:</strong> the homepage URL must always be exactly:</p>
							<p><code>https://yourdomain.com/api/v2/plugins/wizarrinvite/display</code></p>

							<p>You must <strong>not</strong> use:</p>
							<p><code>https://yourdomain.com/api/v2/plugins/wizarrinvite/display-fr</code></p>
							<p><code>https://yourdomain.com/api/v2/plugins/wizarrinvite/display-en</code></p>
							<p><code>https://yourdomain.com/api/v2/plugins/wizarrinvite/display-es</code></p>

							<p>The displayed file is <strong>not</strong> selected in the URL.</p>
							<p>The displayed file is selected only in the <strong>Display Used</strong> field.</p>

							<p>Examples in <strong>Display Used</strong>:</p>
							<p><code>display-fr</code> loads <code>display-fr.php</code></p>
							<p><code>display-en</code> loads <code>display-en.php</code></p>
							<p><code>display-es</code> loads <code>display-es.php</code></p>

							<p>You therefore need to:</p>
							<p>1. set the file name in <strong>Display Used</strong></p>
							<p>2. then use only the single URL <code>/api/v2/plugins/wizarrinvite/display</code></p>

							<p>The automatic code is stored in <code>/config/www/organizr/data/cache/wizarrinvite_current.json</code>.</p>
						</div>
					'
				]
			]
		];
	}

}