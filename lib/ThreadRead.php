<?php
/**
 * p2 - ThreadRead クラス
 */
class ThreadRead extends Thread
{
    var $datlines;  // datから読み込んだラインを格納する配列
    
    var $resrange;  // array('start' => i, 'to' => i, 'nofirst' => bool)
    var $resrange_multi = array();
    var $resrange_readnum;
    var $resrange_multi_exists_next;
    
    var $onbytes;   // サーバから取得したdatサイズ
    var $diedat;    // サーバからdat取得しようとしてできなかった時にtrueがセットされる
    var $onthefly;  // ローカルにdat保存しないオンザフライ読み込みならtrue

    var $idcount;   // 配列。key は ID記号, value は ID出現回数
    var $backword_reses; // arary key は 参照先レス番号, value は参照元のレスの配列
    
    var $getdat_error_msg_ht = ''; // dat取得に失敗した時に表示されるメッセージ（HTML）
    
    var $old_host;  // ホスト移転検出時、移転前のホストを保持する

    /**
     * @constructor
     */
    function ThreadRead()
    {
    }

    /**
     * DATをダウンロード保存する
     *
     * @access  public
     * @return  boolean
     */
    function downloadDat()
    {
        global $_conf;
        
        // まちBBS
        if (P2Util::isHostMachiBbs($this->host)) {
            require_once P2_LIB_DIR . '/downloadDatMachiBbs.func.php';
            downloadDatMachiBbs($this);
            
        // JBBS@したらば
        } elseif (P2Util::isHostJbbsShitaraba($this->host)) {
            require_once P2_LIB_DIR . '/downloadDatShitaraba.func.php';
            downloadDatShitaraba($this);
        
        /*
        // cha2.net（→2ch互換として扱ってみる）
        } elseif (P2Util::isHostCha2($this->host)) {
            require_once P2_LIB_DIR . '/downloadDatCha2.func.php';
            downloadDatCha2($this);
        */

        // 2ch系
        } else {
            $this->getDatBytesFromLocalDat(); // $aThread->length をset

            // 2ch bbspink●読み
            if (P2Util::isHost2chs($this->host) && !empty($_GET['maru'])) {
                
                // ログインしてなければ or ログイン後、24時間以上経過していたら自動再ログイン
                if (
                    (!file_exists($_conf['sid2ch_php']) || !empty($_REQUEST['relogin2ch'])) 
                    or (filemtime($_conf['sid2ch_php']) < time() - 60*60*24)
                ) {
                    require_once P2_LIB_DIR . '/login2ch.func.php';
                    if (!login2ch()) {
                        $this->pushDownloadDatErrorMsgHtml($this->get2chDatError());
                        $this->diedat = true;
                        return false;
                    }
                }

                $this->downloadDat2chMaru();

            // 2chの過去ログ倉庫読み
            } elseif (!empty($_GET['kakolog']) && !empty($_GET['kakoget'])) {
                if ($_GET['kakoget'] == 1) {
                    $ext = '.dat.gz';
                } elseif ($_GET['kakoget'] == 2) {
                    $ext = '.dat';
                }
                $this->downloadDat2chKako($_GET['kakolog'], $ext);
                
            // 2ch or 2ch互換
            } else {
                // DATを差分DLする
                $this->downloadDat2ch($this->length);
            }

        }
        
        return true;
    }
    
    /**
     * @return  void
     */
    function pushDownloadDatErrorMsgHtml($getdat_error_msg_ht)
    {
        $this->getdat_error_msg_ht .= $getdat_error_msg_ht;
    }
    
    /**
     * HTTPヘッダレスポンスを読み込む
     *
     * @access  private
     * @param   resource  $fp  fsockopen で開いたファイルポインタ
     * @return  array|false
     */
    function freadHttpHeader($fp)
    {
        $h = array();
        
        while (!feof($fp)) {
            $l = fgets($fp, 8192);
            
            // ex) HTTP/1.1 304 Not Modified
            if (preg_match("|HTTP/1\.\d (\d+) (.+)\r\n|", $l, $matches)) {
                $h['code']      = $matches[1];
                $h['message']   = $matches[2];
                $h['HTTP']      = rtrim($l);
            }
            
            if (preg_match('/^(.+?): (.+)\r\n/', $l, $matches)) {
                $h['headers'][$matches[1]] = $matches[2];
                
            } elseif ($l == "\r\n") {
                if (!isset($h['code'])) {
                    return false;
                }
                return $h;
            }
        }
        
        return false;
    }
    
    /**
     * HTTPヘッダレスポンスの取得エラーを P2Util::pushInfoHtml() する
     *
     * @access  private
     * @return  void
     */
    function _pushInfoHtmlFreadHttpHeaderError($url)
    {
        global $_conf;

        P2Util::pushInfoHtml(
            sprintf(
                '<p>p2 info: %s からヘッダレスポンスを取得できませんでした。</p>',
                P2View::tagA(P2Util::throughIme($url), hs($url), array('target' => $_conf['ext_win_target']))
            )
        );
    }
    
    /**
     * HTTPヘッダレスポンスからファイルサイズを取得する
     *
     * @access  private
     * @param   array    $headers
     * @param   boolean  $zero_read
     * @return  integer|false
     */
    function getOnbytesFromHeader($headers, $zero_read = true)
    {
        if ($zero_read) {
            if (isset($headers['Content-Length'])) {
                if (preg_match("/^([0-9]+)/", $headers['Content-Length'], $matches)) {
                    return $onbytes = $matches[1];
                }
            }
            
        } else {
            if (isset($headers['Content-Range'])) {
                if (preg_match("/^bytes ([^\/]+)\/([0-9]+)/", $headers['Content-Range'], $matches)) {
                    return $onbytes = $matches[2];
                }
            }
        }
        
        return false;
    }
    
    /**
     * @access  private
     * @return  string
     */
    function getDatUrl($host, $bbs, $key)
    {
        if (P2Util::isHostCha2($host)) {
            $datUrl = sprintf('http://%s/cgi-bin/%s/dat/%s.dat', $host, $bbs, $key);
        } else {
            $datUrl = sprintf('http://%s/%s/dat/%s.dat', $host, $bbs, $key);
        }
        return $datUrl;
    }
    
