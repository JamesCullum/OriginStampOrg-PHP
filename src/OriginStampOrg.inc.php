<?php 
/**
* Handler Class for OriginStamp.org, Revision 2
* Created by Nicolas Giese, published under MIT License
* Requires CURL for PHP & OriginStamp.org API key
* See http://www.originstamp.org/developer for further details & the api key 
*
* Methods
* 	Initialization($api_key)
* 	getHash($mixed) - Generates a SHA256 Hash from text or path
*	submitHash($hash, $title = "", $email = "") - Submits a hash to the website to be queued for the integration into the block chain. A hash can only be added once.
* 	verifyHash($hash) - Does a fast verification of a hash. This will work right after submission but is not evidence proof.
* 	getHashEvidence($hash) - Parses the detailed informations on the website to retrieve all data required for a hash to be verified independantly and evidence proof.
* 	getKeyStats() - Parses the website to get informations about the usage status of the api key. Should be called from time to time to check if the api key is almost empty.
*/
class OriginStamp
{
	private $ch, $api_key, $api_url;
	
	/**
	* Initialization
	*
	* @param string $api_key contains the OriginStamp.org API Key
	* @param string $api_url (optional) contains the current API URL
	*/
	public function __construct($api_key, $api_url = 'http://www.originstamp.org/api/stamps')
	{
		if(empty($api_key)) throw new Exception('__construct: API Key required');
		if(!function_exists("curl_init")) throw new Exception('__construct: CURL PHP Plugin required');
		
		$this->api_key = $api_key;
		$this->api_url = $api_url;
		
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
	}
	
	public function __destruct()
	{
		curl_close($this->ch);
	}
	
	/** 
	* getHash
	* Generates a SHA256 Hash from text or path
	*
	* @param string $mixed contains either the path to a file or any other content that is literally processed)
	* @return string
	*/
	public function getHash($mixed)
	{
		if(file_exists($mixed)) return hash_file("sha256", $mixed);
		else return hash("sha256", $mixed);
	}
	
	/** 
	* submitHash
	* Submits a hash to the website to be queued for the integration into the block chain. A hash can only be added once.
	*
	* @param string $hash containing a hash of up to 64 characters (for example sha256)
	* @param string $title (optional) contains the title for the hash, visible during fast verification
	* @param string $email (optional) contains an email address where the creation receipt is sent to
	* @param boolean $email_send (optional) whether or not the email address should get all informations by email
	* @return json
	*/
	public function submitHash($hash, $title = "", $email = "", $email_send = false)
	{
		if(strlen($hash)>64) throw new Exception('submitHash: Hash length exceeds 64 characters');
		$request = array( "hash_sha256" => $hash );
		if(!empty($title)) $request["title"] = $title;
		if(!empty($email)) $request["sender"] = $email;
		if(!empty($email) && $email_send) $request["send_back"] = 1;

		return json_decode( $this->webRequest($this->api_url, json_encode($request) ) );
	}
	
	/** 
	* verifyHash
	* Does a fast verification of a hash. This will work right after submission but is not evidence proof.
	*
	* @param string $hash containing a hash of up to 64 characters (for example sha256)
	* @return json
	*/
	public function verifyHash($hash)
	{
		return json_decode( $this->webRequest($this->api_url.'/'.$hash ) );
	}
	
