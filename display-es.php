<?php
echo '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invitación Wizarr</title>
<style>
:root{
	--bg-1:#08111f;
	--bg-2:#0f172a;
	--card:#111c31cc;
	--card-border:rgba(255,255,255,.10);
	--text:#f8fafc;
	--muted:#94a3b8;
	--accent:#7c3aed;
	--accent-hover:#8b5cf6;
	--success:#22c55e;
	--success-bg:rgba(34,197,94,.10);
	--success-border:rgba(34,197,94,.22);
	--warning:#f59e0b;
	--warning-bg:rgba(245,158,11,.10);
	--warning-border:rgba(245,158,11,.24);
	--danger:#f87171;
	--danger-bg:rgba(248,113,113,.10);
	--danger-border:rgba(248,113,113,.22);
	--link:#93c5fd;
	--shadow:0 25px 60px rgba(0,0,0,.35);
}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{
	min-height:100vh;
	font-family:Arial,sans-serif;
	color:var(--text);
	background:
		radial-gradient(circle at top left, rgba(124,58,237,.18), transparent 30%),
		radial-gradient(circle at bottom right, rgba(34,197,94,.12), transparent 25%),
		linear-gradient(135deg,var(--bg-1) 0%,var(--bg-2) 100%);
	display:flex;
	align-items:center;
	justify-content:center;
	padding:16px;
}
.wrap{
	width:100%;
	max-width:1060px;
}
.card{
	display:grid;
	grid-template-columns:340px 1fr;
	gap:22px;
	background:var(--card);
	border:1px solid var(--card-border);
	border-radius:28px;
	padding:22px;
	backdrop-filter:blur(12px);
	box-shadow:var(--shadow);
	overflow:hidden;
}
.left{
	border-radius:22px;
	background:
		linear-gradient(180deg, rgba(124,58,237,.20), rgba(124,58,237,.08)),
		rgba(255,255,255,.03);
	border:1px solid rgba(255,255,255,.08);
	padding:24px 22px;
	display:flex;
	flex-direction:column;
	align-items:center;
	justify-content:center;
	min-height:100%;
	text-align:center;
}
.left-inner{
	width:100%;
	display:flex;
	flex-direction:column;
	align-items:center;
	justify-content:center;
	gap:16px;
}
.logo-box{
	display:flex;
	align-items:center;
	justify-content:center;
	padding:2px 0;
}
.logo{
	width:150px;
	height:150px;
	object-fit:contain;
	filter:drop-shadow(0 12px 20px rgba(0,0,0,.28));
}
.brand{
	text-align:center;
}
.brand h1{
	margin:0;
	font-size:30px;
	line-height:1.05;
	font-weight:800;
}
.brand p{
	margin:14px 0 0;
	color:var(--muted);
	font-size:15px;
	line-height:1.75;
	text-align:center;
}
.footer-note{
	font-size:13px;
	color:var(--muted);
	line-height:1.6;
	text-align:center;
	max-width:280px;
	margin-top:8px;
}
.right{
	display:flex;
	flex-direction:column;
	justify-content:flex-start;
}
.status{
	display:inline-flex;
	align-items:center;
	gap:10px;
	font-size:14px;
	font-weight:700;
	padding:10px 14px;
	border-radius:999px;
	width:max-content;
	margin-bottom:16px;
}
.status-dot{
	width:10px;
	height:10px;
	border-radius:50%;
}
.status-loading{
	color:#fde68a;
	background:var(--warning-bg);
	border:1px solid var(--warning-border);
}
.status-loading .status-dot{
	background:var(--warning);
	box-shadow:0 0 12px rgba(245,158,11,.8);
}
.status-valid{
	color:#bbf7d0;
	background:var(--success-bg);
	border:1px solid var(--success-border);
}
.status-valid .status-dot{
	background:var(--success);
	box-shadow:0 0 12px rgba(34,197,94,.8);
}
.status-error{
	color:#fecaca;
	background:var(--danger-bg);
	border:1px solid var(--danger-border);
}
.status-error .status-dot{
	background:var(--danger);
	box-shadow:0 0 12px rgba(248,113,113,.8);
}
.title{
	font-size:32px;
	font-weight:900;
	line-height:1.1;
	margin:0 0 10px;
}
.subtitle{
	color:var(--muted);
	font-size:15px;
	line-height:1.7;
	margin-bottom:18px;
	max-width:560px;
}
.grid{
	display:grid;
	grid-template-columns:1fr;
	gap:14px;
}
.two-cols{
	display:grid;
	grid-template-columns:1fr;
	gap:14px;
	margin-top:14px;
}
.panel{
	background:rgba(255,255,255,.04);
	border:1px solid rgba(255,255,255,.08);
	border-radius:20px;
	padding:14px 18px;
}
.panel-label{
	font-size:13px;
	text-transform:uppercase;
	letter-spacing:.08em;
	color:var(--muted);
	margin-bottom:8px;
	font-weight:700;
}
.code{
	font-size:34px;
	font-weight:900;
	letter-spacing:.06em;
	word-break:break-word;
	line-height:1.15;
}
.link{
	display:inline-block;
	color:var(--link);
	text-decoration:none;
	font-weight:600;
	word-break:break-all;
	line-height:1.5;
}
.link:hover{
	text-decoration:underline;
}
.panel-value{
	font-size:15px;
	line-height:1.6;
}
.small-muted{
	color:var(--muted);
	font-size:13px;
	margin-top:8px;
	line-height:1.6;
}
.inline-row{
	display:flex;
	align-items:center;
	justify-content:space-between;
	gap:16px;
}
.inline-grow{
	flex:1 1 auto;
	min-width:0;
}
.btn{
	appearance:none;
	border:none;
	outline:none;
	cursor:pointer;
	border-radius:12px;
	padding:11px 14px;
	font-size:14px;
	font-weight:700;
	color:#fff;
	background:var(--accent);
	box-shadow:0 10px 24px rgba(124,58,237,.20);
	white-space:nowrap;
	flex:0 0 auto;
}
.btn:hover{
	background:var(--accent-hover);
}
.copy-ok{
	font-size:13px;
	color:#86efac;
	text-align:right;
	display:none;
}
.copy-ok.show{
	display:block;
	margin-top:6px;
}
.help{
	margin-top:14px;
	padding:14px 16px;
	border-radius:16px;
	background:rgba(255,255,255,.04);
	border:1px solid rgba(255,255,255,.08);
	color:var(--muted);
	font-size:14px;
	line-height:1.7;
}
.loading{
	display:flex;
	flex-direction:column;
	align-items:flex-start;
	gap:12px;
}
.loading-bar{
	width:100%;
	max-width:320px;
	height:12px;
	border-radius:999px;
	background:rgba(255,255,255,.08);
	overflow:hidden;
	position:relative;
}
.loading-bar::before{
	content:"";
	position:absolute;
	inset:0;
	width:40%;
	background:linear-gradient(90deg, transparent, rgba(124,58,237,.95), transparent);
	animation:load 1.2s infinite linear;
}
@keyframes load{
	0%{transform:translateX(-120%)}
	100%{transform:translateX(280%)}
}
.error-box{
	background:var(--danger-bg);
	border:1px solid var(--danger-border);
	color:#fecaca;
	border-radius:20px;
	padding:18px;
	font-size:15px;
	line-height:1.6;
}
@media (max-width: 900px){
	.card{grid-template-columns:1fr}
	.logo{width:120px;height:120px}
	.title{font-size:28px}
}
@media (max-width: 700px){
	.inline-row{
		flex-direction:column;
		align-items:flex-start;
	}
	.btn{
		width:100%;
	}
	.copy-ok,
	.copy-ok.show{
		text-align:left;
	}
}
@media (max-width: 560px){
	body{padding:10px}
	.card{padding:14px;border-radius:22px}
	.left,.panel{padding:16px}
	.brand h1{font-size:24px}
	.title{font-size:24px}
	.code{font-size:26px}
}
</style>
</head>
<body>
<div class="wrap">
	<div class="card">
		<div class="left">
			<div class="left-inner">
				<div class="logo-box">
					<img class="logo" src="/api/plugins/wizarrInvite/logo.png" alt="Logo Wizarr">
				</div>

				<div class="brand">
					<h1>Invitación<br>Wizarr</h1>
					<p>Usa este enlace de invitación para invitar a una persona o recuperar tu acceso a Plex.</p>
				</div>

				<div class="footer-note">
					El código mostrado aquí se verifica automáticamente al cargar esta página.
				</div>
			</div>
		</div>

		<div class="right">
			<div id="app">
				<div class="loading">
					<div class="status status-loading"><span class="status-dot"></span>En espera</div>
					<h2 class="title">Preparación de la invitación</h2>
					<div class="subtitle">El plugin recupera el código activo o crea uno nuevo si es necesario.</div>
					<div class="loading-bar"></div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
