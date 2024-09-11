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
 * Checks [[Special:CreatedPagesList]] special page.
 */

use MediaWiki\MediaWikiServices;

/**
 * @covers SpecialCreatedPagesList
 * @group Database
 */
class SpecialCreatedPagesListTest extends SpecialPageTestBase {
	protected function newSpecialPage() {
		return new SpecialCreatedPagesList();
	}

	protected function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'createdpageslist';

		// LibXML (used by DomDocument) has trouble parsing certain parts of pages in MediaWiki 1.39+.
		// This is unrelated to Extension:CreatedPagesList, so we ignore the warnings caused by it.
		libxml_use_internal_errors( true );
	}

	protected function tearDown(): void {
		libxml_use_internal_errors( false );
	}

	/**
	 * Checks the form when Special:CreatedPagesList is opened without a parameter.
	 */
	public function testForm() {
		$dom = new DomDocument;
		$dom->loadHTML( $this->runSpecial() );

		$xpath = new DomXpath( $dom );
		$form = $xpath->query( '//form[contains(@action,"Special:CreatedPagesList")]' )->item( 0 );

		$this->assertNotNull( $form, 'Special:CreatedPagesList: <form> element not found' );

		$legend = $xpath->query( '//form/fieldset/legend', $form )->item( 0 );
		$this->assertNotNull( $legend, 'Special:CreatedPagesList: <legend> not found' );
		$this->assertEquals( '(createdpageslist)', $legend->textContent );

		$input = $xpath->query( '//input[@name="username"]', $form )->item( 0 );
		$this->assertNotNull( $input, 'Special:CreatedPagesList: <input name="username"/> not found' );

		$submit = $xpath->query( '//button[@type="submit"]', $form )->item( 0 );
		$this->assertNotNull( $submit, 'Special:CreatedPagesList: Submit button not found' );
		$this->assertEquals( '(createdpageslist-submit)', $submit->getAttribute( 'value' ) );
	}

	/**
	 * Checks the error message "there is no such user".
	 */
	public function testNoSuchUser() {
		$dom = new DomDocument;
		$dom->loadHTML( $this->runSpecial( 'ItIsHighlyUnlikelyThatSomeUserWouldChooseThisName' ) );

		$this->assertStringContainsString( '(createdpageslist-notfound)', $dom->textContent );
	}

	/**
	 * Checks how Special:CreatedPagesList prints the list of pages.
	 * @param bool $subpageHasUsername
	 * @dataProvider dataProviderShowPages
	 */
	public function testShowPages( $subpageHasUsername ) {
		/* Populate 'createdpagelist' table */
		$user = self::getTestSysop()->getUser();
		$pageNames = [
			'Test page 1',
			'Test page 2',
			'Test page 3'
		];

		// Pages must exist before the test, or else they wouldn't have a valid page_id.
		foreach ( $pageNames as $pageName ) {
			$this->getExistingTestPage( $pageName );
		}

		// Empty the table before the test.
		$this->truncateTable( 'createdpageslist' );

		// Populate the table with test data.
		if ( method_exists( MediaWikiServices::class, 'getConnectionProvider' ) ) {
			// MW 1.42+
			$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		} else {
			$dbw = wfGetDB( DB_PRIMARY );
		}
		foreach ( $pageNames as $pageName ) {
			$title = Title::newFromText( $pageName );

			$dbw->insert(
				'createdpageslist',
				[
					'cpl_timestamp' => $dbw->timestamp(),
					'cpl_actor' => $user->getActorId(),
					'cpl_page' => $title->getArticleId()
				],
				__METHOD__,
				[ 'IGNORE' ]
			);
		}

		/* Now test the contents of Special:CreatedPagesList */

		$html = $subpageHasUsername ?
			$this->runSpecial( $user->getName() ) :
			$this->runSpecial( '', [ 'username' => $user->getName() ] );

		$dom = new DomDocument;
		$dom->loadHTML( $html );

		$this->assertStringNotContainsString( '(createdpageslist-notfound)', $dom->textContent );

		$foundTitles = []; /* [ 'Title1' => 'HTML of link1', ... ] */
		$xpath = new DomXpath( $dom );
		foreach ( $xpath->query( '//li/a' ) as $link ) {
			$foundTitles[$link->textContent] = $link->ownerDocument->saveXML( $link );
		}

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		foreach ( $pageNames as $expectedTitle ) {
			$this->assertArrayHasKey( $expectedTitle, $foundTitles,
				"Special:CreatedPagesList: expected page [$expectedTitle] wasn't listed" );

			$expectedHtml = $linkRenderer->makeLink( Title::newFromText( $expectedTitle ) );
			$this->assertEquals( $expectedHtml, $foundTitles[$expectedTitle] );
		}
	}

	/**
	 * Provide datasets for testShowPages() runs.
	 * @return array
	 */
	public function dataProviderShowPages() {
		return [
			'subpage' => [ true ],
			'querystring' => [ false ]
		];
	}

	/**
	 * Render Special:CreatedPagesList.
	 * @param string $param Subpage, e.g. 'User1' for [[Special:CreatedPagesList/User1]].
	 * @param array $query Query string parameters.
	 * @return HTML of the result.
	 */
	public function runSpecial( $param = '', array $query = [] ) {
		$this->setUserLang( 'qqx' );

		[ $html, ] = $this->executeSpecialPage(
			$param,
			new FauxRequest( $query, false ) // GET request
		);

		return $html;
	}
}
