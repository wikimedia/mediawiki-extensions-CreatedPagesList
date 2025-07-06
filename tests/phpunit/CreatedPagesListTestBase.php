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

use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;

/**
 * @file
 * Parent class for tests that check 'createdpageslist' SQL table.
 */

class CreatedPagesListTestBase extends MediaWikiIntegrationTestCase {
	/**
	 * Asserts that $expectedAuthor is recorded as creator of $title.
	 * @param UserIdentity|null $expectedAuthor
	 * @param LinkTarget $title
	 */
	protected function assertCreatedBy( ?UserIdentity $expectedAuthor, LinkTarget $title ) {
		// Don't want to use ActorNormalization service (1.36+) to get actor ID from UserIdentity,
		// as this change may be backported to MediaWiki 1.35 (LTS).
		if ( $expectedAuthor ) {
			$user = $this->getServiceContainer()->getUserFactory()->newFromUserIdentity( $expectedAuthor );
			$expectedActorId = $user->getActorId();
		}

		$this->assertSelect( 'createdpageslist',
			[ 'cpl_actor' ],
			[
				'cpl_page' => $title->getArticleId()
			],
			$expectedAuthor ? [ [
				$expectedActorId
			] ] : [],
			[],
			[]
		);
	}

	/**
	 * Same as assertCreatedBy(), but expects User/Title as strings.
	 * @param string $username
	 * @param string $pageName
	 */
	protected function assertCreatedByText( $username, $pageName ) {
		$this->assertCreatedBy(
			$username ? User::newFromName( $username, false ) : null,
			Title::newFromText( $pageName )
		);
	}
}
