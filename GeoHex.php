<?php

/**
 * GeoHex
 * GeoHex by @sa2da (http://geogames.net) is licensed under Creative Commons BY-SA 2.1 Japan License.
 *
 * @category  GeoHex
 * @package   GeoHex
 * @copyright Copyright (c) 2011 Tonthidot Corporation. (http://www.tonchidot.com)
 * @license   http://creativecommons.org/licenses/by-sa/2.1/jp/
 * @author    KITA, Junpei
 * @author    OHTSUKA, Ko-hei
 * @version   $Id
 */
class GeoHex
{
    const VERSION = '3.025';

    const H_KEY    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    const H_BASE   = 20037508.34;
    const H_MAXLAT = 85.051128514;
    const H_DEG    = 0.5235987755983;  # pi() / 30 / 180
    const H_K      = 0.57735026918963; # tan(H_DEG)

    private static $_zoneCache  = array();
    private static $_cacheLimit = 100;
    public  static $cache_on    = true;

    public $x = null;
    public $y = null;
    public $code = null;
    public $level = 7;
    public $lat = null;
    public $lon = null;
    public $coords = array();

    /**
     * construct
     *
     * @param array(
     *     'code'  => String,
     *     'level' => Integer,
     *     'lat'   => Float,
     *     'lon'   => Float
     * )
     * @return GeoHex
     * @throws GeoHex_Exception
     */
    public function __construct()
    {
        switch (func_num_args()) {
        case 3:
            $args = func_get_args();
            $this->lat = $args[0];
            $this->lon = $args[1];
            $this->level = $args[2];
            break;
        case 2:
            $args = func_get_args();
            $this->lat = $args[0];
            $this->lon = $args[1];
            break;
        case 1:
            $arg = func_get_arg(0);

            if (is_string($arg)) {
                $this->code = $arg;
            }

            else if (is_array($arg)) {
                $keys = array('code', 'level', 'lat', 'lon','x','y');

                foreach ($keys as $key) {
                    if (isset($arg[$key])) {
                        $this->$key = $arg[$key];
                    }
                }
            }
            break;
        }

        if (isset($this->lat) && isset($this->lon))
        {
            $this->setLocation($this->lat, $this->lon, $this->level);
        }
        else if (isset($this->x) && isset($this->y))
        {
            $this->setXY($this->x, $this->y, $this->level);
        }
        else if (isset($this->code)) {
            $this->setCode($this->code);
        }
    }

    /**
     * public
     */

    public function setLocation($lat, $lon, $_level = null)
    {
        $this->lat = $lat;
        $this->lon = $lon;

        if (isset($_level)) {
            $this->level = $_level;
        }

        if (!isset($this->lat) ||
            !isset($this->lon) ||
            !isset($this->level)
        ) {
            return $this;
        }

        $zone = self::getZoneByLocation($this->lat, $this->lon, $this->level);

        $this->x = $zone['x'];
        $this->y = $zone['y'];
        $this->code = $zone['code'];

        return $this->setCoords();
    }
    public function setXY($x, $y, $_level = null)
    {
        $this->x = $x;
        $this->y = $y;

        if (isset($_level)) {
            $this->level = $_level;
        }

        if (!isset($this->x) ||
            !isset($this->y) ||
            !isset($this->level)
        ) {
            return $this;
        }

        $zone = self::getZoneByXY($this->x, $this->y, $this->level);

        //$this->x = $zone['x'];
        //$this->y = $zone['y'];
        $this->lat = $zone['lat'];
        $this->lon = $zone['lon'];
        $this->code      = $zone['code'];

        return $this->setCoords();
    }
    public function setCode($code)
    {
        $zone = self::getZoneByCode($code);

        $this->x = $zone['x'];
        $this->y = $zone['y'];
        $this->code = $zone['code'];
        $this->level = $zone['level'];
        $this->lat = $zone['lat'];
        $this->lon = $zone['lon'];

        return $this->setCoords();
    }
    public function setLevel($_level)
    {
        return $this->setLocation(
            $this->lat, $this->lon, $_level);
    }
    public function setLat($lat)
    {
        return $this->setLocation($lat, $this->lon);
    }
    public function setLon($lon)
    {
        return $this->setLocation($this->lat, $lon);
    }

