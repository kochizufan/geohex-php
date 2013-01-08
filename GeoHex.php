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
    const VERSION = '3.02';

    const H_KEY  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    const H_BASE = 20037508.34;
    const H_DEG  = 0.5235987755983;  # pi() / 30 / 180
    const H_K    = 0.57735026918963; # tan(H_DEG)

    private static $_zoneCache  = array();
    private static $_cacheLimit = 100;

    public $x = null;
    public $y = null;
    public $code = null;
    public $level = 7;
    public $latitude = null;
    public $longitude = null;
    public $coords = array();

    /**
     * construct
     *
     * @param array(
     *     'code'      => String,
     *     'level'     => Integer,
     *     'latitude'  => Float,
     *     'longitude' => Float
     * )
     * @return GeoHex
     * @throws GeoHex_Exception
     */
    public function __construct()
    {
        switch (func_num_args()) {
        case 3:
            $args = func_get_args();
            $this->latitude = $args[0];
            $this->longitude = $args[1];
            $this->level = $args[2];
            break;
        case 2:
            $args = func_get_args();
            $this->latitude = $args[0];
            $this->longitude = $args[1];
            break;
        case 1:
            $arg = func_get_arg(0);

            if (is_string($arg)) {
                $this->code = $arg;
            }

            else if (is_array($arg)) {
                $keys = array('code', 'level', 'latitude', 'longitude','x','y');

                foreach ($keys as $key) {
                    if (isset($arg[$key])) {
                        $this->$key = $arg[$key];
                    }
                }
            }
            break;
        }

        if (isset($this->latitude) && isset($this->longitude))
        {
            $this->setLocation($this->latitude, $this->longitude, $this->level);
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

    public function setLocation($latitude, $longitude, $level = null)
    {
        $this->latitude  = $latitude;
        $this->longitude = $longitude;

        if (isset($level)) {
            $this->level = $level;
        }

        if (!isset($this->latitude) ||
            !isset($this->longitude) ||
            !isset($this->level)
        ) {
            return $this;
        }

        $zone = self::getZoneByLocation($this->latitude, $this->longitude, $this->level);

        $this->x = $zone['x'];
        $this->y = $zone['y'];
        $this->code = $zone['code'];

        return $this->setCoords();
    }
    public function setXY($x, $y, $level = null)
    {
        $this->x = $x;
        $this->y = $y;

        if (isset($level)) {
            $this->level = $level;
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
        $this->latitude  = $zone['latitude'];
        $this->longitude = $zone['longitude'];
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
        $this->latitude = $zone['latitude'];
        $this->longitude = $zone['longitude'];

        return $this->setCoords();
    }
    public function setLevel($level)
    {
        return $this->setLocation(
            $this->latitude, $this->longitude, $level);
    }
    public function setLatitude($latitude)
    {
        return $this->setLocation($latitude, $this->longitude);
    }
    public function setLongitude($longitude)
    {
        return $this->setLocation($this->latitude, $longitude);
    }

    //Cache API
    private static function _getCachedZone($code) {
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

    public static function getZoneByLocation($lat, $lon, $level) {
        $level_   = $level + 2;
        $h_size   = self::_calcHexSize($level_);
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

        $h_lat = (self::H_K * $h_x * $unit_x + $h_y * $unit_y) / 2;
        $h_lon = ($h_lat - $h_y * $unit_y) / self::H_K;

        $z_loc = self::_xy2loc($h_lon, $h_lat);
        $z_loc_x = $z_loc['lon'];
        $z_loc_y = $z_loc['lat'];

        $inner_xy = self::_adjustXY($h_x,$h_y,$level_);
        $h_x = $inner_xy['x'];
        $h_y = $inner_xy['y'];
        if ($inner_xy['rev']) $z_loc_x = 180;

        $h_code  = '';
        $code3_x = array();
        $code3_y = array();
        $code3   = '';
        $code9   = '';
        $mod_x   = $h_x;
        $mod_y   = $h_y;

        for ($i = 0; $i <= $level_; $i++) {
            $h_pow = pow(3, $level_ - $i);

            if ($mod_x >= ceil($h_pow / 2)) {
                $code3_x[$i] = 2;
                $mod_x -= $h_pow;
            }

            else if ($mod_x <= -ceil($h_pow / 2)) {
                $code3_x[$i] = 0;
                $mod_x += $h_pow;
            }
            
            else {
                $code3_x[$i] = 1;
            }

            if ($mod_y >= ceil($h_pow / 2)) {
                $code3_y[$i] =2;
                $mod_y -= $h_pow;
            }

            else if ($mod_y <= -ceil($h_pow / 2)) {
                $code3_y[$i] = 0;
                $mod_y += $h_pow;
            }
            
            else {
                $code3_y[$i] = 1;
            }
        }

        for ($i = 0; $i < count($code3_x); $i++) {
            $code3  += $code3_x[$i] . $code3_y[$i];
            $code9  += intval((string) $code3, 3);
            $h_code .= $code9;
            $code9   = '';
            $code3   = '';
        }

        $h_2    = substr($h_code, 3);
        $h_1    = substr($h_code, 0, 3);
        $h_a1   = floor($h_1 / 30);
        $h_a2   = $h_1 % 30;
        $h_code = substr(self::H_KEY, $h_a1, 1) . substr(self::H_KEY, $h_a2, 1) . $h_2;

        $zone = array(
            'x' => $h_x,
            'y' => $h_y,
            'code' => $h_code,
            'level' => $level,
            'latitude' => $z_loc_y,
            'longitude' => $z_loc_x
        );

        return self::_setCachedZone($zone);
    }

    public static function getZoneByCode($code) {
        $ret = self::_getCachedZone($code);
        if ($ret != null) {
            return $ret;
        }

        $level  = strlen($code);
        $h_size = self::_calcHexSize($level);
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

        for ($i = 0; $i < $level + 1 - $d9xlen; $i++) {
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

        for ($i = 0; $i <= $level; $i++) {
            $h_pow = pow(3, $level - $i);

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

        $inner_xy = self::_adjustXY( $h_x, $h_y, $level );
        $h_x = $inner_xy['x'];
        $h_y = $inner_xy['y'];

        $h_lat_y = (self::H_K * $h_x * $unit_x + $h_y * $unit_y) / 2;
        $h_lon_x = ($h_lat_y - $h_y * $unit_y) / self::H_K;
        $h_loc = self::_xy2loc($h_lon_x, $h_lat_y);

        if ($inner_xy['rev'] == 1) $h_loc['lon'] = 180;

        if ($h_loc['lon'] > 180) {
            $h_loc['lon'] -= 360;
            $h_x -= pow(3,$level);    // v3.01
            $h_y += pow(3,$level);    // v3.01
        }
        
        else if ($h_loc['lon'] < -180) {
            $h_loc['lon'] += 360;
            $h_x += pow(3,$level);    // v3.01
            $h_y -= pow(3,$level);    // v3.01
        }

        $zone = array(
            'x' => $h_x,
            'y' => $h_y,
            'code' => $code,
            'level' => strlen($code) - 2,
            'latitude' => $h_loc['lat'],
            'longitude' => $h_loc['lon']
        );

        return self::_setCachedZone($zone);
    }

    public static function getZoneByXY($_x, $_y, $level) {
        $level_   = $level + 2;
        $h_size   = self::_calcHexSize($level_);

        $unit_x = 6 * $h_size;
        $unit_y = 6 * $h_size * self::H_K;
    
        $h_x = $_x;
        $h_y = $_y;

        $inner_xy = self::_adjustXY( $h_x, $h_y, $level_ );
        $h_x = $inner_xy['x'];
        $h_y = $inner_xy['y'];

        $h_lat = (self::H_K * $h_x * $unit_x + $h_y * $unit_y) / 2;
        $h_lon = ($h_lat - $h_y * $unit_y) / self::H_K;

        $z_loc = self::_xy2loc($h_lon, $h_lat);
        $z_loc_x = $z_loc['lon'];
        $z_loc_y = $z_loc['lat'];

        if ($inner_xy['rev']) {
            $z_loc_x = 180;
        }

        $h_code  = "";
        $code3_x = array();
        $code3_y = array();
        $code3   = "";
        $code9   = "";
        $mod_x   = $h_x;
        $mod_y   = $h_y;

        for ( $i = 0; $i <= $level_ ; $i++ ) {
            $h_pow = pow( 3, $level_ - $i );
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

        $ret = self::_getCachedZone($h_code);
        if ($ret != null) {
            return $ret;
        }

        $zone = array(
            'x' => $h_x,
            'y' => $h_y,
            'code' => $h_code,
            'level' => $level,
            'latitude' => $z_loc_y,
            'longitude' => $z_loc_x
        );

        return self::_setCachedZone($zone);
    }

    //Port from JavaScript API 3.1
    //Get Hex list from Rect, (array:{x:n,y:m}） : RECT（矩形）内のHEXリスト取得（配列{x:n,y:m}）
    public static function getXYListByRect($_min_lat, $_min_lon, $_max_lat, $_max_lon, $_level , $_buffer) {
        $list = array();
        $steps_x = 0;
        $steps_y = 0;
        $zone_tl = self::getZoneByLocation($_max_lat, $_min_lon, $_level);
        $zone_bl = self::getZoneByLocation($_min_lat, $_min_lon, $_level);
        $zone_br = self::getZoneByLocation($_min_lat, $_max_lon, $_level);
        
        $start_x = $zone_bl['x'];
        $start_y = $zone_bl['y'];
    
        $h_deg   = tan(pi() * (60 / 180));
        $h_size  = self::_calcHexSize(strlen($zone_br['code']));
    
        $bl_xy = self::_loc2xy($zone_bl['longitude'], $zone_bl['latitude']);
        $bl_cl = self::_xy2loc($bl_xy['x'] - 1 * $h_size, $bl_xy['y']);
        $bl_cl = $bl_cl['lon'];
    
        $br_xy = self::_loc2xy($zone_br['longitude'], $zone_br['latitude']);
        $br_cr = self::_xy2loc($br_xy['x'] + 1 * $h_size, $br_xy['y']);
        $br_cr = $br_cr['lon'];
    
        // Checking Edge : 矩形端にHEXの抜けを無くすためのエッジ処理
        $edge = array(
            'l' => 0,
            'r' => 0,
            't' => 0,
            'b' => 0
        );
        if ($bl_cl > $_min_lon) $edge['l']++;
        if ($br_cr < $_max_lon) $edge['r']++;
        if ($zone_bl['latitude'] > $_min_lat) $edge['b']++;
        if ($zone_tl['latitude'] < $_max_lat) $edge['t']++;
    
        if ($edge['l']){
            $start_x -= $edge['b'];
            $start_y -= $edge['b'] - 1; 
        }
    
        $steps_x = self::_getXSteps($zone_bl, $zone_br) + $edge['l'] + $edge['r'];
        $steps_y = self::_getYSteps($zone_bl, $zone_tl) + $edge['b'] * $edge['t'];

        // Calcurating for buffer : バッファ指定時: 画面の上下左右に半画面分ずつ余分取得
        if($_buffer){
            $start_x =($edge['b']) ? $start_x - floor( $steps_x / 2) : $start_x - ceil( $steps_x / 2);
            $steps_x *= 2;
            $steps_y *= 2;
        }
    
        if ( $_min_lon == -180 && $_max_lon == 180 ) $steps_x = pow( 3, $level+2)*2;

        for ( $j=0; $j<$steps_y-$edge['t']; $j++ ) {
            for($i=0;$i<$steps_x;$i++){
                $x = $start_x + $j + ceil( $i / 2);
                $y = $start_y + $j + ceil( $i / 2) - $i;
                $push = $edge['b'] ? array('x'=>$start_x + $j + floor($i/2),'y'=>$start_y + $j + floor($i/2) - $i)
                                   : array('x'=>$start_x + $j + ceil( $i/2),'y'=>$start_y + $j + ceil( $i/2) - $i);
                array_push($list, $push);
            }
        }
        if ($edge['t']) {
            $j = $steps_y - 1;
            for($i=0;$i<$steps_x;$i++) {
                $x = $start_x + $j + ceil( $i / 2);
                $y = $start_y + $j + ceil( $i / 2) - $i;
                if ($steps_y - $edge['t'] == 0  || $edge['b'] == $i % 2 ) {
                    $push = $edge['b'] ? array('x'=>$start_x + $j + floor($i/2),'y'=>$start_y + $j + floor($i/2) - $i)
                                       : array('x'=>$start_x + $j + ceil( $i/2),'y'=>$start_y + $j + ceil( $i/2) - $i);
                    array_push($list, $push);
                }
            }
        }
        return $list;
    }

    // Step numbers along longitude : longitude方向のステップ数取得
    public static function _getXSteps($_min, $_max){
        $code = $_min['code'];
        $max_steps =  pow(3, strlen($code))*2;
        $steps = abs($_min['x'] - $_max['x']) + abs($_min['y'] - $_max['y']);
        $steps = ($steps > ($max_steps-$steps))? $steps - $max_steps: $steps;
        return $steps + 1;
    }

    // Step numbers along latitude : latitude方向のステップ数取得
    public static function _getYSteps($_min, $_max){
        return abs($_min['y'] - $_max['y']) + 1;
    }

    public static function _adjustXY($_x, $_y, $_level) {
        $x   = $_x;
        $y   = $_y;
        $rev = 0;
        $max_hsteps = pow( 3, $_level );
        $hsteps = abs( $x - $y );

        if( $hsteps == $max_hsteps && $x > $y ) {
            $tmp = $x;
            $x   = $y;
            $y   = $tmp;
            $rev = 1;
        } else if ( $hsteps > $max_hsteps ) {
            $dif   = $hsteps % ( $max_hsteps * 2 ) - $max_hsteps;
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
        $h_lat  = $zone['latitude'];
        $h_lon  = $zone['longitude'];
        $h_xy   = self::_loc2xy($h_lon, $h_lat);
        $h_x    = $h_xy['x'];
        $h_y    = $h_xy['y'];
        $h_deg  = tan(pi() * (60 / 180));
        $h_size = strlen($zone['code']);

        $h_top = self::_xy2locLatitude($h_x, $h_y + $h_deg * $h_size);
        $h_btm = self::_xy2locLatitude($h_x, $h_y - $h_deg * $h_size);

        $h_l  = self::_xy2locLongitude($h_x - 2 * $h_size, $h_y);
        $h_r  = self::_xy2locLongitude($h_x + 2 * $h_size, $h_y);
        $h_cl = self::_xy2locLongitude($h_x - 1 * $h_size, $h_y);
        $h_cr = self::_xy2locLongitude($h_x + 1 * $h_size, $h_y);

        return array(
            array('latitude' =>  $h_lat, 'longitude' => $h_l),
            array('latitude' =>  $h_top, 'longitude' => $h_cl),
            array('latitude' =>  $h_top, 'longitude' => $h_cr),
            array('latitude' =>  $h_lat, 'longitude' => $h_r),
            array('latitude' =>  $h_btm, 'longitude' => $h_cr),
            array('latitude' =>  $h_btm, 'longitude' => $h_cl)
        );
    }

    /**
     * private
     */

    public function setCoords()
    {
        if (isset($this->code) &&
            isset($this->latitude) &&
            isset($this->longitude)
        ) {
            $this->coords = self::getHexCoordsByZone(array(
                'code' => $this->code,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude
            ));
        }
        return $this;
    }

    /**
     * private static
     */

    private static function _calcHexSize($level) {
        return self::H_BASE / pow(3, $level + 1);
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
    private static function _xy2locLatitude($x, $y)
    {
        $coord = self::_xy2loc($x, $y);
        return $coord['lat'];
    }
    private static function _xy2locLongitude($x, $y)
    {
        $coord = self::_xy2loc($x, $y);
        return $coord['lon'];
    }
}
