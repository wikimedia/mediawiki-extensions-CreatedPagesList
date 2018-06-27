<?php

/*
	Extension:CreatedPagesList - MediaWiki extension.
	Copyright (C) 2012-2018 Edward Chernenko.

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
	@brief Implements [[Special:CreatedPagesList]].
*/

class SpecialCreatedPagesList extends PageQueryPage {

	protected $username = null; /**< Name of user who created pages that we need to list. */

    	function __construct( $name = 'CreatedPagesList' ) {
		parent::__construct( $name );
	}

	function isSyndicated() {
		return false;
	}
	function getOrderFields() {
		return [ 'rev_timestamp' ];
	}

	function sortDescending() {
		return true;
	}

	public function isCacheable() {
		return false;
	}

	function execute( $param ) {
		$this->username = $this->getRequest()->getVal( 'username', $param );
		if ( $this->username ) {
			parent::execute( $param );
		}
		else {
			$this->setHeaders();
			$this->outputHeader();
			$this->showForm();
		}
	}

	function linkParameters() {
		return [ 'username' => $this->username ];
	}

	protected function showEmptyText() {
		$this->showForm( Status::newFatal( 'createdpageslist-notfound' ) );
	}

	protected function showForm( $error = false ) {
		$form = new HTMLForm( [
			'username' => [
				'type' => 'text',
				'name' => 'username',
				'id' => 'username',
				'size' => 50,
				'label-message' => 'createdpageslist-username'
			]
		], $this->getContext() );
;
		$form->setWrapperLegendMsg( 'createdpageslist' );
		$form->setSubmitTextMsg( 'createdpageslist-submit' );
		$form->setAction( $this->getPageTitle()->getFullURL() );
		$form->setMethod( 'get' );

		$form->prepareForm()->displayForm( $error );
	}

	function getQueryInfo() {
		return [
			'tables' => [ 'revision', 'page' ],
			'fields' => [
				'page_namespace AS namespace',
				'page_title AS title'
			],
			'conds' => [
				'rev_user_text' => str_replace( '_', ' ', $this->username ),
				'rev_parent_id' => 0
			],
			'options' => [
				'USE INDEX' => [
					'revision' => 'rev_newpagesbyuser'
				]
			],
			'join_conds' => [
				'page' => [ 'INNER JOIN', [
					'page_id=rev_page',
					'page_is_redirect' => 0,
					'page_namespace' => MWNamespace::getContentNamespaces()
				] ]
			]
		];
	}
}

