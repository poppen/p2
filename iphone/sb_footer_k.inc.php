<?php
// p2 - サブジェクト - フッタHTMLを表示する 携帯
// for subject.php

$word_qs = _getWordQs();

$allfav_ht = '<p>' . _getAllFavAtag($aThreadList, $sb_view) . '</p>';

// {{{ ページタイトル部分HTML設定

if ($aThreadList->spmode == 'taborn') {
	$ptitle_ht = <<<EOP
	<a href="{$ptitle_url}" {$_conf['accesskey']}="{$_conf['k_accesskey']['up']}">{$_conf['k_accesskey']['up']}.<b>{$aThreadList->itaj}</b></a>（ｱﾎﾞﾝ中）
EOP;
} elseif ($aThreadList->spmode == 'soko') {
	$ptitle_ht = <<<EOP
	<a href="{$ptitle_url}" {$_conf['accesskey']}="{$_conf['k_accesskey']['up']}">{$_conf['k_accesskey']['up']}.<b>{$aThreadList->itaj}</b></a>（dat倉庫）
EOP;
} elseif (!empty($ptitle_url)) {
	$ptitle_ht = <<<EOP
	<a href="{$ptitle_url}"><b>{$ptitle_hs}</b></a>
EOP;
} else {
	$ptitle_ht = "<b>{$ptitle_hs}</b>";
}

// }}}
// {{{ ナビ HTML設定

$mae_ht = '';
if ($disp_navi['from'] > 1) {
    $qs = array(
        'host'    => $aThreadList->host,
        'bbs'     => $aThreadList->bbs,
        'spmode'  => $aThreadList->spmode,
        'norefresh' => '1',
        'from'    => $disp_navi['mae_from'],
        'sb_view' => geti($_REQUEST['sb_view']),
        UA::getQueryKey() => UA::getQueryValue()
    );
    $qs = array_merge($word_qs, $qs);
    $mae_ht = P2View::tagA(
        P2Util::buildQueryUri($_conf['subject_php'], $qs),
        hs("前") // {$_conf['k_accesskey']['prev']}.前
        //,array($_conf['accesskey'] => $_conf['k_accesskey']['prev'])
    );
}

$tugi_ht = '';
if ($disp_navi['tugi_from'] <= $sb_disp_all_num) {
    $qs = array(
        'host'    => $aThreadList->host,
        'bbs'     => $aThreadList->bbs,
        'spmode'  => $aThreadList->spmode,
        'norefresh' => '1',
        'from'    => $disp_navi['tugi_from'],
        'sb_view' => geti($_REQUEST['sb_view']),
        UA::getQueryKey() => UA::getQueryValue()
    );
    $qs = array_merge($word_qs, $qs);
    $tugi_ht = P2View::tagA(
        P2Util::buildQueryUri($_conf['subject_php'], $qs),
        hs("次") // {$_conf['k_accesskey']['next']}.次
        //,array($_conf['accesskey'] => $_conf['k_accesskey']['next'])
    );
}

if ($disp_navi['from'] == $disp_navi['end']) {
	$sb_range_on = $disp_navi['from'];
} else {
	$sb_range_on = "{$disp_navi['from']}-{$disp_navi['end']}";
}
$sb_range_st = "{$sb_range_on}/{$sb_disp_all_num} ";

$k_sb_navi_ht = '';
if (!$disp_navi['all_once']) {
	$k_sb_navi_ht = "{$sb_range_st}{$mae_ht} {$tugi_ht}";
}

// }}}

// dat倉庫
// スペシャルモードでなければ、またはあぼーんリストなら
$dat_soko_ht = _getDatSokoAtag($aThreadList);

// あぼーん中のスレッド
$taborn_link_atag = _getTabornLinkAtag($aThreadList, $ta_num);

// 新規スレッド作成
$buildnewthread_atag = _getBuildNewThreadAtag($aThreadList);

// {{{ ソート変更 （新着 レス No. タイトル 板 すばやさ 勢い Birthday ☆）

$sorts = array('midoku' => '新着', 'res' => 'ﾚｽ', 'no' => 'No.', 'title' => 'ﾀｲﾄﾙ');
if ($aThreadList->spmode and $aThreadList->spmode != 'taborn' and $aThreadList->spmode != 'soko') {
    $sorts['ita'] = '板';
}
if ($_conf['sb_show_spd']) {
    $sorts['spd'] = 'すばやさ';
}
if ($_conf['sb_show_ikioi']) {
    $sorts['ikioi'] = '勢い';
}
$sorts['bd'] = 'Birthday';
if ($_conf['sb_show_fav'] and $aThreadList->spmode != 'taborn') {
    $sorts['fav'] = '☆';
}

$htm['change_sort'] = "<form method=\"get\" action=\"{$_conf['subject_php']}\">";
$htm['change_sort'] .= P2View::getInputHiddenKTag();
$htm['change_sort'] .= '<input type="hidden" name="norefresh" value="1">';
// spmode時
if ($aThreadList->spmode) {
    $htm['change_sort'] .= "<input type=\"hidden\" name=\"spmode\" value=\"{$aThreadList->spmode}\">";
}
// spmodeでない、または、spmodeがあぼーん or dat倉庫なら
if (!$aThreadList->spmode || $aThreadList->spmode == "taborn" || $aThreadList->spmode == "soko") {
    $htm['change_sort'] .= "<input type=\"hidden\" name=\"host\" value=\"{$aThreadList->host}\">";
    $htm['change_sort'] .= "<input type=\"hidden\" name=\"bbs\" value=\"{$aThreadList->bbs}\">";
}

