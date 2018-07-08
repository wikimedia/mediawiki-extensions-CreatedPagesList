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
	@brief Parent class for tests that check 'createdpageslist' SQL table.
*/

class CreatedPagesListTestBase extends MediaWikiTestCase {
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
	protected function getUser() {
		return User::newFromName( 'UTSysop' );
	}

	/** @brief Returns Title object of existing test page. */
	protected function getExistingTitle() {
		// Always created in MediaWikiTestCase::addCoreDBData()
		return Title::newFromText( 'UTPage' );
	}

	/**
	 * @brief Asserts that $expectedAuthor is recorded as creator of $title.
	 * @param User|null $expectedAuthor
	 * @param Title $title
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

	/** @brief Same as assertCreatedBy(), but expects User/Title as strings. */
	protected function assertCreatedByText( $username = '', $pageName ) {
		$this->assertCreatedBy(
			$username ? User::newFromName( $username, false ) : null,
			Title::newFromText( $pageName )
		);
	}
}
