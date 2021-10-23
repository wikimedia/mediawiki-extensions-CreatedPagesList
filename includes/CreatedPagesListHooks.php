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
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;

/**
 * @file
 * Hooks of Extension:CreatedPagesList.
 */

class CreatedPagesListHooks {

	/**
	 * Add newly created article into the 'createdpageslist' SQL table.
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $user
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 */
	public static function onPageSaveComplete( WikiPage $wikiPage,
		UserIdentity $user, $summary, $flags, RevisionRecord $revisionRecord
	) {
		CreatedPagesList::add(
			User::newFromIdentity( $user ),
			$wikiPage->getTitle(),
			$revisionRecord->getTimestamp(),
			$wikiPage->isRedirect()
		);
	}

	/**
	 * Remove deleted article from the 'createdpageslist' SQL table.
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param string $reason
	 * @param int $id
	 * @param Content|null $content
	 * @param ManualLogEntry $logEntry
	 */
	public static function onArticleDeleteComplete(
		$wikiPage, User $user, $reason, $id, $content, LogEntry $logEntry
	) {
		CreatedPagesList::delete( $wikiPage->getTitle() );
	}

	/**
	 * Add newly undeleted article into the 'createdpageslist' SQL table.
	 * @param Title $title
	 * @param bool $create
	 * @param string $comment
	 * @param int $oldPageId
	 * @param array $restoredPages
	 */
	public static function onArticleUndelete(
		$title, $create, $comment, $oldPageId, $restoredPages = []
	) {
		DeferredUpdates::addCallableUpdate( static function () use ( $title ) {
			$rev = MediaWikiServices::getInstance()->getRevisionLookup()->getFirstRevision( $title );
			$user = User::newFromName(
				$rev->getUser( RevisionRecord::RAW )->getName(),
				false
			);

			CreatedPagesList::add( $user, $title, $rev->getTimestamp() );
		} );
	}

	/**
	 * Rename the moved article in 'createdpageslist' SQL table.
	 * @param LinkTarget $old
	 * @param LinkTarget $new
	 */
	public static function onPageMoveComplete( LinkTarget $old, LinkTarget $new ) {
		CreatedPagesList::move(
			Title::newFromLinkTarget( $old ),
			Title::newFromLinkTarget( $new )
		);
	}
}
