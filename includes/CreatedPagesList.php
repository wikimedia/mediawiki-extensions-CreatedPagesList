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
	@brief Methods to keep 'createdpageslist' SQL table up to date.
*/

class CreatedPagesList {

	/**
	 * @brief Update createdpageslist table.
	 * This is called from update.php.
	 */
	public static function recalculateSqlTable() {
		$dbw = wfGetDB( DB_MASTER );

		$dbw->startAtomic( __METHOD__ );
		$dbw->delete( 'createdpageslist', '*', __METHOD__ );

		$dbw->insertSelect(
			'createdpageslist',
			[
				'page',
				'revision',
			],
			[
				'cpl_namespace' => 'page_namespace',
				'cpl_title' => 'page_title',
				'cpl_timestamp' => 'MIN(rev_timestamp)', // First revision on the page
				'cpl_user_text' => 'rev_user_text',
				'cpl_user' => 'rev_user'
			],
			[
				'page_is_redirect' => 0,
				'page_namespace' => MWNamespace::getContentNamespaces(),

				// insertSelect() in MediaWiki 1.27 doesn't have $joinConds parameter
				'rev_page=page_id'
			],
			__METHOD__,
			[],
			[
				'GROUP BY' => 'rev_page',
				'INDEX' => [
					'page' => 'page_redirect_namespace_len',
					'revision' => 'rev_page_id'
				]
			]
		);
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * @brief Add page $title into the CreatedPagesList of $user.
	 * @param User $user
	 * @param Title $title
	 * @param string $timestamp MediaWiki timestamp.
	 * @param bool|null $isRedirect True/false if known, null to get from $title.
	 */
	public static function add( User $user, Title $title, $timestamp, $isRedirect = null ) {
		if ( !MWNamespace::isContent( $title->getNamespace() ) ) {
			return; /* We only need articles, not templates, etc. */
		}

		if ( $isRedirect !== null ? $isRedirect : $title->isRedirect() ) {
			return; /* Redirects are not worthy */
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->replace(
			'createdpageslist',
			[ 'cpl_namespace', 'cpl_title' ],
			[
				'cpl_timestamp' => $dbw->timestamp( $timestamp ),
				'cpl_user' => $user->getId(),
				'cpl_user_text' => $user->getName(),
				'cpl_namespace' => $title->getNamespace(),
				'cpl_title' => $title->getDBKey()
			],
			__METHOD__
		);
	}

	/** @brief Delete page $title from the CreatedPagesList. */
	public static function delete( Title $title ) {
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

	/** @brief Rename page $title in the CreatedPagesList. */
	public static function move( Title $title, Title $newTitle ) {
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
}
