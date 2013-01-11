/// COPYRIGHT 2013 GEOHEX Inc. ///
/// GEOHEX by @sa2da (http://twitter/sa2da) is licensed under Creative Commons BY-SA 2.1 Japan License. ///

(function (win) {   // グローバルを汚さないように関数化

// namspace GEOHEX; // require hex_v2_core.js
if (!win.GEOHEX)    return;

// require google.maps.version >= 3
if (!win.google || !google.maps || parseFloat(google.maps.version) < 3) return;

var Zone = GEOHEX.getZoneByCode('aaa').constructor;

var World = (function () {
    var count = 0;
    var fn = function (map) {
        this.map = map;
        this.stamp = {};    // Zone(HexCode)に対する描画済みHexPolygonのキャッシュ
        this.onDrawHex = [];
    };
    var _commonOnDrawHex = [];
    fn.prototype.fireOnDrawHex = function (zone, polygon, properties) {
        for (var idx = 0, l = _commonOnDrawHex.length; idx < l; idx++) {
            _commonOnDrawHex[idx].call(null, this.map, zone, polygon, properties);
        }
        for (var idx = 0, l = this.onDrawHex.length; idx < l; idx++) {
            this.onDrawHex[idx].call(null, this.map, zone, polygon, properties);
        }
    }
    var _stack = [];
    return {
        get: function (map) {
            var idx = map.ghxid;
            if (!idx || !_stack[idx]) {
                idx = map.ghxid = ++count;
                _stack[count] = new fn(map);
            }
            return _stack[idx];
        },
        reg: function (map, onDrawHex) {
            if (arguments.length < 2) {
                onDrawHex = map;
                _commonOnDrawHex.push(onDrawHex);
            } else {
                World.get(map).onDrawHex.push(onDrawHex);
            }
        }
    };
})();

//var h_stamp = {}; // → World.get(map)::stamp に置換

var def_prop = {
    linecolor: "#FF0000",
    fillcolor: "#efefef",
    lineotp: 0.6,
    lineweight: 1,
    fillopt: 0.1
};  // ＊＊＊ popinfoは非標準プロパティ ＊＊＊

var $merge = function (org, src) {
    org || (org = {});
    src || (src = {});
    var dest = {};
    for (var idx in org) if (org.hasOwnProperty(idx)) {
        dest[idx] = org[idx];
    }
    for (var idx in src) if (src.hasOwnProperty(idx)) {
        dest[idx] = src[idx];
    }
    return dest;
};

Zone.prototype.drawHex = function (map, properties) {
    var prop = $merge(def_prop, properties);
    var linecolor = prop.linecolor;
    var fillcolor = prop.fillcolor;
    var lineotp= prop.lineotp;
    var lineweight= prop.lineweight;
    var fillopt= prop.fillopt;

    var world = World.get(map);
    var hexPolygon = world.stamp[this.code];
    //console.log(hexPolygon);
    if (!hexPolygon) {

        var coords = this.getHexCoords();
        var h_top = coords[1].lat;  // top-left
        var h_btm = coords[4].lat;  // bottom-right
        if ((h_btm > 85.051128514) || (h_top < -85.051128514))  return;

        var level = this.getLevel();
        var len = 6;    // == coords.length
        var gCoords = new Array(len);
        for (var idx = 0; idx < len; idx++) {
            gCoords[idx] = new google.maps.LatLng(coords[idx].lat, coords[idx].lon);
        }
        hexPolygon = new google.maps.Polygon({
            paths: gCoords,
            strokeColor: linecolor,
            strokeOpacity: lineotp,
            strokeWeight: lineweight,
            fillColor: fillcolor,
            fillOpacity: fillopt
                    });
        hexPolygon.setMap(map);
        polylines.push(hexPolygon);

        world.stamp[this.code] = hexPolygon;

    } else {
        hexPolygon.setOptions({
            strokeColor: linecolor,
            strokeOpacity: lineotp,
            strokeWeight: lineweight,
            fillColor: fillcolor,
            fillOpacity: fillopt
        });
        hexPolygon.setMap(map);
        polylines.push(hexPolygon);
    }

    // PostProcess
    world.fireOnDrawHex(this, hexPolygon, prop);
};
Zone.prototype.eraseHex = function (map) {
    var hexPolygon = World.get(map).stamp[this.code];
    if (hexPolygon) hexPolygon.setMap(null);
}

// EXPORT
GEOHEX.registOnDrawHex = World.reg;

})(this);