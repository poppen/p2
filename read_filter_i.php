<?php
/* vim: set fileencoding=cp932 ai et ts=4 sw=4 sts=0 fdm=marker: */
/* mi: charset=Shift_JIS */

// p2 - 携帯版レスフィルタリング

require_once './conf/conf.inc.php';
require_once './iphone/conf.inc.php';


$_login->authorize(); // ユーザ認証

// {{{ スレッド情報

$host = geti($_GET['host']);
$bbs  = geti($_GET['bbs']);
$key  = geti($_GET['key']);
$ttitle = base64_decode(geti($_GET['ttitle_en']));
$ttitle_back_ht = isset($_SERVER['HTTP_REFERER'])
    ? '<a href="' . hs($_SERVER['HTTP_REFERER']) . '" title="戻る">' . hs($ttitle) . '</a>'
    : hs($ttitle);

if (P2Validate::host($host) || P2Validate::bbs($bbs) || P2Validate::key($key)) {
    p2die('不正な引数です');
}

// }}}
// {{{ 前回フィルタ値読み込み

require_once P2_LIB_DIR . '/filectl.class.php';

$cachefile = $_conf['pref_dir'] . '/p2_res_filter.txt';

$res_filter = array();
if (file_exists($cachefile) and $res_filter_cont = file_get_contents($cachefile)) {
    $res_filter = unserialize($res_filter_cont);
}

$field  = array('whole' => '', 'msg' => '', 'name' => '', 'mail' => '', 'date' => '', 'id' => '', 'beid' => '', 'belv' => '');
$match  = array('on' => '', 'off' => '');
$method = array('and' => '', 'or' => '', 'just' => '', 'regex' => '', 'similar' => '');

$field[$res_filter['field']]   = ' selected';
$match[$res_filter['match']]   = ' selected';
$method[$res_filter['method']] = ' selected';

// }}}

$hr = P2View::getHrHtmlK();
$body_at = P2View::getBodyAttrK();

// 検索フォームページ HTML表示

P2Util::headerNoCache();
P2View::printDoctypeTag();
?>
<html lang="ja">
<head>
<?php
P2View::printExtraHeadersHtml();
echo <<<EOF
    <link rel="stylesheet" type="text/css" href="./iui/read.css">
    <title>スレ内検索</title>
</head>
<body{$body_at}>
<div class="toolbar">
<h1>{$ttitle_back_ht}</h1>
<a id="backButton" class="button" href="iphone.php">TOP</a>
</div>


<form class="panel" id="header" method="get" action="{$_conf['read_php']}" accept-charset="{$_conf['accept_charset']}">
<h2>検索ワード</h2>
<filedset>
<input type="hidden" name="detect_hint" value="◎◇">
<input type="hidden" name="host" value="{$host}">
<input type="hidden" name="bbs" value="{$bbs}">
<input type="hidden" name="key" value="{$key}">
<input type="hidden" name="ls" value="all">
<input type="hidden" name="offline" value="1">
<input class="serch" id="word" name="word">
<input class="whitebutton" type="submit" name="s1" value="検索">
</filedset>
<br>
<h2>検索オプション</h2>
<filedset>
<select class="serch" id="field" name="field">
 <option value="whole"{$field['whole']}>全体</option>
 <option value="msg"{$field['msg']}>メッセージ</option>
 <option value="name"{$field['name']}>名前</option>
 <option value="mail"{$field['mail']}>メール</option>
 <option value="date"{$field['date']}>日付</option>
 <option value="id"{$field['id']}>ID</option>
 <!-- <option value="belv"{$field['belv']}>ポイント</option> -->
</select>
に
<select class="serch" id="method" name="method">
 <option value="or"{$method['or']}>いずれか</option>
 <option value="and"{$method['and']}>すべて</option>
 <option value="just"{$method['just']}>そのまま</option>
 <option value="regex"{$method['regex']}>正規表現</option>
</select>
を
<select class="serch" id="match" name="match">
 <option value="on"{$match['on']}>含む</option>
 <option value="off"{$match['off']}>含まない</option>
</select><br>
<input class="whitebutton" type="submit" name="s2" value="検索">

{$_conf['k_input_ht']}
</form>

</filedset>
</div>
</body>
</html>
EOF;
