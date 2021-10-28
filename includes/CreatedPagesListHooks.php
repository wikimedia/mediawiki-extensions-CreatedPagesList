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
 * Hooks of Extension:CreatedPagesList.
 */

use MediaWiki\Page\Hook\ArticleDeleteCompleteHook;
use MediaWiki\Page\Hook\ArticleUndeleteHook;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;

class CreatedPagesListHooks implements
	ArticleDeleteCompleteHook,
	ArticleUndeleteHook,
	PageSaveCompleteHook
{
	/** @var RevisionLookup */
	protected $revisionLookup;

	/**
	 * @param RevisionLookup $revisionLookup
	 */
	public function __construct( RevisionLookup $revisionLookup ) {
		$this->revisionLookup = $revisionLookup;
	}

	/**
	 * Add newly created article into the 'createdpageslist' SQL table.
	 *
	 * @inheritDoc
	 */
	public function onPageSaveComplete(
		$wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
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
	 *
	 * @inheritDoc
	 */
	public function onArticleDeleteComplete(
		$wikiPage, $user, $reason, $id, $content, $logEntry, $archivedRevisionCount
	) {
		CreatedPagesList::delete( $wikiPage->getTitle() );
	}

	/**
	 * Add newly undeleted article into the 'createdpageslist' SQL table.
	 *
	 * @inheritDoc
	 */
	public function onArticleUndelete(
		$title, $create, $comment, $oldPageId, $restoredPages = []
	) {
		DeferredUpdates::addCallableUpdate( function () use ( $title ) {
			$rev = $this->revisionLookup->getFirstRevision( $title );
			$user = User::newFromName(
				$rev->getUser( RevisionRecord::RAW )->getName(),
				false
			);

			CreatedPagesList::add( $user, $title, $rev->getTimestamp() );
		} );
	}
}
