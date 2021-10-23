<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

if ( getenv( 'PHAN_CHECK_DEPRECATED' ) ) {
	# Warn about the use of @deprecated methods, etc.
	$cfg['suppress_issue_types'] = array_filter( $cfg['suppress_issue_types'], static function ( $issue ) {
		return strpos( $issue, 'PhanDeprecated' ) === false;
	} );
}

return $cfg;
