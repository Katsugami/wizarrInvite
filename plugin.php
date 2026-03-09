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
	'image' => 'api/plugins/wizarrInvite/logo.png',
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
			'Connexion Wizarr' => [
				[
					'type' => 'text',
					'name' => 'WIZARRINVITE-url',
					'label' => 'URL interne Wizarr',
					'value' => $this->config['WIZARRINVITE-url'] ?? '',
					'placeholder' => 'http://wizarr:5690'
				],
				[
					'type' => 'password',
					'name' => 'WIZARRINVITE-api-key',
					'label' => 'Clé API Wizarr',
					'value' => $this->config['WIZARRINVITE-api-key'] ?? ''
				],
				[
					'type' => 'text',
					'name' => 'WIZARRINVITE-public-url',
					'label' => 'URL publique',
					'value' => $this->config['WIZARRINVITE-public-url'] ?? '',
					'placeholder' => 'https://invite.nomdedomaine.fr'
				],
				[
					'type' => 'html',
					'label' => 'Test connexion',
					'html' => '
						<button type="button" id="wizarrinvite-test-btn" class="btn btn-primary">Tester connexion Wizarr</button>
						<div id="wizarrinvite-test-result" style="margin-top:10px;"></div>
					'
				]
			],

			'Invitation manuelle' => [
				[
					'type' => 'html',
					'label' => 'Expiration Wizarr',
					'html' => '
						<select id="WIZARRINVITE-manual-expiration" name="WIZARRINVITE-manual-expiration" class="form-control" data-current="' . htmlspecialchars($this->config['WIZARRINVITE-manual-expiration'] ?? '1', ENT_QUOTES, 'UTF-8') . '">
							<option value="1">1 jour</option>
							<option value="7">7 jours</option>
							<option value="30">30 jours</option>
							<option value="never">Jamais</option>
						</select>
					'
				],
				[
					'type' => 'text',
					'name' => 'WIZARRINVITE-manual-access-days',
					'label' => 'Durée d’accès (jours)',
					'value' => $this->config['WIZARRINVITE-manual-access-days'] ?? '7'
				],
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-manual-allow-downloads',
					'label' => 'Autoriser les téléchargements',
					'value' => !empty($this->config['WIZARRINVITE-manual-allow-downloads'])
				],
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-manual-allow-live-tv',
					'label' => 'Autoriser la TV en direct',
					'value' => !empty($this->config['WIZARRINVITE-manual-allow-live-tv'])
				],
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-manual-allow-mobile-uploads',
					'label' => 'Autoriser les uploads mobiles',
					'value' => !empty($this->config['WIZARRINVITE-manual-allow-mobile-uploads'])
				],
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-manual-invite-to-plex-home',
					'label' => 'Inviter dans Plex Home',
					'value' => !empty($this->config['WIZARRINVITE-manual-invite-to-plex-home'])
				],
				[
					'type' => 'html',
					'label' => 'Serveurs et bibliothèques',
					'html' => '
						<input type="hidden" id="WIZARRINVITE-manual-server-ids" name="WIZARRINVITE-manual-server-ids" value="' . htmlspecialchars($manualServerIds, ENT_QUOTES, 'UTF-8') . '">
						<input type="hidden" id="WIZARRINVITE-manual-library-ids" name="WIZARRINVITE-manual-library-ids" value="' . htmlspecialchars($manualLibraryIds, ENT_QUOTES, 'UTF-8') . '">
						<button type="button" id="wizarrinvite-load-manual-selector-btn" class="btn btn-info">Charger les serveurs et bibliothèques</button>
						<div id="wizarrinvite-manual-selector" style="margin-top:12px;"></div>
						<div style="margin-top:12px;">
							<div><strong>Serveurs sélectionnés :</strong> <span id="wizarrinvite-manual-selected-servers">-</span></div>
							<div><strong>Bibliothèques sélectionnées :</strong> <span id="wizarrinvite-manual-selected-libraries">-</span></div>
						</div>
					'
				],
				[
					'type' => 'html',
					'label' => 'Action',
					'html' => '
						<button type="button" id="wizarrinvite-create-manual-btn" class="btn btn-success">Créer une invitation manuelle</button>
						<div id="wizarrinvite-manual-result" style="margin-top:10px;"></div>
					'
				]
			],

			'Invitation automatique' => [
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-auto-enabled',
					'label' => 'Activer le mode automatique',
					'value' => !empty($this->config['WIZARRINVITE-auto-enabled'])
				],
				[
					'type' => 'html',
					'label' => 'Expiration Wizarr',
					'html' => '
						<select id="WIZARRINVITE-auto-expiration" name="WIZARRINVITE-auto-expiration" class="form-control" data-current="' . htmlspecialchars($this->config['WIZARRINVITE-auto-expiration'] ?? '1', ENT_QUOTES, 'UTF-8') . '">
							<option value="1">1 jour</option>
							<option value="7">7 jours</option>
							<option value="30">30 jours</option>
							<option value="never">Jamais</option>
						</select>
					'
				],
				[
					'type' => 'text',
					'name' => 'WIZARRINVITE-auto-access-days',
					'label' => 'Durée d’accès (jours)',
					'value' => $this->config['WIZARRINVITE-auto-access-days'] ?? '7'
				],
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-auto-allow-downloads',
					'label' => 'Autoriser les téléchargements',
					'value' => !empty($this->config['WIZARRINVITE-auto-allow-downloads'])
				],
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-auto-allow-live-tv',
					'label' => 'Autoriser la TV en direct',
					'value' => !empty($this->config['WIZARRINVITE-auto-allow-live-tv'])
				],
				[
					'type' => 'checkbox',
					'name' => 'WIZARRINVITE-auto-allow-mobile-uploads',
					'label' => 'Autoriser les uploads mobiles',
					'value' => !empty($this->config['WIZARRINVITE-auto-allow-mobile-uploads'])
				],
				[
					'type' => 'html',
					'label' => 'Serveurs et bibliothèques',
					'html' => '
						<input type="hidden" id="WIZARRINVITE-auto-server-ids" name="WIZARRINVITE-auto-server-ids" value="' . htmlspecialchars($autoServerIds, ENT_QUOTES, 'UTF-8') . '">
						<input type="hidden" id="WIZARRINVITE-auto-library-ids" name="WIZARRINVITE-auto-library-ids" value="' . htmlspecialchars($autoLibraryIds, ENT_QUOTES, 'UTF-8') . '">
						<button type="button" id="wizarrinvite-load-auto-selector-btn" class="btn btn-info">Charger les serveurs et bibliothèques</button>
						<div id="wizarrinvite-auto-selector" style="margin-top:12px;"></div>
						<div style="margin-top:12px;">
							<div><strong>Serveurs sélectionnés :</strong> <span id="wizarrinvite-auto-selected-servers">-</span></div>
							<div><strong>Bibliothèques sélectionnées :</strong> <span id="wizarrinvite-auto-selected-libraries">-</span></div>
						</div>
					'
				],
				[
					'type' => 'html',
					'label' => 'Action',
					'html' => '
						<button type="button" id="wizarrinvite-check-auto-btn" class="btn btn-success">Vérifier / recréer le code automatique</button>
						<div id="wizarrinvite-auto-result" style="margin-top:10px;"></div>
					'
				]
			],

			'Organizr' => [
				[
					'type' => 'html',
					'label' => 'Groupe minimum Organizr',
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
							Choisis ici le niveau minimum autorisé pour afficher la page du plugin.
						</div>
					'
				]
			],

			'Langue du display' => [
				[
					'type' => 'text',
					'name' => 'WIZARRINVITE-display-custom-file',
					'label' => 'Display utilisé',
					'value' => $this->config['WIZARRINVITE-display-custom-file'] ?? 'display-fr',
					'placeholder' => 'display-fr'
				],
				[
					'type' => 'html',
					'label' => 'Information',
					'html' => '
						<div style="line-height:1.6; opacity:.95;">
							<p>Indique uniquement le nom du fichier display à charger.</p>
							<p>Exemples : <code>display-fr</code>, <code>display-en</code>, <code>display-es</code>.</p>
							<p>Le plugin ajoutera automatiquement <code>.php</code> si nécessaire.</p>
							<p>Ce champ sert uniquement à choisir quel fichier display sera chargé par l’URL unique du plugin.</p>
						</div>
					'
				]
			],

			'Infos' => [
				[
					'type' => 'html',
					'label' => 'Utilisation',
					'html' => '
						<div style="line-height:1.7;">
							<p><strong>Important :</strong> l’URL de la homepage doit toujours être exactement :</p>
							<p><code>https://nomdomaine.com/api/v2/plugins/wizarrinvite/display</code></p>

							<p>Il ne faut <strong>pas</strong> mettre :</p>
							<p><code>https://nomdomaine.com/api/v2/plugins/wizarrinvite/display-fr</code></p>
							<p><code>https://nomdomaine.com/api/v2/plugins/wizarrinvite/display-en</code></p>
							<p><code>https://nomdomaine.com/api/v2/plugins/wizarrinvite/display-es</code></p>

							<p>Le choix du fichier affiché ne se fait <strong>pas</strong> dans l’URL.</p>
							<p>Le choix du fichier affiché se fait uniquement dans le champ <strong>Display utilisé</strong>.</p>

							<p>Exemples dans <strong>Display utilisé</strong> :</p>
							<p><code>display-fr</code> charge <code>display-fr.php</code></p>
							<p><code>display-en</code> charge <code>display-en.php</code></p>
							<p><code>display-es</code> charge <code>display-es.php</code></p>

							<p>Tu dois donc :</p>
							<p>1. mettre le nom du fichier dans <strong>Display utilisé</strong></p>
							<p>2. utiliser ensuite seulement l’URL unique <code>/api/v2/plugins/wizarrinvite/display</code></p>

							<p>Le code automatique est mémorisé dans <code>/config/www/organizr/data/cache/wizarrinvite_current.json</code>.</p>
						</div>
					'
				]
			]
		];
	}
}