	/** 
	* getHashEvidence
	* Parses the detailed informations on the website to retrieve all data required for a hash to be verified independantly and evidence proof.
	* Only hashes submitted to the blockchain are evidence proof.
	*
	* @param string $hash containing a hash of up to 64 characters (for example sha256)
	* @return mixed
	* 	Invalid Hash = False
	* 	Hash not submitted to blockchain = array
	* 		isLive = False
	*		timeLeft = Time in seconds until it will be added to the blockchain
	*		temporary = Content of fast verification
	*	Hash submitted to blockchain = array
	*		isLive = True
	*		transaction-url = URL to the transaction in the blockchain
	*		seed = The seed to recreate the hash that was submitted into the blockchain
	*		pki-secret = Secret Key, used for recipient generation
	*		pki-public = Public Key, used for recipient generation
	*		recipient = Recipient of the blockchain transaction
	*/
	public function getHashEvidence($hash)
	{
		$source = $this->webRequest('http://www.originstamp.org/s/'.$hash);
		$matches = array();
		
		if(strpos($source, '<h2>Timestamp Information</h2>')===false) return false;
		elseif(strpos($source, 'The hash hasn\'t been submitted to the Bitcoin blockchain yet.')!==false)
		{
			$timeleft = mktime(23,59,59) + 1 - time();
			return array("isLive" => false, "timeLeft" => $timeleft, "temporary" => $this->verifyHash($hash));
		}
		elseif(preg_match('/<i class=\'fa fa-external-link\'><\/i>\n?<a href="(.*?)">See the corresponding Bitcoin transaction<\/a>.*?<h5.*?>Transaction Seed<\/h5>\n?<pre>([A-Za-z0-9 ]+)<\/pre>.*?<h5.*?>Key-Pair<\/h5>\n?<pre>Secret ([A-Za-z0-9 ]+)<\/pre>\n<pre>Public Key ([A-Za-z0-9 ]+)<\/pre>.*?<h5.*?>Recipient address<\/h5>\n?<div.*?>\n?<strong>([A-Za-z0-9 ]+)<\/strong>/s', $source, $matches))
		{
			return array("isLive" => true, "transaction-url" => $matches[1], "seed" => $matches[2], "pki-secret" => $matches[3], "pki-public" => $matches[4], "recipient" => $matches[5]);
		}
		else throw new Exception('getHashEvidence: Fatal Regex Error');
	}
	
	/**
	* getKeyStats
	* Parses the website to get informations about the usage status of the api key. Should be called from time to time to check if the api key is almost empty.
	*
	* @return mixed
	*	Key invalid = False
	* 	Key Valid = array
	*		used = How many hashes have been submitted by this api key
	*		remaining = How many hashes you can submit before the api key becomes invalid
	*/
	public function getKeyStats()
	{
		$info = $this->webRequest('http://www.originstamp.org/api_keys/'.$this->api_key);
		if(strpos($info, 'Times used')===false) return false;
		$matches = array();
		preg_match('/<strong>Times used<\/strong>\n?<p>\n?(\d+)\n?<\/p>\n?<p>\n?This key expires after creating (.*?) stamps\n?<\/p>\n?<p>\n?Stamps left: ([\d,]+)\n?<\/p>/', $info, $matches);
		if(count($matches) != 3) throw new Exception('getKeyStats: Fatal Regex Error');
		return array("used" => intval($matches[1]), "remaining" => intval(str_replace(",","",$matches[2])));
	}
	
	/**
	* webRequest (internal)
	* Requests a website
	*
	* @param string $url 
	* @param string $postdata 
	* @return string
	*/
	protected function webRequest($url, $postdata = "")
	{
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getWebHeader($postdata));
		curl_setopt($this->ch, CURLOPT_URL, $url);
		if(!empty($postdata))
		{
			curl_setopt($this->ch, CURLOPT_POST, 1);
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postdata);
		}
		else curl_setopt($this->ch, CURLOPT_POST, 0);
		return curl_exec($this->ch);
	}
	
	/** 
	* getWebHeader (internal)
	* Creates the necessary headers for a webRequest
	*
	* @param string $data contains the $postdata
	* @return array
	*/
	private function getWebHeader($data)
	{
		$header = array('Authorization: Token token="'.$this->api_key.'"');
		if(!empty($data))
		{
			$header[] = 'Content-Type: application/json';
			$header[] = 'Content-Length: '.strlen($data);
		}
		return $header;
	}
}
?>