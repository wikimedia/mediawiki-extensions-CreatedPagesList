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
	@brief Checks [[Special:CreatedPagesList]] special page.
	@group Database
*/

/**
	@covers SpecialCreatedPagesList
*/
class SpecialCreatedPagesListTest extends SpecialPageTestBase
{
	protected function newSpecialPage() {
		return new SpecialCreatedPagesList();
	}

	public function needsDB() {
		return true;
	}

	protected function setUp() {
		parent::setUp();
		$this->tablesUsed[] = 'createdpageslist';
	}

	/**
		@brief Checks the form when Special:CreatedPagesList is opened without a parameter.
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

		$label = $xpath->query( '//label[@for="username"]', $form )->item( 0 );
		$this->assertNotNull( $label, 'Special:CreatedPagesList: <label for="username"> not found' );
		$this->assertEquals( '(createdpageslist-username)', $label->textContent );

		$submit = $xpath->query( '//input[@type="submit"]', $form )->item( 0 );
		$this->assertNotNull( $submit, 'Special:CreatedPagesList: Submit button not found' );
		$this->assertEquals( '(createdpageslist-submit)', $submit->getAttribute( 'value' ) );
	}

	/**
		@brief Checks the error message "there is no such user".
	*/
	public function testNoSuchUser() {
		$dom = new DomDocument;
		$dom->loadHTML( $this->runSpecial( 'ItIsHighlyUnlikelyThatSomeUserWouldChooseThisName' ) );

		$this->assertContains( '(createdpageslist-notfound)', $dom->textContent );
	}

	/**
		@brief Checks how Special:CreatedPagesList prints the list of pages.
		@testWith	[ "subpage", [ true ] ]
				[ "querystring", [ false ] ]
	*/
	public function testShowPages( $subpageHasUsername ) {
		/* Populate 'createdpagelist' table */
		$user = User::newFromName( 'UTSysop' ); // Created in MediaWikiTestCase
		$titles = [
			'Test page 1',
			'Test page 2',
			'Test page 3'
		];

		$dbw = wfGetDB( DB_MASTER );
		foreach ( $titles as $title ) {
			$titleObj = Title::newFromText( $title );

			$dbw->insert(
				'createdpageslist',
				[
					'cpl_timestamp' => $dbw->timestamp(),
					'cpl_user' => $user->getId(),
					'cpl_user_text' => $user->getName(),
					'cpl_namespace' => $titleObj->getNamespace(),
					'cpl_title' => $titleObj->getDBKey()
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

		$this->assertNotContains( '(createdpageslist-notfound)', $dom->textContent );

		$foundTitles = []; /* [ 'Title1' => 'HTML of link1', ... ] */
		$xpath = new DomXpath( $dom );
		foreach ( $xpath->query( '//li/a' ) as $link ) {
			$foundTitles[$link->textContent] = $link->ownerDocument->saveXML( $link );
		}

		foreach ( $titles as $expectedTitle ) {
			$this->assertArrayHasKey( $expectedTitle, $foundTitles,
				"Special:CreatedPagesList: expected page [$expectedTitle] wasn't listed" );

			$expectedHtml = Linker::link( Title::newFromText( $expectedTitle ) );
			$this->assertEquals( $expectedHtml, $foundTitles[$expectedTitle] );
		}
	}

	/**
		@brief Render Special:CreatedPagesList.
		@param $param Subpage, e.g. 'User1' for [[Special:CreatedPagesList/User1]].
		@param $query Query string parameters..
		@returns HTML of the result.
	*/
	public function runSpecial( $param = '', array $query = [] ) {
		global $wgLang; /* HTMLForm sometimes calls wfMessage() without context  */
		$wgLang = Language::factory( 'qqx' );

		list( $html, ) = $this->executeSpecialPage(
			$param,
			new FauxRequest( $query, false ), // GET request
			$wgLang
		);
		return $html;
	}
}
