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

use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;

/**
	@file
	@brief Hooks of Extension:CreatedPagesList.
*/

class CreatedPagesListHooks {

	/** @brief Add newly created article into the 'createdpageslist' SQL table. */
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

	/** @brief Remove deleted article from the 'createdpageslist' SQL table. */
	public static function onArticleDeleteComplete(
		&$article, User &$user, $reason, $id, $content, LogEntry $logEntry
	) {
		CreatedPagesList::delete( $article->getTitle() );
	}

	/** @brief Add newly undeleted article into the 'createdpageslist' SQL table. */
	public static function onArticleUndelete(
		$title, $created, $comment, $oldPageId, $restoredPages = []
	) {
		DeferredUpdates::addCallableUpdate( function () use ( $title ) {
			$rev = MediaWikiServices::getInstance()->getRevisionLookup()->getFirstRevision( $title );
			$user = User::newFromName(
				$rev->getUser( RevisionRecord::RAW )->getName(),
				false
			);

			CreatedPagesList::add( $user, $title, $rev->getTimestamp() );
		} );
	}

	/** @brief Rename the moved article in 'createdpageslist' SQL table. */
	public static function onPageMoveComplete( LinkTarget $old, LinkTarget $new ) {
		CreatedPagesList::move(
			Title::newFromLinkTarget( $old ),
			Title::newFromLinkTarget( $new )
		);
	}

	/** @brief Extra DB fields to rename when user is renamed via Extension:UserMerge. */
	public static function onUserMergeAccountFields( &$updateFields ) {
		$updateFields[] = [
			'createdpageslist',
			'cpl_user',
			'cpl_user_text',
			'batchKey' => 'cpl_id',
			'options' => [ 'IGNORE' ]
		];
		return true;
	}

	/** @brief Delete extra DB rows when account is deleted. */
	public static function onUserMergeAccountDeleteTables( &$tablesToDelete ) {
		$tablesToDelete['createdpageslist'] = 'cpl_user';
		return true;
	}
}
