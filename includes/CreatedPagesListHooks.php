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
	@brief Hooks of Extension:CreatedPagesList.
*/

class CreatedPagesListHooks {

	/** @brief Add newly created article into the 'createdpageslist' SQL table. */
	public static function onPageContentInsertComplete( $wikiPage, User $user, $content,
		$summary, $isMinor, $isWatch, $section, $flags, Revision $revision
	) {
		CreatedPagesList::add(
			$user,
			$wikiPage->getTitle(),
			$revision->getTimestamp(),
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
			$rev = $title->getFirstRevision();
			$user = User::newFromName(
				$rev->getUserText( Revision::RAW ),
				false
			);

			CreatedPagesList::add( $user, $title, $rev->getTimestamp() );
		} );
	}

	/** @brief Rename the moved article in 'createdpageslist' SQL table. */
	public static function onTitleMoveComplete(
		Title &$title, Title &$newTitle, User $user,
		$oldid, $newid, $reason, Revision $revision
	) {
		CreatedPagesList::move( $title, $newTitle );
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