    //Cache API
    private static function _getCachedZone($code) {
        if (!self::$cache_on) return null;
        if (!empty(self::$_zoneCache[$code])) {
            $zone = self::$_zoneCache[$code];

            //優先順位を後ろに持ってくるため
            unset(self::$_zoneCache[$code]);
            self::$_zoneCache[$code] = $zone;

            return $zone;
        }
        return null;
    }

    private static function _setCachedZone($zone) {
        if (!self::$cache_on) return $zone;
        $code = $zone['code'];
        if (!empty(self::$_zoneCache[$code])) {
            unset(self::$_zoneCache[$code]);
        }
        self::$_zoneCache[$code] = $zone;

        while (count(self::$_zoneCache) > self::$_cacheLimit) {
            array_shift(self::$_zoneCache);
        }
        return $zone;
    }

    /**
     * public static
     */
    public static function getZoneByLocation($lat, $lon, $_level) {
        $xy   = self::getXYByLocation($lat, $lon, $_level);
        $zone = self::getZoneByXY($xy['x'], $xy['y'], $_level);
        return $zone;
    }

    public static function getZoneByCode($_code) {
        $xy     = self::getXYByCode($_code);
        $_level = strlen($_code) - 2;
        $zone   = self::getZoneByXY($xy['x'], $xy['y'], $_level);
        return $zone;
    }

    public static function getXYByLocation($lat, $lon, $_level) {
        $h_size   = self::_calcHexSize($_level);
        $z_xy     = self::_loc2xy($lon, $lat);
        $lon_grid = $z_xy['x'];
        $lat_grid = $z_xy['y'];
        $unit_x   = 6 * $h_size;
        $unit_y   = 6 * $h_size * self::H_K;
        $h_pos_x  = ($lon_grid + $lat_grid / self::H_K) / $unit_x;
        $h_pos_y  = ($lat_grid - self::H_K * $lon_grid) / $unit_y;
        $h_x_0    = floor($h_pos_x);
        $h_y_0    = floor($h_pos_y);
        $h_x_q    = $h_pos_x - $h_x_0;
        $h_y_q    = $h_pos_y - $h_y_0;
        $h_x      = round($h_pos_x);
        $h_y      = round($h_pos_y);

        if ($h_y_q > -$h_x_q + 1) {
            if (($h_y_q < 2 * $h_x_q) && ($h_y_q > 0.5 * $h_x_q)) {
                $h_x = $h_x_0 + 1;
                $h_y = $h_y_0 + 1;
            }
        }

        else if ($h_y_q < -$h_x_q + 1) {
            if (($h_y_q > (2 * $h_x_q) - 1) && ($h_y_q < (0.5 * $h_x_q) + 0.5)) {
                $h_x = $h_x_0;
                $h_y = $h_y_0;
            }
        }

        $inner_xy = self::_adjustXY($h_x,$h_y,$_level);
        $h_x = $inner_xy['x'];
        $h_y = $inner_xy['y'];

        //if ($inner_xy['rev']) $z_loc_x = 180;
        return array(
            'x' => $h_x,
            'y' => $h_y
        );
    }

