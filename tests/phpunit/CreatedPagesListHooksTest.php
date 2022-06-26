<?php

/*
	Extension:CreatedPagesList - MediaWiki extension.
	Copyright (C) 2018-2021 Edward Chernenko.

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
 * Tests of the hooks that update 'createdpageslist' SQL table.
 */

require_once __DIR__ . '/CreatedPagesListTestBase.php';

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;

/**
 * @group Database
 * @covers CreatedPagesListHooks
 */
class CreatedPagesListHooksTest extends CreatedPagesListTestBase {
	/**
	 * Ensures that newly created page appears in 'createdpageslist' table.
	 * @covers CreatedPagesListHooks::onPageSaveComplete
	 */
	public function testNewPage() {
		$title = Title::newFromText( 'Non-existent page' );
		$this->assertCreatedBy( null, $title ); // Assert starting conditions

		$user = $this->getUser();
		$content = new WikitextContent( 'UTContent' );
		$summary = CommentStoreComment::newUnsavedComment( 'UTPageSummary' );

		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		} else {
			$page = WikiPage::factory( $title );
		}
		$updater = $page->newPageUpdater( $user );

		$updater->setContent( SlotRecord::MAIN, $content );
		$updater->saveRevision( $summary, EDIT_INTERNAL );

		if ( !$updater->getStatus()->isOK() ) {
			throw new MWException( 'Preparing the test: saveRevision() failed: ' . $status->getMessage() );
		}

		$this->assertCreatedBy( $user, $title );
	}

	/**
	 * Ensures that deleted page is deleted from 'createdpageslist' table.
	 * @covers CreatedPagesListHooks::onArticleDeleteComplete
	 */
	public function testDeletedPage() {
		$title = $this->getExistingTitle();
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		} else {
			$page = WikiPage::factory( $title );
		}

		$user = $page->getCreator();
		$this->assertCreatedBy( $user, $title ); // Assert starting conditions

		$reason = 'for some reason';
		if ( version_compare( MW_VERSION, '1.35', '<' ) ) {
			$page->doDeleteArticle( $reason );
		} else {
			$page->doDeleteArticleReal( $reason, $this->getTestSysop()->getUser() );
		}

		$this->assertCreatedBy( null, $title );
	}

	/**
	 * Ensures that undeleted page is restored in 'createdpageslist' table.
	 * @covers CreatedPagesListHooks::onArticleUndelete
	 */
	public function testUndeletedPage() {
		$title = $this->getExistingTitle();
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		} else {
			$page = WikiPage::factory( $title );
		}
		$user = $page->getCreator();

		$reason = 'for some reason';
		if ( version_compare( MW_VERSION, '1.35', '<' ) ) {
			$page->doDeleteArticle( $reason );
		} else {
			$page->doDeleteArticleReal( $reason, $this->getTestSysop()->getUser() );
		}

		$this->assertCreatedBy( null, $title ); // Assert starting conditions

		$archive = new PageArchive( $title );

		if ( method_exists( $archive, 'undeleteAsUser' ) ) {
			$archive->undeleteAsUser( [], $this->getTestSysop()->getUser() );
		} else {
			$archive->undelete( [] );
		}

		$this->assertCreatedBy( $user, $title );
	}
}
