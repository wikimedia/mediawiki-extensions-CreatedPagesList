<?php

/**
 * @file
 * Backward compatibility file to support require_once() in LocalSettings.
 *
 * Modern syntax (to enable CreatedPagesList in LocalSettings.php) is
 * wfLoadExtension( 'CreatedPagesList' );
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'CreatedPagesList' );
} else {
	die( 'This version of the CreatedPagesList extension requires MediaWiki 1.35+' );
}
