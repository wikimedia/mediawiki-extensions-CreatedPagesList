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
	@file
	@brief Methods to keep 'createdpageslist' SQL table up to date.
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
			'page_namespace AS namespace',
			'page_title AS title',
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
					'cpl_namespace' => $row->namespace,
					'cpl_title' => $row->title,
					'cpl_timestamp' => $row->timestamp,
					'cpl_user_text' => $row->rev_user_text,
					'cpl_user' => $row->rev_user ?? 0
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

		if ( $isRedirect !== null ? $isRedirect : $title->isRedirect() ) {
			return; /* Redirects are not worthy */
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->replace(
			'createdpageslist',
			[ [ 'cpl_namespace', 'cpl_title' ] ],
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

	/** Delete page $title from the CreatedPagesList. */
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

	/** Rename page $title in the CreatedPagesList. */
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
