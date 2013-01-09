/// COPYRIGHT 2013 GEOHEX Inc. ///
/// GEOHEX by @sa2da (http://twitter/sa2da) is licensed under Creative Commons BY-SA 2.1 Japan License. ///

// RECT（矩形）内のHEXリスト取得（配列{x:n,y:m}）
function getXYListByRect(_min_lat, _min_lon, _max_lat, _max_lon, _level , _buffer){
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
    var tl_x = zone_bl.x;
    var tl_y = zone_bl.y;
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

var loops = 0;
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