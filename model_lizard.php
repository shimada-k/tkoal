<?php

class Lizard_DB {

	private $link;

    function __construct($host, $user, $pass, $db){

        // MySQLへ接続する
        $this->link = mysql_connect($host, $user, $pass);
		$this->link ? NULL : exit('MySQLへの接続に失敗しました');
	
	    // 文字コードを設定する
		mysql_set_charset('utf8');

		// データベースを選択する
		$sdb = mysql_select_db($db, $this->link);
		$sdb ? NULL : exit('データベースの選択に失敗しました');
	}

	// mysql_result型の変数$dataを連想配列の配列に変換するメソッド
    public function toAssoc($data){
		$array_assoc = array();

        while($row = mysql_fetch_assoc($data)){
	        $array_assoc[] = $row;
        }
		return $array_assoc;
	}

	// ユーザすべてのリストを返すメソッド
	public function getUsers(){
		$query = "SELECT * FROM user";
		$result = mysql_query($query, $this->link);

		//var_dump($this->toAssoc($result));

		return $this->toAssoc($result);
	}

	// uidのユーザのpost_id一覧を取得するメソッド
	public function getUserPostIds($uid){
		$query = "SELECT post_id FROM comment_from_app WHERE uid = "
			. $uid;
		$result = mysql_query($query, $this->link);
		return $this->toAssoc($result);
	}

	// 投稿のスコアの更新をするメソッド
	public function refreshScore($post_id, $score){
		$query = 'UPDATE comment_from_app SET score = ' . $score
				. ' WHERE post_id = \'' . $post_id . '\'';
		//print $query;
		$result = mysql_query($query, $this->link);
	}

	// ユーザの投稿のスコアのリストを取得するメソッド
	public function getUserScores($uid){
		$query = "SELECT score FROM comment_from_app WHERE uid = " . $uid;
		$result = mysql_query($query, $this->link);

		return $this->toAssoc($result);
	}

	// uidのユーザの最新のコメントを取得するメソッド
	public function getLatestComment($uid){
		$query = "SELECT * FROM comment_from_app WHERE created_at = (SELECT MAX(created_at) FROM comment_from_app WHERE uid = " . $uid . ");";
		$result = mysql_query($query, $this->link);
		return $this->toAssoc($result);
	}

	// コメントをDBに格納するメソッド
	public function insertComment($post_id, $uid, $message){
		$query = 'INSERT INTO comment_from_app (post_id, uid, comment, created_at) values('
		 . '\'' . $post_id . '\',' . $uid . ', \'' . $message . '\', \'' . date('Y-m-d H:i:s'). '\')';

		$result = mysql_query($query, $this->link);
	}

	// ユーザ情報をDBに登録するメソッド
	public function registerUid($uid){
		// すでに登録済みかどうかチェックする
		$query = 'SELECT * FROM user WHERE uid = ' . $uid;
		$result = mysql_query($query, $this->link);

		$result_assoc = $this->toAssoc($result);

		if(empty($result_assoc)){
			$query = 'INSERT INTO user (uid) values (' . $uid .')';
			$result = mysql_query($query, $this->link);
		}
		else{
			//print 'すでに登録済みです';
		}
	}

	// 報酬受け取りログを刺す
	public function insertRewardLog($uid, $reward_id){
		$query = 'INSERT INTO owning_reward (uid, reward_id) values(' . $uid .
					', ' . $reward_id . ')';
		$result = mysql_query($query, $this->link);
	}

	// 受け取った報酬IDをすでに所持しているかどうか判定するメソッド
	public function rewardIsGot($uid, $reward_id){
		$query = 'SELECT * from owning_reward WHERE uid = ' . $uid . 
		' AND reward_id = ' . $reward_id;

		$result = mysql_query($query, $this->link);
		$result_assoc = $this->toAssoc($result);

		//echo "rewardIsGot()<br />";
		//var_dump($result_assoc);
		//echo "<br />";

		// 受け取っていれば真を返す
		return !empty($result_assoc);
	}

	// 直近の報酬オブジェクトを1つだけ返すメソッド
	public function getLatestReward($total_score){

		//echo $total_score . '<br />';
		// $total_scoreより少ないスコアで取得できる報酬一覧を降順で取得
		$query = 'SELECT * FROM reward WHERE require_lp <= ' 
				. $total_score . ' ORDER BY require_lp DESC';
		$result = mysql_query($query, $this->link);
		$result_assoc = $this->toAssoc($result);

		if(empty($result_assoc)){
			return NULL;
		}
		else{

			//echo "getLatestReward() <br />";
			//var_dump($result_assoc);
			//echo "<br />";

			return $result_assoc[0];
		}
	}

    function __destruct(){
        // MySQLへの接続を閉じる
        if(!mysql_close($this->link))
            exit("MySQL切断に失敗しました。");
	}
}

?>
