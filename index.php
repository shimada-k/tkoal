<?php

require_once './facebook/src/facebook.php';
require_once './slim/vendor/autoload.php';
require_once './smarty/libs/Smarty.class.php';
require_once './model_lizard.php';
require_once './signed_request.php';


class Lizard{

    private $smarty;
    private $facebook;
    private $user_id;
	private $con;
	private $access_token;

	const appId = '113558872127430';
	const secret = '736835d25bdca659eed08bf990fd14fc';
	const app_url = 'http://apps.facebook.com/113558872127430/';

    // コンストラクタ
    function __construct(){

	    $this->facebook = new Facebook(array(
		    'appId' => self::appId,
            'secret' => self::secret,
		));

		$this->user_id = $this->facebook->getUser();
		$this->smarty = new Smarty();

		if($this->user_id == NULL){
        	//ログインURL取得
	    	$par = array('scope' => 'read_stream, 
						publish_stream, 
						read_friendlists');
		    $fb_login_url = $this->facebook->getLoginUrl($par);

			if($this->access_token ==  NULL){
				$this->smarty->assign('redirect_url', $fb_login_url);
				$this->smarty->display('redirect.tpl');
				$this->access_token = $this->facebook->getAccessToken();
			}
			else{
				$this->smarty->display('start.tpl');
			}
		}

		$this->con = new Lizard_DB('127.0.0.1', 'root',
									'nbiaReh7', 'lizard');
	}

	// $urlにリダイレクトさせるメソッド
	public function __redirect($url){
		$this->smarty->assign('redirect_url', $url);
		$this->smarty->display('redirect.tpl');
	}

	// uid取得用のメソッド
	public function getUid(){
		return $this->user_id;
	}

	// 初回時にuidをDBに登録するメソッド
	public function registerUser(){
		$this->con->registerUid($this->user_id);
	}

	// 原語表記のユーザ名を取得するメソッド
	private function getUsernameJp($uid){
		$query = 'select name from user where uid = ' . $uid;

		$result = $this->facebook->api(array(
			'method' => 'fql.query',
			'query' => $query,
			'access_token' => $this->access_token
		));

		return $result[0]['name'];
	}

	// 英語表記のユーザ名を取得するメソッド
	public function getUsernameEng($uid){

		// グラフAPI使用
		//$url = '/' . $uid . '&locale=en_US';
		$url = '/' . $uid;
		$result = $this->facebook->api($url, 'GET');

		//var_dump($result['name']);
	
		return $result['name'];
	}

	// uidのユーザのプロフィール画像のURLを取得するメソッド
	private function getImageSquare($size, $uid){
		$query = 
			'SELECT url FROM profile_pic WHERE width = '. $size .
			' AND height = ' . $size .
			' AND id = ' . $uid;

		$result = $this->facebook->api(array(
			'method' => 'fql.query',
			'query' => $query,
			'access_token' => $this->access_token
		));

		return $result[0]['url'];
	}

	// 謀反者のuidとプロフィールURLを返すメソッド
	private function searchRebellion(){
		$rebellions = array();
		$ret = array();

		$query = 'SELECT uid2 FROM friend WHERE uid1 = me()';

		$result = $this->facebook->api(array(
			'method' => 'fql.query',
			'query' => $query,
			'access_token' => $this->access_token
		));

		$lizard_users = $this->con->getUsers();

		foreach($result as $friend){
			foreach($lizard_users as $luser){
				if($friend['uid2'] == $luser['uid']){
					$rebellions[] = array('uid' => $luser['uid'], 'url' => NULL);
				}
			}
		}
		//var_dump($rebellions);

		foreach($rebellions as $rebe){
			$query = 'SELECT url FROM profile WHERE id = ' . $rebe['uid'];

			$result = $this->facebook->api(array(
				'method' => 'fql.query',
				'query' => $query,
				'access_token' => $this->access_token
			));

			$rebe['url'] = $result[0]['url'];
			$ret[] = $rebe;
		}

		// 画面に表示できるのは３つまでなので、ランダムに3つ返す
		if(count($ret) > 3){
			$rd_keys = array_rand($ret, 3);
			$rd_ret = array();
			
			for($i = 0; $i < 3; $i++){
				$rd_ret[] = $ret[$rd_keys[$i]];
			}
			return $rd_ret;
		}
		return $ret;
	}

	// スコアを再計算するメソッド
	public function refreshScore($uid){
		// ローカルのDBからpost_idのリストを取得する
		$post_ids = $this->con->getUserPostIds($uid);
		$comment_num = 0;
		$total_score = 0;
		$like_num = 0;

		//var_dump($post_ids);

		foreach($post_ids as $id){

			$comment_num = 0;
			$like_num = 0;

			$query = 'SELECT comments, likes 
				FROM stream WHERE post_id = \'' . $id['post_id'] . '\'';

			//echo $query . '<br />';

			$result = $this->facebook->api(array(
				'method' => 'fql.query',
				'query' => $query,
				'access_token' => $this->access_token
			));

			//var_dump($result);

			// 自分以外からのコメント数を取得する
			foreach($result[0]['comments']['comment_list'] as $comment){
				if($comment['fromid'] != $uid){
					$comment_num += 1;
				}
			}

			// 自分以外からのいいね数を取得する
			$like_num = $result[0]['likes']['count'];

			if($result[0]['likes']['user_likes'] == TRUE){
				$like_num -= 1;
			}

			$total_score = $like_num + $comment_num * 2;
			//echo  'コメント数:' . $comment_num . 
			//		'いいね数:' . $like_num . 
			//		'トータル:' . $total_score  . '<br />';

			// 引数のuidとクラス変数のuidが一致していたらDBに反映する
			if($uid == $this->user_id){
				$this->con->refreshScore($id['post_id'], $total_score);
			}
		}
		return $total_score;
	}

	// 王の威厳に表示するスコアを計算するメソッド
	public function getScoreSum($uid){
		$sum_score = 0;
		$scores = $this->con->getUserScores($uid);

		foreach($scores as $val){
			$sum_score += $val['score'];
		}

		return $sum_score;
	}

	// 最新のコメントを取得するメソッド
	private function latestComment(){
		$comment = $this->con->getLatestComment($this->user_id);

		if(count($comment) > 0){
			// viewに渡す用に改行コードを<br />に置換する
			$comment[0]['comment'] = str_replace("\n", "<br />", $comment[0]['comment']);
		}
		else{
			return array('comment' => '俺はトカゲの王だ。<br /><br />俺は死に直面した時に、生を感じる。', 'created_at' => '1971-07-03 Jim Morrison');
		}

		return $comment[0];
	}

	// ホーム画面表示用メソッド
	public function index(){

		// ビューに渡す配列
		$view_param = array('name_eng' => NULL, 'name_jp' => NULL, 
				'my_image' => NULL, 'sum_score' => 0, 
				'rebellions' => array(
						'num' => NULL, 'obj' => array(),
						'latest_comment' => NULL
				));

		$view_param['name_jp'] = $this->getUsernameJp($this->user_id);
		$view_param['name_eng'] = $this->getUsernameEng($this->user_id);
		$view_param['my_image'] = $this->getImageSquare(200, $this->user_id);

		$view_param['sum_score'] = $this->getScoreSum($this->user_id);
		$view_param['latest_comment'] = $this->latestComment();

		$rebellions = $this->searchRebellion();
		$view_param['rebellions']['num'] = count($rebellions);
		//var_dump($rebellions);

		foreach($rebellions as $r){
			$obj = array();
			$obj['image'] = $this->getImageSquare(200, $r['uid']);
			$obj['url'] = $r['url'];

			$view_param['rebellions']['obj'][] = $obj;
		}

		//var_dump($view_param);
		$this->smarty->assign('param', $view_param);
		$this->smarty->display('index.tpl');
	}

	// コメント送信用のメソッド（自分のウォールに投稿する）
	public function postComment($message){
		$res = $this->facebook->api('/me/feed', 'POST',
			array('message' => $message)
		);

		$this->con->insertComment($res['id'], $this->user_id, $message);
	}

	// コメント送信用のメソッド（自分のウォールに投稿する）
	public function postCommentWithImg($total_score){
		//echo "total_score<br />";
		//echo $total_score;
		//echo "<br />";

		$name_eng = $this->getUsernameEng($this->user_id);
		$reward = $this->con->getLatestReward($total_score);

		//echo "postCommentWithImg()<br/>";
		//var_dump($reward);
		//echo "<br />";

		if($this->con->rewardIsGot($this->user_id, $reward['id'])){
			//echo "すでに受け取っています<br />";
			return NULL;
		}

		// 報酬受け取りログを刺す
		$this->con->insertRewardLog($this->user_id, $reward['id']);

		// 直近の報酬を取得していなかったら以下が実行される
		$message = $name_eng . 'さんはカリスマの象徴「' . $reward['name'] . '」を取得しました';
		$picture = 'http://tkoal.dip.jp/templates/images/reward' . sprintf("%02d", $reward['id']) . '.png';
		$name = $reward['name'];
		$caption = '「お前はトカゲの王だ」';
		$link = self::app_url;

        $res = $this->facebook->api('/me/feed', 'POST',
          array('message' => $message, 'picture' => $picture,
          'name' => $name, 'caption' => $caption, 'link' => $link)
        );
	}

	public function __redirect_img($name){
		$this->smarty->assign('name', $name);
		$this->smarty->display('reward.tpl');
	}
}

$app = new \Slim\Slim();
$lizard = new Lizard();

// トップページへのリクエスト
$app->get('/', function () use($lizard) {
		if(isset($_REQUEST['state'])){
			$lizard->__redirect(Lizard::app_url);
		}
		else if(isset($_REQUEST['image'])){
			$lizard->__redirect_img($_REQUEST['image']);
		}
		else{
			$lizard->index();
		}
})->name("top");


$app->get('/refresh', function () use($lizard) {
		$uid = $lizard->getUid();
		$lizard->refreshScore($uid);

		$total_score = $lizard->getScoreSum($uid);
		//echo $total_score;

		// デバッグ用に条件付け
		// if($uid == 100001909609525 || $uid == 100004733852619){
	    		$lizard->postCommentWithImg($total_score);
		//}

		$lizard->index();
})->name("refresh");

// 投稿がPOSTされたら実行されるメソッド
$app->post('/comment', function() use($lizard) {

		$str = '俺はトカゲの王だ。' . "\n\n" . $_POST['comment'];
		$lizard->postComment($str);
		$lizard->index();
})->name('comment');

// 最初にsigned_requestがPOSTされた時に実行される
$app->post('/', function() use($lizard) {
		// $lizard->post($_POST['message']);
		if (isset($_POST['signed_request'])) {
		    // $data = parse_signed_request($_POST['signed_request'], 'あなたのアプリケーションの秘密鍵');
			// var_dump($data);

			// uidがNULLでなかったらアプリのトップページを取得する
			if($lizard->getUid()){
				$lizard->registerUser();
				$lizard->index();
			}
		}
})->name("post");

$app->run();

?>
