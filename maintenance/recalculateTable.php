<?php

/*
	Extension:CreatedPagesList - MediaWiki extension.
	Copyright (C) 2022 Edward Chernenko.

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
 * Maintenance script to recalculate the contents of 'createdpageslist' SQL table.
 */

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

class RecalculateTable extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'CreatedPagesList' );
		$this->addDescription( 'Rebuilds the CreatedPagesList table' );
	}

	public function execute() {
		CreatedPagesList::recalculateSqlTable();
		echo "Recalculated \"createdpageslist\" table.\n";
	}
}

$maintClass = RecalculateTable::class;
require_once RUN_MAINTENANCE_IF_MAIN;
