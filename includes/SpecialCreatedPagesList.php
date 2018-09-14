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

	/** @var User Author of the pages we need to list */
	protected $user = null;

	function __construct( $name = 'CreatedPagesList' ) {
		parent::__construct( $name );
	}

	function isSyndicated() {
		return false;
	}
	function getOrderFields() {
		return [ 'cpl_timestamp' ];
	}

	function sortDescending() {
		return true;
	}

	public function isCacheable() {
		return false;
	}

	function execute( $param ) {
		$username = $this->getRequest()->getVal( 'username', $param );
		if ( !$username ) {
			$this->setHeaders();
			$this->outputHeader();
			$this->showForm();

			return;
		}

		$this->user = User::newFromName( $username, false );
		parent::execute( $param );
	}

	function linkParameters() {
		return $this->user ? [ 'username' => $this->user->getName() ] : [];
	}

	protected function showEmptyText() {
		$this->showForm( Status::newFatal( 'createdpageslist-notfound' ) );
	}

	protected function showForm( $error = false ) {
		$formDescriptor = [
			'username' => [
				'type' => 'user',
				'name' => 'username',
				'id' => 'username',
				'size' => 50,
				'label-message' => 'createdpageslist-username'
			]
		];
		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
;
		$htmlForm
			->setWrapperLegendMsg( 'createdpageslist' )
			->setSubmitTextMsg( 'createdpageslist-submit' )
			->setAction( $this->getPageTitle()->getFullURL() )
			->setMethod( 'get' )
			->prepareForm()
			->displayForm( $error );
	}

	function getQueryInfo() {
		return [
			'tables' => [ 'createdpageslist' ],
			'fields' => [
				'cpl_namespace AS namespace',
				'cpl_title AS title'
			],
			'conds' => [
				'cpl_user_text' => str_replace( '_', ' ', $this->user->getName() )
			],
			'options' => [
				'USE INDEX' => 'createdpageslist_user_timestamp'
			],
			'join_conds' => []
		];
	}
}
