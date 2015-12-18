# OriginStampOrg - PHP
PHP Handler for OriginStamp.org

Created by Nicolas Giese, published under MIT License. Requires CURL for PHP & OriginStamp.org API key

See http://www.originstamp.org/developer for further details & the api key 

**Methods**
*	Initialization($api_key)
* 	getHash($mixed) - Generates a SHA256 Hash from text or path
*	submitHash($hash, $title = "", $email = "") - Submits a hash to the website to be queued for the integration into the block chain. A hash can only be added once.
* 	verifyHash($hash) - Does a fast verification of a hash. This will work right after submission but is not evidence proof.
* 	getHashEvidence($hash) - Parses the detailed informations on the website to retrieve all data required for a hash to be verified independantly and evidence proof.
* 	getKeyStats() - Parses the website to get informations about the usage status of the api key. Should be called from time to time to check if the api key is almost empty.