    public static function getXYByCode($code) {
        //$ret = self::_getCachedZone($code);
        //if ($ret != null) {
        //    return $ret;
        //}

        $_level = strlen($code) - 2;
        $h_size = self::_calcHexSize($_level);
        $unit_x = 6 * $h_size;
        $unit_y = 6 * $h_size * self::H_K;
        $h_x    = 0;
        $h_y    = 0;
        $h_dec9 = strpos(self::H_KEY, substr($code, 0, 1)) * 30 + strpos(self::H_KEY, substr($code, 1, 1)) . substr($code, 2);

        if (preg_match('/[15]/', substr($h_dec9, 0, 1)) &&
            preg_match('/[^125]/', substr($h_dec9, 1, 1)) &&
            preg_match('/[^125]/', substr($h_dec9, 2, 1))
        ) {
            if (substr($h_dec9, 0, 1) === '5') {
                $h_dec9 = '7' . substr($h_dec9, 1, strlen($h_dec9));
            }
            
            else if (substr($h_dec9, 0, 1) === '1') {
                $h_dec9 = '3' . substr($h_dec9, 1, strlen($h_dec9));
            }
        }

        $d9xlen = strlen($h_dec9);

        for ($i = 0; $i < $_level + 3 - $d9xlen; $i++) {
            $h_dec9 = '0' . $h_dec9;
            $d9xlen++;
        }

        $h_dec3 = '';

        for ($i = 0; $i < $d9xlen; $i++) {
            $h_dec0 = base_convert(substr($h_dec9, $i, 1), 10, 3);

            if (is_null($h_dec0)) {
                $h_dec3 .= "00";
            }
            
            else if (strlen($h_dec0) === 1) {
                $h_dec3 .= '0';
            }

            $h_dec3 .= $h_dec0;
        }

        $h_decx = array();
        $h_decy = array();

        for ($i = 0; $i < strlen($h_dec3) / 2; $i++) {
            $h_decx[$i] = substr($h_dec3, $i * 2, 1);
            $h_decy[$i] = substr($h_dec3, $i * 2 + 1, 1);
        }

        for ($i = 0; $i <= $_level + 2; $i++) {
            $h_pow = pow(3, $_level + 2 - $i);

            if ((int) $h_decx[$i] === 0) {
                $h_x -= $h_pow;
            }
            
            else if ((int) $h_decx[$i] === 2) {
                $h_x += $h_pow;
            }

            if ((int) $h_decy[$i] === 0) {
                $h_y -= $h_pow;

            }
            
            else if ((int) $h_decy[$i] === 2) {
                $h_y += $h_pow;
            }
        }

        $inner_xy = self::_adjustXY( $h_x, $h_y, $_level );
        $h_x = $inner_xy['x'];
        $h_y = $inner_xy['y'];

        return array(
            'x' => $h_x,
            'y' => $h_y
        );
    }

