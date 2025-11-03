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
 * @file
 * Tests of recalculation of 'createdpageslist' SQL table.
 */

require_once __DIR__ . '/CreatedPagesListTestBase.php';

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use Wikimedia\IPUtils;

/**
 * @group Database
 */
class CreatedPagesListRecalculateTest extends CreatedPagesListTestBase {
	/**
	 * Ensures that 'createdpageslist' table is correctly populated.
	 * @covers CreatedPagesList::recalculateSqlTable
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

		$services = MediaWikiServices::getInstance();
		if ( method_exists( MediaWikiServices::class, 'getConnectionProvider' ) ) {
			// MW 1.42+
			$dbw = $services->getConnectionProvider()->getPrimaryDatabase();
		} else {
			$dbw = wfGetDB( DB_PRIMARY );
		}
		$revStore = $services->getRevisionStore();
		$wikiPageFactory = $services->getWikiPageFactory();
		$userFactory = $services->getUserFactory();
		foreach ( $testData as $subject => $authors ) {
			$title = Title::newFromText( $subject );
			$page = $wikiPageFactory->newFromTitle( $title );
			$page->insertOn( $dbw );

			$ts = new MWTimestamp();
			$ts->timestamp->modify( '-' . count( $authors ) . ' seconds' );

			foreach ( $authors as $username ) {
				if ( IPUtils::isValid( $username ) ) {
					if ( $services->getTempUserCreator()->isEnabled() ) {
						$user = $userFactory->newUnsavedTempUser( null );
						$user->addToDatabase();
					} else {
						$user = User::newFromName( $username, false );
					}
				} else {
					$user = User::newSystemUser( $username, [ 'steal' => true ] );
				}

				$recordToInsert = new MutableRevisionRecord( $title );
				$recordToInsert->setContent(
					SlotRecord::MAIN,
					ContentHandler::makeContent( 'Whatever', null, CONTENT_MODEL_WIKITEXT )
				);
				$recordToInsert->setUser( $user );
				$recordToInsert->setTimestamp( $ts->getTimestamp( TS_MW ) );
				$recordToInsert->setPageId( $page->getId() );
				$recordToInsert->setComment( CommentStoreComment::newUnsavedComment( '' ) );

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
		$page = $wikiPageFactory->newFromTitle( Title::makeTitle( NS_MAIN, 'Page 6' ) );
		$this->assertCreatedBy( $page->getCreator(), $page->getTitle() );

		// Also check some random testpage
		$page = $this->getExistingTestPage();
		$this->assertCreatedBy( $page->getCreator(), $page->getTitle() );
	}
}
