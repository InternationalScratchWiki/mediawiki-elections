<?php
class ElectionHooks {
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable('election_votes', __DIR__ . '/../sql/election_votes.sql');
	}
}