    public static function getZoneByXY($_x, $_y, $_level) {
        $h_size   = self::_calcHexSize($_level);

        $h_x = $_x;
        $h_y = $_y;

        $unit_x = 6 * $h_size;
        $unit_y = 6 * $h_size * self::H_K;

        $h_lat = (self::H_K * $h_x * $unit_x + $h_y * $unit_y) / 2;
        $h_lon = ($h_lat - $h_y * $unit_y) / self::H_K;

        $z_loc = self::_xy2loc($h_lon, $h_lat);
        $z_loc_x = $z_loc['lon'];
        $z_loc_y = $z_loc['lat'];

        $max_hsteps = pow(3, $_level + 2);
        $hsteps     = abs($h_x - $h_y);
    
        if ( $hsteps == $max_hsteps ) {
            if ( $h_x > $h_y ) {
                $tmp = $h_x;
                $h_x = $h_y;
                $h_y = $tmp;
            }
            $z_loc_x = -180;
        }

        $h_code  = "";
        $code3_x = array();
        $code3_y = array();
        $code3   = "";
        $code9   = "";
        $mod_x   = $h_x;
        $mod_y   = $h_y;

        for ( $i = 0; $i <= $_level + 2 ; $i++ ) {
            $h_pow = pow( 3, $_level + 2 - $i );
            if ( $mod_x >= ceil($h_pow/2) ) {
                $code3_x[$i] = 2;
                $mod_x -= $h_pow;
            } else if ( $mod_x <= -ceil($h_pow/2) ) { 
                $code3_x[$i] = 0;
                $mod_x += $h_pow;
            } else {
                $code3_x[$i] = 1;
            }
            if ( $mod_y >= ceil($h_pow/2) ) {
                $code3_y[$i] = 2;
                $mod_y -= $h_pow;
            } else if ( $mod_y <= -ceil($h_pow/2) ) {
                $code3_y[$i] = 0;
                $mod_y += $h_pow;
            } else {
                $code3_y[$i] = 1;
            }

            if ( $i==2 && ($z_loc_x == -180 || $z_loc_x >= 0) ) {
                if ($code3_x[0] == 2 && $code3_y[0] == 1 && $code3_x[1] == $code3_y[1] && $code3_x[2] == $code3_y[2]) {
                    $code3_x[0]=1;
                    $code3_y[0]=2;
                } else if ( $code3_x[0] == 1 && $code3_y[0] == 0 && $code3_x[1] == $code3_y[1] && $code3_x[2] == $code3_y[2]) {
                    $code3_x[0]=0;
                    $code3_y[0]=1;
                }
            }
        }

        for ($i=0;$i<count($code3_x);$i++) {
            $code3  .= $code3_x[$i] . $code3_y[$i];
            $code9  .= base_convert ($code3, 3, 10);
            $h_code .= $code9;
            $code9  = "";
            $code3  = "";
        }
        $h_2    = substr($h_code,3);
        $h_1    = substr($h_code,0,3);
        $h_a1   = floor($h_1/30);
        $h_a2   = $h_1%30;
        $h_code = (substr(self::H_KEY,$h_a1,1) . substr(self::H_KEY,$h_a2,1)) . $h_2;

        $zone = array(
            'x'     => $h_x,
            'y'     => $h_y,
            'code'  => $h_code,
            'level' => $_level,
            'lat'   => $z_loc_y,
            'lon'   => $z_loc_x
        );

        return self::_setCachedZone($zone);
    }

