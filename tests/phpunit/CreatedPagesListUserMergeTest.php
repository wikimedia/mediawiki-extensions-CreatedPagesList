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
	@brief Checks situation when user is renamed/deleted by Extension:UserMerge.
*/

require_once __DIR__ . '/CreatedPagesListTestBase.php';

/**
 * @group Database
 */
class CreatedPagesListUserMergeTest extends CreatedPagesListTestBase {
	public static function setUpBeforeClass() {
		if ( !class_exists( 'MergeUser' ) ) {
			self::markTestSkipped( 'Test skipped: UserMerge extension must be installed to run it.' );
		}

		parent::setUpBeforeClass();
	}

	/**
		@brief Returns User object of performer for MergeUser methods.
		Doesn't matter for these tests, so we'll use an anonymous user.
	*/
	protected function getPerformer() {
		return User::newFromName( '127.0.0.1', false );
	}

	/**
		@brief Ensures that user being renamed by Extension:UserMerge updates 'createdpageslist' table.
		@covers CreatedPagesListHooks::onUserMergeAccountFields
	*/
	public function testRenamedUser() {
		$title = $this->getExistingTitle();

		$oldUser = WikiPage::factory( $title )->getCreator();
		$newUser = ( new TestUser( 'Some other user' ) )->getUser();

		$this->assertCreatedBy( $oldUser, $title );  // Assert starting conditions

		$mu = new MergeUser( $oldUser, $newUser, new UserMergeLogger() );
		$mu->merge( $this->getPerformer() );

		$this->assertCreatedBy( $newUser, $title );
	}

	/**
		@brief Ensures that user being deleted by Extension:UserMerge updates 'createdpageslist' table.
		@covers CreatedPagesListHooks::onUserMergeAccountDeleteTables
	*/
	public function testDeletedUser() {
		$title = $this->getExistingTitle();

		$oldUser = WikiPage::factory( $title )->getCreator();
		$newUser = ( new TestUser( 'Some other user' ) )->getUser();

		$this->assertCreatedBy( $oldUser, $title );  // Assert starting conditions

		$mu = new MergeUser( $oldUser, $newUser, new UserMergeLogger() );
		$mu->delete( $this->getPerformer(), 'wfMessage' );

		$this->assertCreatedBy( null, $title );
	}
}
