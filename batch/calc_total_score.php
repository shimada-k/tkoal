<?php
require_once '../facebook/src/facebook.php';
require_once '../model_lizard.php';


class Lizard{

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


	// スコアを再計算するメソッド
	public function refreshScore(){
		// ローカルのDBからpost_idのリストを取得する
		$post_ids = $this->con->getUserPostIds($this->user_id);

		//var_dump($post_ids);

		$comment_num = 0;
		$like_num = 0;

		//$id = '100001909609525_396779460395704';

		foreach($post_ids as $id){

			$comment_num = 0;
			$like_num = 0;

			$query = 'SELECT comments, likes 
				FROM stream WHERE post_id = \'' . $id['post_id'] . '\'';

			$result = $this->facebook->api(array(
				'method' => 'fql.query',
				'query' => $query,
				'access_token' => $this->access_token
			));

			// 自分以外からのコメント数を取得する
			foreach($result[0]['comments']['comment_list'] as $comment){
				if($comment['fromid'] != $this->user_id){
					$comment_num += 1;
				}
			}

			// 自分以外からのいいね数を取得する
			$like_num = $result[0]['likes']['count'];

			if($result[0]['likes']['user_likes'] == TRUE){
				$like_num -= 1;
			}

			$total_score = $like_num + $comment_num * 2;
			//print 'コメント数:' . $comment_num . 
			//		'いいね数:' . $like_num . 
			//		'トータル:' . $total_score  . '<br />';

			$this->con->refreshScore($id['post_id'], $total_score);
		}
	}

	// 王の威厳に表示するスコアを計算するメソッド
	private function getScoreSum($uid){
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
}

$lizard = new Lizard();

?>