    //Port from JavaScript API 3.1
    //Get Hex list from Rect, (array:{x:n,y:m}） : RECT（矩形）内のHEXリスト取得（配列{x:n,y:m}）
    public static function getXYListByRect($_min_lat, $_min_lon, $_max_lat, $_max_lon, $_level , $_buffer=false) {
        $base_steps = pow(3, $_level + 2) * 2;
        $list       = array();
        $steps_x    = 0;
        $steps_y    = 0;
    
        $min_lat    = ($_min_lat > $_max_lat) ? $_max_lat : $_min_lat;
        $max_lat    = ($_min_lat < $_max_lat) ? $_max_lat : $_min_lat;
        $min_lon    = $_min_lon;
        $max_lon    = $_max_lon;
    
        if ($_buffer) {
            $min_xy    = self::_loc2xy($min_lon, $min_lat);
            $max_xy    = self::_loc2xy($max_lon, $max_lat);
            $x_len     = ($max_lon >= $min_lon) ? abs( $max_xy['x'] - $min_xy['x']) : abs( self::H_BASE + $max_xy['x'] - $min_xy['x'] + self::H_BASE);
            $y_len     = abs($max_xy['y'] - $min_xy['y']);
            $min_coord = self::_xy2loc(($min_xy['x'] - $x_len/2) % ( self::H_BASE*2 ), $min_xy['y'] - $y_len/2);
            $max_coord = self::_xy2loc(($max_xy['x'] + $x_len/2) % ( self::H_BASE*2 ), $max_xy['y'] + $y_len/2);
            $min_lon   = $min_coord['lon'] % 360; 
            $max_lon   = $max_coord['lon'] % 360;
            $min_lat   = ($min_coord['lat'] < -1 * self::H_MAXLAT) ? -1 * self::H_MAXLAT : $min_coord['lat']; 
            $max_lat   = ($max_coord['lat'] > self::H_MAXLAT)      ? self::H_MAXLAT      : $max_coord['lat'];
            $min_lon   = ($x_len * 2 >= self::H_BASE*2) ? -180 : $min_lon;
            $max_lon   = ($x_len * 2 >= self::H_BASE*2) ?  180 : $max_lon;
        }
    
        $zone_tl = self::getZoneByLocation($max_lat, $min_lon, $_level);
        $zone_bl = self::getZoneByLocation($min_lat, $min_lon, $_level);
        $zone_br = self::getZoneByLocation($min_lat, $max_lon, $_level);
        $zone_tr = self::getZoneByLocation($max_lat, $max_lon, $_level);

        $start_x = $zone_bl['x'];
        $start_y = $zone_bl['y'];

        $h_size  = self::_calcHexSize($zone_br['level']);
    
        $bl_xy   = self::_loc2xy($zone_bl['lon'], $zone_bl['lat']);
        $bl_cl   = self::_xy2loc($bl_xy['x'] - $h_size, $bl_xy['y']);
        $bl_cl   = $bl_cl['lon'];
        $bl_cr   = self::_xy2loc($bl_xy['x'] + $h_size, $bl_xy['y']);
        $bl_cr   = $bl_cr['lon'];

        $br_xy   = self::_loc2xy($zone_br['lon'], $zone_br['lat']);
        $br_cl   = self::_xy2loc($br_xy['x'] - $h_size, $br_xy['y']);
        $br_cl   = $br_cl['lon'];
        $br_cr   = self::_xy2loc($br_xy['x'] + $h_size, $br_xy['y']);
        $br_cr   = $br_cr['lon'];

        $s_steps = self::_getXSteps($min_lon, $max_lon, $zone_bl, $zone_br);
        $w_steps = self::_getYSteps($min_lon, $zone_bl, $zone_tl);
        $n_steps = self::_getXSteps($min_lon, $max_lon, $zone_tl, $zone_tr);
        $e_steps = self::_getYSteps($max_lon, $zone_br, $zone_tr);
    
        // 矩形端にHEXの抜けを無くすためのエッジ処理
        $edge    = array(
            'l' => 0,
            'r' => 0,
            't' => 0,
            'b' => 0
        );
    
        if ( $s_steps == $n_steps && $s_steps >= $base_steps) {
            $edge['l'] = 0;
            $edge['r'] = 0; 
        } else {
            if ( $min_lon > 0 && $zone_bl['lon'] == -180 ) {
                $m_lon = $min_lon - 360;
                if ($bl_cr < $m_lon)   $edge['l'] =  1;
                if ($bl_cl > $m_lon)   $edge['l'] = -1;
            } else {
                if ($bl_cr < $min_lon) $edge['l'] =  1;
                if ($bl_cl > $min_lon) $edge['l'] = -1;
            }
    
            if ( $max_lon > 0 && $zone_br['lon'] == -180) {
                $m_lon = $max_lon - 360;
                if ($br_cr < $m_lon)   $edge['r'] =  1;
                if ($br_cl > $m_lon)   $edge['r'] = -1;
            } else {
                if ($br_cr < $max_lon) $edge['r'] =  1;
                if ($br_cl > $max_lon) $edge['r'] = -1;
            }
        }
    
        if ($zone_bl['lat'] > $min_lat) $edge['b']++;
        if ($zone_tl['lat'] > $max_lat) $edge['t']++; 

        // 仮想HEX_XY座標系上の辺リスト（ S & W ）を取得
        $s_list = self::_getXList($zone_bl, $s_steps, $edge['b']);
        $w_list = self::_getYList($zone_bl, $w_steps, $edge['l']);
    
        // 仮想HEX_XY座標系上の矩形端（ NW & SE ）取得
        $tl_end = array(
            "x" => $w_list[count($w_list)-1]['x'], 
            "y" => $w_list[count($w_list)-1]['y']
        );
        $br_end = array(
            "x" => $s_list[count($s_list)-1]['x'],
            "y" => $s_list[count($s_list)-1]['y']
        );
    
        // 仮想HEX_XY座標系上の辺リスト（ N & E ）取得
        $n_list = self::_getXList($tl_end, $n_steps, $edge['t']);
        $e_list = self::_getYList($br_end, $e_steps, $edge['r']);
    
        // S & W & N & E 辺リストに囲まれた内包HEXリストを取得
        $mrg_list = self::_mergeList(array_merge($s_list, $w_list, $n_list, $e_list), $_level);

        return $mrg_list;
    }

