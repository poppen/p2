<?php
/*
    p2 -  スレッド表示 -  フッタ部分 -  for read.php
*/

require_once P2_LIB_DIR . '/DataPhp.php';

//=====================================================================
// フッタ
//=====================================================================

$res_form_html ='';
$onmouse_showform_attrs = array();

if ($_conf['bottom_res_form'] and empty($diedat_msg_ht)) {

    $bbs        = $aThread->bbs;
    $key        = $aThread->key;
    $host       = $aThread->host;
    $rescount   = $aThread->rescount;
    $ttitle_en  = base64_encode($aThread->ttitle);
    
    $submit_value = '書き込む';

    // フォームのオプション読み込み
    require_once P2_LIB_DIR . '/post_options_loader.inc.php';

    $htm['resform_ttitle'] = sprintf(
        '<div style="padding:4px 0px;"><b class="thre_title">%s </b></div>',
        hs($aThread->ttitle_hc)
    );
    
    require_once P2_LIB_DIR . '/post_form.inc.php';

    // フォーム
    $res_form_html = <<<EOP
<div id="kakiko">
{$htm['post_form']}
</div>\n
EOP;

    // onMouseover="showResbar(event, true);"
    $onmouse_showform_attrs = array('onMouseover' => "document.getElementById('kakiko').style.display = 'block';");
}


// ============================================================
$dores_html = '';
$res_form_html_pb = '';

if ($aThread->rescount or (!empty($_GET['onlyone']) && !$aThread->diedat)) { // and (!$_GET['renzokupop'])

    if (!$aThread->diedat) {
        if (!empty($_conf['disable_res'])) {
            $dores_atag = P2View::tagA(
                $motothre_url,
                hs($dores_st),
                array(
                    'accesskey' => $_conf['pc_accesskey']['dores'],
                    'title' => 'アクセスキー[' . $_conf['pc_accesskey']['dores'] . ']',
                    'target' => '_blank'
                )
            );
            
        } else {
            $dores_qs = array(
                'host' => $aThread->host,
                'bbs'  => $aThread->bbs,
                'key'  => $aThread->key,
                'rescount' => $aThread->rescount,
                'ttitle_en' => base64_encode($aThread->ttitle)
            );
            $dores_uri = UriUtil::buildQueryUri('post_form.php', $dores_qs);
            
            $dores_onclick_qs = array_merge($dores_qs, array(
                'popup' => '1',
            ));
            if (defined('SID') && strlen(SID)) {
                $dores_onclick_qs[session_name()] = session_id();
            }
            $dores_onclick_uri = UriUtil::buildQueryUri('post_form.php', $dores_onclick_qs);
            
            $dores_atag = P2View::tagA(
                $dores_uri,
                hs($dores_st),
                array_merge(
                    array(
                        'accesskey' => $_conf['pc_accesskey']['dores'],
                        'title'     => 'アクセスキー[' . $_conf['pc_accesskey']['dores'] . ']',
                        'target'    => '_self',
                        'onClick'   => sprintf(
                            "return !openSubWin('%s',%s,1,0)",
                            str_replace("'", "\\'", $dores_onclick_uri), $STYLE['post_pop_size']
                        )
                    ),
                    $onmouse_showform_attrs
                )
            );
        }
        $dores_html = '<span style="white-space: nowrap;">' . $dores_atag . '</span>';
        $res_form_html_pb = $res_form_html;
    }
    
    $q_ichi_ht = '';
    if (isset($res1['body'])) {
        $q_ichi_ht = $res1['body'] . " | ";
    }
    
    // レスのすばやさ
    $spd_ht = '';
    if ($spd_st = $aThread->getTimePerRes() and $spd_st != '-') {
        $spd_ht = '<span class="spd" style="white-space: nowrap;" title="すばやさ＝時間/レス">' . $spd_st . '</span>';
    }

    // DAT容量
    $datsize_ht = sprintf('<span class="datsize" style="white-space: nowrap;" title="DAT容量">%s</span>', P2Util::getTranslatedUnitFileSize($aThread->getDatBytesFromLocalDat(false), 'KB'));
    // 500KB以上で強調表示
    if ($datsize / 1024 >= 500) {
        $datsize_ht = '<b>' . $datsize_ht . '</b>';
    }
    
    // {{{ フィルタヒットがあった場合、次Xと続きを読むを更新する
    
    if (!empty($GLOBALS['last_hit_resnum'])) {
        $read_navi_next_anchor = "";
        if ($GLOBALS['last_hit_resnum'] == $aThread->rescount) {
            $read_navi_next_anchor = "#r{$aThread->rescount}";
        }
        $after_rnum = $GLOBALS['last_hit_resnum'] + $rnum_range;
        $read_navi_next_ht = P2View::tagA(
            UriUtil::buildQueryUri($_conf['read_php'],
                array_merge(array(
                    'host' => $aThread->host,
                    'bbs'  => $aThread->bbs,
                    'key'  => $aThread->key,
                    'ls' => "{$GLOBALS['last_hit_resnum']}-{$after_rnum}",
                    'nt' => date('gis') // 再読込用のダミークエリー
                ), $offline_range_qs)
            ) . $read_navi_next_anchor,
            hs("{$next_st}{$rnum_range}")
        );
        
        // 「続きを読む」
        $read_footer_navi_new_ht = _getTudukiATag($aThread, $tuduki_st);
    }
    // }}}
    
    $all_atag = _getAllATag($aThread, $all_st);
    
    $latest_atag = _getLatestATag($aThread, $latest_st, $latest_show_res_num);
    
    // フッタHTML出力
    echo <<<EOP
<hr>
<table id="footer" class="toolbar" width="100%" style="padding:0px 10px 0px 0px;">
    <tr>
        <td align="left">
            {$q_ichi_ht}
            $all_atag 
            {$read_navi_previous_ht} 
            {$read_navi_next_ht} 
            $latest_atag
            {$goto_ht}
            | {$read_footer_navi_new_ht}
            | {$dores_html}
            {$spd_ht}
            {$datsize_ht}
        </td>
        <td align="right">
            {$p2frame_ht}
            {$toolbar_right_ht}
        </td>
        <td align="right">
            <a href="#header" title="ページ上部へ移動">▲</a>
        </td>
    </tr>
</table>
{$res_form_html_pb}
EOP;

    if ($diedat_msg_ht) {
        echo "<hr>$diedat_msg_ht<p>$motothre_ht</p>";
    }
}