    /**
     * 標準方法で 2ch互換 DAT を差分ダウンロードする
     *
     * @access  private
     * @return  true|string|false  取得できたか、更新がなかった場合はtrue（または"304 Not Modified"）を返す
     */
    function downloadDat2ch($from_bytes)
    {
        global $_conf;
        global $debug;
    
        if (!($this->host && $this->bbs && $this->key)) {
            return false;
        }

        $range_bytes = intval($from_bytes);
        
        if ($range_bytes == 0) {
            $zero_read = true;
        } else {
            $zero_read = false;
            $range_bytes = $from_bytes - 1;
        }

        //$url = 'http://' . $this->host . '/' . $this->bbs . '/dat/' . $this->key . '.dat';
        $url = $this->getDatUrl($this->host, $this->bbs, $this->key);
        
        $purl = parse_url($url);
        
        $purl['query'] = isset($purl['query']) ? '?' . $purl['query'] : '';

        // プロキシ
        if ($_conf['proxy_use']) {
            $send_host = $_conf['proxy_host'];
            $send_port = $_conf['proxy_port'];
            $send_path = $url;
        } else {
            $send_host = $purl['host'];
            $send_port = isset($purl['port']) ? $purl['port'] : null;
            $send_path = $purl['path'] . $purl['query'];
        }
        
        !$send_port and $send_port = 80;
        
        $request = 'GET ' . $send_path . " HTTP/1.0\r\n";
        $request .= "Host: " . $purl['host'] . "\r\n";
        $request .= "Accept: */*\r\n";
        //$request .= "Accept-Charset: Shift_JIS\r\n";
        //$request .= "Accept-Encoding: gzip, deflate\r\n";
        $request .= "Accept-Language: ja, en\r\n";
        $request .= "User-Agent: " . P2Util::getP2UA($withMonazilla = true) . "\r\n";
        if (!$zero_read) {
            $request .= "Range: bytes={$range_bytes}-\r\n";
        }
        $request .= "Referer: http://{$purl['host']}/{$this->bbs}/\r\n";
        if ($this->modified) {
            $request .= "If-Modified-Since: " . $this->modified . "\r\n";
        }
        
        // Basic認証用のヘッダ
        if (isset($purl['user']) && isset($purl['pass'])) {
            $request .= "Authorization: Basic " . base64_encode($purl['user'] . ":" . $purl['pass']) . "\r\n";
        }

        $request .= "Connection: Close\r\n";
        $request .= "\r\n";
        
        // WEBサーバへ接続
        $fp = @fsockopen($send_host, $send_port, $errno, $errstr, $_conf['fsockopen_time_limit']);
        if (!$fp) {
            P2Util::pushInfoHtml(
                sprintf(
                    '<p>サーバ接続エラー: %s (%s)<br>p2 info - %s に接続できませんでした。</div>',
                    hs($errstr), hs($errno),
                    P2View::tagA(P2Util::throughIme($url), hs($url), array('target' => $_conf['ext_win_target']))
                )
            );
            
            $this->diedat = true;
            return false;
        }
        
        // HTTPリクエスト送信
        fputs($fp, $request);
        
        // HTTPヘッダレスポンスを取得する
        $h = $this->freadHttpHeader($fp);
        if ($h === false) {
            fclose($fp);
            $this->_pushInfoHtmlFreadHttpHeaderError($url);
            $this->diedat = true;
            return false;
        }
        
        // {{{ HTTPコードをチェック
        
        $code = $h['code'];
        
        // 206 Partial Content
        if ($code == "200" || $code == "206") {
            // OK。何もしない
        
        // Found
        } elseif ($code == "302") {
        
            // ホストの移転を追跡
            require_once P2_LIB_DIR . '/BbsMap.php';
            $new_host = BbsMap::getCurrentHost($this->host, $this->bbs);
            if ($new_host != $this->host) {
                fclose($fp);
                $this->old_host = $this->host;
                $this->host = $new_host;
                return $this->downloadDat2ch($from_bytes);
                
            } else {
                fclose($fp);
                
                // 2007/06/11 302の時に、UAをMonazillaにしないでDATアクセスを試みると203が帰ってきて、
                // body中に'過去ログ ★'とあれば、●落ち中とみなすことにする。
                // 仕様の確証が取れていないので、このような判断でよいのかはっきりしない。
                // 203 Non-Authoritative Information
                // 過去ログ ★
                /*
名無し募集中。。。<><>2007/06/10(日) 13:29:51.68 0<> http://mlb.yahoo.co.jp/headlines/?a=2279 <br> くわわ＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞＞井川 <>★くわわメジャー昇格おめ 売上議論14001★
1001, 131428 (総レス数, サイズ)<><>1181480550000000 (最終更新)<><div style="color:navy;font-size:smaller;">|<br />| 中略<br />|</div><>
１００１<><>Over 1000 Thread<> このスレッドは１０００を超えました。 <br> もう書けないので、新しいスレッドを立ててくださいです。。。  <>
過去ログ ★<><>[過去ログ]<><div style="color:red;text-align:center;">■ このスレッドは過去ログ倉庫に格納されています</div><hr /><br />IE等普通のブラウザで見る場合 http://tubo.80.kg/tubo_and_maru.html<br />専用のブラウザで見る場合 http://www.monazilla.org/<br /><br />２ちゃんねる Viewer を使うと、すぐに読めます。 http://2ch.tora3.net/<br /><div style="color:navy;">この Viewer(通称●) の売上で、２ちゃんねるは設備を増強しています。<br />●が売れたら、新しいサーバを投入できるという事です。</div><br />よくわからない場合はソフトウェア板へGo http://pc11.2ch.net/software/<br /><br />モリタポ ( http://find.2ch.net/faq/faq2.php#c1 ) を持っていれば、50モリタポで表示できます。<br />　　　　こちらから → http://find.2ch.net/index.php?STR=dat:http://ex23.2ch.net/test/read.cgi/morningcoffee/1181449791/<br /><br /><hr /><>
                */
                $params = array();
                $params['timeout'] = $_conf['fsockopen_time_limit'];
                if ($_conf['proxy_use']) {
                    $params['proxy_host'] = $_conf['proxy_host'];
                    $params['proxy_port'] = $_conf['proxy_port'];
                }
                $req = new HTTP_Request($url, $params);
                $req->setMethod('GET');
                $err = $req->sendRequest(true);
                
                if (PEAR::isError($err)) {
                    //var_dump('error');
                    
                } else {
                    // レスポンスコードを検証
                    if ('203' == $req->getResponseCode()) {
                        $body2 = $req->getResponseBody();
                        $reason = null;
                        // $body2 "過去ログ ★<><>[過去ログ]<>「隊長! 過去ログ倉庫で、スレッド http://qb5.2ch.net/operate/kako/1103/11034/1103495887.html を発見しました。」<>過去ログ発見![改行]"
                        if (preg_match('/<>過去ログ発見!/', $body2)) {
                            //$reason = 'kakologsoko';
                        } elseif (preg_match('/過去ログ ★<>/', $body2)) {
                            $reason = 'datochi';
                        }
                        $this->downloadDat2chNotFound($reason);
                        return false;
                    }
                }
                
                $this->downloadDat2chNotFound();
                return false;
            }
        
        // Not Modified
        } elseif ($code == '304') {
            fclose($fp);
            $this->isonline = true;
            return '304 Not Modified';
        
        // Requested Range Not Satisfiable
        } elseif ($code == '416') {
            //echo "あぼーん検出";
            fclose($fp);
            $this->clearOnbytesModified();
            return $this->downloadDat2ch(0); // あぼーんを検出したので全部取り直し。
            
        // 予期しないHTTPコード。スレッドがないと判断
        } else {
            fclose($fp);
            $this->downloadDat2chNotFound();
            return false;
        }
        
        // }}}
        
        $r = $this->getOnbytesFromHeader($h['headers'], $zero_read);
        if ($r !== false) {
            $this->onbytes = $r;
        }
        
        if (isset($h['headers']['Last-Modified'])) {
            $this->modified = $h['headers']['Last-Modified'];
        }
        
        // bodyを読む
        $body = '';
        while (!feof($fp)) {
            $body .= fread($fp, 8192);
        }
        fclose($fp);
        
        // 末尾の改行であぼーんをチェックする
        if (!$zero_read) {
            if (substr($body, 0, 1) != "\n") {
                //echo "あぼーん検出";
                $this->clearOnbytesModified();
                return $this->downloadDat2ch(0); // あぼーんを検出したので全部取り直し。
            }
            $body = substr($body, 1);
        }
        
        FileCtl::make_datafile($this->keydat, $_conf['dat_perm']);

        $done = false;
        if ($fp = fopen($this->keydat, 'rb+')) {
            flock($fp, LOCK_EX);
            if (0 === fseek($fp, $from_bytes)) {
                if (false !== fwrite($fp, $body)) {
                    ftruncate($fp, $from_bytes + strlen($body));
                    $done = true;
                }
            }
            flock($fp, LOCK_UN);
            fclose($fp);
        }
        if (!$done) {
            trigger_error('cannot write file (' . $this->keydat . ')', E_USER_WARNING);
            die('Error: cannot write file. downloadDat2ch()');
            return false;
        }
        
        // {{{ 取得後サイズチェック
        
        $debug && $GLOBALS['profiler']->enterSection('dat_size_check');
        if ($zero_read == false && $this->onbytes) {
            $this->getDatBytesFromLocalDat(); // $aThread->length をset
            if ($this->onbytes != $this->length) {
                $onbytes = $this->onbytes;
                $this->clearOnbytesModified();
                P2Util::pushInfoHtml("p2 info: $onbytes/$this->length ファイルサイズが変なので、datを再取得しました<br>");
                $debug && $GLOBALS['profiler']->leaveSection('dat_size_check');
                return $this->downloadDat2ch(0); // datサイズは不正。全部取り直し。
            }
        }
        $debug && $GLOBALS['profiler']->leaveSection('dat_size_check');
        
        // }}}
        
        $this->isonline = true;
        return true;
        
        /*
        あぼーん検出漏れについて
        
        0. p2が読み込む 
        1. レスがあぼーんされる 
        2. (あぼーんされたレス-あぼーんテキスト)と全く同サイズのレスが書き込まれる 
        3. p2が読み込む 

        0-1-2-3が、完全に連続した時にあぼーん検出漏れはありうる。 
        */
    }
    