    public static function getZoneListByRect($_min_lat, $_min_lon, $_max_lat, $_max_lon, $_level , $_buffer=false) {
        $xys = self::getXYListByRect($_min_lat, $_min_lon, $_max_lat, $_max_lon, $_level , $_buffer);
        $ret = array();

        foreach ($xys as $xy) {
            $zone = self::getZoneByXY($xy['x'],$xy['y'],$_level);
            array_push($ret, $zone);
        }

        return $ret;
    }

    // longitude方向の仮想リスト取得
    public static function _getXList($_min, $_xsteps, $_edge){
        $list = array();
        for ($i=0; $i<$_xsteps; $i++) {
            $x = ($_edge) ? $_min['x'] + floor($i/2)      : $_min['x'] + ceil($i/2);
            $y = ($_edge) ? $_min['y'] + floor($i/2) - $i : $_min['y'] + ceil($i/2) - $i;
            array_push($list, array("x" => $x, "y" => $y));
        }
        return $list;
    }

    // latitude方向の仮想リスト取得 （この時点では補正しない）
    public static function _getYList($_min, $_ysteps, $_edge){
        $list = array();
        $steps_base = floor($_ysteps);
        $steps_half = $_ysteps - $steps_base;
    
        for ($i=0; $i<$steps_base; $i++){
            $x = $_min['x'] + $i;
            $y = $_min['y'] + $i;
            array_push($list, array("x" => $x, "y" => $y));
            
            if ($_edge != 0){ 
                if (($steps_half == 0) && ($i == $steps_base - 1)){
                } else {
                    $x = ($_edge > 0) ? $_min['x'] + $i + 1 : $_min['x'] + $i;
                    $y = ($_edge < 0) ? $_min['y'] + $i + 1 : $_min['y'] + $i;
                    array_push($list, array("x" => $x, "y" => $y));
                }
            }
        }
        return $list;
    }

    public static function _mergeList($_arr, $_level) {
        $newArr = array();
        $mrgArr = array();
    
        // HEX_Y座標系でソート
        usort($_arr,function($a, $b) {
            return ( $a['x'] > $b['x'] ? 1 : ($a['x'] < $b['x'] ? -1 : ($a['y'] < $b['y'] ? 1 : -1 )));
        });
    
        // マージ＆補完
        for ($i=0; $i<count($_arr); $i++) {
            if (!$i) {
                // 仮想XY値が確定したこの時点でadjust補正
                $inner_xy = self::_adjustXY($_arr[$i]['x'], $_arr[$i]['y'], $_level);
                $x = $inner_xy['x'];
                $y = $inner_xy['y'];
                if (!isset($mrgArr[$x])) $mrgArr[$x] = array();
                if (!isset($mrgArr[$x][$y])) {
                    $mrgArr[$x][$y] = true;
                    array_push($newArr,array("x"=>$x,"y"=>$y));
                }
            } else {
                $mrg = self::_mergeCheck($_arr[$i-1], $_arr[$i]);
                for($j=0; $j<$mrg ; $j++){
                    // 仮想XY値が確定したこの時点でadjust補正
                    $inner_xy = self::_adjustXY($_arr[$i]['x'], $_arr[$i]['y'] + $j, $_level);
                    $x = $inner_xy['x'];
                    $y = $inner_xy['y'];
                    if (!isset($mrgArr[$x])) $mrgArr[$x] = array();
                    if (!isset($mrgArr[$x][$y])) {
                        $mrgArr[$x][$y] = true;
                        array_push($newArr,array("x"=>$x,"y"=>$y));
                    }
                }
            }
        }
        return $newArr;
    }

