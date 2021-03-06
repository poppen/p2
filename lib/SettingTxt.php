<?php
// {{{ SettingTxt

/**
 * p2 - 2ch の SETTING.TXT を扱うクラス
 * http://news19.2ch.net/newsplus/SETTING.TXT
 *
 * @created  2006/02/27
 */
class SettingTxt
{
    // {{{ properties
    
    // @access  public
    var $setting_array = array(); // SETTING.TXTをパースした連想配列
    
    /**#@+
     * @access  private
     */
    var $host;
    var $bbs;
    var $url;           // SETTING.TXT のURL
    var $setting_txt;   // SETTING.TXT ローカル保存ファイルパス
    
    // p2_kb_setting.srd $this->setting_array を serialize() したキャッシュデータファイルパス
    var $setting_srd;

    var $cache_interval;
    /**#@-*/

    // }}}
    
    /**
     * @constructor
     */
    function SettingTxt($host, $bbs)
    {
        $this->cache_interval = 60 * 60 * 12; // キャッシュは12時間有効
        
        $this->host = $host;
        $this->bbs =  $bbs;
        
        $dat_bbs_dir_s = P2Util::datDirOfHostBbs($this->host, $this->bbs);
        $this->setting_txt = $dat_bbs_dir_s . 'SETTING.TXT';
        $this->setting_srd = $dat_bbs_dir_s . 'p2_kb_setting.srd';
        
        $this->url = 'http://' . $this->host . '/' . $this->bbs . '/SETTING.TXT';
        
        // したらばのlivedoor移転に対応。読込先をlivedoorとする。
        //$this->url = P2Util::adjustHostJbbsShitaraba($this->url);
        
        // SETTING.TXT をダウンロード＆セットする
        $this->dlAndSetData();
    }

    /**
     * SETTING.TXT をダウンロード＆セットする
     *
     * @access  private
     * @return  void
     */
    function dlAndSetData()
    {
        $this->downloadSettingTxt();

        $this->setting_array = $this->readSettingArrayFromSettingSrd();
    }