    /**
     * @access  private
     * @return  void
     */
    function clearOnbytesModified()
    {
        unset($this->onbytes);
        unset($this->modified);
    }
    
    /**
     * 2ch DATをダウンロードできなかったときに呼び出される
     *
     * @access  private
     * @param   string|null  $reason
     * @return  void
     */
    function downloadDat2chNotFound($reason = null)
    {
        // 2ch, bbspink, vip2ch.com ならread.cgiで確認
        if (P2Util::isHost2chs($this->host) || P2Util::isHostVip2ch($this->host)) {
            $this->pushDownloadDatErrorMsgHtml($this->get2chDatError($reason));
        }
        $this->diedat = true;
    }
    
    /**
     * 2ch●用 DATをダウンロードする
     *
     * @access  private
     * @return  true|string|false  取得できたか、更新がなかった場合はtrue（または"304 Not Modified"）を返す
     */
    function downloadDat2chMaru()
    {
        global $_conf;
        
        if (!($this->host && $this->bbs && $this->key && $this->keydat)) {
            return false;
        }
        
        include $_conf['sid2ch_php']; // $uaMona, $SID2ch がセットされる @see login2ch()
        if (!$uaMona || !$SID2ch) {
            return false;
        }
        
        $p2ua = sprintf('%s (%s)', $uaMona, P2Util::getP2UA($withMonazilla = false));
        
        //  GET /test/offlaw.cgi?bbs=板名&key=スレッド番号&sid=セッションID HTTP/1.1
        $SID2ch = urlencode($SID2ch);
        $url = 'http://' . $this->host . "/test/offlaw.cgi/{$this->bbs}/{$this->key}/?raw=0.0&sid={$SID2ch}";

        $purl = parse_url($url);
        
        $purl['query'] = isset($purl['query']) ? '?' . $purl['query'] : '';
        
        // プロキシ
        if ($_conf['proxy_use']) {
            $send_host = $_conf['proxy_host'];
            $send_port = $_conf['proxy_port'];
            $send_path = $url;
        } else {
            $send_host = $purl['host'];
            $send_port = isset($purl['port']) ? $purl['port'] : null;
            $send_path = $purl['path'] . $purl['query'];
        }
        
        !$send_port and $send_port = 80; // デフォルトを80

        $request = 'GET ' . $send_path . " HTTP/1.0" . "\r\n";
        $request .= "Host: " . $purl['host'] . "\r\n";
        $request .= "Accept-Encoding: gzip, deflate" . "\r\n";
        //$request .= "Accept-Language: ja, en" . "\r\n";
        $request .= "User-Agent: " . $p2ua . "\r\n";
        //$request .= 'X-2ch-UA: ' . P2Util::getP2UA($withMonazilla = false) . "\r\n";
        //$request .= "Range: bytes={$from_bytes}-" . "\r\n";
        $request .= "Connection: Close" . "\r\n";
        /*
        if ($modified) {
            $request .= "If-Modified-Since: $modified" . "\r\n";
        }
        */
        $request .= "\r\n";
        
        // WEBサーバへ接続
        $fp = fsockopen($send_host, $send_port, $errno, $errstr, $_conf['fsockopen_time_limit']);
        if (!$fp) {
            P2Util::pushInfoHtml(
                sprintf(
                    '<p>サーバ接続エラー: %s (%s)<br>p2 info - %s に接続できませんでした。</div>',
                    hs($errstr), hs($errno),
                    P2View::tagA(P2Util::throughIme($url), hs($url), array('target' => $_conf['ext_win_target']))
                )
            );
            
            $this->diedat = true;
            return false;
        }
        
        // HTTPリクエスト送信
        fputs($fp, $request);

        // HTTPヘッダレスポンスを取得する
        $h = $this->freadHttpHeader($fp);
        if ($h === false) {
            fclose($fp);
            $this->_pushInfoHtmlFreadHttpHeaderError($url);
            $this->diedat = true;
            return false;
        }
        
        // {{{ HTTPコードをチェック
        
        $code = $h['code'];
        
        // Partial Content
        if ($code == "200") {
            // OK。何もしない
        
        // Found
        } elseif ($code == "304") {
            fclose($fp);
            //$this->isonline = true;
            return "304 Not Modified";
            
        // 予期しないHTTPコード。なかったと判断する
        } else {
            fclose($fp);
            return $this->downloadDat2chMaruNotFound();
        }
        
        // }}}
        
        $isGzip = false;
        if (isset($h['headers']['Content-Encoding'])) {
            if (preg_match("/^(x-)?gzip/", $h['headers']['Content-Encoding'], $matches)) {
                $isGzip = true;
            }
        }
        if (isset($h['headers']['Last-Modified'])) {
            $lastmodified = $h['headers']['Last-Modified'];
        }
        if (isset($h['headers']['Content-Length'])) {
            if (preg_match("/^([0-9]+)/", $h['headers']['Content-Length'], $matches)) {
                $onbytes = $h['headers']['Content-Length'];
            }
        }
        // Transfer-Encoding: chunked
        $chunked = false;
        if (isset($h['headers']['Transfer-Encoding'])) {
            if ($h['headers']['Transfer-Encoding'] == "chunked") {
                $chunked = true;
            }
        }
        
        // bodyを読む
        $body = '';
        while (!feof($fp)) {
            $body .= fread($fp, 8192);
        }
        fclose($fp);
        
        $done_gunzip = false;
        
        // gzip圧縮なら
        if ($isGzip) {
            // gzip tempファイルに保存
            $gztempfile = $this->keydat . '.gz';
            FileCtl::mkdirFor($gztempfile);
            if (file_put_contents($gztempfile, $body, LOCK_EX) === false) {
                die("Error: cannot write file. downloadDat2chMaru()");
                return false;
            }
            
            // PHPで解凍読み込み
            if (extension_loaded('zlib')) {
                $body = FileCtl::getGzFileContents($gztempfile);
            // コマンドラインで解凍
            } else {
                // 既に存在するなら一時datをバックアップ退避
                $keydat_bak = $this->keydat . '.bak';
                if (file_exists($this->keydat)) {
                    if (file_exists($keydat_bak)) {
                        unlink($keydat_bak);
                    }
                    rename($this->keydat, $keydat_bak);
                }
                $rcode = 1;
                // 解凍する
                system("gzip -d $gztempfile", $rcode);
                // 解凍失敗ならバックアップを戻す
                if ($rcode != 0) {
                    if (file_exists($keydat_bak)) {
                        file_exists($this->keydat) and unlink($this->keydat);
                        rename($keydat_bak, $this->keydat);
                    }
                    $this->pushDownloadDatErrorMsgHtml("<p>p2 info - 2ちゃんねる過去ログ倉庫からのスレッド取り込みは、PHPの<a href=\"http://www.php.net/manual/ja/ref.zlib.php\">zlib拡張モジュール</a>がないか、systemでgzipコマンドが使用可能でなければできません。</p>");
                    // gztempファイルを捨てる
                    file_exists($gztempfile) and unlink($gztempfile);
                    
                    $this->diedat = true;
                    return false;
                    
                // 解凍成功なら
                } else {
                    file_exists($keydat_bak) and unlink($keydat_bak);
                    
                    $done_gunzip = true;
                }

            }
            // gzip tempファイルを捨てる
            file_exists($gztempfile) and unlink($gztempfile);
        }
        
        /*
        // -ERR もう　つかえません 
        if (preg_match('/^-ERR/', $body)) {
            return $this->downloadDat2chMaruNotFound();
        }
        */

        // -ERR 過去ログ倉庫で発見 ../operate/kako/1107/11073/1107376477.dat
        if (preg_match('{^-ERR 過去ログ倉庫で発見 \\.\\.([/a-z0-9]+)\\.dat}', $body, $m)) {
            $kakolog = 'http://' . $this->host . $m[1];
            return $this->downloadDat2chKako($kakolog, '.dat');
        }

        if (!$done_gunzip) {
            FileCtl::make_datafile($this->keydat, $_conf['dat_perm']);
            if (file_put_contents($this->keydat, $body, LOCK_EX) === false) {
                die("Error: cannot write file. downloadDat2chMaru()");
                return false;
            }
        }
        
        // クリーニング
        $marudatlines = @file($this->keydat);
        if ($marudatlines) {
            $firstline = array_shift($marudatlines);
            // チャンクとか
            if (!strstr($firstline, "+OK")) {
                $secondline = array_shift($marudatlines);
            }
            $cont = '';
            foreach ($marudatlines as $aline) {
                // チャンクエンコーディングが欲しいところ(HTTP 1.0でしのぐ)
                // http://jp.php.net/manual/ja/function.fsockopen.php#36703
                if ($chunked) {
                    $cont .= $aline;
                } else {
                    $cont .= $aline;
                }
            }
            FileCtl::make_datafile($this->keydat, $_conf['dat_perm']);
            if (file_put_contents($this->keydat, $cont, LOCK_EX) === false) {
                die("Error: cannot write file. downloadDat2chMaru()");
                return false;
            }
        }

        //$this->isonline = true;
        //$this->datochiok = 1;
        return true;
    }
    