    public static function _mergeCheck($_pre, $_next){
        if($_pre['x'] == $_next['x']){
            if($_pre['y'] == $_next['y']){
                return 0;
            }else{
                return abs($_next['y'] - $_pre['y']);
            }
        }else{
            return 1;
        }
    }

    // Step numbers along longitude : longitude方向のステップ数取得
    public static function _getXSteps($_minlon, $_maxlon, $_min, $_max) {
        $minsteps   = abs( $_min['x'] - $_min['y'] );
        $maxsteps   = abs( $_max['x'] - $_max['y'] );
        $code       = $_min['code'];
        $base_steps = pow( 3, strlen($code) ) * 2;
        
        $steps = 0;
        
        if ($_min['lon'] == -180 && $_max['lon'] == -180) {
            if (($_minlon > $_maxlon && $_minlon * $_maxlon >= 0) || ($_minlon < 0 && $_maxlon > 0)) $steps = $base_steps; 
            else $steps=0;
        } else if (abs($_min['lon'] - $_max['lon']) < 0.0000000001) {
            if ($_min['lon'] != -180 && $_minlon > $_maxlon) {
                $steps = $base_steps;
            } else {
                $steps = 0;
            }
                
        } else if ($_min['lon'] < $_max['lon']) {
            if ($_min['lon'] <= 0 && $_max['lon'] <= 0) {
                $steps = $minsteps - $maxsteps;
            } else if ($_min['lon'] <= 0 && $_max['lon'] >= 0) {
                $steps = $minsteps + $maxsteps;
            } else if ($_min['lon'] >= 0 && $_max['lon'] >= 0) {
                $steps = $maxsteps - $minsteps;
            }
        } else if ($_min['lon'] > $_max['lon']) {
            if ($_min['lon'] <= 0 && $_max['lon'] <= 0) {
                $steps = $base_steps - $maxsteps + $minsteps;
            } else if ($_min['lon'] >= 0 && $_max['lon'] <= 0) {
                $steps = $base_steps - ($minsteps + $maxsteps);
            } else if ($_min['lon'] >= 0 && $_max['lon'] >= 0) {
                $steps = $base_steps + $maxsteps - $minsteps;
            }
        }
        return $steps + 1;
    }

    // Step numbers along latitude : latitude方向のステップ数取得
    public static function _getYSteps($_lon, $_min, $_max){
        $steps;
        $min_x = $_min['x'];
        $min_y = $_min['y'];
        $max_x = $_max['x'];
        $max_y = $_max['y'];
            
        if ($_lon > 0) {
            if ($_min['lon'] != -180 && $_max['lon'] == -180) {
                $max_x = $_max['y'];
                $max_y = $_max['x'];
            }
            if ($_min['lon'] == -180 && $_max['lon'] != -180) {
                $min_x = $_min['y'];
                $min_y = $_min['x'];
            }
        }
        $steps = abs($min_y - $max_y);
        $half  = abs($max_x - $min_x) - abs($max_y - $min_y);
        return $steps + $half * 0.5 + 1;
    }

