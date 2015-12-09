<?php

/**
 * APNS推送消息类
 * 
 * @author   pengmeng<pengmeng@staff.sina.com.cn>
 * @date     2014-12-25
 * @version  1.0
 */
/* 
//使用示例:
$push = new IOS_Push();
$push->setIsSandBox(true)
	->setLocalcert('xxxx.pem')
	->setPassphrase('passwrord')
	->connect();
if ($push->isSuccess()) {
	//单发
	$push->setDeviceToken('w9mur0987ctr29n84c87ctw9mur0987r29b')
		->push('hello1');
	if (!$push->isSuccess()) {
		echo $push->error();
	}
	//单发
	$push->setDeviceToken('ctr29n84c87cw9mur0987tw9mur0987r29b')
		->push('hello2');
	if (!$push->isSuccess()) {
		echo $push->error();
	}
	//群发
	$push->setDeviceToken(array('ctr29n84c87cw9mur0987tw9mur0987r29b',
		    'w9mur0987ctr29n84c87ctw9mur0987r29b'
		))->push('hello3');
	if (!$push->isSuccess()) {
		echo $push->error();
	}
} else {
	echo $push->error();
}
$push->disconnect();
*/
class IOS_Push {

	//错误信息
	private $error = array();
	//到服务器的socket连接句柄
	private $handle;
	//设备token
	private $deviceToken;
	//本地证书和密码
	private $localcert = '';
	private $passphrase = '';
	//是否沙盒模式
	private $isSandBox = false;

	/**
	 * 获取设备token
	 * @return type
	 */
	function getDeviceToken() {
		return $this->deviceToken;
	}

	/**
	 * 证书路径
	 * @return type
	 */
	function getLocalcert() {
		return $this->localcert;
	}

	/**
	 * 证书密码
	 * @return type
	 */
	function getPassphrase() {
		return $this->passphrase;
	}

	/**
	 * 是否是沙盒子模式
	 * @return type
	 */
	function getIsSandBox() {
		return $this->isSandBox;
	}

	/**
	 * 设置设备token
	 * @param type $deviceToken
	 * @return \IOS_Push
	 */
	function setDeviceToken($deviceToken) {
		$this->deviceToken = $deviceToken;
		return $this;
	}

	/**
	 * 设置证书路径
	 * @param type $localcert
	 * @return \IOS_Push
	 */
	function setLocalcert($localcert) {
		$this->localcert = $localcert;
		return $this;
	}

	/**
	 * 设置证书密码
	 * @param type $passphrase
	 * @return \IOS_Push
	 */
	function setPassphrase($passphrase) {
		$this->passphrase = $passphrase;
		return $this;
	}

	/**
	 * 设置是否是沙盒模式
	 * @param type $isSandBox
	 * @return \IOS_Push
	 */
	function setIsSandBox($isSandBox) {
		$this->isSandBox = $isSandBox;
		return $this;
	}

	/*
	 * 连接apns服务器
	 */

	public function connect() {
		$this->error = array();
		$ctx = stream_context_create();
		stream_context_set_option($ctx, 'ssl', 'local_cert', $this->localcert);
		stream_context_set_option($ctx, 'ssl', 'passphrase', $this->passphrase);
		if ($this->isSandBox) {
			//这个是沙盒测试地址
			$this->handle = stream_socket_client(
				'ssl://gateway.sandbox.push.apple.com:2195', $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx);
		} else {
			//这个为正是的发布地址
			$this->handle = stream_socket_client(
				'ssl://gateway.push.apple.com:2195', $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx);
		}
		if (!$this->handle) {
			$this->error[] = "Failed to connect: $err $errstr" . PHP_EOL;
		}
		return $this;
	}

	/**
	 * 操作是否成功
	 * @return type
	 */
	public function isSuccess() {
		return empty($this->error);
	}

	/**
	 * 错误消息
	 * @return type
	 */
	public function error() {
		return implode(PHP_EOL, $this->error);
	}

	/*
	  功能：生成发送内容并且转化为json格式
	 */

	private function createPayload($message, $badge, $sound) {
		// Create the payload body
		$body['aps'] = array(
		    'alert' => $message,
		    'sound' => $sound,
		    'badge' => $badge
		);

		// Encode the payload as JSON
		$payload = json_encode($body);

		return $payload;
	}

	/**
	 * 推送消息
	 * @param type $message
	 * @param type $badge
	 * @param type $sound
	 * @return \IOS_Push
	 */
	public function push($message, $badge = 2, $sound = 'default') {
		$this->error = array();
		if (is_array($this->deviceToken)) {
			$tokens = $this->deviceToken;
			foreach ($tokens as $token) {
				$this->setDeviceToken($token)
					->_push($message, $badge, $sound);
			}
		} else {
			$this->_push($message, $badge, $sound);
		}
		return $this;
	}

	private function _push($message, $badge = 2, $sound = 'default') {
		// 创建消息
		$payload = $this->createPayload($message, $badge, $sound);
		// Build the binary notification
		$msg = chr(0) . pack('n', 32) . pack('H*', $this->deviceToken) . pack('n', strlen($payload)) . $payload;
		// Send it to the server
		$result = fwrite($this->handle, $msg, strlen($msg));
		if (!$result) {
			$this->error[] = 'Message not delivered,Token:' . $this->deviceToken . PHP_EOL;
		}
		return $this;
	}

	/**
	 * 断开到服务器的连接
	 * @return \IOS_Push
	 */
	public function disconnect() {
		fclose($this->handle);
		return $this;
	}

}
