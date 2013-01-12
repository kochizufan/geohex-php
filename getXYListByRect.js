/// COPYRIGHT 2013 GEOHEX Inc. ///
/// GEOHEX by @sa2da (http://twitter/sa2da) is licensed under Creative Commons BY-SA 2.1 Japan License. ///

// RECT（矩形）内のHEXリスト取得（配列{x:n,y:m}）
function getXYListByRect(_min_lat, _min_lon, _max_lat, _max_lon, _level , _buffer){
    //東西座標最大/最小値
    var common_ew     = Math.pow(3,_level + 2);
    var common_max_ew = common_ew - 1;
    var common_min_ew = -1 * common_ew;

    //矩形角のゾーン
    var zone_tl  = GEOHEX.getZoneByLocation(_max_lat, _min_lon, _level);
    var zone_tr  = GEOHEX.getZoneByLocation(_max_lat, _max_lon, _level);
    var zone_bl  = GEOHEX.getZoneByLocation(_min_lat, _min_lon, _level);
    var zone_br  = GEOHEX.getZoneByLocation(_min_lat, _max_lon, _level);
    //矩形角の頂点
    var coord_tl = zone_tl.getHexCoords();
    var coord_tr = zone_tr.getHexCoords();
    var coord_bl = zone_bl.getHexCoords();
    var coord_br = zone_br.getHexCoords();

    //最小の東西座標
    var tl_ew  = zone_tl.x - zone_tl.y;
    //西北/西南頂点より西にある時は、スキャンする東西座標を一つ減らす
    if ( ( tl_ew != common_min_ew && _min_lon < coord_tl[1].lon ) || 
         ( tl_ew == common_min_ew && _min_lon > 0 && _min_lon - 360 < coord_tl[1].lon ) ) tl_ew--;
    if ( tl_ew < common_min_ew ) tl_ew = common_max_ew;
    var bl_ew  = zone_bl.x - zone_bl.y;
    if ( ( bl_ew != common_min_ew && _min_lon < coord_bl[1].lon ) || 
         ( bl_ew == common_min_ew && _min_lon > 0 && _min_lon - 360 < coord_bl[1].lon ) ) bl_ew--;
    if ( bl_ew < common_min_ew ) bl_ew = common_max_ew;

    var min_ew = tl_ew < bl_ew ? tl_ew : bl_ew;
    if ((tl_ew == common_min_ew && bl_ew == common_max_ew) || (tl_ew == common_max_ew && bl_ew == common_min_ew)) {
        min_ew = common_min_ew;
    }

    //最大の東西座標
    var tr_ew  = zone_tr.x - zone_tr.y;
    //東北/東南頂点より東にある時は、スキャンする東西座標を一つ増やす
    //また、日付変更線またぎへクスの東経部分に居る時は、東西座標を地球一周分増やす
    if ( tr_ew == common_min_ew && _max_lon > 0 && !( tr_ew == tl_ew && _min_lon < _max_lon && _min_lon > 0 )) tr_ew += 2 * common_ew;
    else if ( ( tr_ew != common_min_ew || _max_lon < 0 ) && _max_lon > coord_tr[2].lon) tr_ew++;
    var br_ew  = zone_br.x - zone_br.y;
    if ( br_ew == common_min_ew && _max_lon > 0 && !( br_ew == bl_ew && _min_lon < _max_lon && _min_lon > 0 )) br_ew += 2 * common_ew;
    else if ( ( br_ew != common_min_ew || _max_lon < 0 ) && _max_lon > coord_br[2].lon) br_ew++;

    var max_ew = tr_ew > br_ew ? tr_ew : br_ew;
    if ((tr_ew == common_min_ew && br_ew == common_max_ew) || (tr_ew == common_max_ew && br_ew == common_min_ew)) {
        max_ew = common_max_ew;
    }

    //最大東西座標より最小東西座標の方が大きい時は、地球一周させる
    while (max_ew < min_ew) {
        max_ew += 2 * common_ew;
    }

    //最大の南北座標、中心より北に居る時は一つ増やす
    var tl_ns  = zone_tl.x + zone_tl.y;
    if (_max_lat > zone_tl.lat) tl_ns++;
    var tr_ns  = zone_tr.x + zone_tr.y;
    if (_max_lat > zone_tr.lat) tr_ns++;
    var max_ns = tl_ns > tr_ns ? tl_ns : tr_ns;

    //最小の南北座標、中心より南に居る時は一つ減らす
    var bl_ns  = zone_bl.x + zone_bl.y;
    if (_min_lat < zone_bl.lat) bl_ns--;
    var br_ns  = zone_br.x + zone_br.y;
    if (_min_lat < zone_br.lat) br_ns--;    
    var min_ns = bl_ns < br_ns ? bl_ns : br_ns;

    //東西南北座標ループ
    var list   = {};
    for (var ew = min_ew; ew <= max_ew; ew++) {
        for (var ns = min_ns; ns <= max_ns; ns++) {
            //東西座標と南北座標の偶奇が一致するところにしかへクスはないので処理を跳ばす
            if ( Math.abs(ew % 2) != Math.abs(ns % 2) ) continue;
            //とんでもない値になっている東西座標を修正
            var adjew = ew; 
            while (1) {
                if ( adjew > common_max_ew ) {
                    adjew -= 2 * common_ew;
                } else if ( adjew < common_min_ew ) {
                    adjew += 2 * common_ew;
                } else {
                    break;
                }
            }

            //東西南北座標をへクス座標に変換
            var x = (ns + adjew) / 2;
            var y = (ns - adjew) / 2;
            
            var zone = {"x":x, "y":y};//GEOHEX.getZoneByXY(x, y,　_level);

            //隅のへクスは一致しない限り採用しない
            if (ew == min_ew) {
                if (ns == min_ns && (zone.x != zone_bl.x || zone.y != zone_bl.y)) continue;
                else if (ns == max_ns && (zone.x != zone_tl.x || zone.y != zone_tl.y)) continue;
            } else if (ew == max_ew) {
                if (ns == min_ns && (zone.x != zone_br.x || zone.y != zone_br.y)) continue;
                else if (ns == max_ns && (zone.x != zone_tr.x || zone.y != zone_tr.y)) continue;
            }

            list[zone.x + "," + zone.y] = zone;
        }
    }

    var ret_list = [];
    for (var code in list) {
        var zone = list[code];
        ret_list.push({"x":zone.x,"y":zone.y});
    }

    return ret_list;
}