    /**
     * SETTING.TXT をダウンロードして、パースして、キャッシュする
     *
     * @access  public
     * @return  true|null|false  成功|更新なし（キャッシュ）|失敗
     */
    function downloadSettingTxt()
    {
        global $_conf;

        $perm = $_conf['dl_perm'] ? $_conf['dl_perm'] : 0606;

        FileCtl::mkdirFor($this->setting_txt); // 板ディレクトリが無ければ作る
        
        $modified = null;
        if (file_exists($this->setting_srd) && file_exists($this->setting_txt)) {
        
            // 更新しない場合は、その場で抜けてしまう
            if (!empty($_GET['norefresh']) || isset($_REQUEST['word'])) {
                return null;
                
            // キャッシュが新しい場合も抜ける
            } elseif ($this->isSettingSrdCacheFresh()) {
                return null;
            }
            
            $modified = gmdate('D, d M Y H:i:s', filemtime($this->setting_txt)) . ' GMT';
        }

        // DL
        /*
        // PHP5
        if (!class_exists('HTTP_Request', false)) {
            require 'HTTP/Request.php';
        }
        */
        require_once 'HTTP/Request.php';
        
        $params = array();
        $params['timeout'] = $_conf['fsockopen_time_limit'];
        if ($_conf['proxy_use']) {
            $params['proxy_host'] = $_conf['proxy_host'];
            $params['proxy_port'] = $_conf['proxy_port'];
        }
        $req = new HTTP_Request($this->url, $params);
        $modified && $req->addHeader('If-Modified-Since', $modified);
        $req->addHeader('User-Agent', 'Monazilla/1.00 (' . $_conf['p2uaname'] . '/' . $_conf['p2version'] . ')');
        
        $response = $req->sendRequest();

        $error_msg = null;
        if (PEAR::isError($response)) {
            $error_msg = $response->getMessage();
        } else {
            $code = $req->getResponseCode();

            if ($code == 302) {
                // ホストの移転を追跡
                require_once P2_LIB_DIR . '/BbsMap.php';
                $new_host = BbsMap::getCurrentHost($this->host, $this->bbs);
                if ($new_host != $this->host) {
                    $aNewSettingTxt = new SettingTxt($new_host, $this->bbs);
                    return $aNewSettingTxt->downloadSettingTxt();
                }
            }

            if (!($code == 200 || $code == 206 || $code == 304)) {
                //var_dump($req->getResponseHeader());
                $error_msg = $code;
            }
        }

        // DLエラー
        if (strlen($error_msg)) {
            P2Util::pushInfoHtml(
                sprintf(
                    '<div>Error: %s<br>p2 info - %s に接続できませんでした。</div>',
                    hs($error_msg),
                    P2View::tagA(
                        P2Util::throughIme($this->url),
                        hs($this->url),
                        array('target' => $_conf['ext_win_target'])
                    )
                )
            );
            touch($this->setting_txt); // DL失敗した場合(404)も touch する
            touch($this->setting_srd);
            return false;
        }

        $body = $req->getResponseBody();

        // DL成功して かつ 更新されていたら保存
        if ($body && $code != 304) {

            // したらば or be.2ch.net ならEUCをSJISに変換
            if (P2Util::isHostJbbsShitaraba($this->host) || P2Util::isHostBe2chNet($this->host)) {
                $body = mb_convert_encoding($body, 'SJIS-win', 'eucJP-win');
            }
            
            if (false === FileCtl::filePutRename($this->setting_txt, $body)) {
                die('Error: cannot write file');
            }
            chmod($this->setting_txt, $perm);
            
            // パースして
            if (!$this->setSettingArrayFromSettingTxt()) {
                return false;
            }
            // srd保存する
            if (!$this->saveSettingSrd($this->setting_array)) {
                return false;
            }
            
        } else {
            // touchすることで更新インターバルが効くので、しばらく再チェックされなくなる
            touch($this->setting_txt);
            // 同時にキャッシュもtouchしないと、setting_txtとsetting_srdで更新時間がずれて、
            // 毎回ここまで処理が来る（サーバへのヘッダリクエストが飛ぶ）場合がある。
            touch($this->setting_srd);
        }

        return true;
    }
    
    
    /**
     * キャッシュが新鮮なら true を返す
     *
     * @acccess  private
     * @return   boolean
     */
    function isSettingSrdCacheFresh()
    {
        if (file_exists($this->setting_srd)) {
            // キャッシュの更新が指定時間以内なら
            // clearstatcache();
            if (filemtime($this->setting_srd) > time() - $this->cache_interval) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * SETTING.TXT をパースしてキャッシュ保存する
     *
     * 成功すれば、$this->setting_array がセットされる
     *
     * @acccess  private
     * @return   boolean  実行成否
     */
    function setSettingArrayFromSettingTxt()
    {
        global $_conf;

        $this->setting_array = array();

        if (!$lines = file($this->setting_txt)) {
            return false;
        }

        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $this->setting_array[$key] = $value;
            }
        }
        $this->setting_array['p2version'] = $_conf['p2version'];

        return true;
    }
    
    /**
     * @access  private
     * @return  boolean
     */
    function saveSettingSrd($setting_array)
    {
        if (false === FileCtl::filePutRename($this->setting_srd, serialize($setting_array))) {
            return false;
        }
        return true;
    }
    
    /**
     * SETTING.TXT のパースデータを読み込む
     *
     * @access  private
     * @return  array
     */
    function readSettingArrayFromSettingSrd()
    {
        global $_conf;
        
        if (!file_exists($this->setting_srd)) {
            //return false;
            return array();
        }

        $setting_array = array();
        if ($cont = file_get_contents($this->setting_srd)) {
            $setting_array = unserialize($cont);
        }
        
        /*
        if ($this->setting_array['p2version'] != $_conf['p2version']) {
            unlink($this->setting_srd);
            unlink($this->setting_txt);
        }
        */
        
        return $setting_array;
    }

}

// }}}

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
