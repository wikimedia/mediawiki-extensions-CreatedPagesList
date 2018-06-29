<?php

/*
	Extension:CreatedPagesList - MediaWiki extension.
	Copyright (C) 2012-2018 Edward Chernenko.

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
	@file
	@brief Creates/updates the SQL tables when 'update.php' is invoked.
*/

class CreatedPagesListUpdater {
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$base = dirname( __FILE__ );
		$updater->addExtensionTable( 'createdpageslist', "$base/../sql/patch-createdpageslist.sql" );

		return true;
	}
}
