function wizarrInviteRender(data) {
	if (!$('#wizarrinvite-app').length) return;

	let html = '';
	html += '<div class="panel panel-info">';
	html += '<div class="panel-heading"><strong>Code automatique actif</strong></div>';
	html += '<div class="panel-body">';
	html += '<p><strong>Code :</strong> ' + (data.code || '-') + '</p>';
	html += '<p><strong>Lien :</strong> <a href="' + (data.url || '#') + '" target="_blank">' + (data.url || '-') + '</a></p>';
	html += '<p><strong>Expire :</strong> ' + (data.expires || '-') + '</p>';
	html += '<p><strong>Accès :</strong> ' + (data.access_days || 7) + ' jour(s)</p>';
	html += '</div>';
	html += '</div>';

	$('#wizarrinvite-app').html(html);
}

function wizarrInviteRenderError(message) {
	if (!$('#wizarrinvite-app').length) return;
	$('#wizarrinvite-app').html('<div class="alert alert-danger">' + message + '</div>');
}

function wizarrInviteLoadCurrent() {
	$.ajax({
		url: 'api/v2/plugins/wizarrinvite/current',
		method: 'GET',
		dataType: 'json',
		cache: false
	}).done(function (res) {
		if (!res || !res.response) {
			wizarrInviteRenderError('Réponse invalide');
			return;
		}
		if (res.response.result === 'error') {
			wizarrInviteRenderError(res.response.message || 'Erreur');
			return;
		}
		wizarrInviteRender(res.response.data || {});
	}).fail(function () {
		wizarrInviteRenderError('Impossible de charger le code');
	});
}

$('body').arrive('#wizarrinvite-app', { onceOnly: false }, function () {
	wizarrInviteLoadCurrent();
});