function getXYListByRect1(_min_lat, _min_lon, _max_lat, _max_lon, _level , _buffer){
    var list = [];
    var steps_x =0;
    var steps_y =0;
    var zone_tl = GEOHEX.getZoneByLocation(_max_lat, _min_lon, _level);
    var zone_bl = GEOHEX.getZoneByLocation(_min_lat, _min_lon, _level);
    var zone_br = GEOHEX.getZoneByLocation(_min_lat, _max_lon, _level);
    var zone_tr = GEOHEX.getZoneByLocation(_max_lat, _max_lon, _level);
    var bl_x = zone_bl.x;
    var bl_y = zone_bl.y;
    var br_x = zone_br.x;
    var br_y = zone_br.y;
    var tl_x = zone_tl.x;
    var tl_y = zone_tl.y;
    var tr_x = zone_tr.x;
    var tr_y = zone_tr.y;
    var eject = {}

    var start_x = bl_x;
    var start_y = bl_y;

    var h_deg = Math.tan(Math.PI * (60 / 180));
    var h_size = zone_br.getHexSize();

    var bl_xy = GEOHEX.loc2xy(zone_bl.lon, zone_bl.lat);
    var bl_cl = GEOHEX.xy2loc(bl_xy.x - h_size, bl_xy.y).lon;

    var br_xy = GEOHEX.loc2xy(zone_br.lon, zone_br.lat);
    var br_cr = GEOHEX.xy2loc(br_xy.x + h_size, br_xy.y).lon;

    // 矩形端にHEXの抜けを無くすためのエッジ処理
    var edge={l:0,r:0,t:0,b:0};
    if(bl_cl > _min_lon) edge.l++;
    if(br_cr < _max_lon) edge.r++;
    if(zone_bl.lat > _min_lat) edge.b++;
    if(zone_tl.lat < _max_lat) edge.t++;

    if(edge.l) start_y++;
    if(edge.b) {
        start_x--;
        start_y--;
    }

    var steps_x = getXSteps(zone_bl, zone_br) + edge.l + edge.r;
    var steps_y = getYSteps(zone_bl, zone_tl) + edge.b;


    // バッファ指定時: 画面の上下左右に半画面分ずつ余分取得
    if(_buffer&&(_min_lon!=-180||_max_lon!=180)){
        start_x =(edge.b)?start_x - Math.floor(steps_x/2):start_x - Math.ceil(steps_x/2);
        steps_x *=2;
        steps_y *=2;
    }

    if(_min_lon==-180&&_max_lon==180) steps_x = Math.pow(3, _level+2)*2;

    for(var j=0;j<steps_y;j++){
        for(var i=0;i<steps_x;i++){
            var x = (edge.l)?start_x + j + Math.floor(i/2):start_x + j + Math.ceil(i/2);
            var y = (edge.l)?start_y + j + Math.floor(i/2) - i:start_y + j + Math.ceil(i/2) - i;

            // リスト出力前にajustXY補正を追加
            var inner_xy = GEOHEX.adjustXY(x,y,_level);
            x = inner_xy.x;
            y = inner_xy.y;
    
            if((j==steps_y-1&&i%2!=edge.l)||(j==0&&edge.b&&i%2==edge.l)); else  list.push({x:x,y:y} );
        }
    }
    if(edge.t){
        j = steps_y -1;
        for(var i=0;i<steps_x;i++){
            var x = (edge.l)?start_x + j + Math.floor(i/2):start_x + j + Math.ceil(i/2);
            var y = (edge.l)?start_y + j + Math.floor(i/2) - i:start_y + j + Math.ceil(i/2) - i;

            // リスト出力前にajustXY補正を追加
            var inner_xy = GEOHEX.adjustXY(x,y,_level);
            x = inner_xy.x;
            y = inner_xy.y;

            list.push({x:x,y:y});
        }
    }
    return list;
}

