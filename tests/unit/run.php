<?php
/**
 * Dependency-free unit test runner.
 *
 * Usage: php tests/unit/run.php [filter]
 *
 * Loads every tests/unit/*Test.php file. Each file returns an array of
 * test name => callable; a test fails by throwing (see utmatra_assert*()).
 * The optional filter runs only tests whose "File::name" contains it.
 * Exits 1 when any test fails.
 *
 * @since 1.1.0
 *
 * @package Utmatra\Tests
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/assert.php';

$utmatra_filter = $argv[1] ?? '';
$utmatra_passed = 0;
$utmatra_failed = array();

$utmatra_files = glob( __DIR__ . '/*Test.php' );
sort( $utmatra_files );

foreach ( $utmatra_files as $utmatra_file ) {
	$utmatra_cases = require $utmatra_file;
	foreach ( $utmatra_cases as $utmatra_name => $utmatra_case ) {
		$utmatra_label = basename( $utmatra_file, '.php' ) . '::' . $utmatra_name;
		if ( '' !== $utmatra_filter && false === strpos( $utmatra_label, $utmatra_filter ) ) {
			continue;
		}

		utmatra_test_reset();
		try {
			$utmatra_case();
			++$utmatra_passed;
			echo "ok      {$utmatra_label}\n";
		} catch ( Throwable $utmatra_error ) {
			$utmatra_failed[] = $utmatra_label;
			echo "FAILED  {$utmatra_label}\n        " . $utmatra_error->getMessage() . "\n";
		}
	}
}

echo "\n" . $utmatra_passed . ' passed, ' . count( $utmatra_failed ) . " failed\n";
exit( array() === $utmatra_failed ? 0 : 1 );
