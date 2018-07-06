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
	@brief Tests of the hooks that update 'createdpageslist' SQL table.
	@group Database
*/

require_once __DIR__ . '/CreatedPagesListTestBase.php';

/**
	@covers CreatedPagesListHooks
*/
class CreatedPagesListHooksTest extends CreatedPagesListTestBase
{
	/**
		@brief Ensures that newly created page appears in 'createdpageslist' table.
		@covers CreatedPagesListHooks::onPageContentInsertComplete
	*/
	public function testNewPage() {
		$title = Title::newFromText( 'Non-existent page' );
		$user = $this->getUser();

		$this->assertCreatedBy( null, $title ); // Assert starting conditions

		$page = WikiPage::factory( $title );
		$status = $page->doEditContent(
			new WikitextContent( 'UTContent' ),
			'UTPageSummary',
			EDIT_NEW,
			false,
			$user
		);
		if ( !$status->isOK() ) {
			throw new MWException( 'Preparing the test: doEditContent() failed: ' . $status->getMessage() );
		}

		$this->assertCreatedBy( $user, $title );
	}

	/**
		@brief Ensures that deleted page is deleted from 'createdpageslist' table.
		@covers CreatedPagesListHooks::onArticleDeleteComplete
	*/
	public function testDeletedPage() {
		$title = $this->getExistingTitle();
		$page = WikiPage::factory( $title );

		$user = $page->getCreator();
		$this->assertCreatedBy( $user, $title ); // Assert starting conditions

		$page->doDeleteArticle( 'for some reason' );

		$this->assertCreatedBy( null, $title );
	}

	/**
		@brief Ensures that moved page remains in 'createdpageslist' table.
		@covers CreatedPagesListHooks::onTitleMoveComplete
	*/
	public function testMovedPage() {
		$ot = $this->getExistingTitle();
		$nt = Title::newFromText( 'New page title' );
		$user = WikiPage::factory( $ot )->getCreator();

		$this->assertCreatedBy( $user, $ot );  // Assert starting conditions

		$mp = new MovePage( $ot, $nt );
		$mp->move( $this->getUser(), 'for some reason', true );

		$this->assertCreatedBy( $user, $nt );
		$this->assertCreatedBy( null, $ot );
	}

	/**
		@brief Ensures that undeleted page is restored in 'createdpageslist' table.
		@covers CreatedPagesListHooks::onArticleUndelete
	*/
	public function testUndeletedPage() {
		$title = $this->getExistingTitle();
		$page = WikiPage::factory( $title );
		$user = $page->getCreator();

		$page->doDeleteArticle( 'for some reason' );
		$this->assertCreatedBy( null, $title ); // Assert starting conditions

		$archive = new PageArchive( $title );
		$archive->undelete( [] );

		$this->assertCreatedBy( $user, $title );
	}
}
