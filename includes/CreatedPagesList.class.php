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
	@brief Keeps 'createdpageslist' SQL table up to date.
*/

class CreatedPagesList {

	/**
		@brief Add newly created article into the 'createdpageslist' SQL table.
	*/
	public static function onPageContentInsertComplete( $wikiPage, User $user, $content,
		$summary, $isMinor, $isWatch, $section, $flags, Revision $revision
	) {
		self::add(
			$user,
			$wikiPage->getTitle(),
			$revision->getTimestamp(),
			$wikiPage->isRedirect()
		);
	}

	/**
		@brief Remove deleted article from the 'createdpageslist' SQL table.
	*/
	public static function onArticleDeleteComplete( &$article, User &$user, $reason, $id, $content, LogEntry $logEntry ) {
		$title = $article->getTitle();

		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'createdpageslist',
			[
				'cpl_namespace' => $title->getNamespace(),
				'cpl_title' => $title->getDBKey()
			],
			__METHOD__
		);
	}

	/**
		@brief Add newly undeleted article into the 'createdpageslist' SQL table.
	*/
	public static function onArticleRevisionUndeleted( $title, $revision, $oldPageID ) {
		if ( $revision->getParentId() != 0 ) {
			return; /* Not the first revision of a page */
		}

		DeferredUpdates::addCallableUpdate( function() use ( $title, $revision ) {
			self::add(
				User::newFromName( $revision->getUserText(), false ),
				$title,
				$revision->getTimestamp()
			);
		} );
	}

	/**
		@brief Rename the moved article in 'createdpageslist' SQL table.
	*/
	public static function onTitleMoveComplete( Title &$title, Title &$newTitle, User $user, $oldid, $newid, $reason, Revision $revision ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'createdpageslist',
			[
				'cpl_namespace' => $newTitle->getNamespace(),
				'cpl_title' => $newTitle->getDBKey()
			],
			[
				'cpl_namespace' => $title->getNamespace(),
				'cpl_title' => $title->getDBKey()
			],
			__METHOD__,
			[ 'IGNORE' ]
		);
	}

	/**
		@brief Scan latest edits of $user and update createdpageslist table.

		This function is mainly to detect pages which were created
		before Extension:CreatedPagesList was installed
		(first time someone's Special:CreatedPagesList is visited).

		New pages are normally added in onPageContentInsertComplete().
	*/
	public static function updateForUser( User $user ) {
		if ( wfReadOnly() ) {
			return; /* No big problem if CreatedPagesList displays stale data */
		}

		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			[
				'revision',
				'page'
			],
			[
				'page_namespace AS namespace',
				'page_title AS title',
				'rev_timestamp AS timestamp'
			],
			[
				'rev_user_text' => $user->getName(),
				'rev_parent_id' => 0, // This revision created a new page
			],
			__METHOD__,
			[
				'ORDER BY' => 'rev_timestamp DESC',
				'USE INDEX' => [
					'revision' => 'usertext_timestamp'
				]
			],
			[
				'page' => [ 'INNER JOIN', [
					'page_id=rev_page',
					'page_is_redirect' => 0,
					'page_namespace' => MWNamespace::getContentNamespaces()
				] ]
			]
		);
		if ( $res->numRows() == 0 ) {
			return; /* No new articles */
		}

		$dbw = wfGetDB( DB_MASTER );
		foreach ( $res as $row ) {
			self::add( $user,
				Title::makeTitle( $row->namespace, $row->title ),
				$row->timestamp,
				false /* Redirects are already filtered */
			);
		}
	}

	/**
		@brief Add page $title into the CreatedPagesList of $user.
		@param $timestamp String (MediaWiki timestamp).
		@param $isRedirect True/false if known, null to get form $title.
	*/
	protected static function add( User $user, Title $title, $timestamp, $isRedirect = null ) {
		if ( !MWNamespace::isContent( $title->getNamespace() ) ) {
			return; /* We only need articles, not templates, etc. */
		}

		if ( $isRedirect !== null ? $isRedirect : $title->isRedirect() ) {
			return; /* Redirects are not worthy */
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert(
			'createdpageslist',
			[
				'cpl_timestamp' => $dbw->timestamp( $timestamp ),
				'cpl_user' => $user->getId(),
				'cpl_user_text' => $user->getName(),
				'cpl_namespace' => $title->getNamespace(),
				'cpl_title' => $title->getDBKey()
			],
			__METHOD__,
			[ 'IGNORE' ]
		);
	}
}