    /**
     * ●IDでの取得ができなかったときに呼び出される
     *
     * @access  private
     * @return  boolean
     */
    function downloadDat2chMaruNotFound()
    {
        global $_conf;
        
        // 再チャレンジがまだなら、再チャレンジする。SIDが変更されてしまっている場合がある時のための自動チャレンジ。
        if (empty($_REQUEST['relogin2ch'])) {
            $_REQUEST['relogin2ch'] = true;
            return $this->downloadDat();
        }
        
        $remarutori_atag = P2View::tagA(
            UriUtil::buildQueryUri($_conf['read_php'],
                array(
                    'host' => $this->host,
                    'bbs'  => $this->bbs,
                    'key'  => $this->key,
                    'ls'   => $this->ls,
                    'maru' => 'true',
                    'relogin2ch' => '1',
                    UA::getQueryKey() => UA::getQueryValue()
                )
            ),
            '再取得を試みる'
        );
        $this->pushDownloadDatErrorMsgHtml("<p>p2 info - ●IDでのスレッド取得に失敗しました。[{$remarutori_atag}]</p>");
        $this->diedat = true;
        return false;
    }
    
    /**
     * 2chの過去ログ倉庫からdat.gzをダウンロード＆解凍する
     *
     * @access  private
     * @return  true|string|false  取得できたか、更新がなかった場合はtrue（または"304 Not Modified"）を返す
     */
    function downloadDat2chKako($uri, $ext)
    {
        global $_conf;

        $url = $uri . $ext;
    
        $purl = parse_url($url);
        
        // クエリー
        $purl['query'] = isset($purl['query']) ? '?' . $purl['query'] : '';
    
        // プロキシ
        if ($_conf['proxy_use']) {
            $send_host = $_conf['proxy_host'];
            $send_port = $_conf['proxy_port'];
            $send_path = $url;
        } else {
            $send_host = $purl['host'];
            $send_port = isset($purl['port']) ? $purl['port'] : null;
            $send_path = $purl['path'] . $purl['query'];
        }
        
        // デフォルトを80
        if (!$send_port) {
            $send_port = 80;
        }
    
        $request = 'GET ' . $send_path . " HTTP/1.0\r\n";
        $request .= "Host: " . $purl['host'] . "\r\n";
        $request .= "User-Agent: " . P2Util::getP2UA($withMonazilla = true) . "\r\n";
        $request .= "Connection: Close\r\n";
        //$request .= "Accept-Encoding: gzip\r\n";
        /*
        if ($modified) {
            $request .= "If-Modified-Since: $modified\r\n";
        }
        */
        $request .= "\r\n";
    
        // WEBサーバへ接続
        $fp = fsockopen($send_host, $send_port, $errno, $errstr, $_conf['fsockopen_time_limit']);
        if (!$fp) {
            P2Util::pushInfoHtml(
                sprintf(
                    '<p>サーバ接続エラー: %s (%s)<br>p2 info - %s に接続できませんでした。</div>',
                    hs($errstr), hs($errno),
                    P2View::tagA(P2Util::throughIme($url), hs($url), array('target' => $_conf['ext_win_target']))
                )
            );
            
            $this->diedat = true;
            return false;
        }
        
        // HTTPリクエスト送信
        fputs($fp, $request);
        
        // HTTPヘッダレスポンスを取得する
        $h = $this->freadHttpHeader($fp);
        if ($h === false) {
            fclose($fp);
            $this->_pushInfoHtmlFreadHttpHeaderError($url);
            $this->diedat = true;
            return false;
        }
        
        // {{{ HTTPコードをチェック
        
        $code = $h['code'];
        
        // Partial Content
        if ($code == "200") {
            // OK。何もしない
        
        // Not Modified
        } elseif ($code == "304") {
            fclose($fp);
            //$this->isonline = true;
            return "304 Not Modified";
        
        // 予期しないHTTPコード。なかったと判断
        } else {
            fclose($fp);
            $this->downloadDat2chKakoNotFound($uri, $ext);
            return false;
        }
        
        // }}}

        if (isset($h['headers']['Last-Modified'])) {
            $lastmodified = $h['headers']['Last-Modified'];
        }
        
        if (isset($h['headers']['Content-Length'])) {
            if (preg_match("/^([0-9]+)/", $h['headers']['Content-Length'], $matches)) {
                $onbytes = $h['headers']['Content-Length'];
            }
        }
        $isGzip = false;
        if (isset($h['headers']['Content-Encoding'])) {
            if (preg_match("/^(x-)?gzip/", $h['headers']['Content-Encoding'], $matches)) {
                $isGzip = true;
            }
        }

        // bodyを読む
        $body = '';
        while (!feof($fp)) {
            $body .= fread($fp, 8192);
        }
        fclose($fp);
        
        $done_gunzip = false;
        
        if ($isGzip) {
            $gztempfile = $this->keydat . '.gz';
            FileCtl::mkdirFor($gztempfile);
            if (file_put_contents($gztempfile, $body, LOCK_EX) === false) {
                die("Error: cannot write file. downloadDat2chKako()");
                return false;
            }
            if (extension_loaded('zlib')) {
                $body = FileCtl::getGzFileContents($gztempfile);
            } else {
                // 既に存在するなら一時バックアップ退避
                $keydat_bak = $this->keydat . '.bak';
                if (file_exists($this->keydat)) {
                    if (file_exists($keydat_bak)) {
                        unlink($keydat_bak);
                    }
                    rename($this->keydat, $keydat_bak);
                }
                $rcode = 1;
                // 解凍
                system("gzip -d $gztempfile", $rcode);
                if ($rcode != 0) {
                    if (file_exists($keydat_bak)) {
                        if (file_exists($this->keydat)) {
                            unlink($this->keydat);
                        }
                        // 失敗ならバックアップ戻す
                        rename($keydat_bak, $this->keydat);
                    }
                    $this->pushDownloadDatErrorMsgHtml("<p>p2 info - 2ちゃんねる過去ログ倉庫からのスレッド取り込みは、PHPの<a href=\"http://www.php.net/manual/ja/ref.zlib.php\">zlib拡張モジュール</a>がないか、systemでgzipコマンドが使用可能でなければできません。</p>");
                    // gztempファイルを捨てる
                    file_exists($gztempfile) and unlink($gztempfile);
                    $this->diedat = true;
                    return false;
                    
                } else {
                    if (file_exists($keydat_bak)) {
                        unlink($keydat_bak);
                    }
                    $done_gunzip = true;
                }

            }
            // tempファイルを捨てる
            file_exists($gztempfile) and unlink($gztempfile);
        }

        if (!$done_gunzip) {
            FileCtl::make_datafile($this->keydat, $_conf['dat_perm']);
            if (false === file_put_contents($this->keydat, $body, LOCK_EX)) {
                die("Error: cannot write file. downloadDat2chKako()");
                return false;
            }
        }

        //$this->isonline = true;
        return true;
    }
    
