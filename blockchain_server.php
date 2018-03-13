<?php

require_once 'vendor/autoload.php';
require_once 'blockchain.php';

use Workerman\Worker;

$host = '127.0.0.1';
$port = $argv['3'];
$bc_worker = new Worker("http://{$host}:{$port}");
$blockchain = new BlockChain();
$bc_worker->onConnect = function($connection){};
$bc_worker->count = 1;
$bc_worker->onMessage = function($connection, $data){
	global $blockchain;
	$method = $data['server']['REQUEST_METHOD'];
	$uri = $data['server']['REQUEST_URI'];
	switch ($uri) {
		case '/mine':
			mine($connection);
			break;
		case '/chain':
			full_chain($connection);
			break;
		case '/transaction/new':
			new_transaction($connection, $post);
			break;
		case '/nodes/register':
			register_nodes($connection, $data['post']);
			break;
		case '/nodes/resolve':
			consensus($connection);
			break;
		default:
			# default
			break;
	}
};

function mine($connection){
	global $blockchain;
	$node_identifier = $blockchain->uuid();
	$last_block = $blockchain->last_block();
	$proof = $blockchain->proof_of_work($last_block);
	$result = $blockchain->new_transaction($sender='0', $recipient=$node_identifier, $amount=1);
	$previous_hash = $blockchain->hash($last_block);
	$block = $blockchain->new_block($proof, $previous_hash);
	$response = [
		'message' => "New Block Froged",
		'index' => $block['index'],
		'transactions' => $block['transaction'],
		'proof' => $block['proof'],
		'previous_hash' => $block['previous_hash'],
	];

	$new_block = json_encode($response);
	$connection->send($new_block);
}

function full_chain($connection){
	global $blockchain;
	$response = [
		'chain' => $blockchain->chain,
		'length' => count($blockchain->chain),
	];
	$response = json_encode($response);
	$connection->send($response);
}

function new_transaction($connection,$params){
	$required = ['sender', 'recipient', 'amount'];
	if(!isset($params['sender']) || !isset($params['recipient']) || !isset($params['amount'])){
		$response = 'Missing values';
		$connection->send($response);
	}else{
		$index = $blockchain->new_transaction($params['sender'], $params['recipient'], $params['amount']);
		$response = [
			'message' => 'Transaction will be added to Blcok {$index}',
		];
		$response = json_encode($response);
		$connection->send($response);
	}
}

function register_nodes($connection, $params){
	global $blockchain;
	if(!isset($params['nodes'])){
		$response = 'Error: Please supply a valid list of nodes';
		$connection->send($response);
	}
	$nodes = $params['nodes'];
	foreach ($nodes as $node) {
		$blockchain->register_node($node);
	}
	$nodes = $blockchain->nodes;
	$response = [
		'message' => 'New nodes have been added',
		'total_nodes' => $blockchain->nodes,
	];
	$response = json_encode($response);
	$connection->send($response);	

}

function consensus($connection){
	global $blockchain;
	$replaced = $blockchain->resolve_conflicts();

	if ($replaced) {
		$response = [
			'message' => 'Our chain was replaced',
			'new_chain' => $blockchain->chain,
		];
	} else {
		$response = [
			'message' => 'Our chain is authoritative',
			'chain' => $blockchain->chain,
		];
	}
	$response = json_encode($response);
	$connection->send($response);
}


$bc_worker::runAll();