<?php
# encode.txt and decode.txt are made from
# https://groups.google.com/group/geohex/web/test-casev3

set_include_path('../');
require_once('GeoHex.php');

$succ_count = 0;
$fail_count = 0;

$testMode = array(
    array(
       	'casename' => 'code2HEX',
        'mode'     => 'code -> HEX',
        'input'    => 'code',
        'output'   => 'zone.lat, zone.lon (expectaion)',
        'logic'    => function($test_case) {
            $lat  = $test_case[1];
            $lon  = $test_case[2];
            $code = $test_case[0];
            $g = new Geohex();
            $g->setCode($code);

            return array(
                'err'     => (abs($g->latitude - $lat) < 0.0000000001 && abs($g->longitude - $lon) < 0.0000000001) ? 0 : 1,
                'message' => $code . " ____ " . $g->latitude . ", " . $g->longitude . " (" . $lat . ", " . $lon . ")"
            );
        }
    ),
//	array()
);

foreach ($testMode as $testMeta) {
    $testJson  = './hex_v3.02_test_' . $testMeta['casename'] . '.json';
    $testLogic = $testMeta['logic'];

    $handle    = fopen($testJson, 'r'); 
    $testCases = json_decode(fread($handle, filesize($testJson)));
    fclose($handle);

    foreach ($testCases as $testCase) {
        $result = $testLogic($testCase);
        if ($result['err']) {
            $fail_count++;
            print "FAILED TESTCASE: \n";
            print "  " . $result['message'];
        } else {
            $succ_count++;
        }
    }
}























/*# encode test
$fp = fopen('encode.txt', 'r');
while (!feof($fp)) {
	$line = fgets($fp);
	if (strlen($line) == 0) {
		continue; // for the last line
	}
	list($lat, $lon, $level, $code) = explode(",", chop($line));
	$g = new Geohex(array(
		'latitude' => $lat,
		'longitude' => $lon,
		'level' => $level,
	));

	if ($g->code === $code) {
		$succ_count ++;
	} else {
		print "FAILED TESTCASE: " . chop($line) . "\n";
		print "  Expected:" . $code . " Actual:" . $zone["code"] . "\n";
		$fail_count ++;
	}
}

# decode test
$fp = fopen('decode.txt', 'r');
while (!feof($fp)) {
	$line = fgets($fp);
	if (strlen($line) == 0) {
		continue; // for the last line
	}
	list($lat, $lon, $level, $code) = explode(",", chop($line));
	$g = new Geohex();
	$g->setCode($code);
	$g2 = new Geohex(array(
		"latitude" => $g->latitude,
		"longitude" => $g->longitude,
		"level" => $g->level,
	));

	if ($g2->code === $code) {
		$succ_count ++;
	} else {
		print "FAILED TESTCASE: " . chop($line) . "\n";
		print "  Expected:" . $code . " Actual:" . $g2->code . "\n";
		$fail_count ++;
	}
}*/

print "Result: succeed:" . $succ_count . " fail:" . $fail_count . "\n";