    /**
     * 過去ログを取得できなかったときに呼び出される
     *
     * @access  private
     * @return  void
     */
    function downloadDat2chKakoNotFound($uri, $ext)
    {
        global $_conf;
        
        if ($ext == '.dat.gz') {
            //.dat.gzがなかったら.datでもう一度
            return $this->downloadDat2chKako($uri, '.dat');
        }
        
        $kakolog_ht = '';
        if (!empty($_GET['kakolog'])) {
            $kakolog_uri = "{$_GET['kakolog']}.html";
            $atag = P2View::tagA($kakolog_uri,
                hs($kakolog_uri),
                array('target' => $_conf['bbs_win_target'])
            );
            $kakolog_ht = "<p>{$atag}</p>";
        }
        $this->pushDownloadDatErrorMsgHtml("<p>p2 info - 2ちゃんねる過去ログ倉庫からのスレッド取り込みに失敗しました。</p>");
        $this->pushDownloadDatErrorMsgHtml($kakolog_ht);
        $this->diedat = true;
    }
    
    /**
     * 2chのdatを取得できなかった原因を返す
     *
     * @access  private
     * @param   string|null  $reason
     * @return  string  エラーメッセージHTML（原因がわからない場合は空で返す）
     */
    function get2chDatError($reason = null, $read_cgi = 'read.cgi')
    {
        global $_conf;
        
        // ホスト移転検出で変更したホストを元に戻す
        if (!empty($this->old_host)) {
            $this->host = $this->old_host;
            $this->old_host = null;
        }
        
        $read_url = "http://{$this->host}/test/{$read_cgi}/{$this->bbs}/{$this->key}/";
        
        // {{{ read.cgi からHTMLを取得
        
        $read_response_html = '';
        
        if (!$reason) {
            require_once P2_LIB_DIR . '/wap.class.php';
            $wap_ua = new WapUserAgent;
            // ここは、"Monazilla/" をつけるとNG
            $wap_ua->setAgent(P2Util::getP2UA($withMonazilla = false));
            $wap_ua->setTimeout($_conf['fsockopen_time_limit']);
            $wap_req = new WapRequest;
            $wap_req->setUrl($read_url);
            if ($_conf['proxy_use']) {
                $wap_req->setProxy($_conf['proxy_host'], $_conf['proxy_port']);
            }
            $wap_res = $wap_ua->request($wap_req);
        
            if (!$wap_res or !$wap_res->is_success()) {
                $atag = P2View::tagA(P2Util::throughIme($wap_req->url), hs($wap_req->url), array('target' => $_conf['ext_win_target']));
                $msg = sprintf(
                    '<div>Error: %s %s<br>p2 info - %s に接続できませんでした。</div>',
                    hs($wap_res->code),
                    hs($wap_res->message),
                    $atag
                );
                P2Util::pushInfoHtml($msg);

            } else {
                $read_response_html = $wap_res->content;
            }
            unset($wap_ua, $wap_req, $wap_res);
        }
        
        // read.htmlの場合はread.soで再試行する
        // http://sports11.2ch.net/test/read.cgi/bicycle/1206427705/
        // 　(正常に表示されない場合は、URL 中の read.cgi を read.so に変えて下さい。
        // 　あるいは、掲示板トップにある「read.cgi モード切替」のリンクから「CGI モード」に設定して下さい)
        // 　JavaScript が有効でないと見られません。。。
        if (preg_match('/read.cgi を read.so/', $read_response_html)) {
            return $this->get2chDatError($reason, $read_cgi = 'read.so');
        }
        
        // }}}
        // {{{ 取得したHTML（$read_response_html）を解析して、原因を見つける
        
        $dat_response_status = '';
        $dat_response_msg_ht = '';

        $kakosoko_match = '/このスレッドは過去ログ倉庫に格.{1,2}されています/';
        $vip2ch_kakosoko_match = '/過去ログ倉庫に格.{1,2}されています/';

        $naidesu_match = "{<title>そんな板orスレッドないです。</title>}";
        $error3939_match = "{<title>２ちゃんねる error 3939</title>}"; // 過去ログ倉庫でhtml化の時（他にもあるかも、よく知らない）
        
        //<a href="http://qb5.2ch.net/sec2chd/kako/1091/10916/1091634596.html">
        //<a href="../../../../mac/kako/1004/10046/1004680972.html">
        //$kakohtml_match = "{<a href=\"\.\./\.\./\.\./\.\./([^/]+/kako/\d+(/\d+)?/(\d+)).html\">}";
        $kakohtml_match = "{/([^/]+/kako/\d+(/\d+)?/(\d+)).html\">}";
        $waithtml_match = "/html化されるのを待っているようです。/";
        
        //
        // <title>がこのスレッドは過去ログ倉庫に
        //
        if ($reason == 'datochi' or preg_match($kakosoko_match, $read_response_html, $matches)) {
            $dat_response_status = "このスレッドは過去ログ倉庫に格納されています。";
            $marutori_ht = '';
            //if (file_exists($_conf['idpw2ch_php']) || file_exists($_conf['sid2ch_php'])) {
                
                $marutori_ht = ' ' . $this->getMarutoriHtml();
                
            //} else {
            //    $marutori_ht = "<a href=\"login2ch.php?b={$_conf['b']}\" target=\"subject\">●IDログイン</a>";
            //}
            $dat_response_msg_ht = "<p>2ch info - このスレッドは過去ログ倉庫に格納されています。 {$marutori_ht}</p>";
        
        //    
        // <title>がそんな板orスレッドないです。or error 3939
        //
        } elseif (
            preg_match($naidesu_match, $read_response_html, $matches) 
            || preg_match($error3939_match, $read_response_html, $matches)
            || preg_match($vip2ch_kakosoko_match, $read_response_html, $matches)
        ) {
            if (preg_match($kakohtml_match, $read_response_html, $matches)) {
                $dat_response_status = "隊長! 過去ログ倉庫で、html化されたスレッドを発見しました。";
                $kakolog_uri = "http://{$this->host}/{$matches[1]}";

                $read_kako_url = $this->getReadKakologUri($kakolog_uri);

                $soko_atag = P2View::tagA($kakolog_uri . '.html',
                    'スレッド ' . $matches[3] . '.html',
                    array('target' => $_conf['bbs_win_target'])
                );
                
                $dat_response_msg_ht = sprintf(
                    '<p>2ch info - 隊長! 過去ログ倉庫で、%s を発見しました。 [<a href="%s">p2に取り込んで読む</a>]</p>',
                    $soko_atag,
                    hs($read_kako_url)
                );
                
            } elseif (preg_match($waithtml_match, $read_response_html, $matches)) {
                $dat_response_status = "隊長! スレッドはhtml化されるのを待っているようです。";
                $marutori_ht = ' ' . $this->getMarutoriHtml();
                $dat_response_msg_ht = "<p>2ch info - 隊長! スレッドはhtml化されるのを待っているようです。 {$marutori_ht}</p>";
                
            } else {
                if (!empty($_GET['kakolog'])) {
                    $dat_response_status = "そんな板orスレッドないです。";
                    
                    $kako_html_url = $_GET['kakolog'] . ".html";
                    $read_kako_url = $this->getReadKakologUri($_GET['kakolog']);

                    $attrs = array();
                    if ($_conf['bbs_win_target']) {
                        $attrs['target'] = $_conf['bbs_win_target'];
                    }
                    $read_kako_atag  = P2View::tagA($kako_html_url, null, $attrs);
                    $read_by_p2_atag = P2View::tagA($read_kako_url, 'p2にログを取り込んで読む');
                    
                    $dat_response_msg_ht = "<p>2ch info - そんな板orスレッドないです。</p>";
                    $dat_response_msg_ht .= "<p>$read_kako_atag [$read_by_p2_atag]</p>";
                    
                } else {
                    $dat_response_status = "そんな板orスレッドないです。";
                    $dat_response_msg_ht = "<p>2ch info - そんな板orスレッドないです。</p>";
                }
            }
        
        // 原因が分からない場合でも、とりあえず過去ログ取り込みのリンクを維持している。と思う。あまり覚えていない 2005/2/27 aki
        } elseif (!empty($_GET['kakolog'])) {
            $dat_response_status = '';
            
            $kako_html_url = $_GET['kakolog'] . '.html';
            $read_kako_url = $this->getReadKakologUri($_GET['kakolog']);
            $attrs = array();
            if ($_conf['bbs_win_target']) {
                $attrs['target'] = $_conf['bbs_win_target'];
            }
            $read_kako_atag  = P2View::tagA($kako_html_url, null, $attrs);
            $read_by_p2_atag = P2View::tagA($read_kako_url, 'p2にログを取り込んで読む');
            
            $dat_response_msg_ht = "<p>$read_kako_atag [$read_by_p2_atag]</p>";
        }
        
        // }}}
        
        return $dat_response_msg_ht;
    }
    