// longitude方向のステップ数取得
function getXSteps(_min, _max){
        var minsteps = Math.abs(_min.x - _min.y);
        var maxsteps = Math.abs(_max.x - _max.y);
        var code = _min.code;
        var base_steps =  Math.pow(3, code.length)*2;
        
        var steps=0;
        
        if(_min.lon!= -180 && Math.abs(_min.lon - _max.lon) < 0.0000000001){
            steps = 0;
        }else if(_min.lon < _max.lon){
            if(_min.lon<=0&&_max.lon<=0){
                steps = minsteps - maxsteps;
            }else if(_min.lon<=0&&_max.lon>=0){
                steps = minsteps + maxsteps;
            }else if(_min.lon>=0&&_max.lon>=0){
                steps = maxsteps - minsteps;
            }
        }else if(_min.lon > _max.lon){
            if(_min.lon<=0&&_max.lon<=0){
                steps = base_steps - maxsteps + minsteps;
            }else if(_min.lon>=0&&_max.lon<=0){
                steps = base_steps-(minsteps + maxsteps);
            }else if(_min.lon>=0&&_max.lon>=0){
                steps = base_steps + maxsteps - minsteps;
            }
        }
        return steps + 1;
}

// latitude方向のステップ数取得
function getYSteps(_min, _max){
    return Math.abs(_min.y - _max.y) + 1;
}