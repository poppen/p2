<?php
/*
    rep2 - デザイン用 設定ファイル

    コメント冒頭の() 内はデフォルト値
    設定は style/*_css.inc と連動
    
    このファイル内での設定は、お好みに応じて変更してください
    iPhoneのスレ表示の設定は、iui/read.cssを直接編集
*/

// _setStyle()
// global $_conf, $STYLE, $MYSTYLE

// リンクに下線を（つける:0, つけない:1, スレタイトル一覧だけつけない:2）
$STYLE['a_underline_none'] = '2'; // ('2')

// {{{ フォント

// 一部の項目は、「ユーザ設定編集」で設定されていれば、そちらの値が優先される

if (strstr(geti($_SERVER['HTTP_USER_AGENT']), 'Mac')) {

	// Safari等の WebKitを使っているものなら
	if (strstr(geti($_SERVER['HTTP_USER_AGENT']), 'AppleWebKit')) {
		$STYLE['fontfamily'] = "Hiragino Kaku Gothic Pro"; // ("Hiragino Kaku Gothic Pro") 基本のフォント
		$STYLE['fontfamily_bold'] = ""; // ("") 基本ボールド用フォント
	} else {
		$STYLE['fontfamily'] = "ヒラギノ角ゴ Pro W3"; // ("ヒラギノ角ゴ Pro W3") 基本のフォント
		$STYLE['fontfamily_bold'] = "ヒラギノ角ゴ Pro W6"; // ("ヒラギノ角ゴ Pro W6") 基本ボールド用フォント（普通に太字にしたい場合は指定しない("")）
	}

	// Mac用フォントサイズ
	$STYLE['fontsize']			= "92%";    // ("12px") 基本フォントの大きさ
	$STYLE['menu_fontsize'] 	= "83%";	// ("11px") 板メニューのフォントの大きさ
	$STYLE['sb_fontsize'] 		= "83%";	// ("11px") スレ一覧のフォントの大きさ
	$STYLE['read_fontsize'] 	= "";		// ("12px") スレッド内容表示のフォントの大きさ
	$STYLE['respop_fontsize'] 	= "83%";	// ("11px") 引用レスポップアップ表示のフォントの大きさ
	$STYLE['infowin_fontsize'] 	= "83%";	// ("11px") 情報ウィンドウのフォントの大きさ
	$STYLE['form_fontsize'] 	= "";		// ("11px") input, option, select のフォントの大きさ（Caminoを除く）
	
	$MYSTYLE['read']['.thread_title']['font-size'] = "14pt";	// ("14pt") スレッドタイトルのフォントの大きさ

} else {

	// Mac以外のフォントサイズ
	$STYLE['fontsize']			= "92%";    // ("") 基本フォントの大きさ
	$STYLE['menu_fontsize'] 	= "83%";    // ("12px") 板メニューのフォントの大きさ
	$STYLE['sb_fontsize'] 		= "83%"; 	// ("12px") スレ一覧のフォントの大きさ
	$STYLE['read_fontsize'] 	= ""; 		// ("") スレッド内容表示のフォントの大きさ
	$STYLE['respop_fontsize'] 	= "83%"; 	// ("12px") 引用レスポップアップ表示のフォントの大きさ
	$STYLE['infowin_fontsize'] 	= "83%"; 	// ("12px") 情報ウィンドウのフォントの大きさ
	$STYLE['form_fontsize'] 	= ""; 		// ("12px") input, option, select のフォントの大きさ
	
	$MYSTYLE['read']['.thread_title']['font-size'] = "";	// ("") スレッドタイトルのフォントの大きさ
}

// 以下の項目は、「ユーザ設定編集」で設定されていれば、そちらを優先して上書きする
// strip_tags()は念のため入れている
$_conf['fontsize']      and $STYLE['fontsize']      = strip_tags($_conf['fontsize']);
$_conf['menu_fontsize'] and $STYLE['menu_fontsize'] = strip_tags($_conf['menu_fontsize']);
$_conf['sb_fontsize']   and $STYLE['sb_fontsize']   = strip_tags($_conf['sb_fontsize']);
$_conf['read_fontsize'] and $STYLE['read_fontsize'] = strip_tags($_conf['read_fontsize']);

// }}}