function escapeHtml(value) {
	return String(value)
		.replace(/&/g, "&amp;")
		.replace(/</g, "&lt;")
		.replace(/>/g, "&gt;")
		.replace(/"/g, "&quot;")
		.replace(/\'/g, "&#039;");
}

function formatDate(value) {
	if (!value || value === "-") return "-";
	var date = new Date(value);
	if (isNaN(date.getTime())) return value;
	return date.toLocaleString("es-ES");
}

function localizeApiMessage(message) {
	var map = {
		"Plugin disabled": "El plugin está desactivado",
		"Automatic mode is disabled": "El modo automático está desactivado",
		"Automatic mode disabled": "El modo automático está desactivado",
		"Configuration missing": "Falta la configuración",
		"Invalid Wizarr response": "Respuesta de Wizarr no válida",
		"Invalid plugin response.": "Respuesta no válida del plugin.",
		"Network error": "Error de red",
		"Access denied": "Acceso denegado",
		"Display file not found": "Archivo display no encontrado"
	};

	return map[message] || message || "Error";
}

function copyText(text, feedbackId) {
	if (!text) return;
	navigator.clipboard.writeText(text).then(function () {
		var el = document.getElementById(feedbackId);
		if (el) {
			el.textContent = "Copiado al portapapeles";
			el.classList.add("show");
			setTimeout(function () {
				el.textContent = "";
				el.classList.remove("show");
			}, 1800);
		}
	}).catch(function () {
		var el = document.getElementById(feedbackId);
		if (el) {
			el.textContent = "No se puede copiar";
			el.classList.add("show");
			setTimeout(function () {
				el.textContent = "";
				el.classList.remove("show");
			}, 1800);
		}
	});
}

fetch("/api/v2/plugins/wizarrinvite/current", { credentials: "same-origin" })
.then(function(r){ return r.json(); })
.then(function(data){
	var app = document.getElementById("app");

	if (!data || !data.response) {
		app.innerHTML =
			"<div class=\"status status-error\"><span class=\"status-dot\"></span>Error</div>" +
			"<div class=\"error-box\">Respuesta no válida del plugin.</div>";
		return;
	}

	if (data.response.result !== "success") {
		app.innerHTML =
			"<div class=\"status status-error\"><span class=\"status-dot\"></span>Error</div>" +
			"<div class=\"error-box\">" + escapeHtml(localizeApiMessage(data.response.message)) + "</div>";
		return;
	}

	var d = data.response.data || {};
	var code = d.code || "-";
	var url = d.url || "";
	var expires = d.expires || "-";

	app.innerHTML =
		"<div class=\"status status-valid\"><span class=\"status-dot\"></span>Válido</div>" +
		"<h2 class=\"title\">Tu invitación está lista</h2>" +
		"<div class=\"subtitle\">Usa el enlace directo o comparte simplemente el código de abajo.</div>" +

		"<div class=\"grid\">" +

			"<div class=\"panel\">" +
				"<div class=\"panel-label\">Código de invitación</div>" +
				"<div class=\"code\">" + escapeHtml(code) + "</div>" +
				"<div class=\"small-muted\">Este código ya está incluido en el enlace de invitación justo debajo.</div>" +
			"</div>" +

			"<div class=\"panel\">" +
				"<div class=\"panel-label\">Enlace de invitación</div>" +
				"<div class=\"inline-row\">" +
					"<div class=\"inline-grow\">" +
						(url
							? "<a class=\"link\" target=\"_blank\" rel=\"noopener noreferrer\" href=\"" + escapeHtml(url) + "\">" + escapeHtml(url) + "</a>"
							: "<div>-</div>") +
					"</div>" +
					"<button class=\"btn\" id=\"copy-link-btn\" type=\"button\">Copiar el enlace</button>" +
				"</div>" +
				"<div class=\"small-muted\">Usa este enlace completo para compartir directamente la invitación Wizarr.</div>" +
				"<div id=\"copy-link-feedback\" class=\"copy-ok\"></div>" +
			"</div>" +

		"</div>" +

		"<div class=\"two-cols\">" +
			"<div class=\"panel\">" +
				"<div class=\"panel-label\">Expiración</div>" +
				"<div class=\"panel-value\">" + escapeHtml(formatDate(expires)) + "</div>" +
				"<div class=\"small-muted\">Hora local</div>" +
			"</div>" +
		"</div>" +

		"<div class=\"help\">Usa este enlace para invitar a una persona o recuperar un acceso Plex ya existente. El enlace de arriba puede copiarse con un solo clic.</div>";

	var copyLinkBtn = document.getElementById("copy-link-btn");

	if (copyLinkBtn) {
		copyLinkBtn.addEventListener("click", function () {
			copyText(url, "copy-link-feedback");
		});
	}
})
.catch(function(){
	document.getElementById("app").innerHTML =
		"<div class=\"status status-error\"><span class=\"status-dot\"></span>Error</div>" +
		"<div class=\"error-box\">No se puede cargar el código de invitación.</div>";
});
</script>
</body>
</html>';