    /**
     * @access  private
     * @param   string  $kakolog  過去ログURIから.htmlを外した文字列 @see $kakohtml_match
     * @return  string
     */
    function getReadKakologUri($kakolog)
    {
        global $_conf;
        
        return UriUtil::buildQueryUri($_conf['read_php'],
            array(
                'host' => $this->host,
                'bbs'  => $this->bbs,
                'key'  => $this->key,
                'ls'   => $this->ls,
                'kakolog' => $kakolog,
                'kakoget' => '1',
                UA::getQueryKey() => UA::getQueryValue()
            )
        );
    }
    
    /**
     * @access  private
     * @return  string  HTML
     */
    function getMarutoriHtml()
    {
        global $_conf;
        
        $marutori_atag = P2View::tagA(
            UriUtil::buildQueryUri($_conf['read_php'],
                array(
                    'host' => $this->host,
                    'bbs'  => $this->bbs,
                    'key'  => $this->key,
                    'ls'   => $this->ls,
                    'maru' => 'true',
                    UA::getQueryKey() => UA::getQueryValue()
                )
            ),
            hs('●IDでp2に取り込む')
        );
        return "[$marutori_atag]";
    }
    
    /**
     * >>1のみをプレビュー表示するためのHTMLを取得する（オンザフライに対応）
     *
     * @access  public
     * @return  string|false
     */
    function previewOne()
    {
        global $_conf, $ptitle_ht;

        if (!($this->host && $this->bbs && $this->key)) {
            return false;
        }
        
        $first_line = '';
        
        // ローカルdatから取得
        if (is_readable($this->keydat)) {
            $fd = fopen($this->keydat, "rb");
            $first_line = fgets($fd, 32800);
            fclose($fd);
        }
        
        if ($first_line) {
        
            // be.2ch.net ならEUC→SJIS変換
            if (P2Util::isHostBe2chNet($this->host)) {
                $first_line = mb_convert_encoding($first_line, 'SJIS-win', 'eucJP-win');
            }
            
            $first_datline = rtrim($first_line);
            if (strstr($first_datline, "<>")) {
                $datline_sepa = "<>";
            } else {
                $datline_sepa = ",";
                $this->dat_type = "2ch_old";
            }
            $d = explode($datline_sepa, $first_datline);
            $this->setTtitle($d[4]);
            
            // 便宜上
            if (!$this->readnum) {
                $this->readnum = 1;
            }
        }
        
        // ローカルdatなければオンラインから
        if (!$first_line) {
        
            $url = $this->getDatUrl($this->host, $this->bbs, $this->key);
            
            $purl = parse_url($url);
            
            $purl['query'] = isset($purl['query']) ? '?' . $purl['query'] : '';
            
            // プロキシ
            if ($_conf['proxy_use']) {
                $send_host = $_conf['proxy_host'];
                $send_port = $_conf['proxy_port'];
                $send_path = $url;
            } else {
                $send_host = $purl['host'];
                $send_port = isset($purl['port']) ? $purl['port'] : null;
                $send_path = $purl['path'] . $purl['query'];
            }
            
            // デフォルトを80
            !$send_port and $send_port = 80;
    
            $request = 'GET ' . $send_path . " HTTP/1.0\r\n";
            $request .= "Host: " . $purl['host'] . "\r\n";
            $request .= 'User-Agent: ' . P2Util::getP2UA($withMonazilla = true) . "\r\n";
            // $request .= "Range: bytes={$from_bytes}-\r\n";
    
            // Basic認証用のヘッダ
            if (isset($purl['user']) && isset($purl['pass'])) {
                $request .= "Authorization: Basic " . base64_encode($purl['user'] . ":" . $purl['pass']) . "\r\n";
            }
            
            $request .= "Connection: Close\r\n";
            $request .= "\r\n";
            
            // WEBサーバへ接続
            $fp = fsockopen($send_host, $send_port, $errno, $errstr, $_conf['fsockopen_time_limit']);
            if (!$fp) {
                P2Util::pushInfoHtml(
                    sprintf(
                        '<p>サーバ接続エラー: %s (%s)<br>p2 info - %s に接続できませんでした。</p>',
                        $errstr, $errno,
                        P2View::tagA(P2Util::throughIme($url), hs($url), array('target' => $_conf['ext_win_target']))
                    )
                );
                
                $this->diedat = true;
                return false;
            }
            
            // HTTPリクエスト送信
            fputs($fp, $request);
            
            // HTTPヘッダレスポンスを取得する
            $h = $this->freadHttpHeader($fp);
            if ($h === false) {
                fclose($fp);
                $this->_pushInfoHtmlFreadHttpHeaderError($url);
                $this->diedat = true;
                return false;
            }
            
            // {{{ HTTPコードをチェック
            
            $code = $h['code'];
            
            // Partial Content
            if ($code == "200") {
                // OK。何もしない
            
            // 予期しないHTTPコード。なかったと判断する
            } else {
                fclose($fp);
                $this->previewOneNotFound();
                return false;
            }
            
            // }}}

            if (isset($h['headers']['Content-Length'])) {
                if (preg_match("/^([0-9]+)/", $h['headers']['Content-Length'], $matches)) {
                    $onbytes = $h['headers']['Content-Length'];
                }
            }

            // bodyを一行目だけ読む
            $first_line = fgets($fp, 32800);
            fclose($fp);
            
            // be.2ch.net ならEUC→SJIS変換
            if (P2Util::isHostBe2chNet($this->host)) {
                $first_line = mb_convert_encoding($first_line, 'SJIS-win', 'eucJP-win');
            }
            
            $first_datline = rtrim($first_line);
            if (strstr($first_datline, '<>')) {
                $datline_sepa = '<>';
            } else {
                $datline_sepa = ',';
                $this->dat_type = '2ch_old';
            }
            $d = explode($datline_sepa, $first_datline);
            $this->setTtitle($d[4]);
            
            $this->onthefly = true;
        }
        
        // 厳密にはオンザフライではないが、個人にとっては（既読記録がされないという意味で）オンザフライ
        if (!$this->isKitoku()) {
            $this->onthefly = true;
        }
        
        $body = '';
        if (!empty($this->onthefly)) {
            // PC
            if (UA::isPC()) {
                $body .= '<div><span class="onthefly">プレビュー</span></div>';
            // 携帯
            } else {
                $body .= '<div><font size="-1" color="#00aa00">ﾌﾟﾚﾋﾞｭｰ</font></div>';
            }
        }
        
        UA::isPC() and $body .= '<dl>';
        
        require_once P2_LIB_DIR . '/ShowThread.php';
        
        // PC
        if (UA::isPC()) {
            require_once P2_LIB_DIR . '/ShowThreadPc.php';
            $aShowThread = new ShowThreadPc($this);
        // 携帯
        } else {
            require_once P2_LIB_DIR . '/ShowThreadK.php';
            $aShowThread = new ShowThreadK($this);
        }
        
        $body .= $aShowThread->transRes($first_line, 1); // 1を表示
        
        UA::isPC() and $body .= "</dl>\n";
        
        return $body;
    }
    
