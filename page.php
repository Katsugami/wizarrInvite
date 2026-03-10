<?php

$GLOBALS['organizrPages'][] = 'wizarrinvite';

function get_page_wizarrinvite($Organizr)
{
	if (!$Organizr) {
		$Organizr = new Organizr();
	}

	if (!$Organizr->hasDB()) {
		return false;
	}

	$minGroup = (int)$Organizr->config['WIZARRINVITE-min-group'];

	if (!$Organizr->qualifyRequest($minGroup, true)) {
		return false;
	}

	return '
	<div class="container-fluid">
		<div class="row">
			<div class="col-md-10 col-md-offset-1">
				<div class="panel panel-default" style="margin-top:20px;">
					<div class="panel-heading">
						<h3 class="panel-title">Wizarr Invitation</h3>
					</div>
					<div class="panel-body">
						<div id="wizarrinvite-app">Loading...</div>
					</div>
				</div>
			</div>
		</div>
	</div>';
}