if (!empty($_GET['showres'])) {
?>
	<script type="text/javascript">
	<!--
	document.getElementById('kakiko').style.display = 'block';
	//-->
	</script>
<?php
}
?>
</body></html>
<?php


//==============================================================================
// 関数（このファイル内でのみ利用）
//==============================================================================
/**
 * 全部 <a>
 *
 * @return  string  HTML
 */
function _getAllATag($aThread, $all_st)
{
    global $_conf;
    
    return $all_atag = P2View::tagA(
        UriUtil::buildQueryUri(
            $_conf['read_php'],
            array(
                'host' => $aThread->host,
                'bbs'  => $aThread->bbs,
                'key'  => $aThread->key,
                'ls'   => 'all'
            )
        ),
        hs($all_st),
        array('title' => sprintf('アクセスキー[%s]', $_conf['pc_accesskey']['all']))
    );
}

/**
 * 最新N <a>
 *
 * @return  string  HTML
 */
function _getLatestATag($aThread, $latest_st, $latest_show_res_num)
{
    global $_conf;
    
    return $latest_atag = P2View::tagA(
        UriUtil::buildQueryUri(
            $_conf['read_php'],
            array(
                'host' => $aThread->host,
                'bbs'  => $aThread->bbs,
                'key'  => $aThread->key,
                'ls'   => "l{$latest_show_res_num}"
            )
        ),
        hs("$latest_st{$latest_show_res_num}")
    );
}

/**
 * 「続きを読む」 <a>
 *
 * @return  string  HTML
 */
function _getTudukiATag($aThread, $tuduki_st)
{
    global $_conf;
    
    return P2View::tagA(
        UriUtil::buildQueryUri(
            $_conf['read_php'],
            array(
                'host' => $aThread->host,
                'bbs'  => $aThread->bbs,
                'key'  => $aThread->key,
                'ls'   => $GLOBALS['last_hit_resnum'] . '-',
                'offline' => '1'
            )
        ),
        hs($tuduki_st),
        array(
            'accesskey' => $_conf['pc_accesskey']['tuduki'],
            'title' => sprintf('アクセスキー[%s]', $_conf['pc_accesskey']['tuduki']),
            'style' => 'white-space: nowrap;'
        )
    );
}

/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: nil
 * End:
 */
// vim: set syn=php fenc=cp932 ai et ts=4 sw=4 sts=4 fdm=marker:
