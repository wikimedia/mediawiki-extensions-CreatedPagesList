<?php

/*
	Extension:CreatedPagesList - MediaWiki extension.
	Copyright (C) 2018 Edward Chernenko.

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
	@brief Tests of recalculation of 'createdpageslist' SQL table.
*/

require_once __DIR__ . '/CreatedPagesListTestBase.php';

use MediaWiki\MediaWikiServices;
use Wikimedia\IPUtils;

/**
 * @group Database
 */
class CreatedPagesListRecalculateTest extends CreatedPagesListTestBase {
	/**
		@brief Ensures that 'createdpageslist' table is correctly populated.
		@covers CreatedPagesList::recalculateSqlTable
	*/
	public function testInitialize() {
		// Fill 'revision' table with fake edits,
		// then call recalculateSqlTable() and check the results.
		$testData = [
			'Page 1' => [ 'User 1', 'User 2', 'User 3' ], // Has 3 edits, first by "User 1"
			'Page 2' => [ 'User 1' ],
			'Page 3' => [ 'User 2', 'User 1' ],
			'Page 4' => [ 'User 2', 'User 1', 'User 1' ],
			'Page 5' => [ 'User 3', 'User 2', 'User 1' ],
			'Page 6' => [ '127.0.0.1', 'User 1' ],
		];

		$dbw = wfGetDB( DB_MASTER );
		$services = MediaWikiServices::getInstance();
		$revStore = $services->getRevisionStore();
		foreach ( $testData as $title => $authors ) {
			$page = WikiPage::factory( Title::newFromText( $title ) );
			$page->insertOn( $dbw );

			$ts = new MWTimestamp();
			$ts->timestamp->modify( '-' . count( $authors ) . ' seconds' );

			foreach ( $authors as $username ) {
				if ( IPUtils::isValid( $username ) ) {
					$user = User::newFromName( $username, false );
				} else {
					$user = User::newSystemUser( $username, [ 'steal' => true ] );
				}

				$recordToInsert = $revStore->newMutableRevisionFromArray( [
					'page'       => $page->getId(),
					'comment'    => '',
					'text'       => 'Whatever',
					'user'       => $user->getId(),
					'user_text'  => $user->getName(),
					'timestamp'  => $ts->getTimestamp( TS_MW ),
					'content_model' => CONTENT_MODEL_WIKITEXT
				] );

				$storedRecord = $revStore->insertRevisionOn( $recordToInsert, $dbw );
				$page->updateRevisionOn( $dbw, $storedRecord );

				$ts->timestamp->modify( '+1 second' );
			}
		}

		// Calculate the 'createdpageslist' SQL table
		CreatedPagesList::recalculateSqlTable();

		// Check the expected authors
		$this->assertCreatedByText( 'User 1', 'Page 1' );
		$this->assertCreatedByText( 'User 1', 'Page 2' );
		$this->assertCreatedByText( 'User 2', 'Page 3' );
		$this->assertCreatedByText( 'User 2', 'Page 4' );
		$this->assertCreatedByText( 'User 3', 'Page 5' );
		$this->assertCreatedByText( '127.0.0.1', 'Page 6' );

		// Also check the testpage from MediaWikiTestCase::addCoreDBData()
		$title = $this->getExistingTitle();
		$user = WikiPage::factory( $title )->getCreator();
		$this->assertCreatedBy( $user, $title );
	}
}