//======================================================================
// 色彩の設定
//======================================================================
// 無指定("")はブラウザのデフォルト色、または基本指定となります。
// 優先度は、個別ページ指定 → 基本指定 → 使用ブラウザのデフォルト指定 です。

// 基本(style) =======================
$STYLE['bgcolor'] = "#ffffff";	// ("#ffffff") 基本 背景色
$STYLE['textcolor'] = "#000";	// ("#000") 基本 テキスト色
$STYLE['acolor'] = "";	// ("") 基本 リンク色
$STYLE['acolor_v'] = "";	// ("") 基本 訪問済みリンク色。
$STYLE['acolor_h'] = "#09c";	// ("#09c") 基本 マウスオーバー時のリンク色

$STYLE['fav_color'] = "#999";	// ("#999") お気にマークの色

// メニュー(menu) ====================
$STYLE['menu_bgcolor'] = "#fff";	// ("#fff") menu 背景色
$STYLE['menu_background'] = ''; // ('') menu 背景画像
$STYLE['menu_color'] = "#000";	// ("#000") menu テキスト色
$STYLE['menu_cate_color'] = "#333";	// ("#333") menu カテゴリーの色

$STYLE['menu_acolor_h'] = "#09c";	// ("#09c") menu マウスオーバー時のリンク色

$STYLE['menu_ita_color'] = "";	// ("") menu 板 リンク色
$STYLE['menu_ita_color_v'] = "";	// ("") menu 板 訪問済みリンク色
$STYLE['menu_ita_color_h'] = "#09c";	// ("#09c") menu 板 マウスオーバー時のリンク色

$STYLE['menu_newthre_color'] = "hotpink";	// ("hotpink") menu 新規スレッド数の色
$STYLE['menu_newres_color'] = "#ff3300";	// ("#ff3300") menu 新着レス数の色

// スレ一覧(subject) ====================
$STYLE['sb_bgcolor'] = "#fff"; // ("#fff") subject 背景色
$STYLE['sb_color'] = "#000";  // ("#000") subject テキスト色

$STYLE['sb_acolor'] = "#000"; // ("#000") subject リンク色
$STYLE['sb_acolor_v'] = "#000"; // ("#000") subject 訪問済みリンク色
$STYLE['sb_acolor_h'] = "#09c"; // ("#09c") subject マウスオーバー時のリンク色

$STYLE['sb_th_bgcolor'] = "#d6e7ff"; // ("#d6e7ff") subject テーブルヘッダ背景色
$STYLE['sb_tbgcolor'] = "#fff"; // ("#fff") subject テーブル内背景色0
$STYLE['sb_tbgcolor1'] = "#eef"; // ("#eef") subject テーブル内背景色1

$STYLE['sb_ttcolor'] = "#333"; // ("#333") subject テーブル内 テキスト色
$STYLE['sb_tacolor'] = "#000"; // ("#000") subject テーブル内 リンク色
$STYLE['sb_tacolor_h'] = "#09c"; // ("#09c")subject テーブル内 マウスオーバー時のリンク色

$STYLE['sb_order_color'] = "#111"; // ("#111") スレ一覧の番号 リンク色

$STYLE['thre_title_color'] = "#000"; // ("#000") subject スレタイトル リンク色
$STYLE['thre_title_color_v'] = "#999"; // ("#999") subject スレタイトル 訪問済みリンク色
$STYLE['thre_title_color_h'] = "#09c"; // ("#09c") subject スレタイトル マウスオーバー時のリンク色

$STYLE['sb_tool_bgcolor'] = "#8cb5ff"; // ("#8cb5ff") subject ツールバーの背景色
$STYLE['sb_tool_border_color'] = "#6393ef"; // ("#6393ef") subject ツールバーのボーダー色
$STYLE['sb_tool_color'] = "#d6e7ff"; // ("#d6e7ff") subject ツールバー内 文字色
$STYLE['sb_tool_acolor'] = "#d6e7ff"; // ("#d6e7ff") subject ツールバー内 リンク色
$STYLE['sb_tool_acolor_v'] = "#d6e7ff"; // ("#d6e7ff") subject ツールバー内 訪問済みリンク色
$STYLE['sb_tool_acolor_h'] = "#fff"; // ("#fff") subject ツールバー内 マウスオーバー時のリンク色
$STYLE['sb_tool_sepa_color'] = "#000"; // ("#000") subject ツールバー内 セパレータ文字色

