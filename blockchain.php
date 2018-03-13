<?php

class BlockChain{

	protected $current_transaction = [];
	protected $chain = [];
	protected $nodes = [];

	public function __construct(){
		$this->current_transaction = [];
		$this->chain = [];
		$this->nodes = [];

		$this->new_block($proof = 100, $previous_hash='1');
	}

	public function register_node($address){
		$parsed_url = parse_url($address);

		if (is_array($parsed_url)) {
			$this->nodes[] = $parsed_url['host'] . ':' . $parsed_url['port'];
		} else {
			return false;
		}
	}

	public function valid_chain($chain){
		
		$last_block = $chain[0];
		$current_index = 1;
		while ($current_index < count($chain)) {
			$block = $chain[$current_index];
			if($block['previous_hash'] != self::hash($last_block)){
				return false;
			}

			if(!$this->valid_proof($last_block['proof'], $block['proof'], $block['previous_hash'])){
				return false;
			}

			$last_block = $block;
			$current_index += 1;
		}

		return true;
	}

	public function valid_proof($last_proof, $proof, $last_hash){
		$guess = $last_proof . $proof . $last_hash;
		$guess_hash = hash('sha256', $guess);
		return substr($guess_hash, 0, 4) == '0000';
	}

	public function resolve_conflicts(){
		$neighbour = $this->nodes;
		$new_chain = null;

		$max_length = count($this->chain);
		foreach ($neighbour as $node) {
			$response = $this->request("http://{$node}/chain");
			$response = json_decode($response, true);
			
			if(is_array($response)){
				$length = $response['length'];
				$chain = $response['chain'];
				if($length > $max_length && self::valid_chain($chain)){
					$max_length = $length;
					$new_chain = $chain;
				}
			}
		}

		if(is_array($new_chain)){
			$this->chain = $new_chain;
			return true;
		}

		return false;
	}

	public function request($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$res = curl_exec($ch);
		return $res;
	}

	public function new_block($proof, $previous_hash){
		$block = [
			'index' => count($this->chain) + 1,
			'timestamp' => time(),
			'transaction' => $this->current_transaction,
			'proof' => $proof,
			'previous_hash' => $previous_hash ?: self::hash(end($this->chain))
		];

		$this->current_transaction = [];
		$this->chain[] = $block;

		return $block;
	}

	public function new_transaction($sender, $recipient, $amount){
		$this->current_transaction[] = [
			'sender' => $sender,
			'recipient' => $recipient,
			'amount' => $amount,
		];

		$last_block = $this->last_block();
		return $last_block['index'] + 1;
	}

	public function last_block(){
		return end($this->chain);
	}

	public static function hash($block){
		$block_string = json_encode($block);
		return hash('sha256', $block_string);
	}

	public function proof_of_work($last_block){
		$last_proof = $last_block['proof'];
		$last_hash = self::hash($last_block);
		$proof = 0;
		while ($this->valid_proof($last_proof, $proof, $last_hash) == false) {
			$proof += 1;
		}

		return $proof;
	}

	public function __get($name){
		return $this->$name;
	}

	public function __set($name, $value){
		$this->$name = $value;
	}

	public function uuid(){
		$uuid = $this->gen_uuid();
		return str_replace('-', '', $uuid);
	}

	private function gen_uuid(){
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff)| 0x4000,
			mt_rand(0, 0x3fff)| 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),mt_rand(0, 0xffff)
		);
	}

}