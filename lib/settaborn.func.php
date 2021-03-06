<?php
/**
 * スレッドあぼーんをオンオフする関数
 *
 * $set は、0(解除), 1(追加), 2(トグル)
 *
 * @access  public
 * @return  boolean  実行成否
 */
function settaborn($host, $bbs, $key, $set)
{
    global $_conf, $title_msg, $info_msg;

    // {{{ key.idx 読み込む
    
    $idxfile = P2Util::getKeyIdxFilePath($host, $bbs, $key);
    
    $data[0] = null;
    
    // データがあるなら読み込む
    if (file_exists($idxfile)) {
        $lines = file($idxfile);
        $l = rtrim($lines[0]);
        $data = explode('<>', $l);
    }
    
    // }}}

    // p2_threads_aborn.idx のパス取得
    $taborn_idx = P2Util::getThreadAbornFile($host, $bbs);
    
    if (false === FileCtl::make_datafile($taborn_idx, $_conf['p2_perm'])) {
        return false;
    }
    
    if (false === $taborn_lines = file($taborn_idx)) {
        return false;
    }
    
    $newlines = array();
    
    $aborn_attayo = false;
    $title_msg_pre = '';
    $info_msg_pre = '';
    
    if ($taborn_lines) {
        foreach ($taborn_lines as $line) {
            $line = rtrim($line);
            $lar = explode('<>', $line);
            if ($lar[1] == $key) {
                $aborn_attayo = true; // 既にあぼーん中である
                if ($set == 0 or $set == 2) {
                    $title_msg_pre = "+ あぼーん 解除しますた";
                    $info_msg_pre = "+ あぼーん 解除しますた";
                }
                continue;
            }
            if (!$lar[1]) { continue; } // keyのないものは不正データ
            $newlines[] = $line;
        }
    }
    
    // 新規データ追加
    if ($set == 1 or !$aborn_attayo && $set == 2) {
        $newdata = "$data[0]<>{$key}<><><><><><><><>";
        $newlines ? array_unshift($newlines, $newdata) : $newlines = array($newdata);
        $title_msg_pre = "○ あぼーん しますた";
        $info_msg_pre = "○ あぼーん しますた";
    }
    
    // 書き込む
    $cont = '';
    if ($newlines) {
        foreach ($newlines as $l) {
            $cont .= $l . "\n";
        }
    }
    if (false === file_put_contents($taborn_idx, $cont, LOCK_EX)) {
        p2die('cannot write file.');
        return false;
    }
    
    $GLOBALS['title_msg'] = $title_msg_pre;
    $GLOBALS['info_msg'] = $info_msg_pre;
    
    return true;
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
