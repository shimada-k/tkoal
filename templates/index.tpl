<?xml version="1.0" encoding="UTF-8" ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<title>お前はトカゲの王だ</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" type="text/css" href="./templates/main.css" />
<link rel="stylesheet" type="text/css" href="./templates/button.css" />

<script type="text/javascript">
	function showPreview() {
		var html = document.getElementById('comment').value;

		html2 = html.replace(/[\n\r]/g, "<br />");  

		if(html2.length > 128){
			html2 = html2.substring(0,129);
		}

		/*
			必要なら html に変換処理を入れる
		*/
		document.getElementById('preview_body').innerHTML = '俺はトカゲの王だ。<br /><br />' + html2;
	}
</script>
</head>

<body>

<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/ja_JP/all.js#xfbml=1&appId=113558872127430";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>

<div id="wrapper">
	<div id="upper">
		<img src={$param['my_image']} />
		<div id="name">
			<p class="headline">{$param['name_eng']}</p>
			<hr /><br />
			<p id="text">
			{$param['latest_comment']['comment']}
			<div id="post_date">
			{$param['latest_comment']['created_at']}
			</div>
		</div>
	</div> <!-- end of upper -->

	<div id="lower">
		<div id="status">
			<p class="lower_head"><img src='./templates/images/lower_icon.png' />&nbsp;王国のステータス</p>
			<hr/>

			<div id="status_upper">
				<p class="status_headline">王の威厳</p>
				<div id='body'>
					スコア：{$param['sum_score']}LP<br /><br />
					<a href="http://tkoal.dip.jp/tkoal/refresh" class="shiny-button"><strong>refreshする</strong><br>いいねとコメントの数から計算します</a>
				</div>
			</div>

			<div id="status_lower">
			<p class="status_headline">謀反者リスト</p>
			{foreach from = $param['rebellions']['obj'] item = obj}
				<a href={$obj['url']} target="_blank"><img src={$obj['image']} /></a>
			{/foreach}
			</div>

		</div> <!-- end of status -->

		<div id="post_form">
			<p class="lower_head"><img src='./templates/images/lower_icon.png' />&nbsp;アプリから投稿</p>
			<hr/>

			<div id="preview">
				<div id="preview_header">
					<img src={$param['my_image']} />
					<p>&nbsp;&nbsp;{$param['name_jp']}</p>
				</div><br />

				<div id ="preview_body">
				</div>

				<div id="preview_footer">
				Preview...
				</div>
			</div> <!-- end of preview -->

			<div id="form_body">
				<form method="post" id="testForm" action="http://tkoal.dip.jp/tkoal/comment">

					<textarea style="height:170px; width:200px;" name="comment" id="comment" onkeyup="showPreview()"></textarea><br />
					<input type="submit" id="btn" >
				</form>

			</div> <!-- end of form_body -->

		</div> <!-- end of post_form -->
	</div> <!-- end of lower -->

	<div id="footer">
		<div class="fb-like" data-href="http://apps.facebook.com/113558872127430/" data-send="true" data-width="740" data-show-faces="true" data-colorscheme="dark">
		</div>
	</div>

</div> <!-- end of wrapper -->

</body>
</html>
