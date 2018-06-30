<?php

/*
	Extension:CreatedPagesList - MediaWiki extension.
	Copyright (C) 2018 Edward Chernenko.

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
*/

/**
	@file
	@brief Checks the code that updates 'createdpageslist' SQL table.
	@group Database
*/

/**
	@covers CreatedPagesList
*/
class CreatedPagesListTest extends MediaWikiTestCase
{
	public function needsDB() {
		return true;
	}

	protected function setUp() {
		parent::setUp();
		$this->tablesUsed = array_merge( $this->tablesUsed, [
			'createdpageslist',
			'revision',
			'page',
			'text'
		] );
	}

	/** @brief Returns User object of test user. */
	public function getUser() {
		return User::newFromName( 'UTSysop' );
	}

	/** @brief Returns Title object of existing test page. */
	public function getExistingTitle() {
		// Always created in MediaWikiTestCase::addCoreDBData()
		return Title::newFromText( 'UTPage' );
	}

	/**
		@brief Asserts that $expectedAuthor is recorded as creator of $title.
		@param $expectedAuthor User object or null (null means "assert that $title is not in the database").
	*/
	protected function assertCreatedBy( User $expectedAuthor = null, Title $title ) {
		$this->assertSelect( 'createdpageslist',
			[ 'cpl_user', 'cpl_user_text' ],
			[
				'cpl_namespace' => $title->getNamespace(),
				'cpl_title' => $title->getDBKey()
			],
			$expectedAuthor ? [ [
				$expectedAuthor->getId(),
				$expectedAuthor->getName()
			] ] : [],
			[],
			[]
		);
	}

	/**
		@brief Ensures that newly created page appears in 'createdpageslist' table.
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

	/**
		@brief Ensures that user being renamed by Extension:UserMerge updates 'createdpageslist' table.
	*/
	public function testRenamedUser() {
		$this->skipIfNoUserMerge();

		$performer = User::newFromName( '127.0.0.1', false );
		$title = $this->getExistingTitle();

		$oldUser = WikiPage::factory( $title )->getCreator();
		$newUser = ( new TestUser( 'Some other user' ) )->getUser();

		$this->assertCreatedBy( $oldUser, $title );  // Assert starting conditions

		$mu = new MergeUser( $oldUser, $newUser, new UserMergeLogger() );
		$mu->merge( $performer );

		$this->assertCreatedBy( $newUser, $title );
	}

	/**
		@brief Ensures that user being deleted by Extension:UserMerge updates 'createdpageslist' table.
	*/
	public function testDeletedUser() {
		$this->skipIfNoUserMerge();

		$performer = User::newFromName( '127.0.0.1', false );
		$title = $this->getExistingTitle();

		$oldUser = WikiPage::factory( $title )->getCreator();
		$newUser = ( new TestUser( 'Some other user' ) )->getUser();

		$this->assertCreatedBy( $oldUser, $title );  // Assert starting conditions

		$mu = new MergeUser( $oldUser, $newUser, new UserMergeLogger() );
		$mu->delete( $performer, 'wfMessage' );

		$this->assertCreatedBy( null, $title );
	}

	public function skipIfNoUserMerge() {
		if ( !class_exists( 'MergeUser' ) ) {
			$this->markTestSkipped( 'Test skipped: UserMerge extension must be installed to run it.' );
		}
	}
}
