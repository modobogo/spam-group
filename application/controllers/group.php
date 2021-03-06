<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Group extends MY_Controller {
	/**
	 *
	 * @var GroupModel
	 */
	var $GroupModel; //GroupModel
	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see http://codeigniter.com/user_guide/general/urls.html
	 */
	public function index()
	{
		$gids = $this->getGids();
		$this->load->view('welcome_message',
			Array(
				"pageTitle" => "Facebook 廣告社團檢查器",
				"fbgids" => $gids,
				"selector" => "check"
			)
		);
	}

	private function getGids(){
		$this->load->driver('cache');
		if (!$this->cache->file->is_supported()){
			return $this->GroupModel->getGIDs();
		}
		$CACHE_ID = "Gids";
		$data = $this->cache->file->get($CACHE_ID);
		if($data != false){
			return $data;
		}
		$result = $this->GroupModel->getGIDs();
		$this->cache->file->save($CACHE_ID, $result, 600);
 		return $result;
	}

	public function groups($type="web"){
		$gids = $this->getGids();

		if($type == "json"){
			echo json_encode($gids);
			return true;
		}
		if($type == "jsonp"){
			$jsonp = $this->input->get("jsonp");
			if(!empty($jsonp)){
				echo $jsonp."(".json_encode($gids).")";
			}else{
				echo json_encode($gids);
			}
			return true;
		}


		$this->load->view('group_list',
			Array(
				"pageTitle" => "已認定 Facebook 廣告社團列表",
				"fbgids" => $gids,
				"selector" => "group"
			)
		);
	}

	public function confirming($type="web"){
		$gids = $this->GroupModel->getConfirmingGIDs();

		$this->load->view('group_confirm_list',
			Array(
				"pageTitle" => "審核中 Facebook 廣告社團列表",
				"fbgids" => $gids,
				"selector" => "group"
			)
		);
	}

	public function admin_login(){
		try{
			$token = $this->input->get("access_token");
			$items = $this->get("https://graph.facebook.com/me?access_token=".($token));
			$obj = json_decode($items);

			$admins = $this->config->item('admins');
			if(@isset($admins[$obj->id])){
				$_SESSION["admin"] = $obj->id;
				echo json_encode(Array("IsSuccess" => true));
			}else{
				echo json_encode(Array("IsSuccess" => false));
			}
			return true;
		}catch(Exception $ex){
			echo json_encode(Array("IsSuccess" => false));
			return true;
		}
	}

	public function js_confirming(){
		if(!isset($_SESSION["admin"])){
			echo json_encode(Array("IsSuccess" => false));
			return false;
		}

		$gid = $this->input->post("gid");

		$groupid = $this->GroupModel->confirm($gid,$_SESSION["admin"]);
		echo json_encode(Array("IsSuccess" => true));
	}

	private function get($url){
		$options = array (CURLOPT_RETURNTRANSFER => true, // return web page
		CURLOPT_FOLLOWLOCATION => true, // follow redirects
		CURLOPT_ENCODING => "", // handle compressed
		CURLOPT_AUTOREFERER => true, // set referer on redirect
		CURLOPT_CONNECTTIMEOUT => 120, // timeout on connect
		CURLOPT_TIMEOUT => 120, // timeout on response
		CURLOPT_MAXREDIRS => 10 ); // stop after 10 redirects

		$ch = curl_init ( $url );
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);
  		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt_array ( $ch, $options );

//		curl_setopt($ch,CURLOPT_POST, true);
//		curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($fields));

		//execute postc
		return curl_exec($ch);
	}

	public function report(){
		$gurl = $this->input->get("gurl");
		$this->load->view('report',
			Array(
				"pageTitle" => "回報 Facebook 廣告社團",
				"selector" => "report",
				"gurl" => $gurl
			)
		);
	}

	public function js_report_gid(){
		$gid = $this->input->get("gid");
		$group = $this->GroupModel->find_by_gid($gid);
		echo json_encode($group);
	}

	public function js_report_group(){
		$gid = $this->input->post("gid");
		$name = $this->input->post("name");
		$uid = $this->input->post("uid");
		$uname = $this->input->post("uname");
		$privacy = $this->input->post("privacy");

		$groupid = $this->GroupModel->insert_report(
			Array(
				"Name" => $name ,
				"GID" => $gid,
				"Type" => $privacy,
				"ReporterFBUID" => $uid,
				"Reporter" => $uname
		));

		if($groupid == -1){
			echo json_encode(Array("IsSuccess" => false, "ErrorCode" => 2 , "ErrorMessage" => "新增群組時發生意外錯誤" ));
			return false;
		}
		echo json_encode(Array("IsSuccess" => true, "Data" => $groupid));
	}
	public function js_insert_group(){
//		die('{"IsSuccess":true,"Data":119}');

		$gid = $this->input->post("gid");
		$name = $this->input->post("name");
		$uid = $this->input->post("uid");
		$uname = $this->input->post("uname");
		$privacy = $this->input->post("privacy");

		$groupid = $this->GroupModel->insert(
			Array(
				"Name" => $name ,
				"GID" => $gid,
				"Type" => $privacy,
				"Enabled" => false,
				"ReporterFBUID" => $uid,
				"Reporter" => $uname
		));

		if($groupid == -1){
			echo json_encode(Array("IsSuccess" => false, "ErrorCode" => 2 , "ErrorMessage" => "新增群組時發生意外錯誤" ));
			return false;
		}
		echo json_encode(Array("IsSuccess" => true, "Data" => $groupid));
		//ReporterFBUID
	}

	public function privacy(){
		echo "本應用程式將取得您的社團清單與您的朋友的社團清單，以為您檢查是否有加入惡意社團清單。";
	}
}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */