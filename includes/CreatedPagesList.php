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
 * Methods to keep 'createdpageslist' SQL table up to date.
 */

use MediaWiki\MediaWikiServices;

class CreatedPagesList {

	/**
	 * Update createdpageslist table.
	 * This is called from update.php.
	 */
	public static function recalculateSqlTable() {
		$dbw = wfGetDB( DB_MASTER );

		$dbw->startAtomic( __METHOD__ );
		$dbw->delete( 'createdpageslist', '*', __METHOD__ );

		$actorQuery = ActorMigration::newMigration()->getJoin( 'rev_user' );

		$tables = array_merge( $actorQuery['tables'], [
			'page',
			'revision'
		] );
		$fields = array_merge( $actorQuery['fields'], [
			'page_id AS page',
			'rev_timestamp AS timestamp'
		] );

		$res = $dbw->select(
			$tables,
			$fields,
			[
				'page_is_redirect' => 0,
				'page_namespace' => MediaWikiServices::getInstance()
					->getNamespaceInfo()
					->getContentNamespaces(),
				'rev_page=page_id',
				'rev_parent_id' => 0// First revision on the page
			],
			__METHOD__,
			[
				'DISTINCT',
				'INDEX' => [
					'page' => 'page_redirect_namespace_len',
					'revision' => 'rev_page_id'
				]
			],
			$actorQuery['joins']
		);

		foreach ( $res as $row ) {
			$dbw->insert(
				'createdpageslist',
				[
					'cpl_page' => $row->page,
					'cpl_timestamp' => $row->timestamp,
					'cpl_actor' => $row->rev_actor ?? 0
				],
				__METHOD__
			);
		}

		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Add page $title into the CreatedPagesList of $user.
	 * @param User $user
	 * @param Title $title
	 * @param string $timestamp MediaWiki timestamp.
	 * @param bool|null $isRedirect True/false if known, null to get from $title.
	 */
	public static function add( User $user, Title $title, $timestamp, $isRedirect = null ) {
		if ( !MediaWikiServices::getInstance()->getNamespaceInfo()->isContent( $title->getNamespace() ) ) {
			return; /* We only need articles, not templates, etc. */
		}

		if ( $isRedirect ?? $title->isRedirect() ) {
			return; /* Redirects are not worthy */
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->replace(
			'createdpageslist',
			[ [ 'cpl_page' ] ],
			[
				'cpl_timestamp' => $dbw->timestamp( $timestamp ),
				'cpl_actor' => $user->getActorId(),
				'cpl_page' => $title->getArticleId()
			],
			__METHOD__
		);
	}

	/**
	 * Delete page $title from the CreatedPagesList.
	 * @param Title $title
	 */
	public static function delete( Title $title ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'createdpageslist',
			[
				'cpl_page' => $title->getArticleId()
			],
			__METHOD__
		);
	}
}