    public static function _adjustXY($_x, $_y, $_level) {
        $x   = $_x;
        $y   = $_y;
        $rev = 0;
        $max_hsteps = pow( 3, $_level + 2);
        $hsteps = abs( $x - $y );

        if( $hsteps == $max_hsteps && $x > $y ) {
            $tmp   = $x;
            $x     = $y;
            $y     = $tmp;
            $rev   = 1;
            $loops = 0;
        } else if ( $hsteps > $max_hsteps ) {
            $dif   = $hsteps - $max_hsteps;
            $dif_x = floor( $dif / 2 );
            $dif_y = $dif - $dif_x;
            $edge_x;
            $edge_y;
            if( $x > $y ){
                $edge_x = $x - $dif_x;
                $edge_y = $y + $dif_y;
                $h_xy   = $edge_x;
                $edge_x = $edge_y;
                $edge_y = $h_xy;
                $x      = $edge_x + $dif_x;
                $y      = $edge_y - $dif_y;
            } else if ( $y > $x ) {
                $edge_x = $x + $dif_x;
                $edge_y = $y - $dif_y;
                $h_xy   = $edge_x;
                $edge_x = $edge_y;
                $edge_y = $h_xy;
                $x      = $edge_x - $dif_x;
                $y      = $edge_y + $dif_y;
            }
        }
        return array( 'x' => $x, 'y' => $y , 'rev' => $rev );
    }

    public static function getHexCoordsByZone($zone)
    {
        $h_lat  = $zone['lat'];
        $h_lon  = $zone['lon'];
        $h_xy   = self::_loc2xy($h_lon, $h_lat);
        $h_x    = $h_xy['x'];
        $h_y    = $h_xy['y'];
        $h_deg  = tan(pi() * (60 / 180));
        $h_size = strlen($zone['code']);

        $h_top = self::_xy2locLat($h_x, $h_y + $h_deg * $h_size);
        $h_btm = self::_xy2locLat($h_x, $h_y - $h_deg * $h_size);

        $h_l  = self::_xy2locLon($h_x - 2 * $h_size, $h_y);
        $h_r  = self::_xy2locLon($h_x + 2 * $h_size, $h_y);
        $h_cl = self::_xy2locLon($h_x - 1 * $h_size, $h_y);
        $h_cr = self::_xy2locLon($h_x + 1 * $h_size, $h_y);

        return array(
            array('lat' =>  $h_lat, 'lon' => $h_l),
            array('lat' =>  $h_top, 'lon' => $h_cl),
            array('lat' =>  $h_top, 'lon' => $h_cr),
            array('lat' =>  $h_lat, 'lon' => $h_r),
            array('lat' =>  $h_btm, 'lon' => $h_cr),
            array('lat' =>  $h_btm, 'lon' => $h_cl)
        );
    }

    /**
     * private
     */

    public function setCoords()
    {
        if (isset($this->code) &&
            isset($this->lat) &&
            isset($this->lon)
        ) {
            $this->coords = self::getHexCoordsByZone(array(
                'code' => $this->code,
                'lat'  => $this->lat,
                'lon'  => $this->lon
            ));
        }
        return $this;
    }

    /**
     * private static
     */

    private static function _calcHexSize($_level) {
        return self::H_BASE / pow(3, $_level + 3);
    }
    private static function _loc2xy($lon, $lat)
    {
        $x = $lon * self::H_BASE / 180;
        $y = log(tan((90 + $lat) * pi() / 360)) / (pi() / 180);
        $y *= self::H_BASE / 180;

        return array(
            'x' => $x,
            'y' => $y
        );
    }
    private static function _xy2loc($x, $y)
    {
        $lon = ($x / self::H_BASE) * 180;
        $lat = ($y / self::H_BASE) * 180;
        $lat = 180 / pi() * (2 * atan(exp($lat * pi() / 180)) - pi() / 2);

        return array(
            'lat' => $lat,
            'lon' => $lon
        );
    }
    private static function _xy2locLat($x, $y)
    {
        $coord = self::_xy2loc($x, $y);
        return $coord['lat'];
    }
    private static function _xy2locLon($x, $y)
    {
        $coord = self::_xy2loc($x, $y);
        return $coord['lon'];
    }
}
