<?php

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdates;

class ElectionHooks implements LoadExtensionSchemaUpdatesHook {
	public function onLoadExtensionSchemaUpdates($updater) {
		$updater->addExtensionTable('election_votes', __DIR__ . '/../sql/election_votes.sql');
		$updater->addExtensionTable('election_voters', __DIR__ . '/../sql/election_voters.sql');
	}
}
