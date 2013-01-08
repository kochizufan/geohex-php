<?php
# encode.txt and decode.txt are made from
# https://groups.google.com/group/geohex/web/test-casev3

set_include_path('../');
require_once('GeoHex.php');

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
	array(
        'casename' => 'coord2HEX',
        'mode'     => 'coordinate -> HEX',
        'input'    => 'level, lat, lon',
        'output'   => 'zone.code (expectaion)',
        'logic'    => function($test_case) {
            $level = $test_case[0];
            $lat   = $test_case[1];
            $lon   = $test_case[2];
            $code  = $test_case[3];
            $g = new Geohex(array(
                'latitude' => $lat,
                'longitude' => $lon,
                'level' => $level,
            ));

            return array(
                'err'     => $g->code === $code ? 0 : 1,
                'message' => $level . ", " . $lat . ", " . $lon . " ____ " . $g->code . " (" . $code . ")"
            );
        }
    ),
    array(
        'casename' => 'code2XY',
        'mode'     => 'code -> XY',
        'input'    => 'code',
        'output'   => 'zone.x, zone.y (expectaion)',
        'logic'    => function($test_case) {
            $code  = $test_case[0];
            $X     = $test_case[1];
            $Y     = $test_case[2];
            $g = new Geohex();
            $g->setCode($code);
            $h_max = pow(3,strlen($code));

            return array(
                'err'     => $g->x == $X && $g->y == $Y ? 0 : 1,
                'message' => $code . " ____ " . $g->x . ", " . $g->y . " (" . $X . ", " . $Y . ")"
            );
        }
    ),
    array(
        'casename' => 'XY2HEX',
        'mode'     => 'XY -> HEX',
        'input'    => 'level, X, Y',
        'output'   => 'zone.code (expectaion)',
        'logic'    => function($test_case) {
            $level = $test_case[0];
            $X     = $test_case[1];
            $Y     = $test_case[2];
            $code  = $test_case[3];
            $g = new Geohex(array(
                'x'     => $X,
                'y'     => $Y,
                'level' => $level,
            ));

            return array(
                'err'     => $g->code == $code ? 0 : 1,
                'message' => $level . ", " . $X . ", " . $Y . " ____ " . $g->code . " (" . $code . ")"
            );
        }
    ), 
    array(
        'casename' => 'Rect2XYs',
        'mode'     => 'Rect -> XYs',
        'input'    => 'South, West, North, East, Level, Buffer',
        'output'   => 'List of XYs (expectaion)',
        'logic'    => function($test_case) {
            $south  = $test_case[0];
            $west   = $test_case[1];
            $north  = $test_case[2];
            $east   = $test_case[3];
            $level  = $test_case[4];
            $buffer = $test_case[5];
            $expect = $test_case[6];
            $result = Geohex::getXYListByRect($south, $west, $north, $east, $level , $buffer);

            $errMsg = '';

            foreach ($expect as $e_idx => $e_dat) {
                $e_exist = false;
                foreach ($result as $r_idx => $r_dat) {
                    if ($e_dat->x == $r_dat['x'] && $e_dat->y == $r_dat['y']) {
                        $e_exist = true;
                        array_splice($result,$r_idx,1);
                        break;
                    }
                }
                if (!$e_exist) {
                    $errMsg .= "\n" . 'X:' . $e_dat->x . ' and Y:' . $e_dat->y . ' is included in expected but not in result.';
                }
            }

            foreach ($result as $r_idx => $r_dat) {
                $errMsg .= "\n" . 'X:' . $r_dat['x'] . ' and Y:' . $r_dat['y'] . ' is included in result but not in expected.';
            }

            return array(
                'err'     => $errMsg === '' ? 0 : 1,
                'message' => 'SW: ' . $south . ',' . $west . " NE: " . $north . ',' . $east . " Level: " . $level . " Buffer " . $buffer . $errMsg
            );
        }
    ),
);

foreach ($testMode as $testMeta) {
    $succ_count = 0;
    $fail_count = 0;

    $testJson  = 'hex_v3.02_test_' . $testMeta['casename'] . '.json';
    $testLogic = $testMeta['logic'];

    $handle    = fopen('./' . $testJson, 'r'); 
    $testCases = json_decode(fread($handle, filesize($testJson)));
    fclose($handle);

    print "TEST_CASE: " . $testJson . "\n";
    print "VERSION: " . GeoHex::VERSION . "\n";
    print "MODE: " . $testMeta['mode'] . "\n";
    print "TOTAL_COUNT: " . count($testCases) . "\n";
    print "---------------------------------------------------------------------------------------\n";
    print "INPUT: " . $testMeta['input'] . " ____ OUTPUT: " . $testMeta['output'] . "\n";
    print "---------------------------------------------------------------------------------------\n";

    foreach ($testCases as $testCase) {
        $result = $testLogic($testCase);
        if ($result['err']) {
            $fail_count++;
            print "FAILED TESTCASE: \n";
            print "  " . $result['message'] . "\n";
        } else {
            $succ_count++;
        }
    }

    print "Result: succeed: " . $succ_count . " fail: " . $fail_count . "\n\n---------------------------------------------------------------------------------------\n";
}

print "All tests done\n";
