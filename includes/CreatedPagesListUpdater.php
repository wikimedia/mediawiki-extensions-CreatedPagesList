<?php

/*
	Extension:CreatedPagesList - MediaWiki extension.
	Copyright (C) 2012-2021 Edward Chernenko.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

/**
 * @file
 * Creates/updates the SQL tables when 'update.php' is invoked.
 */

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class CreatedPagesListUpdater implements LoadExtensionSchemaUpdatesHook {
	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$db = $updater->getDB();

		// Because the table "createdpageslist" can be completely recalculated with recalculateSqlTable(),
		// there is no reason to patch individual fields when the schema changes.
		// Instead we drop the table and recreate it with the new schema.
		$mustCreateTable = false;
		if ( !$updater->tableExists( 'createdpageslist' ) ) {
			$mustCreateTable = true;
		} elseif (
			!$db->fieldInfo( 'createdpageslist', 'cpl_actor' ) ||
			!$db->fieldInfo( 'createdpageslist', 'cpl_page' ) ||
			!$db->indexUnique( 'createdpageslist', 'createdpageslist_page' )
		) {
			// Table already exists, but the schema is outdated.
			$updater->dropExtensionTable( 'createdpageslist' );
			$mustCreateTable = true;
		}

		if ( $mustCreateTable ) {
			$sqlDir = __DIR__ . '/../sql';
			$updater->addExtensionTable( 'createdpageslist', "$sqlDir/patch-createdpageslist.sql" );

			// Repopulate the entire table. This only happens when needed, not on every update.php
			$updater->addExtensionUpdate( [ [ __CLASS__, 'populateSqlTable' ] ] );
		}

		return true;
	}

	public static function populateSqlTable( DatabaseUpdater $updater ) {
		$updater->output( "Populating createdpageslist table...\n" );
		CreatedPagesList::recalculateSqlTable();
	}
}