$STYLE['sb_now_sort_color'] = "#1144aa";	// ("#1144aa") subject 現在のソート色

$STYLE['sb_thre_title_new_color'] = "red";	// ("red") subject 新規スレタイトルの色

$STYLE['sb_tool_newres_color'] = "#ff3300"; // ("#ff3300") subject ツールバー内 新規レス数の色
$STYLE['sb_newres_color'] = "#ff3300"; // ("#ff3300") subject 新着レス数の色


// スレ内容(read) ====================
$STYLE['read_bgcolor'] = "#efefef"; // ("#efefef") スレッド表示の背景色
$STYLE['read_color'] = "#000"; // ("#000") スレッド表示のテキスト色

$STYLE['read_acolor'] = ""; // ("") スレッド表示 リンク色
$STYLE['read_acolor_v'] = ""; // ("") スレッド表示 訪問済みリンク色
$STYLE['read_acolor_h'] = "#09c"; // ("#09c") スレッド表示 マウスオーバー時のリンク色

$STYLE['read_newres_color'] = "#ff3300"; // ("#ff3300")  新着レス番の色

$STYLE['read_thread_title_color'] = "#f40"; // ("#f40") スレッドタイトル色
$STYLE['read_name_color'] = "#1144aa"; // ("#1144aa") 投稿者の名前の色
$STYLE['read_mail_color'] = ""; // ("") 投稿者のmailの色 ex)"#a00000"
$STYLE['read_mail_sage_color'] = ""; // ("") sageの時の投稿者のmailの色 ex)"#00b000"
$STYLE['read_ngword'] = "#bbbbbb"; // ("#bbbbbb") NGワードの色

// 携帯用
// 「ユーザ設定編集」で設定されていれば、そちらの値が優先される
$STYLE['read_k_thread_title_color'] = '#1144aa'; // ('#1144aa') スレッドタイトル色
$STYLE['k_bgcolor']  = ''; // ('') 携帯、基本背景色
$STYLE['k_color']    = ''; // ('') 携帯、基本テキスト色
$STYLE['k_acolor']   = ''; // ('') 携帯、基本リンク色
$STYLE['k_acolor_v'] = ''; // ('') 携帯、基本訪問済みリンク色

// 以下の項目は、「ユーザ設定編集」で設定されていれば、そちらを優先して上書きする
// strip_tags()は念のため入れている
$_conf['read_k_thread_title_color'] and $STYLE['read_k_thread_title_color'] = strip_tags($_conf['read_k_thread_title_color']); 
$_conf['k_bgcolor']  and $STYLE['k_bgcolor']  = strip_tags($_conf['k_bgcolor']);
$_conf['k_color']    and $STYLE['k_color']    = strip_tags($_conf['k_color']);
$_conf['k_acolor']   and $STYLE['k_acolor']   = strip_tags($_conf['k_acolor']);
$_conf['k_acolor_v'] and $STYLE['k_acolor_v'] = strip_tags($_conf[k_acolor_v]);

// レス書き込みフォーム
$STYLE['post_pop_size'] = "620,360"; // ("620,360") レス書き込みポップアップウィンドウの大きさ（横,縦）

$STYLE['post_msg_cols'] = 70; // (70) レス書き込みフォーム、メッセージフィールドの桁数。PC表示用。
$STYLE['post_msg_rows'] = 10; // (10) レス書き込みフォーム、メッセージフィールドの行数。PC表示用。
// ※携帯では、$_conf['k_post_msg_cols'], $_conf['k_post_msg_rows'] の設定が利用される。

// レスポップアップ
$STYLE['respop_color'] = "#000"; // ("#000") レスポップアップのテキスト色
$STYLE['respop_bgcolor'] = "#ffffcc"; // ("#ffffcc") レスポップアップの背景色
$STYLE['respop_background'] = ""; // ("") レスポップアップの背景画像
$STYLE['respop_b_width'] = "1px"; // ("1px") レスポップアップのボーダー幅
$STYLE['respop_b_color'] = "black"; // ("black") レスポップアップのボーダー色
$STYLE['respop_b_style'] = "solid"; // ("solid") レスポップアップのボーダー形式

$STYLE['info_pop_size'] = "600,430"; // ("600,400") 情報ポップアップウィンドウの大きさ（横,縦）


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