    /**
     * >>1をプレビューでスレッドデータが見つからなかったときに呼び出される
     *
     * @access  private
     * @return  void
     */
    function previewOneNotFound()
    {
        // 2ch, bbspink ならread.cgiで確認
        if (P2Util::isHost2chs($this->host)) {
            $this->pushDownloadDatErrorMsgHtml($this->get2chDatError());
        }
        $this->diedat = true;
    }
    
    /**
     * @access  private
     * @return  array
     */
    function getStartToFromLs($ls, &$nofirst)
    {
        // 範囲指定で分割
        $lr = explode('-', $ls);
        
        // 範囲指定があれば
        if (sizeof($lr) > 1) {
            if (!$start = intval($lr[0])) {
                $start = 1;
            }
            if (!$to = intval($lr[1])) {
                $to = $this->rescount;
            }
        
        // 範囲指定がなければ
        } else {
        
            // レス番指定
            if (intval($ls) > 0) {
                $start = intval($ls);
                $to = intval($ls);
                $nofirst = true;
            
            // 指定がない or 不正な場合は、allと同じ表示にする
            } else {
                $start = 1;
                $to = $this->rescount;
            }
        }
        
        // 反転
        if ($start > $to) {
            $start_t = $start;
            $start = $to;
            $to = $start_t;
        }
        
        return array($start, $to);
    }
    
    /**
     * @access  public
     * @return  boolean
     */
    function inResrangeMulti($num)
    {
        foreach ($this->resrange_multi as $ls) {
            if ($ls['start'] <= $num and $num <= $ls['to']) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * @access  private
     * @return  integer
     */
    function countResrangeMulti($nofirst = false)
    {
        $c = array();
        foreach ($this->resrange_multi as $ls) {
            for ($i = $ls['start']; $i <= $ls['to']; $i++) {
                $c[$i] = true;
            }
        }
        return count($c);
    }
    
    /**
     * $lsを分解して start と to と nofirst を求めてセットする
     *
     * @access  public
     * @return  void
     */
    function lsToPoint()
    {
        global $_conf;

        $to = false;
        $nofirst = false;
        
        /*
        if (!empty($_GET['onlyone'])) {
            $this->ls = '1';
        }
        */
        
        $this->ls = str_replace(' ', '+', $this->ls);
        
        if ($this->ls != 'all') {
            $this->ls = preg_replace('/[^0-9,\-\+ln]/', '', $this->ls);
        }
        $ls = $this->ls;
        
        // nを含んでいる場合は、>>1を表示しない（$nofirst）
        if (strstr($ls, 'n')) {
            $nofirst = true;
            $ls = preg_replace("/n/", "", $ls);
        }
        
        // l指定があれば（最近N件の指定）
        if (substr($ls, 0, 1) == "l") {
            $ln = intval(substr($ls, 1));
            if ($_conf['ktai']) {
                if ($ln > $_conf['k_rnum_range']) {
                    $ln = $_conf['k_rnum_range'];
                }
            }
            $start = $this->rescount - $ln + 1;
            if ($start < 1) {
                $start = 1;
            }
            $to = $this->rescount;
            
        // all指定なら
        } elseif ($ls == "all") {
            $start = 1;
            $to = $this->rescount;
            
        } else {
            
            $lss = preg_split('/[,+ ]/', $ls, -1, PREG_SPLIT_NO_EMPTY);
            
            // マルチ指定なら
            if (sizeof($lss) > 1) {
                $nofirst = true;
                
                foreach ($lss as $v) {
                    list($start_t, $to_t) = $this->getStartToFromLs($v, $dummy_nofirst);
                    
                    $this->resrange_multi[] = array('start' => $start_t, 'to' => $to_t);
                    
                    if (empty($start) || $start > $start_t) {
                        $start = $start_t;
                    }
                    if (empty($to) || $to < $to_t) {
                        $to = $to_t;
                    }
                }
            
            // 普通指定なら
            } else {
                list($start, $to) = $this->getStartToFromLs($ls, $nofirst);
            }
        }
        
        // 新着まとめ読みの表示数制限
        if (isset($GLOBALS['rnum_all_range']) and $GLOBALS['rnum_all_range'] > 0) {
        
            /*
            ■携帯の新着まとめ読みが、ちょっきしで終わった時に、の「続きor更新」判定問題

            リミット < スレの表示範囲
            次リミットは　0
            スレの表示範囲を終える前にリミット数消化
            →続き

            リミット > スレの表示範囲
            次リミットは +
            リミット数が残っている間に、スレの表示範囲を終えた
            →更新

            リミット = スレの表示範囲
            次リミットは 0
            スレの表示範囲丁度でリミットを消化した
            →続き? 更新?
            続きの場合も更新の場合もある。逐次処理のため、
            他のスレの残り新着数があるかどうかが不明で判定できない。
            */
            
            // リミットがスレの表示範囲より小さい場合は、スレの表示範囲をリミットに合わせる
            $limit_to = $start + $GLOBALS['rnum_all_range'] - 1;
            
            if ($limit_to < $to) {
                $to = $limit_to;
            
            // スレの表示範囲丁度でリミットを消化した場合
            } elseif ($limit_to == $to) {
                $GLOBALS['_is_eq_limit_to_and_to'] = true;
            }
            
            // 次のリミットは、今回のスレの表示範囲分を減らした数
            $GLOBALS['rnum_all_range'] = $GLOBALS['rnum_all_range'] - ($to - $start) -1;
            
            //print_r("$start, $to, {$GLOBALS['rnum_all_range']}");
        
        } else {
            // 携帯用の表示数制限
            if ($_conf['ktai']) {
                /*
                if ($start + $_conf['k_rnum_range'] -1 <= $to) {
                    $to = $start + $_conf['k_rnum_range'] -1;
                }
                */
                
                // マルチ時の携帯表示数制限は別処理
                if (!$this->resrange_multi) {
                    // 次X件では、前一つを含み、実質+1となるので、1つおまけする
                    if ($start + $_conf['k_rnum_range'] <= $to) {
                        $to = $start + $_conf['k_rnum_range'];
                    }
                }
                
                // フィルタリング時は、全レス適用となる（$_filter_range で別途処理される）
                if (isset($GLOBALS['word'])) {
                    $start = 1;
                    $to = $this->rescount;
                    $nofirst = false;
                }
            }
        }
        
        if ($this->resrange_multi) {
            $page = isset($_REQUEST['page']) ? max(1, intval($_REQUEST['page'])) : 1;
            $reach = $page * $GLOBALS['_conf']['k_rnum_range'];
            if ($reach < $this->countResrangeMulti()) {
                $this->resrange_multi_exists_next = true;
            }
        } else {
            $this->resrange_readnum = $to;
        }
        
        $this->resrange = array('start' => $start, 'to' => $to, 'nofirst' => $nofirst);
    }
    
    /**
     * Datを読み込む
     * $this->datlines を set する
     *
     * @access  public
     * @return  boolean  実行成否
     */
    function readDat()
    {
        global $_conf;
        
        if (!file_exists($this->keydat)) {
            return false;
        }
        
        if ($this->datlines = file($this->keydat)) {

            // be.2ch.net ならEUC→SJIS変換
            // 念のためSJISとUTF-8も文字コード判定の候補に入れておく
            // ・・・が、文字化けしたタイトルのスレッドで誤判定があったので、指定しておく
            if (P2Util::isHostBe2chNet($this->host)) {
                //mb_convert_variables('SJIS-win', 'eucJP-win,SJIS-win,UTF-8', $this->datlines);
                mb_convert_variables('SJIS-win', 'eucJP-win', $this->datlines);
            }

            if (!strstr($this->datlines[0], '<>')) {
                $this->dat_type = '2ch_old';
            }
        }
        
        $this->rescount = sizeof($this->datlines);
        
        return true;
    }
    
    /**
     * datlineをexplodeする
     *
     * @access  public
     * @param   string  $aline
     * @return  array
     */
    function explodeDatLine($aline)
    {
        global $_conf;
        
        if (!$aline = rtrim($aline)) {
            return array();
        }
        
        $stripped = false;
        if ($_conf['strip_tags_trusted_dat'] || !P2Util::isTrustedHost($this->host)) {
            require_once P2_LIB_DIR . '/HTML/StripTags.php';
            $HTML_StripTags = new HTML_StripTags;
            $aline = $HTML_StripTags->cleanup($aline);
            $stripped = true;
        }
        
        if ($this->dat_type == '2ch_old') {
            $parts = explode(',', $aline);
        } else {
            $parts = explode('<>', $aline);
        }
        
        if (!$stripped && P2Util::isHost2chs($this->host)) {
            // iframe を削除。2chが正常化して必要なくなったらこのコードは外したい。2005/05/19
            isset($parts[3]) and $parts[3] = preg_replace('{<(iframe|script)( .*?)?>.*?</\\1>}i', '', $parts[3]);
        }
        
        return $parts;
    }
    
    /**
     * デフォルトのdat取得失敗エラーメッセージHTMLを取得する
     *
     * @access public
     * @param void
     * @return string
     */
    function getDefaultGetDatErrorMessageHTML()
    {
        global $_conf;

        $diedat_msg = '<p><b>p2 info - 板サーバから最新のスレッド情報を取得できませんでした。</b>';
        $diedat_msg .= '</p>';

        return $diedat_msg;
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
