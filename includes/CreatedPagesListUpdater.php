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

class CreatedPagesListUpdater {
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$sqlDir = __DIR__ . '/../sql';
		$updater->addExtensionTable( 'createdpageslist', "$sqlDir/patch-createdpageslist.sql" );

		$db = $updater->getDB();
		$needRecalculation = false;

		if ( !$db->fieldInfo( 'createdpageslist', 'cpl_actor' ) ) {
			// Old schema, table needs to be recalculated.
			$needRecalculation = true;
		} elseif ( $db->selectRowCount( 'createdpageslist' ) === 0 ) {
			// Table is empty (extension was just installed), needs to be populated.
			$needRecalculation = true;
		}

		$updater->addExtensionField( 'createdpageslist', 'cpl_actor',
				"$sqlDir/patch-createdpageslist-cpl_actor.sql" );

		if ( $needRecalculation ) {
			// Recalculate only when needed, not on every update.php
			$updater->addExtensionUpdate( [ [ __CLASS__, 'populateSqlTable' ] ] );
		}
	}

	public static function populateSqlTable( DatabaseUpdater $updater ) {
		$updater->output( "Populating createdpageslist table...\n" );
		CreatedPagesList::recalculateSqlTable();
	}
}
