<?php
// p2 スレッドサブジェクト表示関数 携帯, iPhone用
// for subject.php

/**
 * スレッド一覧を表示する (<tr>〜</tr>)
 *
 * @access  public
 * @return  void
 */
function sb_print_k(&$aThreadList)
{
    global $_conf, $sb_view, $p2_setting, $STYLE;
    global $sb_view;
    
    if (!$aThreadList->threads) {
        if ($aThreadList->spmode == 'fav' && $sb_view == 'shinchaku') {
            if (UA::isIPhoneGroup()) {
                ?><p>お気にスレに新着なかったぽ</p><?php
            } else {
                ?><p>お気にｽﾚに新着なかったぽ</p><?php
            }
        } else {
            if (UA::isIPhoneGroup()) {
                ?><p>該当サブジェクトはなかったぽ</p><?php
            } else {
                ?><p>該当ｻﾌﾞｼﾞｪｸﾄはなかったぽ</p><?php
            }
        }
        return;
    }
    
    // 変数 ================================================
    
    // >>1 表示
    $onlyone_bool = false;
    
    /*
    // ニュース系の板なら適用
    if (ereg('news', $aThreadList->bbs) || $aThreadList->bbs == 'bizplus' || $aThreadList->spmode == 'news') {
        // 倉庫は除く
        if ($aThreadList->spmode != "soko") {
            $onlyone_bool = true;
        }
    }
    */
    
    // 板名
    if ($aThreadList->spmode and $aThreadList->spmode != "taborn" and $aThreadList->spmode != "soko") {
        $ita_name_bool = true;
    } else {
        $ita_name_bool = false;
    }

    /*
    // {{{ 新着ソート（現在未使用）
    
    $qs = array(
        'sort'      => 'midoku',
        'norefresh' => '1',
        UA::getQueryKey() => UA::getQueryValue()
    );
    // スペシャルモード時
    if ($aThreadList->spmode) {
        $qs['spmode'] = $aThreadList->spmode;
        // あぼーんなら
        if ($aThreadList->spmode == "taborn" or $aThreadList->spmode == "soko") {
            $qs['host'] = $aThreadList->host;
            $qs['bbs']  = $aThreadList->bbs;
        }
    } else {
        $qs['host'] = $aThreadList->host;
        $qs['bbs']  = $aThreadList->bbs;
    }

    $midoku_sort_ht = P2View::tagA(
        UriUtil::buildQueryUri($_conf['subject_php'], $qs),
        '新着'
    );

    // }}}
    */

    //=====================================================
    // ボディ
    //=====================================================

    // spmodeがあればクエリー追加
    if ($aThreadList->spmode) {$spmode_q = "&amp;spmode={$aThreadList->spmode}";}

    $i = 0;
    foreach ($aThreadList->threads as $aThread) {
    
        $i++;
        $midoku_ari = "";
        $anum_ht = ""; // #r1

        $bbs_q = "&amp;bbs=" . $aThread->bbs;
        $key_q = "&amp;key=" . $aThread->key;

        if ($aThreadList->spmode != "taborn") {
            if (!$aThread->torder) { $aThread->torder = $i; }
        }

        // {{{ 新着レス数
        
        $unum_ht = '';
        
        // 既得済み
        if ($aThread->isKitoku()) {
            $unum_ht = "{$aThread->unum}";
        
            $anum = $aThread->rescount - $aThread->unum + 1 - $_conf['respointer'];
            if ($anum > $aThread->rescount) { $anum = $aThread->rescount; }
            $anum_ht = "#r{$anum}";
            
            // 新着あり
            if ($aThread->unum > 0) { 
                $midoku_ari = true;
                if (UA::isIPhoneGroup()) {
                    $unum_ht = "{$aThread->unum}";
                } else {
                    $unum_ht = "<font color=\"#ff6600\">{$aThread->unum}</font>";
                }
            }
        
            // subject.txtにない時
            if (!$aThread->isonline) {
                // 誤動作防止のためログ削除操作をロック
                $unum_ht = "-"; 
            }
            
            if (UA::isIPhoneGroup()) {
                $unum_ht = '<font class="unum">' . $unum_ht . '</font>';
            } else {
                $unum_ht = '[' . $unum_ht . ']';
            }
        }
        
        // }}}
        
        // 新規スレ
        $unum_new_ht = '';
        if ($aThread->new) { 
            if (UA::isIPhoneGroup()) {
                // $unum_ht = '<font color="#0000ff">●</font>';
                $unum_ht = '';
                $unum_new_ht = '<img class="unew" src="iui/icon_new.png">';
            } else {
                $unum_ht = '<font color="#ff0000">新</font>';
            }
        }

        // {{{ 板名
        
        $ita_name_ht = '';
        if ($ita_name_bool) {
            $ita_name = $aThread->itaj ? $aThread->itaj : $aThread->bbs;
            
            // 全角英数カナスペースを半角に
            if ($_conf['k_save_packet']) {
                $ita_name = mb_convert_kana($ita_name, 'rnsk');
            }
            
            /*
            $ita_name_ht = sprintf('(%s)',
                P2View::tagA(
                    UriUtil::buildQueryUri($_conf['subject_php'],
                        array(
                            'host' => $aThread->host,
                            'bbs'  => $aThread->bbs,
                            UA::getQueryKey() => UA::getQueryValue()
                        )
                    ),
                    hs($ita_name)
                )
            );
            */
            if (UA::isIPhoneGroup()) {
                $ita_name_ht = sprintf(' <span class="ita">(%s)</span>', hs($ita_name));
            } else {
                $ita_name_ht = sprintf('(%s)', hs($ita_name));
            }
        }
        
        // }}}
        
        // torder(info) =================================================
        /*
        // お気にスレ
        if ($aThread->fav) { 
            $torder_st = "<b>{$aThread->torder}</b>";
        } else {
            $torder_st = $aThread->torder;
        }
        $torder_ht = "<a id=\"to{$i}\" class=\"info\" href=\"info.php?host={$aThread->host}{$bbs_q}{$key_q}{$_conf['k_at_a']}\">{$torder_st}</a>";
        */
        $torder_ht = $aThread->torder;
        
        // title =========================================================
        $rescount_qs = array('rc' => $aThread->rescount);
        $offline_qs  = array();
        
        // dat倉庫 or 殿堂なら
        if ($aThreadList->spmode == 'soko' || $aThreadList->spmode == 'palace') { 
            $rescount_qs = array();
            $offline_qs  = array('offline' => '1');
            $anum_ht = '';
        }
        
        // タイトル未取得なら
        if (!$aThread->ttitle_ht) {
            // 見かけ上のタイトルなので携帯対応URLである必要はない
            //if (P2Util::isHost2chs($aThread->host)) {
            //    $aThread->ttitle_ht = "http://c.2ch.net/z/-/{$aThread->bbs}/{$aThread->key}/";
            //} else {
                $aThread->ttitle_ht = "http://{$aThread->host}/test/read.cgi/{$aThread->bbs}/{$aThread->key}/";
            //}
        }

        // 全角英数カナスペースを半角に
        if ($_conf['k_save_packet']) {
            $aThread->ttitle_ht = mb_convert_kana($aThread->ttitle_ht, 'rnsk');
        }
        
        // 総レス数
        if (UA::isIPhoneGroup()) {
            $rescount_ht = '<font class="sbnum"> ' . $aThread->rescount . '</font>';
        } else {
            $rescount_ht = ' (' . $aThread->rescount . ')';
        }

        $similarity_ht = '';
        if ($aThread->similarity) {
            $similarity_ht = sprintf(' %0.1f%%', $aThread->similarity * 100);
        }
        
        // 新規スレ
        if ($aThread->new) { 
            $classtitle_q = ' class="thre_title_new"';
        } else {
            $classtitle_q = ' class="thre_title"';
        }

        $thre_url = UriUtil::buildQueryUri($_conf['read_php'],
            array_merge(array(
                'host' => $aThread->host,
                'bbs'  => $aThread->bbs,
                'key'  => $aThread->key,
                UA::getQueryKey() => UA::getQueryValue()
            ), $rescount_qs, $offline_qs)
        ) . $anum_ht;
        
        // オンリー>>1
        $onlyone_url = UriUtil::buildQueryUri($_conf['read_php'],
            array_merge(array(
                'host' => $aThread->host,
                'bbs'  => $aThread->bbs,
                'key'  => $aThread->key,
                'onlyone' => '1',
                'k_continue' => '1',
                UA::getQueryKey() => UA::getQueryValue()
            ), $rescount_qs)
        );
        
        if ($onlyone_bool) {
            $one_ht = "<a href=\"{$onlyone_url}\">&gt;&gt;1</a>";
        }
        
        if (P2Util::isHost2chs($aThreadList->host) and !$aThread->isKitoku()) {
            if ($GLOBALS['_conf']['k_sb_show_first'] == 1) {
                $thre_url = $onlyone_url;
            } elseif ($GLOBALS['_conf']['k_sb_show_first'] == 2) {
                $thre_url .= '&amp;ls=1-';
            }
        }
        
        // アクセスキー
        /*
        $access_ht = "";
        if ($aThread->torder >= 1 and $aThread->torder <= 9) {
            $access_ht = " {$_conf['accesskey_for_k']}=\"{$aThread->torder}\"";
        }
        */

        if (UA::isIPhoneGroup()) {
            // お気にマーク設定
            $favvalue      = !empty($aThread->fav) ? 0 : 1;
            $favtitle   = $favvalue ? 'お気にスレに追加' : 'お気にスレから外す';
            $itaj_hs = htmlspecialchars($aThread->itaj, ENT_QUOTES);
    
            $favmark    = !empty($aThread->fav) ? '★' : '+';
            if ($favmark  == '★') {
                $favmark = '<img src="iui/icon_del.png">';
            } else {
                $favmark = '<img src="iui/icon_add.png">';
            }
    
            $sid_qs = array();
            $sid_q = '';
            if (defined('SID') && strlen(SID)) {
                $sid_qs[session_name()] = session_id();
                $sid_q = hs('&' . session_name() . '=' . session_id());
            }
            
            $setFavUri = UriUtil::buildQueryUri('info_i.php',
                array_merge(array(
                    'host' => $aThread->host,
                    'bbs'  => $aThread->bbs,
                    'key'  => $aThread->key,
                    'ttitle_en' => base64_encode($aThread->ttitle),
                    'setfav' => $favvalue
                ), $sid_qs)
            );
            $setFavUri_hs = hs($setFavUri);
        }

        //====================================================================================
        // スレッド一覧 table ボディ HTMLプリント <tr></tr> 
        //====================================================================================
        if (UA::isIPhoneGroup()) {
            ?><li><?php

            echo "<span class=\"plus\" id=\"{$aThread->torder}\" ><a href=\"{$setFavUri_hs}\" target=\"info\" onClick=\"return setFavJsNoStr('host={$aThread->host}{$bbs_q}{$key_q}{$ttitle_en_q}{$sid_q}', '{$favvalue}', {$STYLE['info_pop_size']}, 'read', 'this',{$aThread->torder});\" title=\"{$favtitle}\">{$favmark}</a></span>";

            // ボディ
            echo <<<EOP
 <a href="{$thre_url}" class="ttitle">{$unum_new_ht}{$aThread->ttitle_ht}{$ita_name_ht}{$rescount_ht}{$similarity_ht}</a>{$unum_ht}</li>
EOP;
        } else {
            echo <<<EOP
<div>
    {$unum_ht}{$aThread->torder}.<a href="{$thre_url}">{$aThread->ttitle_ht}{$rescount_ht}{$similarity_ht}</a>{$ita_name_ht}
</div>
EOP;
        }
    }

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
