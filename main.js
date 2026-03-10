function wizarrInviteRender(data) {
	if (!$('#wizarrinvite-app').length) return;

	let html = '';
	html += '<div class="panel panel-info">';
	html += '<div class="panel-heading"><strong>Automatic code active</strong></div>';
	html += '<div class="panel-body">';
	html += '<p><strong>Code:</strong> ' + (data.code || '-') + '</p>';
	html += '<p><strong>Link:</strong> <a href="' + (data.url || '#') + '" target="_blank">' + (data.url || '-') + '</a></p>';
	html += '<p><strong>Expires:</strong> ' + (data.expires || '-') + '</p>';
	html += '<p><strong>Access:</strong> ' + (data.access_days || 7) + ' day(s)</p>';
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
			wizarrInviteRenderError('Invalid response');
			return;
		}
		if (res.response.result === 'error') {
			wizarrInviteRenderError(res.response.message || 'Error');
			return;
		}
		wizarrInviteRender(res.response.data || {});
	}).fail(function () {
		wizarrInviteRenderError('Unable to load the code');
	});
}

$('body').arrive('#wizarrinvite-app', { onceOnly: false }, function () {
	wizarrInviteLoadCurrent();
});