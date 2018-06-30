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
		$this->tablesUsed[] = 'revision';
		$this->tablesUsed[] = 'createdpageslist';
	}

	public function getUser() {
		return User::newFromName( 'UTSysop' );
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

		$this->assertCreatedBy( null, $title );

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
		$title = Title::newFromText( 'UTPage' ); // Always created in MediaWikiTestCase::addCoreDBData()
		$page = WikiPage::factory( $title );

		$user = $page->getCreator();
		$this->assertCreatedBy( $user, $title );

		$page->doDeleteArticle( 'for some reason' );

		$this->assertCreatedBy( null, $title );
	}

	/**
		@brief Ensures that moved page remains in 'createdpageslist' table.
	*/
	public function testMovedPage() {
		$ot = Title::newFromText( 'UTPage' ); // Always created im MediaWikiTestCase::addCoreDBData()
		$nt = Title::newFromText( 'New page title' );
		$user = WikiPage::factory( $ot )->getCreator();

		$this->assertCreatedBy( $user, $ot );

		$mp = new MovePage( $ot, $nt );
		$mp->move( $this->getUser(), 'for some reason', true );

		$this->assertCreatedBy( $user, $nt );
		$this->assertCreatedBy( null, $ot );
	}
}