if (!empty($_REQUEST['sb_view'])) {
    $htm['change_sort'] .= sprintf('<input type="hidden" name="sb_view" value="%s">', hs($_REQUEST['sb_view']));
}

$htm['change_sort'] .= 'ソート:<select name="sort">';
foreach ($sorts as $k => $v) {
    $selected = '';
    if ($GLOBALS['now_sort'] == $k) {
        $selected = ' selected';
    }
    $htm['change_sort'] .= "<option value=\"{$k}\"{$selected}>{$v}</option>";
}
$htm['change_sort'] .= '</select>';
$htm['change_sort'] .= '<input type="submit" value="変更"></form>';

// }}}

// {{{ HTMLプリント

/*
echo "<hr>";
echo $k_sb_navi_ht;

require_once P2_LIB_DIR . '/sb_toolbar_k.funcs.php'; // getShinchakuMatomeATag()
echo getShinchakuMatomeATag();
echo $allfav_ht;
echo "<p>";
echo $dat_soko_ht;
echo $taborn_link_atag;
echo $buildnewthread_atag;
echo "</p>";
echo '<p>'. $htm['change_sort'] . '</p>';
//echo "<hr>";
echo "<p><a {$_conf['accesskey']}=\"0\" href=\"index.php{$_conf['k_at_q']}\">TOP</a></p>";
echo '</body></html>';
*/
/*
$k_sb_navi_ht は下の３と同じ
{$sb_range_st}
*/
$foot_sure = ""; 
if ($dat_soko_ht) $foot_sure .= "<span class=\"soko\">{$dat_soko_ht}</span>";
if ($buildnewthread_atag) $foot_sure .= "<span class=\"build\">{$buildnewthread_atag}</span>";
if ($allfav_ht) $foot_sure .= "<span class=\"all\">{$allfav_ht}</span>";
if ($taborn_link_atag) $foot_sure .= "<span class=\"abon\">{$taborn_link_atag}</span>";
if ($mae_ht) $foot_sure .= "<span class=\"mae\">{$mae_ht}</span>";
if ($tugi_ht) $foot_sure .= "<span class=\"tugi\">{$tugi_ht}</span>";

echo <<<IUI
{$htm['change_sort']} 
<div id="foot">
  <div class="foot_sure">
    {$foot_sure}
  </div>
</div>
<p><a id="backButton"class="button" href="iphone.php">TOP</a></p>
</body></html>
IUI;

// }}}


//================================================================================
// 関数（このファイル内でのみ利用）
//================================================================================
/**
 * @return  array
 */
function _getWordQs()
{
    $word_qs = array();
    if (!empty($GLOBALS['wakati_words'])) {
        $word_qs = array(
            'detect_hint' => '◎◇',
            'method' => 'similar',
            'word'   => $GLOBALS['wakati_word']
        );
    } elseif (isset($GLOBALS['word'])) {
        $word_qs = array(
            'detect_hint' => '◎◇',
            'word'   => $GLOBALS['word']
        );
    }
    return $word_qs;
}

/**
 * @return  string  <a>
 */
function _getAllFavAtag($aThreadList, $sb_view)
{
    global $_conf;
    
    $allfav_atag = '';
    if ($aThreadList->spmode == 'fav' && $sb_view == 'shinchaku') {
        $uri = P2Util::buildQueryUri($_conf['subject_php'],
            array(
                'spmode' => 'fav',
                'norefresh' => '1',
                UA::getQueryKey() => UA::getQueryValue()
            )
        );
        $allfav_atag = P2View::tagA($uri, hs("全てのお気にｽﾚを表示"));
    }
    return $allfav_atag;
}

/**
 * @return  string  <a>
 */
function _getTabornLinkAtag($aThreadList, $ta_num)
{
    global $_conf;
    
    $taborn_link_atag = '';
    if (!empty($ta_num)) {
        $uri = P2Util::buildQueryUri($_conf['subject_php'], array(
            'host'   => $aThreadList->host,
            'bbs'    => $aThreadList->bbs,
            'norefresh' => '1',
            'spmode' => 'taborn',
            UA::getQueryKey() => UA::getQueryValue()
        ));
        $taborn_link_atag = P2View::tagA($uri, hs("ｱﾎﾞﾝ中({$ta_num})"));
    }
    return $taborn_link_atag;
}

/**
 * @return  string  <a>
 */
function _getBuildNewThreadAtag($aThreadList)
{
    $buildnewthread_atag = '';
    if (!$aThreadList->spmode and !P2Util::isHostKossoriEnq($aThreadList->host)) {
        $uri = P2Util::buildQueryUri('post_form_i.php', array(
            'host'   => $aThreadList->host,
            'bbs'    => $aThreadList->bbs,
            'newthread' => '1',
            UA::getQueryKey() => UA::getQueryValue()
        ));
        $buildnewthread_atag = P2View::tagA($uri, hs("ｽﾚ立て"));
    }
    return $buildnewthread_atag;
}

/**
 * @return  string  <a>
 */
function _getDatSokoAtag($aThreadList)
{
    global $_conf;
    
    $dat_soko_atag = '';
    if (!$aThreadList->spmode or $aThreadList->spmode == "taborn") {
        $uri = P2Util::buildQueryUri($_conf['subject_php'], array(
            'host'   => $aThreadList->host,
            'bbs'    => $aThreadList->bbs,
            'norefresh' => '1',
            'spmode' => 'soko',
            UA::getQueryKey() => UA::getQueryValue()
        ));
        $dat_soko_atag = P2View::tagA($uri, hs('dat倉庫'));
    }
    return $dat_soko_atag;
}
